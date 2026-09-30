<?php
$servidor = "localhost";
$usuario = "root";
$password = ""; // En XAMPP la contraseña por defecto viene vacía
$base_datos = "salud_usm";

// Crear la conexión
$conexion = new mysqli($servidor, $usuario, $password, $base_datos);

// Verificar si hubo un error
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
} 

echo "¡Conexión exitosa a la base de datos salud_usm!";
?>