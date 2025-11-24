<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db/config.php';
require_once 'header.php';

$message = '';
$message_type = '';

// --- DB Schema and Data Fetching ---
try {
    $pdo = db();
    // Add department FK to appointment_slots table
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_departamento INT NOT NULL,
        day_of_week TINYINT NOT NULL, -- 1 for Monday, 7 for Sunday
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_active BOOLEAN DEFAULT true,
        UNIQUE KEY (id_departamento, day_of_week, start_time, end_time),
        FOREIGN KEY (id_departamento) REFERENCES departamentos(id) ON DELETE CASCADE
    );");

    // Fetch departments
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

    // Get selected department (from GET param or first available)
    $selected_depto_id = $_GET['id_departamento'] ?? ($departamentos[0]['id'] ?? null);

} catch (PDOException $e) {
    $message = 'Error de conexión con la base de datos: ' . $e->getMessage();
    $message_type = 'danger';
}

// Handle POST request to update slots
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo) && isset($_POST['id_departamento'])) {
    $id_departamento_post = $_POST['id_departamento'];
    try {
        // Deactivate all existing slots for the specific department first
        $stmt_deactivate = $pdo->prepare("UPDATE appointment_slots SET is_active = false WHERE id_departamento = ?");
        $stmt_deactivate->execute([$id_departamento_post]);

        if (isset($_POST['slots'])) {
            $slots = $_POST['slots'];
            $stmt_upsert = $pdo->prepare(
                "INSERT INTO appointment_slots (id_departamento, day_of_week, start_time, end_time, is_active) 
                 VALUES (:id_departamento, :day_of_week, :start_time, :end_time, true)
                 ON DUPLICATE KEY UPDATE is_active = true"
            );

            foreach ($slots as $day => $times) {
                foreach ($times as $time_range => $on) {
                    list($start_time, $end_time) = explode('-', $time_range);
                    $stmt_upsert->execute([
                        ':id_departamento' => $id_departamento_post,
                        ':day_of_week' => $day,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time
                    ]);
                }
            }
        }
        $message = 'Horarios actualizados correctamente para el departamento seleccionado.';
        $message_type = 'success';
        $selected_depto_id = $id_departamento_post; // Keep the current department selected

    } catch (PDOException $e) {
        $message = 'Error al actualizar los horarios: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Fetch current active slots for the selected department
$active_slots = [];
if (isset($pdo) && $selected_depto_id) {
    try {
        $stmt = $pdo->prepare("SELECT day_of_week, start_time, end_time FROM appointment_slots WHERE is_active = true AND id_departamento = ? ORDER BY day_of_week, start_time");
        $stmt->execute([$selected_depto_id]);
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $active_slots[$row['day_of_week']][date('H:i', strtotime($row['start_time'])) . '-' . date('H:i', strtotime($row['end_time']))] = true;
        }
    } catch (PDOException $e) {
        $message = 'Error al obtener los horarios: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

$days_of_week = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$time_ranges = [];
for ($h = 8; $h < 20; $h++) {
    $start = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
    $end = str_pad($h + 1, 2, '0', STR_PAD_LEFT) . ':00';
    $time_ranges[] = $start . '-' . $end;
}
?>

<style>
    .schedule-grid { display: grid; grid-template-columns: 120px repeat(<?php echo count($time_ranges); ?>, 1fr); gap: 5px; overflow-x: auto; }
    .grid-header, .day-label, .slot-checkbox { padding: 10px; text-align: center; background-color: #f8f9fa; font-size: 0.8rem; white-space: nowrap; }
    .day-label { font-weight: bold; position: sticky; left: 0; z-index: 1; }
    .slot-checkbox { background-color: #fff; }
</style>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Horarios para Citas</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Horarios</li>
    </ol>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-alt me-1"></i>
            Seleccionar Departamento
        </div>
        <div class="card-body">
            <form action="horarios.php" method="GET" id="depto-select-form">
                <div class="input-group">
                    <select class="form-select" name="id_departamento" onchange="document.getElementById('depto-select-form').submit();">
                        <option value="">Seleccione un departamento</option>
                        <?php foreach ($departamentos as $depto): ?>
                            <option value="<?php echo $depto['id']; ?>" <?php echo ($depto['id'] == $selected_depto_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($depto['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_depto_id): ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Marque los bloques de tiempo disponibles</h5>
            <p class="card-text">Seleccione las franjas horarias disponibles para el departamento seleccionado. Los cambios guardados se aplicarán solo a este departamento.</p>
            <form action="horarios.php?id_departamento=<?php echo $selected_depto_id; ?>" method="POST">
                <input type="hidden" name="id_departamento" value="<?php echo $selected_depto_id; ?>">
                <div class="schedule-grid mb-3">
                    <div class="grid-header day-label">Día</div>
                    <?php foreach ($time_ranges as $range): ?>
                        <div class="grid-header"><?php echo str_replace('-', ' - ', $range); ?></div>
                    <?php endforeach; ?>

                    <?php foreach ($days_of_week as $day_num => $day_name): ?>
                        <div class="day-label"><?php echo $day_name; ?></div>
                        <?php foreach ($time_ranges as $range): ?>
                            <div class="slot-checkbox">
                                <input class="form--input" type="checkbox" 
                                       name="slots[<?php echo $day_num; ?>][<?php echo $range; ?>]" 
                                       <?php echo isset($active_slots[$day_num][$range]) ? 'checked' : ''; ?>>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Guardar Horarios</button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Por favor, seleccione un departamento para ver y gestionar sus horarios.</div>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>