<?php
namespace app\controllers;

use Yii;
use yii\rest\Controller;
use app\models\CaloriesBurned;
use app\models\User;

class CaloriesburnedController extends Controller
{

    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $deviceKey = Yii::$app->request->get('device_key');
        $date = Yii::$app->request->get('date', date('Y-m-d'));
        
        if (!$deviceKey) {
            return ['success' => false, 'message' => 'Device key required'];
        }
        
        $user = User::findOne(['device_key' => $deviceKey]);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $burned = CaloriesBurned::find()
            ->where(['user_id' => $user->id, 'date' => $date])
            ->one();
        
        return [
            'success' => true,
            'calories' => $burned ? (int)$burned->calories : 0,
            'date' => $date,
        ];
    }

    public function actionSave()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $deviceKey = Yii::$app->request->post('device_key');
        $calories = Yii::$app->request->post('calories');
        
        if (!$deviceKey || $calories === null) {
            return ['success' => false, 'message' => 'Missing required fields'];
        }
        
        $calories = (int)$calories;
        if ($calories < 0) {
            return ['success' => false, 'message' => 'Calories must be positive'];
        }
        
        $user = User::findOne(['device_key' => $deviceKey]);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $burned = CaloriesBurned::find()
            ->where(['user_id' => $user->id, 'date' => date('Y-m-d')])
            ->one();
        
        if (!$burned) {
            $burned = new CaloriesBurned();
            $burned->user_id = $user->id;
            $burned->date = date('Y-m-d');
        }
        
        $burned->calories = $calories;
        
        if ($burned->save()) {
            return [
                'success' => true, 
                'calories' => (int)$burned->calories,
                'date' => $burned->date,
            ];
        }
        
        return ['success' => false, 'errors' => $burned->errors];
    }
}