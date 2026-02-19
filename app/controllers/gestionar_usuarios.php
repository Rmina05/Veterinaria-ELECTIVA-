<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';
require_once __DIR__ . '/../models/usuario.php';

if (!Sesion::estaLogueado()) {
    header('Location: login.php');
    exit;
}

$usuario = Sesion::obtenerUsuarioActual();
$db = DB::conn();
$usuarioObj = new Usuario($db);

// Manejo de formularios
$mensaje = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $rol = trim($_POST['rol']);

        $stmt = $db->prepare("CALL insertar_usuario(:nombre, :email, :password, :rol)");
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT));
        $stmt->bindValue(':rol', $rol);
        $stmt->execute();

        $mensaje = "Usuario agregado correctamente.";
    }

    if ($accion === 'editar') {
        $id = intval($_POST['id']);
        if($id != $usuario['id']) { // No puede editarse a sí mismo
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            $rol = trim($_POST['rol']);
            $estado = trim($_POST['estado']);

            $stmt = $db->prepare("CALL actualizar_usuario(:id, :nombre, :email, :password, :rol, :estado)");
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT));
            $stmt->bindValue(':rol', $rol);
            $stmt->bindValue(':estado', $estado);
            $stmt->execute();

            $mensaje = "Usuario actualizado correctamente.";
        } else {
            $errores[] = "No puedes editar tu propia cuenta.";
        }
    }

    if ($accion === 'eliminar') {
        $id = intval($_POST['id']);
        if($id != $usuario['id']) { // No puede eliminarse a sí mismo
            $stmt = $db->prepare("CALL eliminar_usuario(:id)");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            $mensaje = "Usuario eliminado correctamente.";
        } else {
            $errores[] = "No puedes eliminar tu propia cuenta.";
        }
    }
}

// Obtener todos los usuarios
$usuariosStmt = $db->query("SELECT id, nombre, email, rol, estado, created_at, updated_at 
                            FROM usuarios 
                            WHERE rol IN ('veterinario','recepcionista')
                            ORDER BY id DESC");
$usuarios = $usuariosStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Usuarios - Lugo Vet</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:'Montserrat', sans-serif; background:#e0f4ff; padding:20px;}
h1 {font-family:'Playfair Display', serif; color:#2c5f7f;}
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
    <h1>Gestionar Usuarios</h1>

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

    <button class="btn-agregar" onclick="abrirModal('agregar')">Agregar Usuario</button>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Creado</th>
                <th>Actualizado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($usuarios as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= ucfirst($u['rol']) ?></td>
                    <td><?= ucfirst($u['estado']) ?></td>
                    <td><?= $u['created_at'] ?></td>
                    <td><?= $u['updated_at'] ?></td>
                    <td>
                        <?php if($u['id'] != $usuario['id']): ?>
                            <button class="btn-editar" onclick="abrirModal('editar', <?= $u['id'] ?>, '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', '<?= $u['rol'] ?>', '<?= $u['estado'] ?>')">Editar</button>
                            <form style="display:inline;" method="POST" onsubmit="return confirm('¿Desea eliminar este usuario?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-eliminar">Eliminar</button>
                            </form>
                        <?php else: ?>
                            <button class="btn-editar" disabled title="No puedes editar tu propia cuenta"><i class="fa fa-lock"></i></button>
                            <button class="btn-eliminar" disabled title="No puedes eliminar tu propia cuenta"><i class="fa fa-lock"></i></button>
                        <?php endif; ?>
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
            <input type="hidden" name="id" id="usuario-id">
            <label>Nombre</label>
            <input type="text" name="nombre" id="nombre" required>
            <label>Email</label>
            <input type="email" name="email" id="email" required>
            <label>Contraseña</label>
            <input type="password" name="password" id="password" required>
            <label>Rol</label>
            <select name="rol" id="rol" required>
                <option value="veterinario">Veterinario</option>
                <option value="recepcionista">Recepcionista</option>
            </select>
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
function abrirModal(accion, id='', nombre='', email='', rol='', estado='') {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('accion').value = accion;
    document.getElementById('modal-title').innerText = accion === 'agregar' ? 'Agregar Usuario' : 'Editar Usuario';
    document.getElementById('usuario-id').value = id;
    document.getElementById('nombre').value = nombre;
    document.getElementById('email').value = email;
    document.getElementById('password').required = accion === 'agregar';
    document.getElementById('rol').value = rol || 'veterinario';
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
