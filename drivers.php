<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db/config.php';
require_once 'header.php';

$message = '';
$message_type = '';

// DDL to create table
try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        license_number VARCHAR(50) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
} catch (PDOException $e) {
    // die("DB error: " . $e->getMessage()); // Avoid dying on production
    $message = 'Error de conexión con la base de datos.';
    $message_type = 'danger';
}

// Handle POST request to add a new driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
    $name = trim($_POST['name']);
    $license_number = trim($_POST['license_number']);
    $phone = trim($_POST['phone']);

    if (!empty($name) && !empty($license_number) && !empty($phone)) {
        try {
            $sql = "INSERT INTO drivers (name, license_number, phone) VALUES (:name, :license_number, :phone)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':license_number' => $license_number,
                ':phone' => $phone
            ]);
            $message = 'Conductor añadido exitosamente.';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error al añadir conductor: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'Por favor, complete todos los campos.';
        $message_type = 'warning';
    }
}

// Fetch all drivers
$drivers = [];
if(isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT id, name, license_number, phone, created_at FROM drivers ORDER BY id DESC");
        $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = 'Error al obtener los conductores: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Gestión de Conductores</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDriverModal">
        <i class="bi bi-plus-circle"></i> Añadir Conductor
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
                        <th scope="col">ID</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Nº Licencia</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Fecha de Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay conductores registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($driver['id']); ?></td>
                                <td><?php echo htmlspecialchars($driver['name']); ?></td>
                                <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                                <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                                <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($driver['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addDriverModal" tabindex="-1" aria-labelledby="addDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="drivers.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDriverModalLabel">Añadir Nuevo Conductor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_driver" value="1">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="license_number" class="form-label">Número de Licencia</label>
                        <input type="text" class="form-control" id="license_number" name="license_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar Conductor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
