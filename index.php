<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Prueba Bootstrap</title>
</head>
<body class="bg-gray-50">
  <div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-2">Bootstrap OK</h1>
    <p class="text-gray-600">Conexión BD y helpers cargados correctamente.</p>
    <p class="mt-2 text-sm">Token CSRF: <code><?= htmlspecialchars(csrf_token()) ?></code></p>
  </div>
</body>
</html>
