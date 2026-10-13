<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Токен сессии. Клиент получает токен один раз при входе; в БД хранится sha256.
 *
 * @property int $id
 * @property int $user_account_id
 * @property string $token_hash
 * @property int $expires_at
 * @property int $created_at
 * @property string|null $ip
 */
class AuthToken extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%auth_token}}';
    }

    /** Создаёт токен и возвращает его открытое значение (показывается только клиенту). */
    public static function issue(int $userId, ?string $ip): array
    {
        $plain = Yii::$app->security->generateRandomString(48);
        $t = new static();
        $t->user_account_id = $userId;
        $t->token_hash = hash('sha256', $plain);
        $t->created_at = time();
        $t->expires_at = time() + (int)Yii::$app->params['tokenTtl'];
        $t->ip = $ip;
        $t->save(false);
        return [$plain, $t->expires_at];
    }

    public static function findValid(string $plain): ?self
    {
        if ($plain === '') return null;
        return static::find()
            ->where(['token_hash' => hash('sha256', $plain)])
            ->andWhere(['>', 'expires_at', time()])
            ->one();
    }

    public static function revoke(string $plain): void
    {
        static::deleteAll(['token_hash' => hash('sha256', $plain)]);
    }

    public static function purgeExpired(): int
    {
        return static::deleteAll(['<', 'expires_at', time()]);
    }
}
