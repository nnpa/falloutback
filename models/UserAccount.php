<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Аккаунт игрока.
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $auth_key
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $last_login_at
 * @property string|null $last_login_ip
 * @property PlayerCharacter[] $characters
 */
class UserAccount extends ActiveRecord implements IdentityInterface
{
    public const STATUS_BANNED = 0;
    public const STATUS_ACTIVE = 10;

    public static function tableName(): string
    {
        return '{{%user_account}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['username', 'email'], 'required'],
            ['username', 'string', 'min' => 3, 'max' => 32],
            ['username', 'match', 'pattern' => '/^[A-Za-z0-9_\-]+$/', 'message' => 'Логин: латиница, цифры, «_» и «-»'],
            ['email', 'email'],
            ['username', 'unique', 'message' => 'Этот логин уже занят'],
            ['email', 'unique', 'message' => 'Этот e-mail уже зарегистрирован'],
        ];
    }

    public function getCharacters()
    {
        return $this->hasMany(PlayerCharacter::class, ['user_account_id' => 'id'])->andWhere(['is_deleted' => false]);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    // ---------- IdentityInterface ----------

    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /** Bearer-токен: в БД хранится только sha256, сравнение — по хешу, с проверкой срока. */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $t = AuthToken::findValid((string)$token);
        return $t ? static::findIdentity($t->user_account_id) : null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return hash_equals($this->auth_key, (string)$authKey);
    }
}
