<?php

namespace app\controllers;

use app\components\ApiController;
use app\components\CharacterService;
use app\components\ShardRegistry;
use app\components\rules\CharacterRules;
use app\models\PlayerCharacter;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Персонажи аккаунта.
 *   GET    /api/characters                    → {items:[CharacterDto]}
 *   GET    /api/characters/check-name?name=…  → {available, error}
 *   POST   /api/characters                    → CharacterDto (экран создания персонажа)
 *   GET    /api/characters/{id}               → CharacterDto с инвентарём
 *   DELETE /api/characters/{id}
 *   GET    /api/characters/{id}/server       → {host, port} игрового сервера (пусто — сервер по умолчанию)
 */
class CharacterController extends ApiController
{
    public function actionIndex(): array
    {
        $items = PlayerCharacter::find()
            ->where(['user_account_id' => Yii::$app->user->id, 'is_deleted' => false])
            ->orderBy(['id' => SORT_ASC])->all();
        return ['items' => array_map(fn(PlayerCharacter $c) => $c->toDto(), $items)];
    }

    public function actionCheckName(string $name = ''): array
    {
        $err = CharacterRules::validateName($name);
        if ($err) return ['available' => false, 'error' => $err];
        return ['available' => !PlayerCharacter::isNameTaken($name), 'error' => ''];
    }

    public function actionCreate(): array
    {
        [$c, $errors] = CharacterService::create((int)Yii::$app->user->id, $this->body());
        if ($errors) return $this->validationError($errors);
        Yii::$app->response->statusCode = 201;
        return $c->toDto(true);
    }

    public function actionView(int $id): array
    {
        return $this->find($id)->toDto(true);
    }

    public function actionServer(int $id): array
    {
        [$data, $status] = ShardRegistry::forCharacter($this->find($id));
        Yii::$app->response->statusCode = $status;
        return $data;
    }

    public function actionDelete(int $id): array
    {
        $c = $this->find($id);
        // Мягкое удаление: имя освобождается переименованием
        $c->is_deleted = true;
        $c->name = mb_substr('~' . $c->id . '_' . $c->name, 0, 20);
        $c->save(false, ['is_deleted', 'name', 'name_lower', 'updated_at']);
        return ['ok' => true];
    }

    private function find(int $id): PlayerCharacter
    {
        $c = PlayerCharacter::findOne(['id' => $id, 'user_account_id' => Yii::$app->user->id, 'is_deleted' => false]);
        if (!$c) throw new NotFoundHttpException('Персонаж не найден');
        return $c;
    }
}
