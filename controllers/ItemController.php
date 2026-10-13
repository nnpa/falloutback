<?php

namespace app\controllers;

use app\components\ApiController;
use app\models\Item;

/** GET /api/items — каталог предметов (для сайта, вики, аукциона). */
class ItemController extends ApiController
{
    protected array $publicActions = ['index'];

    public function actionIndex(): array
    {
        return ['items' => array_map(fn(Item $i) => $i->toArray(), Item::find()->orderBy(['item_type' => SORT_ASC, 'id' => SORT_ASC])->all())];
    }
}
