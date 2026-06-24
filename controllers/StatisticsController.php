<?php
namespace app\controllers;

use Yii;
use yii\rest\Controller;
use app\models\User;
use app\models\FoodIntake;
use app\models\CaloriesBurned;

class StatisticsController extends Controller
{
    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        try {
            // Получаем device_key из заголовка
            $deviceKey = Yii::$app->request->headers->get('X-Device-Key');
            if (!$deviceKey) {
                $deviceKey = Yii::$app->request->get('device_key');
            }
            
            $days = Yii::$app->request->get('days', 30);
            
            if (!$deviceKey) {
                return ['success' => false, 'message' => 'Device key required'];
            }
            
            $user = User::findOne(['device_key' => $deviceKey]);
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            $dates = [];
            $eaten = [];
            $burned = [];
            
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dates[] = $date;
                
                // Сумма калорий съеденных
                $eatenTotal = FoodIntake::find()
                    ->where(['user_id' => $user->id, 'date' => $date])
                    ->sum('calories') ?? 0;
                $eaten[] = (int)$eatenTotal;
                
                // Сумма калорий сожженных
                $burnedTotal = CaloriesBurned::find()
                    ->where(['user_id' => $user->id, 'date' => $date])
                    ->sum('calories') ?? 0;
                $burned[] = (int)$burnedTotal;
            }
            
            return [
                'success' => true,
                'dates' => $dates,
                'eaten' => $eaten,
                'burned' => $burned,
            ];
            
        } catch (\Exception $e) {
            Yii::error('Statistics error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ];
        }
    }
}