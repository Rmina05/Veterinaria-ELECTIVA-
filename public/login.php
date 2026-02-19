<?php
session_start();
require_once __DIR__ . '/../config/coneccion.php';
require_once __DIR__ . '/../app/helpers/validacion.php';
require_once __DIR__ . '/../app/models/usuario.php';
require_once __DIR__ . '/../app/helpers/sesion.php';

$db = DB::conn();
$usuarioObj = new Usuario($db);
$errores = [];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $valEmail = Validacion::validarEmail($email);
    if (!$valEmail['valido']) {
        $errores[] = $valEmail['mensaje'];
    }

    if (empty($password)) {
        $errores[] = 'La contraseña es requerida';
    }

    if (empty($errores)) {
        $resultado = $usuarioObj->login($email, $password);

        if ($resultado && isset($resultado['exito']) && $resultado['exito'] === true) {
            Sesion::iniciarSesion($resultado['usuario']);
            $rol = strtolower($resultado['usuario']['rol'] ?? '');

            // Redirigir según el rol (eliminado dashboard_cliente)
            switch ($rol) {
                case 'administrador':
    header('Location: /lugo-vet - copia/app/controllers/dashboard.php');
    exit;
case 'veterinario':
    header('Location: /lugo-vet - copia/app/controllers/dashboard_veterinario.php');
    exit;
case 'recepcionista':
    header('Location: /lugo-vet - copia/app/controllers/dashboard_recepcionista.php');
    exit;
                default:
                    $errores[] = 'Rol de usuario no reconocido o sin acceso.';
                    break;
            }
        } else {
            $errores[] = 'Correo o contraseña incorrectos.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar Sesión - Lugo Vet</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
    * {margin:0; padding:0; box-sizing:border-box;}
    body {
        font-family: 'Montserrat', sans-serif;
        background: linear-gradient(135deg, #a8d8ff 0%, #e0f4ff 100%);
        min-height:100vh; display:flex; justify-content:center; align-items:center;
    }
    .login-container {
        background:white; padding:40px; border-radius:15px;
        box-shadow:0 10px 30px rgba(0,0,0,0.15);
        width:100%; max-width:400px;
        position: relative;
    }
    h2 {
        font-family:'Playfair Display', serif;
        color:#2c5f7f; margin-bottom:30px;
        text-align:center; font-size:2rem;
    }
    .form-group {margin-bottom:20px; position: relative;}
    label {display:block; margin-bottom:8px; color:#2c5f7f; font-weight:600; font-size:0.95rem;}
    input[type="email"], input[type="password"] {
        width:100%; padding:12px;
        border:2px solid #a8d8ff; border-radius:8px;
        font-family:'Montserrat', sans-serif; font-size:1rem;
        transition:all 0.3s; color:#333;
    }
    input::placeholder {
        color:#999;
        font-style: italic;
        transition: color 0.3s ease;
    }
    input:focus::placeholder { color: transparent; }
    input[type="email"]:focus, input[type="password"]:focus {
        outline:none; border-color:#2c5f7f;
        box-shadow:0 0 8px rgba(44,95,127,0.2);
        background-color:#f9fbfc;
    }
    button {
        width:100%; padding:12px;
        background:linear-gradient(135deg,#739ee3 0%,#2c5f7f 100%);
        color:white; border:none; border-radius:8px;
        font-weight:600; font-size:1rem; cursor:pointer;
        transition:all 0.3s; font-family:'Montserrat',sans-serif;
    }
    button:hover {transform:translateY(-2px); box-shadow:0 8px 15px rgba(44,95,127,0.3);}
    .links {text-align:center; margin-top:25px;}
    .links p {margin-bottom:10px; color:#555; font-size:0.95rem;}
    .links a {color:#2c5f7f; text-decoration:none; font-weight:600; transition:color 0.3s;}
    .links a:hover {color:#739ee3; text-decoration:underline;}

    /* Alerta */
    .alerta {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #fff0f0;
        border-left: 6px solid #ff4d4d;
        color: #a00;
        padding: 20px 25px;
        border-radius: 10px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        z-index: 9999;
        font-size: 0.95rem;
        animation: aparecer 0.5s ease;
    }
    .alerta ul {list-style:none; margin:0; padding:0;}
    .alerta li {margin-bottom:5px;}
    @keyframes aparecer {
        from {opacity:0; transform:translateY(-20px);}
        to {opacity:1; transform:translateY(0);}
    }
</style>
</head>
<body>
<div class="login-container">
    <h2>Iniciar Sesión</h2>

    <form method="POST">
        <div class="form-group">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" placeholder="Escribe tu correo" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="Escribe tu contraseña" required>
        </div>

        <button type="submit">Iniciar Sesión</button>
    </form>

    <div class="links">
        <p><a href="index.php">Volver al inicio</a></p>
    </div>
</div>

<?php if (!empty($errores)): ?>
    <div class="alerta" id="alertaErrores">
        <ul>
            <?php foreach ($errores as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <script>
        setTimeout(() => {
            const alerta = document.getElementById('alertaErrores');
            if(alerta){
                alerta.style.opacity = '0';
                alerta.style.transform = 'translateY(-20px)';
                setTimeout(() => alerta.remove(), 500);
            }
        }, 4000);
    </script>
<?php endif; ?>

</body>
</html>
