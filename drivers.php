<?php
require_once 'db/config.php';
require_once 'header.php';

$message = '';
$message_type = 'success';

// --- DB Schema Migration ---
try {
    $pdo = db();
    
    // Add new columns to taxis table if they don't exist
    $columns = [
        'nombre' => 'VARCHAR(255) NULL',
        'licencia' => 'VARCHAR(100) NULL',
        'municipio' => "ENUM('TIAS', 'TEGUISE', 'YAIZA') NULL",
        'ultima_localizacion_lat' => 'DECIMAL(10, 8) NULL',
        'ultima_localizacion_lng' => 'DECIMAL(11, 8) NULL',
        'id_departamento' => 'INT NULL'
    ];

    foreach ($columns as $column => $type) {
        $stmt = $pdo->query("SHOW COLUMNS FROM taxis LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE taxis ADD COLUMN `$column` $type;");
        }
    }
    
    // Add foreign key for id_departamento separately to avoid issues in loop
    $stmt = $pdo->query("SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'taxis' AND constraint_name = 'taxis_ibfk_1'");
    if($stmt->rowCount() == 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM taxis LIKE 'id_departamento'");
        if ($stmt->rowCount() > 0) {
             $pdo->exec("ALTER TABLE taxis ADD FOREIGN KEY (id_departamento) REFERENCES departamentos(id) ON DELETE SET NULL;");
        }
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_conductor'])) {
            $sql = "INSERT INTO taxis (nombre, licencia, matricula, municipio, id_departamento) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$_POST['nombre'], $_POST['licencia'], $_POST['matricula'], $_POST['municipio'], $_POST['id_departamento'] ?: null]);
            $message = 'Conductor añadido con éxito.';
        } elseif (isset($_POST['edit_conductor'])) {
            $sql = "UPDATE taxis SET nombre=?, licencia=?, matricula=?, municipio=?, id_departamento=? WHERE id=?";
            $pdo->prepare($sql)->execute([$_POST['nombre'], $_POST['licencia'], $_POST['matricula'], $_POST['municipio'], $_POST['id_departamento'] ?: null, $_POST['id']]);
            $message = 'Conductor actualizado con éxito.';
        } elseif (isset($_POST['delete_conductor'])) {
            $sql = "DELETE FROM taxis WHERE id=?";
            $pdo->prepare($sql)->execute([$_POST['id']]);
            $message = 'Conductor eliminado con éxito.';
        }
    }

    // Fetch data for table
    $conductores = $pdo->query("SELECT 
            t.id, t.nombre, t.licencia, t.matricula, t.municipio, t.ultima_localizacion_lat, t.ultima_localizacion_lng,
            (SELECT COUNT(*) FROM documents WHERE id_conductor = t.id) as num_documentos,
            (SELECT COUNT(*) FROM citas WHERE id_conductor = t.id) as num_citas,
            (SELECT COUNT(*) FROM consultas WHERE id_conductor = t.id) as num_consultas,
            (SELECT COUNT(*) FROM taxi_locations WHERE taxi_id = t.id) as num_ubicaciones
        FROM taxis t
        GROUP BY t.id
        ORDER BY t.nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = 'Error de base de datos: ' . $e->getMessage();
    $message_type = 'danger';
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Conductores</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Conductores</li>
    </ol>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Listado de Conductores
            <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#formConductorModal" onclick="prepareAddForm()">
                <i class="fas fa-plus"></i> Añadir Conductor
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="conductoresTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Licencia</th>
                            <th>Matrícula</th>
                            <th>Municipio</th>
                            <th>Ubicación</th>
                            <th>Docs</th>
                            <th>Citas</th>
                            <th>Consultas</th>
                            <th>Ubics.</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conductores as $conductor): ?>
                        <tr>
                            <td><?php echo $conductor['id']; ?></td>
                            <td><?php echo htmlspecialchars($conductor['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($conductor['licencia']); ?></td>
                            <td><?php echo htmlspecialchars($conductor['matricula']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($conductor['municipio']); ?></span></td>
                            <td>
                                <?php if($conductor['ultima_localizacion_lat']): ?>
                                <small><?php echo $conductor['ultima_localizacion_lat'] . ', ' . $conductor['ultima_localizacion_lng']; ?></small>
                                <?php else: echo 'N/A'; endif; ?>
                            </td>
                            <td><span class="badge bg-info"><?php echo $conductor['num_documentos']; ?></span></td>
                            <td><span class="badge bg-info"><?php echo $conductor['num_citas']; ?></span></td>
                            <td><span class="badge bg-info"><?php echo $conductor['num_consultas']; ?></span></td>
                            <td><span class="badge bg-info"><?php echo $conductor['num_ubicaciones']; ?></span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick='prepareEditForm(<?php echo json_encode($conductor); ?>)'><i class="fas fa-edit"></i></button>
                                    <a href="documents.php?id_conductor=<?php echo $conductor['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Añadir Documento"><i class="fas fa-file-medical"></i></a>
                                    <a href="citas.php?id_conductor=<?php echo $conductor['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Añadir Cita"><i class="fas fa-calendar-plus"></i></a>
                                    <a href="consultas.php?id_conductor=<?php echo $conductor['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Añadir Consulta"><i class="fas fa-question-circle"></i></a>
                                    <a href="localizacion.php?id_taxi=<?php echo $conductor['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Añadir Ubicación"><i class="fas fa-map-marker-alt"></i></a>
                                    <button class="btn btn-sm btn-outline-danger" onclick='prepareDeleteForm(<?php echo $conductor['id']; ?>)'><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Conductor Modal -->
<div class="modal fade" id="formConductorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="conductorForm" action="drivers.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="formModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="conductor_id">
                    <div id="formMethod"></div>

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre" id="conductor_nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Licencia</label>
                        <input type="text" class="form-control" name="licencia" id="conductor_licencia">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Matrícula</label>
                        <input type="text" class="form-control" name="matricula" id="conductor_matricula" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Municipio</label>
                        <select class="form-select" name="municipio" id="conductor_municipio">
                            <option value="">Seleccionar...</option>
                            <option value="TIAS">Tías</option>
                            <option value="TEGUISE">Teguise</option>
                            <option value="YAIZA">Yaiza</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Departamento</label>
                        <select class="form-select" name="id_departamento" id="conductor_departamento">
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConductorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="drivers.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que quieres eliminar a este conductor? Esta acción no se puede deshacer.</p>
                    <input type="hidden" name="delete_conductor" value="1">
                    <input type="hidden" name="id" id="delete_conductor_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareAddForm() {
    document.getElementById('conductorForm').reset();
    document.getElementById('formModalLabel').innerText = 'Añadir Conductor';
    document.getElementById('formMethod').innerHTML = '<input type="hidden" name="add_conductor" value="1">';
    new bootstrap.Modal(document.getElementById('formConductorModal')).show();
}

function prepareEditForm(conductor) {
    document.getElementById('conductorForm').reset();
    document.getElementById('formModalLabel').innerText = 'Editar Conductor';
    document.getElementById('formMethod').innerHTML = '<input type="hidden" name="edit_conductor" value="1">';
    
    document.getElementById('conductor_id').value = conductor.id;
    document.getElementById('conductor_nombre').value = conductor.nombre;
    document.getElementById('conductor_licencia').value = conductor.licencia;
    document.getElementById('conductor_matricula').value = conductor.matricula;
    document.getElementById('conductor_municipio').value = conductor.municipio;
    document.getElementById('conductor_departamento').value = conductor.id_departamento;

    new bootstrap.Modal(document.getElementById('formConductorModal')).show();
}

function prepareDeleteForm(id) {
    document.getElementById('delete_conductor_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteConductorModal')).show();
}
</script>

<?php
require_once 'footer.php';
?>
