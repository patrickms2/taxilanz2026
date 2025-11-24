<?php
require_once 'db/config.php';
require_once 'header.php';

try {
    $pdo = db();
    
    // Create taxis table if it doesn't exist (as a dependency)
    $pdo->exec("CREATE TABLE IF NOT EXISTS taxis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        matricula VARCHAR(255) NOT NULL UNIQUE,
        modelo VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create localizacion_taxis table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS localizacion_taxis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_taxi INT NOT NULL,
        latitud DECIMAL(10, 8) NOT NULL,
        longitud DECIMAL(11, 8) NOT NULL,
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_taxi) REFERENCES taxis(id)
    )");

    // Handle form submission to add a new taxi for simplicity
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_taxi'])) {
        $matricula = trim($_POST['matricula']);
        $modelo = trim($_POST['modelo']);
        if(!empty($matricula)) {
            $stmt = $pdo->prepare("INSERT INTO taxis (matricula, modelo) VALUES (?, ?) ON DUPLICATE KEY UPDATE modelo=VALUES(modelo)");
            $stmt->execute([$matricula, $modelo]);
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_localizacion'])) {
        $id_taxi = $_POST['id_taxi'];
        $latitud = $_POST['latitud'];
        $longitud = $_POST['longitud'];

        if (!empty($id_taxi) && is_numeric($latitud) && is_numeric($longitud)) {
            // Check if a location for this taxi already exists
            $stmt_check = $pdo->prepare("SELECT id FROM localizacion_taxis WHERE id_taxi = ?");
            $stmt_check->execute([$id_taxi]);
            $existing_location = $stmt_check->fetch();

            if ($existing_location) {
                // Update existing location
                $stmt = $pdo->prepare("UPDATE localizacion_taxis SET latitud = ?, longitud = ? WHERE id_taxi = ?");
                $stmt->execute([$latitud, $longitud, $id_taxi]);
            } else {
                // Insert new location
                $stmt = $pdo->prepare("INSERT INTO localizacion_taxis (id_taxi, latitud, longitud) VALUES (?, ?, ?)");
                $stmt->execute([$id_taxi, $latitud, $longitud]);
            }
            echo '<div class="alert alert-success" role="alert">Localización actualizada con éxito.</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">Todos los campos son obligatorios y la latitud/longitud deben ser números.</div>';
        }
    }

    // Fetch all taxis for dropdown
    $taxis_stmt = $pdo->query("SELECT id, matricula, modelo FROM taxis ORDER BY matricula");
    $taxis = $taxis_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />

<div class="container-fluid px-4">
    <h1 class="mt-4">Localización de Taxis en Tiempo Real</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Localización</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-map-marked-alt me-1"></i>
            Mapa de Taxis
        </div>
        <div class="card-body">
            <div id="map" style="height: 500px;"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-taxi me-1"></i>
                    Añadir o Actualizar Localización
                </div>
                <div class="card-body">
                    <form action="localizacion.php" method="POST">
                        <div class="form-floating mb-3">
                            <select class="form-select" id="selectTaxi" name="id_taxi" required>
                                <option value="">Seleccione un taxi</option>
                                <?php foreach ($taxis as $taxi): ?>
                                    <option value="<?php echo $taxi['id']; ?>"><?php echo htmlspecialchars($taxi['matricula'] . ' - ' . $taxi['modelo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="selectTaxi">Taxi</label>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="inputLatitud" type="text" name="latitud" placeholder="Latitud" required />
                                    <label for="inputLatitud">Latitud</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="inputLongitud" type="text" name="longitud" placeholder="Longitud" required />
                                    <label for="inputLongitud">Longitud</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 mb-0">
                            <div class="d-grid">
                                <button type="submit" name="add_localizacion" class="btn btn-primary btn-block">Actualizar Localización</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
             <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-plus me-1"></i>
                    Añadir Nuevo Taxi (para demo)
                </div>
                <div class="card-body">
                    <form action="localizacion.php" method="POST">
                         <div class="form-floating mb-3">
                            <input class="form-control" id="inputMatricula" type="text" name="matricula" placeholder="Matrícula" required />
                            <label for="inputMatricula">Matrícula</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputModelo" type="text" name="modelo" placeholder="Modelo" />
                            <label for="inputModelo">Modelo</label>
                        </div>
                        <div class="mt-4 mb-0">
                            <div class="d-grid">
                                <button type="submit" name="add_taxi" class="btn btn-secondary btn-block">Añadir Taxi</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Coordenadas iniciales para centrar el mapa (ej. Madrid)
        const initialCoords = [40.416775, -3.703790];
        const map = L.map('map').setView(initialCoords, 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        let markers = {};

        function updateMap() {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    // Limpiar marcadores que ya no existen
                    Object.keys(markers).forEach(matricula => {
                        if (!data.find(taxi => taxi.matricula === matricula)) {
                            map.removeLayer(markers[matricula]);
                            delete markers[matricula];
                        }
                    });

                    data.forEach(taxi => {
                        const lat = parseFloat(taxi.latitud);
                        const lon = parseFloat(taxi.longitud);
                        const matricula = taxi.matricula;

                        if (!isNaN(lat) && !isNaN(lon)) {
                            const popupContent = `<b>Taxi:</b> ${matricula}<br><b>Modelo:</b> ${taxi.modelo || 'N/A'}<br><b>Última vez:</b> ${taxi.ultima_actualizacion}`;

                            if (markers[matricula]) {
                                markers[matricula].setLatLng([lat, lon]).setPopupContent(popupContent);
                            } else {
                                markers[matricula] = L.marker([lat, lon]).addTo(map)
                                    .bindPopup(popupContent);
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar las localizaciones:', error));
        }

        // Actualizar el mapa cada 5 segundos
        setInterval(updateMap, 5000);

        // Carga inicial
        updateMap();
    });
</script>

<?php
require_once 'footer.php';
?>