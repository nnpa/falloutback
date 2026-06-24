<?php
namespace app\models;

use yii\db\ActiveRecord;

class CaloriesBurned extends ActiveRecord
{
    public static function tableName()
    {
        return 'calories_burned';
    }

    public function rules()
    {
        return [
            [['user_id', 'calories', 'date'], 'required'],
            [['user_id', 'calories'], 'integer', 'min' => 0],
            [['date'], 'safe'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}