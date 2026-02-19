<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: ../../public/login.php');
    exit;
}

$usuario = Sesion::obtenerUsuarioActual();
$rol = $usuario['rol'] ?? '';

// Solo admin y recepcionista
if (!in_array($rol, ['administrador', 'recepcionista'])) {
    header('Location: ../../public/login.php');
    exit;
}

$db = DB::conn();
$mensaje = '';
$errores = [];

// AGREGAR PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $stock_minimo = intval($_POST['stock_minimo']);
    $categoria = trim($_POST['categoria']);
    $proveedor = trim($_POST['proveedor']);
    $estado = $_POST['estado'];
    
    if (empty($nombre) || $precio <= 0) {
        $errores[] = "El nombre y el precio son obligatorios.";
    }
    
    if (empty($errores)) {
        try {
            $stmt = $db->prepare("CALL insertar_producto(:nombre, :descripcion, :precio, :stock, :stock_minimo, :categoria, :proveedor, :estado)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':precio' => $precio,
                ':stock' => $stock,
                ':stock_minimo' => $stock_minimo,
                ':categoria' => $categoria,
                ':proveedor' => $proveedor,
                ':estado' => $estado
            ]);
            $mensaje = "✅ Producto agregado exitosamente.";
        } catch (PDOException $e) {
            $errores[] = "Error al agregar producto: " . $e->getMessage();
        }
    }
}

// EDITAR PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $stock_minimo = intval($_POST['stock_minimo']);
    $categoria = trim($_POST['categoria']);
    $proveedor = trim($_POST['proveedor']);
    $estado = $_POST['estado'];
    
    if (empty($nombre) || $precio <= 0) {
        $errores[] = "El nombre y el precio son obligatorios.";
    }
    
    if (empty($errores)) {
        try {
            $stmt = $db->prepare("CALL actualizar_producto(:id, :nombre, :descripcion, :precio, :stock, :stock_minimo, :categoria, :proveedor, :estado)");
            $stmt->execute([
                ':id' => $id,
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':precio' => $precio,
                ':stock' => $stock,
                ':stock_minimo' => $stock_minimo,
                ':categoria' => $categoria,
                ':proveedor' => $proveedor,
                ':estado' => $estado
            ]);
            $mensaje = "✅ Producto actualizado correctamente.";
        } catch (PDOException $e) {
            $errores[] = "Error al actualizar producto: " . $e->getMessage();
        }
    }
}

// AJUSTAR STOCK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajustar_stock'])) {
    $id = intval($_POST['id']);
    $cantidad = intval($_POST['cantidad']);
    $tipo = $_POST['tipo'];
    
    try {
        $stmt = $db->prepare("CALL ajustar_stock_producto(:id, :cantidad, :tipo)");
        $stmt->execute([':id' => $id, ':cantidad' => $cantidad, ':tipo' => $tipo]);
        $mensaje = "✅ Stock ajustado correctamente.";
    } catch (PDOException $e) {
        $errores[] = "Error al ajustar stock: " . $e->getMessage();
    }
}

// CAMBIAR ESTADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $id = intval($_POST['id']);
    $estado = $_POST['nuevo_estado'];
    
    try {
        $stmt = $db->prepare("CALL cambiar_estado_producto(:id, :estado)");
        $stmt->execute([':id' => $id, ':estado' => $estado]);
        $mensaje = "✅ Estado actualizado correctamente.";
    } catch (PDOException $e) {
        $errores[] = "Error al cambiar estado: " . $e->getMessage();
    }
}

// ELIMINAR PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $id = intval($_POST['id']);
    
    try {
        $stmt = $db->prepare("CALL eliminar_producto(:id, @resultado)");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        
        $resultado = $db->query("SELECT @resultado")->fetchColumn();
        
        if ($resultado === 'OK') {
            $mensaje = "✅ Producto eliminado correctamente.";
        } else {
            $errores[] = $resultado;
        }
    } catch (PDOException $e) {
        $errores[] = "Error al eliminar producto: " . $e->getMessage();
    }
}

// OBTENER ESTADÍSTICAS
$stmt = $db->prepare("CALL obtener_estadisticas_productos(@total, @activos, @inactivos, @stock_bajo, @sin_stock)");
$stmt->execute();
$stats = $db->query("SELECT @total AS total, @activos AS activos, @inactivos AS inactivos, @stock_bajo AS stock_bajo, @sin_stock AS sin_stock")->fetch(PDO::FETCH_ASSOC);

// OBTENER PRODUCTOS
$stmt = $db->query("SELECT * FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dashboardUrl = $rol === 'administrador' ? 'dashboard.php' : 'dashboard_recepcionista.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Productos - Lugo Vet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1 {color:#2c5f7f;}
.container {max-width:1400px; margin:0 auto;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;}
.btn-volver {background:#6c757d; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; transition:0.3s;}
.btn-volver:hover {background:#5a6268;}
.btn-agregar {background:#28a745; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer; margin-bottom:20px;}
.mensaje {background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:8px; margin-bottom:20px;}
.errores {background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; border-radius:8px; margin-bottom:20px;}
.stats {display:flex; gap:15px; margin-bottom:20px; flex-wrap:wrap;}
.stat-card {background:white; padding:20px; border-radius:8px; flex:1; min-width:180px; text-align:center; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.stat-card h3 {font-size:2rem; margin-bottom:5px;}
.stat-card p {color:#666; font-size:0.9rem;}
.stat-total h3 {color:#739ee3;}
.stat-activos h3 {color:#28a745;}
.stat-stock-bajo h3 {color:#ffc107;}
.stat-sin-stock h3 {color:#dc3545;}
table {width:100%; border-collapse:collapse; margin-bottom:20px; background:white;}
table th, table td {border:1px solid #ddd; padding:12px; text-align:left;}
table th {background:#739ee3; color:white;}
table tr:nth-child(even){background:#f2f2f2;}
button {padding:8px 15px; border:none; border-radius:5px; cursor:pointer; transition:0.3s;}
button:hover {opacity:0.8;}
.btn-editar {background:#739ee3; color:white;}
.btn-eliminar {background:#dc3545; color:white;}
.btn-stock {background:#ffc107; color:#333;}
.estado-activo {color:#28a745; font-weight:bold;}
.estado-inactivo {color:#dc3545; font-weight:bold;}
.stock-bajo {background:#fff3cd; color:#856404; padding:3px 8px; border-radius:12px; font-size:0.85rem;}
.sin-stock {background:#f8d7da; color:#721c24; padding:3px 8px; border-radius:12px; font-size:0.85rem;}
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000;}
.modal-content {background:white; padding:30px; border-radius:15px; max-width:600px; width:90%; max-height:90vh; overflow-y:auto; position:relative;}
.close {position:absolute; top:15px; right:15px; font-size:1.5rem; cursor:pointer; color:#333;}
form input, form select, form textarea {padding:8px; margin-bottom:10px; width:100%; border:1px solid #ccc; border-radius:5px;}
form label {display:block; margin-top:10px; font-weight:bold; color:#2c5f7f;}
textarea {min-height:80px; resize:vertical;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-box"></i> Gestionar Productos</h1>
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
            <p><i class="fas fa-box"></i> Total Productos</p>
        </div>
        <div class="stat-card stat-activos">
            <h3><?= $stats['activos'] ?></h3>
            <p><i class="fas fa-check-circle"></i> Activos</p>
        </div>
        <div class="stat-card stat-stock-bajo">
            <h3><?= $stats['stock_bajo'] ?></h3>
            <p><i class="fas fa-exclamation-triangle"></i> Stock Bajo</p>
        </div>
        <div class="stat-card stat-sin-stock">
            <h3><?= $stats['sin_stock'] ?></h3>
            <p><i class="fas fa-times-circle"></i> Sin Stock</p>
        </div>
    </div>

    <button class="btn-agregar" onclick="abrirModalAgregar()">
        <i class="fas fa-plus-circle"></i> Agregar Producto
    </button>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Stock Mín.</th>
                <th>Proveedor</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $p): ?>
                    <?php 
                    $alerta_stock = '';
                    if ($p['stock'] == 0) {
                        $alerta_stock = '<span class="sin-stock">SIN STOCK</span>';
                    } elseif ($p['stock'] <= $p['stock_minimo']) {
                        $alerta_stock = '<span class="stock-bajo">STOCK BAJO</span>';
                    }
                    ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($p['categoria'] ?: '-') ?></td>
                        <td><strong style="color:#28a745;">$<?= number_format($p['precio'], 0, ',', '.') ?></strong></td>
                        <td>
                            <strong><?= $p['stock'] ?></strong> unidades
                            <?= $alerta_stock ?>
                        </td>
                        <td><?= $p['stock_minimo'] ?></td>
                        <td><?= htmlspecialchars($p['proveedor'] ?: '-') ?></td>
                        <td class="estado-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></td>
                        <td>
                            <button class="btn-stock" onclick="abrirModalStock(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nombre']) ?>', <?= $p['stock'] ?>)">
                                <i class="fas fa-boxes"></i> Stock
                            </button>
                            <button class="btn-editar" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($p)) ?>)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que desea eliminar este producto?')">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" name="eliminar" class="btn-eliminar">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align:center; padding:20px; color:#777;">
                        <i class="fas fa-info-circle"></i> No hay productos registrados
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal AGREGAR -->
<div class="modal" id="modalAgregar">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalAgregar()">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Agregar Producto</h2>
        <form method="POST">
            <label>Nombre *</label>
            <input type="text" name="nombre" required>
            
            <label>Descripción</label>
            <textarea name="descripcion"></textarea>
            
            <label>Precio *</label>
            <input type="number" name="precio" step="0.01" min="0" required>
            
            <label>Stock Inicial *</label>
            <input type="number" name="stock" min="0" value="0" required>
            
            <label>Stock Mínimo *</label>
            <input type="number" name="stock_minimo" min="0" value="5" required>
            
            <label>Categoría</label>
            <input type="text" name="categoria" placeholder="Ej: Medicamentos, Alimentos, Accesorios">
            
            <label>Proveedor</label>
            <input type="text" name="proveedor">
            
            <label>Estado *</label>
            <select name="estado" required>
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
            
            <button type="submit" name="agregar" class="btn-agregar" style="width:100%; margin-top:15px;">
                <i class="fas fa-save"></i> Guardar Producto
            </button>
        </form>
    </div>
</div>

<!-- Modal EDITAR -->
<div class="modal" id="modalEditar">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalEditar()">&times;</span>
        <h2><i class="fas fa-edit"></i> Editar Producto</h2>
        <form method="POST" id="formEditar">
            <input type="hidden" name="id" id="edit_id">
            
            <label>Nombre *</label>
            <input type="text" name="nombre" id="edit_nombre" required>
            
            <label>Descripción</label>
            <textarea name="descripcion" id="edit_descripcion"></textarea>
            
            <label>Precio *</label>
            <input type="number" name="precio" id="edit_precio" step="0.01" min="0" required>
            
            <label>Stock Actual *</label>
            <input type="number" name="stock" id="edit_stock" min="0" required>
            
            <label>Stock Mínimo *</label>
            <input type="number" name="stock_minimo" id="edit_stock_minimo" min="0" required>
            
            <label>Categoría</label>
            <input type="text" name="categoria" id="edit_categoria">
            
            <label>Proveedor</label>
            <input type="text" name="proveedor" id="edit_proveedor">
            
            <label>Estado *</label>
            <select name="estado" id="edit_estado" required>
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
            
            <button type="submit" name="editar" class="btn-agregar" style="width:100%; margin-top:15px;">
                <i class="fas fa-save"></i> Actualizar Producto
            </button>
        </form>
    </div>
</div>

<!-- Modal AJUSTAR STOCK -->
<div class="modal" id="modalStock">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalStock()">&times;</span>
        <h2><i class="fas fa-boxes"></i> Ajustar Stock</h2>
        <p id="stock_info" style="background:#e7f3ff; padding:10px; border-radius:5px; margin-bottom:15px;"></p>
        <form method="POST">
            <input type="hidden" name="id" id="stock_id">
            
            <label>Tipo de Ajuste *</label>
            <select name="tipo" required>
                <option value="agregar">➕ Agregar (Reposición)</option>
                <option value="restar">➖ Restar (Ajuste)</option>
            </select>
            
            <label>Cantidad *</label>
            <input type="number" name="cantidad" min="1" required>
            
            <button type="submit" name="ajustar_stock" class="btn-agregar" style="width:100%; margin-top:15px;">
                <i class="fas fa-check"></i> Confirmar Ajuste
            </button>
        </form>
    </div>
</div>

<script>
function abrirModalAgregar() {
    document.getElementById('modalAgregar').style.display = 'flex';
}

function cerrarModalAgregar() {
    document.getElementById('modalAgregar').style.display = 'none';
}

function abrirModalEditar(producto) {
    document.getElementById('modalEditar').style.display = 'flex';
    document.getElementById('edit_id').value = producto.id;
    document.getElementById('edit_nombre').value = producto.nombre;
    document.getElementById('edit_descripcion').value = producto.descripcion || '';
    document.getElementById('edit_precio').value = producto.precio;
    document.getElementById('edit_stock').value = producto.stock;
    document.getElementById('edit_stock_minimo').value = producto.stock_minimo;
    document.getElementById('edit_categoria').value = producto.categoria || '';
    document.getElementById('edit_proveedor').value = producto.proveedor || '';
    document.getElementById('edit_estado').value = producto.estado;
}

function cerrarModalEditar() {
    document.getElementById('modalEditar').style.display = 'none';
}

function abrirModalStock(id, nombre, stock) {
    document.getElementById('modalStock').style.display = 'flex';
    document.getElementById('stock_id').value = id;
    document.getElementById('stock_info').innerHTML = '<strong>' + nombre + '</strong><br>Stock actual: <strong>' + stock + '</strong> unidades';
}

function cerrarModalStock() {
    document.getElementById('modalStock').style.display = 'none';
}

window.onclick = function(event) {
    if(event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
</script>
</body>
</html>