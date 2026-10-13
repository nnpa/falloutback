<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Предмет в инвентаре персонажа (таблица Inventories из ТЗ).
 *
 * @property int $id
 * @property int $player_character_id
 * @property string $item_id
 * @property int $quantity
 * @property int $item_condition
 * @property string $slot
 * @property int $ammo_count
 * @property string|null $ammo_item_id
 * @property int $sort_order
 */
class InventoryItem extends ActiveRecord
{
    public const SLOTS = ['', 'armor', 'item1', 'item2'];

    public static function tableName(): string
    {
        return '{{%inventory_item}}';
    }

    public function getItem()
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
    }

    /** Поля совпадают с C# InventoryItemDto. */
    public function toDto(): array
    {
        return [
            'uid' => (int)$this->id,
            'item_id' => $this->item_id,
            'quantity' => (int)$this->quantity,
            'condition' => (int)$this->item_condition,
            'slot' => $this->slot,
            'ammo_count' => (int)$this->ammo_count,
            'ammo_item_id' => (string)$this->ammo_item_id,
        ];
    }
}
