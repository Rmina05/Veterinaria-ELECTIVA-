<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: ../../public/login.php');
    exit;
}

// Obtener rol del usuario
$usuario = Sesion::obtenerUsuarioActual();
$rol = $usuario['rol'] ?? '';

// Solo administrador y recepcionista pueden gestionar servicios
if (!in_array($rol, ['administrador', 'recepcionista'])) {
    header('Location: ../../public/login.php');
    exit;
}

$db = DB::conn();
$mensaje = '';
$errores = [];

// Manejo de formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar' || $accion === 'editar') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $precio = floatval($_POST['precio']);
        $duracion_minutos = intval($_POST['duracion_minutos']);
        $categoria = $_POST['categoria'];
        $estado = $_POST['estado'] ?? 'activo';

        // Validaciones
        if (empty($nombre)) {
            $errores[] = "El nombre del servicio es obligatorio.";
        }
        if ($precio <= 0) {
            $errores[] = "El precio debe ser mayor a 0.";
        }
        if ($duracion_minutos <= 0) {
            $errores[] = "La duración debe ser mayor a 0 minutos.";
        }

        if (empty($errores)) {
            if ($accion === 'agregar') {
                $stmt = $db->prepare("CALL insertar_servicio(:nombre, :descripcion, :precio, :duracion, :categoria, :estado)");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':descripcion' => $descripcion,
                    ':precio' => $precio,
                    ':duracion' => $duracion_minutos,
                    ':categoria' => $categoria,
                    ':estado' => $estado
                ]);
                $mensaje = "✅ Servicio agregado correctamente.";
            } else {
                $stmt = $db->prepare("CALL actualizar_servicio(:id, :nombre, :descripcion, :precio, :duracion, :categoria, :estado)");
                $stmt->execute([
                    ':id' => $id,
                    ':nombre' => $nombre,
                    ':descripcion' => $descripcion,
                    ':precio' => $precio,
                    ':duracion' => $duracion_minutos,
                    ':categoria' => $categoria,
                    ':estado' => $estado
                ]);
                $mensaje = "✅ Servicio actualizado correctamente.";
            }
        }
    }

    if ($accion === 'eliminar') {
        $id = intval($_POST['id']);
        
        // Usar procedimiento almacenado con validación
        $stmt = $db->prepare("CALL eliminar_servicio(:id, @resultado)");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        
        // Obtener el resultado
        $resultado = $db->query("SELECT @resultado")->fetchColumn();
        
        if ($resultado === 'OK') {
            $mensaje = "🗑️ Servicio eliminado correctamente.";
        } else {
            $errores[] = "No se puede eliminar el servicio porque está siendo usado en recetas.";
        }
    }

    if ($accion === 'cambiar_estado') {
        $id = intval($_POST['id']);
        $nuevo_estado = $_POST['nuevo_estado'];
        
        $stmt = $db->prepare("CALL cambiar_estado_servicio(:id, :estado)");
        $stmt->execute([':id' => $id, ':estado' => $nuevo_estado]);
        $mensaje = "✅ Estado actualizado correctamente.";
    }
}

// Obtener filtros
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Consultar servicios con filtros
$query = "SELECT * FROM servicios WHERE 1=1";
$params = [];

if (!empty($filtro_categoria)) {
    $query .= " AND categoria = :categoria";
    $params[':categoria'] = $filtro_categoria;
}

if (!empty($filtro_estado)) {
    $query .= " AND estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if (!empty($busqueda)) {
    $query .= " AND (nombre LIKE :busqueda OR descripcion LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

$query .= " ORDER BY nombre ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determinar el dashboard de retorno según el rol
$dashboardUrl = $rol === 'administrador' ? 'dashboard.php' : 'dashboard_recepcionista.php';
$tituloRol = $rol === 'administrador' ? 'Administrador' : 'Recepcionista';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Servicios - <?= $tituloRol ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1 {color:#2c5f7f; margin-bottom:10px;}
.container {max-width:1400px; margin:0 auto;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;}
.btn-volver {background:#6c757d; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block; transition:0.3s;}
.btn-volver:hover {background:#5a6268;}
.mensaje {background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:8px; margin-bottom:20px;}
.errores {background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; border-radius:8px; margin-bottom:20px;}
.filtros {background:white; padding:20px; border-radius:8px; margin-bottom:20px; display:flex; gap:15px; flex-wrap:wrap; align-items:end;}
.filtros > div {flex:1; min-width:200px;}
.filtros label {display:block; margin-bottom:5px; font-weight:bold; color:#2c5f7f;}
.filtros input, .filtros select {width:100%; padding:8px; border:1px solid #ccc; border-radius:5px;}
table {width:100%; border-collapse:collapse; margin-bottom:20px; background:white;}
table th, table td {border:1px solid #ddd; padding:12px; text-align:left;}
table th {background:#739ee3; color:white;}
table tr:nth-child(even){background:#f2f2f2;}
button {padding:8px 15px; border:none; border-radius:5px; cursor:pointer; transition:0.3s;}
button:hover {opacity:0.8;}
.btn-agregar {background:#2c5f7f; color:white; margin-bottom:20px;}
.btn-editar {background:#739ee3; color:white;}
.btn-eliminar {background:#c0392b; color:white;}
.btn-filtrar {background:#17a2b8; color:white;}
.btn-limpiar {background:#6c757d; color:white;}
form input, form select, form textarea {padding:8px; margin-bottom:10px; width:100%; border:1px solid #ccc; border-radius:5px;}
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000;}
.modal-content {background:white; padding:30px; border-radius:15px; max-width:600px; width:90%; max-height:90vh; overflow-y:auto; position:relative;}
.close {position:absolute; top:15px; right:15px; font-size:1.5rem; cursor:pointer; color:#333;}
.rol-badge {padding:5px 10px; border-radius:20px; font-size:0.8rem; margin-left:10px;}
.badge-admin {background:#e74c3c; color:white;}
.badge-recepcionista {background:#3498db; color:white;}
.precio {color:#27ae60; font-weight:bold;}
.categoria-badge {padding:3px 8px; border-radius:12px; font-size:0.85rem; font-weight:bold;}
.cat-estetica {background:#ffeb3b; color:#333;}
.cat-medico {background:#4caf50; color:white;}
.cat-spa {background:#9c27b0; color:white;}
.cat-otros {background:#607d8b; color:white;}
.estado-badge {padding:3px 8px; border-radius:12px; font-size:0.85rem; font-weight:bold;}
.est-activo {background:#4caf50; color:white;}
.est-inactivo {background:#f44336; color:white;}
.btn-estado {padding:5px 10px; font-size:0.85rem;}
.stats {display:flex; gap:15px; margin-bottom:20px; flex-wrap:wrap;}
.stat-card {background:white; padding:20px; border-radius:8px; flex:1; min-width:200px; text-align:center; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.stat-card h3 {color:#739ee3; font-size:2rem; margin-bottom:5px;}
.stat-card p {color:#666; font-size:0.9rem;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-concierge-bell"></i> Gestionar Servicios 
            <span class="rol-badge badge-<?= $rol ?>">
                <i class="fas fa-<?= $rol === 'administrador' ? 'crown' : 'user' ?>"></i> 
                <?= strtoupper($tituloRol) ?>
            </span>
        </h1>
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
        <div class="stat-card">
            <h3><?= count($servicios) ?></h3>
            <p><i class="fas fa-list"></i> Total Servicios</p>
        </div>
        <div class="stat-card">
            <h3><?= count(array_filter($servicios, fn($s) => $s['estado'] === 'activo')) ?></h3>
            <p><i class="fas fa-check-circle"></i> Activos</p>
        </div>
        <div class="stat-card">
            <h3><?= count(array_filter($servicios, fn($s) => $s['categoria'] === 'estetica')) ?></h3>
            <p><i class="fas fa-cut"></i> Estética</p>
        </div>
        <div class="stat-card">
            <h3><?= count(array_filter($servicios, fn($s) => $s['categoria'] === 'medico')) ?></h3>
            <p><i class="fas fa-stethoscope"></i> Médicos</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros">
        <form method="GET" style="display:contents;">
            <div>
                <label><i class="fas fa-search"></i> Buscar:</label>
                <input type="text" name="busqueda" placeholder="Nombre o descripción..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
            <div>
                <label><i class="fas fa-tag"></i> Categoría:</label>
                <select name="categoria">
                    <option value="">Todas</option>
                    <option value="estetica" <?= $filtro_categoria === 'estetica' ? 'selected' : '' ?>>Estética</option>
                    <option value="medico" <?= $filtro_categoria === 'medico' ? 'selected' : '' ?>>Médico</option>
                    <option value="spa" <?= $filtro_categoria === 'spa' ? 'selected' : '' ?>>SPA</option>
                    <option value="otros" <?= $filtro_categoria === 'otros' ? 'selected' : '' ?>>Otros</option>
                </select>
            </div>
            <div>
                <label><i class="fas fa-toggle-on"></i> Estado:</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $filtro_estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div style="display:flex; gap:10px; align-items:end;">
                <button type="submit" class="btn-filtrar"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="gestionar_servicios.php" class="btn-limpiar" style="text-decoration:none; padding:8px 15px; display:inline-block;">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </div>
        </form>
    </div>

    <button class="btn-agregar" onclick="abrirModal('agregar')">
        <i class="fas fa-plus-circle"></i> Agregar Nuevo Servicio
    </button>

    <?php if(count($servicios) === 0): ?>
        <p style="background:white; padding:20px; border-radius:8px; text-align:center; color:#777;">
            <i class="fas fa-info-circle"></i> No hay servicios registrados con los filtros seleccionados.
        </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Precio</th>
                <th>Duración</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($servicios as $s): ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><strong><?= htmlspecialchars($s['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars(substr($s['descripcion'] ?? '', 0, 60)) . (strlen($s['descripcion'] ?? '') > 60 ? '...' : '') ?></td>
                    <td class="precio">$<?= number_format($s['precio'], 0, ',', '.') ?></td>
                    <td><i class="fas fa-clock"></i> <?= $s['duracion_minutos'] ?> min</td>
                    <td>
                        <span class="categoria-badge cat-<?= $s['categoria'] ?>">
                            <?= ucfirst($s['categoria']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="estado-badge est-<?= $s['estado'] ?>">
                            <?= ucfirst($s['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn-editar" 
                            onclick='abrirModal("editar", <?= $s["id"] ?>, "<?= htmlspecialchars($s["nombre"], ENT_QUOTES) ?>", "<?= htmlspecialchars($s["descripcion"] ?? "", ENT_QUOTES) ?>", <?= $s["precio"] ?>, <?= $s["duracion_minutos"] ?>, "<?= $s["categoria"] ?>", "<?= $s["estado"] ?>")'>
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        
                        <form style="display:inline;" method="POST">
                            <input type="hidden" name="accion" value="cambiar_estado">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="nuevo_estado" value="<?= $s['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                            <button type="submit" class="btn-editar btn-estado">
                                <i class="fas fa-<?= $s['estado'] === 'activo' ? 'toggle-off' : 'toggle-on' ?>"></i>
                                <?= $s['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        
                        <form style="display:inline;" method="POST" onsubmit="return confirm('⚠️ ¿Está seguro de eliminar este servicio?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-eliminar"><i class="fas fa-trash-alt"></i> Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2 id="modal-title"></h2>
        <form method="POST" id="modal-form">
            <input type="hidden" name="accion" id="accion">
            <input type="hidden" name="id" id="servicio-id">

            <label><i class="fas fa-tag"></i> Nombre del Servicio *</label>
            <input type="text" name="nombre" id="nombre" required placeholder="Ej: Baño Completo">

            <label><i class="fas fa-align-left"></i> Descripción</label>
            <textarea name="descripcion" id="descripcion" rows="3" placeholder="Descripción del servicio..."></textarea>

            <label><i class="fas fa-dollar-sign"></i> Precio *</label>
            <input type="number" name="precio" id="precio" required min="0" step="0.01" placeholder="0.00">

            <label><i class="fas fa-clock"></i> Duración (minutos) *</label>
            <input type="number" name="duracion_minutos" id="duracion_minutos" required min="1" placeholder="60">

            <label><i class="fas fa-list"></i> Categoría *</label>
            <select name="categoria" id="categoria" required>
                <option value="estetica">Estética</option>
                <option value="medico">Médico</option>
                <option value="spa">SPA</option>
                <option value="otros">Otros</option>
            </select>

            <label><i class="fas fa-toggle-on"></i> Estado</label>
            <select name="estado" id="estado">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>

            <button type="submit" class="btn-agregar" style="margin-top:10px; width:100%;">
                <i class="fas fa-save"></i> Guardar Servicio
            </button>
        </form>
    </div>
</div>

<script>
function abrirModal(accion, id='', nombre='', descripcion='', precio='', duracion='', categoria='estetica', estado='activo') {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('accion').value = accion;
    document.getElementById('modal-title').innerText = accion === 'agregar' ? '➕ Agregar Nuevo Servicio' : '✏️ Editar Servicio';
    document.getElementById('servicio-id').value = id;
    document.getElementById('nombre').value = nombre;
    document.getElementById('descripcion').value = descripcion;
    document.getElementById('precio').value = precio;
    document.getElementById('duracion_minutos').value = duracion || 60;
    document.getElementById('categoria').value = categoria;
    document.getElementById('estado').value = estado;
}

function cerrarModal(){
    document.getElementById('modal').style.display = 'none';
    document.getElementById('modal-form').reset();
}

window.onclick = function(event) {
    if(event.target == document.getElementById('modal')) cerrarModal();
}
</script>
</body>
</html>