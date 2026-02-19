<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: ../../public/login.php');
    exit;
}

$usuario = Sesion::obtenerUsuarioActual();
$rol = $usuario['rol'] ?? '';

// Solo admin y recepcionista pueden generar PDFs de facturas
if (!in_array($rol, ['administrador', 'recepcionista'])) {
    header('Location: ../../public/login.php');
    exit;
}

$db = DB::conn();
$factura_id = intval($_GET['id'] ?? 0);

if ($factura_id === 0) {
    die("ID de factura inválido");
}

// Obtener datos completos de la factura
try {
    // Datos principales
    $stmt = $db->prepare("
        SELECT 
            f.id,
            f.numero_factura,
            f.fecha_factura,
            f.subtotal,
            f.impuestos,
            f.descuento,
            f.total,
            f.estado,
            f.metodo_pago,
            CONCAT(c.nombre, ' ', c.apellido) AS cliente,
            c.telefono AS cliente_telefono,
            c.correo AS cliente_correo,
            c.direccion AS cliente_direccion
        FROM facturas f
        INNER JOIN clientes c ON f.cliente_id = c.id
        WHERE f.id = :id
    ");
    $stmt->bindValue(':id', $factura_id);
    $stmt->execute();
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        die("Factura no encontrada");
    }
    
    // Detalles (items)
    $stmt = $db->prepare("
        SELECT 
            df.tipo,
            CASE 
                WHEN df.tipo = 'servicio' THEN s.nombre
                WHEN df.tipo = 'producto' THEN p.nombre
            END AS item,
            df.cantidad,
            df.precio_unitario,
            df.subtotal
        FROM detalle_facturas df
        LEFT JOIN servicios s ON df.servicio_id = s.id
        LEFT JOIN productos p ON df.producto_id = p.id
        WHERE df.factura_id = :id
    ");
    $stmt->bindValue(':id', $factura_id);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error al cargar factura: " . $e->getMessage());
}

// Configurar PDF
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factura <?= htmlspecialchars($factura['numero_factura']) ?></title>
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: white;
}
.factura-container {
    max-width: 800px;
    margin: 0 auto;
    border: 2px solid #2c5f7f;
    padding: 30px;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    border-bottom: 3px solid #2c5f7f;
    padding-bottom: 20px;
    margin-bottom: 25px;
}
.logo-section h1 {
    color: #2c5f7f;
    font-size: 2.5rem;
    margin-bottom: 5px;
}
.logo-section .subtitle {
    color: #666;
    font-size: 1rem;
}
.factura-info {
    text-align: right;
}
.factura-numero {
    font-size: 2rem;
    font-weight: bold;
    color: #2c5f7f;
    margin-bottom: 5px;
}
.estado {
    display: inline-block;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
    margin-top: 5px;
}
.estado-pagada {background: #28a745; color: white;}
.estado-pendiente {background: #ffc107; color: #333;}
.estado-anulada {background: #dc3545; color: white;}
.fecha {
    color: #666;
    margin-top: 10px;
    font-size: 0.95rem;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}
.info-box h3 {
    color: #2c5f7f;
    font-size: 1.1rem;
    margin-bottom: 12px;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 5px;
}
.info-box p {
    margin: 8px 0;
    color: #555;
    font-size: 0.95rem;
}
.info-box strong {
    color: #333;
    display: inline-block;
    min-width: 100px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}
table th, table td {
    padding: 12px;
    text-align: left;
    border: 1px solid #ddd;
}
table th {
    background: #739ee3;
    color: white;
    font-weight: bold;
}
table tbody tr:nth-child(even) {
    background: #f9f9f9;
}
.item-tipo {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: bold;
}
.tipo-servicio {background: #17a2b8; color: white;}
.tipo-producto {background: #ffc107; color: #333;}
.totales {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}
.total-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 1.1rem;
}
.total-final {
    font-size: 1.8rem;
    font-weight: bold;
    color: #28a745;
    border-top: 3px solid #ddd;
    padding-top: 12px;
    margin-top: 10px;
}
.footer {
    text-align: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 2px solid #e0e0e0;
    color: #666;
    font-size: 0.9rem;
}
.footer strong {
    color: #2c5f7f;
    font-size: 1.1rem;
}
@media print {
    body {padding: 0;}
    .no-print {display: none;}
}
</style>
</head>
<body>
<div class="factura-container">
    <!-- Encabezado -->
    <div class="header">
        <div class="logo-section">
            <h1>🐾 LUGO VET</h1>
            <p class="subtitle">Clínica Veterinaria Profesional</p>
            <p style="font-size:0.85rem; color:#888; margin-top:5px;">
                Tel: (123) 456-7890 | info@lugovet.com<br>
                Dirección: Calle Principal #123, Ciudad
            </p>
        </div>
        <div class="factura-info">
            <div class="factura-numero"><?= htmlspecialchars($factura['numero_factura']) ?></div>
            <span class="estado estado-<?= $factura['estado'] ?>">
                <?= strtoupper($factura['estado']) ?>
            </span>
            <div class="fecha">
                Fecha: <?= date('d/m/Y H:i', strtotime($factura['fecha_factura'])) ?>
            </div>
        </div>
    </div>

    <!-- Información Cliente y Pago -->
    <div class="info-grid">
        <div class="info-box">
            <h3>📋 INFORMACIÓN DEL CLIENTE</h3>
            <p><strong>Nombre:</strong> <?= htmlspecialchars($factura['cliente']) ?></p>
            <p><strong>Teléfono:</strong> <?= htmlspecialchars($factura['cliente_telefono'] ?? 'N/A') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($factura['cliente_correo'] ?? 'N/A') ?></p>
            <p><strong>Dirección:</strong> <?= htmlspecialchars($factura['cliente_direccion'] ?? 'N/A') ?></p>
        </div>
        <div class="info-box">
            <h3>💳 INFORMACIÓN DE PAGO</h3>
            <p><strong>Método:</strong> <?= $factura['metodo_pago'] ? ucfirst($factura['metodo_pago']) : 'No especificado' ?></p>
            <p><strong>Estado:</strong> 
                <span class="estado estado-<?= $factura['estado'] ?>">
                    <?= ucfirst($factura['estado']) ?>
                </span>
            </p>
        </div>
    </div>

    <!-- Detalle de Items -->
    <h3 style="color:#2c5f7f; margin-bottom:15px;">📦 DETALLE DE SERVICIOS Y PRODUCTOS</h3>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Descripción</th>
                <th style="text-align:center; width:80px;">Cant.</th>
                <th style="text-align:right; width:120px;">Precio Unit.</th>
                <th style="text-align:right; width:120px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($items)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; color:#999; padding:20px;">
                        No hay items en esta factura
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td>
                            <span class="item-tipo tipo-<?= $item['tipo'] ?>">
                                <?= $item['tipo'] === 'servicio' ? 'Servicio' : 'Producto' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($item['item']) ?></td>
                        <td style="text-align:center;"><?= $item['cantidad'] ?></td>
                        <td style="text-align:right;">$<?= number_format($item['precio_unitario'], 0, ',', '.') ?></td>
                        <td style="text-align:right;"><strong>$<?= number_format($item['subtotal'], 0, ',', '.') ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Totales -->
    <div class="totales">
        <div class="total-row">
            <span>Subtotal:</span>
            <strong>$<?= number_format($factura['subtotal'], 0, ',', '.') ?></strong>
        </div>
        <?php if($factura['impuestos'] > 0): ?>
            <div class="total-row">
                <span>Impuestos (IVA):</span>
                <strong>$<?= number_format($factura['impuestos'], 0, ',', '.') ?></strong>
            </div>
        <?php endif; ?>
        <?php if($factura['descuento'] > 0): ?>
            <div class="total-row" style="color:#dc3545;">
                <span>Descuento:</span>
                <strong>-$<?= number_format($factura['descuento'], 0, ',', '.') ?></strong>
            </div>
        <?php endif; ?>
        <div class="total-row total-final">
            <span>TOTAL A PAGAR:</span>
            <strong>$<?= number_format($factura['total'], 0, ',', '.') ?></strong>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><strong>¡Gracias por confiar en LUGO VET!</strong></p>
        <p>Este documento es un comprobante válido de la transacción realizada.</p>
        <p style="margin-top:15px; font-size:0.85rem;">
            <?= htmlspecialchars($factura['numero_factura']) ?> | 
            Generada el <?= date('d/m/Y H:i:s', strtotime($factura['fecha_factura'])) ?>
        </p>
        <p style="margin-top:10px; font-size:0.8rem; color:#999;">
            Para cualquier consulta o reclamo, contáctenos al (123) 456-7890
        </p>
    </div>
</div>

<script>
// Auto-imprimir al cargar
window.onload = function() {
    window.print();
}
</script>
</body>
</html>