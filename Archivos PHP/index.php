<?php
session_start();

// Si no ha iniciado sesión, lo expulsamos al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_rol = $_SESSION['id_rol'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">SaludUSM</span>
        <div class="d-flex align-items-center">
            
            <!-- NUEVA BARRA DE BÚSQUEDA GLOBAL -->
            <form class="d-flex me-4" method="GET" action="buscar_medicos.php">
                <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Buscar médico o especialidad..." aria-label="Buscar" required>
                <button class="btn btn-dark btn-sm text-nowrap" type="submit">🔍 Buscar</button>
            </form>

            <span class="text-white me-3 fw-semibold d-none d-md-inline">
                <?php 
                    if ($id_rol == 1) echo "Portal Paciente";
                    if ($id_rol == 2) echo "Portal Médico";
                    if ($id_rol == 3) echo "Administrador de Red";
                ?>
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm text-nowrap">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="row justify-content-center">

        <?php if ($id_rol == 1): ?>
        <!-- ========================================== -->
        <!-- PORTAL DEL PACIENTE                        -->
        <!-- ========================================== -->
        <div class="col-md-12 mb-4">
            <h3 class="text-success">Portal del Paciente</h3>
            <hr>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-success">
                <div class="card-body text-center p-4">
                    <h5 class="card-title text-success mb-3">Agendar Nueva Cita</h5>
                    <p class="card-text text-muted mb-4">Reserva una hora con nuestros profesionales médicos según tu previsión.</p>
                    <a href="agendar_cita.php" class="btn btn-success px-4">Agendar Hora</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-success">
                <div class="card-body text-center p-4">
                    <h5 class="card-title text-success mb-3">Mis Citas</h5>
                    <p class="card-text text-muted mb-4">Revisa tu historial médico y cancela tus citas programadas si no puedes asistir.</p>
                    <a href="mis_citas.php" class="btn btn-outline-success px-4">Ver mi Historial</a>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Zona de Peligro (Solo Paciente) -->
        <div class="col-md-12 mt-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-center p-4">
                    <div class="mb-3 mb-md-0">
                        <h5 class="card-title text-danger mb-1">Zona de Peligro</h5>
                        <p class="card-text text-muted mb-0">Elimina tu cuenta y todo tu historial del sistema de forma permanente.</p>
                    </div>
                    <a href="eliminar_cuenta.php" class="btn btn-danger px-4">Eliminar mi Cuenta</a>
                </div>
            </div>
        </div>

        <?php elseif ($id_rol == 2): ?>
        <!-- ========================================== -->
        <!-- PANEL DEL MÉDICO                           -->
        <!-- ========================================== -->
        <div class="col-md-12 mb-4">
            <h3 class="text-primary">Portal Clínico (Médico)</h3>
            <hr>
        </div>
        
        <div class="col-md-8 mx-auto mb-4">
            <div class="card h-100 shadow-sm border-primary">
                <div class="card-body text-center p-5">
                    <h4 class="card-title text-primary mb-3">Mi Agenda Clínica</h4>
                    <p class="card-text text-muted mb-4">Revisa tu lista de pacientes agendados, selecciona su ficha y registra las atenciones y diagnósticos del día.</p>
                    <a href="agenda_medico.php" class="btn btn-primary btn-lg px-5">Ir a mi Agenda</a>
                </div>
            </div>
        </div>

        <?php elseif ($id_rol == 3): ?>
        <!-- ========================================== -->
        <!-- PANEL DEL ADMINISTRADOR                    -->
        <!-- ========================================== -->
        <div class="col-md-12 mb-4">
            <h3 class="text-secondary">Panel de Gestión - Administrador</h3>
            <hr>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-dark">
                <div class="card-body d-flex flex-column text-center p-4">
                    <h5 class="card-title text-dark mb-3">Búsqueda Avanzada</h5>
                    <p class="card-text text-muted mb-4">Busca citas usando múltiples filtros cruzados en la red asistencial.</p>
                    <a href="busqueda_avanzada.php" class="btn btn-dark mt-auto mx-auto px-4">Ir al Buscador</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-danger">
                <div class="card-body d-flex flex-column text-center p-4">
                    <h5 class="card-title text-danger mb-3">Estadísticas Clínicas</h5>
                    <p class="card-text text-muted mb-4">Métricas de inasistencia y top 5 de diagnósticos más frecuentes.</p>
                    <a href="estadisticas.php" class="btn btn-danger mt-auto mx-auto px-4">Ver Reportes</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-secondary">
                <div class="card-body d-flex flex-column text-center p-4">
                    <h5 class="card-title text-secondary mb-3">Gestión de Médicos</h5>
                    <p class="card-text text-muted mb-4">Administra el personal clínico, sus especialidades y lugares de atención.</p>
                    <a href="gestion_medicos.php" class="btn btn-secondary mt-auto mx-auto px-4">Administrar Médicos</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-info">
                <div class="card-body d-flex flex-column text-center p-4">
                    <h5 class="card-title text-info mb-3">Gestión de Centros</h5>
                    <p class="card-text text-muted mb-4">Administra las sucursales, clínicas y recintos médicos de la red.</p>
                    <a href="gestion_centros.php" class="btn btn-info text-white mt-auto mx-auto px-4">Administrar Centros</a>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
