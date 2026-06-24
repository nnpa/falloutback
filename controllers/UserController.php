<?php
namespace app\controllers;

use Yii;
use yii\rest\Controller;
use app\models\User;

class UserController extends Controller
{
    
    public function actionRegister()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $deviceKey = Yii::$app->security->generateRandomString(32);
        
        $user = new User();
        $user->device_key = $deviceKey;
        
        if ($user->save()) {
            return ['success' => true, 'device_key' => $deviceKey];
        }
        
        return ['success' => false, 'errors' => $user->errors];
    }

    public function actionValidate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $deviceKey = Yii::$app->request->get('device_key');
        
        if (!$deviceKey) {
            return ['success' => false, 'message' => 'Device key required'];
        }
        
        $user = User::findOne(['device_key' => $deviceKey]);
        
        return ['success' => true, 'valid' => $user !== null];
    }
}