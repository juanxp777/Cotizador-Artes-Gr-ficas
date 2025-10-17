<?php
require_once 'config/database.php';
require_once 'motor_cotizador.php';

// Usaremos una librería simple de PDF
require_once 'tcpdf/tcpdf.php';

if (isset($_GET['cotizacion_id'])) {
    $cotizacion_id = (int)$_GET['cotizacion_id'];
    $cotizacion = obtenerCotizacion($cotizacion_id);
    
    if ($cotizacion) {
        generarPDF($cotizacion);
    }
}

function obtenerCotizacion($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return null;
    
    try {
        $query = "SELECT * FROM cotizaciones WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return null;
    }
}

function generarPDF($cotizacion) {
    // Crear PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurar documento
    $pdf->SetCreator('Cotizador Impresión');
    $pdf->SetAuthor('Sistema de Cotizaciones');
    $pdf->SetTitle('Cotización #' . $cotizacion['id']);
    $pdf->SetSubject('Cotización de Impresión');
    
    // Agregar página
    $pdf->AddPage();
    
    // Contenido del PDF
    $html = '
    <h1 style="text-align: center; color: #333;">COTIZACIÓN DE IMPRESIÓN</h1>
    <p style="text-align: center;">Fecha: ' . date('d/m/Y', strtotime($cotizacion['fecha_creacion'])) . '</p>
    
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px; background: #f8f9fa;"><strong>Número de Cotización</strong></td>
            <td style="padding: 10px;">#' . $cotizacion['id'] . '</td>
        </tr>
        <tr>
            <td style="padding: 10px; background: #f8f9fa;"><strong>Cantidad</strong></td>
            <td style="padding: 10px;">' . number_format($cotizacion['cantidad']) . ' unidades</td>
        </tr>
        <tr>
            <td style="padding: 10px; background: #f8f9fa;"><strong>Páginas</strong></td>
            <td style="padding: 10px;">' . $cotizacion['paginas'] . '</td>
        </tr>
        <tr>
            <td style="padding: 10px; background: #f8f9fa;"><strong>Mejor Opción</strong></td>
            <td style="padding: 10px;">' . $cotizacion['mejor_opcion'] . '</td>
        </tr>
        <tr>
            <td style="padding: 10px; background: #f8f9fa;"><strong>Costo Total</strong></td>
            <td style="padding: 10px; font-weight: bold;">$' . number_format($cotizacion['costo_total'], 0, ',', '.') . '</td>
        </tr>
    </table>
    
    <h2 style="margin-top: 20px;">Detalles Técnicos</h2>
    <p><strong>Tipo de Interior:</strong> ' . ucfirst(str_replace('_', ' ', $cotizacion['tipo_interior'])) . '</p>
    ' . ($cotizacion['acabado'] ? '<p><strong>Acabado:</strong> ' . $cotizacion['acabado'] . '</p>' : '') . '
    
    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <p><strong>Nota:</strong> Esta cotización es válida por 30 días. Los precios pueden variar según disponibilidad de materiales.</p>
    </div>
    ';
    
    // Escribir HTML
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Salida del PDF
    $pdf->Output('cotizacion_' . $cotizacion['id'] . '.pdf', 'I');
}
?>