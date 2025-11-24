<?php
require_once 'db/config.php';
require_once 'header.php';

try {
    $pdo = db();
    
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS departamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        color VARCHAR(7) DEFAULT '#FFFFFF',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
        $nombre = trim($_POST['nombre']);
        $color = trim($_POST['color']);

        if (!empty($nombre)) {
            $stmt = $pdo->prepare("INSERT INTO departamentos (nombre, color) VALUES (?, ?)");
            $stmt->execute([$nombre, $color]);
            echo '<div class="alert alert-success" role="alert">Departamento añadido con éxito.</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">El nombre del departamento es obligatorio.</div>';
        }
    }

    // Fetch all departments
    $stmt = $pdo->query("SELECT id, nombre, color, created_at FROM departamentos ORDER BY created_at DESC");
    $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Departamentos</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Departamentos</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Añadir Nuevo Departamento
        </div>
        <div class="card-body">
            <form action="departamentos.php" method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-floating mb-3 mb-md-0">
                            <input class="form-control" id="inputNombre" type="text" name="nombre" placeholder="Nombre del departamento" required />
                            <label for="inputNombre">Nombre del departamento</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                           <input class="form-control" id="inputColor" type="color" name="color" value="#FFFFFF" />
                           <label for="inputColor">Color</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4 mb-0">
                    <div class="d-grid">
                        <button type="submit" name="add_department" class="btn btn-primary btn-block">Añadir Departamento</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Lista de Departamentos
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Color</th>
                        <th>Fecha de Creación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($departamentos)): ?>
                        <?php foreach ($departamentos as $departamento): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($departamento['id']); ?></td>
                                <td><?php echo htmlspecialchars($departamento['nombre']); ?></td>
                                <td style="background-color: <?php echo htmlspecialchars($departamento['color']); ?>;"><?php echo htmlspecialchars($departamento['color']); ?></td>
                                <td><?php echo htmlspecialchars($departamento['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay departamentos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
