<?php

return [
    // Общий секрет игровых серверов (заголовок X-Server-Key). Задаётся через переменную окружения.
    'serverKey' => getenv('F2O_SERVER_KEY') ?: 'dev-secret-0123456789abcdef0123456789',
    // Необязательный список IP игровых серверов; пусто — без ограничения по IP.
    'serverIps' => array_filter(explode(',', getenv('F2O_SERVER_IPS') ?: '')),
    // Время жизни токена сессии игрока, сек.
    'tokenTtl' => 7 * 24 * 3600,
    'maxCharactersPerAccount' => 3,
    'startLocation' => 'sweetwater',
    // Ограничение регистраций/входов с одного IP (в минуту)
    'authRateLimit' => 10,
];
