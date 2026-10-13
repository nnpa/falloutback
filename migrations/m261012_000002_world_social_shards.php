<?php

use app\components\rules\ItemCatalog;
use yii\db\Migration;

/**
 * Этапы 6–8 и шардирование:
 *  - перки, карма, точка входа при переходе между шардами — в player_character;
 *  - фракции игроков;
 *  - аукцион: срок лота, выплата продавцу, возврат непроданного;
 *  - реестр игровых серверов (какой шард обслуживает какие локации);
 *  - новые предметы каталога (ловушки, плазменная граната, трофеи).
 */
class m261012_000002_world_social_shards extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $this->addColumn('{{%player_character}}', 'perks', $this->json());
        $this->addColumn('{{%player_character}}', 'pending_perks', $this->integer()->notNull()->defaultValue(0));
        $this->addColumn('{{%player_character}}', 'entry_from', $this->string(64));
        $this->addColumn('{{%player_character}}', 'faction_id', $this->integer());
        $this->addColumn('{{%player_character}}', 'faction_rank', $this->string(16));

        $this->createTable('{{%faction}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(32)->notNull(),
            'name_lower' => $this->string(32)->notNull()->unique(),
            'tag' => $this->string(5)->notNull(),
            'tag_lower' => $this->string(5)->notNull()->unique(),
            'leader_character_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], $opts);
        $this->addForeignKey('fk_char_faction', '{{%player_character}}', 'faction_id', '{{%faction}}', 'id', 'SET NULL');

        $this->addColumn('{{%auction_lot}}', 'ammo_count', $this->integer()->notNull()->defaultValue(0));
        $this->addColumn('{{%auction_lot}}', 'ammo_item_id', $this->string(64));
        $this->addColumn('{{%auction_lot}}', 'expires_at', $this->integer()->notNull()->defaultValue(0));
        $this->addColumn('{{%auction_lot}}', 'payout_claimed', $this->boolean()->notNull()->defaultValue(false));
        $this->addColumn('{{%auction_lot}}', 'item_returned', $this->boolean()->notNull()->defaultValue(false));
        $this->createIndex('idx_lot_seller', '{{%auction_lot}}', ['seller_character_id', 'status']);

        $this->createTable('{{%game_server}}', [
            'id' => $this->string(64)->notNull(),
            'host' => $this->string(255)->notNull(),
            'port' => $this->integer()->notNull(),
            'locations' => $this->json()->notNull(),
            'players' => $this->integer()->notNull()->defaultValue(0),
            'last_heartbeat' => $this->integer()->notNull(),
        ], $opts);
        $this->addPrimaryKey('pk_game_server', '{{%game_server}}', 'id');

        foreach (ItemCatalog::all() as $it) {
            $this->upsert('{{%item}}', [
                'id' => $it['id'], 'name_ru' => $it['name_ru'], 'name_en' => $it['name_en'], 'item_type' => $it['item_type'],
                'weight' => $it['weight'], 'value' => $it['value'], 'data' => json_encode($it['data'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%game_server}}');
        $this->dropIndex('idx_lot_seller', '{{%auction_lot}}');
        foreach (['item_returned', 'payout_claimed', 'expires_at', 'ammo_item_id', 'ammo_count'] as $c) $this->dropColumn('{{%auction_lot}}', $c);
        $this->dropForeignKey('fk_char_faction', '{{%player_character}}');
        $this->dropTable('{{%faction}}');
        foreach (['faction_rank', 'faction_id', 'entry_from', 'pending_perks', 'perks'] as $c) $this->dropColumn('{{%player_character}}', $c);
    }
}
