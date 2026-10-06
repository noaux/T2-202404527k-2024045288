<?php
session_start();

// Validar que el usuario haya iniciado sesión (cualquier rol tiene acceso)
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require 'conexion.php';

// Capturar el término de búsqueda si existe
$termino = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

// Consulta SQL que agrupa especialidades y centros por médico
$sql = "
    SELECT 
        m.nom_completo, 
        IFNULL(GROUP_CONCAT(DISTINCT e.nom_especialidad SEPARATOR ', '), 'Sin especialidad') AS especialidades,
        IFNULL(GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', '), 'Sin centro asignado') AS centros
    FROM medico m
    LEFT JOIN medico_especialidad me ON m.rut_medico = me.rut_medico
    LEFT JOIN especialidad e ON me.id_especialidad = e.id_especialidad
    LEFT JOIN medico_centro mc ON m.rut_medico = mc.rut_medico
    LEFT JOIN centro_medico c ON mc.codigo_interno = c.codigo_interno
    GROUP BY m.rut_medico
";

// Si el usuario escribió algo, filtramos por nombre O especialidad
if (!empty($termino)) {
    // Usamos HAVING porque nos permite filtrar por las columnas construidas con GROUP_CONCAT
    $sql .= " HAVING nom_completo LIKE '%$termino%' OR especialidades LIKE '%$termino%'";
}

$sql .= " ORDER BY nom_completo ASC";
$resultados = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio Médico - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Directorio de Profesionales</span>
        <div class="d-flex align-items-center">
            <!-- Barra de búsqueda integrada -->
            <form class="d-flex me-3" method="GET" action="buscar_medicos.php">
                <input class="form-control form-control-sm me-2" type="search" name="q" value="<?php echo htmlspecialchars($termino); ?>" placeholder="Buscar nombre o especialidad..." required>
                <button class="btn btn-outline-light btn-sm" type="submit">Buscar</button>
            </form>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Volver al Inicio</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="text-secondary mb-0">Resultados de la Búsqueda</h5>
            <?php if (!empty($termino)): ?>
                <span class="badge bg-primary">Filtrando por: "<?php echo htmlspecialchars($termino); ?>"</span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Profesional Médico</th>
                            <th style="width: 35%">Especialidades</th>
                            <th style="width: 35%">Centros donde atiende</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultados->num_rows > 0): ?>
                            <?php while($doc = $resultados->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-primary"><?php echo $doc['nom_completo']; ?></td>
                                    <td class="small text-muted"><?php echo $doc['especialidades']; ?></td>
                                    <td class="small text-muted"><?php echo $doc['centros']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">No se encontraron profesionales con ese criterio.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
