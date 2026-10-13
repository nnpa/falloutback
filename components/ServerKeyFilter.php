<?php

namespace app\components;

use Yii;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

/**
 * Доступ только для игровых серверов: заголовок X-Server-Key (общий секрет) и, опционально, IP из списка.
 */
class ServerKeyFilter extends ActionFilter
{
    public function beforeAction($action)
    {
        $expected = (string)Yii::$app->params['serverKey'];
        $given = (string)Yii::$app->request->headers->get('X-Server-Key');
        if ($expected === '' || !hash_equals($expected, $given)) {
            throw new ForbiddenHttpException('Неверный ключ игрового сервера');
        }
        $ips = Yii::$app->params['serverIps'];
        if ($ips && !in_array(Yii::$app->request->userIP, $ips, true)) {
            throw new ForbiddenHttpException('IP игрового сервера не разрешён');
        }
        return parent::beforeAction($action);
    }
}
