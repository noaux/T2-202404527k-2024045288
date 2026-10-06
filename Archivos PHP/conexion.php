<?php
// Parámetros de conexión a la base de datos
$servidor = "localhost";
$usuario = "root";       // Usuario por defecto en XAMPP
$password = "";          // Contraseña por defecto en XAMPP (vacía)
$base_datos = "salud_usm";

// Habilitar el reporte de errores estricto para facilitar el debug
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Intentar establecer la conexión
    $conn = new mysqli($servidor, $usuario, $password, $base_datos);
    
    // Forzar el uso de UTF-8 para que las tildes y las 'ñ' (como en Ñuñoa o Médico) se lean bien
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Si la conexión falla, detenemos todo y mostramos un mensaje limpio
    die("Error crítico: No se pudo conectar a la base de datos. Detalle: " . $e->getMessage());
}
?>
