<?php
namespace app\controllers;

use Yii;
use yii\rest\Controller;
use app\models\FoodIntake;
use app\models\User;
use app\models\Product;

class FoodintakeController extends Controller
{

    
        // Обработка OPTIONS запроса
    public function actionOptions()
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        Yii::$app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-Device-Key');
        Yii::$app->response->headers->set('Access-Control-Allow-Credentials', 'true');
        Yii::$app->response->headers->set('Access-Control-Max-Age', '86400');
        Yii::$app->response->data = '';
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        return '';
    }
    
   public function actionIndex()
{
    Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    
    // Сначала пробуем получить device_key из заголовка
    $deviceKey = Yii::$app->request->headers->get('X-Device-Key');
    // Если нет в заголовке, пробуем из GET
    if (!$deviceKey) {
        $deviceKey = Yii::$app->request->get('device_key');
    }
    
    $date = Yii::$app->request->get('date', date('Y-m-d'));
    
    if (!$deviceKey) {
        return ['success' => false, 'message' => 'Device key required'];
    }
    
    $user = User::findOne(['device_key' => $deviceKey]);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $intakes = FoodIntake::find()
        ->where(['user_id' => $user->id, 'date' => $date])
        ->with('product')
        ->all();
    
    $totalCalories = 0;
    $items = [];
    
    foreach ($intakes as $intake) {
        $totalCalories += $intake->calories;
        $items[] = [
            'id' => $intake->id,
            'product_id' => $intake->product_id,
            'product_name' => $intake->product->name,
            'grams' => (float)$intake->grams,
            'calories' => $intake->calories,
        ];
    }
    
    return [
        'success' => true,
        'items' => $items,
        'total_calories' => $totalCalories,
        'date' => $date,
    ];
}

    public function actionCreate()
{
    Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    
    // Сначала пробуем получить device_key из заголовка
    $deviceKey = Yii::$app->request->headers->get('X-Device-Key');
    // Если нет в заголовке, пробуем из POST
    if (!$deviceKey) {
        $deviceKey = Yii::$app->request->post('device_key');
    }
    
    $productId = Yii::$app->request->post('product_id');
    $grams = Yii::$app->request->post('grams');
    
    if (!$deviceKey || !$productId || !$grams) {
        return [
            'success' => false, 
            'message' => 'Missing required fields',
            'debug' => [
                'deviceKey' => $deviceKey,
                'productId' => $productId,
                'grams' => $grams
            ]
        ];
    }
    
    $user = User::findOne(['device_key' => $deviceKey]);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $product = Product::findOne($productId);
    if (!$product) {
        return ['success' => false, 'message' => 'Product not found'];
    }
    
    $calories = round(($product->calories_per_100g / 100) * $grams);
    
    $intake = new FoodIntake();
    $intake->user_id = $user->id;
    $intake->product_id = $productId;
    $intake->grams = $grams;
    $intake->calories = $calories;
    $intake->date = date('Y-m-d');
    
    if ($intake->save()) {
        return [
            'success' => true, 
            'intake' => [
                'id' => $intake->id,
                'product_id' => $intake->product_id,
                'product_name' => $product->name,
                'grams' => (float)$intake->grams,
                'calories' => $intake->calories,
            ]
        ];
    }
    
    return ['success' => false, 'errors' => $intake->errors];
}

    public function actionDelete($id)
{
    Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    
    // Сначала пробуем получить device_key из заголовка
    $deviceKey = Yii::$app->request->headers->get('X-Device-Key');
    // Если нет в заголовке, пробуем из GET
    if (!$deviceKey) {
        $deviceKey = Yii::$app->request->get('device_key');
    }
    
    if (!$deviceKey) {
        return ['success' => false, 'message' => 'Device key required'];
    }
    
    $user = User::findOne(['device_key' => $deviceKey]);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $intake = FoodIntake::findOne(['id' => $id, 'user_id' => $user->id]);
    if (!$intake) {
        return ['success' => false, 'message' => 'Record not found'];
    }
    
    if ($intake->delete()) {
        return ['success' => true];
    }
    
    return ['success' => false, 'message' => 'Could not delete record'];
}
}