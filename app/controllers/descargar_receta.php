<?php
require_once __DIR__ . '/../../config/coneccion.php';
require_once __DIR__ . '/../helpers/sesion.php';

if (!Sesion::estaLogueado()) {
    header("Location: ../../public/login.php");
    exit;
}

$db = DB::conn();
$receta_id = intval($_GET['receta_id'] ?? 0);

if ($receta_id === 0) {
    die("❌ ID de receta inválido");
}

// Obtener información completa de la receta
try {
    $stmt = $db->prepare("
        SELECT 
            r.id as receta_id,
            r.instrucciones,
            r.duracion_dias,
            r.fecha_emision,
            h.diagnostico,
            h.tratamiento,
            h.fecha_atencion,
            m.nombre as mascota_nombre,
            m.especie,
            m.raza,
            m.sexo,
            m.peso,
            m.fecha_nacimiento,
            CONCAT(cli.nombre, ' ', cli.apellido) as propietario,
            cli.telefono,
            cli.correo,
            cli.direccion,
            vet.nombre as veterinario_nombre
        FROM recetas r
        INNER JOIN historiales_medicos h ON r.historial_id = h.id
        INNER JOIN citas c ON h.cita_id = c.id
        INNER JOIN mascotas m ON c.mascota_id = m.id
        INNER JOIN clientes cli ON m.cliente_id = cli.id
        INNER JOIN usuarios vet ON r.veterinario_id = vet.id
        WHERE r.id = :receta_id
    ");
    $stmt->bindValue(':receta_id', $receta_id);
    $stmt->execute();
    $receta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receta) {
        die("❌ No se encontró la receta");
    }

} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage());
}

// Calcular edad de la mascota
$edad = '';
if ($receta['fecha_nacimiento']) {
    $nacimiento = new DateTime($receta['fecha_nacimiento']);
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
<title>Receta Médica #<?= str_pad($receta_id, 6, '0', STR_PAD_LEFT) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: white;
}
.receta-container {
    max-width: 800px;
    margin: 0 auto;
    border: 3px solid #2c5f7f;
    padding: 30px;
    background: white;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    border-bottom: 3px solid #2c5f7f;
    padding-bottom: 20px;
    margin-bottom: 25px;
}
.logo-section h1 {
    color: #2c5f7f;
    font-size: 2.5rem;
    margin-bottom: 5px;
}
.logo-section .subtitle {
    color: #666;
    font-size: 1rem;
    margin-bottom: 10px;
}
.logo-section .contact {
    font-size: 0.85rem;
    color: #888;
}
.receta-info {
    text-align: right;
}
.receta-numero {
    font-size: 1.8rem;
    font-weight: bold;
    color: #2c5f7f;
    margin-bottom: 10px;
}
.fecha {
    color: #666;
    font-size: 0.95rem;
}
.rx-symbol {
    font-size: 4rem;
    color: #2c5f7f;
    text-align: center;
    margin: 20px 0;
    font-weight: bold;
    font-style: italic;
}
.section {
    margin-bottom: 25px;
}
.section-title {
    color: #2c5f7f;
    font-size: 1.2rem;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e0e0e0;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}
.info-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}
.info-item label {
    display: block;
    color: #666;
    font-size: 0.85rem;
    margin-bottom: 5px;
    font-weight: bold;
}
.info-item p {
    color: #333;
    font-size: 0.95rem;
}
.prescripcion-box {
    background: #fff9e6;
    border: 2px solid #ffc107;
    padding: 20px;
    border-radius: 8px;
    min-height: 150px;
}
.prescripcion-box p {
    color: #333;
    line-height: 1.8;
    white-space: pre-wrap;
    font-size: 1.05rem;
}
.diagnostico-box {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #2c5f7f;
}
.diagnostico-box p {
    color: #333;
    line-height: 1.6;
}
.firma-section {
    margin-top: 50px;
    text-align: center;
    padding-top: 30px;
    border-top: 2px solid #e0e0e0;
}
.firma-line {
    border-top: 2px solid #333;
    width: 300px;
    margin: 0 auto 10px;
}
.footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e0e0e0;
    color: #666;
    font-size: 0.85rem;
}
.footer strong {
    color: #2c5f7f;
}
.validity {
    background: #fff3cd;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    margin-top: 20px;
    font-weight: bold;
    color: #856404;
}
@media print {
    body {padding: 0;}
    .no-print {display: none;}
}
</style>
</head>
<body>
<div class="receta-container">
    <!-- Encabezado -->
    <div class="header">
        <div class="logo-section">
            <h1>🐾 LUGO VET</h1>
            <p class="subtitle">Clínica Veterinaria Profesional</p>
            <p class="contact">
                Tel: (123) 456-7890 | info@lugovet.com<br>
                Dirección: Calle Principal #123, Ciudad
            </p>
        </div>
        <div class="receta-info">
            <div class="receta-numero">RECETA MÉDICA</div>
            <div class="receta-numero" style="font-size:1.3rem;">
                #<?= str_pad($receta_id, 6, '0', STR_PAD_LEFT) ?>
            </div>
            <div class="fecha">
                Fecha: <?= date('d/m/Y', strtotime($receta['fecha_emision'])) ?>
            </div>
        </div>
    </div>

    <!-- Símbolo Rx -->
    <div class="rx-symbol">℞</div>

    <!-- Información del Paciente -->
    <div class="section">
        <h3 class="section-title">🐾 INFORMACIÓN DEL PACIENTE</h3>
        <div class="info-grid">
            <div class="info-item">
                <label>Paciente:</label>
                <p><?= htmlspecialchars($receta['mascota_nombre']) ?></p>
            </div>
            <div class="info-item">
                <label>Propietario:</label>
                <p><?= htmlspecialchars($receta['propietario']) ?></p>
            </div>
            <div class="info-item">
                <label>Especie:</label>
                <p><?= htmlspecialchars(ucfirst($receta['especie'])) ?></p>
            </div>
            <div class="info-item">
                <label>Raza:</label>
                <p><?= htmlspecialchars($receta['raza'] ?: 'No especificada') ?></p>
            </div>
            <div class="info-item">
                <label>Sexo:</label>
                <p><?= htmlspecialchars(ucfirst($receta['sexo'])) ?></p>
            </div>
            <div class="info-item">
                <label>Peso:</label>
                <p><?= htmlspecialchars($receta['peso']) ?> kg</p>
            </div>
            <div class="info-item">
                <label>Edad:</label>
                <p><?= htmlspecialchars($edad ?: 'No especificada') ?></p>
            </div>
            <div class="info-item">
                <label>Teléfono:</label>
                <p><?= htmlspecialchars($receta['telefono']) ?></p>
            </div>
        </div>
    </div>

    <!-- Diagnóstico -->
    <div class="section">
        <h3 class="section-title">🩺 DIAGNÓSTICO</h3>
        <div class="diagnostico-box">
            <p><?= nl2br(htmlspecialchars($receta['diagnostico'])) ?></p>
        </div>
    </div>

    <!-- Prescripción -->
    <div class="section">
        <h3 class="section-title">💊 PRESCRIPCIÓN</h3>
        <div class="prescripcion-box">
            <p><?= nl2br(htmlspecialchars($receta['instrucciones'])) ?></p>
        </div>
    </div>

    <!-- Duración del Tratamiento -->
    <div class="section">
        <div class="info-grid">
            <div class="info-item">
                <label>Duración del Tratamiento:</label>
                <p><?= htmlspecialchars($receta['duracion_dias']) ?> días</p>
            </div>
            <div class="info-item">
                <label>Fecha de Atención:</label>
                <p><?= date('d/m/Y', strtotime($receta['fecha_atencion'])) ?></p>
            </div>
        </div>
    </div>

    <!-- Firma -->
    <div class="firma-section">
        <div class="firma-line"></div>
        <p><strong>Dr(a). <?= htmlspecialchars($receta['veterinario_nombre']) ?></strong></p>
        <p style="color:#666; font-size:0.9rem;">Médico Veterinario</p>
        <p style="color:#666; font-size:0.9rem;">Reg. Profesional: MV-XXXXX</p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><strong>LUGO VET - Clínica Veterinaria</strong></p>
        <p>Tel: (123) 456-7890 | Email: info@lugovet.com</p>
        <p>Dirección: Calle Principal #123, Ciudad</p>
        <p style="margin-top:10px; font-size:0.8rem;">
            Receta #<?= str_pad($receta_id, 6, '0', STR_PAD_LEFT) ?> | 
            Emitida el <?= date('d/m/Y H:i', strtotime($receta['fecha_emision'])) ?>
        </p>
    </div>
</div>

<script>
// Auto-imprimir al cargar
window.onload = function() {
    window.print();
}
</script>
</body>
</html>