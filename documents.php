<?php
require_once 'db/config.php';

// --- DB Schema Setup ---
try {
    $pdo = db();
    // Create documents table with new fields
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_conductor INT,
        tipo_documento VARCHAR(255),
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_conductor) REFERENCES taxis(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add columns if they don't exist (for existing tables)
    $check_columns = $pdo->query("SHOW COLUMNS FROM documents LIKE 'id_conductor'");
    if ($check_columns->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN id_conductor INT, ADD FOREIGN KEY (id_conductor) REFERENCES taxis(id) ON DELETE SET NULL;");
    }
    $check_columns = $pdo->query("SHOW COLUMNS FROM documents LIKE 'tipo_documento'");
    if ($check_columns->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN tipo_documento VARCHAR(255);");
    }

} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}

// --- File Upload & Update Logic ---
$upload_dir = 'uploads/';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    // Handle Update
    if (isset($_POST['update_document'])) {
        $id = $_POST['document_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $id_conductor = $_POST['id_conductor'];
        $tipo_documento = $_POST['tipo_documento'];

        try {
            $stmt = $pdo->prepare("UPDATE documents SET title = ?, description = ?, id_conductor = ?, tipo_documento = ? WHERE id = ?");
            $stmt->execute([$title, $description, $id_conductor, $tipo_documento, $id]);
            $message = '<div class="alert alert-success">Documento actualizado con éxito.</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Error al actualizar: ' . $e->getMessage() . '</div>';
        }
    }
    // Handle Upload
    elseif (isset($_FILES['document'])) {
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $title = $_POST['title'] ?? 'Sin Título';
        $description = $_POST['description'] ?? '';
        $id_conductor = $_POST['id_conductor'] ?? null;
        $tipo_documento = $_POST['tipo_documento'] ?? '';
        $file_name = basename($_FILES['document']['name']);
        $file_tmp = $_FILES['document']['tmp_name'];
        $file_error = $_FILES['document']['error'];

        if ($file_error === UPLOAD_ERR_OK) {
            $safe_file_name = uniqid() . '-' . preg_replace("/[^a-zA-Z0-9._-]/", "", $file_name);
            $file_path = $upload_dir . $safe_file_name;

            if (move_uploaded_file($file_tmp, $file_path)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO documents (title, description, id_conductor, tipo_documento, file_name, file_path) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $id_conductor, $tipo_documento, $file_name, $file_path]);
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
}

// --- Fetch Data ---
$documents = [];
$conductores = [];
try {
    $pdo = db();
    
    // Fetch drivers (taxis)
    $conductores = $pdo->query("SELECT id, matricula, modelo FROM taxis ORDER BY matricula")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch documents with driver info
    $sort_column = $_GET['sort'] ?? 'uploaded_at';
    $sort_order = $_GET['order'] ?? 'DESC';
    $valid_columns = ['title', 'tipo_documento', 'conductor', 'uploaded_at'];
    if (!in_array($sort_column, $valid_columns)) {
        $sort_column = 'uploaded_at';
    }

    $sql = "SELECT d.id, d.title, d.description, d.file_name, d.file_path, d.uploaded_at, d.tipo_documento, t.matricula as conductor, d.id_conductor
            FROM documents d
            LEFT JOIN taxis t ON d.id_conductor = t.id
            ORDER BY $sort_column $sort_order";
    
    $stmt = $pdo->query($sql);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message .= '<div class="alert alert-danger">Error al obtener los datos: ' . $e->getMessage() . '</div>';
}

function get_sort_link($column, $display) {
    $sort_column = $_GET['sort'] ?? 'uploaded_at';
    $sort_order = $_GET['order'] ?? 'DESC';
    $order = ($sort_column == $column && $sort_order == 'ASC') ? 'DESC' : 'ASC';
    $icon = $sort_column == $column ? ($sort_order == 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up-square';
    return '<a href="?sort=' . $column . '&order=' . $order . '">' . $display . ' <i class="bi '.$icon.'"></i></a>';
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
                        <th><?php echo get_sort_link('title', 'Título'); ?></th>
                        <th>Descripción</th>
                        <th><?php echo get_sort_link('tipo_documento', 'Tipo'); ?></th>
                        <th><?php echo get_sort_link('conductor', 'Conductor'); ?></th>
                        <th>Fichero</th>
                        <th><?php echo get_sort_link('uploaded_at', 'Fecha de Subida'); ?></th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay documentos todavía.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                <td><?php echo htmlspecialchars($doc['description']); ?></td>
                                <td><?php echo htmlspecialchars($doc['tipo_documento']); ?></td>
                                <td><?php echo htmlspecialchars($doc['conductor'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                <td><?php echo date("d/m/Y H:i", strtotime($doc['uploaded_at'])); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-btn"
                                            data-id="<?php echo $doc['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($doc['title']); ?>"
                                            data-description="<?php echo htmlspecialchars($doc['description']); ?>"
                                            data-id-conductor="<?php echo $doc['id_conductor']; ?>"
                                            data-tipo-documento="<?php echo htmlspecialchars($doc['tipo_documento']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
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
                        <label for="title" class="form-label">Título</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="id_conductor_upload" class="form-label">Conductor</label>
                        <select class="form-select" id="id_conductor_upload" name="id_conductor">
                            <option value="">Sin asignar</option>
                            <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['id']; ?>"><?php echo htmlspecialchars($conductor['matricula']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                        <input type="text" class="form-control" id="tipo_documento" name="tipo_documento" placeholder="Ej: ITV, Seguro, DNI...">
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Editar Documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="documents.php" method="post">
                <input type="hidden" name="update_document" value="1">
                <input type="hidden" id="edit_document_id" name="document_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Título</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_id_conductor" class="form-label">Conductor</label>
                        <select class="form-select" id="edit_id_conductor" name="id_conductor">
                            <option value="">Sin asignar</option>
                            <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['id']; ?>"><?php echo htmlspecialchars($conductor['matricula']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tipo_documento" class="form-label">Tipo de Documento</label>
                        <input type="text" class="form-control" id="edit_tipo_documento" name="tipo_documento">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const modal = this;
        
        modal.querySelector('#edit_document_id').value = button.dataset.id;
        modal.querySelector('#edit_title').value = button.dataset.title;
        modal.querySelector('#edit_description').value = button.dataset.description;
        modal.querySelector('#edit_id_conductor').value = button.dataset.idConductor;
        modal.querySelector('#edit_tipo_documento').value = button.dataset.tipoDocumento;
    });
});
</script>

<?php include 'footer.php'; ?>