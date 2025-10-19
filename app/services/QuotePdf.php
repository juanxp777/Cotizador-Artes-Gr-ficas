<?php
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class QuotePdf
{
    public function __construct(
        private QuoteRepo $repo,
        private Params $params
    ) {}

    /**
     * Renderiza y envía el PDF al navegador.
     * @param string $publicId ID público de la cotización (quotes.public_id)
     */
    public function stream(string $publicId): void
    {
        $q = $this->repo->getByPublicId($publicId);
        if (!$q) {
            http_response_code(404);
            die('Cotización no encontrada.');
        }

        // Parámetros de marca (opcionales en pricing_params)
        $company   = $this->params->get('company_name', 'Tu Empresa de Impresión');
        $logoUrl   = $this->params->get('company_logo_url', ''); // URL absoluta o archivo accesible
        $terms     = $this->params->get('quote_terms', 'Precios sujetos a cambio sin previo aviso. Validez: {VALID_DAYS} días.');
        $validDays = (int)$this->params->getFloat('quote_valid_days', 7);

        $validText = str_replace('{VALID_DAYS}', (string)$validDays, $terms);

        // Enlace público (para QR)
        $publicUrl = $this->publicQuoteUrl($publicId);

        // QR simple (Google Chart API)
        $qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chld=L|0&chl='.rawurlencode($publicUrl);

        $html = $this->buildHtml($q, $company, $logoUrl, $validText, $qrUrl, $publicUrl);

        $dompdf = new Dompdf($this->pdfOptions());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Cotizacion_'.$q['public_id'].'.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
    }

    private function pdfOptions(): Options
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);     // permite logo/QR remotos
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // soporte UTF-8
        return $options;
    }

    private function publicQuoteUrl(string $publicId): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // ruta pública conocida de la vista
        $path   = '/public/quote_view.php?id='.rawurlencode($publicId);
        return $scheme.'://'.$host.$path;
    }

    private function buildHtml(array $q, string $company, string $logoUrl, string $validText, string $qrUrl, string $publicUrl): string
    {
        $res      = $q['result'] ?? [];
        $parts    = $res['parts'] ?? [];
        $ladder   = $q['ladder'] ?? null;

        // Variables pre-calculadas para NO llamar métodos dentro del HEREDOC
        $dateNow     = date('Y-m-d H:i');
        $productType = $this->ucfirstSafe($q['product_type'] ?? '');
        $marginPct   = number_format(((float)($q['margin'] ?? 0))*100, 2, ',', '.');
        $totalCost   = number_format((float)$q['total_cost'], 0, ',', '.');
        $totalPvp    = number_format((float)$q['total_pvp'], 0, ',', '.');
        $taxHtml     = $this->renderTax($q);
        $partsHtml   = $this->renderParts($parts);
        $ladderHtml  = $this->renderLadder($ladder);
        $logoTag     = $logoUrl
            ? '<img class="logo" src="'.htmlspecialchars($logoUrl).'" alt="logo">'
            : '<div style="font-weight:bold;font-size:16px">'.htmlspecialchars($company).'</div>';

        // CSS básico embebido
        $css = <<<CSS
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .wrap { width: 100%; padding: 20px; }
        .header { display: table; width: 100%; }
        .logo { width: 180px; height: 60px; object-fit: contain; }
        .h-left { display: table-cell; vertical-align: middle; }
        .h-right { display: table-cell; vertical-align: top; text-align: right; }
        h1 { font-size: 22px; margin: 4px 0; }
        .meta { color: #555; font-size: 12px; }
        .box { border: 1px solid #ddd; border-radius: 6px; padding: 10px; margin-top: 10px; }
        .grid-3 { width: 100%; border-collapse: collapse; }
        .grid-3 td, .grid-3 th { border: 1px solid #eee; padding: 8px; }
        .grid-3 th { background: #f7f7f7; text-align: left; }
        .tag { display: inline-block; font-size: 10px; padding: 2px 6px; background: #eef8f1; color: #067647; border:1px solid #ccebd7; border-radius: 8px; }
        .subtotal { text-align: right; }
        .totals { width: 100%; margin-top: 10px; }
        .totals td { padding: 6px; }
        .totals .label { color: #555; }
        .totals .value { font-weight: bold; }
        .section-title { font-weight: bold; margin: 18px 0 8px 0; font-size: 14px; }
        .muted { color: #666; font-size: 11px; }
        .qr { width: 120px; height: 120px; }
        .divider { height: 1px; background: #eee; margin: 10px 0; }
        .mb8 { margin-bottom: 8px; }
        .mb12 { margin-bottom: 12px; }
        .mb16 { margin-bottom: 16px; }
        .right { text-align: right; }
        .center { text-align: center; }
        CSS;

        // Cliente (opcionales)
        $customerHtmlParts = [];
        if (!empty($q['customer_name']))  $customerHtmlParts[] = '<div><b>Cliente:</b> '.htmlspecialchars($q['customer_name']).'</div>';
        if (!empty($q['customer_email'])) $customerHtmlParts[] = '<div><b>Email:</b> '.htmlspecialchars($q['customer_email']).'</div>';
        $customerHtml = implode('', $customerHtmlParts);

        // Construcción del HTML final (solo interpolamos variables simples)
        $html = <<<HTML
        <html>
        <head><meta charset="utf-8"><style>$css</style></head>
        <body>
          <div class="wrap">
            <div class="header">
              <div class="h-left">$logoTag</div>
              <div class="h-right">
                <div class="meta"><b>{$this->e($company)}</b></div>
                <div class="meta">Cotización: <b>{$this->e($q['public_id'])}</b></div>
                <div class="meta">Fecha: <b>$dateNow</b></div>
              </div>
            </div>

            <div class="divider"></div>

            <h1>Cotización de impresión</h1>
            <div class="meta mb8">Producto: <b>{$this->e($productType)}</b> &nbsp;•&nbsp; Margen aplicado: <b>$marginPct%</b></div>
            $customerHtml

            <div class="section-title">Resumen económico</div>
            <table class="totals">
              <tr>
                <td class="label">Costo total</td>
                <td class="value right">$ $totalCost</td>
              </tr>
              <tr>
                <td class="label">PVP total sugerido</td>
                <td class="value right">$ $totalPvp</td>
              </tr>
              $taxHtml
            </table>

            <div class="section-title">Detalle por parte</div>
            $partsHtml

            $ladderHtml

            <div class="section-title">Ver en línea</div>
            <table width="100%">
              <tr>
                <td class="muted">Escanea el QR o visita: <br><a href="{$this->e($publicUrl)}">{$this->e($publicUrl)}</a></td>
                <td class="right"><img class="qr" src="{$this->e($qrUrl)}" alt="QR"></td>
              </tr>
            </table>

            <div class="divider"></div>
            <div class="muted">
              {$this->e($validText)}
            </div>
          </div>
        </body>
        </html>
        HTML;

        return $html;
    }

    private function renderTax(array $q): string
    {
        if ($q['tax_pct'] === null) return '';
        $pct = (float)$q['tax_pct'] * 100;
        $base = (float)$q['total_pvp'];
        $taxAmt = $base * (float)$q['tax_pct'];
        $total = $base + $taxAmt;

        $pctFmt   = number_format($pct, 2, ',', '.');
        $taxFmt   = number_format($taxAmt, 0, ',', '.');
        $totalFmt = number_format($total, 0, ',', '.');

        return <<<HTML
            <tr><td class="label">Impuesto ({$pctFmt}%)</td><td class="value right">$ {$taxFmt}</td></tr>
            <tr><td class="label"><b>Total con impuesto</b></td><td class="value right">$ {$totalFmt}</td></tr>
        HTML;
    }

    private function renderParts(array $parts): string
    {
        $map = [
            'cover'   => 'Tapas / Cubiertas',
            'interior'=> 'Hojas Interiores',
            'insert'  => 'Inserto'
        ];
        $blocks = [];

        foreach ($map as $key=>$label) {
            if (empty($parts[$key])) continue;
            $p   = $parts[$key];
            $sel = $p['selected'] ?? 'digital';

            $rows = '';
            foreach (['digital'=>'Digital','offset_q'=>'Offset ¼','offset_m'=>'Offset ½'] as $k=>$name) {
                if (empty($p['options'][$k])) continue;
                $o = $p['options'][$k];

                $mark = ($k===$sel) ? '<span class="tag">Seleccionado</span>' : '';
                $formato = $this->e((string)($o['formato'] ?? ''));
                $ups     = (int)($o['ups'] ?? 0);
                $cost    = number_format((float)($o['total_costo'] ?? 0), 0, ',', '.');
                $pvp     = number_format((float)($o['pvp'] ?? 0), 0, ',', '.');

                $rows .= <<<ROW
                    <tr>
                        <td>{$this->e($name)} {$mark}<br><span class="muted">Formato {$formato} — UPS {$ups}</span></td>
                        <td class="right">$ {$cost}</td>
                        <td class="right">$ {$pvp}</td>
                    </tr>
                ROW;
            }

            $blocks[] = <<<HTML
            <div class="box">
              <div class="mb8"><b>{$this->e($label)}</b></div>
              <table class="grid-3">
                <thead>
                  <tr><th>Tecnología</th><th>Costo total</th><th>PVP</th></tr>
                </thead>
                <tbody>$rows</tbody>
              </table>
            </div>
            HTML;
        }

        return $blocks ? implode('', $blocks) : '<div class="muted">Sin partes para mostrar.</div>';
    }

    private function renderLadder(?array $ladder): string
    {
        if (empty($ladder) || !is_array($ladder)) return '';
        $rows = '';
        foreach ($ladder as $r) {
            $cant = number_format((int)($r['cantidad'] ?? 0), 0, ',', '.');
            $cost = number_format((float)($r['cost'] ?? 0), 0, ',', '.');
            $pvp  = number_format((float)($r['pvp'] ?? 0), 0, ',', '.');
            $rows .= "<tr><td class=\"right\">{$cant}</td><td class=\"right\">$ {$cost}</td><td class=\"right\">$ {$pvp}</td></tr>";
        }
        return <<<HTML
            <div class="section-title">Ladder de cantidades</div>
            <table class="grid-3">
              <thead><tr><th>Cantidad</th><th>Costo total</th><th>PVP sugerido</th></tr></thead>
              <tbody>$rows</tbody>
            </table>
        HTML;
    }

    private function ucfirstSafe(?string $s): string
    {
        if (!$s) return '';
        if (function_exists('mb_strtolower')) {
            $s = mb_strtolower($s, 'UTF-8');
            return mb_strtoupper(mb_substr($s,0,1),'UTF-8').mb_substr($s,1,null,'UTF-8');
        }
        $s = strtolower($s);
        return ucfirst($s);
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
