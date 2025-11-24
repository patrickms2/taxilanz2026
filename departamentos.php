<?php
require_once 'db/config.php';

// --- DB Schema and Logic ---
try {
    $pdo = db();
    // Create departments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS departamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $message = '';
    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new
        if (isset($_POST['add_departamento'])) {
            $nombre = trim($_POST['nombre']);
            if (!empty($nombre)) {
                $stmt = $pdo->prepare("INSERT INTO departamentos (nombre) VALUES (?)");
                $stmt->execute([$nombre]);
                $message = '<div class="alert alert-success">Departamento añadido.</div>';
            } else {
                $message = '<div class="alert alert-danger">El nombre no puede estar vacío.</div>';
            }
        }
        // Update
        elseif (isset($_POST['update_departamento'])) {
            $id = $_POST['id'];
            $nombre = trim($_POST['nombre']);
            if (!empty($nombre)) {
                $stmt = $pdo->prepare("UPDATE departamentos SET nombre = ? WHERE id = ?");
                $stmt->execute([$nombre, $id]);
                $message = '<div class="alert alert-success">Departamento actualizado.</div>';
            } else {
                $message = '<div class="alert alert-danger">El nombre no puede estar vacío.</div>';
            }
        }
        // Delete
        elseif (isset($_POST['delete_departamento'])) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM departamentos WHERE id = ?");
            $stmt->execute([$id]);
            $message = '<div class="alert alert-warning">Departamento eliminado.</div>';
        }
    }

    // Fetch all departments
    $departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Departamentos</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Departamentos</li>
    </ol>

    <?php echo $message; ?>

    <div class="row">
        <!-- Add Department Form -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-plus me-1"></i>
                    Añadir Nuevo Departamento
                </div>
                <div class="card-body">
                    <form action="departamentos.php" method="POST">
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputNombre" type="text" name="nombre" placeholder="Nombre del departamento" required />
                            <label for="inputNombre">Nombre del Departamento</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="add_departamento" class="btn btn-primary">Añadir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Departments List -->
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-building me-1"></i>
                    Listado de Departamentos
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departamentos)): ?>
                                    <tr><td colspan="2" class="text-center">No hay departamentos.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($departamentos as $depto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($depto['nombre']); ?></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal-<?php echo $depto['id']; ?>">
                                                    <i class="bi bi-pencil"></i> Editar
                                                </button>
                                                <form action="departamentos.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este departamento?');">
                                                    <input type="hidden" name="id" value="<?php echo $depto['id']; ?>">
                                                    <button type="submit" name="delete_departamento" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i> Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal-<?php echo $depto['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Editar Departamento</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="departamentos.php" method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?php echo $depto['id']; ?>">
                                                            <div class="form-floating">
                                                                <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($depto['nombre']); ?>" required>
                                                                <label>Nombre</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                            <button type="submit" name="update_departamento" class="btn btn-primary">Guardar Cambios</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>