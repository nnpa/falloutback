<?php
namespace app\controllers;

use Yii;
use yii\rest\Controller;
use app\models\Product;

class ProductController extends Controller
{

    // Получить все продукты
    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $products = Product::find()->orderBy(['name' => SORT_ASC])->all();
        return $products;
    }

    // Добавить новый продукт (для админки)
    public function actionCreate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $product = new Product();
        $product->load(Yii::$app->request->post(), '');
        
        if ($product->save()) {
            return ['success' => true, 'product' => $product];
        }
        
        return ['success' => false, 'errors' => $product->errors];
    }

    // Обновить продукт
    public function actionUpdate($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $product = Product::findOne($id);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        $product->load(Yii::$app->request->post(), '');
        
        if ($product->save()) {
            return ['success' => true, 'product' => $product];
        }
        
        return ['success' => false, 'errors' => $product->errors];
    }

    // Удалить продукт
    public function actionDelete($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $product = Product::findOne($id);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        if ($product->delete()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Could not delete product'];
    }
    
    public function actionAdminindex()
{
    $dataProvider = new \yii\data\ActiveDataProvider([
        'query' => Product::find(),
        'sort' => ['defaultOrder' => ['name' => SORT_ASC]],
    ]);

    return $this->render('index', [
        'dataProvider' => $dataProvider,
    ]);
}

public function actionAdmincreate()
{
    $model = new Product();

    if ($model->load(Yii::$app->request->post()) && $model->save()) {
        return $this->redirect(['admin-index']);
    }

    return $this->render('create', [
        'model' => $model,
    ]);
}

public function actionAdminupdate($id)
{
    $model = Product::findOne($id);

    if ($model->load(Yii::$app->request->post()) && $model->save()) {
        return $this->redirect(['admin-index']);
    }

    return $this->render('create', [
        'model' => $model,
    ]);
}

public function actionAdmindelete($id)
{
    $model = Product::findOne($id);
    if ($model) {
        $model->delete();
    }
    return $this->redirect(['admin-index']);
}
}