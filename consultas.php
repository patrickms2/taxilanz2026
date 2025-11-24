<?php
require_once 'db/config.php';
require_once 'header.php';

$message = '';
$message_type = '';

// --- DB Schema and Data Fetching ---
try {
    $pdo = db();
    
    // Create/update consultas table
    $pdo->exec("CREATE TABLE IF NOT EXISTS consultas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_conductor INT NOT NULL,
        id_departamento INT NOT NULL,
        asunto VARCHAR(255) NOT NULL,
        resultado TEXT,
        status VARCHAR(50) DEFAULT 'Pendiente',
        fecha_consulta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_conductor) REFERENCES taxis(id) ON DELETE CASCADE,
        FOREIGN KEY (id_departamento) REFERENCES departamentos(id) ON DELETE CASCADE
    )");

    // Fetch departments for dropdowns
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch taxis/conductores for dropdowns
    $conductores = $pdo->query("SELECT id, matricula FROM taxis ORDER BY matricula")->fetchAll(PDO::FETCH_ASSOC);

    // Get selected department for filtering
    $selected_depto_id = $_GET['id_departamento'] ?? null;

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consulta'])) {
        $id_conductor = $_POST['id_conductor'];
        $id_departamento = $_POST['id_departamento'];
        $asunto = trim($_POST['asunto']);
        $resultado = trim($_POST['resultado']);
        $status = $_POST['status'];

        if (!empty($id_conductor) && !empty($id_departamento) && !empty($asunto)) {
            $stmt = $pdo->prepare("INSERT INTO consultas (id_conductor, id_departamento, asunto, resultado, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_conductor, $id_departamento, $asunto, $resultado, $status]);
            $message = '<div class="alert alert-success">Consulta añadida con éxito.</div>';
        } else {
            $message = '<div class="alert alert-danger">Conductor, departamento y asunto son obligatorios.</div>';
        }
    }

    // Fetch consultations
    $filter_status = $_GET['status'] ?? null;
    $sql = "SELECT c.id, t.matricula as conductor_nombre, dep.nombre as departamento_nombre, c.asunto, c.resultado, c.status, c.fecha_consulta 
            FROM consultas c
            JOIN taxis t ON c.id_conductor = t.id
            JOIN departamentos dep ON c.id_departamento = dep.id";
    
    $conditions = [];
    $params = [];
    if ($filter_status) {
        $conditions[] = "c.status = :status";
        $params[':status'] = $filter_status;
    }
    if ($selected_depto_id) {
        $conditions[] = "c.id_departamento = :id_departamento";
        $params[':id_departamento'] = $selected_depto_id;
    }

    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY c.fecha_consulta DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Error de base de datos: " . $e->getMessage();
    $message_type = 'danger';
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Consultas</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Consultas</li>
    </ol>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ? $message_type : 'info'; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-filter me-1"></i>Filtro por Departamento</div>
        <div class="card-body">
            <form action="consultas.php" method="GET" id="depto-select-form">
                <div class="input-group">
                    <select class="form-select" name="id_departamento" onchange="this.form.submit()">
                        <option value="">Todos los departamentos</option>
                        <?php foreach ($departamentos as $depto): ?>
                            <option value="<?php echo $depto['id']; ?>" <?php echo ($depto['id'] == $selected_depto_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($depto['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($filter_status): ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>" />
                    <?php endif; ?>
                    <a href="consultas.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-plus me-1"></i>Añadir Nueva Consulta</div>
        <div class="card-body">
            <form action="consultas.php" method="POST">
                <div class="form-floating mb-3">
                    <input class="form-control" id="inputAsunto" type="text" name="asunto" placeholder="Asunto" required />
                    <label for="inputAsunto">Asunto</label>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" name="id_conductor" required>
                                <option value="">Seleccione un conductor</option>
                                <?php foreach ($conductores as $conductor): ?>
                                    <option value="<?php echo $conductor['id']; ?>"><?php echo htmlspecialchars($conductor['matricula']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label>Conductor</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" name="id_departamento" required>
                                <option value="">Seleccione un departamento</option>
                                <?php foreach ($departamentos as $depto): ?>
                                    <option value="<?php echo $depto['id']; ?>"><?php echo htmlspecialchars($depto['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label>Departamento</label>
                        </div>
                    </div>
                </div>
                <div class="form-floating mb-3">
                    <textarea class="form-control" name="resultado" placeholder="Resultado" style="height: 100px;"></textarea>
                    <label>Resultado</label>
                </div>
                <div class="form-floating mb-3">
                    <select class="form-select" name="status">
                        <option value="Pendiente">Pendiente</option>
                        <option value="En Progreso">En Progreso</option>
                        <option value="Resuelta">Resuelta</option>
                    </select>
                    <label>Estado</label>
                </div>
                <div class="d-grid">
                    <button type="submit" name="add_consulta" class="btn btn-primary">Añadir Consulta</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-table me-1"></i>Lista de Consultas</div>
        <div class="card-body">
            <table class="table table-striped">
                <thead><tr><th>Conductor</th><th>Departamento</th><th>Asunto</th><th>Resultado</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php if (empty($consultas)): ?>
                        <tr><td colspan="6" class="text-center">No hay consultas.</td></tr>
                    <?php else: foreach ($consultas as $consulta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($consulta['conductor_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($consulta['departamento_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($consulta['asunto']); ?></td>
                            <td><?php echo htmlspecialchars($consulta['resultado']); ?></td>
                            <td><?php echo htmlspecialchars($consulta['status']); ?></td>
                            <td><?php echo date("d/m/Y H:i", strtotime($consulta['fecha_consulta'])); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
