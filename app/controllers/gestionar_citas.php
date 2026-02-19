<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';

if (!Sesion::estaLogueado()) {
    header('Location: login.php');
    exit;
}

// Obtener rol del usuario
$usuario = Sesion::obtenerUsuarioActual();
$rol = $usuario['rol'] ?? '';

// Solo administrador y recepcionista pueden acceder
if (!in_array($rol, ['administrador', 'recepcionista'])) {
    header('Location: login.php');
    exit;
}

$db = DB::conn();
$mensaje = '';
$errores = [];

// Manejo de formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
// Agregar o editar cita
    if ($accion === 'agregar' || $accion === 'editar') {
        $id = intval($_POST['id'] ?? 0);
        $mascota_id = intval($_POST['mascota_id']);
        $usuario_id = intval($_POST['veterinario_id']);
        $fecha = $_POST['fecha'];
        $hora = $_POST['hora'];
        $motivo = trim($_POST['motivo']);
        $estado = $_POST['estado'] ?? 'programada';
        $historial = $_POST['historial'] ?? '';
        $receta = $_POST['receta'] ?? '';

        // Verificar si la cita ya existe para la misma mascota y hora
        $stmt = $db->prepare("SELECT COUNT(*) FROM citas WHERE mascota_id=:mascota_id AND fecha_cita=:fecha AND hora_cita=:hora" . ($accion==='editar' ? " AND id!=:id" : ""));
        $stmt->bindValue(':mascota_id', $mascota_id);
        $stmt->bindValue(':fecha', $fecha);
        $stmt->bindValue(':hora', $hora);
        if($accion==='editar') $stmt->bindValue(':id', $id);
        $stmt->execute();

        if($stmt->fetchColumn() > 0){
            $errores[] = "Esta mascota ya tiene una cita a esa hora.";
        } else {
            if($accion === 'agregar'){
                $stmt = $db->prepare("CALL insertar_cita(:mascota_id, :usuario_id, :fecha, :hora, :motivo)");
                $stmt->execute([
                    ':mascota_id' => $mascota_id,
                    ':usuario_id' => $usuario_id,
                    ':fecha' => $fecha,
                    ':hora' => $hora,
                    ':motivo' => $motivo
                ]);
                $mensaje = "✅ Cita agregada correctamente.";
            } else {
                $stmt = $db->prepare("CALL actualizar_cita(:id, :mascota_id, :usuario_id, :fecha, :hora, :motivo, :estado)");
                $stmt->execute([
                    ':id' => $id,
                    ':mascota_id' => $mascota_id,
                    ':usuario_id' => $usuario_id,
                    ':fecha' => $fecha,
                    ':hora' => $hora,
                    ':motivo' => $motivo,
                    ':estado' => $estado
                ]);
                $mensaje = "✅ Cita actualizada correctamente.";

                // Si la cita se marca como realizada, guardar historial y receta (solo si se proporcionaron)
                if($estado === 'realizada' && ($historial || $receta)) {
                    $stmt_hist = $db->prepare("INSERT INTO historial_mascotas (mascota_id, cita_id, historial, receta, fecha_registro) VALUES (:mascota_id, :cita_id, :historial, :receta, NOW())");
                    $stmt_hist->execute([
                        ':mascota_id' => $mascota_id,
                        ':cita_id' => $id,
                        ':historial' => $historial,
                        ':receta' => $receta
                    ]);
                    $mensaje .= " Historial y receta registrados.";
                }
            }
        }
    }
// Eliminar cita
    if ($accion === 'eliminar') {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("CALL eliminar_cita(:id)");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $mensaje = "🗑️ Cita eliminada correctamente.";
    }
}

// Obtener mascotas activas
$mascotas = $db->query("SELECT id, nombre FROM mascotas WHERE estado='activo'")->fetchAll(PDO::FETCH_ASSOC);

// Obtener veterinarios activos
$veterinarios = $db->query("SELECT id, nombre FROM usuarios WHERE rol='veterinario' AND estado='activo'")->fetchAll(PDO::FETCH_ASSOC);

// Consultar citas
$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');
$consulta = $db->prepare("
    SELECT c.id, m.id as mascota_id, m.nombre AS mascota, CONCAT(cli.nombre,' ',cli.apellido) AS propietario,
           u.nombre AS veterinario, c.fecha_cita, c.hora_cita, c.motivo, c.estado
    FROM citas c
    INNER JOIN mascotas m ON c.mascota_id = m.id
    INNER JOIN clientes cli ON m.cliente_id = cli.id
    INNER JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.fecha_cita = :fecha
    ORDER BY c.hora_cita ASC
");
$consulta->execute([':fecha'=>$filtro_fecha]);
$citas = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Determinar el dashboard de retorno según el rol
$dashboardUrl = $rol === 'administrador' ? 'dashboard.php' : 'dashboard_recepcionista.php';
$tituloRol = $rol === 'administrador' ? 'Administrador' : 'Recepcionista';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar Citas - <?= $tituloRol ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1 {color:#2c5f7f; margin-bottom:10px;}
.container {max-width:1200px; margin:0 auto;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
.btn-volver {background:#6c757d; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block; transition:0.3s;}
.btn-volver:hover {background:#5a6268;}
.mensaje {background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:8px; margin-bottom:20px;}
.errores {background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; border-radius:8px; margin-bottom:20px;}
table {width:100%; border-collapse:collapse; margin-bottom:20px; background:white;}
table th, table td {border:1px solid #ddd; padding:12px; text-align:left;}
table th {background:#739ee3; color:white;}
table tr:nth-child(even){background:#f2f2f2;}
button {padding:8px 15px; border:none; border-radius:5px; cursor:pointer; transition:0.3s;}
button:hover {opacity:0.8;}
.btn-agregar {background:#2c5f7f; color:white; margin-bottom:20px;}
.btn-editar {background:#739ee3; color:white;}
.btn-eliminar {background:#c0392b; color:white;}
form input, form select, form textarea {padding:8px; margin-bottom:10px; width:100%; border:1px solid #ccc; border-radius:5px;}
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000;}
.modal-content {background:white; padding:30px; border-radius:15px; max-width:600px; width:100%; position:relative; max-height:90vh; overflow-y:auto;}
.close {position:absolute; top:15px; right:15px; font-size:1.5rem; cursor:pointer; color:#333;}
.filtro-form {background:white; padding:15px; border-radius:8px; margin-bottom:20px;}
.estado-badge {padding:5px 10px; border-radius:20px; font-size:0.85rem; font-weight:bold;}
.estado-programada {background:#ffeaa7; color:#2d3436;}
.estado-realizada {background:#55efc4; color:#00b894;}
.estado-cancelada {background:#ff7675; color:#d63031;}
.rol-badge {padding:5px 10px; border-radius:20px; font-size:0.8rem; margin-left:10px;}
.badge-admin {background:#e74c3c; color:white;}
.badge-recepcionista {background:#3498db; color:white;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-calendar-check"></i> Gestionar Citas 
            <span class="rol-badge badge-<?= $rol ?>">
                <i class="fas fa-<?= $rol === 'administrador' ? 'crown' : 'user' ?>"></i> 
                <?= strtoupper($tituloRol) ?>
            </span>
        </h1>
        <a href="<?= $dashboardUrl ?>" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al Dashboard
        </a>
    </div>

    <div class="filtro-form">
        <form method="GET" style="display:flex; gap:10px; align-items:end;">
            <div style="flex:1;">
                <label><strong><i class="fas fa-filter"></i> Filtrar por fecha:</strong></label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>">
            </div>
            <button type="submit" class="btn-agregar"><i class="fas fa-search"></i> Filtrar</button>
        </form>
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

    <button class="btn-agregar" onclick="abrirModal('agregar')">
        <i class="fas fa-plus-circle"></i> Agregar Nueva Cita
    </button>

    <?php if(count($citas) === 0): ?>
        <p style="background:white; padding:20px; border-radius:8px; text-align:center; color:#777;">
            <i class="fas fa-info-circle"></i> No hay citas programadas para esta fecha.
        </p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Mascota</th>
                <th>Propietario</th>
                <th>Veterinario</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Motivo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($citas as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><i class="fas fa-paw"></i> <?= htmlspecialchars($c['mascota']) ?></td>
                    <td><?= htmlspecialchars($c['propietario']) ?></td>
                    <td><i class="fas fa-user-md"></i> <?= htmlspecialchars($c['veterinario']) ?></td>
                    <td><?= date('d/m/Y', strtotime($c['fecha_cita'])) ?></td>
                    <td><?= date('h:i A', strtotime($c['hora_cita'])) ?></td>
                    <td><?= htmlspecialchars($c['motivo']) ?></td>
                    <td>
                        <span class="estado-badge estado-<?= $c['estado'] ?>">
                            <?= ucfirst($c['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn-editar" 
                            onclick='abrirModal("editar", <?= $c['id'] ?>, <?= $c['mascota_id'] ?>, "<?= date('Y-m-d', strtotime($c['fecha_cita'])) ?>", "<?= $c['hora_cita'] ?>", "<?= htmlspecialchars($c['motivo'], ENT_QUOTES) ?>", "<?= $c['estado'] ?>")'>
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <form style="display:inline;" method="POST" onsubmit="return confirm('⚠️ ¿Está seguro de eliminar esta cita?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
            <input type="hidden" name="id" id="cita-id">

            <label><i class="fas fa-paw"></i> Mascota *</label>
            <select name="mascota_id" id="mascota_id" required>
                <option value="">Seleccione una mascota</option>
                <?php foreach($mascotas as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><i class="fas fa-user-md"></i> Veterinario *</label>
            <select name="veterinario_id" id="veterinario_id" required>
                <option value="">Seleccione un veterinario</option>
                <?php foreach($veterinarios as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><i class="fas fa-calendar"></i> Fecha *</label>
            <input type="date" name="fecha" id="fecha" required>

            <label><i class="fas fa-clock"></i> Hora *</label>
            <input type="time" name="hora" id="hora" required>

            <label><i class="fas fa-notes-medical"></i> Motivo *</label>
            <textarea name="motivo" id="motivo" rows="3" required></textarea>

            <label><i class="fas fa-info-circle"></i> Estado</label>
            <select name="estado" id="estado" onchange="toggleHistorialReceta()">
                <option value="programada">Programada</option>
                <option value="realizada">Realizada</option>
                <option value="cancelada">Cancelada</option>
            </select>

            <?php if($rol === 'administrador'): ?>
            <!-- Solo administrador puede agregar historial y receta -->
            <div id="historial-receta" style="display:none; margin-top:10px; padding:15px; background:#f8f9fa; border-radius:8px;">
                <p style="color:#666; font-size:0.9rem; margin-bottom:10px;">
                    <i class="fas fa-info-circle"></i> Solo disponible cuando el estado es "Realizada"
                </p>
                <label><i class="fas fa-file-medical"></i> Historial / Observaciones</label>
                <textarea name="historial" id="historial" rows="3" placeholder="Detalles de la consulta..."></textarea>
                <label><i class="fas fa-prescription"></i> Receta</label>
                <textarea name="receta" id="receta" rows="3" placeholder="Medicamentos recetados..."></textarea>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-agregar" style="margin-top:10px; width:100%;">
                <i class="fas fa-save"></i> Guardar Cita
            </button>
        </form>
    </div>
</div>

<script>
const esAdmin = <?= $rol === 'administrador' ? 'true' : 'false' ?>;

function abrirModal(accion, id='', mascota_id='', fecha='', hora='', motivo='', estado='programada') {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('accion').value = accion;
    document.getElementById('modal-title').innerText = accion === 'agregar' ? '➕ Agregar Nueva Cita' : '✏️ Editar Cita';
    document.getElementById('cita-id').value = id;
    document.getElementById('mascota_id').value = mascota_id;
    document.getElementById('fecha').value = fecha || new Date().toISOString().split('T')[0];
    document.getElementById('hora').value = hora;
    document.getElementById('motivo').value = motivo;
    document.getElementById('estado').value = estado;
    
    if(esAdmin) {
        toggleHistorialReceta();
    }
}

function cerrarModal(){
    document.getElementById('modal').style.display = 'none';
    document.getElementById('modal-form').reset();
}

window.onclick = function(event) {
    if(event.target == document.getElementById('modal')) cerrarModal();
}

function toggleHistorialReceta() {
    if(!esAdmin) return; // Solo para admin
    
    const estado = document.getElementById('estado').value;
    const div = document.getElementById('historial-receta');
    if(div) {
        div.style.display = estado === 'realizada' ? 'block' : 'none';
    }
}
</script>
</body>
</html>