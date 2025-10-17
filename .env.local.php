<?php
// .env.local.php - SOLO PARA DESARROLLO LOCAL
putenv('APP_DEBUG=1');
putenv('APP_TZ=America/Bogota');

putenv('DB_HOST=127.0.0.1');
putenv('DB_NAME=cotizador');      // crea esta base en phpMyAdmin o usa la que importaste
putenv('DB_USER=root');
putenv('DB_PASS=');
putenv('DB_CHARSET=utf8mb4');
putenv('ADMIN_USER=admin');
putenv('ADMIN_PASS_HASH=$2y$10$T3YJ3o...'); // usa password_hash('tuClaveSegura', PASSWORD_DEFAULT)

