<?php

namespace app\controllers;

use app\components\ApiController;
use app\models\Faction;
use yii\web\NotFoundHttpException;

/** Публичные списки фракций (для сайта): GET /api/factions, GET /api/factions/{id} */
class FactionController extends ApiController
{
    protected array $publicActions = ['index', 'view'];

    public function actionIndex(): array
    {
        $rows = Faction::find()->alias('f')
            ->select(['f.id', 'f.name', 'f.tag', 'members' => '(SELECT COUNT(*) FROM {{%player_character}} c WHERE c.faction_id = f.id AND c.is_deleted = 0)'])
            ->orderBy(['members' => SORT_DESC, 'f.id' => SORT_ASC])->limit(200)->asArray()->all();
        return ['items' => array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'tag' => $r['tag'], 'members' => (int)$r['members']], $rows)];
    }

    public function actionView(int $id): array
    {
        $f = Faction::findOne($id);
        if (!$f) throw new NotFoundHttpException('Фракция не найдена');
        return $f->toDto();
    }
}
