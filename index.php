<?php
require_once 'db/config.php';
require_once 'header.php';

// Fetch data for selectors
$conductores = [];
$departamentos = [];
try {
    $pdo = db();
    $conductores = $pdo->query("SELECT id, matricula, nombre FROM taxis ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently fail, selectors will be empty
}
?>

<style>
    .dashboard-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        padding-top: 1rem;
    }
    .role-card {
        background-color: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        padding: 2rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .role-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    .role-card-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid #eee;
        padding-bottom: 1rem;
    }
    .role-card-header i {
        font-size: 1.8rem;
        color: #0d6efd;
    }
    .role-card-header h3 {
        margin: 0;
        font-weight: 600;
        color: #343a40;
    }
    .role-card .form-select {
        margin-bottom: 1rem;
    }
    .action-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .action-list li a {
        display: block;
        padding: 0.75rem 1rem;
        margin-bottom: 0.5rem;
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        text-decoration: none;
        color: #495057;
        font-weight: 500;
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .action-list li a:hover {
        background-color: #e9ecef;
        color: #0d6efd;
    }
    .action-list li a i {
        margin-right: 0.75rem;
        width: 20px;
        text-align: center;
    }
    .hidden-list {
        display: none;
    }
</style>

<div class="container-fluid px-4">
    <h1 class="mt-4">Dashboard Interactivo</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Seleccione un rol para empezar</li>
    </ol>

    <div class="dashboard-container">
        <!-- Administrador Card -->
        <div class="role-card" id="admin-card">
            <div class="role-card-header">
                <i class="bi bi-shield-lock-fill"></i>
                <h3>Administrador</h3>
            </div>
            <ul class="action-list">
                <li><a href="drivers.php"><i class="bi bi-people-fill"></i>Conductores y Taxis</a></li>
                <li><a href="departamentos.php"><i class="bi bi-building"></i>Departamentos</a></li>
                <li><a href="documents.php"><i class="bi bi-file-earmark-text"></i>Documentos</a></li>
                <li><a href="citas.php"><i class="bi bi-calendar-check"></i>Citas</a></li>
                <li><a href="consultas.php"><i class="bi bi-question-circle"></i>Consultas</a></li>
                <li><a href="horarios.php"><i class="bi bi-clock"></i>Horarios de Citas</a></li>
                <li><a href="localizacion.php"><i class="bi bi-pin-map-fill"></i>Localizaciones de Taxis</a></li>
            </ul>
        </div>

        <!-- Conductor Card -->
        <div class="role-card" id="conductor-card">
            <div class="role-card-header">
                <i class="bi bi-person-badge-fill"></i>
                <h3>Conductor</h3>
            </div>
            <select class="form-select" id="conductor-selector">
                <option value="">Seleccione un conductor...</option>
                <?php foreach ($conductores as $conductor): ?>
                    <option value="<?php echo $conductor['id']; ?>"><?php echo htmlspecialchars($conductor['nombre'] . ' (' . $conductor['matricula'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
            <ul class="action-list hidden-list" id="conductor-actions">
                <li><a href="#" data-url="drivers.php?action=edit&id="><i class="bi bi-person-lines-fill"></i>Mi Información</a></li>
                <li><a href="#" data-url="documents.php?id_conductor="><i class="bi bi-file-earmark-text"></i>Mis Documentos</a></li>
                <li><a href="#" data-url="citas.php?id_conductor="><i class="bi bi-calendar-check"></i>Mis Citas</a></li>
                <li><a href="#" data-url="consultas.php?id_conductor="><i class="bi bi-question-circle"></i>Mis Consultas</a></li>
                <li><a href="#" data-url="localizacion.php?action=show_history&id_taxi="><i class="bi bi-clock-history"></i>Mi Historial de Ubicaciones</a></li>
            </ul>
        </div>

        <!-- Departamento Card -->
        <div class="role-card" id="departamento-card">
            <div class="role-card-header">
                <i class="bi bi-diagram-3-fill"></i>
                <h3>Departamento</h3>
            </div>
            <select class="form-select" id="departamento-selector">
                <option value="">Seleccione un departamento...</option>
                <?php foreach ($departamentos as $depto): ?>
                    <option value="<?php echo $depto['id']; ?>"><?php echo htmlspecialchars($depto['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
            <ul class="action-list hidden-list" id="departamento-actions">
                <li><a href="#" data-url="departamentos.php?action=edit&id="><i class="bi bi-pencil-square"></i>Ver/Editar Departamento</a></li>
                <li><a href="#" data-url="citas.php?id_departamento="><i class="bi bi-calendar-check"></i>Citas del Departamento</a></li>
                <li><a href="#" data-url="consultas.php?id_departamento="><i class="bi bi-question-circle"></i>Consultas del Departamento</a></li>
                <li><a href="#" data-url="horarios.php?id_departamento="><i class="bi bi-clock"></i>Horarios del Departamento</a></li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const conductorSelector = document.getElementById('conductor-selector');
    const conductorActions = document.getElementById('conductor-actions');
    const departamentoSelector = document.getElementById('departamento-selector');
    const departamentoActions = document.getElementById('departamento-actions');

    conductorSelector.addEventListener('change', function() {
        const conductorId = this.value;
        if (conductorId) {
            conductorActions.classList.remove('hidden-list');
            conductorActions.querySelectorAll('a').forEach(link => {
                const baseUrl = link.getAttribute('data-url');
                link.href = baseUrl + conductorId;
            });
        } else {
            conductorActions.classList.add('hidden-list');
        }
    });

    departamentoSelector.addEventListener('change', function() {
        const deptoId = this.value;
        if (deptoId) {
            departamentoActions.classList.remove('hidden-list');
            departamentoActions.querySelectorAll('a').forEach(link => {
                const baseUrl = link.getAttribute('data-url');
                link.href = baseUrl + deptoId;
            });
        } else {
            departamentoActions.classList.add('hidden-list');
        }
    });
});
</script>

<?php
require_once 'footer.php';
?>