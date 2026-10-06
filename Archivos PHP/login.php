<?php
session_start();
date_default_timezone_set('America/Santiago');

// Si el usuario ya está logueado, lo redirigimos al inicio
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';

// ===================================================================================
// RUTINA DE LIMPIEZA AUTOMÁTICA (Regla de Negocio)
// Pasa a estado "Cancelada" (5) las citas Reservadas (1) o Confirmadas (2) vencidas
// ===================================================================================
$conn->query("UPDATE cita SET id_estado = 5 WHERE id_estado IN (1, 2) AND fecha_hora < NOW()");

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);

    // Buscamos al usuario en la base de datos
    $sql = "SELECT id_usuario, id_rol, password FROM usuario WHERE email = '$email'";
    $resultado = $conn->query($sql);

    if ($resultado->num_rows == 1) {
        $usuario = $resultado->fetch_assoc();
        
        // Verificamos si la contraseña coincide
        if ($password === $usuario['password']) {
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['id_rol'] = $usuario['id_rol'];
            
            header("Location: index.php");
            exit();
        } else {
            $mensaje = "Contraseña incorrecta. Por favor, inténtelo de nuevo.";
        }
    } else {
        $mensaje = "No existe una cuenta registrada con ese correo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Cargando la imagen de fondo personalizada */
        body { 
            background: url('fondo_principal.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
        }
        .login-container { 
            max-width: 420px; 
            width: 100%; 
            margin-top: 8vh; 
        }
    </style>
</head>
<body class="d-flex justify-content-center align-items-start">

<div class="container login-container">
    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-primary text-white text-center py-4 border-0">
            <h3 class="mb-0 fw-bold">SaludUSM</h3>
            <p class="mb-0 text-white-50 small">Sistema de Gestión Clínica</p>
        </div>
        <div class="card-body p-4 p-md-5 bg-white">
            <h5 class="text-center mb-4 text-secondary">Iniciar Sesión</h5>
            
            <?php if ($mensaje != ""): ?>
                <div class="alert alert-danger text-center small py-2" role="alert">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label text-muted fw-semibold small">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" placeholder="usuario@correo.cl" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-muted fw-semibold small">Contraseña</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pass_login" class="form-control" placeholder="Ingrese su contraseña" required>
                        <button class="btn btn-outline-secondary bg-light" type="button" onclick="togglePass('pass_login', this)">👁️</button>
                    </div>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">Ingresar al Portal</button>
                </div>
                
                <!-- SECCIÓN DE REGISTRO CON LA RUTA CORREGIDA -->
                <div class="text-center mt-3 pt-3 border-top">
                    <p class="text-muted small mb-1">¿Eres paciente nuevo?</p>
                    <a href="registro.php" class="btn btn-outline-success btn-sm px-4">Regístrate Aquí</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="text-center mt-4 small fw-semibold">
        <span class="bg-white bg-opacity-75 px-2 py-1 rounded text-dark">
            &copy; <?php echo date('Y'); ?> SaludUSM. Todos los derechos reservados.
        </span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Función para alternar la visibilidad de la contraseña
function togglePass(inputId, btn) {
    var input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        btn.textContent = "🙈"; 
    } else {
        input.type = "password";
        btn.textContent = "👁️"; 
    }
}
</script>
</body>
</html>
