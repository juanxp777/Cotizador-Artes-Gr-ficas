<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Services\{QuoteRepo, Params, QuotePdf};

$publicId = $_GET['id'] ?? '';
if (!$publicId) {
    http_response_code(400);
    die('Falta id');
}

$repo   = new QuoteRepo(db());
$params = new Params(db());
$pdf    = new QuotePdf($repo, $params);

$pdf->stream($publicId);
