<?php
// Permitir peticiones desde cualquier origen (CORS) y manejo de JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST");
header("Content-Type: application/json; charset=UTF-8");

// Configuración de la base de datos
$host = "localhost";
$user = "root";       
$password = "";       
$database = "basededatos";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["error" => "Error de conexión: " . $conn->connect_error]);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    
    // 1. Obtener todos los partidos (Incluye goles reales)
    case 'get_matches':
        $query = "SELECT id, team1 AS equipoA, team2 AS equipoB, scheduled_at AS fecha, real_goals_team1, real_goals_team2 FROM matches";
        $result = $conn->query($query);
        $matches = [];
        
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }
        echo json_encode($matches);
        break;

    // 2. Obtener todos los usuarios con su puntaje acumulado e incluir el rol
    case 'get_users':
        $query = "SELECT id, name AS nombre, role,
                  COALESCE((SELECT SUM(score) FROM predictions WHERE user_id = users.id), 0) AS puntaje 
                  FROM users";
        $result = $conn->query($query);
        $users = [];
        
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode($users);
        break;

    // 3. Obtener el Top 10 de usuarios
    case 'get_top10':
        $query = "SELECT users.id, users.name AS nombre, COALESCE(SUM(predictions.score), 0) AS puntaje 
                  FROM users 
                  LEFT JOIN predictions ON users.id = predictions.user_id 
                  GROUP BY users.id 
                  ORDER BY puntaje DESC 
                  LIMIT 10";
        $result = $conn->query($query);
        $top10 = [];
        
        while ($row = $result->fetch_assoc()) {
            $top10[] = $row;
        }
        echo json_encode($top10);
        break;

    // Obtener los IDs de partidos que un usuario específico ya pronosticó
    case 'get_user_predictions':
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            echo json_encode([]);
            exit();
        }
        $stmt = $conn->prepare("SELECT match_id FROM predictions WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['match_id'];
        }
        echo json_encode($ids);
        break;

    // 4. Guardar una predicción (Apuesta) con restricción de una sola vez
    case 'save_prediction':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(["error" => "Método no permitido"]);
            exit();
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $match_id = $data['match_id'] ?? null;
        $user_id = $data['user_id'] ?? null;
        $goals1 = $data['predicted_goals_team1'] ?? null;
        $goals2 = $data['predicted_goals_team2'] ?? null;

        if ($match_id === null || $user_id === null || $goals1 === null || $goals2 === null) {
            echo json_encode(["error" => "Faltan datos obligatorios"]);
            exit();
        }

        $check_stmt = $conn->prepare("SELECT id FROM predictions WHERE match_id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $match_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "Ya registraste un pronóstico para este partido."]);
            $check_stmt->close();
            exit();
        }
        $check_stmt->close();

        $stmt = $conn->prepare("INSERT INTO predictions (match_id, user_id, predicted_goals_team1, predicted_goals_team2) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $match_id, $user_id, $goals1, $goals2);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Predicción guardada correctamente."]);
        } else {
            echo json_encode(["error" => $stmt->error]);
        }
        $stmt->close();
        break;

    // 🚀 Guarda el resultado en 'matches' y calcula los puntos correspondientes
    case 'settle_match':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(["error" => "Método no permitido"]);
            exit();
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $match_id = $data['match_id'] ?? null;
        $real_goals1 = $data['real_goals_team1'] ?? null;
        $real_goals2 = $data['real_goals_team2'] ?? null;

        if ($match_id === null || $real_goals1 === null || $real_goals2 === null) {
            echo json_encode(["error" => "Faltan datos obligatorios para cerrar el partido."]);
            exit();
        }

        // 1. Guardar de forma oficial el resultado en la tabla 'matches'
        $match_update = $conn->prepare("UPDATE matches SET real_goals_team1 = ?, real_goals_team2 = ? WHERE id = ?");
        $match_update->bind_param("iii", $real_goals1, $real_goals2, $match_id);
        $match_update->execute();
        $match_update->close();

        // 2. Traer todas las predicciones de este partido para procesar puntajes
        $pred_stmt = $conn->prepare("SELECT id, predicted_goals_team1, predicted_goals_team2 FROM predictions WHERE match_id = ?");
        $pred_stmt->bind_param("i", $match_id);
        $pred_stmt->execute();
        $predictions = $pred_stmt->get_result();

        $update_stmt = $conn->prepare("UPDATE predictions SET score = ? WHERE id = ?");

        $real_trend = ($real_goals1 > $real_goals2) ? 1 : (($real_goals1 < $real_goals2) ? -1 : 0);
        $real_diff = $real_goals1 - $real_goals2;

        while ($pred = $predictions->fetch_assoc()) {
            $pred_id = $pred['id'];
            $p_g1 = $pred['predicted_goals_team1'];
            $p_g2 = $pred['predicted_goals_team2'];

            $pred_trend = ($p_g1 > $p_g2) ? 1 : (($p_g1 < $p_g2) ? -1 : 0);
            $pred_diff = $p_g1 - $p_g2;

            $score_otorgado = 0;

            if ($p_g1 == $real_goals1 && $p_g2 == $real_goals2) {
                $score_otorgado = 7; // Goles exactos y ganador
            } 
            elseif ($real_trend === $pred_trend && $real_diff === $pred_diff) {
                $score_otorgado = 5; // Ganador y diferencia exacta
            } 
            elseif ($real_trend === $pred_trend) {
                $score_otorgado = 2; // Solo ganador / tendencia
            }

            $update_stmt->bind_param("ii", $score_otorgado, $pred_id);
            $update_stmt->execute();
        }

        $pred_stmt->close();
        $update_stmt->close();

        echo json_encode(["success" => true, "message" => "Resultado guardado y puntajes actualizados (7, 5, 2)."]);
        break;

    default:
        echo json_encode(["error" => "Acción no válida o no especificada."]);
        break;
}

$conn->close();
?>