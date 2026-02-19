<?php
require_once __DIR__ . '/../config/coneccion.php';
require_once __DIR__ . '/../app/helpers/sesion.php';

if (!Sesion::estaLogueado()) {
    header("Location: login.php");
    exit;
}

$db = DB::conn();
$usuario = Sesion::obtenerUsuarioActual();

// Obtener ID de la cita
$cita_id = intval($_GET['cita_id'] ?? 0);

if ($cita_id === 0) {
    die("❌ ID de cita inválido");
}

// Obtener información completa
try {
    $stmt = $db->prepare("
        SELECT 
            c.id as cita_id,
            c.fecha_cita,
            c.hora_cita,
            c.motivo,
            m.nombre as mascota_nombre,
            m.especie,
            m.raza,
            m.sexo,
            m.peso,
            m.fecha_nacimiento,
            CONCAT(cli.nombre, ' ', cli.apellido) as propietario,
            cli.telefono,
            cli.correo,
            h.id as historial_id,
            h.diagnostico,
            h.tratamiento,
            h.observaciones,
            h.fecha_atencion,
            vet.nombre as veterinario_nombre,
            r.id as receta_id,
            r.instrucciones as receta_instrucciones,
            r.duracion_dias,
            r.fecha_emision
        FROM citas c
        INNER JOIN mascotas m ON c.mascota_id = m.id
        INNER JOIN clientes cli ON m.cliente_id = cli.id
        INNER JOIN historiales_medicos h ON h.cita_id = c.id
        INNER JOIN usuarios vet ON h.veterinario_id = vet.id
        LEFT JOIN recetas r ON r.historial_id = h.id
        WHERE c.id = :cita_id
    ");
    $stmt->bindValue(':cita_id', $cita_id);
    $stmt->execute();
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$datos) {
        die("❌ No se encontró el historial para esta cita");
    }

} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage());
}

// Calcular edad de la mascota
$edad = '';
if ($datos['fecha_nacimiento']) {
    $nacimiento = new DateTime($datos['fecha_nacimiento']);
    $hoy = new DateTime();
    $diff = $nacimiento->diff($hoy);
    $edad = $diff->y . ' años, ' . $diff->m . ' meses';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historial Médico - Lugo Vet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:sans-serif; background:#e0f4ff; padding:20px;}
.container {max-width:900px; margin:0 auto;}
.header {background:white; padding:25px; border-radius:15px; margin-bottom:20px; box-shadow:0 5px 15px rgba(0,0,0,0.1); display:flex; justify-content:space-between; align-items:center;}
h1 {color:#2c5f7f;}
.btn-back {background:#739ee3; color:white; padding:10px 20px; text-decoration:none; border-radius:8px; transition:0.3s;}
.btn-back:hover {background:#2c5f7f;}
.card {background:white; padding:25px; border-radius:15px; margin-bottom:20px; box-shadow:0 5px 15px rgba(0,0,0,0.1);}
.card h2 {color:#2c5f7f; margin-bottom:15px; border-bottom:2px solid #739ee3; padding-bottom:10px;}
.info-grid {display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;}
.info-item {padding:10px; background:#f8f9fa; border-radius:8px;}
.info-item label {display:block; color:#666; font-size:0.9rem; margin-bottom:5px;}
.info-item p {color:#333; font-weight:600;}
.text-section {background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:15px;}
.text-section h3 {color:#2c5f7f; margin-bottom:10px; font-size:1.1rem;}
.text-section p {color:#333; line-height:1.6; white-space:pre-wrap;}
.receta-box {background:#fff3cd; border:2px solid #ffc107; padding:20px; border-radius:8px;}
.receta-box h3 {color:#856404; margin-bottom:15px;}
.btn-descargar {background:#28a745; color:white; padding:12px 24px; border:none; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block; transition:0.3s;}
.btn-descargar:hover {background:#218838;}
.sin-receta {text-align:center; padding:20px; color:#999;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📋 Historial Médico</h1>
        <a href="mis_citas.php" class="btn-back"><i class="fa fa-arrow-left"></i> Volver</a>
    </div>

    <!-- INFORMACIÓN DEL PACIENTE -->
    <div class="card">
        <h2>🐾 Información del Paciente</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Nombre de la Mascota</label>
                <p><?= htmlspecialchars($datos['mascota_nombre']) ?></p>
            </div>
            <div class="info-item">
                <label>Propietario</label>
                <p><?= htmlspecialchars($datos['propietario']) ?></p>
            </div>
            <div class="info-item">
                <label>Especie</label>
                <p><?= htmlspecialchars(ucfirst($datos['especie'])) ?></p>
            </div>
            <div class="info-item">
                <label>Raza</label>
                <p><?= htmlspecialchars($datos['raza'] ?: 'No especificada') ?></p>
            </div>
            <div class="info-item">
                <label>Sexo</label>
                <p><?= htmlspecialchars(ucfirst($datos['sexo'])) ?></p>
            </div>
            <div class="info-item">
                <label>Edad</label>
                <p><?= htmlspecialchars($edad ?: 'No especificada') ?></p>
            </div>
            <div class="info-item">
                <label>Peso</label>
                <p><?= htmlspecialchars($datos['peso']) ?> kg</p>
            </div>
            <div class="info-item">
                <label>Teléfono Propietario</label>
                <p><?= htmlspecialchars($datos['telefono']) ?></p>
            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DE LA CITA -->
    <div class="card">
        <h2>📅 Información de la Consulta</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Fecha de Atención</label>
                <p><?= date('d/m/Y', strtotime($datos['fecha_atencion'])) ?></p>
            </div>
            <div class="info-item">
                <label>Hora</label>
                <p><?= date('h:i A', strtotime($datos['fecha_atencion'])) ?></p>
            </div>
            <div class="info-item">
                <label>Veterinario</label>
                <p>Dr(a). <?= htmlspecialchars($datos['veterinario_nombre']) ?></p>
            </div>
            <div class="info-item">
                <label>Motivo de Consulta</label>
                <p><?= htmlspecialchars($datos['motivo']) ?></p>
            </div>
        </div>
    </div>

    <!-- DIAGNÓSTICO Y TRATAMIENTO -->
    <div class="card">
        <h2>🩺 Diagnóstico y Tratamiento</h2>
        
        <div class="text-section">
            <h3>📝 Diagnóstico</h3>
            <p><?= nl2br(htmlspecialchars($datos['diagnostico'])) ?></p>
        </div>

        <div class="text-section">
            <h3>💊 Tratamiento</h3>
            <p><?= nl2br(htmlspecialchars($datos['tratamiento'])) ?></p>
        </div>

        <?php if (!empty($datos['observaciones'])): ?>
            <div class="text-section">
                <h3>📋 Observaciones</h3>
                <p><?= nl2br(htmlspecialchars($datos['observaciones'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- RECETA -->
    <div class="card">
        <h2>💊 Receta Médica</h2>
        <?php if ($datos['receta_id']): ?>
            <div class="receta-box">
                <h3>📄 Receta #<?= str_pad($datos['receta_id'], 6, '0', STR_PAD_LEFT) ?></h3>
                
                <div class="text-section" style="background:white;">
                    <p><?= nl2br(htmlspecialchars($datos['receta_instrucciones'])) ?></p>
                </div>

                <div class="info-grid" style="margin-top:15px;">
                    <div class="info-item" style="background:white;">
                        <label>Fecha de Emisión</label>
                        <p><?= date('d/m/Y', strtotime($datos['fecha_emision'])) ?></p>
                    </div>
                    <div class="info-item" style="background:white;">
                        <label>Duración del Tratamiento</label>
                        <p><?= htmlspecialchars($datos['duracion_dias']) ?> días</p>
                    </div>
                </div>

                <div style="margin-top:20px; text-align:center;">
                    <a href="../app/controllers/descargar_receta.php?receta_id=<?= $datos['receta_id'] ?>" class="btn-descargar" target="_blank">
                        <i class="fa fa-download"></i> Descargar Receta en PDF
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="sin-receta">
                <i class="fa fa-prescription" style="font-size:3rem; display:block; margin-bottom:10px;"></i>
                <p>No se emitió receta para esta consulta</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>