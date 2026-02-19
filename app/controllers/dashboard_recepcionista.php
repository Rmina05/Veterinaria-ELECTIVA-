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

// Obtener estadísticas para recepcionista
$datos = [];

// Total de clientes activos
$stmt = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE estado = 'activo'");
$stmt->execute();
$datos['total_clientes'] = $stmt->fetch()['total'];

// Citas programadas para hoy
$stmt = $db->prepare("SELECT COUNT(*) as total FROM citas WHERE DATE(fecha_cita) = CURDATE() AND estado = 'programada'");
$stmt->execute();
$datos['citas_hoy'] = $stmt->fetch()['total'];

// Total de mascotas registradas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM mascotas WHERE estado = 'activo'");
$stmt->execute();
$datos['total_mascotas'] = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Recepcionista - Lugo Vet</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    * {margin:0; padding:0; box-sizing:border-box;}
    body {font-family:'Montserrat', sans-serif; background: #e0f4ff;}
    nav {
        background: linear-gradient(135deg, #739ee3 0%, #2c5f7f 100%);
        padding: 20px 40px; color: white;
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;
    }
    nav h1 {font-family:'Playfair Display', serif; font-size:2rem;}
    nav .user-info {display:flex; align-items:center; gap:15px;}
    nav a {color:white; text-decoration:none; padding:8px 15px; border-radius:5px; background: rgba(255,255,255,0.2); transition:0.3s;}
    nav a:hover {background: rgba(255,255,255,0.35);}
    .container {max-width:1200px; margin:40px auto; padding:0 20px;}
    .welcome {background:white; padding:30px; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.1); margin-bottom:30px;}
    .welcome h2 {font-family:'Playfair Display', serif; color:#2c5f7f; margin-bottom:10px;}
    .welcome p {color:#555; font-size:1.1rem;}
    .stats {display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-bottom:30px;}
    .stat-card {
        background:linear-gradient(135deg, #739ee3 0%, #2c5f7f 100%);
        color:white; padding:30px; border-radius:15px;
        text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.15);
        transition:transform 0.3s, box-shadow 0.3s;
    }
    .stat-card:hover {transform: translateY(-5px); box-shadow:0 15px 30px rgba(0,0,0,0.25);}
    .stat-card h3 {font-size:3rem; margin-bottom:10px; font-weight:700;}
    .stat-card p {font-size:1rem; font-weight:500;}
    .menu-title {margin-bottom:20px;}
    .menu-title h3 {font-family:'Playfair Display', serif; color:#2c5f7f; font-size:1.5rem;}
    .menu {display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px;}
    .menu-item {
        background:white; padding:25px; border-radius:15px;
        text-align:center; box-shadow:0 5px 20px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .menu-item:hover {transform: translateY(-5px); box-shadow:0 10px 25px rgba(0,0,0,0.15);}
    .menu-item i {font-size:2.5rem; color:#739ee3; margin-bottom:15px; display:block;}
    .menu-item a {display:block; color:#2c5f7f; text-decoration:none; font-weight:600; transition:0.3s;}
    .menu-item a:hover {color:#739ee3;}
    @media (max-width:768px){.menu{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));}}
</style>
</head>
<body>
<nav>
    <h1>Lugo Vet</h1>
    <div class="user-info">
        <span><?= htmlspecialchars($usuario['nombre']) ?> (Recepcionista)</span>
        <a href="/lugo-vet - copia/public/logout.php">Cerrar Sesión</a>
    </div>
</nav>

<div class="container">
    <div class="welcome">
        <h2>Bienvenido, <?= htmlspecialchars($usuario['nombre']) ?>!</h2>
        <p>Rol: <strong>Recepcionista</strong></p>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3><?= $datos['total_clientes'] ?></h3>
            <p>Clientes Activos</p>
        </div>
        <div class="stat-card">
            <h3><?= $datos['citas_hoy'] ?></h3>
            <p>Citas para Hoy</p>
        </div>
        <div class="stat-card">
            <h3><?= $datos['total_mascotas'] ?></h3>
            <p>Mascotas Registradas</p>
        </div>
    </div>

    <div class="menu-title"><h3>Opciones Disponibles</h3></div>
    <div class="menu">
        <?php if(Rol::tienePermiso($usuario['rol'], 'ver_clientes')): ?>
        <div class="menu-item">
            <i class="fas fa-user-circle"></i>
            <a href="gestionar_clientes.php">Gestionar Clientes</a>
        </div>
        <?php endif; ?>

        <?php if(Rol::tienePermiso($usuario['rol'], 'ver_mascotas')): ?>
        <div class="menu-item">
            <i class="fas fa-paw"></i>
            <a href="gestionar_mascotas.php">Gestionar Mascotas</a>
        </div>
        <?php endif; ?>

        <?php if(Rol::tienePermiso($usuario['rol'], 'ver_citas')): ?>
        <div class="menu-item">
            <i class="fas fa-calendar-alt"></i>
            <a href="gestionar_citas.php">Gestionar Citas</a>
        </div>
        <?php endif; ?>

        <?php if(Rol::tienePermiso($usuario['rol'], 'ver_veterinarios')): ?>
        <div class="menu-item">
            <i class="fas fa-stethoscope"></i>
            <a href="gestionar_productos.php">Productos</a>
        </div>
        <?php endif; ?>

        <?php if(Rol::tienePermiso($usuario['rol'], 'ver_servicios')): ?>
        <div class="menu-item">
            <i class="fas fa-briefcase"></i>
            <a href="gestionar_servicios.php">Ver Servicios</a>
        </div>
        <?php endif; ?>

        <?php if(Rol::tienePermiso($usuario['rol'], 'ver_facturas')): ?>
        <div class="menu-item">
            <i class="fas fa-file-invoice-dollar"></i>
            <a href="gestionar_facturas.php">Facturas</a>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>