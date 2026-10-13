<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Каталог предметов (таблица Items из ТЗ). id — строковый код, общий для C# и PHP.
 *
 * @property string $id
 * @property string $name_ru
 * @property string $name_en
 * @property string $item_type
 * @property int $weight
 * @property int $value
 * @property array|null $data
 */
class Item extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%item}}';
    }

    /** Множество существующих id для быстрой проверки при сохранении инвентаря. */
    public static function knownIds(): array
    {
        static $ids = null;
        if ($ids === null) {
            $ids = array_fill_keys(static::find()->select('id')->column(), true);
        }
        return $ids;
    }
}
