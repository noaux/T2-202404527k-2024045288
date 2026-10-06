<?php
session_start();
date_default_timezone_set('America/Santiago'); // Forzar zona horaria correcta

if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';

$id_usuario = $_SESSION['id_usuario'];
$mensaje = "";
$tipo_alerta = "";

// Obtener RUT del paciente logueado
$sql_paciente = "SELECT rut_paciente FROM paciente WHERE id_usuario = $id_usuario";
$resultado_paciente = $conn->query($sql_paciente);
$rut_paciente = $resultado_paciente->fetch_assoc()['rut_paciente'];

// ==========================================
// 1. PROCESAR CANCELACIÓN DE CITA
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancelar_cita'])) {
    $id_cita_cancelar = (int)$_POST['id_cita'];
    
    // Verificar que la cita pertenezca al paciente, esté Reservada (1) o Confirmada (2) y sea futura
    $check = $conn->query("SELECT id_estado, fecha_hora FROM cita WHERE id_cita = $id_cita_cancelar AND rut_paciente = '$rut_paciente'");
    if ($check->num_rows > 0) {
        $cita_data = $check->fetch_assoc();
        if (($cita_data['id_estado'] == 1 || $cita_data['id_estado'] == 2) && strtotime($cita_data['fecha_hora']) > time()) {
            $conn->query("UPDATE cita SET id_estado = 5 WHERE id_cita = $id_cita_cancelar");
            $mensaje = "La cita ha sido cancelada exitosamente.";
            $tipo_alerta = "success";
        } else {
            $mensaje = "No se puede cancelar una cita pasada o previamente anulada.";
            $tipo_alerta = "danger";
        }
    }
}

// ==========================================
// 2. PROCESAR REPROGRAMACIÓN DE CITA
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reprogramar_cita'])) {
    $id_cita_repro = (int)$_POST['id_cita'];
    $nueva_fecha = $conn->real_escape_string($_POST['nueva_fecha']);
    $nueva_hora = $conn->real_escape_string($_POST['nueva_hora']);
    
    // Unir para formato DATETIME
    $nueva_fecha_hora = $nueva_fecha . ' ' . $nueva_hora . ':00';

    // Validar por seguridad que la nueva fecha/hora sea en el futuro
    if (strtotime($nueva_fecha_hora) <= time()) {
        $mensaje = "Error: La nueva fecha y hora debe ser posterior al momento actual.";
        $tipo_alerta = "warning";
    } else {
        try {
            // El UPDATE dispara automáticamente el Trigger trg_evitar_choque_citas_update en la BD
            $sql_update = "UPDATE cita SET fecha_hora = '$nueva_fecha_hora' 
                           WHERE id_cita = $id_cita_repro AND rut_paciente = '$rut_paciente' AND id_estado IN (1, 2)";
            $conn->query($sql_update);
            
            if ($conn->affected_rows > 0) {
                $mensaje = "¡Cita reprogramada exitosamente para el " . date('d-m-Y \a \l\a\s H:i', strtotime($nueva_fecha_hora)) . " hrs!";
                $tipo_alerta = "success";
            } else {
                $mensaje = "No se pudo reprogramar. Verifique que la cita siga vigente.";
                $tipo_alerta = "warning";
            }
        } catch (mysqli_sql_exception $e) {
            // Atrapamos el error lanzado por el Trigger si hay choque de horario
            $mensaje = "No se pudo reprogramar: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// ==========================================
// CONSULTA 1: CITAS VIGENTES (Futuras, para cancelar o reprogramar)
// ==========================================
$sql_vigentes = "
    SELECT c.id_cita, c.fecha_hora, m.nom_completo AS medico, e.nom_especialidad AS especialidad, cm.nombre AS centro, ec.nom_estado AS estado
    FROM cita c
    JOIN medico m ON c.rut_medico = m.rut_medico
    JOIN especialidad e ON c.id_especialidad = e.id_especialidad
    JOIN centro_medico cm ON c.codigo_interno = cm.codigo_interno
    JOIN estado_cita ec ON c.id_estado = ec.id_estado
    WHERE c.rut_paciente = '$rut_paciente' 
    AND c.id_estado IN (1, 2) 
    AND c.fecha_hora > NOW() 
    ORDER BY c.fecha_hora ASC
";
$citas_vigentes = $conn->query($sql_vigentes);

// ==========================================
// CONSULTA 2: HISTORIAL MÉDICO Y RECETAS (Citas Atendidas)
// ==========================================
$sql_historial = "
    SELECT 
        c.fecha_hora,
        m.nom_completo AS medico,
        e.nom_especialidad AS especialidad,
        a.motivo_consulta,
        a.observaciones,
        IFNULL(GROUP_CONCAT(DISTINCT d.descripcion SEPARATOR '<br>'), 'Sin diagnóstico registrado') AS diagnosticos,
        IFNULL(GROUP_CONCAT(DISTINCT CONCAT('• ', r.medicamento, ': ', r.dosis, ' (', r.dias_tratamiento, ' días)') SEPARATOR '<br>'), 'Sin recetas indicadas') AS recetas
    FROM cita c
    JOIN medico m ON c.rut_medico = m.rut_medico
    JOIN especialidad e ON c.id_especialidad = e.id_especialidad
    JOIN atencion a ON c.id_cita = a.id_cita
    LEFT JOIN atencion_diagnostico ad ON a.id_atencion = ad.id_atencion
    LEFT JOIN diagnostico d ON ad.codigo_cie10 = d.codigo_cie10
    LEFT JOIN receta r ON a.id_atencion = r.id_atencion
    WHERE c.rut_paciente = '$rut_paciente' AND c.id_estado = 3
    GROUP BY a.id_atencion
    ORDER BY c.fecha_hora DESC
";
$historial_clinico = $conn->query($sql_historial);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas y Recetas - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Portal del Paciente | SaludUSM</span>
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

    <!-- SECCIÓN 1: CITAS VIGENTES -->
    <div class="card shadow-sm border-success mb-5">
        <div class="card-header bg-success text-white py-3">
            <h5 class="mb-0">Mis Citas Agendadas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Fecha y Hora</th>
                            <th>Profesional</th>
                            <th>Especialidad</th>
                            <th>Centro Médico</th>
                            <th>Estado</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($citas_vigentes->num_rows > 0): ?>
                            <?php while($cita = $citas_vigentes->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-success"><?php echo date('d-m-Y \a \l\a\s H:i', strtotime($cita['fecha_hora'])); ?> hrs</td>
                                    <td><?php echo $cita['medico']; ?></td>
                                    <td><?php echo $cita['especialidad']; ?></td>
                                    <td><?php echo $cita['centro']; ?></td>
                                    <td><span class="badge bg-primary"><?php echo $cita['estado']; ?></span></td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-2">
                                            
                                            <!-- Botón Reprogramar (Abre Modal) -->
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRepro<?php echo $cita['id_cita']; ?>">
                                                Reprogramar
                                            </button>

                                            <!-- Formulario Cancelar -->
                                            <form method="POST" action="mis_citas.php" class="d-inline">
                                                <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                                <button type="submit" name="cancelar_cita" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Está seguro de que desea cancelar esta cita de forma permanente?');">Cancelar</button>
                                            </form>
                                        </div>

                                        <!-- Modal de Reprogramación para esta fila -->
                                        <div class="modal fade" id="modalRepro<?php echo $cita['id_cita']; ?>" tabindex="-1" aria-hidden="true">
                                          <div class="modal-dialog">
                                            <div class="modal-content text-start">
                                              <form method="POST" action="mis_citas.php">
                                                <div class="modal-header bg-primary text-white">
                                                  <h5 class="modal-title">Reprogramar Hora Médica</h5>
                                                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                  <div class="alert alert-info border-0 small">
                                                      Está reprogramando su atención con <strong><?php echo $cita['medico']; ?></strong> en <strong><?php echo $cita['centro']; ?></strong>. Su fecha actual es el <?php echo date('d-m-Y', strtotime($cita['fecha_hora'])); ?> a las <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?> hrs.
                                                  </div>
                                                  <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                                  
                                                  <div class="mb-3">
                                                      <label class="form-label fw-semibold text-muted small">Nueva Fecha</label>
                                                      <input type="date" name="nueva_fecha" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                                  </div>
                                                  
                                                  <div class="mb-3">
                                                      <label class="form-label fw-semibold text-muted small">Nuevo Bloque Horario</label>
                                                      <select name="nueva_hora" class="form-select" required>
                                                          <option value="">Seleccione hora disponible...</option>
                                                          <?php 
                                                          // Genera bloques de 09:00 a 17:00
                                                          for($h = 9; $h <= 17; $h++) {
                                                              $hr = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                                                              echo "<option value=\"$hr\">$hr hrs</option>";
                                                          }
                                                          ?>
                                                      </select>
                                                  </div>
                                                </div>
                                                <div class="modal-footer">
                                                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                                                  <button type="submit" name="reprogramar_cita" class="btn btn-primary btn-sm px-4">Confirmar Cambio</button>
                                                </div>
                                              </form>
                                            </div>
                                          </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No tienes citas médicas futuras vigentes.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SECCIÓN 2: HISTORIAL DE ATENCIONES Y RECETAS -->
    <div class="card shadow-sm border-secondary">
        <div class="card-header bg-secondary text-white py-3">
            <h5 class="mb-0">Historial Clínico y Recetas Médicas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%">Fecha de Atención</th>
                            <th style="width: 20%">Profesional Médico</th>
                            <th style="width: 25%">Detalle de Consulta</th>
                            <th style="width: 15%">Diagnósticos</th>
                            <th class="pe-4" style="width: 25%">Recetas e Indicaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($historial_clinico->num_rows > 0): ?>
                            <?php while($atencion = $historial_clinico->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-secondary">
                                        <?php echo date('d-m-Y', strtotime($atencion['fecha_hora'])); ?><br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($atencion['fecha_hora'])); ?> hrs</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?php echo $atencion['medico']; ?></span><br>
                                        <small class="text-muted"><?php echo $atencion['especialidad']; ?></small>
                                    </td>
                                    <td class="small">
                                        <strong>Motivo:</strong> <?php echo $atencion['motivo_consulta']; ?><br>
                                        <strong>Obs:</strong> <span class="text-muted"><?php echo $atencion['observaciones']; ?></span>
                                    </td>
                                    <td class="small text-danger fw-semibold">
                                        <?php echo $atencion['diagnosticos']; ?>
                                    </td>
                                    <td class="small text-primary pe-4">
                                        <?php echo $atencion['recetas']; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">Aún no tienes atenciones médicas registradas en tu historial.</td>
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
