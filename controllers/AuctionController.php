<?php

namespace app\controllers;

use app\components\ApiController;
use app\components\AuctionService;
use Yii;

/** Публичный просмотр аукциона (сайт, вики цен): GET /api/auction?search=&item_type=&page= */
class AuctionController extends ApiController
{
    protected array $publicActions = ['index'];

    public function actionIndex(): array
    {
        $q = Yii::$app->request;
        return AuctionService::search(['search' => $q->get('search', ''), 'item_type' => $q->get('item_type', ''), 'page' => (int)$q->get('page', 0)]);
    }
}
