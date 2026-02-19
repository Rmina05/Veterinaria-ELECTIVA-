<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: ../../public/login.php');
    exit;
}

$usuario = Sesion::obtenerUsuarioActual();
$rol = $usuario['rol'] ?? '';

// Solo admin y recepcionista pueden ver facturas
if (!in_array($rol, ['administrador', 'recepcionista'])) {
    header('Location: ../../public/login.php');
    exit;
}

$db = DB::conn();
$factura_id = intval($_GET['id'] ?? 0);
$mensaje = $_GET['msg'] ?? '';

if ($factura_id === 0) {
    header('Location: gestionar_facturas.php');
    exit;
}

// Obtener datos completos de la factura usando procedimiento almacenado
try {
    $stmt = $db->prepare("CALL obtener_factura_completa(:id)");
    $stmt->bindValue(':id', $factura_id);
    $stmt->execute();
    
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
    if (!$factura) {
        header('Location: gestionar_facturas.php');
        exit;
    }
    
    // Obtener detalles (items) de la factura
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

$dashboardUrl = $rol === 'administrador' ? 'dashboard.php' : 'dashboard_recepcionista.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factura <?= htmlspecialchars($factura['numero_factura']) ?> - Lugo Vet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1, h2, h3 {color:#2c5f7f;}
.container {max-width:900px; margin:0 auto;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;}
.btn {padding:10px 20px; border:none; border-radius:5px; cursor:pointer; transition:0.3s; text-decoration:none; display:inline-block; font-size:0.95rem;}
.btn:hover {opacity:0.8;}
.btn-volver {background:#6c757d; color:white;}
.btn-pdf {background:#e74c3c; color:white;}
.btn-imprimir {background:#17a2b8; color:white;}
.mensaje {background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:8px; margin-bottom:20px;}
.factura-card {background:white; padding:30px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1);}
.factura-header {border-bottom:3px solid #2c5f7f; padding-bottom:20px; margin-bottom:25px;}
.factura-title {display:flex; justify-content:space-between; align-items:start; margin-bottom:15px;}
.logo-section h1 {color:#2c5f7f; font-size:2rem; margin-bottom:5px;}
.logo-section p {color:#666; font-size:0.9rem;}
.factura-numero {text-align:right;}
.factura-numero h2 {color:#2c5f7f; font-size:1.8rem; margin-bottom:5px;}
.estado-badge {padding:8px 15px; border-radius:20px; font-weight:bold; font-size:0.9rem; display:inline-block; margin-top:5px;}
.est-pagada {background:#28a745; color:white;}
.est-pendiente {background:#ffc107; color:#333;}
.est-anulada {background:#dc3545; color:white;}
.info-section {display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:30px;}
.info-box h3 {color:#2c5f7f; font-size:1.1rem; margin-bottom:10px; border-bottom:2px solid #e0e0e0; padding-bottom:5px;}
.info-box p {margin:8px 0; color:#555;}
.info-box strong {color:#333; display:inline-block; min-width:120px;}
table {width:100%; border-collapse:collapse; margin-bottom:25px;}
table th, table td {padding:12px; text-align:left; border-bottom:1px solid #ddd;}
table th {background:#739ee3; color:white; font-weight:bold;}
table tr:hover {background:#f5f5f5;}
.item-badge {padding:4px 10px; border-radius:12px; font-size:0.85rem; font-weight:bold;}
.badge-servicio {background:#17a2b8; color:white;}
.badge-producto {background:#ffc107; color:#333;}
.totales {background:#f8f9fa; padding:20px; border-radius:8px; margin-top:20px;}
.total-row {display:flex; justify-content:space-between; padding:8px 0; font-size:1.05rem;}
.total-final {font-size:1.5rem; font-weight:bold; color:#28a745; border-top:2px solid #ddd; padding-top:12px; margin-top:10px;}
.footer-info {text-align:center; margin-top:30px; padding-top:20px; border-top:2px solid #e0e0e0; color:#666; font-size:0.9rem;}
.action-buttons {display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;}
@media print {
    .no-print {display:none;}
    body {background:white; padding:0;}
    .factura-card {box-shadow:none; padding:20px;}
}
</style>
</head>
<body>
<div class="container">
    <!-- Botones de acción (no se imprimen) -->
    <div class="action-buttons no-print">
        <a href="gestionar_facturas.php" class="btn btn-volver">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <a href="imprimir_factura.php?id=<?= $factura_id ?>" target="_blank" class="btn btn-pdf">
            <i class="fas fa-file-pdf"></i> Descargar PDF
        </a>
        <button onclick="window.print()" class="btn btn-imprimir">
            <i class="fas fa-print"></i> Imprimir
        </button>
    </div>

    <?php if($mensaje === 'creada'): ?>
        <div class="mensaje no-print">
            <i class="fas fa-check-circle"></i> ¡Factura creada exitosamente!
        </div>
    <?php endif; ?>

    <!-- Factura -->
    <div class="factura-card">
        <!-- Encabezado -->
        <div class="factura-header">
            <div class="factura-title">
                <div class="logo-section">
                    <h1><i class="fas fa-paw"></i> LUGO VET</h1>
                    <p>Clínica Veterinaria</p>
                    <p style="font-size:0.85rem;">Tel: (123) 456-7890 | Email: info@lugovet.com</p>
                </div>
                <div class="factura-numero">
                    <h2><?= htmlspecialchars($factura['numero_factura']) ?></h2>
                    <span class="estado-badge est-<?= $factura['estado'] ?>">
                        <?= strtoupper($factura['estado']) ?>
                    </span>
                    <p style="color:#666; margin-top:10px; font-size:0.9rem;">
                        Fecha: <?= date('d/m/Y H:i', strtotime($factura['fecha_factura'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Información del Cliente -->
        <div class="info-section">
            <div class="info-box">
                <h3><i class="fas fa-user"></i> Cliente</h3>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($factura['cliente']) ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($factura['cliente_telefono'] ?? 'N/A') ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($factura['cliente_correo'] ?? 'N/A') ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($factura['cliente_direccion'] ?? 'N/A') ?></p>
            </div>
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Información de Pago</h3>
                <p><strong>Método:</strong> <?= $factura['metodo_pago'] ? ucfirst($factura['metodo_pago']) : 'No especificado' ?></p>
                <p><strong>Estado:</strong> 
                    <span class="estado-badge est-<?= $factura['estado'] ?>">
                        <?= ucfirst($factura['estado']) ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Detalle de Items -->
        <h3 style="margin-bottom:15px;"><i class="fas fa-list"></i> Detalle de Servicios y Productos</h3>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th style="text-align:center;">Cantidad</th>
                    <th style="text-align:right;">Precio Unit.</th>
                    <th style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($items)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:#999; padding:20px;">
                            No hay items registrados en esta factura
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($items as $item): ?>
                        <tr>
                            <td>
                                <span class="item-badge badge-<?= $item['tipo'] ?>">
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
                    <span>Impuestos:</span>
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
                <span>TOTAL:</span>
                <strong>$<?= number_format($factura['total'], 0, ',', '.') ?></strong>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-info">
            <p><strong>¡Gracias por confiar en LUGO VET!</strong></p>
            <p>Este documento es un comprobante válido de la transacción realizada.</p>
            <p style="margin-top:10px; font-size:0.85rem;">
                <?= htmlspecialchars($factura['numero_factura']) ?> | 
                Generada el <?= date('d/m/Y H:i', strtotime($factura['fecha_factura'])) ?>
            </p>
        </div>
    </div>
</div>
</body>
</html>