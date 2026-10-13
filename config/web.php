<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'f2o-api',
    'basePath' => dirname(__DIR__),
    'language' => 'ru-RU',
    'timeZone' => 'UTC',
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfValidation' => false,
            'parsers' => ['application/json' => 'yii\web\JsonParser'],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
            'on beforeSend' => function ($event) {
                // Единый формат ошибок: {"name","message","status","errors":[{field,message}]}
                $r = $event->sender;
                if (!$r->isSuccessful && is_array($r->data) && !isset($r->data['errors'])) {
                    $r->data = [
                        'name' => $r->data['name'] ?? 'Error',
                        'message' => $r->data['message'] ?? 'Ошибка',
                        'status' => $r->statusCode,
                        'errors' => [],
                    ];
                }
            },
        ],
        'cache' => ['class' => 'yii\caching\FileCache'],
        'user' => [
            'identityClass' => 'app\models\UserAccount',
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'errorHandler' => ['errorAction' => null],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                ['class' => 'yii\log\FileTarget', 'levels' => ['error', 'warning']],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/auth/register' => 'auth/register',
                'POST api/auth/login' => 'auth/login',
                'POST api/auth/logout' => 'auth/logout',

                'GET api/characters' => 'character/index',
                'GET api/characters/check-name' => 'character/check-name',
                'POST api/characters' => 'character/create',
                'GET api/characters/<id:\d+>' => 'character/view',
                'DELETE api/characters/<id:\d+>' => 'character/delete',
                'GET api/characters/<id:\d+>/server' => 'character/server',

                'GET api/factions' => 'faction/index',
                'GET api/factions/<id:\d+>' => 'faction/view',
                'GET api/auction' => 'auction/index',
                'GET api/servers' => 'health/servers',

                'GET api/items' => 'item/index',

                // Только для игровых серверов (X-Server-Key)
                'POST api/server/auth' => 'server/auth',
                'POST api/server/save' => 'server/save',
                'POST api/server/save-batch' => 'server/save-batch',
                'POST api/server/log' => 'server/log',
                'POST api/server/heartbeat' => 'server/heartbeat',
                'POST api/server/locate' => 'server/locate',
                'POST api/server/faction/<op:\w+>' => 'server/faction',
                'POST api/server/auction/<op:\w+>' => 'server/auction',

                'GET api/health' => 'health/index',
                'GET metrics' => 'health/metrics',
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV && class_exists('yii\debug\Module')) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = ['class' => 'yii\debug\Module', 'allowedIPs' => ['127.0.0.1', '::1']];
}

return $config;
