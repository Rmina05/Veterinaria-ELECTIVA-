<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Veterinaria Lugo Vet</title>

    <!-- Fuentes elegantes -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <!-- Hero / Imagen principal -->
    <header class="hero">
        <div class="overlay">
            <nav>
                <h1>Lugo Vet</h1>
                <ul class="nav-links">
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                </ul>
            </nav>

            <div class="hero-content">
                <h2>Cuidamos a tu mascota con amor y profesionalismo</h2>
                <p>Brindamos servicios veterinarios y estéticos de alta calidad.</p>
                <a href="login.php" class="btn">Iniciar Sesión</a>
            </div>
        </div>
    </header>

    <!-- Sección de Servicios -->
    <section id="servicios" class="servicios">
        <h2>Nuestros Servicios</h2>
        <div class="cards">
            <div class="card animate">
                <h3>Consultas Médicas</h3>
                <p>Atención veterinaria completa para el bienestar de tu mascota.</p>
            </div>
            <div class="card animate">
                <h3>Estética y Baños</h3>
                <p>Cortes, baños y cuidado de pelaje con productos de calidad.</p>
            </div>
            <div class="card animate">
                <h3>Vacunación y Control</h3>
                <p>Mantén la salud de tu mascota al día con nuestras vacunas y controles.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contacto">
        <p>&copy; <?php echo date('Y'); ?> Lugo Vet. Todos los derechos reservados.</p>
        <p>Tel: 123-456-789 | Email: contacto@lugovet.com</p>
    </footer>

    <!-- Animaciones scroll -->
    <script>
        const animItems = document.querySelectorAll('.animate');
        const animOnScroll = () => {
            animItems.forEach(item => {
                const rect = item.getBoundingClientRect();
                if(rect.top < window.innerHeight - 100) {
                    item.classList.add('show');
                }
            });
        };
        window.addEventListener('scroll', animOnScroll);
        window.addEventListener('load', animOnScroll);
    </script>
</body>
</html>
