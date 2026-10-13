<?php

namespace app\components;

use app\models\Faction;
use app\models\PlayerCharacter;
use Yii;

/**
 * Фракции игроков. Все методы возвращают [FactionDto|null, ошибка|null].
 */
final class FactionService
{
    public static function create(int $characterId, string $name, string $tag): array
    {
        $name = trim($name);
        $tag = trim($tag);
        $len = mb_strlen($name);
        if ($len < 3 || $len > 32) return [null, 'Название фракции: от 3 до 32 символов'];
        if (!preg_match('/^[\p{L}\p{N} _\-]+$/u', $name)) return [null, 'Название: буквы, цифры, пробел, дефис'];
        if (!preg_match('/^[\p{L}\p{N}]{2,5}$/u', $tag)) return [null, 'Тег: 2–5 букв или цифр'];
        $c = PlayerCharacter::findOne(['id' => $characterId, 'is_deleted' => false]);
        if (!$c) return [null, 'Персонаж не найден'];
        if ($c->faction_id) return [null, 'Вы уже состоите во фракции'];
        if (Faction::find()->where(['name_lower' => mb_strtolower($name, 'UTF-8')])->exists()) return [null, 'Фракция с таким названием уже есть'];
        if (Faction::find()->where(['tag_lower' => mb_strtolower($tag, 'UTF-8')])->exists()) return [null, 'Такой тег уже занят'];

        $tx = Yii::$app->db->beginTransaction();
        try {
            $f = new Faction(['name' => $name, 'tag' => $tag, 'leader_character_id' => $c->id, 'created_at' => time()]);
            $f->save(false);
            $c->faction_id = $f->id;
            $c->faction_rank = 'leader';
            $c->save(false, ['faction_id', 'faction_rank']);
            $tx->commit();
            return [$f->toDto(), null];
        } catch (\Throwable $e) {
            $tx->rollBack();
            return [null, 'Название или тег уже заняты'];
        }
    }

    public static function join(int $characterId, string $factionName): array
    {
        $f = Faction::findOne(['name_lower' => mb_strtolower(trim($factionName), 'UTF-8')]);
        if (!$f) return [null, 'Фракция не найдена'];
        $c = PlayerCharacter::findOne(['id' => $characterId, 'is_deleted' => false]);
        if (!$c) return [null, 'Персонаж не найден'];
        if ($c->faction_id) return [null, 'Вы уже состоите во фракции'];
        $c->faction_id = $f->id;
        $c->faction_rank = 'member';
        $c->save(false, ['faction_id', 'faction_rank']);
        return [$f->toDto(), null];
    }

    /** Выход: лидерство переходит к самому давнему участнику; пустая фракция удаляется. */
    public static function leave(int $characterId): array
    {
        $c = PlayerCharacter::findOne(['id' => $characterId, 'is_deleted' => false]);
        if (!$c || !$c->faction_id) return [null, 'Вы не состоите во фракции'];
        $f = Faction::findOne($c->faction_id);
        $tx = Yii::$app->db->beginTransaction();
        try {
            $c->faction_id = null;
            $c->faction_rank = null;
            $c->save(false, ['faction_id', 'faction_rank']);
            if ($f && (int)$f->leader_character_id === $characterId) {
                $next = PlayerCharacter::find()->where(['faction_id' => $f->id, 'is_deleted' => false])->orderBy(['id' => SORT_ASC])->one();
                if ($next) {
                    $f->leader_character_id = $next->id;
                    $f->save(false, ['leader_character_id']);
                    $next->faction_rank = 'leader';
                    $next->save(false, ['faction_rank']);
                } else {
                    $f->delete();
                    $f = null;
                }
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return [$f ? $f->toDto(false) : ['id' => 0, 'name' => '', 'tag' => '', 'leader_character_id' => 0, 'members' => [], 'error' => ''], null];
    }

    public static function kick(int $leaderId, string $targetName): array
    {
        $leader = PlayerCharacter::findOne(['id' => $leaderId, 'is_deleted' => false]);
        if (!$leader || !$leader->faction_id || $leader->faction_rank !== 'leader') return [null, 'Исключать может только лидер'];
        $target = PlayerCharacter::findOne(['name_lower' => PlayerCharacter::nameKey($targetName), 'is_deleted' => false]);
        if (!$target || (int)$target->faction_id !== (int)$leader->faction_id) return [null, 'Этот персонаж не в вашей фракции'];
        if ((int)$target->id === $leaderId) return [null, 'Чтобы уйти, используйте /faction leave'];
        $target->faction_id = null;
        $target->faction_rank = null;
        $target->save(false, ['faction_id', 'faction_rank']);
        return [Faction::findOne($leader->faction_id)->toDto(), null];
    }

    public static function info(int $characterId): array
    {
        $c = PlayerCharacter::findOne(['id' => $characterId, 'is_deleted' => false]);
        if (!$c || !$c->faction_id) return [null, 'Вы не состоите во фракции'];
        $f = Faction::findOne($c->faction_id);
        return $f ? [$f->toDto(), null] : [null, 'Фракция не найдена'];
    }
}
