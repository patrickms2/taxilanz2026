<?php
require_once 'db/config.php';
require_once 'header.php';

try {
    $pdo = db();
    
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS consultas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_taxista INT NOT NULL,
        id_departamento INT NOT NULL,
        resultado TEXT,
        fecha_consulta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_taxista) REFERENCES drivers(id),
        FOREIGN KEY (id_departamento) REFERENCES departamentos(id)
    )");

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consulta'])) {
        $id_taxista = $_POST['id_taxista'];
        $id_departamento = $_POST['id_departamento'];
        $resultado = trim($_POST['resultado']);

        if (!empty($id_taxista) && !empty($id_departamento)) {
            $stmt = $pdo->prepare("INSERT INTO consultas (id_taxista, id_departamento, resultado) VALUES (?, ?, ?)");
            $stmt->execute([$id_taxista, $id_departamento, $resultado]);
            echo '<div class="alert alert-success" role="alert">Consulta añadida con éxito.</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">Taxista y departamento son obligatorios.</div>';
        }
    }

    // Fetch all drivers for dropdown
    $drivers_stmt = $pdo->query("SELECT id, name FROM drivers ORDER BY name");
    $drivers = $drivers_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all departments for dropdown
    $departamentos_stmt = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre");
    $departamentos = $departamentos_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all consultas
    $stmt = $pdo->query("SELECT c.id, d.name as taxista_nombre, dep.nombre as departamento_nombre, c.resultado, c.fecha_consulta 
                           FROM consultas c
                           JOIN drivers d ON c.id_taxista = d.id
                           JOIN departamentos dep ON c.id_departamento = dep.id
                           ORDER BY c.fecha_consulta DESC");
    $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Consultas</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Consultas</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Añadir Nueva Consulta
        </div>
        <div class="card-body">
            <form action="consultas.php" method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" id="selectTaxista" name="id_taxista" required>
                                <option value="">Seleccione un taxista</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="selectTaxista">Taxista</label>
                        </div>
                    </div>
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
                </div>
                <div class="form-floating mb-3">
                    <textarea class="form-control" id="inputResultado" name="resultado" placeholder="Resultado de la consulta" style="height: 100px;"></textarea>
                    <label for="inputResultado">Resultado</label>
                </div>
                <div class="mt-4 mb-0">
                    <div class="d-grid">
                        <button type="submit" name="add_consulta" class="btn btn-primary btn-block">Añadir Consulta</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Lista de Consultas
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Taxista</th>
                        <th>Departamento</th>
                        <th>Resultado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($consultas)):
                        foreach ($consultas as $consulta):
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($consulta['id']); ?></td>
                                <td><?php echo htmlspecialchars($consulta['taxista_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($consulta['departamento_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($consulta['resultado']); ?></td>
                                <td><?php echo htmlspecialchars($consulta['fecha_consulta']); ?></td>
                            </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay consultas registradas.</td>
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
