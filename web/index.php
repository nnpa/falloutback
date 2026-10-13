<?php

defined('YII_DEBUG') or define('YII_DEBUG', getenv('F2O_DEBUG') === '1');
defined('YII_ENV') or define('YII_ENV', getenv('F2O_ENV') ?: 'prod');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
