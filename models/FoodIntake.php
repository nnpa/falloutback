<?php
namespace app\models;

use yii\db\ActiveRecord;

class FoodIntake extends ActiveRecord
{
    public static function tableName()
    {
        return 'food_intake';
    }

    public function rules()
    {
        return [
            [['user_id', 'product_id', 'grams', 'calories', 'date'], 'required'],
            [['user_id', 'product_id', 'calories'], 'integer'],
            [['grams'], 'number', 'min' => 0.01],
            [['date'], 'safe'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }
}