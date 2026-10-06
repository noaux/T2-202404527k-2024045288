<?php
session_start();

// Validar seguridad: Solo el Administrador (Rol 3) puede entrar aquí
if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 3) {
    // Si un paciente o médico intenta entrar escribiendo la URL, lo pateamos
    header("Location: index.php");
    exit();
}

require 'conexion.php';

// Arreglos para guardar la información que vendrá de la base de datos
$tabla_centros = [];
$tabla_diagnosticos = [];

// Llamar al Procedimiento Almacenado y preparar PHP para recibir múltiples resultados
if ($conn->multi_query("CALL sp_estadisticas_administrador()")) {
    
    // 1. Capturar el PRIMER resultado (Estadísticas por Centro)
    if ($resultado1 = $conn->store_result()) {
        while ($fila = $resultado1->fetch_assoc()) {
            $tabla_centros[] = $fila;
        }
        $resultado1->free();
    }
    
    // 2. Preguntar si hay más resultados y avanzar al SEGUNDO resultado (Top 5 Diagnósticos)
    if ($conn->more_results() && $conn->next_result()) {
        if ($resultado2 = $conn->store_result()) {
            while ($fila = $resultado2->fetch_assoc()) {
                $tabla_diagnosticos[] = $fila;
            }
            $resultado2->free();
        }
    }
} else {
    $error = "Error al obtener las estadísticas: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas Clínicas - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Panel de Gestión | SaludUSM</span>
        <a href="index.php" class="btn btn-outline-light btn-sm">Volver al Inicio</a>
    </div>
</nav>

<div class="container mt-5">
    <h2 class="text-danger mb-4">Estadísticas de la Red Asistencial</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Tarjeta 1: Estadísticas por Centro -->
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="text-secondary mb-0">Citas e Inasistencias por Centro</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 text-center">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-start ps-4">Centro Médico</th>
                                    <th>Total de Citas</th>
                                    <th class="pe-4">% Inasistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tabla_centros as $centro): ?>
                                    <tr>
                                        <td class="text-start ps-4 fw-semibold"><?php echo $centro['centro_medico']; ?></td>
                                        <td><?php echo $centro['total_citas']; ?></td>
                                        <td class="pe-4">
                                            <!-- Agregamos un color visual si la inasistencia es mayor al 20% -->
                                            <span class="badge <?php echo ($centro['porcentaje_inasistencia'] > 20) ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $centro['porcentaje_inasistencia']; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta 2: Top Diagnósticos -->
        <div class="col-md-5 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="text-secondary mb-0">Top 5 Diagnósticos Frecuentes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Diagnóstico (CIE-10)</th>
                                    <th class="text-center pe-4">Casos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $posicion = 1;
                                foreach ($tabla_diagnosticos as $diag): 
                                ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="text-muted me-2">#<?php echo $posicion; ?></span> 
                                            <?php echo $diag['diagnostico']; ?>
                                        </td>
                                        <td class="text-center pe-4 fw-bold text-primary">
                                            <?php echo $diag['cantidad_casos']; ?>
                                        </td>
                                    </tr>
                                <?php 
                                $posicion++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
