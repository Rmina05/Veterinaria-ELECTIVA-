<?php

class Usuario {
    private $pdo;
    
    public function __construct($conexion) {
        $this->pdo = $conexion;
    }
    
    public function registrar($nombre, $email, $password, $rol = 'cliente') {
        try {
            // Verificar si el email ya existe
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $resultado = $stmt->fetch();
            
            if($resultado['count'] > 0) {
                return ['exito' => false, 'mensaje' => 'El email ya está registrado'];
            }
            
            // Encriptar contraseña
            $passwordCifrada = password_hash($password, PASSWORD_BCRYPT);
            
            // Insertar usuario
            $stmt = $this->pdo->prepare("
                INSERT INTO usuarios (nombre, email, password, rol, estado, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'activo', NOW(), NOW())
            ");
            
            $stmt->execute([$nombre, $email, $passwordCifrada, $rol]);
            
            return ['exito' => true, 'mensaje' => 'Usuario registrado correctamente', 'id' => $this->pdo->lastInsertId()];
            
        } catch(PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Error en la base de datos: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND estado = 'activo'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$usuario) {
                return ['exito' => false, 'mensaje' => 'Email o contraseña incorrectos'];
            }
            
            if(!password_verify($password, $usuario['password'])) {
                return ['exito' => false, 'mensaje' => 'Email o contraseña incorrectos'];
            }
            
            // Actualizar último acceso
            $stmt = $this->pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            
            return ['exito' => true, 'mensaje' => 'Login exitoso', 'usuario' => $usuario];
            
        } catch(PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Error en la base de datos'];
        }
    }
    
    public function obtenerPorEmail($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return null;
        }
    }
    
    public function obtenerPorId($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return null;
        }
    }
    
    public function listarTodos() {
        try {
            $stmt = $this->pdo->prepare("SELECT id, nombre, email, rol, estado, created_at FROM usuarios ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function actualizar($id, $nombre, $email, $rol) {
        try {
            $stmt = $this->pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, $id]);
            return ['exito' => true, 'mensaje' => 'Usuario actualizado correctamente'];
        } catch(PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Error al actualizar usuario'];
        }
    }
    
    public function desactivar($id) {
        try {
            $stmt = $this->pdo->prepare("UPDATE usuarios SET estado = 'inactivo', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            return ['exito' => true, 'mensaje' => 'Usuario desactivado correctamente'];
        } catch(PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Error al desactivar usuario'];
        }
    }
}

?>