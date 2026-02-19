<?php
// config/db.php
declare(strict_types=1);

class DB {
    private static ?PDO $pdo = null;

    public static function conn(): PDO {
        if (self::$pdo === null) {
            // 🔧 Configuración de la base de datos LugoVet
            $host = '127.0.0.1';       // Servidor local
            $port = '3306';            // Puerto MySQL por defecto
            $db   = 'estetica_veterinaria1';      // Nombre de tu base de datos
            $user = 'root';            // Usuario (por defecto en XAMPP)
            $pass = '';                // Contraseña (deja vacío si no tienes una)

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Muestra errores claros
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Resultados como arrays asociativos
                PDO::ATTR_EMULATE_PREPARES => false, // Mejor seguridad en consultas preparadas
            ];

            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }

        return self::$pdo;
    }
    
}
