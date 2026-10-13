<?php

namespace app\components;

use app\models\AuctionLot;
use app\models\Item;
use app\models\PlayerCharacter;
use Yii;

/**
 * Аукцион. Атомарность обеспечивается условными UPDATE (... WHERE status = 'active'):
 * два покупателя не могут купить один лот, лот нельзя одновременно купить и снять.
 */
final class AuctionService
{
    public static function create(array $b): array
    {
        $sellerId = (int)($b['character_id'] ?? 0);
        $itemId = (string)($b['item_id'] ?? '');
        $qty = (int)($b['quantity'] ?? 0);
        $price = (int)($b['price'] ?? 0);
        if (!PlayerCharacter::find()->where(['id' => $sellerId, 'is_deleted' => false])->exists()) return [null, 'Персонаж не найден'];
        if (!isset(Item::knownIds()[$itemId]) || $itemId === 'caps') return [null, 'Этот предмет нельзя выставить'];
        if ($qty < 1 || $qty > 100000) return [null, 'Некорректное количество'];
        if ($price < 1 || $price > AuctionLot::MAX_PRICE) return [null, 'Цена: от 1 до ' . AuctionLot::MAX_PRICE];
        $active = AuctionLot::find()->where(['seller_character_id' => $sellerId, 'status' => AuctionLot::STATUS_ACTIVE])
            ->andWhere(['>', 'expires_at', time()])->count();
        if ($active >= AuctionLot::MAX_LOTS) return [null, 'Не более ' . AuctionLot::MAX_LOTS . ' лотов одновременно'];
        $ammo = (string)($b['ammo_item_id'] ?? '');

        $lot = new AuctionLot([
            'seller_character_id' => $sellerId, 'item_id' => $itemId, 'quantity' => $qty,
            'item_condition' => max(0, min(100, (int)($b['condition'] ?? 100))),
            'ammo_count' => $ammo !== '' && isset(Item::knownIds()[$ammo]) ? max(0, (int)($b['ammo_count'] ?? 0)) : 0,
            'ammo_item_id' => $ammo !== '' && isset(Item::knownIds()[$ammo]) ? $ammo : null,
            'price' => $price, 'status' => AuctionLot::STATUS_ACTIVE,
            'created_at' => time(), 'expires_at' => time() + AuctionLot::LIFETIME,
            'payout_claimed' => false, 'item_returned' => false,
        ]);
        $lot->save(false);
        return [$lot, null];
    }

    /** Поиск активных лотов: по названию, типу предмета или id; 20 на страницу, дешёвые первыми. */
    public static function search(array $b): array
    {
        $q = AuctionLot::find()->alias('l')->innerJoin(Item::tableName() . ' i', 'i.id = l.item_id')
            ->where(['l.status' => AuctionLot::STATUS_ACTIVE])->andWhere(['>', 'l.expires_at', time()]);
        if (!empty($b['lot_id'])) $q->andWhere(['l.id' => (int)$b['lot_id']]);
        if (($s = trim((string)($b['search'] ?? ''))) !== '') $q->andWhere(['or', ['like', 'i.name_ru', $s], ['like', 'i.name_en', $s]]);
        if (($t = (string)($b['item_type'] ?? '')) !== '') {
            $q->andWhere($t === 'misc' ? ['in', 'i.item_type', ['misc', 'tool', 'food', 'key']] : ['i.item_type' => $t]);
        }
        $total = (int)(clone $q)->count();
        $pages = max(1, (int)ceil($total / AuctionLot::PAGE_SIZE));
        $page = max(0, min($pages - 1, (int)($b['page'] ?? 0)));
        $items = $q->with('seller')->orderBy(['l.price' => SORT_ASC, 'l.id' => SORT_ASC])
            ->offset($page * AuctionLot::PAGE_SIZE)->limit(AuctionLot::PAGE_SIZE)->all();
        return ['items' => array_map(fn(AuctionLot $l) => $l->toDto(), $items), 'page' => $page, 'total_pages' => $pages];
    }

    public static function mine(int $characterId): array
    {
        $items = AuctionLot::find()->with('seller')->where(['seller_character_id' => $characterId])
            ->andWhere(['or', ['status' => AuctionLot::STATUS_ACTIVE], ['>', 'closed_at', time() - 7 * 86400]])
            ->orderBy(['id' => SORT_DESC])->limit(50)->all();
        return ['items' => array_map(fn(AuctionLot $l) => $l->toDto(), $items), 'page' => 0, 'total_pages' => 1];
    }

    public static function buy(int $buyerId, int $lotId): array
    {
        if (!PlayerCharacter::find()->where(['id' => $buyerId, 'is_deleted' => false])->exists()) return [null, 'Персонаж не найден'];
        $n = Yii::$app->db->createCommand()->update(AuctionLot::tableName(),
            ['status' => AuctionLot::STATUS_SOLD, 'buyer_character_id' => $buyerId, 'closed_at' => time()],
            ['and', ['id' => $lotId, 'status' => AuctionLot::STATUS_ACTIVE], ['>', 'expires_at', time()], ['<>', 'seller_character_id', $buyerId]]
        )->execute();
        if ($n !== 1) return [null, 'Лот уже продан, снят или истёк'];
        return [AuctionLot::findOne($lotId), null];
    }

    public static function cancel(int $sellerId, int $lotId): array
    {
        $n = Yii::$app->db->createCommand()->update(AuctionLot::tableName(),
            ['status' => AuctionLot::STATUS_CANCELLED, 'closed_at' => time(), 'item_returned' => true],
            ['id' => $lotId, 'seller_character_id' => $sellerId, 'status' => AuctionLot::STATUS_ACTIVE]
        )->execute();
        if ($n !== 1) return [null, 'Лот нельзя снять (уже продан или не ваш)'];
        return [AuctionLot::findOne($lotId), null];
    }

    /**
     * Выплаты продавцу: выручка за проданные лоты (за вычетом 6%) и возврат истёкших.
     * Каждая строка помечается условным UPDATE — повторный вызов ничего не выплатит дважды.
     */
    public static function claim(int $sellerId): array
    {
        $db = Yii::$app->db;
        $caps = 0; $sold = 0; $returned = [];
        $soldLots = AuctionLot::find()->where(['seller_character_id' => $sellerId, 'status' => AuctionLot::STATUS_SOLD, 'payout_claimed' => false])->all();
        foreach ($soldLots as $l) {
            $n = $db->createCommand()->update(AuctionLot::tableName(), ['payout_claimed' => true], ['id' => $l->id, 'payout_claimed' => false])->execute();
            if ($n === 1) { $caps += AuctionLot::payout((int)$l->price); $sold++; }
        }
        $expired = AuctionLot::find()->where(['seller_character_id' => $sellerId, 'status' => AuctionLot::STATUS_ACTIVE])
            ->andWhere(['<=', 'expires_at', time()])->all();
        foreach ($expired as $l) {
            $n = $db->createCommand()->update(AuctionLot::tableName(),
                ['status' => AuctionLot::STATUS_EXPIRED, 'item_returned' => true, 'closed_at' => time()],
                ['id' => $l->id, 'status' => AuctionLot::STATUS_ACTIVE])->execute();
            if ($n === 1) $returned[] = $l->toDto();
        }
        return ['caps' => $caps, 'returned' => $returned, 'sold_count' => $sold];
    }
}
