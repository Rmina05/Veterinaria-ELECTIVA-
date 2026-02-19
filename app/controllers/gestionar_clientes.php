<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: login.php');
    exit;
}

$db = DB::conn();

// Manejo de formularios
$mensaje = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $telefono = trim($_POST['telefono']);
        $correo = trim($_POST['correo']);
        $direccion = trim($_POST['direccion']);

        $stmt = $db->prepare("CALL insertar_cliente(:nombre, :apellido, :telefono, :correo, :direccion)");
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':apellido', $apellido);
        $stmt->bindValue(':telefono', $telefono);
        $stmt->bindValue(':correo', $correo);
        $stmt->bindValue(':direccion', $direccion);

        if ($stmt->execute()) {
            $mensaje = "Cliente agregado correctamente.";
        } else {
            $errores[] = "Error al agregar cliente.";
        }
    }
// Editar cliente
    if ($accion === 'editar') {
        $id = intval($_POST['id']);
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $telefono = trim($_POST['telefono']);
        $correo = trim($_POST['correo']);
        $direccion = trim($_POST['direccion']);
        $estado = trim($_POST['estado']);

        $stmt = $db->prepare("CALL actualizar_cliente(:id, :nombre, :apellido, :telefono, :correo, :direccion, :estado)");
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':apellido', $apellido);
        $stmt->bindValue(':telefono', $telefono);
        $stmt->bindValue(':correo', $correo);
        $stmt->bindValue(':direccion', $direccion);
        $stmt->bindValue(':estado', $estado);

        if ($stmt->execute()) {
            $mensaje = "Cliente actualizado correctamente.";
        } else {
            $errores[] = "Error al actualizar cliente.";
        }
    }
// Eliminar cliente
    if ($accion === 'eliminar') {
        $id = intval($_POST['id']);

        $stmt = $db->prepare("CALL eliminar_cliente(:id)");
        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            $mensaje = "Cliente eliminado correctamente.";
        } else {
            $errores[] = "Error al eliminar cliente.";
        }
    }
}

// Filtrado por correo
$correoFiltro = $_GET['correo'] ?? '';

$sql = "SELECT * FROM clientes WHERE 1=1";
$params = [];

if ($correoFiltro) {
    $sql .= " AND correo = :correo";
    $params[':correo'] = $correoFiltro;
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Clientes - Lugo Vet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1 {color:#2c5f7f;}
.container {max-width:1200px; margin:0 auto;}
.mensaje {background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:8px; margin-bottom:20px;}
.errores {background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; border-radius:8px; margin-bottom:20px;}
table {width:100%; border-collapse:collapse; margin-bottom:20px;}
table th, table td {border:1px solid #ddd; padding:12px; text-align:left;}
table th {background:#739ee3; color:white;}
table tr:nth-child(even){background:#f2f2f2;}
button {padding:8px 15px; border:none; border-radius:5px; cursor:pointer; transition:0.3s;}
button:hover {opacity:0.8;}
.btn-agregar {background:#2c5f7f; color:white; margin-bottom:20px;}
.btn-editar {background:#739ee3; color:white;}
.btn-eliminar {background:#c0392b; color:white;}
form input, form select {padding:8px; margin-bottom:10px; width:100%; border:1px solid #ccc; border-radius:5px;}
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center;}
.modal-content {background:white; padding:30px; border-radius:15px; max-width:500px; width:100%; position:relative;}
.close {position:absolute; top:15px; right:15px; font-size:1.5rem; cursor:pointer; color:#333;}
</style>
</head>
<body>
<div class="container">
    <h1>Gestionar Clientes</h1>

    <?php if($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if(!empty($errores)): ?>
        <div class="errores">
            <ul>
                <?php foreach($errores as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Filtro por correo -->
    <form method="GET" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
        <input type="email" name="correo" placeholder="Buscar por correo" value="<?= htmlspecialchars($_GET['correo'] ?? '') ?>" style="flex:1; min-width:200px;">
        <button type="submit" class="btn-agregar">Filtrar</button>
        <a href="gestionar_clientes.php"><button type="button" class="btn-editar">Limpiar</button></a>
    </form>

    <button class="btn-agregar" onclick="abrirModal('agregar')">Agregar Cliente</button>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Dirección</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($clientes as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['nombre']) ?></td>
                    <td><?= htmlspecialchars($c['apellido']) ?></td>
                    <td><?= htmlspecialchars($c['telefono']) ?></td>
                    <td><?= htmlspecialchars($c['correo']) ?></td>
                    <td><?= htmlspecialchars($c['direccion']) ?></td>
                    <td><?= ucfirst($c['estado']) ?></td>
                    <td>
                        <button class="btn-editar" onclick="abrirModal('editar', <?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['apellido'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['telefono'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['correo'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['direccion'], ENT_QUOTES) ?>', '<?= $c['estado'] ?>')">Editar</button>
                        <form style="display:inline;" method="POST" onsubmit="return confirm('¿Desea eliminar este cliente?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2 id="modal-title"></h2>
        <form method="POST" id="modal-form">
            <input type="hidden" name="accion" id="accion">
            <input type="hidden" name="id" id="cliente-id">
            <label>Nombre</label>
            <input type="text" name="nombre" id="nombre" required>
            <label>Apellido</label>
            <input type="text" name="apellido" id="apellido" required>
            <label>Teléfono</label>
            <input type="text" name="telefono" id="telefono" required>
            <label>Correo</label>
            <input type="email" name="correo" id="correo" required>
            <label>Dirección</label>
            <input type="text" name="direccion" id="direccion" required>
            <label>Estado</label>
            <select name="estado" id="estado">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
            <button type="submit" class="btn-agregar" style="margin-top:10px;">Guardar</button>
        </form>
    </div>
</div>

<script>
function abrirModal(accion, id='', nombre='', apellido='', telefono='', correo='', direccion='', estado='') {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('accion').value = accion;
    document.getElementById('modal-title').innerText = accion === 'agregar' ? 'Agregar Cliente' : 'Editar Cliente';
    document.getElementById('cliente-id').value = id;
    document.getElementById('nombre').value = nombre;
    document.getElementById('apellido').value = apellido;
    document.getElementById('telefono').value = telefono;
    document.getElementById('correo').value = correo;
    document.getElementById('direccion').value = direccion;
    document.getElementById('estado').value = estado || 'activo';
}
function cerrarModal(){
    document.getElementById('modal').style.display = 'none';
}
window.onclick = function(event) {
    if(event.target == document.getElementById('modal')) cerrarModal();
}
</script>
</body>
</html>
