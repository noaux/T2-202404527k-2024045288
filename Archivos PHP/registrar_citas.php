<?php
session_start();
date_default_timezone_set('America/Santiago');

// Validar que sea Médico (Rol 2)
if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 2) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';

$id_usuario = $_SESSION['id_usuario'];
$mensaje = "";
$tipo_alerta = "";

// 1. Obtener datos del médico logueado
$sql_medico = "SELECT rut_medico FROM medico WHERE id_usuario = $id_usuario";
$rut_medico = $conn->query($sql_medico)->fetch_assoc()['rut_medico'];

// 2. Validar que venga el ID de la cita
$id_cita = isset($_GET['id_cita']) ? (int)$_GET['id_cita'] : (isset($_POST['id_cita']) ? (int)$_POST['id_cita'] : 0);

if ($id_cita == 0) {
    header("Location: agenda_medico.php");
    exit();
}

// ==========================================
// PROCESAMIENTO CRUD (ATENCIÓN, DIAGNÓSTICOS Y RECETAS)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // A. GUARDAR O ACTUALIZAR ATENCIÓN (Crea la ficha y cambia estado de cita)
    if ($accion == 'guardar_atencion') {
        $motivo = $conn->real_escape_string($_POST['motivo']);
        $observaciones = $conn->real_escape_string($_POST['observaciones']);

        $check_atencion = $conn->query("SELECT id_atencion FROM atencion WHERE id_cita = $id_cita");
        
        $conn->begin_transaction();
        try {
            if ($check_atencion->num_rows > 0) {
                // Actualizar si ya existe
                $id_atencion = $check_atencion->fetch_assoc()['id_atencion'];
                $conn->query("UPDATE atencion SET motivo_consulta = '$motivo', observaciones = '$observaciones' WHERE id_atencion = $id_atencion");
                $mensaje = "Registro clínico actualizado.";
            } else {
                // Insertar nueva y pasar a estado "Atendida" (3)
                $conn->query("INSERT INTO atencion (motivo_consulta, observaciones, id_cita) VALUES ('$motivo', '$observaciones', $id_cita)");
                $conn->query("UPDATE cita SET id_estado = 3 WHERE id_cita = $id_cita");
                $mensaje = "Ficha base creada. Ahora puede agregar diagnósticos y recetas.";
            }
            $conn->commit();
            $tipo_alerta = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al guardar: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }

    // B. AGREGAR DIAGNÓSTICO
    if ($accion == 'agregar_diag') {
        $id_atencion = (int)$_POST['id_atencion'];
        $codigo_cie10 = $conn->real_escape_string($_POST['codigo_cie10']);
        try {
            $conn->query("INSERT INTO atencion_diagnostico (id_atencion, codigo_cie10) VALUES ($id_atencion, '$codigo_cie10')");
            $mensaje = "Diagnóstico agregado.";
            $tipo_alerta = "success";
        } catch (Exception $e) {
            $mensaje = "El diagnóstico ya está registrado en esta atención.";
            $tipo_alerta = "warning";
        }
    }

    // C. EDITAR DIAGNÓSTICO
    if ($accion == 'editar_diag') {
        $id_atencion = (int)$_POST['id_atencion'];
        $codigo_viejo = $conn->real_escape_string($_POST['codigo_viejo']);
        $codigo_nuevo = $conn->real_escape_string($_POST['codigo_nuevo']);
        try {
            $conn->query("UPDATE atencion_diagnostico SET codigo_cie10 = '$codigo_nuevo' WHERE id_atencion = $id_atencion AND codigo_cie10 = '$codigo_viejo'");
            $mensaje = "Diagnóstico modificado.";
            $tipo_alerta = "success";
        } catch (Exception $e) {
            $mensaje = "Error al modificar (Posible duplicado).";
            $tipo_alerta = "danger";
        }
    }

    // D. ELIMINAR DIAGNÓSTICO
    if ($accion == 'eliminar_diag') {
        $id_atencion = (int)$_POST['id_atencion'];
        $codigo_cie10 = $conn->real_escape_string($_POST['codigo_cie10']);
        $conn->query("DELETE FROM atencion_diagnostico WHERE id_atencion = $id_atencion AND codigo_cie10 = '$codigo_cie10'");
        $mensaje = "Diagnóstico removido.";
        $tipo_alerta = "info";
    }

    // E. AGREGAR RECETA
    if ($accion == 'agregar_receta') {
        $id_atencion = (int)$_POST['id_atencion'];
        $medicamento = $conn->real_escape_string($_POST['medicamento']);
        $dosis = $conn->real_escape_string($_POST['dosis']);
        $dias = (int)$_POST['dias'];
        $conn->query("INSERT INTO receta (medicamento, dosis, dias_tratamiento, id_atencion) VALUES ('$medicamento', '$dosis', $dias, $id_atencion)");
        $mensaje = "Línea de receta agregada.";
        $tipo_alerta = "success";
    }

    // F. EDITAR RECETA
    if ($accion == 'editar_receta') {
        $id_receta = (int)$_POST['id_receta'];
        $medicamento = $conn->real_escape_string($_POST['medicamento_edit']);
        $dosis = $conn->real_escape_string($_POST['dosis_edit']);
        $dias = (int)$_POST['dias_edit'];
        $conn->query("UPDATE receta SET medicamento = '$medicamento', dosis = '$dosis', dias_tratamiento = $dias WHERE id_receta = $id_receta");
        $mensaje = "Línea de receta actualizada.";
        $tipo_alerta = "success";
    }

    // G. ELIMINAR RECETA
    if ($accion == 'eliminar_receta') {
        $id_receta = (int)$_POST['id_receta'];
        $conn->query("DELETE FROM receta WHERE id_receta = $id_receta");
        $mensaje = "Receta eliminada.";
        $tipo_alerta = "info";
    }
}

// ==========================================
// CARGAR DATOS DE LA CITA Y PACIENTE
// ==========================================
$sql_cita = "
    SELECT c.*, p.nom_completo AS paciente, p.rut_paciente, p.sexo, p.fecha_nacimiento 
    FROM cita c
    JOIN paciente p ON c.rut_paciente = p.rut_paciente
    WHERE c.id_cita = $id_cita AND c.rut_medico = '$rut_medico'";
$resultado_cita = $conn->query($sql_cita);

if ($resultado_cita->num_rows == 0) {
    die("Error: Cita no encontrada o no tiene permisos.");
}
$datos_cita = $resultado_cita->fetch_assoc();
$rut_paciente_actual = $datos_cita['rut_paciente'];

// ==========================================
// CARGAR ATENCIÓN ACTUAL
// ==========================================
$id_atencion = 0;
$motivo_actual = "";
$obs_actual = "";

$res_atencion = $conn->query("SELECT * FROM atencion WHERE id_cita = $id_cita");
if ($res_atencion->num_rows > 0) {
    $row_atencion = $res_atencion->fetch_assoc();
    $id_atencion = $row_atencion['id_atencion'];
    $motivo_actual = $row_atencion['motivo_consulta'];
    $obs_actual = $row_atencion['observaciones'];
}

// ==========================================
// CARGAR DIAGNÓSTICOS, RECETAS E HISTORIAL
// ==========================================
$catalogo_diagnosticos = $conn->query("SELECT * FROM diagnostico ORDER BY descripcion");
$cat_diag_array = [];
while($row = $catalogo_diagnosticos->fetch_assoc()) {
    $cat_diag_array[] = $row; 
}

$diagnosticos_actuales = null;
$recetas_actuales = null;

if ($id_atencion > 0) {
    $diagnosticos_actuales = $conn->query("SELECT d.codigo_cie10, d.descripcion FROM atencion_diagnostico ad JOIN diagnostico d ON ad.codigo_cie10 = d.codigo_cie10 WHERE ad.id_atencion = $id_atencion");
    $recetas_actuales = $conn->query("SELECT * FROM receta WHERE id_atencion = $id_atencion");
}

$sql_historial = "
    SELECT c.fecha_hora, m.nom_completo AS medico, e.nom_especialidad AS especialidad, a.motivo_consulta, a.observaciones,
           IFNULL(GROUP_CONCAT(DISTINCT d.descripcion SEPARATOR '<br>'), 'Sin diagnóstico') AS diags,
           IFNULL(GROUP_CONCAT(DISTINCT CONCAT(r.medicamento, ' (', r.dosis, ')') SEPARATOR '<br>'), 'Sin recetas') AS recs
    FROM cita c
    JOIN medico m ON c.rut_medico = m.rut_medico
    JOIN especialidad e ON c.id_especialidad = e.id_especialidad
    JOIN atencion a ON c.id_cita = a.id_cita
    LEFT JOIN atencion_diagnostico ad ON a.id_atencion = ad.id_atencion
    LEFT JOIN diagnostico d ON ad.codigo_cie10 = d.codigo_cie10
    LEFT JOIN receta r ON a.id_atencion = r.id_atencion
    WHERE c.rut_paciente = '$rut_paciente_actual' AND c.id_cita != $id_cita AND c.id_estado = 3
    GROUP BY a.id_atencion ORDER BY c.fecha_hora DESC
";
$historial_paciente = $conn->query($sql_historial);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atención Clínica - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Ficha de Atención Clínica</span>
        <a href="agenda_medico.php" class="btn btn-outline-light btn-sm">Volver a la Agenda</a>
    </div>
</nav>

<div class="container-fluid mt-4 px-4">
    <?php if ($mensaje != ""): ?>
        <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- CABECERA DEL PACIENTE -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body py-3 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0 text-primary fw-bold"><?php echo $datos_cita['paciente']; ?></h4>
                <small class="text-muted">RUT: <?php echo $datos_cita['rut_paciente']; ?> | Sexo: <?php echo $datos_cita['sexo']; ?> | Nacimiento: <?php echo date('d-m-Y', strtotime($datos_cita['fecha_nacimiento'])); ?></small>
            </div>
            <div class="text-end">
                <span class="badge bg-secondary fs-6">Fecha de Cita: <?php echo date('d-m-Y H:i', strtotime($datos_cita['fecha_hora'])); ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- COLUMNA IZQUIERDA: FORMULARIOS DE ATENCIÓN -->
        <div class="col-lg-8">
            
            <!-- 1. MOTIVO Y OBSERVACIONES -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white fw-semibold">1. Registro Clínico</div>
                <div class="card-body">
                    <form method="POST" action="registrar_atencion.php">
                        <input type="hidden" name="accion" value="guardar_atencion">
                        <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small">Motivo de Consulta</label>
                            <textarea name="motivo" class="form-control" rows="2" required><?php echo htmlspecialchars($motivo_actual); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small">Observaciones Clínicas</label>
                            <textarea name="observaciones" class="form-control" rows="3" required><?php echo htmlspecialchars($obs_actual); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary px-4"><?php echo ($id_atencion > 0) ? 'Actualizar Texto' : 'Guardar Ficha e Iniciar Atención'; ?></button>
                            <?php if ($id_atencion > 0): ?>
                                <a href="agenda_medico.php" class="btn btn-success px-4">Finalizar y Salir</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 2. DIAGNÓSTICOS Y RECETAS (SOLO VISIBLE SI YA SE GUARDÓ LA ATENCIÓN) -->
            <?php if ($id_atencion > 0): ?>
            <div class="row">
                
                <!-- PANEL DIAGNÓSTICOS (Agregar, Editar, Eliminar) -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-danger h-100">
                        <div class="card-header bg-danger text-white fw-semibold">2. Diagnósticos CIE-10</div>
                        <div class="card-body d-flex flex-column">
                            
                            <form method="POST" action="registrar_atencion.php" class="mb-3">
                                <input type="hidden" name="accion" value="agregar_diag">
                                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                                <input type="hidden" name="id_atencion" value="<?php echo $id_atencion; ?>">
                                <div class="input-group input-group-sm">
                                    <select name="codigo_cie10" class="form-select" required>
                                        <option value="">Seleccione diagnóstico...</option>
                                        <?php foreach($cat_diag_array as $diag): ?>
                                            <option value="<?php echo $diag['codigo_cie10']; ?>">[<?php echo $diag['codigo_cie10']; ?>] <?php echo $diag['descripcion']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-danger">Agregar</button>
                                </div>
                            </form>

                            <div class="table-responsive mt-auto">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light"><tr><th>CIE-10</th><th>Descripción</th><th class="text-center">Acción</th></tr></thead>
                                    <tbody>
                                        <?php if($diagnosticos_actuales && $diagnosticos_actuales->num_rows > 0): ?>
                                            <?php while($d = $diagnosticos_actuales->fetch_assoc()): ?>
                                            <tr>
                                                <td class="fw-bold text-danger"><?php echo $d['codigo_cie10']; ?></td>
                                                <td class="small"><?php echo $d['descripcion']; ?></td>
                                                <td class="text-center text-nowrap">
                                                    <!-- Botón Editar Diagnóstico -->
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#editDiag<?php echo $d['codigo_cie10']; ?>">✎</button>
                                                    
                                                    <!-- Formulario Eliminar Diagnóstico -->
                                                    <form method="POST" action="registrar_atencion.php" class="d-inline m-0">
                                                        <input type="hidden" name="accion" value="eliminar_diag">
                                                        <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                                                        <input type="hidden" name="id_atencion" value="<?php echo $id_atencion; ?>">
                                                        <input type="hidden" name="codigo_cie10" value="<?php echo $d['codigo_cie10']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Quitar">x</button>
                                                    </form>
                                                </td>
                                            </tr>

                                            <!-- Modal Editar Diagnóstico -->
                                            <div class="modal fade" id="editDiag<?php echo $d['codigo_cie10']; ?>" tabindex="-1">
                                              <div class="modal-dialog modal-sm">
                                                <div class="modal-content">
                                                  <form method="POST" action="registrar_atencion.php">
                                                    <div class="modal-header bg-danger text-white py-2">
                                                      <h6 class="modal-title">Cambiar Diagnóstico</h6>
                                                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                      <input type="hidden" name="accion" value="editar_diag">
                                                      <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                                                      <input type="hidden" name="id_atencion" value="<?php echo $id_atencion; ?>">
                                                      <input type="hidden" name="codigo_viejo" value="<?php echo $d['codigo_cie10']; ?>">
                                                      <label class="small mb-1">Seleccione el nuevo diagnóstico:</label>
                                                      <select name="codigo_nuevo" class="form-select form-select-sm" required>
                                                          <?php foreach($cat_diag_array as $cd): ?>
                                                              <option value="<?php echo $cd['codigo_cie10']; ?>" <?php if($cd['codigo_cie10'] == $d['codigo_cie10']) echo 'selected'; ?>>
                                                                  [<?php echo $cd['codigo_cie10']; ?>] <?php echo $cd['descripcion']; ?>
                                                              </option>
                                                          <?php endforeach; ?>
                                                      </select>
                                                    </div>
                                                    <div class="modal-footer p-1">
                                                      <button type="submit" class="btn btn-danger btn-sm w-100">Guardar Cambio</button>
                                                    </div>
                                                  </form>
                                                </div>
                                              </div>
                                            </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center text-muted small py-2">Sin diagnósticos asignados.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANEL RECETAS (Agregar, Editar, Eliminar) -->
                <div class="col-md-6">
                    <div class="card shadow-sm border-success h-100 mt-4 mt-md-0">
                        <div class="card-header bg-success text-white fw-semibold">3. Prescripción de Recetas</div>
                        <div class="card-body d-flex flex-column">
                            
                            <form method="POST" action="registrar_atencion.php" class="row g-2 mb-3">
                                <input type="hidden" name="accion" value="agregar_receta">
                                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                                <input type="hidden" name="id_atencion" value="<?php echo $id_atencion; ?>">
                                <div class="col-12"><input type="text" name="medicamento" class="form-control form-control-sm" placeholder="Nombre del Medicamento" required></div>
                                <div class="col-7"><input type="text" name="dosis" class="form-control form-control-sm" placeholder="Dosis (Ej: 1 c/8hrs)" required></div>
                                <div class="col-5"><div class="input-group input-group-sm"><input type="number" name="dias" class="form-control" placeholder="Días" required><button type="submit" class="btn btn-success">+</button></div></div>
                            </form>

                            <div class="table-responsive mt-auto">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light"><tr><th>Medicamento</th><th>Indicación</th><th class="text-center">Acción</th></tr></thead>
                                    <tbody>
                                        <?php if($recetas_actuales && $recetas_actuales->num_rows > 0): ?>
                                            <?php while($r = $recetas_actuales->fetch_assoc()): ?>
                                            <tr>
                                                <td class="small fw-semibold"><?php echo $r['medicamento']; ?></td>
                                                <td class="small"><?php echo $r['dosis']; ?> (<?php echo $r['dias_tratamiento']; ?>d)</td>
                                                <td class="text-center text-nowrap">
                                                    <!-- Botón Editar Receta -->
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $r['id_receta']; ?>">✎</button>
                                                    
                                                    <!-- Formulario Eliminar Receta -->
                                                    <form method="POST" action="registrar_atencion.php" class="d-inline m-0">
                                                        <input type="hidden" name="accion" value="eliminar_receta">
                                                        <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                                                        <input type="hidden" name="id_receta" value="<?php echo $r['id_receta']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1">x</button>
                                                    </form>
                                                </td>
                                            </tr>

                                            <!-- Modal Editar Receta -->
                                            <div class="modal fade" id="editModal<?php echo $r['id_receta']; ?>" tabindex="-1">
                                              <div class="modal-dialog modal-sm">
                                                <div class="modal-content">
                                                  <form method="POST" action="registrar_atencion.php">
                                                    <div class="modal-header bg-success text-white py-2">
                                                      <h6 class="modal-title">Editar Receta</h6>
                                                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                      <input type="hidden" name="accion" value="editar_receta">
                                                      <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                                                      <input type="hidden" name="id_receta" value="<?php echo $r['id_receta']; ?>">
                                                      <div class="mb-2"><label class="small">Medicamento</label><input type="text" name="medicamento_edit" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r['medicamento']); ?>" required></div>
                                                      <div class="mb-2"><label class="small">Dosis</label><input type="text" name="dosis_edit" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r['dosis']); ?>" required></div>
                                                      <div class="mb-2"><label class="small">Días de Tratamiento</label><input type="number" name="dias_edit" class="form-control form-control-sm" value="<?php echo $r['dias_tratamiento']; ?>" required></div>
                                                    </div>
                                                    <div class="modal-footer p-1">
                                                      <button type="submit" class="btn btn-success btn-sm w-100">Guardar Cambios</button>
                                                    </div>
                                                  </form>
                                                </div>
                                              </div>
                                            </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center text-muted small py-2">Sin recetas prescritas.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-info border-0 mt-3 text-center">
                    <small>Los paneles de <strong>Diagnóstico</strong> y <strong>Receta</strong> se habilitarán al presionar el botón azul "Guardar Ficha e Iniciar Atención".</small>
                </div>
            <?php endif; ?>
        </div>

        <!-- COLUMNA DERECHA: HISTORIAL CLÍNICO -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-secondary h-100">
                <div class="card-header bg-secondary text-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Historial del Paciente</span>
                    <span class="badge bg-light text-dark"><?php echo $historial_paciente->num_rows; ?> previas</span>
                </div>
                <div class="card-body p-0" style="max-height: 80vh; overflow-y: auto;">
                    <?php if ($historial_paciente->num_rows > 0): ?>
                        <div class="accordion accordion-flush" id="accordionHistorial">
                            <?php $i = 1; while($hist = $historial_paciente->fetch_assoc()): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?php echo $i; ?>">
                                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $i; ?>">
                                            <div>
                                                <strong><?php echo date('d-m-Y', strtotime($hist['fecha_hora'])); ?></strong><br>
                                                <small class="text-muted"><?php echo $hist['especialidad']; ?></small>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionHistorial">
                                        <div class="accordion-body small bg-light p-3">
                                            <strong>Médico Tratante:</strong> <?php echo $hist['medico']; ?><hr class="my-1">
                                            <strong>Motivo:</strong> <?php echo $hist['motivo_consulta']; ?><br>
                                            <strong>Observaciones:</strong> <?php echo $hist['observaciones']; ?><hr class="my-1">
                                            <strong class="text-danger">Diagnósticos:</strong><br> <?php echo $hist['diags']; ?><hr class="my-1">
                                            <strong class="text-success">Recetas:</strong><br> <?php echo $hist['recs']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php $i++; endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5 text-muted">
                            <p class="mb-0">El paciente no registra atenciones médicas previas.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
