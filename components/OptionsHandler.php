<?php
namespace app\components;

use yii\base\Component;
use yii\web\Response;

class OptionsHandler extends Component
{
    public function init()
    {
        parent::init();
        
        $request = \Yii::$app->request;
        
        // Если это OPTIONS запрос
        if ($request->isOptions) {
            $response = \Yii::$app->response;
            $response->statusCode = 200;
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-Device-Key');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
            $response->data = '';
            $response->format = Response::FORMAT_RAW;
            $response->send();
            \Yii::$app->end();
        }
    }
}