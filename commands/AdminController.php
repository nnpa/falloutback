<?php

namespace app\commands;

use app\models\AuthToken;
use app\models\GameLog;
use app\models\GameServer;
use app\models\PlayerCharacter;
use app\models\UserAccount;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Администрирование:
 *   php yii admin/ban <логин>          — заблокировать аккаунт и отозвать все сессии
 *   php yii admin/unban <логин>
 *   php yii admin/servers              — живые игровые серверы и их локации
 *   php yii admin/log <имя персонажа>  — последние 50 записей журнала персонажа (разбор жалоб)
 */
class AdminController extends Controller
{
    public function actionBan(string $username): int
    {
        $u = UserAccount::findOne(['username' => $username]);
        if (!$u) { $this->stderr("Аккаунт не найден\n"); return ExitCode::DATAERR; }
        $u->status = UserAccount::STATUS_BANNED;
        $u->save(false, ['status']);
        $n = AuthToken::deleteAll(['user_account_id' => $u->id]);
        $this->stdout("Аккаунт $username заблокирован, отозвано сессий: $n\n");
        return ExitCode::OK;
    }

    public function actionUnban(string $username): int
    {
        $u = UserAccount::findOne(['username' => $username]);
        if (!$u) { $this->stderr("Аккаунт не найден\n"); return ExitCode::DATAERR; }
        $u->status = UserAccount::STATUS_ACTIVE;
        $u->save(false, ['status']);
        $this->stdout("Аккаунт $username разблокирован\n");
        return ExitCode::OK;
    }

    public function actionServers(): int
    {
        $alive = GameServer::alive();
        if (!$alive) $this->stdout("Нет живых серверов (одиночный режим)\n");
        foreach ($alive as $s) {
            $this->stdout(sprintf("%-24s %s:%d  игроков: %d  локации: %s\n", $s->id, $s->host, $s->port, $s->players, implode(', ', $s->getLocationList())));
        }
        return ExitCode::OK;
    }

    public function actionLog(string $characterName): int
    {
        $c = PlayerCharacter::findOne(['name_lower' => PlayerCharacter::nameKey($characterName)]);
        if (!$c) { $this->stderr("Персонаж не найден\n"); return ExitCode::DATAERR; }
        foreach (GameLog::find()->where(['player_character_id' => $c->id])->orderBy(['id' => SORT_DESC])->limit(50)->all() as $l) {
            $this->stdout(date('Y-m-d H:i:s', $l->ts) . "  {$l->kind}  {$l->payload}\n");
        }
        return ExitCode::OK;
    }
}
