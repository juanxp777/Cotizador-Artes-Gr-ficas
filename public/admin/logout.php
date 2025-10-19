<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Services\Auth;

$auth = new Auth(db());
$auth->logout();
header('Location: /public/admin/login.php');
exit;
