<?php
require_once __DIR__ . '/../config/coneccion.php';
require_once __DIR__ . '/../app/helpers/sesion.php';

// Verificar si el usuario está logueado
if (!Sesion::estaLogueado()) {
    header("Location: login.php");
    exit;
}

$db = DB::conn();
$usuario = Sesion::obtenerUsuarioActual();
$usuario_id = $usuario['id'];
$rol = $usuario['rol'];

// Cargar citas según el rol
try {
    if ($rol === 'veterinario') {
        $stmt = $db->prepare("
            SELECT c.id, m.nombre AS mascota, CONCAT(cli.nombre, ' ', cli.apellido) AS propietario,
                   u.nombre AS veterinario, c.fecha_cita, c.hora_cita, c.motivo, c.estado,
                   m.id as mascota_id, m.especie, m.raza, m.peso
            FROM citas c
            INNER JOIN mascotas m ON c.mascota_id = m.id
            INNER JOIN clientes cli ON m.cliente_id = cli.id
            INNER JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.usuario_id = :usuario_id
            ORDER BY c.fecha_cita DESC, c.hora_cita ASC
        ");
        $stmt->bindValue(':usuario_id', $usuario_id);
    } else {
        $stmt = $db->prepare("
            SELECT c.id, m.nombre AS mascota, CONCAT(cli.nombre, ' ', cli.apellido) AS propietario,
                   u.nombre AS veterinario, c.fecha_cita, c.hora_cita, c.motivo, c.estado,
                   m.id as mascota_id, m.especie, m.raza, m.peso
            FROM citas c
            INNER JOIN mascotas m ON c.mascota_id = m.id
            INNER JOIN clientes cli ON m.cliente_id = cli.id
            INNER JOIN usuarios u ON c.usuario_id = u.id
            ORDER BY c.fecha_cita DESC, c.hora_cita ASC
        ");
    }
    $stmt->execute();
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar las citas: " . $e->getMessage());
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['id'])) {
    $accion = $_POST['accion'];
    $id = intval($_POST['id']);

    try {
        $db->beginTransaction();

        if ($accion === 'cancelar') {
            // Cambiar estado a cancelada
            $stmt = $db->prepare("UPDATE citas SET estado = 'cancelada', updated_at = NOW() WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();
            
            $db->commit();
            header('Location: mis_citas.php?msg=cancelada');
            exit;
        }

        if ($accion === 'completar') {
            // Obtener datos de la cita
            $cita_id = $id;
            $mascota_id = intval($_POST['mascota_id']);
            $diagnostico = trim($_POST['diagnostico']);
            $tratamiento = trim($_POST['tratamiento']);
            $observaciones = trim($_POST['observaciones']);
            $emitir_receta = isset($_POST['emitir_receta']) ? 1 : 0;

            // Validar campos requeridos
            if (empty($diagnostico) || empty($tratamiento)) {
                $db->rollBack();
                header('Location: mis_citas.php?error=campos_vacios');
                exit;
            }

            // 1. Actualizar estado de la cita
            $stmt = $db->prepare("UPDATE citas SET estado = 'completada', updated_at = NOW() WHERE id = :id");
            $stmt->bindValue(':id', $cita_id);
            $stmt->execute();

            // 2. Insertar historial médico
            $stmt = $db->prepare("
                INSERT INTO historiales_medicos 
                (cita_id, diagnostico, tratamiento, observaciones, veterinario_id, fecha_atencion, created_at, updated_at)
                VALUES (:cita_id, :diagnostico, :tratamiento, :observaciones, :veterinario_id, NOW(), NOW(), NOW())
            ");
            $stmt->execute([
                ':cita_id' => $cita_id,
                ':diagnostico' => $diagnostico,
                ':tratamiento' => $tratamiento,
                ':observaciones' => $observaciones,
                ':veterinario_id' => $usuario_id
            ]);

            $historial_id = $db->lastInsertId();

            // 3. Si marcó emitir receta, crear receta
            if ($emitir_receta) {
                $medicamentos = trim($_POST['medicamentos'] ?? '');
                $instrucciones_receta = trim($_POST['instrucciones_receta'] ?? '');
                $duracion_dias = intval($_POST['duracion_dias'] ?? 0);

                if (!empty($medicamentos) && !empty($instrucciones_receta)) {
                    $stmt = $db->prepare("
                        INSERT INTO recetas 
                        (historial_id, instrucciones, duracion_dias, veterinario_id, fecha_emision, created_at, updated_at)
                        VALUES (:historial_id, :instrucciones, :duracion_dias, :veterinario_id, NOW(), NOW(), NOW())
                    ");
                    $stmt->execute([
                        ':historial_id' => $historial_id,
                        ':instrucciones' => "MEDICAMENTOS:\n{$medicamentos}\n\nINSTRUCCIONES:\n{$instrucciones_receta}",
                        ':duracion_dias' => $duracion_dias,
                        ':veterinario_id' => $usuario_id
                    ]);
                }
            }

            $db->commit();
            header('Location: mis_citas.php?msg=completada');
            exit;
        }

    } catch (PDOException $e) {
        $db->rollBack();
        die("❌ Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis Citas - Lugo Vet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
h1 {color:#2c5f7f;}
.container {max-width:1200px; margin:0 auto;}
.header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
.btn-volver {background:#6c757d; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; transition:0.3s;}
.btn-volver:hover {background:#5a6268;}
.mensaje {background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; border-radius:8px; margin-bottom:20px;}
.error {background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; border-radius:8px; margin-bottom:20px;}
table {width:100%; border-collapse:collapse; margin-bottom:20px; background:white;}
table th, table td {border:1px solid #ddd; padding:12px; text-align:left;}
table th {background:#739ee3; color:white;}
table tr:nth-child(even){background:#f2f2f2;}
button {padding:8px 15px; border:none; border-radius:5px; cursor:pointer; transition:0.3s;}
button:hover {opacity:0.8;}
.btn-agregar {background:#2c5f7f; color:white; margin-bottom:20px;}
.btn-editar {background:#739ee3; color:white;}
.btn-eliminar {background:#c0392b; color:white;}
.btn-completar {background:#28a745; color:white;}
.btn-cancelar {background:#dc3545; color:white;}
.btn-detalle {background:#17a2b8; color:white;}
.btn-historial {background:#ffc107; color:#333;}
form input, form select, form textarea {padding:8px; margin-bottom:10px; width:100%; border:1px solid #ccc; border-radius:5px;}
textarea {min-height:80px; resize:vertical; font-family:sans-serif;}
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000;}
.modal-content {background:white; padding:30px; border-radius:15px; max-width:700px; width:90%; max-height:90vh; overflow-y:auto; position:relative;}
.close {position:absolute; top:15px; right:15px; font-size:1.5rem; cursor:pointer; color:#333;}
.estado-programada {color:#007bff; font-weight:bold;}
.estado-completada {color:#28a745; font-weight:bold;}
.estado-cancelada {color:#dc3545; font-weight:bold;}
.checkbox-group {margin:15px 0; display:flex; align-items:center; gap:10px;}
.receta-fields {display:none; background:#f8f9fa; padding:15px; border-radius:8px; margin-top:10px;}
.info-mascota {background:#e7f3ff; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #2c5f7f;}
.info-mascota h3 {color:#2c5f7f; margin-bottom:10px;}
.info-mascota p {margin:5px 0;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-calendar-check"></i> Mis Citas Programadas</h1>
        <a href="/lugo-vet - copia/app/controllers/dashboard_veterinario.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'completada'): ?>
            <div class="mensaje">Cita completada y registrada correctamente</div>
        <?php elseif ($_GET['msg'] === 'cancelada'): ?>
            <div class="mensaje">Cita cancelada correctamente</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <?php if ($_GET['error'] === 'campos_vacios'): ?>
            <div class="error">Error: El diagnóstico y tratamiento son obligatorios.</div>
        <?php endif; ?>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Mascota</th>
                <th>Propietario</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Motivo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($citas) > 0): ?>
                <?php foreach ($citas as $cita): ?>
                    <tr>
                        <td><?= $cita['id'] ?></td>
                        <td><i class="fas fa-paw"></i> <?= htmlspecialchars($cita['mascota']) ?></td>
                        <td><?= htmlspecialchars($cita['propietario']) ?></td>
                        <td><?= date('d/m/Y', strtotime($cita['fecha_cita'])) ?></td>
                        <td><?= date('h:i A', strtotime($cita['hora_cita'])) ?></td>
                        <td><?= htmlspecialchars(substr($cita['motivo'], 0, 40)) . (strlen($cita['motivo']) > 40 ? '...' : '') ?></td>
                        <td class="estado-<?= htmlspecialchars($cita['estado']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $cita['estado'])) ?>
                        </td>
                        <td>
                            <?php if ($cita['estado'] === 'programada'): ?>
                                <button class="btn-completar" onclick="abrirModalCompletar(<?= $cita['id'] ?>, '<?= htmlspecialchars($cita['mascota'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cita['propietario'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cita['especie'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cita['raza'], ENT_QUOTES) ?>', <?= $cita['peso'] ?>, <?= $cita['mascota_id'] ?>)">
                                    <i class="fas fa-check-circle"></i> Completar
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que desea cancelar esta cita?')">
                                    <input type="hidden" name="id" value="<?= $cita['id'] ?>">
                                    <input type="hidden" name="accion" value="cancelar">
                                    <button type="submit" class="btn-cancelar"><i class="fas fa-times-circle"></i> Cancelar</button>
                                </form>
                            <?php elseif ($cita['estado'] === 'completada'): ?>
                              <a href="ver_historial.php?cita_id=<?= $cita['id'] ?>" class="btn-historial" style="text-decoration:none; display:inline-block; padding:8px 15px;">
                                  <i class="fas fa-file-medical"></i> Ver Historial
                              </a>
                            <?php endif; ?>
                            <button class="btn-detalle" onclick="verDetalle(<?= $cita['id'] ?>, '<?= htmlspecialchars($cita['mascota'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cita['propietario'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cita['motivo'], ENT_QUOTES) ?>', '<?= $cita['estado'] ?>')">
                                <i class="fas fa-info-circle"></i> Detalle
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:20px; color:#777;">
                        <i class="fas fa-info-circle"></i> No hay citas registradas
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal COMPLETAR CITA -->
<div class="modal" id="modalCompletar">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalCompletar()">&times;</span>
        <h2><i class="fas fa-stethoscope"></i> Completar Atención Médica</h2>
        
        <div class="info-mascota" id="infoMascota"></div>

        <form method="POST">
            <input type="hidden" name="accion" value="completar">
            <input type="hidden" name="id" id="cita_id">
            <input type="hidden" name="mascota_id" id="mascota_id">

            <label><strong><i class="fas fa-diagnoses"></i> Diagnóstico *</strong></label>
            <textarea name="diagnostico" required placeholder="Descripción del diagnóstico..."></textarea>

            <label><strong><i class="fas fa-pills"></i> Tratamiento *</strong></label>
            <textarea name="tratamiento" required placeholder="Tratamiento prescrito..."></textarea>

            <label><strong><i class="fas fa-notes-medical"></i> Observaciones</strong></label>
            <textarea name="observaciones" placeholder="Observaciones adicionales..."></textarea>

            <div class="checkbox-group">
                <input type="checkbox" name="emitir_receta" id="emitir_receta" onchange="toggleReceta()">
                <label for="emitir_receta"><strong><i class="fas fa-prescription"></i> Emitir receta médica</strong></label>
            </div>

            <div class="receta-fields" id="recetaFields">
                <h3 style="color:#2c5f7f; margin-bottom:10px;"><i class="fas fa-file-prescription"></i> Datos de la Receta</h3>
                
                <label><strong>Medicamentos</strong></label>
                <textarea name="medicamentos" placeholder="Lista de medicamentos..."></textarea>

                <label><strong>Instrucciones de Administración</strong></label>
                <textarea name="instrucciones_receta" placeholder="Dosis, frecuencia, etc..."></textarea>

                <label><strong>Duración del Tratamiento (días)</strong></label>
                <input type="number" name="duracion_dias" min="1" placeholder="Ej: 7">
            </div>

            <button type="submit" class="btn-agregar" style="width:100%; margin-top:15px;">
                <i class="fas fa-save"></i> Guardar y Completar Cita
            </button>
        </form>
    </div>
</div>

<!-- Modal Ver Detalle -->
<div class="modal" id="detalleModal">
    <div class="modal-content">
        <span class="close" onclick="cerrarDetalle()">&times;</span>
        <h2 id="detalleTitulo"></h2>
        <p><strong><i class="fas fa-paw"></i> Mascota:</strong> <span id="detalleMascota"></span></p>
        <p><strong><i class="fas fa-user"></i> Propietario:</strong> <span id="detallePropietario"></span></p>
        <p><strong><i class="fas fa-comment-medical"></i> Motivo:</strong> <span id="detalleMotivo"></span></p>
        <p><strong><i class="fas fa-info-circle"></i> Estado:</strong> <span id="detalleEstado"></span></p>
    </div>
</div>

<script> // Script para manejar los modales y acciones
function abrirModalCompletar(id, mascota, propietario, especie, raza, peso, mascota_id) {
    document.getElementById('modalCompletar').style.display = 'flex';
    document.getElementById('cita_id').value = id;
    document.getElementById('mascota_id').value = mascota_id;
    
    document.getElementById('infoMascota').innerHTML = `
        <h3><i class="fas fa-dog"></i> Información del Paciente</h3>
        <p><strong>Mascota:</strong> ${mascota}</p>
        <p><strong>Propietario:</strong> ${propietario}</p>
        <p><strong>Especie:</strong> ${especie} | <strong>Raza:</strong> ${raza}</p>
        <p><strong>Peso:</strong> ${peso} kg</p>
    `;
}

function cerrarModalCompletar() {
    document.getElementById('modalCompletar').style.display = 'none';
}

function toggleReceta() {
    const checkbox = document.getElementById('emitir_receta');
    const fields = document.getElementById('recetaFields');
    fields.style.display = checkbox.checked ? 'block' : 'none';
}

function verDetalle(id, mascota, propietario, motivo, estado) {
    document.getElementById('detalleModal').style.display = 'flex';
    document.getElementById('detalleTitulo').innerText = 'Detalle de la cita #' + id;
    document.getElementById('detalleMascota').innerText = mascota;
    document.getElementById('detallePropietario').innerText = propietario;
    document.getElementById('detalleMotivo').innerText = motivo;
    document.getElementById('detalleEstado').innerText = estado.charAt(0).toUpperCase() + estado.slice(1).replace('_', ' ');
}

function cerrarDetalle() {
    document.getElementById('detalleModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('detalleModal')) {
        cerrarDetalle();
    }
    if (event.target == document.getElementById('modalCompletar')) {
        cerrarModalCompletar();
    }
}
</script>
</body>
</html>
