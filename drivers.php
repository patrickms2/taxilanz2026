<?php
require_once 'db/config.php';
require_once 'header.php';

$message = '';
$message_type = '';

// --- DB Schema and Data Fetching ---
try {
    $pdo = db();
    // Add department FK to taxis table
    $check_column = $pdo->query("SHOW COLUMNS FROM taxis LIKE 'id_departamento'");
    if ($check_column->rowCount() == 0) {
        $pdo->exec("ALTER TABLE taxis ADD COLUMN id_departamento INT, ADD FOREIGN KEY (id_departamento) REFERENCES departamentos(id) ON DELETE SET NULL;");
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new taxi
        if (isset($_POST['add_taxi'])) {
            $matricula = trim($_POST['matricula']);
            $modelo = trim($_POST['modelo']);
            $id_departamento = $_POST['id_departamento'] ?: null;

            if (!empty($matricula)) {
                $sql = "INSERT INTO taxis (matricula, modelo, id_departamento) VALUES (?, ?, ?)";
                $pdo->prepare($sql)->execute([$matricula, $modelo, $id_departamento]);
                $message = 'Taxi/Conductor añadido exitosamente.';
                $message_type = 'success';
            } else {
                $message = 'La matrícula es obligatoria.';
                $message_type = 'warning';
            }
        }
    }

    // Fetch departments for dropdown
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all taxis with department info
    $taxis = $pdo->query(
        "SELECT t.*, d.nombre as departamento_nombre 
         FROM taxis t 
         LEFT JOIN departamentos d ON t.id_departamento = d.id 
         ORDER BY t.matricula ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = 'Error de base de datos: ' . $e->getMessage();
    $message_type = 'danger';
}

?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Gestión de Taxis/Conductores</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaxiModal">
            <i class="bi bi-plus-circle"></i> Añadir Taxi/Conductor
        </button>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Matrícula</th>
                            <th>Modelo</th>
                            <th>Departamento</th>
                            <th>Fecha de Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($taxis)): ?>
                            <tr><td colspan="4" class="text-center">No hay taxis registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($taxis as $taxi): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($taxi['matricula']); ?></td>
                                    <td><?php echo htmlspecialchars($taxi['modelo']); ?></td>
                                    <td><?php echo htmlspecialchars($taxi['departamento_nombre'] ?? 'N/A'); ?></td>
                                    <td><?php echo date("d/m/Y H:i", strtotime($taxi['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Taxi Modal -->
<div class="modal fade" id="addTaxiModal" tabindex="-1" aria-labelledby="addTaxiModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="drivers.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTaxiModalLabel">Añadir Nuevo Taxi/Conductor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_taxi" value="1">
                    <div class="mb-3">
                        <label for="matricula" class="form-label">Matrícula</label>
                        <input type="text" class="form-control" id="matricula" name="matricula" required>
                    </div>
                    <div class="mb-3">
                        <label for="modelo" class="form-label">Modelo</label>
                        <input type="text" class="form-control" id="modelo" name="modelo">
                    </div>
                    <div class="mb-3">
                        <label for="id_departamento" class="form-label">Departamento</label>
                        <select class="form-select" id="id_departamento" name="id_departamento">
                            <option value="">Sin asignar</option>
                            <?php foreach ($departamentos as $depto): ?>
                                <option value="<?php echo $depto['id']; ?>"><?php echo htmlspecialchars($depto['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>