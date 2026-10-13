<?php

namespace app\controllers;

use app\components\ApiController;
use app\components\CharacterService;
use app\components\AuctionService;
use app\components\FactionService;
use app\components\ServerKeyFilter;
use app\components\ShardRegistry;
use app\models\AuctionLot;
use app\models\AuthToken;
use app\models\GameLog;
use app\models\PlayerCharacter;
use Yii;

/**
 * API для игровых серверов Unity (X-Server-Key).
 *   POST /api/server/auth       {access_token, character_id} → CharacterDto с инвентарём
 *   POST /api/server/save       ServerSaveRequest            → {ok}
 *   POST /api/server/save-batch {characters:[...]}           → {ok}
 *   POST /api/server/log        {entries:[...]}              → {ok}
 *   POST /api/server/heartbeat  {server_id, host, port, locations[], players} → {ok}
 *   POST /api/server/locate     {location_id, exclude_server_id} → {host, port, server_id}
 *   POST /api/server/faction/{create|join|leave|kick|info}   → FactionDto
 *   POST /api/server/auction/{list|mine|create|buy|cancel|claim}
 */
class ServerController extends ApiController
{
    protected array $publicActions = ['auth', 'save', 'save-batch', 'log', 'heartbeat', 'locate', 'faction', 'auction'];

    public function behaviors(): array
    {
        $b = parent::behaviors();
        unset($b['cors']);
        $b['serverKey'] = ['class' => ServerKeyFilter::class];
        return $b;
    }

    /** Проверка сессии игрока при подключении к игровому серверу. */
    public function actionAuth(): array
    {
        $b = $this->body();
        $token = AuthToken::findValid((string)($b['access_token'] ?? ''));
        if (!$token) {
            Yii::$app->response->statusCode = 401;
            return ['name' => 'Unauthorized', 'message' => 'Сессия истекла, войдите заново', 'status' => 401, 'errors' => []];
        }
        $c = PlayerCharacter::findOne(['id' => (int)($b['character_id'] ?? 0), 'user_account_id' => $token->user_account_id, 'is_deleted' => false]);
        if (!$c) {
            Yii::$app->response->statusCode = 404;
            return ['name' => 'Not found', 'message' => 'Персонаж не найден', 'status' => 404, 'errors' => []];
        }
        $c->last_login_at = time();
        $c->save(false, ['last_login_at']);
        return $c->toDto(true);
    }

    public function actionSave(): array
    {
        $err = CharacterService::saveState($this->body());
        if ($err) {
            Yii::$app->response->statusCode = 422;
            return ['ok' => false, 'error' => $err];
        }
        return ['ok' => true, 'error' => ''];
    }

    public function actionSaveBatch(): array
    {
        $errors = [];
        foreach ((array)($this->body()['characters'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $err = CharacterService::saveState($s);
            if ($err) $errors[] = ($s['character_id'] ?? '?') . ': ' . $err;
        }
        if ($errors) {
            Yii::$app->response->statusCode = 207;
            return ['ok' => false, 'error' => implode('; ', $errors)];
        }
        return ['ok' => true, 'error' => ''];
    }

    public function actionLog(): array
    {
        $rows = [];
        $ip = Yii::$app->request->userIP;
        foreach ((array)($this->body()['entries'] ?? []) as $e) {
            if (!is_array($e)) continue;
            $rows[] = [
                ((int)($e['character_id'] ?? 0)) ?: null,
                substr((string)($e['kind'] ?? 'unknown'), 0, 32),
                mb_substr((string)($e['payload'] ?? ''), 0, 2000),
                (int)($e['ts'] ?? time()),
                $ip,
            ];
            if (count($rows) >= 2000) break;
        }
        if ($rows) {
            Yii::$app->db->createCommand()->batchInsert(GameLog::tableName(), ['player_character_id', 'kind', 'payload', 'ts', 'server_ip'], $rows)->execute();
        }
        return ['ok' => true, 'error' => ''];
    }

    // ======================= Шарды =======================

    public function actionHeartbeat(): array
    {
        $err = ShardRegistry::heartbeat($this->body());
        if ($err) { Yii::$app->response->statusCode = 422; return ['ok' => false, 'error' => $err]; }
        return ['ok' => true, 'error' => ''];
    }

    public function actionLocate(): array
    {
        $b = $this->body();
        $s = ShardRegistry::locate((string)($b['location_id'] ?? ''), (string)($b['exclude_server_id'] ?? ''));
        if (!$s) {
            Yii::$app->response->statusCode = 404;
            return ['name' => 'Not found', 'message' => 'Нет сервера для этой локации', 'status' => 404, 'errors' => []];
        }
        return ['host' => $s->host, 'port' => (int)$s->port, 'server_id' => $s->id, 'location_id' => (string)$b['location_id']];
    }

    // ======================= Фракции =======================

    public function actionFaction(string $op): array
    {
        $b = $this->body();
        $cid = (int)($b['character_id'] ?? 0);
        switch ($op) {
            case 'create': [$dto, $err] = FactionService::create($cid, (string)($b['name'] ?? ''), (string)($b['tag'] ?? '')); break;
            case 'join': [$dto, $err] = FactionService::join($cid, (string)($b['name'] ?? '')); break;
            case 'leave': [$dto, $err] = FactionService::leave($cid); break;
            case 'kick': [$dto, $err] = FactionService::kick($cid, (string)($b['target_name'] ?? '')); break;
            case 'info': [$dto, $err] = FactionService::info($cid); break;
            default: [$dto, $err] = [null, 'Неизвестная операция'];
        }
        if ($err) {
            Yii::$app->response->statusCode = 422;
            return ['name' => 'Unprocessable entity', 'message' => $err, 'status' => 422, 'errors' => []];
        }
        return $dto;
    }

    // ======================= Аукцион =======================

    public function actionAuction(string $op): array
    {
        $b = $this->body();
        $cid = (int)($b['character_id'] ?? 0);
        switch ($op) {
            case 'list': return AuctionService::search($b);
            case 'mine': return AuctionService::mine($cid);
            case 'claim': return AuctionService::claim($cid);
            case 'create': [$lot, $err] = AuctionService::create($b); break;
            case 'buy': [$lot, $err] = AuctionService::buy($cid, (int)($b['lot_id'] ?? 0)); break;
            case 'cancel': [$lot, $err] = AuctionService::cancel($cid, (int)($b['lot_id'] ?? 0)); break;
            default: [$lot, $err] = [null, 'Неизвестная операция'];
        }
        if ($err) {
            Yii::$app->response->statusCode = 409;
            return ['lot' => null, 'error' => $err, 'name' => 'Conflict', 'message' => $err, 'status' => 409, 'errors' => []];
        }
        /** @var AuctionLot $lot */
        return ['lot' => $lot->toDto(), 'error' => ''];
    }
}
