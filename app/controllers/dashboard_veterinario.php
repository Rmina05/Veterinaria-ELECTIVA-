<?php
require_once __DIR__ . '/../helpers/sesion.php';
require_once __DIR__ . '/../../config/coneccion.php';
require_once __DIR__ . '/../models/usuario.php';
require_once __DIR__ . '/../helpers/rol.php';

if (!Sesion::estaLogueado()) {
    header('Location: login.php');
    exit;
}

$usuario = Sesion::obtenerUsuarioActual();
$db = DB::conn();
$usuarioObj = new Usuario($db);

/* ===== ESTADÍSTICAS ===== */
$datos = [];

/* Citas programadas */
$stmt = $db->prepare("SELECT COUNT(*) total FROM citas WHERE usuario_id = :usuario AND estado = 'programada'");
$stmt->bindValue(':usuario', $usuario['id']);
$stmt->execute();
$datos['citas_programadas'] = $stmt->fetch()['total'] ?? 0;

/* Clientes atendidos */
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT m.cliente_id) total
    FROM citas c
    INNER JOIN mascotas m ON c.mascota_id = m.id
    WHERE c.usuario_id = :usuario
");
$stmt->bindValue(':usuario', $usuario['id']);
$stmt->execute();
$datos['clientes_atendidos'] = $stmt->fetch()['total'] ?? 0;

/* Mascotas atendidas */
$stmt = $db->prepare("SELECT COUNT(DISTINCT mascota_id) total FROM citas WHERE usuario_id = :usuario");
$stmt->bindValue(':usuario', $usuario['id']);
$stmt->execute();
$datos['mascotas_atendidas'] = $stmt->fetch()['total'] ?? 0;

/* ===== HISTORIAL DE CITAS COMPLETADAS ===== */
$stmt = $db->prepare("
    SELECT c.id, c.fecha_cita, c.hora_cita, m.nombre AS mascota,
           CONCAT(cli.nombre, ' ', cli.apellido) AS cliente,
           h.diagnostico, r.id AS receta_id
    FROM citas c
    INNER JOIN mascotas m ON c.mascota_id = m.id
    INNER JOIN clientes cli ON m.cliente_id = cli.id
    LEFT JOIN historiales_medicos h ON h.cita_id = c.id
    LEFT JOIN recetas r ON r.historial_id = h.id
    WHERE c.usuario_id = :usuario AND c.estado = 'completada'
    ORDER BY c.fecha_cita DESC
");
$stmt->bindValue(':usuario', $usuario['id']);
$stmt->execute();
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Veterinario - Lugo Vet</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Montserrat',sans-serif;background:#e0f4ff}

/* NAV */
nav{
    background:linear-gradient(135deg,#739ee3,#2c5f7f);
    padding:20px 40px;
    color:white;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-radius:0 0 20px 20px;
    box-shadow:0 5px 15px rgba(0,0,0,.1)
}
nav h1{font-family:'Playfair Display',serif;font-size:2rem}
nav .user-info{display:flex;align-items:center;gap:15px}
nav a{
    color:white;
    text-decoration:none;
    padding:8px 15px;
    background:rgba(255,255,255,.2);
    border-radius:6px;
    transition:.3s
}
nav a:hover{background:rgba(255,255,255,.35)}

.container{max-width:1200px;margin:40px auto;padding:0 20px}

/* BIENVENIDA */
.welcome{
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.1);
    margin-bottom:30px
}
.welcome h2{font-family:'Playfair Display',serif;color:#2c5f7f}
.welcome p{color:#555;font-size:1.05rem}

/* ALERTA */
.alert{
    background:#ffefc4;
    border:2px solid #ffd42a;
    padding:15px;
    border-radius:10px;
    margin-bottom:25px
}

/* ESTADÍSTICAS */
.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
    margin-bottom:40px
}
.stat-card{
    background:linear-gradient(135deg,#739ee3,#2c5f7f);
    color:white;
    padding:28px;
    border-radius:18px;
    text-align:center;
    box-shadow:0 10px 25px rgba(0,0,0,.15);
    transition:.3s
}
.stat-card:hover{transform:translateY(-5px);box-shadow:0 15px 30px rgba(0,0,0,.25)}
.stat-card h3{
    font-size:2.2rem;
    font-weight:600;
    margin-bottom:6px
}
.stat-card p{
    font-size:1rem;
    font-weight:500;
    opacity:.95
}

/* MENÚ */
.menu-title h3{
    font-family:'Playfair Display',serif;
    color:#2c5f7f;
    font-size:1.5rem;
    margin-bottom:20px
}
.menu{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-bottom:50px
}
.menu-item{
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 20px rgba(0,0,0,.1);
    transition:.3s;

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:12px;
    text-align:center;
}
.menu-item:hover{transform:translateY(-5px)}
.menu-item i{
    font-size:2.5rem;
    color:#739ee3;
}
.menu-item a{
    text-decoration:none;
    color:#2c5f7f;
    font-weight:600
}

/* HISTORIAL */
.historial{
    background:white;
    padding:30px;
    border-radius:18px;
    box-shadow:0 5px 20px rgba(0,0,0,.1)
}
.historial h3{
    font-family:'Playfair Display',serif;
    color:#2c5f7f;
    margin-bottom:20px
}
table{width:100%;border-collapse:collapse}
th,td{
    padding:14px;
    border-bottom:1px solid #ddd;
    font-size:.95rem
}
th{
    background:linear-gradient(135deg,#739ee3,#2c5f7f);
    color:white
}
tr:hover{background:#f5f9ff}

.btn-ver{
    background:#2c5f7f;
    color:white;
    padding:7px 14px;
    border:none;
    border-radius:20px;
    text-decoration:none;
    cursor:pointer
}
.btn-ver:hover{background:#4a86b6}
.btn-ver:disabled{background:#ccc}
</style>
</head>

<body>

<nav>
    <h1>Lugo Vet</h1>
    <div class="user-info">
        <span><?= htmlspecialchars($usuario['nombre']) ?> (Veterinario)</span>
        <a href="/lugo-vet - copia/public/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
    </div>
</nav>

<div class="container">

<div class="welcome">
    <h2>¡Bienvenido, Dr(a). <?= htmlspecialchars($usuario['nombre']) ?>!</h2>
    <p>Rol: <strong>Veterinario</strong></p>
</div>

<?php if($datos['citas_programadas']==0): ?>
<div class="alert">¡No tienes citas programadas por el momento!</div>
<?php endif; ?>

<div class="stats">
    <div class="stat-card">
        <h3><?= $datos['citas_programadas'] ?></h3>
        <p>Citas Programadas</p>
    </div>
    <div class="stat-card">
        <h3><?= $datos['clientes_atendidos'] ?></h3>
        <p>Clientes Atendidos</p>
    </div>
    <div class="stat-card">
        <h3><?= $datos['mascotas_atendidas'] ?></h3>
        <p>Mascotas Atendidas</p>
    </div>
</div>

<div class="menu-title"><h3>Opciones Disponibles</h3></div>
<div class="menu">
    <div class="menu-item"><i class="fas fa-calendar-alt"></i><a href="/lugo-vet - copia/public/mis_citas.php">Mis Citas</a></div>
    <div class="menu-item"><i class="fas fa-paw"></i><a href="historial_mascotas.php">Historial de Mascotas</a></div>
    <div class="menu-item"><i class="fas fa-user-circle"></i><a href="mis_clientes.php">Mis Clientes</a></div>
    <div class="menu-item"><i class="fas fa-chart-bar"></i><a href="#">Estadísticas</a></div>
</div>

<div class="historial">
<h3><i class="fas fa-notes-medical"></i> Historial de Citas Completadas</h3>
<table>
<thead>
<tr>
<th>ID</th><th>Fecha</th><th>Hora</th><th>Mascota</th><th>Cliente</th><th>Diagnóstico</th><th>Receta</th>
</tr>
</thead>
<tbody>
<?php if(empty($historial)): ?>
<tr><td colspan="7" style="text-align:center">No hay citas completadas aún.</td></tr>
<?php else: foreach($historial as $h): ?>
<tr>
<td><?= $h['id'] ?></td>
<td><?= htmlspecialchars($h['fecha_cita']) ?></td>
<td><?= htmlspecialchars($h['hora_cita']) ?></td>
<td><?= htmlspecialchars($h['mascota']) ?></td>
<td><?= htmlspecialchars($h['cliente']) ?></td>
<td><?= htmlspecialchars($h['diagnostico'] ?? '—') ?></td>
<td>
<?php if($h['receta_id']): ?>
<a class="btn-ver" target="_blank" href="ver_receta.php?id=<?= $h['receta_id'] ?>">📄 Ver Receta</a>
<?php else: ?>
<button class="btn-ver" disabled>Sin receta</button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

</div>
</body>
</html>
