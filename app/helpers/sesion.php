<?php

class Sesion {
    
    private static function iniciar() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function iniciarSesion($usuario) {
        self::iniciar();
        $_SESSION['id_usuario'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['rol'] = $usuario['rol'];
        $_SESSION['inicio_sesion'] = time();
    }
    
    public static function cerrarSesion() {
        self::iniciar();
        session_destroy();
        header('Location: index.php');
        exit;
    }
    
    public static function estaLogueado() {
        self::iniciar();
        return isset($_SESSION['id_usuario']) && !empty($_SESSION['id_usuario']);
    }
    
    public static function verificarRol($rol_requerido) {
        self::iniciar();
        
        if(!self::estaLogueado()) {
            header('Location: login.php');
            exit;
        }
        
    }
    
    public static function verificarPermiso($permiso) {
        self::iniciar();
        
        if(!self::estaLogueado()) {
            header('Location: login.php');
            exit;
        }
        
    }
    
    public static function obtenerUsuarioActual() {
        self::iniciar();
        if(self::estaLogueado()) {
            return [
                'id' => $_SESSION['id_usuario'],
                'nombre' => $_SESSION['nombre'],
                'email' => $_SESSION['email'],
                'rol' => $_SESSION['rol']
            ];
        }
        return null;
    }
    
    public static function obtenerRolActual() {
        self::iniciar();
        return isset($_SESSION['rol']) ? $_SESSION['rol'] : null;
    }
}

?>