<?php
require_once 'db/config.php';
require_once 'header.php';

try {
    $pdo = db();
    
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS citas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        id_departamento INT NOT NULL,
        lugar VARCHAR(255),
        usuarios TEXT,
        estado VARCHAR(50) DEFAULT 'Pendiente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_departamento) REFERENCES departamentos(id)
    )");

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cita'])) {
        $fecha = $_POST['fecha'];
        $hora = $_POST['hora'];
        $id_departamento = $_POST['id_departamento'];
        $lugar = trim($_POST['lugar']);
        $usuarios = trim($_POST['usuarios']);
        $estado = $_POST['estado'];

        if (!empty($fecha) && !empty($hora) && !empty($id_departamento)) {
            $stmt = $pdo->prepare("INSERT INTO citas (fecha, hora, id_departamento, lugar, usuarios, estado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fecha, $hora, $id_departamento, $lugar, $usuarios, $estado]);
            echo '<div class="alert alert-success" role="alert">Cita añadida con éxito.</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">Fecha, hora y departamento son obligatorios.</div>';
        }
    }

    // Fetch all departments for dropdown
    $departamentos_stmt = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre");
    $departamentos = $departamentos_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all citas
    $stmt = $pdo->query("SELECT c.id, c.fecha, c.hora, dep.nombre as departamento_nombre, c.lugar, c.usuarios, c.estado, c.created_at 
                           FROM citas c
                           JOIN departamentos dep ON c.id_departamento = dep.id
                           ORDER BY c.fecha DESC, c.hora DESC");
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Citas</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Citas</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Añadir Nueva Cita
        </div>
        <div class="card-body">
            <form action="citas.php" method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputFecha" type="date" name="fecha" required />
                            <label for="inputFecha">Fecha</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputHora" type="time" name="hora" required />
                            <label for="inputHora">Hora</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" id="selectDepartamento" name="id_departamento" required>
                                <option value="">Seleccione un departamento</option>
                                <?php foreach ($departamentos as $departamento): ?>
                                    <option value="<?php echo $departamento['id']; ?>"><?php echo htmlspecialchars($departamento['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="selectDepartamento">Departamento</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputLugar" type="text" name="lugar" placeholder="Lugar" />
                            <label for="inputLugar">Lugar (Lat, Lon)</label>
                        </div>
                    </div>
                </div>
                <div class="form-floating mb-3">
                    <textarea class="form-control" id="inputUsuarios" name="usuarios" placeholder="Usuarios" style="height: 100px;"></textarea>
                    <label for="inputUsuarios">Usuarios</label>
                </div>
                 <div class="form-floating mb-3">
                    <select class="form-select" id="selectEstado" name="estado">
                        <option value="Pendiente">Pendiente</option>
                        <option value="Confirmada">Confirmada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                    <label for="selectEstado">Estado</label>
                </div>
                <div class="mt-4 mb-0">
                    <div class="d-grid">
                        <button type="submit" name="add_cita" class="btn btn-primary btn-block">Añadir Cita</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Lista de Citas
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha y Hora</th>
                        <th>Departamento</th>
                        <th>Lugar</th>
                        <th>Usuarios</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($citas)):
                        foreach ($citas as $cita):
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cita['id']); ?></td>
                                <td><?php echo htmlspecialchars($cita['fecha'] . ' ' . $cita['hora']); ?></td>
                                <td><?php echo htmlspecialchars($cita['departamento_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($cita['lugar']); ?></td>
                                <td><?php echo htmlspecialchars($cita['usuarios']); ?></td>
                                <td><?php echo htmlspecialchars($cita['estado']); ?></td>
                            </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay citas registradas.</td>
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
