<?php
require_once __DIR__ . '/../../bootstrap.php';
use function App\Helpers\admin_logout;

admin_logout();
header('Location: /public/admin/login.php');
exit;
