<?php

namespace app\components;

use app\components\rules\CharacterRules;
use app\components\rules\ItemCatalog;
use app\models\InventoryItem;
use app\models\Item;
use app\models\PlayerCharacter;
use Yii;
use yii\db\IntegrityException;

/**
 * Создание персонажей и транзакционное сохранение состояния от игрового сервера.
 */
final class CharacterService
{
    /**
     * Создать персонажа после экрана создания. Сервер пересчитывает HP и прочее сам.
     * @return array [PlayerCharacter|null, errors]
     */
    public static function create(int $userId, array $b): array
    {
        $name = trim((string)($b['name'] ?? ''));
        $stats = $b['stats'] ?? null;
        if (is_array($stats)) $stats = array_map(fn($v) => is_numeric($v) ? (int)$v : $v, array_values($stats));
        $tags = $b['tag_skills'] ?? null;
        $traits = $b['traits'] ?? [];

        $errors = CharacterRules::validateCreation($name, $stats, $tags, $traits);
        if ($errors) return [null, $errors];

        $max = (int)Yii::$app->params['maxCharactersPerAccount'];
        $count = PlayerCharacter::find()->where(['user_account_id' => $userId, 'is_deleted' => false])->count();
        if ($count >= $max) return [null, ['name' => ["Не более $max персонажей на аккаунт"]]];
        if (PlayerCharacter::isNameTaken($name)) return [null, ['name' => ['Имя уже занято']]];

        $derived = CharacterRules::derived($stats, $traits, 1);
        $tx = Yii::$app->db->beginTransaction();
        try {
            $c = new PlayerCharacter();
            $c->user_account_id = $userId;
            $c->name = $name;
            $c->level = 1;
            $c->experience = 0;
            $c->setStats($stats);
            $c->tag_skills = array_values($tags);
            $c->traits = array_values($traits);
            $c->skill_points_spent = array_fill(0, count(CharacterRules::SKILLS), 0);
            $c->unspent_skill_points = 0;
            $c->max_hp = $derived['max_hp'];
            $c->current_hp = $derived['max_hp'];
            $c->location_id = Yii::$app->params['startLocation'];
            $c->hex_q = 0;
            $c->hex_r = 0;
            $c->active_hand = 'item1';
            $c->karma = 0;
            $c->perks = [];
            $c->pending_perks = 0;
            $c->is_deleted = false;
            $c->save(false);

            foreach (ItemCatalog::starterInventory() as $i => $row) {
                $it = new InventoryItem();
                $it->player_character_id = $c->id;
                $it->item_id = $row['item_id'];
                $it->quantity = $row['quantity'];
                $it->item_condition = 100;
                $it->slot = $row['slot'];
                $it->ammo_count = 0;
                $it->sort_order = $i;
                $it->save(false);
            }
            $tx->commit();
            return [$c, []];
        } catch (IntegrityException $e) {
            $tx->rollBack();
            // Гонка: имя заняли между проверкой и вставкой
            return [null, ['name' => ['Имя уже занято']]];
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * Сохранение состояния персонажа с игрового сервера. Всё в одной транзакции:
     * если запись инвентаря прервалась — откат целиком (нет дублирования/исчезновения предметов).
     */
    public static function saveState(array $s): ?string
    {
        $id = (int)($s['character_id'] ?? 0);
        $c = PlayerCharacter::findOne(['id' => $id, 'is_deleted' => false]);
        if (!$c) return "Персонаж $id не найден";

        $inventory = $s['inventory'] ?? [];
        if (!is_array($inventory)) return 'Некорректный инвентарь';
        $known = Item::knownIds();
        $rows = [];
        $slotsUsed = [];
        foreach (array_values($inventory) as $i => $it) {
            $itemId = (string)($it['item_id'] ?? '');
            if (!isset($known[$itemId])) return "Неизвестный предмет: $itemId";
            $slot = (string)($it['slot'] ?? '');
            if (!in_array($slot, InventoryItem::SLOTS, true)) return "Некорректный слот: $slot";
            if ($slot !== '') {
                if (isset($slotsUsed[$slot])) return "Слот $slot занят дважды";
                $slotsUsed[$slot] = true;
            }
            $qty = (int)($it['quantity'] ?? 1);
            if ($qty < 1 || $qty > 1000000) return "Некорректное количество: $qty";
            $ammoId = (string)($it['ammo_item_id'] ?? '');
            if ($ammoId !== '' && !isset($known[$ammoId])) return "Неизвестные патроны: $ammoId";
            $rows[] = [$id, $itemId, $qty, max(0, min(100, (int)($it['condition'] ?? 100))), $slot,
                max(0, (int)($it['ammo_count'] ?? 0)), $ammoId === '' ? null : $ammoId, $i];
        }

        $spent = $s['skill_points_spent'] ?? $c->skill_points_spent;
        if (!is_array($spent) || count($spent) !== count(CharacterRules::SKILLS)) return 'Некорректные очки навыков';

        $perks = $s['perks'] ?? $c->perks ?? [];
        if (!is_array($perks) || count($perks) > 60) return 'Некорректные перки';
        foreach ($perks as $p) if (!in_array($p, CharacterRules::PERKS, true)) return "Неизвестный перк: $p";

        $vars = $s['vars'] ?? null;
        $varRows = [];
        if (is_array($vars)) {
            if (count($vars) > 2000) return 'Слишком много переменных персонажа';
            $seen = [];
            foreach ($vars as $v) {
                $npc = substr((string)($v['npc_id'] ?? ''), 0, 64);
                $key = substr((string)($v['var_key'] ?? ''), 0, 64);
                $val = (int)($v['value'] ?? 0);
                if ($npc === '' || $key === '' || $val === 0 || isset($seen["$npc|$key"])) continue;
                $seen["$npc|$key"] = true;
                $varRows[] = [$id, $npc, $key, $val];
            }
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $c->level = max(1, (int)($s['level'] ?? $c->level));
            $c->experience = max(0, (int)($s['experience'] ?? $c->experience));
            $c->skill_points_spent = array_map('intval', array_values($spent));
            $c->unspent_skill_points = max(0, (int)($s['unspent_skill_points'] ?? $c->unspent_skill_points));
            $c->max_hp = max(1, (int)($s['max_hp'] ?? $c->max_hp));
            $c->current_hp = max(0, min($c->max_hp, (int)($s['current_hp'] ?? $c->current_hp)));
            $c->location_id = substr((string)($s['location_id'] ?? $c->location_id), 0, 64);
            $c->hex_q = (int)($s['hex_q'] ?? $c->hex_q);
            $c->hex_r = (int)($s['hex_r'] ?? $c->hex_r);
            $c->active_hand = ($s['active_hand'] ?? 'item1') === 'item2' ? 'item2' : 'item1';
            $c->perks = array_values($perks);
            $c->pending_perks = max(0, min(20, (int)($s['pending_perks'] ?? $c->pending_perks)));
            $c->karma = max(-10000, min(10000, (int)($s['karma'] ?? $c->karma)));
            $c->entry_from = substr((string)($s['entry_from'] ?? ''), 0, 64) ?: null;
            $c->last_login_at = time();
            $c->save(false);

            InventoryItem::deleteAll(['player_character_id' => $id]);
            if ($rows) {
                Yii::$app->db->createCommand()->batchInsert(InventoryItem::tableName(),
                    ['player_character_id', 'item_id', 'quantity', 'item_condition', 'slot', 'ammo_count', 'ammo_item_id', 'sort_order'],
                    $rows)->execute();
            }
            if (is_array($vars)) {
                Yii::$app->db->createCommand()->delete('{{%dialog_var}}', ['player_character_id' => $id])->execute();
                if ($varRows) {
                    Yii::$app->db->createCommand()->batchInsert('{{%dialog_var}}', ['player_character_id', 'npc_id', 'var_key', 'value'], $varRows)->execute();
                }
            }
            $tx->commit();
            return null;
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error("Ошибка сохранения персонажа $id: " . $e->getMessage(), __METHOD__);
            return 'Ошибка сохранения';
        }
    }
}
