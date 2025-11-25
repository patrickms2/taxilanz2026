<?php
header('Content-Type: application/json');
require_once 'db/config.php';

$action = $_GET['action'] ?? 'get_current_locations';

try {
    $pdo = db();

    switch ($action) {
        case 'get_route_history':
            $id_taxi = $_GET['id_taxi'] ?? null;
            if (!$id_taxi) {
                http_response_code(400);
                echo json_encode(['error' => 'Se requiere id_taxi']);
                exit;
            }

            $stmt = $pdo->prepare(
                "SELECT latitud, longitud FROM localizacion_historico WHERE id_taxi = ? ORDER BY fecha_registro ASC"
            );
            $stmt->execute([$id_taxi]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($history);
            break;

        case 'get_current_locations':
        default:
            $stmt = $pdo->query(
                "SELECT 
                    t.id, t.nombre, t.licencia, t.matricula, t.municipio,
                    t.ultima_localizacion_lat as latitud, t.ultima_localizacion_lng as longitud,
                    (SELECT COUNT(*) FROM documents WHERE id_conductor = t.id) as num_documentos,
                    (SELECT COUNT(*) FROM citas WHERE id_conductor = t.id) as num_citas,
                    (SELECT COUNT(*) FROM consultas WHERE id_conductor = t.id) as num_consultas,
                    (SELECT COUNT(*) FROM localizacion_historico WHERE id_taxi = t.id) as num_ubicaciones
                FROM taxis t
                GROUP BY t.id
                ORDER BY t.nombre ASC"
            );
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($locations);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>