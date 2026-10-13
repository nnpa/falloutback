<?php

namespace app\controllers;

use app\components\ApiController;
use app\models\AuthToken;
use app\models\UserAccount;
use Yii;

/**
 * Регистрация, вход, выход.
 *   POST /api/auth/register {username, email, password} → {access_token, expires_at, user_id, username}
 *   POST /api/auth/login    {username, password}        → то же
 *   POST /api/auth/logout   (Bearer)
 */
class AuthController extends ApiController
{
    protected array $publicActions = ['register', 'login'];

    public function actionRegister(): array
    {
        if (!$this->throttle('register', (int)Yii::$app->params['authRateLimit'])) {
            return ['name' => 'Too many requests', 'message' => 'Слишком много попыток, подождите минуту', 'status' => 429, 'errors' => []];
        }
        $b = $this->body();
        $password = (string)($b['password'] ?? '');
        if (mb_strlen($password) < 6) return $this->validationError(['password' => ['Пароль должен быть не короче 6 символов']]);

        $u = new UserAccount();
        $u->username = trim((string)($b['username'] ?? ''));
        $u->email = trim((string)($b['email'] ?? ''));
        $u->status = UserAccount::STATUS_ACTIVE;
        $u->auth_key = Yii::$app->security->generateRandomString(32);
        $u->setPassword($password);
        if (!$u->validate()) return $this->validationError($u->getErrors());
        $u->save(false);

        Yii::$app->response->statusCode = 201;
        return $this->issueToken($u);
    }

    public function actionLogin(): array
    {
        if (!$this->throttle('login', (int)Yii::$app->params['authRateLimit'])) {
            return ['name' => 'Too many requests', 'message' => 'Слишком много попыток, подождите минуту', 'status' => 429, 'errors' => []];
        }
        $b = $this->body();
        $u = UserAccount::findOne(['username' => trim((string)($b['username'] ?? ''))]);
        if (!$u || !$u->validatePassword((string)($b['password'] ?? ''))) {
            Yii::$app->response->statusCode = 401;
            return ['name' => 'Unauthorized', 'message' => 'Неверный логин или пароль', 'status' => 401, 'errors' => []];
        }
        if ((int)$u->status !== UserAccount::STATUS_ACTIVE) {
            Yii::$app->response->statusCode = 403;
            return ['name' => 'Forbidden', 'message' => 'Аккаунт заблокирован', 'status' => 403, 'errors' => []];
        }
        $u->last_login_at = time();
        $u->last_login_ip = Yii::$app->request->userIP;
        $u->save(false, ['last_login_at', 'last_login_ip']);
        return $this->issueToken($u);
    }

    public function actionLogout(): array
    {
        $header = (string)Yii::$app->request->headers->get('Authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) AuthToken::revoke(trim($m[1]));
        return ['ok' => true];
    }

    private function issueToken(UserAccount $u): array
    {
        [$token, $expires] = AuthToken::issue((int)$u->id, Yii::$app->request->userIP);
        return ['access_token' => $token, 'expires_at' => $expires, 'user_id' => (int)$u->id, 'username' => $u->username];
    }
}
