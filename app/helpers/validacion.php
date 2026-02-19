<?php
class Validacion {
    
    public static function validarEmail($email) {
        if (empty($email)) {
            return ['valido' => false, 'mensaje' => 'El correo es requerido'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valido' => false, 'mensaje' => 'Formato de correo inválido'];
        }
        return ['valido' => true, 'mensaje' => ''];
    }

    public static function validarContrasena($password) {
        if (empty($password)) {
            return ['valido' => false, 'mensaje' => 'La contraseña es requerida'];
        }
        return ['valido' => true, 'mensaje' => ''];
    }
}
?>
