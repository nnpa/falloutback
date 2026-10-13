<?php

namespace app\commands;

use app\components\rules\ItemCatalog;
use app\models\AuthToken;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * php yii seed/items     — обновить каталог предметов из ItemCatalog (после правок баланса)
 * php yii seed/cleanup   — удалить просроченные токены и журнал старше 90 дней (cron раз в сутки)
 */
class SeedController extends Controller
{
    public function actionItems(): int
    {
        $n = 0;
        foreach (ItemCatalog::all() as $it) {
            Yii::$app->db->createCommand()->upsert('{{%item}}', [
                'id' => $it['id'], 'name_ru' => $it['name_ru'], 'name_en' => $it['name_en'], 'item_type' => $it['item_type'],
                'weight' => $it['weight'], 'value' => $it['value'], 'data' => json_encode($it['data'], JSON_UNESCAPED_UNICODE),
            ])->execute();
            $n++;
        }
        $this->stdout("Предметов обновлено: $n\n");
        return ExitCode::OK;
    }

    public function actionCleanup(): int
    {
        $tokens = AuthToken::purgeExpired();
        $logs = Yii::$app->db->createCommand()->delete('{{%game_log}}', ['<', 'ts', time() - 90 * 86400])->execute();
        $this->stdout("Удалено токенов: $tokens, записей журнала: $logs\n");
        return ExitCode::OK;
    }
}
