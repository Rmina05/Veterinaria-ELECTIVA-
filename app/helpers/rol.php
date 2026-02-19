<?php

class Rol {
    
    public static $ROLES = [
        'administrador' => 'Administrador',
        'veterinario' => 'Veterinario',
        'recepcionista' => 'Recepcionista'
    ];
    
    public static $PERMISOS = [
        'administrador' => [
            'ver_usuarios', 'crear_usuarios', 'editar_usuarios', 'desactivar_usuarios',
            'ver_clientes', 'crear_cliente', 'editar_cliente',
            'ver_mascotas', 'crear_mascota', 'editar_mascota',
            'ver_veterinarios', 'crear_veterinario', 'editar_veterinario',
            'ver_servicios', 'crear_servicio', 'editar_servicio',
            'ver_citas', 'crear_cita', 'editar_cita', 'cancelar_cita',
            'ver_facturas', 'crear_factura', 'editar_factura',
            'ver_reportes'
        ],
        'veterinario' => [
            'ver_mascotas',
            'ver_citas', 'editar_cita', 'crear_diagnostico',
            'ver_historiales', 'crear_historial', 'editar_historial',
            'crear_receta', 'editar_receta',
            'ver_facturas'
        ],
        'recepcionista' => [
            'ver_clientes', 'crear_cliente', 'editar_cliente',
            'ver_mascotas', 'crear_mascota', 'editar_mascota',
            'ver_citas', 'crear_cita', 'editar_cita', 'cancelar_cita',
            'ver_veterinarios',
            'ver_servicios',
            'ver_facturas', 'crear_factura'
        ]
    ];
    
    public static function validarRol($rol) {
        return isset(self::$ROLES[$rol]);
    }
    
    public static function tienePermiso($rolUsuario, $permiso) {
        if(!isset(self::$PERMISOS[$rolUsuario])) {
            return false;
        }
        return in_array($permiso, self::$PERMISOS[$rolUsuario]);
    }
    
    public static function esAdmin($rol) {
        return $rol === 'administrador';
    }
    
    public static function esVeterinario($rol) {
        return $rol === 'veterinario';
    }
    
    public static function esRecepcionista($rol) {
        return $rol === 'recepcionista';
    }
    
    public static function obtenerRolesDisponibles() {
        return self::$ROLES;
    }
}

?>