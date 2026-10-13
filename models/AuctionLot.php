<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Лот аукциона.
 *
 * @property int $id
 * @property int $seller_character_id
 * @property string $item_id
 * @property int $quantity
 * @property int $item_condition
 * @property int $ammo_count
 * @property string|null $ammo_item_id
 * @property int $price
 * @property string $status active|sold|cancelled|expired
 * @property int|null $buyer_character_id
 * @property int $created_at
 * @property int $expires_at
 * @property int|null $closed_at
 * @property bool $payout_claimed
 * @property bool $item_returned
 * @property PlayerCharacter $seller
 */
class AuctionLot extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SOLD = 'sold';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const MAX_LOTS = 30;
    public const TAX = 0.06;
    public const LIFETIME = 72 * 3600;
    public const MAX_PRICE = 10000000;
    public const PAGE_SIZE = 20;

    public static function tableName(): string
    {
        return '{{%auction_lot}}';
    }

    public function getSeller()
    {
        return $this->hasOne(PlayerCharacter::class, ['id' => 'seller_character_id']);
    }

    public static function payout(int $price): int
    {
        return $price - (int)ceil($price * self::TAX);
    }

    /** Поля совпадают с C# AuctionLotDto. */
    public function toDto(): array
    {
        return [
            'id' => (int)$this->id,
            'seller_character_id' => (int)$this->seller_character_id,
            'seller_name' => $this->seller ? $this->seller->name : '',
            'item_id' => $this->item_id,
            'quantity' => (int)$this->quantity,
            'condition' => (int)$this->item_condition,
            'ammo_count' => (int)$this->ammo_count,
            'ammo_item_id' => (string)$this->ammo_item_id,
            'price' => (int)$this->price,
            'status' => $this->status === self::STATUS_ACTIVE && (int)$this->expires_at <= time() ? self::STATUS_EXPIRED : $this->status,
            'expires_at' => (int)$this->expires_at,
        ];
    }
}
