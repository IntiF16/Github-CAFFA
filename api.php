<?php
// Permitir peticiones desde cualquier origen (CORS) y manejo de JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST");
header("Content-Type: application/json; charset=UTF-8");

// Configuración de la base de datos
$host = "localhost";
$user = "root";       // Usuario por defecto en XAMPP
$password = "";       // Contraseña por defecto vacía en XAMPP
$database = "basededatos";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["error" => "Error de conexión: " . $conn->connect_error]);
    exit();
}

// =========================================================================
// RECALCULO Y ACTUALIZACIÓN MASIVA DE PUNTOS AL CARGAR LA PÁGINA (BD FÍSICA)
// =========================================================================

// 1. Obtener todos los partidos ya finalizados (que tienen goles reales ingresados)
$query_finalized = "SELECT id, real_goals_team1, real_goals_team2 FROM matches WHERE real_goals_team1 IS NOT NULL AND real_goals_team2 IS NOT NULL";
$res_finalized = $conn->query($query_finalized);

if ($res_finalized && $res_finalized->num_rows > 0) {
    $stmt_update_pred = $conn->prepare("UPDATE predictions SET score = ? WHERE id = ?");
    
    while ($match = $res_finalized->fetch_assoc()) {
        $m_id = $match['id'];
        $r_g1 = $match['real_goals_team1'];
        $r_g2 = $match['real_goals_team2'];

        $real_trend = ($r_g1 > $r_g2) ? 1 : (($r_g1 < $r_g2) ? -1 : 0);
        $real_diff = $r_g1 - $r_g2;

        // Traer predicciones asociadas a este partido cerrado
        $query_preds = "SELECT id, predicted_goals_team1, predicted_goals_team2 FROM predictions WHERE match_id = $m_id";
        $res_preds = $conn->query($query_preds);

        if ($res_preds) {
            while ($pred = $res_preds->fetch_assoc()) {
                $p_id = $pred['id'];
                $p_g1 = $pred['predicted_goals_team1'];
                $p_g2 = $pred['predicted_goals_team2'];

                $pred_trend = ($p_g1 > $p_g2) ? 1 : (($p_g1 < $p_g2) ? -1 : 0);
                $pred_diff = $p_g1 - $p_g2;

                $score_otorgado = 0;

                if ($p_g1 == $r_g1 && $p_g2 == $r_g2) {
                    $score_otorgado = 7; // Goles exactos
                } elseif ($real_trend === $pred_trend && $real_diff === $pred_diff) {
                    $score_otorgado = 5; // Tendencia + Diferencia exacta
                } elseif ($real_trend === $pred_trend) {
                    $score_otorgado = 2; // Solo tendencia
                }

                $stmt_update_pred->bind_param("ii", $score_otorgado, $p_id);
                $stmt_update_pred->execute();
            }
        }
    }
    $stmt_update_pred->close();
}

// 2. Sincronizar de forma masiva los totales acumulados en la columna 'score' de la tabla 'users'
$query_users_sync = "SELECT id FROM users";
$res_users_sync = $conn->query($query_users_sync);

if ($res_users_sync && $res_users_sync->num_rows > 0) {
    $stmt_sum = $conn->prepare("SELECT COALESCE(SUM(score), 0) AS total FROM predictions WHERE user_id = ?");
    $stmt_update_user = $conn->prepare("UPDATE users SET score = ? WHERE id = ?"); // <- CORREGIDO: score en lugar de puntaje

    while ($u = $res_users_sync->fetch_assoc()) {
        $uid = $u['id'];

        $stmt_sum->bind_param("i", $uid);
        $stmt_sum->execute();
        $res_sum = $stmt_sum->get_result();
        $row_sum = $res_sum->fetch_assoc();
        $total_puntos = (int)$row_sum['total'];

        $stmt_update_user->bind_param("ii", $total_puntos, $uid);
        $stmt_update_user->execute();
    }
    $stmt_sum->close();
    $stmt_update_user->close();
}

// =========================================================================

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

    // 2. Obtener todos los usuarios leyendo la columna física 'score' (con alias 'puntaje' para mantener compatibilidad con JS)
    case 'get_users':
        $query = "SELECT id, name AS nombre, role, score AS puntaje FROM users"; // <- CORREGIDO
        $result = $conn->query($query);
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode($users);
        break;

    // 3. Obtener el Top 10 leyendo DIRECTAMENTE de la columna física 'score'
    case 'get_top10':
        $query = "SELECT id, name AS nombre, score AS puntaje FROM users ORDER BY score DESC LIMIT 10"; // <- CORREGIDO
        $result = $conn->query($query);
        $top10 = [];

        while ($row = $result->fetch_assoc()) {
            $top10[] = $row;
        }
        echo json_encode($top10);
        break;

    // 4. Obtener los IDs de partidos que un usuario específico ya pronosticó
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

    // 5. Guardar una predicción (Apuesta) con restricción de una sola vez
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

    // 6. Crear partidos desde el Dashboard del Admin
    case 'create_match':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(["error" => "Método no permitido"]);
            exit();
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $t1 = $data['team1'] ?? null;
        $t2 = $data['team2'] ?? null;
        $fecha = $data['scheduled_at'] ?? null;

        if (!$t1 || !$t2 || !$fecha) {
            echo json_encode(["error" => "Campos obligatorios incompletos para crear el partido."]);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO matches (team1, team2, scheduled_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $t1, $t2, $fecha);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Partido creado con éxito."]);
        } else {
            echo json_encode(["error" => $stmt->error]);
        }
        $stmt->close();
        break;

    // 7. Cierra un partido asignando goles reales individuales
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

        $match_update = $conn->prepare("UPDATE matches SET real_goals_team1 = ?, real_goals_team2 = ? WHERE id = ?");
        $match_update->bind_param("iii", $real_goals1, $real_goals2, $match_id);
        
        if ($match_update->execute()) {
            echo json_encode(["success" => true, "message" => "Resultado del partido asentado con éxito."]);
        } else {
            echo json_encode(["error" => $match_update->error]);
        }
        $match_update->close();
        break;

    default:
        echo json_encode(["error" => "Acción no válida o no especificada."]);
        break;
}

$conn->close();
?>