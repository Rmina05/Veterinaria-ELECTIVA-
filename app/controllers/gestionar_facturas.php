<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: ../../public/login.php');
    exit;
}

$usuario = Sesion::obtenerUsuarioActual();
$rol = $usuario['rol'] ?? '';

// Solo admin y recepcionista pueden gestionar facturas
if (!in_array($rol, ['administrador', 'recepcionista'])) {
    header('Location: ../../public/login.php');
    exit;
}

$db = DB::conn();
$mensaje = '';
$errores = [];

// ANULAR FACTURA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anular_factura'])) {
    $factura_id = intval($_POST['factura_id']);
    
    try {
        $stmt = $db->prepare("CALL anular_factura(:id)");
        $stmt->bindValue(':id', $factura_id);
        $stmt->execute();
        $mensaje = "✅ Factura anulada correctamente.";
    } catch (PDOException $e) {
        $errores[] = "Error al anular factura: " . $e->getMessage();
    }
}

// MARCAR COMO PAGADA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pagada'])) {
    $factura_id = intval($_POST['factura_id']);
    $metodo_pago = $_POST['metodo_pago'];
    
    try {
        $stmt = $db->prepare("CALL marcar_factura_pagada(:id, :metodo)");
        $stmt->execute([':id' => $factura_id, ':metodo' => $metodo_pago]);
        $mensaje = "✅ Factura marcada como pagada.";
    } catch (PDOException $e) {
        $errores[] = "Error al actualizar factura: " . $e->getMessage();
    }
}

// FILTROS
$filtro_estado = $_GET['estado'] ?? '';
$filtro_cliente = $_GET['cliente'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// CONSULTAR FACTURAS CON FILTROS
$query = "
    SELECT 
        f.id,
        f.numero_factura,
        f.fecha_factura,
        f.total,
        f.estado,
        f.metodo_pago,
        CONCAT(c.nombre, ' ', c.apellido) AS cliente,
        c.id AS cliente_id
    FROM facturas f
    INNER JOIN clientes c ON f.cliente_id = c.id
    WHERE 1=1
";

$params = [];

if (!empty($filtro_estado)) {
    $query .= " AND f.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if (!empty($filtro_cliente)) {
    $query .= " AND (c.nombre LIKE :cliente OR c.apellido LIKE :cliente)";
    $params[':cliente'] = "%$filtro_cliente%";
}

if (!empty($fecha_desde)) {
    $query .= " AND DATE(f.fecha_factura) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $query .= " AND DATE(f.fecha_factura) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$query .= " ORDER BY f.fecha_factura DESC, f.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// OBTENER ESTADÍSTICAS
$stmt = $db->prepare("CALL obtener_estadisticas_facturas(@total, @pagadas, @pendientes, @anuladas, @vendido, @pendiente)");
$stmt->execute();
$stats = $db->query("SELECT @total AS total, @pagadas AS pagadas, @pendientes AS pendientes, @anuladas AS anuladas, @vendido AS vendido, @pendiente AS pendiente")->fetch(PDO::FETCH_ASSOC);

$dashboardUrl = $rol === 'administrador' ? 'dashboard.php' : 'dashboard_recepcionista.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Facturas - Lugo Vet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1 {color:#2c5f7f; margin-bottom:10px;}
.container {max-width:1400px; margin:0 auto;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;}
.btn-volver {background:#6c757d; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block; transition:0.3s;}
.btn-volver:hover {background:#5a6268;}
.btn-crear {background:#28a745; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block; transition:0.3s; margin-bottom:20px;}
.btn-crear:hover {background:#218838;}
.mensaje {background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:8px; margin-bottom:20px;}
.errores {background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; border-radius:8px; margin-bottom:20px;}
.filtros {background:white; padding:20px; border-radius:8px; margin-bottom:20px; display:flex; gap:15px; flex-wrap:wrap; align-items:end;}
.filtros > div {flex:1; min-width:180px;}
.filtros label {display:block; margin-bottom:5px; font-weight:bold; color:#2c5f7f;}
.filtros input, .filtros select {width:100%; padding:8px; border:1px solid #ccc; border-radius:5px;}
table {width:100%; border-collapse:collapse; margin-bottom:20px; background:white;}
table th, table td {border:1px solid #ddd; padding:12px; text-align:left;}
table th {background:#739ee3; color:white;}
table tr:nth-child(even){background:#f2f2f2;}
button, .btn {padding:8px 15px; border:none; border-radius:5px; cursor:pointer; transition:0.3s; text-decoration:none; display:inline-block; font-size:0.9rem;}
button:hover, .btn:hover {opacity:0.8;}
.btn-ver {background:#17a2b8; color:white;}
.btn-pdf {background:#e74c3c; color:white;}
.btn-anular {background:#dc3545; color:white;}
.btn-pagar {background:#28a745; color:white;}
.btn-filtrar {background:#17a2b8; color:white;}
.btn-limpiar {background:#6c757d; color:white;}
.estado-badge {padding:5px 10px; border-radius:15px; font-size:0.85rem; font-weight:bold;}
.est-pagada {background:#28a745; color:white;}
.est-pendiente {background:#ffc107; color:#333;}
.est-anulada {background:#dc3545; color:white;}
.stats {display:flex; gap:15px; margin-bottom:20px; flex-wrap:wrap;}
.stat-card {background:white; padding:20px; border-radius:8px; flex:1; min-width:200px; text-align:center; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.stat-card h3 {font-size:2rem; margin-bottom:5px;}
.stat-card p {color:#666; font-size:0.9rem;}
.stat-vendido h3 {color:#28a745;}
.stat-pendiente h3 {color:#ffc107;}
.stat-total h3 {color:#739ee3;}
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000;}
.modal-content {background:white; padding:30px; border-radius:15px; max-width:400px; width:90%; position:relative;}
.close {position:absolute; top:15px; right:15px; font-size:1.5rem; cursor:pointer; color:#333;}
.modal input, .modal select {width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:5px;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Gestionar Facturas</h1>
        <a href="<?= $dashboardUrl ?>" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al Dashboard
        </a>
    </div>

    <?php if($mensaje): ?>
        <div class="mensaje"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    
    <?php if(!empty($errores)): ?>
        <div class="errores">
            <ul>
                <?php foreach($errores as $error): ?>
                    <li><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="stats">
        <div class="stat-card stat-total">
            <h3><?= $stats['total'] ?></h3>
            <p><i class="fas fa-file-invoice"></i> Total Facturas</p>
        </div>
        <div class="stat-card stat-vendido">
            <h3>$<?= number_format($stats['vendido'], 0, ',', '.') ?></h3>
            <p><i class="fas fa-dollar-sign"></i> Total Vendido</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['pagadas'] ?></h3>
            <p><i class="fas fa-check-circle"></i> Pagadas</p>
        </div>
    </div>

    <!-- Botón crear factura -->
    <a href="crear_factura.php" class="btn-crear">
        <i class="fas fa-plus-circle"></i> Crear Nueva Factura
    </a>

    <!-- Filtros -->
    <div class="filtros">
        <form method="GET" style="display:contents;">
            <div>
                <label><i class="fas fa-filter"></i> Estado:</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="pagada" <?= $filtro_estado === 'pagada' ? 'selected' : '' ?>>Pagada</option>
                    <option value="anulada" <?= $filtro_estado === 'anulada' ? 'selected' : '' ?>>Anulada</option>
                </select>
            </div>
            <div>
                <label><i class="fas fa-user"></i> Cliente:</label>
                <input type="text" name="cliente" placeholder="Nombre del cliente..." value="<?= htmlspecialchars($filtro_cliente) ?>">
            </div>
            <div>
                <label><i class="fas fa-calendar"></i> Desde:</label>
                <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div>
                <label><i class="fas fa-calendar"></i> Hasta:</label>
                <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div style="display:flex; gap:10px; align-items:end;">
                <button type="submit" class="btn-filtrar"><i class="fas fa-search"></i> Filtrar</button>
                <a href="gestionar_facturas.php" class="btn btn-limpiar"><i class="fas fa-times"></i> Limpiar</a>
            </div>
        </form>
    </div>

    <!-- Tabla de facturas -->
    <?php if(count($facturas) === 0): ?>
        <p style="background:white; padding:20px; border-radius:8px; text-align:center; color:#777;">
            <i class="fas fa-info-circle"></i> No hay facturas registradas con los filtros seleccionados.
        </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>N° Factura</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Total</th>
                <th>Método Pago</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($facturas as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                    <td><?= htmlspecialchars($f['cliente']) ?></td>
                    <td><?= date('d/m/Y', strtotime($f['fecha_factura'])) ?></td>
                    <td><strong style="color:#27ae60;">$<?= number_format($f['total'], 0, ',', '.') ?></strong></td>
                    <td><?= $f['metodo_pago'] ? ucfirst($f['metodo_pago']) : '-' ?></td>
                    <td>
                        <span class="estado-badge est-<?= $f['estado'] ?>">
                            <?= ucfirst($f['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="ver_factura.php?id=<?= $f['id'] ?>" class="btn btn-ver" title="Ver detalles">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                        
                        <a href="imprimir_factura.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-pdf" title="Imprimir PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        
                        <?php if($f['estado'] === 'pendiente'): ?>
                            <button class="btn btn-pagar" onclick="abrirModalPagar(<?= $f['id'] ?>, '<?= htmlspecialchars($f['numero_factura']) ?>')">
                                <i class="fas fa-dollar-sign"></i> Pagar
                            </button>
                        <?php endif; ?>
                        
                        <?php if($f['estado'] !== 'anulada'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ ¿Está seguro de anular esta factura?');">
                                <input type="hidden" name="factura_id" value="<?= $f['id'] ?>">
                                <button type="submit" name="anular_factura" class="btn btn-anular" title="Anular factura">
                                    <i class="fas fa-ban"></i> Anular
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal para marcar como pagada -->
<div class="modal" id="modalPagar">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalPagar()">&times;</span>
        <h2><i class="fas fa-dollar-sign"></i> Marcar como Pagada</h2>
        <p id="factura-info" style="margin:10px 0; color:#666;"></p>
        <form method="POST">
            <input type="hidden" name="factura_id" id="factura_id_pagar">
            <label><strong>Método de Pago:</strong></label>
            <select name="metodo_pago" required>
                <option value="">Seleccione...</option>
                <option value="efectivo">Efectivo</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="transferencia">Transferencia</option>
            </select>
            <button type="submit" name="marcar_pagada" class="btn-pagar" style="width:100%; margin-top:10px;">
                <i class="fas fa-check"></i> Confirmar Pago
            </button>
        </form>
    </div>
</div>

<script>
function abrirModalPagar(id, numero) {
    document.getElementById('modalPagar').style.display = 'flex';
    document.getElementById('factura_id_pagar').value = id;
    document.getElementById('factura-info').innerText = 'Factura: ' + numero;
}

function cerrarModalPagar() {
    document.getElementById('modalPagar').style.display = 'none';
}

window.onclick = function(event) {
    if(event.target == document.getElementById('modalPagar')) {
        cerrarModalPagar();
    }
}
</script>
</body>
</html>