<?php
require_once 'db/config.php';
require_once 'header.php';

$message = '';
$message_type = '';

// --- DB Schema and Data Fetching ---
try {
    $pdo = db();
    
    // Main schema definition
    $pdo->exec("CREATE TABLE IF NOT EXISTS citas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        start_event DATETIME NOT NULL,
        end_event DATETIME NOT NULL,
        id_departamento INT NOT NULL,
        lugar VARCHAR(255),
        usuarios TEXT,
        estado VARCHAR(50) DEFAULT 'Pendiente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_departamento) REFERENCES departamentos(id) ON DELETE CASCADE
    )");

    // Add 'title' column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM citas LIKE 'title'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE citas ADD COLUMN title VARCHAR(255) NOT NULL AFTER id;");
    }

    // Add 'id_conductor' column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM citas LIKE 'id_conductor'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE citas ADD COLUMN id_conductor INT NULL AFTER id_departamento, ADD FOREIGN KEY (id_conductor) REFERENCES taxis(id) ON DELETE SET NULL;");
    }

    // Fetch departments and conductors
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $conductores = $pdo->query("SELECT id, matricula FROM taxis ORDER BY matricula")->fetchAll(PDO::FETCH_ASSOC);

    // Get selected filters
    $selected_depto_id = $_GET['id_departamento'] ?? null;
    $selected_conductor_id = $_GET['id_conductor'] ?? null;

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cita'])) {
        $title = $_POST['title'];
        $fecha = $_POST['fecha'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];
        $id_departamento = $_POST['id_departamento'];
        $id_conductor = $_POST['id_conductor'] ?: null;
        $lugar = trim($_POST['lugar']);
        $usuarios = trim($_POST['usuarios']);
        $estado = $_POST['estado'];

        if (!empty($title) && !empty($fecha) && !empty($hora_inicio) && !empty($hora_fin) && !empty($id_departamento)) {
            $start_event = $fecha . ' ' . $hora_inicio;
            $end_event = $fecha . ' ' . $hora_fin;
            
            $stmt = $pdo->prepare("INSERT INTO citas (title, start_event, end_event, id_departamento, id_conductor, lugar, usuarios, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $start_event, $end_event, $id_departamento, $id_conductor, $lugar, $usuarios, $estado]);
            $message = '<div class="alert alert-success">Cita añadida con éxito.</div>';
        } else {
            $message = '<div class="alert alert-danger">Título, fecha, horas y departamento son obligatorios.</div>';
        }
    }

    // Fetch citas for the calendar and list
    $events = [];
    $citas_list = [];
    
    $sql = "SELECT c.id, c.title, c.start_event as start, c.end_event as end, c.estado, c.lugar, c.usuarios, c.created_at, t.matricula as conductor_nombre
            FROM citas c
            LEFT JOIN taxis t ON c.id_conductor = t.id
            WHERE 1=1";
    $params = [];
    if ($selected_depto_id) {
        $sql .= " AND c.id_departamento = ?";
        $params[] = $selected_depto_id;
    }
    if ($selected_conductor_id) {
        $sql .= " AND c.id_conductor = ?";
        $params[] = $selected_conductor_id;
    }
    $sql .= " ORDER BY c.start_event DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $citas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for FullCalendar
    foreach($citas_list as $cita) {
        $events[] = [
            'title' => $cita['title'],
            'start' => $cita['start'],
            'end' => $cita['end'],
            'extendedProps' => [
                'estado' => $cita['estado'],
                'conductor' => $cita['conductor_nombre'] ?? 'N/A'
            ]
        ];
    }

} catch (PDOException $e) {
    $message = "Error de base de datos: " . $e->getMessage();
    $message_type = 'danger';
}
?>

<!-- FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />

<div class="container-fluid px-4">
    <h1 class="mt-4">Calendario de Citas</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Citas</li>
    </ol>

    <?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-filter me-1"></i>Filtros</div>
        <div class="card-body">
            <form action="citas.php" method="GET" id="filter-form">
                <div class="row">
                    <div class="col-md-6">
                        <select class="form-select" name="id_departamento" onchange="this.form.submit()">
                            <option value="">Seleccione un departamento</option>
                            <?php foreach ($departamentos as $depto): ?>
                                <option value="<?php echo $depto['id']; ?>" <?php echo ($depto['id'] == $selected_depto_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($depto['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                         <select class="form-select" name="id_conductor" onchange="this.form.submit()">
                            <option value="">Todos los conductores</option>
                            <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['id']; ?>" <?php echo ($conductor['id'] == $selected_conductor_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($conductor['matricula']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_depto_id || $selected_conductor_id): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div id='calendar'></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-list me-1"></i>Listado de Citas</div>
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>Título</th><th>Conductor</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Lugar</th><th>Usuarios</th></tr></thead>
                    <tbody>
                        <?php if(empty($citas_list)): ?>
                            <tr><td colspan="7" class="text-center">No hay citas para los filtros seleccionados.</td></tr>
                        <?php else: foreach($citas_list as $cita): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cita['title']); ?></td>
                                <td><?php echo htmlspecialchars($cita['conductor_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($cita['start'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($cita['end'])); ?></td>
                                <td><?php echo htmlspecialchars($cita['estado']); ?></td>
                                <td><?php echo htmlspecialchars($cita['lugar']); ?></td>
                                <td><?php echo htmlspecialchars($cita['usuarios']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Por favor, seleccione un departamento o conductor para ver el calendario y las citas.</div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-plus me-1"></i>Añadir Nueva Cita</div>
        <div class="card-body">
            <form action="citas.php?<?php echo http_build_query($_GET); ?>" method="POST">
                 <div class="form-floating mb-3">
                    <input class="form-control" type="text" name="title" placeholder="Título" required />
                    <label>Título de la cita</label>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" type="date" name="fecha" required /><label>Fecha</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" type="time" name="hora_inicio" required /><label>Hora Inicio</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" type="time" name="hora_fin" required /><label>Hora Fin</label></div></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><div class="form-floating"><select class="form-select" name="id_departamento" required>
                        <option value="">Seleccione departamento</option>
                        <?php foreach ($departamentos as $depto): ?>
                            <option value="<?php echo $depto['id']; ?>" <?php echo ($depto['id'] == $selected_depto_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($depto['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select><label>Departamento</label></div></div>
                    <div class="col-md-6"><div class="form-floating"><select class="form-select" name="id_conductor">
                        <option value="">Seleccione un conductor (Opcional)</option>
                        <?php foreach ($conductores as $conductor): ?>
                            <option value="<?php echo $conductor['id']; ?>" <?php echo ($conductor['id'] == $selected_conductor_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($conductor['matricula']); ?></option>
                        <?php endforeach; ?>
                    </select><label>Conductor</label></div></div>
                </div>
                <div class="form-floating mb-3">
                    <input class="form-control" type="text" name="lugar" placeholder="Lugar" />
                    <label>Lugar</label>
                </div>
                <div class="form-floating mb-3">
                    <textarea class="form-control" name="usuarios" placeholder="Usuarios" style="height: 80px;"></textarea>
                    <label>Usuarios</label>
                </div>
                 <div class="form-floating mb-3"><select class="form-select" name="estado">
                    <option value="Pendiente">Pendiente</option>
                    <option value="Confirmada">Confirmada</option>
                    <option value="Cancelada">Cancelada</option>
                </select><label>Estado</label></div>
                <div class="d-grid"><button type="submit" name="add_cita" class="btn btn-primary">Añadir Cita</button></div>
            </form>
        </div>
    </div>
</div>

<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($selected_depto_id || $selected_conductor_id): ?>
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: <?php echo json_encode($events); ?>
    });
    calendar.render();
    <?php endif; ?>
});
</script>

<?php
require_once 'footer.php';
?>