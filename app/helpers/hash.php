<?php
$password_plain = 'admin123'; // la contraseña que quieras usar
$hash = password_hash($password_plain, PASSWORD_DEFAULT);
echo "Hash generado: " . $hash;
?>
