<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: ../../public/login.php');
    exit;
}

$usuario = Sesion::obtenerUsuarioActual();
$rol = $usuario['rol'] ?? '';

if (!in_array($rol, ['administrador', 'recepcionista'])) {
    header('Location: ../../public/login.php');
    exit;
}

$db = DB::conn();
$errores = [];

// PROCESAR CREACIÓN DE FACTURA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_factura'])) {
    $cliente_id = intval($_POST['cliente_id']);
    $cita_id = !empty($_POST['cita_id']) ? intval($_POST['cita_id']) : null;
    $metodo_pago = $_POST['metodo_pago'];
    $descuento = floatval($_POST['descuento'] ?? 0);
    
    // Items (servicios y productos)
    $items = json_decode($_POST['items_data'], true);
    
    if (empty($cliente_id)) {
        $errores[] = "Debe seleccionar un cliente.";
    }
    
    if (empty($items)) {
        $errores[] = "Debe agregar al menos un servicio o producto.";
    }
    
    if (empty($errores)) {
        try {
            $db->beginTransaction();
            
            // Calcular totales
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['cantidad'] * $item['precio'];
            }
            
            $impuestos = 0; //   calcular IVA
            $total = $subtotal - $descuento + $impuestos;
            
            // Crear factura
            $stmt = $db->prepare("CALL crear_factura(:cliente_id, :cita_id, :subtotal, :impuestos, :descuento, :total, :metodo_pago, @factura_id)");
            $stmt->execute([
                ':cliente_id' => $cliente_id,
                ':cita_id' => $cita_id,
                ':subtotal' => $subtotal,
                ':impuestos' => $impuestos,
                ':descuento' => $descuento,
                ':total' => $total,
                ':metodo_pago' => $metodo_pago
            ]);
            
            // Obtener ID de la factura creada
            $factura_id = $db->query("SELECT @factura_id")->fetchColumn();
            
            // Agregar items a la factura
            foreach ($items as $item) {
                $stmt = $db->prepare("CALL agregar_item_factura(:factura_id, :tipo, :item_id, :cantidad, :precio)");
                $stmt->execute([
                    ':factura_id' => $factura_id,
                    ':tipo' => $item['tipo'],
                    ':item_id' => $item['id'],
                    ':cantidad' => $item['cantidad'],
                    ':precio' => $item['precio']
                ]);
            }
            
            $db->commit();
            header("Location: ver_factura.php?id=$factura_id&msg=creada");
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errores[] = "Error al crear factura: " . $e->getMessage();
        }
    }
}

// Obtener clientes activos
$stmt = $db->query("SELECT id, CONCAT(nombre, ' ', apellido) AS nombre_completo FROM clientes WHERE estado = 'activo' ORDER BY nombre ASC");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener servicios activos
$stmt = $db->query("SELECT id, nombre, precio FROM servicios WHERE estado = 'activo' ORDER BY nombre ASC");
$servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos activos con stock
$stmt = $db->query("SELECT id, nombre, precio, stock FROM productos WHERE estado = 'activo' AND stock > 0 ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dashboardUrl = $rol === 'administrador' ? 'dashboard.php' : 'dashboard_recepcionista.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Factura - Lugo Vet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1, h2, h3 {color:#2c5f7f;}
.container {max-width:1200px; margin:0 auto;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;}
.btn-volver {background:#6c757d; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block; transition:0.3s;}
.btn-volver:hover {background:#5a6268;}
.errores {background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px;}
.card {background:white; padding:25px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.form-group {margin-bottom:15px;}
.form-group label {display:block; margin-bottom:5px; font-weight:bold; color:#2c5f7f;}
.form-group input, .form-group select {width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;}
.form-row {display:flex; gap:15px; flex-wrap:wrap;}
.form-row > div {flex:1; min-width:250px;}
.btn {padding:10px 20px; border:none; border-radius:5px; cursor:pointer; transition:0.3s; font-size:1rem; text-decoration:none; display:inline-block;}
.btn:hover {opacity:0.8;}
.btn-agregar {background:#28a745; color:white;}
.btn-primary {background:#2c5f7f; color:white;}
.btn-danger {background:#dc3545; color:white;}
table {width:100%; border-collapse:collapse; margin-top:15px;}
table th, table td {border:1px solid #ddd; padding:10px; text-align:left;}
table th {background:#739ee3; color:white;}
.total-section {background:#f8f9fa; padding:20px; border-radius:8px; margin-top:20px;}
.total-row {display:flex; justify-content:space-between; padding:10px 0; font-size:1.1rem;}
.total-final {font-size:1.5rem; font-weight:bold; color:#28a745; border-top:2px solid #ddd; padding-top:10px; margin-top:10px;}
.item-badge {padding:3px 8px; border-radius:12px; font-size:0.85rem; font-weight:bold;}
.badge-servicio {background:#17a2b8; color:white;}
.badge-producto {background:#ffc107; color:#333;}
.search-box {position:relative;}
.search-results {position:absolute; background:white; border:1px solid #ccc; border-radius:5px; max-height:200px; overflow-y:auto; width:100%; z-index:100; display:none; box-shadow:0 2px 5px rgba(0,0,0,0.2);}
.search-item {padding:10px; cursor:pointer; border-bottom:1px solid #eee;}
.search-item:hover {background:#f0f0f0;}
.input-group {display:flex; gap:10px;}
.input-group input {flex:1;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Crear Nueva Factura</h1>
        <div>
            <a href="gestionar_facturas.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver a Facturas
            </a>
        </div>
    </div>

    <?php if(!empty($errores)): ?>
        <div class="errores">
            <strong><i class="fas fa-exclamation-triangle"></i> Errores:</strong>
            <ul style="margin-top:10px; margin-left:20px;">
                <?php foreach($errores as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="formFactura">
        <!-- Información del Cliente -->
        <div class="card">
            <h2><i class="fas fa-user"></i> Información del Cliente</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="cliente_id" id="cliente_id" required>
                        <option value="">Seleccione un cliente...</option>
                        <?php foreach($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Método de Pago *</label>
                    <select name="metodo_pago" required>
                        <option value="">Seleccione...</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Cita Relacionada (Opcional)</label>
                <input type="number" name="cita_id" placeholder="ID de la cita (dejar vacío si no aplica)">
            </div>
        </div>

        <!-- Agregar Items -->
        <div class="card">
            <h2><i class="fas fa-shopping-cart"></i> Agregar Servicios / Productos</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo</label>
                    <select id="tipo_item">
                        <option value="servicio">Servicio</option>
                        <option value="producto">Producto</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Buscar</label>
                    <div class="search-box">
                        <input type="text" id="buscar_item" placeholder="Buscar servicio o producto...">
                        <div class="search-results" id="resultados"></div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Item Seleccionado</label>
                    <input type="text" id="item_nombre" placeholder="Seleccione un item..." readonly>
                    <input type="hidden" id="item_id">
                    <input type="hidden" id="item_precio">
                </div>
                <div class="form-group">
                    <label>Cantidad</label>
                    <div class="input-group">
                        <input type="number" id="cantidad" value="1" min="1" style="max-width:100px;">
                        <button type="button" class="btn btn-agregar" onclick="agregarItem()">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de items -->
            <table id="tabla_items">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Item</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="items_tbody">
                    <tr>
                        <td colspan="6" style="text-align:center; color:#999;">
                            No hay items agregados. Use el formulario de arriba para agregar servicios o productos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totales -->
        <div class="card">
            <h2><i class="fas fa-calculator"></i> Totales</h2>
            <div class="form-group" style="max-width:300px;">
                <label>Descuento ($)</label>
                <input type="number" name="descuento" id="descuento" value="0" min="0" step="0.01" onchange="calcularTotales()">
            </div>
            
            <div class="total-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <strong id="subtotal_display">$0</strong>
                </div>
                <div class="total-row">
                    <span>Descuento:</span>
                    <strong id="descuento_display">$0</strong>
                </div>
                <div class="total-row total-final">
                    <span>TOTAL:</span>
                    <strong id="total_display">$0</strong>
                </div>
            </div>
        </div>

        <!-- Datos ocultos -->
        <input type="hidden" name="items_data" id="items_data">

        <!-- Botones -->
        <div style="display:flex; gap:15px; justify-content:flex-end;">
            <a href="gestionar_facturas.php" class="btn btn-danger">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="submit" name="crear_factura" class="btn btn-primary">
                <i class="fas fa-save"></i> Crear Factura
            </button>
        </div>
    </form>
</div>

<script>
const servicios = <?= json_encode($servicios) ?>;
const productos = <?= json_encode($productos) ?>;
let items = [];

// Buscar items
document.getElementById('buscar_item').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    const tipo = document.getElementById('tipo_item').value;
    const resultados = document.getElementById('resultados');
    
    if (query.length < 2) {
        resultados.style.display = 'none';
        return;
    }
    
    const lista = tipo === 'servicio' ? servicios : productos;
    const filtrados = lista.filter(item => item.nombre.toLowerCase().includes(query));
    
    if (filtrados.length === 0) {
        resultados.innerHTML = '<div style="padding:10px; color:#999;">No se encontraron resultados</div>';
    } else {
        resultados.innerHTML = filtrados.map(item => 
            `<div class="search-item" onclick="seleccionarItem(${item.id}, '${item.nombre.replace(/'/g, "\\'")}', ${item.precio}, '${tipo}')">
                <strong>${item.nombre}</strong> - $${Number(item.precio).toLocaleString('es-CO')}
                ${tipo === 'producto' ? ` <small>(Stock: ${item.stock})</small>` : ''}
            </div>`
        ).join('');
    }
    
    resultados.style.display = 'block';
});

function seleccionarItem(id, nombre, precio, tipo) {
    document.getElementById('item_id').value = id;
    document.getElementById('item_nombre').value = nombre;
    document.getElementById('item_precio').value = precio;
    document.getElementById('buscar_item').value = '';
    document.getElementById('resultados').style.display = 'none';
}

function agregarItem() {
    const id = document.getElementById('item_id').value;
    const nombre = document.getElementById('item_nombre').value;
    const precio = parseFloat(document.getElementById('item_precio').value);
    const cantidad = parseInt(document.getElementById('cantidad').value);
    const tipo = document.getElementById('tipo_item').value;
    
    if (!id || !nombre || !precio || !cantidad) {
        alert('Debe seleccionar un item y especificar la cantidad');
        return;
    }
    
    items.push({
        id: id,
        tipo: tipo,
        nombre: nombre,
        precio: precio,
        cantidad: cantidad
    });
    
    actualizarTabla();
    calcularTotales();
    
    // Limpiar campos
    document.getElementById('item_id').value = '';
    document.getElementById('item_nombre').value = '';
    document.getElementById('item_precio').value = '';
    document.getElementById('cantidad').value = '1';
}

function eliminarItem(index) {
    items.splice(index, 1);
    actualizarTabla();
    calcularTotales();
}

function actualizarTabla() {
    const tbody = document.getElementById('items_tbody');
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999;">No hay items agregados</td></tr>';
        return;
    }
    
    tbody.innerHTML = items.map((item, index) => `
        <tr>
            <td><span class="item-badge badge-${item.tipo}">${item.tipo === 'servicio' ? 'Servicio' : 'Producto'}</span></td>
            <td>${item.nombre}</td>
            <td>${item.cantidad}</td>
            <td>$${Number(item.precio).toLocaleString('es-CO')}</td>
            <td><strong>$${Number(item.precio * item.cantidad).toLocaleString('es-CO')}</strong></td>
            <td>
                <button type="button" class="btn btn-danger" onclick="eliminarItem(${index})" style="padding:5px 10px; font-size:0.85rem;">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function calcularTotales() {
    const subtotal = items.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
    const descuento = parseFloat(document.getElementById('descuento').value) || 0;
    const total = subtotal - descuento;
    
    document.getElementById('subtotal_display').textContent = '$' + subtotal.toLocaleString('es-CO');
    document.getElementById('descuento_display').textContent = '$' + descuento.toLocaleString('es-CO');
    document.getElementById('total_display').textContent = '$' + total.toLocaleString('es-CO');
}

// Antes de enviar el formulario
document.getElementById('formFactura').addEventListener('submit', function(e) {
    if (items.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un servicio o producto');
        return;
    }
    
    document.getElementById('items_data').value = JSON.stringify(items);
});

// Cerrar resultados al hacer click fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-box')) {
        document.getElementById('resultados').style.display = 'none';
    }
});
</script>
</body>
</html>