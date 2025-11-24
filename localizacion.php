<?php
require_once 'db/config.php';
require_once 'header.php';

// The schema creation is kept for robustness, but handled in drivers.php mainly.
try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS localizacion_taxis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_taxi INT NOT NULL UNIQUE,
        latitud DECIMAL(10, 8) NOT NULL,
        longitud DECIMAL(11, 8) NOT NULL,
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_taxi) REFERENCES taxis(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS localizacion_historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_taxi INT NOT NULL,
        latitud DECIMAL(10, 8) NOT NULL,
        longitud DECIMAL(11, 8) NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_taxi) REFERENCES taxis(id) ON DELETE CASCADE
    )");

    // Handle historical location submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_localizacion_historica'])) {
        $id_taxi = $_POST['id_taxi'];
        $latitud = $_POST['latitud'];
        $longitud = $_POST['longitud'];
        $fecha_registro = $_POST['fecha_registro'];

        if (!empty($id_taxi) && is_numeric($latitud) && is_numeric($longitud) && !empty($fecha_registro)) {
            $stmt = $pdo->prepare("INSERT INTO localizacion_historico (id_taxi, latitud, longitud, fecha_registro) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_taxi, $latitud, $longitud, $fecha_registro]);
            
            // Also update the taxi's main location for immediate reflection
            $stmt_update = $pdo->prepare("UPDATE taxis SET ultima_localizacion_lat = ?, ultima_localizacion_lng = ? WHERE id = ?");
            $stmt_update->execute([$latitud, $longitud, $id_taxi]);

            echo '<div class="alert alert-success">Ubicación histórica añadida y ubicación principal del conductor actualizada.</div>';
        } else {
            echo '<div class="alert alert-danger">Todos los campos son obligatorios.</div>';
        }
    }
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<style>
    .map-container {
        display: grid;
        grid-template-columns: 280px 1fr;
        grid-template-rows: 1fr;
        gap: 1rem;
        height: 60vh; /* Adjust height as needed */
    }
    #map-sidebar {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-y: auto;
    }
    #map-sidebar .list-group-item {
        cursor: pointer;
    }
    #map {
        border-radius: 0.5rem;
        height: 100%;
    }
    .leaflet-popup-content-wrapper {
        border-radius: 0.5rem;
    }
    .leaflet-popup-content b { color: #0d6efd; }
</style>

<div class="container-fluid px-4">
    <h1 class="mt-4">Mapa General de Taxis</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Mapa</li>
    </ol>

    <div class="card mb-4">
        <div class="card-body">
            <div class="map-container">
                <div id="map-sidebar">
                    <div class="d-grid mb-2">
                        <button class="btn btn-secondary" id="show-all-btn">Mostrar Todos</button>
                    </div>
                    <ul class="list-group" id="taxi-list"></ul>
                </div>
                <div id="map"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-plus me-1"></i>Añadir Ubicación Histórica</div>
        <div class="card-body">
            <form action="localizacion.php" method="POST">
                <p class="small text-muted">Haz clic en un taxi del mapa para rellenar los datos o haz clic en el mapa para obtener coordenadas.</p>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputTaxiInfo" type="text" placeholder="Taxi" readonly />
                            <label>Taxi Seleccionado</label>
                            <input type="hidden" id="inputTaxiId" name="id_taxi" />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputLatitud" type="text" name="latitud" placeholder="Latitud" required />
                            <label>Latitud</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <input class="form-control" id="inputLongitud" type="text" name="longitud" placeholder="Longitud" required />
                            <label>Longitud</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                         <div class="form-floating mb-3">
                            <input class="form-control" id="inputFecha" type="datetime-local" name="fecha_registro" required />
                            <label>Fecha y Hora</label>
                        </div>
                    </div>
                </div>
                <div class="d-grid"><button type="submit" name="add_localizacion_historica" class="btn btn-primary">Añadir a Historial</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('map').setView([28.963, -13.548], 11); // Centered on Lanzarote
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    const markers = {};
    const taxiList = document.getElementById('taxi-list');

    const icons = {
        'TIAS': new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
        'TEGUISE': new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
        'YAIZA': new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
        'default': new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] })
    };

    function createPopupContent(taxi) {
        return `
            <b>${taxi.nombre || 'Conductor sin nombre'}</b><br>
            <b>Matrícula:</b> ${taxi.matricula}<br>
            <b>Licencia:</b> ${taxi.licencia || 'N/A'}<br>
            <b>Municipio:</b> ${taxi.municipio || 'N/A'}<br><hr>
            <b>Docs:</b> ${taxi.num_documentos} | 
            <b>Citas:</b> ${taxi.num_citas} | 
            <b>Consultas:</b> ${taxi.num_consultas} | 
            <b>Ubics.:</b> ${taxi.num_ubicaciones}
        `;
    }

    function updateMap() {
        fetch('api.php?action=get_current_locations')
            .then(response => response.json())
            .then(data => {
                taxiList.innerHTML = ''; // Clear list before update
                const activeTaxiIds = data.map(t => t.id);

                // Remove old markers
                Object.keys(markers).forEach(id => {
                    if (!activeTaxiIds.includes(parseInt(id))) {
                        map.removeLayer(markers[id]);
                        delete markers[id];
                    }
                });

                data.forEach(taxi => {
                    const lat = parseFloat(taxi.latitud);
                    const lon = parseFloat(taxi.longitud);
                    if (isNaN(lat) || isNaN(lon)) return;

                    // Update sidebar
                    const listItem = document.createElement('li');
                    listItem.className = 'list-group-item list-group-item-action';
                    listItem.textContent = `${taxi.matricula} (${taxi.nombre || 'Sin nombre'})`;
                    listItem.dataset.taxiId = taxi.id;
                    listItem.addEventListener('click', () => {
                        map.setView([lat, lon], 15);
                        markers[taxi.id].openPopup();
                    });
                    taxiList.appendChild(listItem);

                    // Update map markers
                    const popupContent = createPopupContent(taxi);
                    const icon = icons[taxi.municipio] || icons['default'];
                    if (markers[taxi.id]) {
                        markers[taxi.id].setLatLng([lat, lon]).setPopupContent(popupContent).setIcon(icon);
                    } else {
                        markers[taxi.id] = L.marker([lat, lon], { icon: icon }).addTo(map)
                            .bindPopup(popupContent);
                    }

                    markers[taxi.id].off('click').on('click', () => {
                        document.getElementById('inputTaxiInfo').value = `${taxi.matricula} - ${taxi.nombre}`;
                        document.getElementById('inputTaxiId').value = taxi.id;
                        document.getElementById('inputLatitud').value = lat.toFixed(8);
                        document.getElementById('inputLongitud').value = lon.toFixed(8);
                        document.getElementById('inputFecha').value = new Date().toISOString().slice(0, 16);
                    });
                });
            });
    }

    document.getElementById('show-all-btn').addEventListener('click', () => {
        const group = new L.featureGroup(Object.values(markers));
        if (Object.keys(markers).length > 0) {
            map.fitBounds(group.getBounds().pad(0.2));
        }
    });
    
    map.on('click', function(e) {
        document.getElementById('inputLatitud').value = e.latlng.lat.toFixed(8);
        document.getElementById('inputLongitud').value = e.latlng.lng.toFixed(8);
    });

    setInterval(updateMap, 10000); // Update every 10 seconds
    updateMap(); // Initial load
});
</script>

<?php
require_once 'footer.php';
?>
