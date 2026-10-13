<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => getenv('F2O_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=f2o',
    'username' => getenv('F2O_DB_USER') ?: 'root',
    'password' => getenv('F2O_DB_PASS') ?: '',
    'charset' => 'utf8mb4',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
];
