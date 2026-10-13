<?php

namespace app\models;

use app\components\rules\CharacterRules;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Персонаж игрока (таблица PlayerCharacters из ТЗ).
 *
 * @property int $id
 * @property int $user_account_id
 * @property string $name
 * @property string $name_lower
 * @property int $level
 * @property int $experience
 * @property int $strength
 * @property int $perception
 * @property int $endurance
 * @property int $charisma
 * @property int $intelligence
 * @property int $agility
 * @property int $luck
 * @property array $tag_skills
 * @property array $traits
 * @property array $skill_points_spent
 * @property int $unspent_skill_points
 * @property int $current_hp
 * @property int $max_hp
 * @property string $location_id
 * @property int $hex_q
 * @property int $hex_r
 * @property string $active_hand
 * @property int $karma
 * @property bool $is_deleted
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $last_login_at
 * @property array|null $perks
 * @property int $pending_perks
 * @property string|null $entry_from
 * @property int|null $faction_id
 * @property string|null $faction_rank
 * @property Faction|null $faction
 * @property InventoryItem[] $inventoryItems
 */
class PlayerCharacter extends ActiveRecord
{
    public const STAT_COLUMNS = ['strength', 'perception', 'endurance', 'charisma', 'intelligence', 'agility', 'luck'];

    public static function tableName(): string
    {
        return '{{%player_character}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    private const JSON_ATTRS = ['tag_skills', 'traits', 'skill_points_spent', 'perks'];

    /**
     * MySQL: Yii сам кодирует/декодирует JSON-колонки. Для других СУБД (SQLite в тестах) делаем это вручную.
     */
    public function afterFind()
    {
        parent::afterFind();
        foreach (self::JSON_ATTRS as $a) {
            if (is_string($this->$a)) $this->setOldAttribute($a, $this->$a = json_decode($this->$a, true) ?? []);
        }
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) return false;
        $this->name_lower = self::nameKey((string)$this->name);
        if (static::getDb()->driverName !== 'mysql') {
            foreach (self::JSON_ATTRS as $a) if (is_array($this->$a)) $this->$a = json_encode(array_values($this->$a));
        }
        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        foreach (self::JSON_ATTRS as $a) if (is_string($this->$a)) $this->$a = json_decode($this->$a, true) ?? [];
    }

    public function getFaction()
    {
        return $this->hasOne(Faction::class, ['id' => 'faction_id']);
    }

    /** Переменные персонажа (Unicum NPC, кулдауны квестов и краж, открытые места карты, репутация). */
    public function getVars(): array
    {
        return array_map(fn($r) => ['npc_id' => $r['npc_id'], 'var_key' => $r['var_key'], 'value' => (int)$r['value']],
            (new \yii\db\Query())->from('{{%dialog_var}}')->where(['player_character_id' => $this->id])->all(static::getDb()));
    }

    public function getInventoryItems()
    {
        return $this->hasMany(InventoryItem::class, ['player_character_id' => 'id'])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getStats(): array
    {
        return array_map(fn($c) => (int)$this->$c, self::STAT_COLUMNS);
    }

    public function setStats(array $stats): void
    {
        foreach (self::STAT_COLUMNS as $i => $c) $this->$c = (int)$stats[$i];
    }

    public static function isNameTaken(string $name): bool
    {
        // Уникальность без учёта регистра (в т.ч. кириллицы) — по колонке name_lower с UNIQUE индексом
        return static::find()->where(['name_lower' => self::nameKey($name)])->exists();
    }

    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    /** Ответ API — поля совпадают с C# CharacterDto. */
    public function toDto(bool $withInventory = false): array
    {
        $dto = [
            'id' => (int)$this->id,
            'user_account_id' => (int)$this->user_account_id,
            'name' => $this->name,
            'level' => (int)$this->level,
            'experience' => (int)$this->experience,
            'stats' => $this->getStats(),
            'tag_skills' => array_values((array)$this->tag_skills),
            'traits' => array_values((array)$this->traits),
            'skill_points_spent' => array_map('intval', array_values((array)$this->skill_points_spent)),
            'unspent_skill_points' => (int)$this->unspent_skill_points,
            'current_hp' => (int)$this->current_hp,
            'max_hp' => (int)$this->max_hp,
            'location_id' => $this->location_id,
            'hex_q' => (int)$this->hex_q,
            'hex_r' => (int)$this->hex_r,
            'active_hand' => $this->active_hand,
            'perks' => array_values((array)($this->perks ?? [])),
            'pending_perks' => (int)$this->pending_perks,
            'karma' => (int)$this->karma,
            'entry_from' => (string)$this->entry_from,
            'faction_id' => (int)$this->faction_id,
            'faction_name' => $this->faction ? $this->faction->name : '',
            'faction_tag' => $this->faction ? $this->faction->tag : '',
            'faction_rank' => (string)$this->faction_rank,
            'created_at' => date('c', (int)$this->created_at),
        ];
        if ($withInventory) {
            $dto['inventory'] = array_map(fn(InventoryItem $i) => $i->toDto(), $this->inventoryItems);
        } else {
            $dto['inventory'] = [];
        }
        $d = CharacterRules::derived($this->getStats(), $dto['traits'], (int)$this->level, null, $dto['perks']);
        $dto['vars'] = $withInventory ? $this->getVars() : [];
        $dto['derived'] = $d; // для веб-кабинета; Unity игнорирует лишние поля
        return $dto;
    }
}
