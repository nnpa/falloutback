<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Фракция игроков.
 *
 * @property int $id
 * @property string $name
 * @property string $name_lower
 * @property string $tag
 * @property string $tag_lower
 * @property int $leader_character_id
 * @property int $created_at
 * @property PlayerCharacter[] $members
 */
class Faction extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%faction}}';
    }

    public function getMembers()
    {
        return $this->hasMany(PlayerCharacter::class, ['faction_id' => 'id'])->andWhere(['is_deleted' => false])->orderBy(['id' => SORT_ASC]);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) return false;
        $this->name_lower = mb_strtolower($this->name, 'UTF-8');
        $this->tag_lower = mb_strtolower($this->tag, 'UTF-8');
        return true;
    }

    /** Поля совпадают с C# FactionDto. */
    public function toDto(bool $withMembers = true): array
    {
        $dto = ['id' => (int)$this->id, 'name' => $this->name, 'tag' => $this->tag, 'leader_character_id' => (int)$this->leader_character_id, 'error' => ''];
        $dto['members'] = $withMembers ? array_map(fn(PlayerCharacter $c) => [
            'character_id' => (int)$c->id, 'name' => $c->name, 'rank' => (string)$c->faction_rank, 'level' => (int)$c->level,
        ], $this->members) : [];
        return $dto;
    }
}
