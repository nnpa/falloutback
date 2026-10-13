<?php

use app\components\rules\ItemCatalog;
use yii\db\Migration;

/**
 * Схема БД Fallout 2 Online 3D (раздел 9.7 ТЗ): аккаунты, токены, персонажи, предметы, инвентарь,
 * квесты, журнал, аукцион (для этапа 8). InnoDB + utf8mb4, внешние ключи, транзакционные сохранения.
 */
class m261012_000001_init_schema extends Migration
{
    public function safeUp()
    {
        $opts = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $this->createTable('{{%user_account}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(32)->notNull()->unique(),
            'email' => $this->string(255)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'status' => $this->tinyInteger()->notNull()->defaultValue(10),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'last_login_at' => $this->integer(),
            'last_login_ip' => $this->string(45),
        ], $opts);

        $this->createTable('{{%auth_token}}', [
            'id' => $this->primaryKey(),
            'user_account_id' => $this->integer()->notNull(),
            'token_hash' => $this->char(64)->notNull()->unique(), // sha256 от токена, сам токен не хранится
            'expires_at' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'ip' => $this->string(45),
        ], $opts);
        $this->addForeignKey('fk_token_user', '{{%auth_token}}', 'user_account_id', '{{%user_account}}', 'id', 'CASCADE');
        $this->createIndex('idx_token_expires', '{{%auth_token}}', 'expires_at');

        $this->createTable('{{%player_character}}', [
            'id' => $this->primaryKey(),
            'user_account_id' => $this->integer()->notNull(),
            // utf8mb4_unicode_ci — уникальность без учёта регистра («Вася» = «вася»)
            'name' => $this->string(20)->notNull()->unique(),
            // mb_strtolower(name): уникальность имени без учёта регистра не зависит от collation СУБД
            'name_lower' => $this->string(20)->notNull()->unique(),
            'level' => $this->integer()->notNull()->defaultValue(1),
            'experience' => $this->integer()->notNull()->defaultValue(0),
            'strength' => $this->tinyInteger()->notNull(),
            'perception' => $this->tinyInteger()->notNull(),
            'endurance' => $this->tinyInteger()->notNull(),
            'charisma' => $this->tinyInteger()->notNull(),
            'intelligence' => $this->tinyInteger()->notNull(),
            'agility' => $this->tinyInteger()->notNull(),
            'luck' => $this->tinyInteger()->notNull(),
            'tag_skills' => $this->json()->notNull(),
            'traits' => $this->json()->notNull(),
            'skill_points_spent' => $this->json()->notNull(),
            'unspent_skill_points' => $this->integer()->notNull()->defaultValue(0),
            'current_hp' => $this->integer()->notNull(),
            'max_hp' => $this->integer()->notNull(),
            'location_id' => $this->string(64)->notNull(),
            'hex_q' => $this->integer()->notNull()->defaultValue(0),
            'hex_r' => $this->integer()->notNull()->defaultValue(0),
            'active_hand' => $this->string(8)->notNull()->defaultValue('item1'),
            'karma' => $this->integer()->notNull()->defaultValue(0),
            'is_deleted' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'last_login_at' => $this->integer(),
        ], $opts);
        $this->addForeignKey('fk_char_user', '{{%player_character}}', 'user_account_id', '{{%user_account}}', 'id', 'CASCADE');

        // Каталог предметов: строковые коды, совпадающие с C# ItemDatabase
        $this->createTable('{{%item}}', [
            'id' => $this->string(64)->notNull(),
            'name_ru' => $this->string(128)->notNull(),
            'name_en' => $this->string(128)->notNull(),
            'item_type' => $this->string(16)->notNull(),
            'weight' => $this->integer()->notNull()->defaultValue(0),
            'value' => $this->integer()->notNull()->defaultValue(0),
            'data' => $this->json(),
        ], $opts);
        $this->addPrimaryKey('pk_item', '{{%item}}', 'id');

        $this->createTable('{{%inventory_item}}', [
            'id' => $this->bigPrimaryKey(),
            'player_character_id' => $this->integer()->notNull(),
            'item_id' => $this->string(64)->notNull(),
            'quantity' => $this->integer()->notNull()->defaultValue(1),
            'item_condition' => $this->tinyInteger()->notNull()->defaultValue(100), // `condition` — зарезервированное слово MySQL
            'slot' => $this->string(8)->notNull()->defaultValue(''),
            'ammo_count' => $this->integer()->notNull()->defaultValue(0),
            'ammo_item_id' => $this->string(64),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
        ], $opts);
        $this->addForeignKey('fk_inv_char', '{{%inventory_item}}', 'player_character_id', '{{%player_character}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_inv_item', '{{%inventory_item}}', 'item_id', '{{%item}}', 'id', 'RESTRICT');

        $this->createTable('{{%quest}}', [
            'id' => $this->primaryKey(),
            'player_character_id' => $this->integer()->notNull(),
            'quest_name' => $this->string(64)->notNull(),
            'status' => "ENUM('active','completed','failed') NOT NULL DEFAULT 'active'",
            'progress_data' => $this->json(),
            'cooldown_until' => $this->integer(),
            'updated_at' => $this->integer()->notNull(),
        ], $opts);
        $this->addForeignKey('fk_quest_char', '{{%quest}}', 'player_character_id', '{{%player_character}}', 'id', 'CASCADE');
        $this->createIndex('ux_quest_char_name', '{{%quest}}', ['player_character_id', 'quest_name'], true);

        // Репутация: персонаж ↔ фракция/город/NPC
        $this->createTable('{{%reputation}}', [
            'player_character_id' => $this->integer()->notNull(),
            'subject' => $this->string(64)->notNull(),
            'value' => $this->integer()->notNull()->defaultValue(0),
        ], $opts);
        $this->addPrimaryKey('pk_reputation', '{{%reputation}}', ['player_character_id', 'subject']);
        $this->addForeignKey('fk_rep_char', '{{%reputation}}', 'player_character_id', '{{%player_character}}', 'id', 'CASCADE');

        // Unicum-переменные «игрок — NPC» (раздел 7.2)
        $this->createTable('{{%dialog_var}}', [
            'player_character_id' => $this->integer()->notNull(),
            'npc_id' => $this->string(64)->notNull(),
            'var_key' => $this->string(64)->notNull(),
            'value' => $this->integer()->notNull()->defaultValue(0),
        ], $opts);
        $this->addPrimaryKey('pk_dialog_var', '{{%dialog_var}}', ['player_character_id', 'npc_id', 'var_key']);
        $this->addForeignKey('fk_dv_char', '{{%dialog_var}}', 'player_character_id', '{{%player_character}}', 'id', 'CASCADE');

        // Журнал команд, транзакций, смертей, чата (раздел 9.10)
        $this->createTable('{{%game_log}}', [
            'id' => $this->bigPrimaryKey(),
            'player_character_id' => $this->integer(),
            'kind' => $this->string(32)->notNull(),
            'payload' => $this->text(),
            'ts' => $this->integer()->notNull(),
            'server_ip' => $this->string(45),
        ], $opts);
        $this->createIndex('idx_log_kind_ts', '{{%game_log}}', ['kind', 'ts']);
        $this->createIndex('idx_log_char_ts', '{{%game_log}}', ['player_character_id', 'ts']);

        // Аукцион (этап 8): налог 6%, не более 30 лотов на игрока
        $this->createTable('{{%auction_lot}}', [
            'id' => $this->primaryKey(),
            'seller_character_id' => $this->integer()->notNull(),
            'item_id' => $this->string(64)->notNull(),
            'quantity' => $this->integer()->notNull(),
            'item_condition' => $this->tinyInteger()->notNull()->defaultValue(100),
            'price' => $this->integer()->notNull(),
            'status' => "ENUM('active','sold','cancelled','expired') NOT NULL DEFAULT 'active'",
            'buyer_character_id' => $this->integer(),
            'created_at' => $this->integer()->notNull(),
            'closed_at' => $this->integer(),
        ], $opts);
        $this->addForeignKey('fk_lot_seller', '{{%auction_lot}}', 'seller_character_id', '{{%player_character}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_lot_item', '{{%auction_lot}}', 'item_id', '{{%item}}', 'id', 'RESTRICT');
        $this->createIndex('idx_lot_status', '{{%auction_lot}}', ['status', 'item_id']);

        // Начальное заполнение каталога предметов
        foreach (ItemCatalog::all() as $it) {
            $this->insert('{{%item}}', [
                'id' => $it['id'], 'name_ru' => $it['name_ru'], 'name_en' => $it['name_en'], 'item_type' => $it['item_type'],
                'weight' => $it['weight'], 'value' => $it['value'], 'data' => json_encode($it['data'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function safeDown()
    {
        foreach (['auction_lot', 'game_log', 'dialog_var', 'reputation', 'quest', 'inventory_item', 'item', 'player_character', 'auth_token', 'user_account'] as $t) {
            $this->dropTable('{{%' . $t . '}}');
        }
    }
}
