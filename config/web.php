<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'container' => [
        'singletons' => [
            \yii\mail\MailerInterface::class => [
                'class' => \yii\symfonymailer\Mailer::class,
                'useFileTransport' => true,
                'viewPath' => '@app/mail',
            ],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        // УДАЛИТЕ ЭТОТ КОМПОНЕНТ - он создает CORS заголовки
        // 'options' => [
        //     'class' => 'app\components\OptionsHandler',
        // ],
        
        'request' => [
            'cookieValidationKey' => 'asdasdasdad',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => \yii\mail\MailerInterface::class,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        
        // Оставляем пустой response - без CORS
        'response' => [
            'class' => 'yii\web\Response',
        ],
        
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
                'POST user/register' => 'user/register',
                'GET user/validate' => 'user/validate',
                'GET products' => 'product/index',
                'POST products' => 'product/create',
                'PUT products/<id>' => 'product/update',
                'DELETE products/<id>' => 'product/delete',
                'GET foodintake' => 'foodintake/index',
                'POST foodintake' => 'foodintake/create',
                'DELETE foodintake/<id>' => 'foodintake/delete',
                'GET caloriesburned' => 'caloriesburned/index',
                'POST caloriesburned' => 'caloriesburned/save',
                'GET statistics' => 'statistics/index',
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
    ];
}

return $config;