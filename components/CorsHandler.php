<?php
namespace app\components;

use yii\base\Component;
use yii\web\Response;

class CorsHandler extends Component
{
    public function init()
    {
        parent::init();
        
        $response = \Yii::$app->response;
        $request = \Yii::$app->request;
        
        // Добавляем CORS заголовки для всех ответов
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        // Если это OPTIONS запрос - возвращаем пустой ответ с 200
        if ($request->isOptions) {
            $response->statusCode = 200;
            $response->data = '';
            $response->format = Response::FORMAT_RAW;
            $response->send();
            \Yii::$app->end();
        }
    }
}