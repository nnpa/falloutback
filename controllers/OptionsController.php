<?php
namespace app\controllers;

use yii\web\Controller;
use yii\web\Response;

class OptionsController extends Controller
{
    public function actionIndex()
    {
        \Yii::$app->response->statusCode = 200;
        \Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        \Yii::$app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        \Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        \Yii::$app->response->headers->set('Access-Control-Allow-Credentials', 'true');
        \Yii::$app->response->headers->set('Access-Control-Max-Age', '86400');
        \Yii::$app->response->data = '';
        \Yii::$app->response->format = Response::FORMAT_RAW;
        \Yii::$app->response->send();
        \Yii::$app->end();
    }
}