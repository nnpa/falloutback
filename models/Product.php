<?php
namespace app\models;

use yii\db\ActiveRecord;

class Product extends ActiveRecord
{
    public static function tableName()
    {
        return 'products';
    }

    public function rules()
    {
        return [
            [['name', 'calories_per_100g'], 'required'],
            [['calories_per_100g'], 'integer', 'min' => 0],
            [['name'], 'string', 'max' => 255],
            [['name'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Название продукта',
            'calories_per_100g' => 'Калорий на 100г',
            'created_at' => 'Дата создания',
        ];
    }

    public function getFoodIntakes()
    {
        return $this->hasMany(FoodIntake::class, ['product_id' => 'id']);
    }
}