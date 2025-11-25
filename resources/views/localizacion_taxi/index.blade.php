<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Taxis</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        #map { height: 100vh; }
    </style>
</head>
<body>

<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var map = L.map('map').setView([40.416775, -3.703790], 12); // Centrado en Madrid por defecto

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        function fetchTaxiLocations() {
            fetch('/api/taxis/locations')
                .then(response => response.json())
                .then(locations => {
                    // Limpiar marcadores existentes
                    map.eachLayer(function (layer) {
                        if (layer instanceof L.Marker) {
                            map.removeLayer(layer);
                        }
                    });

                    // Añadir nuevos marcadores
                    locations.forEach(location => {
                        L.marker([location.latitud, location.longitud])
                            .addTo(map)
                            .bindPopup(`<b>Taxi ID:</b> ${location.taxi_id}<br><b>Última actualización:</b> ${new Date(location.updated_at).toLocaleString()}`);
                    });
                })
                .catch(error => console.error('Error al cargar las localizaciones:', error));
        }

        // Cargar localizaciones al inicio y luego cada 10 segundos
        fetchTaxiLocations();
        setInterval(fetchTaxiLocations, 10000);
    });
</script>

</body>
</html>
