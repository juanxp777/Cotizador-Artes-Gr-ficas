<?php
require __DIR__.'/config.php';
if (is_file(__DIR__.'/.env.local.php')) require __DIR__.'/.env.local.php';

try {
  $pdo = db();
  echo "✅ Conexión OK a ".DB_NAME;
} catch (Throwable $e) {
  echo "❌ Error DB: ".htmlspecialchars($e->getMessage());
}
