<?php

namespace app\controllers;

use app\components\ApiController;
use Yii;
use yii\web\Response;

/**
 * GET /api/health — проверка живости (для nginx/балансировщика).
 * GET /metrics    — метрики в формате Prometheus (раздел 9.10): аккаунты, персонажи, игроки за 5 минут, события журнала.
 */
class HealthController extends ApiController
{
    protected array $publicActions = ['index', 'metrics', 'servers'];

    /** GET /api/servers — список живых шардов (статус для сайта/лаунчера). */
    public function actionServers(): array
    {
        return ['items' => array_map(fn(\app\models\GameServer $s) => [
            'id' => $s->id, 'players' => (int)$s->players, 'locations' => $s->getLocationList(),
        ], \app\models\GameServer::alive())];
    }

    public function actionIndex(): array
    {
        $dbOk = true;
        try { Yii::$app->db->createCommand('SELECT 1')->queryScalar(); } catch (\Throwable $e) { $dbOk = false; }
        if (!$dbOk) Yii::$app->response->statusCode = 503;
        return ['ok' => $dbOk, 'time' => time()];
    }

    public function actionMetrics(): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/plain; version=0.0.4');
        $db = Yii::$app->db;
        $q = fn(string $sql, array $p = []) => (int)$db->createCommand($sql, $p)->queryScalar();
        $lines = [
            '# TYPE f2o_accounts_total gauge',
            'f2o_accounts_total ' . $q('SELECT COUNT(*) FROM {{%user_account}}'),
            '# TYPE f2o_characters_total gauge',
            'f2o_characters_total ' . $q('SELECT COUNT(*) FROM {{%player_character}} WHERE is_deleted = 0'),
            '# TYPE f2o_players_active_5m gauge',
            'f2o_players_active_5m ' . $q('SELECT COUNT(*) FROM {{%player_character}} WHERE last_login_at > :t', [':t' => time() - 300]),
            '# TYPE f2o_game_servers_alive gauge',
            'f2o_game_servers_alive ' . count(\app\models\GameServer::alive()),
            '# TYPE f2o_auction_active_lots gauge',
            'f2o_auction_active_lots ' . $q("SELECT COUNT(*) FROM {{%auction_lot}} WHERE status = 'active' AND expires_at > :t", [':t' => time()]),
            '# TYPE f2o_log_events_1h gauge',
        ];
        foreach ($db->createCommand('SELECT kind, COUNT(*) c FROM {{%game_log}} WHERE ts > :t GROUP BY kind', [':t' => time() - 3600])->queryAll() as $r) {
            $lines[] = 'f2o_log_events_1h{kind="' . preg_replace('/[^a-z_]/', '', $r['kind']) . '"} ' . (int)$r['c'];
        }
        return implode("\n", $lines) . "\n";
    }
}
