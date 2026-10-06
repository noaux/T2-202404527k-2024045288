<?php
session_start();
require 'conexion.php';

$mensaje = "";
$tipo_alerta = "danger";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Limpiar y capturar datos del formulario
    $rut = strtoupper($conn->real_escape_string($_POST['rut']));
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $fecha_nac = $conn->real_escape_string($_POST['fecha_nacimiento']);
    $sexo = $conn->real_escape_string($_POST['sexo']);
    $telefono = $conn->real_escape_string($_POST['telefono']);
    $comuna = $conn->real_escape_string($_POST['comuna']);
    $id_prevision = (int)$_POST['prevision'];
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // 2. Validar que el RUT o el Email no existan ya en el sistema
    $check_rut = $conn->query("SELECT rut_paciente FROM paciente WHERE rut_paciente = '$rut'");
    $check_email = $conn->query("SELECT email FROM usuario WHERE email = '$email'");

    if ($check_rut->num_rows > 0) {
        $mensaje = "El RUT ingresado ya está registrado en el sistema.";
    } elseif ($check_email->num_rows > 0) {
        $mensaje = "El correo electrónico ya está en uso.";
    } else {
        // 3. Validar seguridad de la contraseña en el backend (PHP)
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
            $mensaje = "La contraseña no cumple con los requisitos de seguridad.";
        } else {
            // 4. Iniciar Transacción Segura
            $conn->begin_transaction();
            try {
                // A. Insertar primero en la tabla USUARIO (Rol 1 = Paciente)
                $sql_usuario = "INSERT INTO usuario (email, password, id_rol) VALUES ('$email', '$password', 1)";
                $conn->query($sql_usuario);
                $id_usuario = $conn->insert_id; // Capturar el ID generado automáticamente

                // B. Insertar luego en la tabla PACIENTE usando el ID del usuario
                $sql_paciente = "INSERT INTO paciente (rut_paciente, nom_completo, fecha_nacimiento, sexo, telefono, comuna, id_prevision, id_usuario) 
                                 VALUES ('$rut', '$nombre', '$fecha_nac', '$sexo', '$telefono', '$comuna', $id_prevision, $id_usuario)";
                $conn->query($sql_paciente);

                // Si ambos queries funcionaron, confirmamos los cambios
                $conn->commit();
                $mensaje = "¡Registro exitoso! Ya puedes iniciar sesión.";
                $tipo_alerta = "success";
            } catch (Exception $e) {
                // Si algo falla, deshacemos todo
                $conn->rollback();
                $mensaje = "Error crítico al registrar: " . $e->getMessage();
            }
        }
    }
}

// Obtener las previsiones para llenar el select del formulario dinámicamente
$previsiones = $conn->query("SELECT * FROM prevision");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Paciente - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center py-5" style="min-height: 100vh; background-image: url('fondo.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-success text-white text-center py-3">
                    <h4 class="mb-0">Registro de Nuevo Paciente</h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($mensaje != ""): ?>
                        <div class="alert alert-<?php echo $tipo_alerta; ?>" role="alert">
                            <?php echo $mensaje; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="registro.php">
                        <h5 class="text-primary mb-3">Datos de la Cuenta</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Correo Electrónico *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña *</label>
                                <!-- pattern HTML5 para validación inmediata en el navegador -->
                                <input type="password" class="form-control" name="password" 
                                       pattern="(?=.*[A-Z])(?=.*[^a-zA-Z\d]).{8,}" 
                                       title="Mínimo 8 caracteres, 1 mayúscula y 1 símbolo" required>
                                <div class="form-text">Mínimo 8 caracteres, 1 mayúscula y 1 símbolo.</div>
                            </div>
                        </div>

                        <hr>
                        <h5 class="text-primary mb-3">Datos Personales</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">RUT * (Ej: 12345678-9)</label>
                                <input type="text" class="form-control" name="rut" 
                                       pattern="^[0-9]{7,8}-[0-9Kk]{1}$" 
                                       title="Formato válido: XXXXXXXX-X" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Fecha de Nacimiento *</label>
                                <input type="date" class="form-control" name="fecha_nacimiento" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sexo *</label>
                                <select class="form-select" name="sexo" required>
                                    <option value="">Seleccione...</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Previsión *</label>
                                <select class="form-select" name="prevision" required>
                                    <option value="">Seleccione...</option>
                                    <?php while($row = $previsiones->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id_prevision']; ?>">
                                            <?php echo $row['nom_prevision']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Comuna *</label>
                                <input type="text" class="form-control" name="comuna" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">Completar Registro</button>
                            <a href="login.php" class="btn btn-outline-secondary">Volver al Login</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
