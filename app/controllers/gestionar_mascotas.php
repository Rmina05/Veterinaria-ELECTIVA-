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
        $cliente_id = intval($_POST['cliente_id']);
        $nombre = trim($_POST['nombre']);
        $especie = $_POST['especie'];
        $raza = trim($_POST['raza']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $sexo = $_POST['sexo'];
        $peso = $_POST['peso'];
        $color = trim($_POST['color']);
        $observaciones = trim($_POST['observaciones']);

        $stmt = $db->prepare("CALL insertar_mascota(:cliente_id, :nombre, :especie, :raza, :fecha_nacimiento, :sexo, :peso, :color, :observaciones)");
        $stmt->bindValue(':cliente_id', $cliente_id);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':especie', $especie);
        $stmt->bindValue(':raza', $raza);
        $stmt->bindValue(':fecha_nacimiento', $fecha_nacimiento);
        $stmt->bindValue(':sexo', $sexo);
        $stmt->bindValue(':peso', $peso);
        $stmt->bindValue(':color', $color);
        $stmt->bindValue(':observaciones', $observaciones);

        if ($stmt->execute()) {
            $mensaje = "Mascota agregada correctamente.";
        } else {
            $errores[] = "Error al agregar mascota.";
        }
    }

    if ($accion === 'editar') {
        $id = intval($_POST['id']);
        $cliente_id = intval($_POST['cliente_id']);
        $nombre = trim($_POST['nombre']);
        $especie = $_POST['especie'];
        $raza = trim($_POST['raza']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $sexo = $_POST['sexo'];
        $peso = $_POST['peso'];
        $color = trim($_POST['color']);
        $observaciones = trim($_POST['observaciones']);
        $estado = $_POST['estado'];

        $stmt = $db->prepare("CALL actualizar_mascota(:id, :cliente_id, :nombre, :especie, :raza, :fecha_nacimiento, :sexo, :peso, :color, :observaciones, :estado)");
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':cliente_id', $cliente_id);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':especie', $especie);
        $stmt->bindValue(':raza', $raza);
        $stmt->bindValue(':fecha_nacimiento', $fecha_nacimiento);
        $stmt->bindValue(':sexo', $sexo);
        $stmt->bindValue(':peso', $peso);
        $stmt->bindValue(':color', $color);
        $stmt->bindValue(':observaciones', $observaciones);
        $stmt->bindValue(':estado', $estado);

        if ($stmt->execute()) {
            $mensaje = "Mascota actualizada correctamente.";
        } else {
            $errores[] = "Error al actualizar mascota.";
        }
    }

    if ($accion === 'eliminar') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("CALL eliminar_mascota(:id)");
        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            $mensaje = "Mascota eliminada correctamente.";
        } else {
            $errores[] = "Error al eliminar mascota.";
        }
    }
}

// FILTRO
$nombreCliente = $_GET['cliente'] ?? '';
$especieFiltro = $_GET['especie'] ?? '';

// Consulta de mascotas con filtro
$sql = "SELECT m.id, m.nombre, m.especie, m.raza, m.fecha_nacimiento, m.sexo, m.peso, m.color, m.estado, m.cliente_id, CONCAT(c.nombre,' ',c.apellido) AS cliente
        FROM mascotas m
        INNER JOIN clientes c ON m.cliente_id=c.id
        WHERE 1=1";
$params = [];

if ($nombreCliente) {
    $sql .= " AND CONCAT(c.nombre,' ',c.apellido) LIKE :cliente";
    $params[':cliente'] = "%$nombreCliente%";
}
if ($especieFiltro) {
    $sql .= " AND m.especie = :especie";
    $params[':especie'] = $especieFiltro;
}
$sql .= " ORDER BY m.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los clientes para el select
$clientesStmt = $db->query("SELECT id, nombre, apellido FROM clientes WHERE estado='activo'");
$clientes = $clientesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Mascotas - Lugo Vet</title>
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
.btn-agregar, .btn-limpiar {background:#2c5f7f; color:white; margin-bottom:20px;}
.btn-editar {background:#739ee3; color:white;}
.btn-eliminar {background:#c0392b; color:white;}
form input, form select, form textarea {padding:8px; margin-bottom:10px; width:100%; border:1px solid #ccc; border-radius:5px; box-sizing:border-box;}
textarea {resize: vertical;}
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center;}
.modal-content {background:white; padding:20px; border-radius:15px; max-width:400px; width:90%; position:relative; overflow-y:auto; max-height:90vh;}
.close {position:absolute; top:15px; right:15px; font-size:1.5rem; cursor:pointer; color:#333;}
button.btn-agregar-modal {width:100%; padding:10px;}
.filtro {margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;}
.filtro input, .filtro select {width:auto; flex:1;}
@media(max-width:500px){
    .modal-content{padding:15px;}
}
</style>
</head>
<body>
<div class="container">
<h1>Gestionar Mascotas</h1>

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

<!-- Formulario de filtro -->
<form method="GET" class="filtro">
    <input type="text" name="cliente" placeholder="Buscar por cliente" value="<?= htmlspecialchars($nombreCliente) ?>">
    <select name="especie">
        <option value="">Todas las especies</option>
        <option value="perro" <?= $especieFiltro==='perro'?'selected':'' ?>>Perro</option>
        <option value="gato" <?= $especieFiltro==='gato'?'selected':'' ?>>Gato</option>
        <option value="otro" <?= $especieFiltro==='otro'?'selected':'' ?>>Otro</option>
    </select>
    <button type="submit" class="btn-agregar">Filtrar</button>
    <a href="gestionar_mascotas.php"><button type="button" class="btn-limpiar">Limpiar</button></a>
</form>

<button class="btn-agregar" onclick="abrirModal('agregar')">Agregar Mascota</button>

<!-- Tabla de mascotas -->
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Especie</th>
            <th>Raza</th>
            <th>Fecha Nac.</th>
            <th>Sexo</th>
            <th>Peso</th>
            <th>Color</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($mascotas as $m): ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><?= htmlspecialchars($m['nombre']) ?></td>
                <td><?= ucfirst($m['especie']) ?></td>
                <td><?= htmlspecialchars($m['raza']) ?></td>
                <td><?= $m['fecha_nacimiento'] ?></td>
                <td><?= ucfirst($m['sexo']) ?></td>
                <td><?= $m['peso'] ?></td>
                <td><?= htmlspecialchars($m['color']) ?></td>
                <td><?= htmlspecialchars($m['cliente']) ?></td>
                <td><?= ucfirst($m['estado']) ?></td>
                <td>
                    <button class="btn-editar" onclick="abrirModal('editar', <?= $m['id'] ?>, '<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>', '<?= $m['especie'] ?>', '<?= htmlspecialchars($m['raza'], ENT_QUOTES) ?>', '<?= $m['fecha_nacimiento'] ?>', '<?= $m['sexo'] ?>', '<?= $m['peso'] ?>', '<?= htmlspecialchars($m['color'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['observaciones'] ?? '', ENT_QUOTES) ?>', <?= $m['cliente_id'] ?>, '<?= $m['estado'] ?>')">Editar</button>
                    <form style="display:inline;" method="POST" onsubmit="return confirm('¿Desea eliminar esta mascota?');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn-eliminar">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Modal agregar/editar -->
<div class="modal" id="modal">
<div class="modal-content">
    <span class="close" onclick="cerrarModal()">&times;</span>
    <h2 id="modal-title"></h2>
    <form method="POST" id="modal-form">
        <input type="hidden" name="accion" id="accion">
        <input type="hidden" name="id" id="mascota-id">

        <label>Cliente</label>
        <select name="cliente_id" id="cliente_id" required>
            <option value="">Seleccione un cliente</option>
            <?php foreach($clientes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'].' '.$c['apellido']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Nombre</label>
        <input type="text" name="nombre" id="nombre" required>
        <label>Especie</label>
        <select name="especie" id="especie" required>
            <option value="perro">Perro</option>
            <option value="gato">Gato</option>
            <option value="otro">Otro</option>
        </select>
        <label>Raza</label>
        <input type="text" name="raza" id="raza">
        <label>Fecha de Nacimiento</label>
        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
        <label>Sexo</label>
        <select name="sexo" id="sexo" required>
            <option value="macho">Macho</option>
            <option value="hembra">Hembra</option>
        </select>
        <label>Peso (kg)</label>
        <input type="number" step="0.01" name="peso" id="peso">
        <label>Color</label>
        <input type="text" name="color" id="color">
        <label>Observaciones</label>
        <textarea name="observaciones" id="observaciones" rows="3"></textarea>
        <label>Estado</label>
        <select name="estado" id="estado">
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
        </select>

        <button type="submit" class="btn-agregar-modal">Guardar</button>
    </form>
</div>
</div>

<script>
function abrirModal(accion, id='', nombre='', especie='', raza='', fecha='', sexo='', peso='', color='', observaciones='', cliente_id='', estado='activo'){
    document.getElementById('modal').style.display='flex';
    document.getElementById('accion').value=accion;
    document.getElementById('modal-title').innerText=accion==='agregar'?'Agregar Mascota':'Editar Mascota';
    document.getElementById('mascota-id').value=id;
    document.getElementById('nombre').value=nombre;
    document.getElementById('especie').value=especie;
    document.getElementById('raza').value=raza;
    document.getElementById('fecha_nacimiento').value=fecha;
    document.getElementById('sexo').value=sexo;
    document.getElementById('peso').value=peso;
    document.getElementById('color').value=color;
    document.getElementById('observaciones').value=observaciones;
    document.getElementById('cliente_id').value=cliente_id;
    document.getElementById('estado').value=estado;
}

function cerrarModal(){
    document.getElementById('modal').style.display='none';
}

window.onclick=function(event){
    if(event.target==document.getElementById('modal')) cerrarModal();
}
</script>
</body>
</html>
