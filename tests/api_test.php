<?php
/**
 * Интеграционный тест REST API без веб-сервера и MySQL: Yii2 + SQLite в памяти.
 *   php tests/api_test.php                      (Yii из vendor/)
 *   YII_PATH=/path/to/yii2/framework php tests/api_test.php
 *
 * Проверяет полный путь: регистрация → вход → проверка имени → экран создания персонажа (валидация) →
 * список → авторизация игрового сервера → сохранение состояния (транзакция) → журнал.
 */

define('YII_DEBUG', true);
define('YII_ENV', 'test');
$root = dirname(__DIR__);
$yiiPath = getenv('YII_PATH') ?: "$root/vendor/yiisoft/yii2";
if (!is_file("$yiiPath/Yii.php")) { echo "Yii2 не найден ($yiiPath). Выполните composer install или задайте YII_PATH.\n"; exit(0); }
if (is_file("$root/vendor/autoload.php")) require "$root/vendor/autoload.php";
require "$yiiPath/Yii.php";
Yii::setAlias('@app', $root);
spl_autoload_register(function ($c) use ($root) {
    if (str_starts_with($c, 'app\\')) { $f = $root . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($f)) require $f; }
});

putenv('F2O_SERVER_KEY=test-key');
$config = require "$root/config/web.php";
$config['components']['db'] = ['class' => 'yii\db\Connection', 'dsn' => 'sqlite::memory:'];
$config['components']['cache'] = ['class' => 'yii\caching\ArrayCache'];
$config['components']['log'] = ['targets' => []];
$config['params']['serverKey'] = 'test-key';
$config['bootstrap'] = [];
unset($config['modules']);
$app = new yii\web\Application($config);

// ---------- Схема (упрощённый аналог миграции для SQLite) ----------
$db = Yii::$app->db;
foreach ([
    'CREATE TABLE user_account (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, auth_key TEXT NOT NULL, status INTEGER NOT NULL DEFAULT 10, created_at INTEGER, updated_at INTEGER, last_login_at INTEGER, last_login_ip TEXT)',
    'CREATE TABLE auth_token (id INTEGER PRIMARY KEY AUTOINCREMENT, user_account_id INTEGER NOT NULL, token_hash TEXT UNIQUE NOT NULL, expires_at INTEGER NOT NULL, created_at INTEGER NOT NULL, ip TEXT)',
    'CREATE TABLE player_character (id INTEGER PRIMARY KEY AUTOINCREMENT, user_account_id INTEGER NOT NULL, name TEXT NOT NULL UNIQUE, name_lower TEXT NOT NULL UNIQUE, level INTEGER, experience INTEGER, strength INTEGER, perception INTEGER, endurance INTEGER, charisma INTEGER, intelligence INTEGER, agility INTEGER, luck INTEGER, tag_skills TEXT, traits TEXT, skill_points_spent TEXT, unspent_skill_points INTEGER, current_hp INTEGER, max_hp INTEGER, location_id TEXT, hex_q INTEGER, hex_r INTEGER, active_hand TEXT, karma INTEGER DEFAULT 0, is_deleted INTEGER DEFAULT 0, created_at INTEGER, updated_at INTEGER, last_login_at INTEGER, perks TEXT, pending_perks INTEGER DEFAULT 0, entry_from TEXT, faction_id INTEGER, faction_rank TEXT)',
    'CREATE TABLE item (id TEXT PRIMARY KEY, name_ru TEXT, name_en TEXT, item_type TEXT, weight INTEGER, value INTEGER, data TEXT)',
    'CREATE TABLE inventory_item (id INTEGER PRIMARY KEY AUTOINCREMENT, player_character_id INTEGER NOT NULL, item_id TEXT NOT NULL, quantity INTEGER, item_condition INTEGER, slot TEXT, ammo_count INTEGER, ammo_item_id TEXT, sort_order INTEGER)',
    'CREATE TABLE game_log (id INTEGER PRIMARY KEY AUTOINCREMENT, player_character_id INTEGER, kind TEXT, payload TEXT, ts INTEGER, server_ip TEXT)',
    'CREATE TABLE dialog_var (player_character_id INTEGER, npc_id TEXT, var_key TEXT, value INTEGER, PRIMARY KEY (player_character_id, npc_id, var_key))',
    'CREATE TABLE faction (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, name_lower TEXT UNIQUE, tag TEXT, tag_lower TEXT UNIQUE, leader_character_id INTEGER, created_at INTEGER)',
    "CREATE TABLE auction_lot (id INTEGER PRIMARY KEY AUTOINCREMENT, seller_character_id INTEGER, item_id TEXT, quantity INTEGER, item_condition INTEGER, price INTEGER, status TEXT DEFAULT 'active', buyer_character_id INTEGER, created_at INTEGER, closed_at INTEGER, ammo_count INTEGER DEFAULT 0, ammo_item_id TEXT, expires_at INTEGER DEFAULT 0, payout_claimed INTEGER DEFAULT 0, item_returned INTEGER DEFAULT 0)",
    'CREATE TABLE game_server (id TEXT PRIMARY KEY, host TEXT, port INTEGER, locations TEXT, players INTEGER, last_heartbeat INTEGER)',
] as $sql) $db->createCommand($sql)->execute();
foreach (app\components\rules\ItemCatalog::all() as $it) {
    $db->createCommand()->insert('item', ['id' => $it['id'], 'name_ru' => $it['name_ru'], 'name_en' => $it['name_en'], 'item_type' => $it['item_type'], 'weight' => $it['weight'], 'value' => $it['value'], 'data' => json_encode($it['data'])])->execute();
}

// ---------- Мини-клиент ----------
function call(string $route, array $body = [], array $headers = [], array $query = []): array
{
    Yii::$app->set('request', new yii\web\Request(['parsers' => ['application/json' => 'yii\web\JsonParser'], 'enableCookieValidation' => false, 'enableCsrfValidation' => false]));
    Yii::$app->set('response', new yii\web\Response(['format' => yii\web\Response::FORMAT_JSON]));
    Yii::$app->user->setIdentity(null);
    Yii::$app->request->setBodyParams($body);
    Yii::$app->request->setQueryParams($query);
    foreach ($headers as $k => $v) Yii::$app->request->headers->set($k, $v);
    try {
        $data = Yii::$app->runAction($route, $query);
        return [Yii::$app->response->statusCode, $data];
    } catch (yii\web\HttpException $e) {
        return [$e->statusCode, ['message' => $e->getMessage()]];
    }
}

$failed = 0; $passed = 0;
function ok(string $name, bool $cond, $info = null): void
{
    global $failed, $passed;
    if ($cond) { $passed++; return; }
    $failed++;
    echo "FAIL: $name\n" . ($info !== null ? '  ' . json_encode($info, JSON_UNESCAPED_UNICODE) . "\n" : '');
}

// ---------- Сценарий ----------
[$st, $r] = call('auth/register', ['username' => 'vault_dweller', 'email' => 'v@example.com', 'password' => 'secret123']);
ok('регистрация 201', $st === 201, $r);
ok('токен выдан', strlen($r['access_token'] ?? '') === 48, $r);

[$st, $r] = call('auth/register', ['username' => 'vault_dweller', 'email' => 'x@example.com', 'password' => 'secret123']);
ok('повтор логина → 422', $st === 422 && ($r['errors'][0]['field'] ?? '') === 'username', $r);

[$st, $r] = call('auth/login', ['username' => 'vault_dweller', 'password' => 'wrong']);
ok('неверный пароль → 401', $st === 401, $r);

[$st, $r] = call('auth/login', ['username' => 'vault_dweller', 'password' => 'secret123']);
ok('вход', $st === 200 && !empty($r['access_token']), $r);
$auth = ['Authorization' => 'Bearer ' . $r['access_token']];

[$st, $r] = call('character/index', [], []);
ok('без токена → 401', $st === 401, $r);

[$st, $r] = call('character/check-name', [], $auth, ['name' => 'Избранный']);
ok('имя свободно', $st === 200 && $r['available'] === true, $r);

$bad = ['name' => 'Избранный', 'stats' => [5, 5, 5, 5, 5, 5, 5], 'tag_skills' => ['small_guns', 'speech'], 'traits' => []];
[$st, $r] = call('character/create', $bad, $auth);
ok('нераспределённые очки и 2 тега → 422', $st === 422 && count($r['errors']) >= 2, $r);

$good = ['name' => 'Избранный', 'stats' => [6, 6, 6, 6, 6, 5, 5], 'tag_skills' => ['small_guns', 'speech', 'lockpick'], 'traits' => ['gifted', 'small_frame']];
[$st, $r] = call('character/create', $good, $auth);
ok('создание персонажа 201', $st === 201, $r);
$charId = $r['id'] ?? 0;
// Одарённый: все +1 → ST7 EN7 → HP 15+7+14 = 36
ok('HP пересчитан сервером', ($r['max_hp'] ?? 0) === 36 && $r['current_hp'] === 36, $r);
ok('стартовый инвентарь', count($r['inventory'] ?? []) === 4, $r['inventory'] ?? null);
ok('Шустрик: груз 25+15×СИЛ', ($r['derived']['carry_weight'] ?? 0) === 25 + 15 * 7, $r['derived'] ?? null);

[$st, $r] = call('character/create', array_merge($good, ['name' => 'избранный']), $auth);
ok('имя уникально без учёта регистра → 422', $st === 422 && ($r['errors'][0]['field'] ?? '') === 'name', $r);

[$st, $r] = call('character/index', [], $auth);
ok('список персонажей', $st === 200 && count($r['items']) === 1 && $r['items'][0]['name'] === 'Избранный', $r);

// ---------- Игровой сервер ----------
$token = substr($auth['Authorization'], 7);
[$st, $r] = call('server/auth', ['access_token' => $token, 'character_id' => $charId], ['X-Server-Key' => 'wrong']);
ok('неверный ключ сервера → 403', $st === 403, $r);

$srv = ['X-Server-Key' => 'test-key'];
[$st, $r] = call('server/auth', ['access_token' => $token, 'character_id' => $charId], $srv);
ok('сервер авторизовал игрока', $st === 200 && $r['id'] === $charId && count($r['inventory']) === 4, $r);

$save = [
    'character_id' => $charId, 'level' => 2, 'experience' => 1200, 'skill_points_spent' => array_fill(0, 18, 1), 'unspent_skill_points' => 3,
    'current_hp' => 20, 'max_hp' => 40, 'location_id' => 'wasteland_1', 'hex_q' => 5, 'hex_r' => 7, 'active_hand' => 'item2',
    'inventory' => [
        ['uid' => 1, 'item_id' => '10mm_pistol', 'quantity' => 1, 'condition' => 87, 'slot' => 'item2', 'ammo_count' => 7, 'ammo_item_id' => 'ammo_10mm_jhp'],
        ['uid' => 2, 'item_id' => 'caps', 'quantity' => 250, 'condition' => 100, 'slot' => '', 'ammo_count' => 0, 'ammo_item_id' => ''],
    ],
];
[$st, $r] = call('server/save', $save, $srv);
ok('сохранение состояния', $st === 200 && $r['ok'] === true, $r);

[$st, $r] = call('server/save', array_merge($save, ['inventory' => [['item_id' => 'bfg9000', 'quantity' => 1, 'slot' => '']]]), $srv);
ok('неизвестный предмет отклонён', $st === 422 && $r['ok'] === false, $r);
[$st, $r] = call('character/view', [], $auth, ['id' => $charId]);
ok('после отказа инвентарь не повреждён', $st === 200 && count($r['inventory']) === 2 && $r['inventory'][0]['condition'] === 87, $r);
ok('позиция и уровень сохранены', $r['location_id'] === 'wasteland_1' && $r['hex_q'] === 5 && $r['level'] === 2 && $r['active_hand'] === 'item2', $r);

[$st, $r] = call('server/save', array_merge($save, ['inventory' => [
    ['item_id' => 'crowbar', 'quantity' => 1, 'slot' => 'item1'], ['item_id' => 'combat_knife', 'quantity' => 1, 'slot' => 'item1']]]), $srv);
ok('два предмета в одном слоте отклонены', $st === 422, $r);

[$st, $r] = call('server/save-batch', ['characters' => [$save]], $srv);
ok('пакетное сохранение', $st === 200 && $r['ok'] === true, $r);

[$st, $r] = call('server/log', ['entries' => [['character_id' => $charId, 'kind' => 'attack', 'payload' => 'test', 'ts' => time()]]], $srv);
ok('журнал', $st === 200 && (int)$db->createCommand('SELECT COUNT(*) FROM game_log')->queryScalar() === 1, $r);


// ======================== Этапы 6–8 и шарды ========================
[$st, $r] = call('auth/register', ['username' => 'second', 'email' => 's@example.com', 'password' => 'secret123']);
$auth2 = ['Authorization' => 'Bearer ' . $r['access_token']];
$token2 = $r['access_token'];
[$st, $r] = call('character/create', ['name' => 'Торгаш', 'stats' => [5, 5, 5, 8, 6, 6, 5], 'tag_skills' => ['barter', 'speech', 'steal'], 'traits' => []], $auth2);
ok('второй персонаж', $st === 201, $r);
$char2 = $r['id'];

// Перки, карма, переменные (Unicum/квесты/карта), точка входа
$save2 = array_merge($save, ['perks' => ['toughness', 'toughness', 'thief'], 'pending_perks' => 1, 'karma' => -15,
    'vars' => [['npc_id' => 'arroyo_guard', 'var_key' => 'trusted', 'value' => 1], ['npc_id' => '_map', 'var_key' => 'klamath', 'value' => 1]],
    'entry_from' => 'worldmap', 'location_id' => 'worldmap', 'hex_q' => 550, 'hex_r' => 350]);
[$st, $r] = call('server/save', $save2, $srv);
ok('сохранение перков и переменных', $st === 200 && $r['ok'] === true, $r);
[$st, $r] = call('server/auth', ['access_token' => $token2, 'character_id' => $charId], $srv);
ok('чужой персонаж по токену → 404', $st === 404, $r);
[$st, $r] = call('character/view', [], $auth, ['id' => $charId]);
ok('перки/карма/переменные вернулись', ($r['perks'] ?? null) === ['toughness', 'toughness', 'thief'] && $r['pending_perks'] === 1 && $r['karma'] === -15
    && count($r['vars']) === 2 && $r['entry_from'] === 'worldmap' && $r['location_id'] === 'worldmap', $r);
ok('производные учитывают перки (Крепкая шкура ×2)', ($r['derived']['damage_resistance'] ?? -1) === 20, $r['derived'] ?? null);
[$st, $r] = call('server/save', array_merge($save2, ['perks' => ['god_mode']]), $srv);
ok('неизвестный перк отклонён', $st === 422, $r);

// ---------- Фракции ----------
[$st, $r] = call('server/faction', ['character_id' => $charId, 'name' => 'Братство Арройо', 'tag' => 'BRA'], $srv, ['op' => 'create']);
ok('фракция создана', $st === 200 && $r['tag'] === 'BRA' && $r['leader_character_id'] === $charId, $r);
$factionId = $r['id'] ?? 0;
[$st, $r] = call('server/faction', ['character_id' => $char2, 'name' => 'братство арройо', 'tag' => 'XYZ'], $srv, ['op' => 'create']);
ok('название фракции уникально без учёта регистра', $st === 422, $r);
[$st, $r] = call('server/faction', ['character_id' => $char2, 'name' => 'Братство Арройо'], $srv, ['op' => 'join']);
ok('вступление во фракцию', $st === 200 && count($r['members']) === 2, $r);
[$st, $r] = call('server/auth', ['access_token' => $token2, 'character_id' => $char2], $srv);
ok('DTO содержит фракцию', $r['faction_tag'] === 'BRA' && $r['faction_rank'] === 'member', $r);
[$st, $r] = call('server/faction', ['character_id' => $char2, 'target_name' => 'Избранный'], $srv, ['op' => 'kick']);
ok('не лидер не может исключать', $st === 422, $r);
[$st, $r] = call('server/faction', ['character_id' => $charId], $srv, ['op' => 'leave']);
ok('лидер ушёл → лидерство перешло', $st === 200 && $r['leader_character_id'] === $char2, $r);
[$st, $r] = call('faction/index', []);
ok('публичный список фракций', $st === 200 && $r['items'][0]['members'] === 1, $r);
[$st, $r] = call('server/faction', ['character_id' => $char2], $srv, ['op' => 'leave']);
ok('последний участник ушёл → фракция удалена', $st === 200 && (int)$db->createCommand('SELECT COUNT(*) FROM faction')->queryScalar() === 0, $r);

// ---------- Аукцион ----------
[$st, $r] = call('server/auction', ['character_id' => $charId, 'item_id' => 'desert_eagle', 'quantity' => 1, 'condition' => 80, 'price' => 1000], $srv, ['op' => 'create']);
ok('лот создан', $st === 200 && $r['lot']['status'] === 'active', $r);
$lotId = $r['lot']['id'] ?? 0;
[$st, $r] = call('server/auction', ['character_id' => $charId, 'item_id' => 'caps', 'quantity' => 1, 'price' => 10], $srv, ['op' => 'create']);
ok('крышки продавать нельзя', $st === 409, $r);
[$st, $r] = call('server/auction', ['character_id' => $char2, 'search' => 'Eagle'], $srv, ['op' => 'list']);
ok('поиск лота', $st === 200 && count($r['items']) === 1 && $r['items'][0]['seller_name'] === 'Избранный', $r);
[$st, $r] = call('server/auction', ['character_id' => $charId, 'lot_id' => $lotId], $srv, ['op' => 'buy']);
ok('свой лот купить нельзя', $st === 409, $r);
[$st, $r] = call('server/auction', ['character_id' => $char2, 'lot_id' => $lotId], $srv, ['op' => 'buy']);
ok('покупка', $st === 200 && $r['lot']['status'] === 'sold' && $r['lot']['condition'] === 80, $r);
[$st, $r] = call('server/auction', ['character_id' => $char2, 'lot_id' => $lotId], $srv, ['op' => 'buy']);
ok('повторная покупка того же лота невозможна', $st === 409, $r);
[$st, $r] = call('server/auction', ['character_id' => $charId], $srv, ['op' => 'claim']);
ok('выплата продавцу за вычетом 6%', $st === 200 && $r['caps'] === 940 && $r['sold_count'] === 1, $r);
[$st, $r] = call('server/auction', ['character_id' => $charId], $srv, ['op' => 'claim']);
ok('повторная выплата — ноль', $r['caps'] === 0, $r);
[$st, $r] = call('server/auction', ['character_id' => $charId, 'item_id' => 'stimpak', 'quantity' => 3, 'price' => 300], $srv, ['op' => 'create']);
$lot2 = $r['lot']['id'];
[$st, $r] = call('server/auction', ['character_id' => $charId, 'lot_id' => $lot2], $srv, ['op' => 'cancel']);
ok('снятие лота', $st === 200 && $r['lot']['quantity'] === 3, $r);
[$st, $r] = call('server/auction', ['character_id' => $char2, 'lot_id' => $lot2], $srv, ['op' => 'buy']);
ok('снятый лот не купить', $st === 409, $r);
[$st, $r] = call('server/auction', ['character_id' => $charId, 'item_id' => 'rope', 'quantity' => 1, 'price' => 50], $srv, ['op' => 'create']);
$db->createCommand()->update('auction_lot', ['expires_at' => time() - 1], ['id' => $r['lot']['id']])->execute();
[$st, $r] = call('server/auction', ['character_id' => $charId], $srv, ['op' => 'claim']);
ok('истёкший лот возвращён', count($r['returned']) === 1 && $r['returned'][0]['item_id'] === 'rope', $r);
for ($i = 0; $i < 30; $i++) call('server/auction', ['character_id' => $char2, 'item_id' => 'rock', 'quantity' => 1, 'price' => 1], $srv, ['op' => 'create']);
[$st, $r] = call('server/auction', ['character_id' => $char2, 'item_id' => 'rock', 'quantity' => 1, 'price' => 1], $srv, ['op' => 'create']);
ok('лимит 30 лотов', $st === 409, $r);
[$st, $r] = call('auction/index', [], [], ['search' => 'Камень']);
ok('публичный просмотр с пагинацией', $st === 200 && count($r['items']) === 20 && $r['total_pages'] === 2, ['n' => count($r['items'] ?? []), 'p' => $r['total_pages'] ?? null]);

// ---------- Шарды ----------
[$st, $r] = call('character/server', [], $auth, ['id' => $charId]);
ok('одиночный режим: пустой адрес', $st === 200 && $r['host'] === '', $r);
[$st, $r] = call('server/heartbeat', ['server_id' => 'north', 'host' => '10.0.0.1', 'port' => 7777, 'locations' => ['arroyo', 'wasteland_1'], 'players' => 5], $srv);
ok('heartbeat 1', $st === 200, $r);
[$st, $r] = call('server/heartbeat', ['server_id' => 'world', 'host' => '10.0.0.2', 'port' => 7778, 'locations' => ['worldmap', 'klamath'], 'players' => 1], $srv);
[$st, $r] = call('server/locate', ['location_id' => 'klamath'], $srv);
ok('поиск шарда по локации', $st === 200 && $r['server_id'] === 'world' && $r['port'] === 7778, $r);
[$st, $r] = call('server/locate', ['location_id' => 'enc_105'], $srv);
ok('встречи обслуживает шард карты мира', $st === 200 && $r['server_id'] === 'world', $r);
[$st, $r] = call('server/locate', ['location_id' => 'arroyo', 'exclude_server_id' => 'north'], $srv);
ok('исключение своего шарда', $st === 404, $r);
[$st, $r] = call('character/server', [], $auth, ['id' => $charId]);
ok('персонаж на карте мира → шард карты мира', $st === 200 && $r['host'] === '10.0.0.2', $r);
$db->createCommand()->update('game_server', ['last_heartbeat' => time() - 100], ['id' => 'world'])->execute();
[$st, $r] = call('character/server', [], $auth, ['id' => $charId]);
ok('шард недоступен → стартовый город', $st === 200 && $r['server_id'] === 'north', $r);

[$st, $r] = call('auth/logout', [], $auth);
[$st, $r] = call('character/index', [], $auth);
ok('после выхода токен недействителен', $st === 401, $r);

echo "\nПройдено: $passed, провалено: $failed\n";
exit($failed > 0 ? 1 : 0);
