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
        id_taxi INT NOT NULL UNIQUE, -- Un taxi solo puede tener una última ubicación
        latitud DECIMAL(10, 8) NOT NULL,
        longitud DECIMAL(11, 8) NOT NULL,
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_taxi) REFERENCES taxis(id)
    )");

    // Create location history table
    $pdo->exec("CREATE TABLE IF NOT EXISTS localizacion_historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_taxi INT NOT NULL,
        latitud DECIMAL(10, 8) NOT NULL,
        longitud DECIMAL(11, 8) NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
            $pdo->beginTransaction();
            try {
                // Upsert (Update or Insert) the last known location
                $stmt_upsert = $pdo->prepare(
                    "INSERT INTO localizacion_taxis (id_taxi, latitud, longitud) VALUES (?, ?, ?) " .
                    "ON DUPLICATE KEY UPDATE latitud = VALUES(latitud), longitud = VALUES(longitud)"
                );
                $stmt_upsert->execute([$id_taxi, $latitud, $longitud]);

                // Insert into history
                $stmt_history = $pdo->prepare("INSERT INTO localizacion_historico (id_taxi, latitud, longitud) VALUES (?, ?, ?)");
                $stmt_history->execute([$id_taxi, $latitud, $longitud]);
                
                $pdo->commit();
                echo '<div class="alert alert-success" role="alert">Localización actualizada con éxito.</div>';
            } catch (Exception $e) {
                $pdo->rollBack();
                echo '<div class="alert alert-danger" role="alert">Error al guardar los datos: '. $e->getMessage() .'</div>';
            }
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
            <button id="get-location-btn" class="btn btn-sm btn-outline-secondary float-end">Mi Ubicación</button>
        </div>
        <div class="card-body">
            <div id="map" style="height: 500px;"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-route me-1"></i>
            Historial de Ruta por Taxi
        </div>
        <div class="card-body">
            <form id="history-form">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-floating mb-3">
                            <select class="form-select" id="selectTaxiHistory" name="id_taxi" required>
                                <option value="">Seleccione un taxi para ver su historial</option>
                                <?php foreach ($taxis as $taxi): ?>
                                    <option value="<?php echo $taxi['id']; ?>"><?php echo htmlspecialchars($taxi['matricula'] . ' - ' . $taxi['modelo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="selectTaxiHistory">Taxi</label>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="d-grid w-100">
                            <button type="submit" class="btn btn-info">Mostrar Historial</button>
                        </div>
                    </div>
                </div>
            </form>
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
        const initialCoords = [40.416775, -3.703790]; // Coordenadas iniciales (Madrid)
        const map = L.map('map').setView(initialCoords, 6); // Zoom inicial más alejado

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Definir un icono personalizado para los taxis
        const taxiIcon = L.icon({
            iconUrl: 'https://cdn-icons-png.flaticon.com/512/15/15437.png', // URL de un icono de coche genérico
            iconSize: [32, 32], // Tamaño del icono
            iconAnchor: [16, 32], // Punto del icono que corresponde a la ubicación del marcador
            popupAnchor: [0, -32] // Punto desde donde se abrirá el popup
        });

        let markers = {};

        function updateMap() {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    const bounds = L.latLngBounds();
                    const activeMatriculas = data.map(taxi => taxi.matricula);

                    // Limpiar marcadores de taxis que ya no están en los datos
                    Object.keys(markers).forEach(matricula => {
                        if (!activeMatriculas.includes(matricula)) {
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
                            const latLng = [lat, lon];

                            if (markers[matricula]) {
                                markers[matricula].setLatLng(latLng).setPopupContent(popupContent);
                            } else {
                                markers[matricula] = L.marker(latLng, { icon: taxiIcon }).addTo(map)
                                    .bindPopup(popupContent);
                            }
                            // Extender los límites para el auto-zoom
                            bounds.extend(latLng);
                        }
                    });

                    // Ajustar el mapa a los límites de los marcadores si hay alguno
                    if (bounds.isValid()) {
                        map.fitBounds(bounds, { padding: [50, 50] }); // Añade un poco de padding
                    }
                })
                .catch(error => console.error('Error al cargar las localizaciones:', error));
        }

        // Actualizar el mapa cada 5 segundos
        setInterval(updateMap, 5000);

        // Carga inicial
        updateMap();

        // Lógica para el historial de rutas
        let routePolyline = null;
        document.getElementById('history-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const taxiId = document.getElementById('selectTaxiHistory').value;
            if (!taxiId) {
                alert('Por favor, seleccione un taxi.');
                return;
            }

            fetch(`api.php?action=get_route_history&id_taxi=${taxiId}`)
                .then(response => response.json())
                .then(historyData => {
                    if (routePolyline) {
                        map.removeLayer(routePolyline);
                    }

                    if (historyData.length < 2) {
                        alert('No hay suficiente historial para mostrar una ruta.');
                        return;
                    }

                    const latLngs = historyData.map(point => [parseFloat(point.latitud), parseFloat(point.longitud)]);
                    
                    routePolyline = L.polyline(latLngs, {color: '#007bff', weight: 5}).addTo(map);
                    
                    map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] });
                })
                .catch(error => console.error('Error al cargar el historial:', error));
        });

        // Lógica para el botón "Mi Ubicación"
        let userLocationMarker = null;
        const userIcon = L.icon({ // Icono personalizado para el usuario
            iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684809.png', // Icono de persona
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });

        document.getElementById('get-location-btn').addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('La geolocalización no es soportada por tu navegador.');
                return;
            }

            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                const userLatLng = [lat, lon];

                if (userLocationMarker) {
                    userLocationMarker.setLatLng(userLatLng);
                } else {
                    userLocationMarker = L.marker(userLatLng, { icon: userIcon }).addTo(map)
                        .bindPopup('<b>Tu estás aquí</b>');
                }
                map.setView(userLatLng, 15); // Centrar el mapa en el usuario con un zoom más cercano
                userLocationMarker.openPopup();

            }, function() {
                alert('No se pudo obtener tu ubicación. Asegúrate de haber concedido los permisos.');
            });
        });
    });
</script>

<?php
require_once 'footer.php';
?>