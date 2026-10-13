<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Реестр игровых серверов (шардов): адрес и обслуживаемые локации, heartbeat каждые 10 с.
 *
 * @property string $id
 * @property string $host
 * @property int $port
 * @property array|string $locations
 * @property int $players
 * @property int $last_heartbeat
 */
class GameServer extends ActiveRecord
{
    /** Сервер считается живым, если heartbeat был не позже 30 с назад. */
    public const ALIVE_SECONDS = 30;

    public static function tableName(): string
    {
        return '{{%game_server}}';
    }

    public function getLocationList(): array
    {
        $l = $this->locations;
        if (is_string($l)) $l = json_decode($l, true);
        return is_array($l) ? $l : [];
    }

    /** @return static[] */
    public static function alive(): array
    {
        return static::find()->where(['>', 'last_heartbeat', time() - self::ALIVE_SECONDS])->all();
    }
}
