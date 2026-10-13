<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Журнал действий игроков: команды, транзакции, смерти, чат (раздел 9.10).
 *
 * @property int $id
 * @property int|null $player_character_id
 * @property string $kind
 * @property string|null $payload
 * @property int $ts
 * @property string|null $server_ip
 */
class GameLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%game_log}}';
    }
}
