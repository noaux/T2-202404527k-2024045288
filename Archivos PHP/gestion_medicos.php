<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 3) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';

$mensaje = "";
$tipo_alerta = "";

// ==========================================
// PROCESAR FORMULARIOS (VINCULAR Y DESVINCULAR)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'];

    // 1. CREAR NUEVO MÉDICO
    if ($accion == 'crear_medico') {
        $rut = strtoupper($conn->real_escape_string($_POST['rut_medico']));
        $nombre = $conn->real_escape_string($_POST['nombre_medico']);
        $email = $conn->real_escape_string($_POST['email_medico']);
        $password = $conn->real_escape_string($_POST['password_medico']); 

        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO usuario (email, password, id_rol) VALUES ('$email', '$password', 2)");
            $id_usuario = $conn->insert_id;
            $conn->query("INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) 
                          VALUES ('$rut', '$nombre', '$email', $id_usuario)");
            $conn->commit();
            $mensaje = "Profesional médico registrado con éxito.";
            $tipo_alerta = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al registrar médico (Posible RUT o Email duplicado).";
            $tipo_alerta = "danger";
        }
    }

    // 2. ASIGNAR ESPECIALIDAD
    if ($accion == 'asignar_especialidad') {
        $rut_med = $conn->real_escape_string($_POST['medico_select']);
        $id_esp = (int)$_POST['especialidad_select'];
        try {
            $conn->query("INSERT INTO medico_especialidad (rut_medico, id_especialidad) VALUES ('$rut_med', $id_esp)");
            $mensaje = "Especialidad asignada correctamente.";
            $tipo_alerta = "success";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "No se pudo asignar: " . $e->getMessage(); // Aquí salta el Trigger del máximo de 3
            $tipo_alerta = "warning";
        }
    }

    // 3. ASIGNAR CENTRO
    if ($accion == 'asignar_centro') {
        $rut_med = $conn->real_escape_string($_POST['medico_select']);
        $cod_centro = $conn->real_escape_string($_POST['centro_select']);
        try {
            $conn->query("INSERT INTO medico_centro (rut_medico, codigo_interno) VALUES ('$rut_med', '$cod_centro')");
            $mensaje = "Centro médico vinculado exitosamente.";
            $tipo_alerta = "success";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "Error: El profesional ya está asignado a ese centro.";
            $tipo_alerta = "danger";
        }
    }

    // 4. DESVINCULAR ESPECIALIDAD (Con reglas de negocio)
    if ($accion == 'remover_especialidad') {
        $rut_med = $conn->real_escape_string($_POST['medico_select']);
        $id_esp = (int)$_POST['especialidad_select'];
        
        // Regla A: Mantener mínimo 1 especialidad
        $check_esp = $conn->query("SELECT COUNT(*) as total FROM medico_especialidad WHERE rut_medico = '$rut_med'");
        $total_esp = $check_esp->fetch_assoc()['total'];
        
        // Regla B: Verificar citas futuras asociadas a esta especialidad
        $check_citas = $conn->query("SELECT COUNT(*) as total FROM cita WHERE rut_medico = '$rut_med' AND id_especialidad = $id_esp AND fecha_hora > NOW() AND id_estado IN (1, 2)");
        $citas_futuras = $check_citas->fetch_assoc()['total'];
        
        if ($citas_futuras > 0) {
            $mensaje = "Bloqueo por Regla de Negocio: El médico tiene $citas_futuras cita(s) futura(s) agendada(s) para esta especialidad. Cancélelas o atiéndalas antes de removerla.";
            $tipo_alerta = "danger";
        } elseif ($total_esp <= 1) {
            $mensaje = "Restricción: El médico debe mantener al menos una (1) especialidad asignada.";
            $tipo_alerta = "warning";
        } else {
            $conn->query("DELETE FROM medico_especialidad WHERE rut_medico = '$rut_med' AND id_especialidad = $id_esp");
            if ($conn->affected_rows > 0) {
                $mensaje = "Especialidad removida del perfil del médico.";
                $tipo_alerta = "success";
            } else {
                $mensaje = "El médico seleccionado no tenía asignada esa especialidad.";
                $tipo_alerta = "info";
            }
        }
    }

    // 5. DESVINCULAR CENTRO (Con reglas de negocio)
    if ($accion == 'remover_centro') {
        $rut_med = $conn->real_escape_string($_POST['medico_select']);
        $cod_centro = $conn->real_escape_string($_POST['centro_select']);
        
        // Regla A: Mantener mínimo 1 centro
        $check_centro = $conn->query("SELECT COUNT(*) as total FROM medico_centro WHERE rut_medico = '$rut_med'");
        $total_centros = $check_centro->fetch_assoc()['total'];
        
        // Regla B: Verificar citas futuras asociadas a este centro
        $check_citas = $conn->query("SELECT COUNT(*) as total FROM cita WHERE rut_medico = '$rut_med' AND codigo_interno = '$cod_centro' AND fecha_hora > NOW() AND id_estado IN (1, 2)");
        $citas_futuras = $check_citas->fetch_assoc()['total'];
        
        if ($citas_futuras > 0) {
            $mensaje = "Bloqueo por Regla de Negocio: El médico tiene $citas_futuras cita(s) futura(s) agendada(s) en este recinto. No se puede desvincular.";
            $tipo_alerta = "danger";
        } elseif ($total_centros <= 1) {
            $mensaje = "Restricción: El médico debe estar vinculado a al menos un (1) centro médico.";
            $tipo_alerta = "warning";
        } else {
            $conn->query("DELETE FROM medico_centro WHERE rut_medico = '$rut_med' AND codigo_interno = '$cod_centro'");
            if ($conn->affected_rows > 0) {
                $mensaje = "El médico ya no atiende en el centro seleccionado.";
                $tipo_alerta = "success";
            } else {
                $mensaje = "El médico no estaba vinculado a ese centro médico.";
                $tipo_alerta = "info";
            }
        }
    }
}

// ==========================================
// CARGAR DATOS EN MEMORIA
// ==========================================
$lista_centros = $conn->query("SELECT * FROM centro_medico")->fetch_all(MYSQLI_ASSOC);
$lista_especialidades = $conn->query("SELECT * FROM especialidad")->fetch_all(MYSQLI_ASSOC);
$lista_medicos_opt = $conn->query("SELECT * FROM medico ORDER BY nom_completo ASC")->fetch_all(MYSQLI_ASSOC);

$sql_lista = "
    SELECT 
        m.rut_medico, m.nom_completo, m.email_institucional,
        IFNULL(GROUP_CONCAT(DISTINCT e.nom_especialidad SEPARATOR ', '), 'Sin especialidad') AS especialidades,
        IFNULL(GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', '), 'Sin centro asignado') AS centros
    FROM medico m
    LEFT JOIN medico_especialidad me ON m.rut_medico = me.rut_medico
    LEFT JOIN especialidad e ON me.id_especialidad = e.id_especialidad
    LEFT JOIN medico_centro mc ON m.rut_medico = mc.rut_medico
    LEFT JOIN centro_medico c ON mc.codigo_interno = c.codigo_interno
    GROUP BY m.rut_medico
    ORDER BY m.nom_completo ASC";
$directorio = $conn->query($sql_lista);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Médicos - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Panel de Gestión | Personal Médico</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Volver al Inicio</a>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($mensaje != ""): ?>
        <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- FILA 1: INGRESOS Y VINCULACIONES -->
    <div class="row mb-3">
        <!-- Crear Médico -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-secondary">
                <div class="card-header bg-secondary text-white fw-semibold">1. Nuevo Profesional</div>
                <div class="card-body">
                    <form method="POST" action="gestion_medicos.php">
                        <input type="hidden" name="accion" value="crear_medico">
                        <div class="mb-2">
                            <label class="form-label small mb-0">RUT (Ej: 12345678-9)</label>
                            <input type="text" class="form-control form-control-sm" name="rut_medico" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Nombre Completo</label>
                            <input type="text" class="form-control form-control-sm" name="nombre_medico" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Correo Institucional</label>
                            <input type="email" class="form-control form-control-sm" name="email_medico" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-0">Contraseña</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control" name="password_medico" id="pass_crear" required>
                                <button class="btn btn-outline-secondary bg-light" type="button" onclick="togglePass('pass_crear', this)">👁️</button>
                            </div>
                        </div>
                        <div class="d-grid"><button type="submit" class="btn btn-secondary btn-sm">Registrar Médico</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Asignar Especialidad -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-header bg-primary text-white fw-semibold">2. Asignar Especialidad</div>
                <div class="card-body d-flex flex-column">
                    <form method="POST" action="gestion_medicos.php">
                        <input type="hidden" name="accion" value="asignar_especialidad">
                        <div class="mb-2">
                            <label class="form-label small mb-0">Médico</label>
                            <select name="medico_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_medicos_opt as $med): ?>
                                    <option value="<?php echo $med['rut_medico']; ?>"><?php echo $med['nom_completo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-0">Especialidad a Sumar</label>
                            <select name="especialidad_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_especialidades as $esp): ?>
                                    <option value="<?php echo $esp['id_especialidad']; ?>"><?php echo $esp['nom_especialidad']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-grid mt-auto"><button type="submit" class="btn btn-primary btn-sm">Vincular Especialidad</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Asignar Centro -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-dark">
                <div class="card-header bg-dark text-white fw-semibold">3. Asignar Recinto</div>
                <div class="card-body d-flex flex-column">
                    <form method="POST" action="gestion_medicos.php">
                        <input type="hidden" name="accion" value="asignar_centro">
                        <div class="mb-2">
                            <label class="form-label small mb-0">Médico</label>
                            <select name="medico_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_medicos_opt as $med): ?>
                                    <option value="<?php echo $med['rut_medico']; ?>"><?php echo $med['nom_completo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-0">Centro a Sumar</label>
                            <select name="centro_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_centros as $cen): ?>
                                    <option value="<?php echo $cen['codigo_interno']; ?>"><?php echo $cen['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-grid mt-auto"><button type="submit" class="btn btn-dark btn-sm">Vincular Centro</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- FILA 2: RETIROS Y DESVINCULACIONES -->
    <div class="row mb-4">
        <!-- Remover Especialidad -->
        <div class="col-md-6">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white fw-semibold">Remover Especialidad</div>
                <div class="card-body">
                    <form method="POST" action="gestion_medicos.php" class="row g-2 align-items-end">
                        <input type="hidden" name="accion" value="remover_especialidad">
                        <div class="col-md-5">
                            <label class="form-label small mb-0">Médico</label>
                            <select name="medico_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_medicos_opt as $med): ?>
                                    <option value="<?php echo $med['rut_medico']; ?>"><?php echo $med['nom_completo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-0">Especialidad a Quitar</label>
                            <select name="especialidad_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_especialidades as $esp): ?>
                                    <option value="<?php echo $esp['id_especialidad']; ?>"><?php echo $esp['nom_especialidad']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Quitar esta especialidad al médico?');">Desvincular</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Remover Centro -->
        <div class="col-md-6">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white fw-semibold">Remover Centro Médico</div>
                <div class="card-body">
                    <form method="POST" action="gestion_medicos.php" class="row g-2 align-items-end">
                        <input type="hidden" name="accion" value="remover_centro">
                        <div class="col-md-5">
                            <label class="form-label small mb-0">Médico</label>
                            <select name="medico_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_medicos_opt as $med): ?>
                                    <option value="<?php echo $med['rut_medico']; ?>"><?php echo $med['nom_completo']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-0">Centro a Quitar</label>
                            <select name="centro_select" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($lista_centros as $cen): ?>
                                    <option value="<?php echo $cen['codigo_interno']; ?>"><?php echo $cen['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Quitar al médico de este centro?');">Desvincular</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE RESULTADOS -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="text-secondary mb-0">Directorio de Profesionales Activos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Profesional Médico</th>
                            <th>Contacto</th>
                            <th style="width: 25%">Especialidades Registradas</th>
                            <th style="width: 30%">Centros de Atención</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($doc = $directorio->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-3 fw-semibold">
                                    <?php echo $doc['nom_completo']; ?><br>
                                    <span class="badge bg-secondary fw-normal"><?php echo $doc['rut_medico']; ?></span>
                                </td>
                                <td class="text-muted small"><?php echo $doc['email_institucional']; ?></td>
                                <td class="small"><?php echo $doc['especialidades']; ?></td>
                                <td class="small text-primary"><?php echo $doc['centros']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
