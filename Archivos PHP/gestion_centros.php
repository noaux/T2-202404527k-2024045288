<?php
session_start();

if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 3) {
    header("Location: index.php");
    exit();
}

require 'conexion.php';

$mensaje = "";
$tipo_alerta = "";

// ==========================================
// PROCESAMIENTO CRUD DE CENTROS MÉDICOS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // 1. CREAR CENTRO
    if ($accion == 'crear_centro') {
        $codigo = strtoupper($conn->real_escape_string($_POST['codigo_interno']));
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $region = $conn->real_escape_string($_POST['region']);
        $comuna = $conn->real_escape_string($_POST['comuna']);

        try {
            $conn->query("INSERT INTO centro_medico (codigo_interno, nombre, region, comuna) VALUES ('$codigo', '$nombre', '$region', '$comuna')");
            $mensaje = "Nuevo Centro Médico agregado a la red.";
            $tipo_alerta = "success";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "Error al crear: Posible código interno duplicado.";
            $tipo_alerta = "danger";
        }
    }

    // 2. EDITAR CENTRO
    if ($accion == 'editar_centro') {
        $codigo = $conn->real_escape_string($_POST['codigo_interno']);
        $nombre = $conn->real_escape_string($_POST['nombre_edit']);
        $region = $conn->real_escape_string($_POST['region_edit']);
        $comuna = $conn->real_escape_string($_POST['comuna_edit']);

        try {
            $conn->query("UPDATE centro_medico SET nombre = '$nombre', region = '$region', comuna = '$comuna' WHERE codigo_interno = '$codigo'");
            $mensaje = "Datos del recinto actualizados correctamente.";
            $tipo_alerta = "success";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "Error al actualizar los datos.";
            $tipo_alerta = "danger";
        }
    }

    // 3. ELIMINAR CENTRO
    if ($accion == 'eliminar_centro') {
        $codigo = $conn->real_escape_string($_POST['codigo_interno']);
        
        try {
            $conn->query("DELETE FROM centro_medico WHERE codigo_interno = '$codigo'");
            $mensaje = "Centro Médico eliminado de los registros.";
            $tipo_alerta = "info";
        } catch (mysqli_sql_exception $e) {
            $mensaje = "No se puede eliminar este centro porque tiene médicos, pacientes o citas asociadas a él (Restricción de Integridad).";
            $tipo_alerta = "danger";
        }
    }
}

// Obtener listado actualizado
$lista_centros = $conn->query("SELECT * FROM centro_medico ORDER BY region, nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Centros - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Panel de Gestión | Sucursales y Clínicas</span>
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

    <div class="row">
        <!-- FORMULARIO CREAR -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-dark h-100">
                <div class="card-header bg-dark text-white fw-semibold">
                    Registrar Nueva Sucursal
                </div>
                <div class="card-body">
                    <form method="POST" action="gestion_centros.php">
                        <input type="hidden" name="accion" value="crear_centro">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold mb-1">Código Interno (Ej: CM6)</label>
                            <input type="text" name="codigo_interno" class="form-control form-control-sm" maxlength="10" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold mb-1">Nombre Comercial</label>
                            <input type="text" name="nombre" class="form-control form-control-sm" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold mb-1">Región</label>
                            <input type="text" name="region" class="form-control form-control-sm" placeholder="Ej: Valparaíso" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-semibold mb-1">Comuna</label>
                            <input type="text" name="comuna" class="form-control form-control-sm" placeholder="Ej: Viña del Mar" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark">Agregar Centro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TABLA DE CENTROS -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="text-secondary mb-0">Red de Atención Vigente</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Cod.</th>
                                    <th>Recinto Médico</th>
                                    <th>Región</th>
                                    <th>Comuna</th>
                                    <th class="text-center pe-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($lista_centros->num_rows > 0): ?>
                                    <?php while($c = $lista_centros->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-secondary"><?php echo $c['codigo_interno']; ?></td>
                                            <td class="fw-semibold text-primary"><?php echo $c['nombre']; ?></td>
                                            <td class="small"><?php echo $c['region']; ?></td>
                                            <td class="small"><?php echo $c['comuna']; ?></td>
                                            <td class="text-center text-nowrap pe-3">
                                                
                                                <!-- Botón Editar (Abre Modal) -->
                                                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editCentro<?php echo $c['codigo_interno']; ?>">Editar</button>
                                                
                                                <!-- Formulario Eliminar -->
                                                <form method="POST" action="gestion_centros.php" class="d-inline">
                                                    <input type="hidden" name="accion" value="eliminar_centro">
                                                    <input type="hidden" name="codigo_interno" value="<?php echo $c['codigo_interno']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar este recinto de la base de datos?');">x</button>
                                                </form>
                                                
                                            </td>
                                        </tr>

                                        <!-- Modal Editar -->
                                        <div class="modal fade" id="editCentro<?php echo $c['codigo_interno']; ?>" tabindex="-1">
                                          <div class="modal-dialog">
                                            <div class="modal-content">
                                              <form method="POST" action="gestion_centros.php">
                                                <div class="modal-header bg-dark text-white">
                                                  <h5 class="modal-title">Editar Centro Médico</h5>
                                                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-start">
                                                  <input type="hidden" name="accion" value="editar_centro">
                                                  <input type="hidden" name="codigo_interno" value="<?php echo $c['codigo_interno']; ?>">
                                                  
                                                  <div class="mb-3">
                                                      <label class="form-label small fw-semibold">Código Interno</label>
                                                      <input type="text" class="form-control bg-light" value="<?php echo $c['codigo_interno']; ?>" readonly>
                                                      <div class="form-text">El código primario no puede ser modificado.</div>
                                                  </div>
                                                  <div class="mb-3">
                                                      <label class="form-label small fw-semibold">Nombre Comercial</label>
                                                      <input type="text" name="nombre_edit" class="form-control" value="<?php echo htmlspecialchars($c['nombre']); ?>" required>
                                                  </div>
                                                  <div class="mb-3">
                                                      <label class="form-label small fw-semibold">Región</label>
                                                      <input type="text" name="region_edit" class="form-control" value="<?php echo htmlspecialchars($c['region']); ?>" required>
                                                  </div>
                                                  <div class="mb-3">
                                                      <label class="form-label small fw-semibold">Comuna</label>
                                                      <input type="text" name="comuna_edit" class="form-control" value="<?php echo htmlspecialchars($c['comuna']); ?>" required>
                                                  </div>
                                                </div>
                                                <div class="modal-footer">
                                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                  <button type="submit" class="btn btn-dark">Guardar Cambios</button>
                                                </div>
                                              </form>
                                            </div>
                                          </div>
                                        </div>

                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No hay centros médicos registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
