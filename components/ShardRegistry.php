<?php

namespace app\components;

use app\models\GameServer;
use app\models\PlayerCharacter;

/**
 * Реестр шардов: какой игровой сервер обслуживает какую локацию (раздел 9.8 ТЗ).
 */
final class ShardRegistry
{
    public const WORLD_MAP = 'worldmap';
    public const ENCOUNTER_PREFIX = 'enc_';

    public static function heartbeat(array $b): ?string
    {
        $id = substr(trim((string)($b['server_id'] ?? '')), 0, 64);
        $host = substr(trim((string)($b['host'] ?? '')), 0, 255);
        $port = (int)($b['port'] ?? 0);
        if ($id === '' || $host === '' || $port < 1 || $port > 65535) return 'Некорректные данные сервера';
        $locations = array_values(array_filter(array_map(fn($l) => substr((string)$l, 0, 64), (array)($b['locations'] ?? []))));
        $s = GameServer::findOne($id) ?? new GameServer(['id' => $id]);
        $s->host = $host;
        $s->port = $port;
        $s->locations = GameServer::getDb()->driverName === 'mysql' ? $locations : json_encode($locations);
        $s->players = max(0, (int)($b['players'] ?? 0));
        $s->last_heartbeat = time();
        $s->save(false);
        return null;
    }

    /** Живой сервер, обслуживающий локацию (наименее загруженный). */
    public static function locate(string $locationId, string $exclude = ''): ?GameServer
    {
        if (str_starts_with($locationId, self::ENCOUNTER_PREFIX)) $locationId = self::WORLD_MAP;
        $best = null;
        foreach (GameServer::alive() as $s) {
            if ($exclude !== '' && $s->id === $exclude) continue;
            if (!in_array($locationId, $s->getLocationList(), true)) continue;
            if (!$best || $s->players < $best->players) $best = $s;
        }
        return $best;
    }

    /**
     * Адрес сервера для входа персонажа. Если в реестре нет ни одного живого сервера (одиночный режим) —
     * пустой адрес: клиент подключится к серверу по умолчанию.
     * @return array [ответ, http-статус]
     */
    public static function forCharacter(PlayerCharacter $c): array
    {
        $alive = GameServer::alive();
        if (!$alive) return [['host' => '', 'port' => 0, 'server_id' => '', 'location_id' => $c->location_id], 200];
        $s = self::locate((string)$c->location_id);
        if (!$s) $s = self::locate('arroyo'); // локация недоступна — в стартовый город
        if (!$s) return [['name' => 'Service unavailable', 'message' => 'Игровой сервер локации недоступен, попробуйте позже', 'status' => 503, 'errors' => []], 503];
        return [['host' => $s->host, 'port' => (int)$s->port, 'server_id' => $s->id, 'location_id' => $c->location_id], 200];
    }
}
