<?php
session_start();

// Validar seguridad: Solo el Paciente (Rol 1) puede entrar aquí
if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';
$id_usuario = $_SESSION['id_usuario'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // La magia de la base de datos: Al borrar el usuario, el ON DELETE CASCADE
    // destruye automáticamente al paciente y todas sus citas en las otras tablas.
    $sql_delete = "DELETE FROM usuario WHERE id_usuario = $id_usuario";
    
    if ($conn->query($sql_delete)) {
        // Destruimos la sesión porque la cuenta ya no existe
        session_unset();
        session_destroy();
        
        // Redirigimos al login
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Cuenta - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="height: 100vh;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-danger">
                <div class="card-header bg-danger text-white text-center py-3">
                    <h4 class="mb-0">⚠️ Zona de Peligro: Eliminar Cuenta</h4>
                </div>
                <div class="card-body p-5 text-center">
                    <h5 class="text-secondary mb-3">¿Estás absolutamente seguro?</h5>
                    <p class="text-muted">
                        Al eliminar tu cuenta perderás el acceso al portal. Además, <strong>se borrará permanentemente todo tu historial clínico y se cancelarán todas tus citas agendadas</strong>. 
                        <br><br>Esta acción es irreversible.
                    </p>
                    
                    <form method="POST" action="eliminar_cuenta.php" class="mt-4">
                        <a href="index.php" class="btn btn-outline-secondary me-2">Cancelar y Volver</a>
                        <button type="submit" class="btn btn-danger">Sí, eliminar mi cuenta</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
