<?php
namespace app\models;

use yii\db\ActiveRecord;

class User extends ActiveRecord
{
    public static function tableName()
    {
        return 'user';
    }

    public function rules()
    {
        return [
            [['device_key'], 'required'],
            [['device_key'], 'string', 'max' => 255],
            [['device_key'], 'unique'],
        ];
    }

    public function getFoodIntakes()
    {
        return $this->hasMany(FoodIntake::class, ['user_id' => 'id']);
    }

    public function getCaloriesBurned()
    {
        return $this->hasMany(CaloriesBurned::class, ['user_id' => 'id']);
    }
}