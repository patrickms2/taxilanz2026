<?php
require_once 'header.php';
?>

<div class="px-4 py-5 my-5 text-center">
    <h1 class="display-5 fw-bold">Bienvenido a <?php echo htmlspecialchars(getenv('PROJECT_NAME') ?: 'Taxi HRM'); ?></h1>
    <div class="col-lg-6 mx-auto">
        <p class="lead mb-4"><?php echo htmlspecialchars(getenv('PROJECT_DESCRIPTION') ?: 'Una solución completa para la gestión de su negocio de taxis.'); ?></p>
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="drivers.php" class="btn btn-primary btn-lg px-4 gap-3">Gestionar Conductores</a>
            <button type="button" class="btn btn-outline-secondary btn-lg px-4">Ver Reportes</button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-badge"></i> Conductores</h5>
                <p class="card-text">Administre el personal de conducción, incluyendo sus perfiles, licencias y documentación.</p>
                <a href="drivers.php" class="btn btn-primary">Ir a Conductores</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-geo-alt-fill"></i> Mapa en Tiempo Real</h5>
                <p class="card-text">Visualice la ubicación de toda su flota en tiempo real para una gestión eficiente. (Próximamente)</p>
                <a href="#" class="btn btn-secondary disabled">Ir al Mapa</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>