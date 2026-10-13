<?php

namespace app\components;

use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\Response;

/**
 * Базовый REST-контроллер: JSON, CORS, Bearer-авторизация, единый формат ошибок валидации.
 */
abstract class ApiController extends Controller
{
    /** Действия без авторизации. */
    protected array $publicActions = [];

    public function behaviors(): array
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
            'cors' => ['class' => Cors::class],
            'authenticator' => [
                'class' => HttpBearerAuth::class,
                'except' => array_merge($this->publicActions, ['options']),
            ],
        ];
    }

    /**
     * Ошибка валидации 422 в формате, который понимает Unity-клиент:
     * {"name":"Unprocessable entity","message":"...","status":422,"errors":[{"field":"name","message":"..."}]}
     */
    protected function validationError(array $errorsByField): array
    {
        Yii::$app->response->statusCode = 422;
        $list = [];
        foreach ($errorsByField as $field => $messages) {
            foreach ((array)$messages as $m) $list[] = ['field' => $field, 'message' => $m];
        }
        return [
            'name' => 'Unprocessable entity',
            'message' => $list[0]['message'] ?? 'Ошибка валидации',
            'status' => 422,
            'errors' => $list,
        ];
    }

    protected function body(): array
    {
        $b = Yii::$app->request->getBodyParams();
        return is_array($b) ? $b : [];
    }

    /** Простое ограничение частоты по IP (кэш), чтобы не перебирали пароли. */
    protected function throttle(string $bucket, int $perMinute): bool
    {
        $key = 'rl:' . $bucket . ':' . Yii::$app->request->userIP . ':' . intdiv(time(), 60);
        $n = (int)Yii::$app->cache->get($key);
        if ($n >= $perMinute) {
            Yii::$app->response->statusCode = 429;
            return false;
        }
        Yii::$app->cache->set($key, $n + 1, 70);
        return true;
    }
}
