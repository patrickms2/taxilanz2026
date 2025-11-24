<?php
header('Content-Type: application/json');
require_once 'db/config.php';

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT t.matricula, t.modelo, lt.latitud, lt.longitud, lt.ultima_actualizacion
                           FROM localizacion_taxis lt
                           JOIN taxis t ON lt.id_taxi = t.id
                           ORDER BY lt.ultima_actualizacion DESC");
    $localizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($localizaciones);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>