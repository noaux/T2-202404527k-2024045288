<?php
session_start();
date_default_timezone_set('America/Santiago'); 

// Validar seguridad: Solo el Médico (Rol 2) puede entrar aquí
if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 2) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';

$id_usuario = $_SESSION['id_usuario'];
$mensaje = "";
$tipo_alerta = "";

// 1. Obtener el RUT y Nombre del médico logueado
$sql_medico = "SELECT rut_medico, nom_completo FROM medico WHERE id_usuario = $id_usuario";
$resultado_medico = $conn->query($sql_medico);
$datos_medico = $resultado_medico->fetch_assoc();
$rut_medico = $datos_medico['rut_medico'];
$nombre_medico = $datos_medico['nom_completo'];

// Capturar la fecha seleccionada o usar la fecha actual por defecto
$fecha_filtro = isset($_GET['fecha']) ? $conn->real_escape_string($_GET['fecha']) : date('Y-m-d');

// ==========================================
// PROCESAR CAMBIO DE ESTADO RÁPIDO (Confirmar / No Asistió)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cambiar_estado'])) {
    $id_cita_estado = (int)$_POST['id_cita'];
    $nuevo_estado = (int)$_POST['nuevo_estado']; // 2 = Confirmada, 4 = No Asistió
    
    // Verificar por seguridad que la cita pertenece a este médico
    $check = $conn->query("SELECT id_estado FROM cita WHERE id_cita = $id_cita_estado AND rut_medico = '$rut_medico'");
    
    if ($check->num_rows > 0) {
        $estado_actual = $check->fetch_assoc()['id_estado'];
        
        // Solo podemos alterar citas que no hayan finalizado (Reservadas o Confirmadas)
        if ($estado_actual == 1 || $estado_actual == 2) {
            $conn->query("UPDATE cita SET id_estado = $nuevo_estado WHERE id_cita = $id_cita_estado");
            $mensaje = "El estado del paciente ha sido actualizado correctamente.";
            $tipo_alerta = "success";
        } else {
            $mensaje = "No se puede cambiar el estado de una cita ya finalizada o cancelada.";
            $tipo_alerta = "danger";
        }
    }
}

// 2. Obtener la agenda usando la Vista SQL, filtrando por la fecha seleccionada
$sql_agenda = "SELECT * FROM vista_resumen_citas 
               WHERE rut_medico = '$rut_medico' 
               AND DATE(fecha_hora) = '$fecha_filtro'
               ORDER BY fecha_hora ASC";
$agenda = $conn->query($sql_agenda);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Médica - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Portal Clínico | <?php echo $nombre_medico; ?></span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Volver al Inicio</a>
    </div>
</nav>

<div class="container mt-5">
    
    <?php if ($mensaje != ""): ?>
        <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filtro por Fecha -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-body">
            <form method="GET" action="agenda_medico.php" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="fecha" class="col-form-label fw-semibold text-muted">Seleccionar Fecha:</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="<?php echo htmlspecialchars($fecha_filtro); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Ver Agenda</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-primary">
        <div class="card-header bg-white border-bottom border-primary py-3 d-flex justify-content-between align-items-center">
            <h4 class="text-primary mb-0">Mi Agenda de Pacientes</h4>
            <span class="badge bg-primary fs-6">Día: <?php echo date('d-m-Y', strtotime($fecha_filtro)); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Hora</th>
                            <th>Paciente</th>
                            <th>Previsión</th>
                            <th>Centro Médico</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center pe-4" style="width: 25%;">Acción Clínica</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($agenda->num_rows > 0): ?>
                            <?php while($cita = $agenda->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold"><?php echo date('H:i', strtotime($cita['fecha_hora'])); ?> hrs</td>
                                    <td>
                                        <?php echo $cita['paciente']; ?> <br>
                                        <small class="text-muted">RUT: <?php echo $cita['rut_paciente']; ?></small>
                                    </td>
                                    <td><?php echo $cita['prevision']; ?></td>
                                    <td><?php echo $cita['centro_medico']; ?></td>
                                    <td class="text-center">
                                        <?php 
                                            $color = 'bg-secondary';
                                            if ($cita['estado'] == 'Confirmada') $color = 'bg-primary';
                                            if ($cita['estado'] == 'Atendida') $color = 'bg-success';
                                            if ($cita['estado'] == 'No Asistió') $color = 'bg-danger';
                                            if ($cita['estado'] == 'Cancelada') $color = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $color; ?>"><?php echo $cita['estado']; ?></span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <?php if ($cita['estado'] == 'Reservada' || $cita['estado'] == 'Confirmada'): ?>
                                            
                                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                                <!-- Formulario para Confirmar (Solo si está Reservada) -->
                                                <?php if ($cita['estado'] == 'Reservada'): ?>
                                                    <form method="POST" action="agenda_medico.php?fecha=<?php echo $fecha_filtro; ?>" class="m-0">
                                                        <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                                        <input type="hidden" name="nuevo_estado" value="2"> <!-- 2 = Confirmada -->
                                                        <button type="submit" name="cambiar_estado" class="btn btn-outline-primary btn-sm" title="Confirmar asistencia">Confirmar</button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Botón para Atender (Abre registro de ficha) -->
                                                <a href="registrar_atencion.php?id_cita=<?php echo $cita['id_cita']; ?>" class="btn btn-success btn-sm">
                                                   Atender
                                                </a>

                                                <!-- Formulario para Ausente -->
                                                <form method="POST" action="agenda_medico.php?fecha=<?php echo $fecha_filtro; ?>" class="m-0">
                                                    <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                                    <input type="hidden" name="nuevo_estado" value="4"> <!-- 4 = No Asistió -->
                                                    <button type="submit" name="cambiar_estado" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Marcar que el paciente no asistió a su hora?');">Ausente</button>
                                                </form>
                                            </div>

                                        <?php elseif ($cita['estado'] == 'Atendida'): ?>
                                            <button class="btn btn-outline-success btn-sm w-100" disabled>Ficha Completada</button>
                                        <?php else: ?>
                                            <button class="btn btn-light btn-sm text-muted w-100" disabled>Finalizada</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    No tienes pacientes agendados para este día.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
