<?php
session_start();

// Validar que solo el Paciente (Rol 1) pueda acceder
if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';

$id_usuario = $_SESSION['id_usuario'];
$mensaje = "";
$tipo_alerta = "";
$paso = 1; 
$bloques_disponibles = [];

// Obtener RUT del paciente logueado
$sql_paciente = "SELECT rut_paciente FROM paciente WHERE id_usuario = $id_usuario";
$resultado_paciente = $conn->query($sql_paciente);
$rut_paciente = $resultado_paciente->fetch_assoc()['rut_paciente'];

// ==========================================
// PROCESAR FORMULARIO (2 PASOS)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // PASO 1: Validar médico y proyectar disponibilidad
    if (isset($_POST['buscar_horas'])) {
        $rut_medico = $conn->real_escape_string($_POST['medico']);
        $cod_centro = $conn->real_escape_string($_POST['centro']);
        $id_especialidad = (int)$_POST['especialidad'];

        // Validar consistencia de negocio
        $check_centro = $conn->query("SELECT * FROM medico_centro WHERE rut_medico = '$rut_medico' AND codigo_interno = '$cod_centro'");
        $check_esp = $conn->query("SELECT * FROM medico_especialidad WHERE rut_medico = '$rut_medico' AND id_especialidad = $id_especialidad");

        if ($check_centro->num_rows == 0 || $check_esp->num_rows == 0) {
            $mensaje = "Error de consistencia: El profesional seleccionado no atiende en el recinto indicado o no imparte la especialidad.";
            $tipo_alerta = "warning";
        } else {
            $paso = 2; // Avanzamos a mostrar los horarios
            
            // 1. Obtener todas las citas del médico desde HOY hasta 14 días en el futuro
            $fecha_hoy = date('Y-m-d');
            $fecha_limite = date('Y-m-d', strtotime("+14 days"));
            
            $sql_ocupadas = "SELECT fecha_hora FROM cita 
                             WHERE rut_medico = '$rut_medico' 
                             AND DATE(fecha_hora) BETWEEN '$fecha_hoy' AND '$fecha_limite'
                             AND id_estado IN (1, 2, 3)"; // Reservada, Confirmada, Atendida
            $res_ocupadas = $conn->query($sql_ocupadas);
            
            $horas_ocupadas = [];
            while($row = $res_ocupadas->fetch_assoc()) {
                $horas_ocupadas[] = $row['fecha_hora']; 
            }

            // 2. Calcular los próximos 7 días hábiles y sus bloques de atención (09:00 a 17:00)
            $dias_proyectados = 0;
            $contador_dias = 1; // Empezamos a buscar desde mañana

            while ($dias_proyectados < 7) {
                $fecha_eval = date('Y-m-d', strtotime("+$contador_dias days"));
                $dia_semana = date('w', strtotime($fecha_eval)); // 0=Domingo, 6=Sábado
                
                // Excluimos fines de semana
                if ($dia_semana != 0 && $dia_semana != 6) {
                    
                    // Evaluamos cada bloque horario
                    for ($h = 9; $h <= 17; $h++) {
                        $hora_formateada = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00:00';
                        $fecha_hora_eval = $fecha_eval . ' ' . $hora_formateada;

                        // Si esta fecha y hora NO está en el arreglo de ocupadas, la mostramos
                        if (!in_array($fecha_hora_eval, $horas_ocupadas)) {
                            // Traducir los días al español para la interfaz
                            $dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                            $nombre_dia = $dias_es[$dia_semana];
                            
                            $bloques_disponibles[] = [
                                'valor_bd' => $fecha_hora_eval,
                                'texto_mostrar' => "$nombre_dia " . date('d-m-Y', strtotime($fecha_eval)) . " - " . substr($hora_formateada, 0, 5) . " hrs"
                            ];
                        }
                    }
                    $dias_proyectados++; // Día procesado
                }
                $contador_dias++;
            }

            if (empty($bloques_disponibles)) {
                $mensaje = "El profesional tiene su agenda completamente llena para los próximos días.";
                $tipo_alerta = "danger";
                $paso = 1; // Lo devolvemos al inicio
            }
        }
    }

    // PASO 2: Confirmar la reserva exacta
    if (isset($_POST['confirmar_reserva'])) {
        $rut_medico = $conn->real_escape_string($_POST['medico']);
        $cod_centro = $conn->real_escape_string($_POST['centro']);
        $id_especialidad = (int)$_POST['especialidad'];
        $fecha_hora = $conn->real_escape_string($_POST['fecha_hora_elegida']); // Ej: 2026-10-14 09:00:00
        
        $id_estado = 1; // 1 = Reservada

        try {
            $sql_insert = "INSERT INTO cita (fecha_hora, id_estado, rut_paciente, rut_medico, codigo_interno, id_especialidad) 
                           VALUES ('$fecha_hora', $id_estado, '$rut_paciente', '$rut_medico', '$cod_centro', $id_especialidad)";
            
            if ($conn->query($sql_insert)) {
                $mensaje = "¡Éxito! Tu cita médica ha sido agendada para el " . date('d-m-Y \a \l\a\s H:i', strtotime($fecha_hora)) . " hrs.";
                $tipo_alerta = "success";
                $paso = 1; // Reiniciamos el formulario
            }
        } catch (mysqli_sql_exception $e) {
            $mensaje = "No se pudo agendar (Posible error de red): " . $e->getMessage();
            $tipo_alerta = "danger";
            $paso = 1;
        }
    }
}

// Catálogos para los selectores
$especialidades = $conn->query("SELECT * FROM especialidad ORDER BY nom_especialidad");
$centros = $conn->query("SELECT * FROM centro_medico ORDER BY nombre");
$medicos = $conn->query("SELECT * FROM medico ORDER BY nom_completo");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Cita - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Portal del Paciente | SaludUSM</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Volver al Inicio</a>
    </div>
</nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-success">
                <div class="card-header bg-white border-bottom border-success">
                    <h4 class="text-success mb-0">Reserva de Hora Médica</h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($mensaje != ""): ?>
                        <div class="alert alert-<?php echo $tipo_alerta; ?>" role="alert">
                            <?php echo $mensaje; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($paso == 1): ?>
                    <!-- PASO 1: SELECCIONAR PROFESIONAL -->
                    <form method="POST" action="agendar_cita.php">
                        <input type="hidden" name="buscar_horas" value="1">
                        
                        <div class="alert alert-success bg-opacity-10 border-0 mb-4">
                            <strong>Paso 1:</strong> Seleccione el médico, recinto y especialidad que necesita. El sistema buscará automáticamente los días y horas que el doctor tenga disponibles.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-semibold">Especialidad Requerida</label>
                                <select name="especialidad" class="form-select" required>
                                    <option value="">Seleccione especialidad...</option>
                                    <?php while($row = $especialidades->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id_especialidad']; ?>"><?php echo $row['nom_especialidad']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-semibold">Centro Médico</label>
                                <select name="centro" class="form-select" required>
                                    <option value="">Seleccione recinto...</option>
                                    <?php while($row = $centros->fetch_assoc()): ?>
                                        <option value="<?php echo $row['codigo_interno']; ?>"><?php echo $row['nombre']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label class="form-label text-muted fw-semibold">Médico Tratante</label>
                                <select name="medico" class="form-select" required>
                                    <option value="">Seleccione profesional...</option>
                                    <?php while($row = $medicos->fetch_assoc()): ?>
                                        <option value="<?php echo $row['rut_medico']; ?>"><?php echo $row['nom_completo']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Ver Horarios Disponibles</button>
                        </div>
                    </form>

                    <?php elseif ($paso == 2): ?>
                    <!-- PASO 2: ELEGIR DÍA Y HORA DE LA LISTA DISPONIBLE -->
                    <form method="POST" action="agendar_cita.php">
                        <input type="hidden" name="confirmar_reserva" value="1">
                        <!-- Ocultos para mantener los datos elegidos en el paso 1 -->
                        <input type="hidden" name="medico" value="<?php echo htmlspecialchars($_POST['medico']); ?>">
                        <input type="hidden" name="centro" value="<?php echo htmlspecialchars($_POST['centro']); ?>">
                        <input type="hidden" name="especialidad" value="<?php echo htmlspecialchars($_POST['especialidad']); ?>">

                        <div class="alert alert-info border-0 mb-4">
                            <strong>Paso 2:</strong> Seleccione uno de los bloques horarios disponibles para el profesional.
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Días y Horas Libres (Próximos 7 días hábiles):</label>
                            <select name="fecha_hora_elegida" class="form-select form-select-lg border-success" size="8" required>
                                <?php foreach ($bloques_disponibles as $bloque): ?>
                                    <option value="<?php echo $bloque['valor_bd']; ?>">
                                        <?php echo $bloque['texto_mostrar']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text mt-2">* Horario de atención: 09:00 a 17:00 hrs. Los horarios ya agendados no se muestran en esta lista.</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="agendar_cita.php" class="btn btn-outline-secondary">Atrás</a>
                            <button type="submit" class="btn btn-success px-5">Confirmar Reserva</button>
                        </div>
                    </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
