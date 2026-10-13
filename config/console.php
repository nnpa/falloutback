<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

return [
    'id' => 'f2o-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'components' => [
        'cache' => ['class' => 'yii\caching\FileCache'],
        'log' => ['targets' => [['class' => 'yii\log\FileTarget', 'levels' => ['error', 'warning']]]],
        'db' => $db,
    ],
    'controllerMap' => [
        'migrate' => ['class' => 'yii\console\controllers\MigrateController', 'migrationPath' => '@app/migrations'],
    ],
    'params' => $params,
];
