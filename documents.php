<?php
require_once 'db/config.php';

// --- DB Schema Setup ---
try {
    $pdo = db();
    // Create documents table
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}

// --- File Upload Logic ---
$upload_dir = 'uploads/';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $title = $_POST['title'] ?? 'Sin Título';
    $description = $_POST['description'] ?? '';
    $file_name = basename($_FILES['document']['name']);
    $file_tmp = $_FILES['document']['tmp_name'];
    $file_error = $_FILES['document']['error'];

    if ($file_error === UPLOAD_ERR_OK) {
        $safe_file_name = uniqid() . '-' . preg_replace("/[^a-zA-Z0-9._-]/", "", $file_name);
        $file_path = $upload_dir . $safe_file_name;

        if (move_uploaded_file($file_tmp, $file_path)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO documents (title, description, file_name, file_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $description, $file_name, $file_path]);
                $message = '<div class="alert alert-success">Documento subido con éxito.</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error al guardar en la base de datos: ' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Error al mover el fichero subido.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Error en la subida del fichero: ' . $file_error . '</div>';
    }
}

// --- Fetch Documents ---
$documents = [];
try {
    $stmt = $pdo->query("SELECT id, title, description, file_name, file_path, uploaded_at FROM documents ORDER BY uploaded_at DESC");
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message .= '<div class="alert alert-danger">Error al obtener los documentos: ' . $e->getMessage() . '</div>';
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Gestor de Documentos</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-upload"></i> Subir Documento
    </button>
</div>

<?php echo $message; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Título</th>
                        <th>Descripción</th>
                        <th>Fichero</th>
                        <th>Fecha de Subida</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay documentos todavía.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                <td><?php echo htmlspecialchars($doc['description']); ?></td>
                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                <td><?php echo date("d/m/Y H:i", strtotime($doc['uploaded_at'])); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                        <i class="bi bi-download"></i> Descargar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Subir Nuevo Documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="documents.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Título del Documento</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción (Opcional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="document" class="form-label">Seleccionar Fichero</label>
                        <input class="form-control" type="file" id="document" name="document" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Subir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
