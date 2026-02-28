<?php

namespace app\models;

use yii\db\ActiveRecord;

class Project extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%projects}}';
    }
}
