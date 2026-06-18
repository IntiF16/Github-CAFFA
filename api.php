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

/**
 * Recalcula y actualiza el puntaje acumulado de un usuario
 * basado en la suma de los campos 'score' de la tabla predictions.
 */
function recalculate_user_score($conn, $user_id) {
    $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(score), 0) AS total FROM predictions WHERE user_id = ?");
    if (!$sum_stmt) return false;
    $sum_stmt->bind_param("i", $user_id);
    $sum_stmt->execute();
    $res = $sum_stmt->get_result();
    $row = $res->fetch_assoc();
    $total = (int)$row['total'];
    $sum_stmt->close();

    $update_user_stmt = $conn->prepare("UPDATE users SET puntaje = ? WHERE id = ?");
    if (!$update_user_stmt) return false;
    $update_user_stmt->bind_param("ii", $total, $user_id);
    $ok = $update_user_stmt->execute();
    $update_user_stmt->close();

    return $ok;
}

/**
 * Función que calcula y actualiza los scores de todas las predicciones
 * de un partido dado, usando los goles reales almacenados en la tabla matches.
 * Devuelve array con resumen: updated_predictions_count y affected_user_ids.
 */
function update_predictions_scores_for_match($conn, $match_id) {
    // Obtener goles reales desde la tabla matches
    $mstmt = $conn->prepare("SELECT real_goals_team1, real_goals_team2 FROM matches WHERE id = ?");
    if (!$mstmt) return ["error" => "Error preparando consulta de partido"];
    $mstmt->bind_param("i", $match_id);
    $mstmt->execute();
    $mres = $mstmt->get_result();
    if ($mres->num_rows === 0) {
        $mstmt->close();
        return ["error" => "Partido no encontrado"];
    }
    $mrow = $mres->fetch_assoc();
    $mstmt->close();

    // Si no hay goles reales registrados, no hay nada que calcular
    if ($mrow['real_goals_team1'] === null || $mrow['real_goals_team2'] === null) {
        return ["error" => "El partido no tiene goles reales registrados"];
    }

    $real_goals1 = (int)$mrow['real_goals_team1'];
    $real_goals2 = (int)$mrow['real_goals_team2'];

    // Traer predicciones del partido
    $pred_stmt = $conn->prepare("SELECT id, user_id, predicted_goals_team1, predicted_goals_team2 FROM predictions WHERE match_id = ?");
    if (!$pred_stmt) return ["error" => "Error preparando consulta de predicciones"];
    $pred_stmt->bind_param("i", $match_id);
    $pred_stmt->execute();
    $predictions = $pred_stmt->get_result();

    $update_stmt = $conn->prepare("UPDATE predictions SET score = ? WHERE id = ?");
    if (!$update_stmt) {
        $pred_stmt->close();
        return ["error" => "Error preparando actualización de predicciones"];
    }

    $real_trend = ($real_goals1 > $real_goals2) ? 1 : (($real_goals1 < $real_goals2) ? -1 : 0);
    $real_diff = $real_goals1 - $real_goals2;

    $affected_users = [];
    $updated_count = 0;

    while ($pred = $predictions->fetch_assoc()) {
        $pred_id = (int)$pred['id'];
        $p_g1 = (int)$pred['predicted_goals_team1'];
        $p_g2 = (int)$pred['predicted_goals_team2'];
        $pred_user_id = (int)$pred['user_id'];

        $pred_trend = ($p_g1 > $p_g2) ? 1 : (($p_g1 < $p_g2) ? -1 : 0);
        $pred_diff = $p_g1 - $p_g2;

        $score_otorgado = 0;

        if ($p_g1 === $real_goals1 && $p_g2 === $real_goals2) {
            $score_otorgado = 7; // Goles exactos y ganador
        } elseif ($real_trend === $pred_trend && $real_diff === $pred_diff) {
            $score_otorgado = 5; // Ganador y diferencia exacta
        } elseif ($real_trend === $pred_trend) {
            $score_otorgado = 2; // Solo ganador / tendencia
        } else {
            $score_otorgado = 0; // No acertó tendencia ni diferencia
        }

        $update_stmt->bind_param("ii", $score_otorgado, $pred_id);
        if ($update_stmt->execute()) {
            $updated_count++;
            $affected_users[$pred_user_id] = true;
        }
    }

    $pred_stmt->close();
    $update_stmt->close();

    return ["updated_predictions_count" => $updated_count, "affected_user_ids" => array_keys($affected_users)];
}

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

        // Insertar la predicción con score inicial 0
        $stmt = $conn->prepare("INSERT INTO predictions (match_id, user_id, predicted_goals_team1, predicted_goals_team2, score) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("iiii", $match_id, $user_id, $goals1, $goals2);

        if ($stmt->execute()) {
            // Recalcular puntaje acumulado del usuario (la nueva predicción tiene score 0)
            recalculate_user_score($conn, $user_id);

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
        if (!$match_update->execute()) {
            $match_update->close();
            echo json_encode(["error" => "No se pudo actualizar el partido: " . $match_update->error]);
            exit();
        }
        $match_update->close();

        // 2. Actualizar puntajes de predicciones basados en los goles reales guardados
        $res = update_predictions_scores_for_match($conn, $match_id);
        if (isset($res['error'])) {
            echo json_encode(["error" => $res['error']]);
            exit();
        }

        // 3. Recalcular puntaje acumulado de los usuarios afectados
        if (!empty($res['affected_user_ids'])) {
            foreach ($res['affected_user_ids'] as $uid) {
                recalculate_user_score($conn, $uid);
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Resultado guardado y puntajes de predicciones actualizados.",
            "updated_predictions" => $res['updated_predictions_count'],
            "affected_users" => $res['affected_user_ids']
        ]);
        break;

    // Endpoint adicional: recalcula scores de predicciones para un partido ya cerrado (usa goles en matches)
    case 'recalculate_match_predictions':
        // Permitir POST o GET según preferencia
        $match_id = $_GET['match_id'] ?? null;
        if (!$match_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $match_id = $data['match_id'] ?? null;
        }
        if (!$match_id) {
            echo json_encode(["error" => "Falta match_id"]);
            exit();
        }

        $res = update_predictions_scores_for_match($conn, (int)$match_id);
        if (isset($res['error'])) {
            echo json_encode(["error" => $res['error']]);
            exit();
        }

        // Recalcular puntajes de usuarios afectados
        if (!empty($res['affected_user_ids'])) {
            foreach ($res['affected_user_ids'] as $uid) {
                recalculate_user_score($conn, $uid);
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Puntajes de predicciones recalculados para el partido.",
            "updated_predictions" => $res['updated_predictions_count'],
            "affected_users" => $res['affected_user_ids']
        ]);
        break;

    // Endpoint opcional para recalcular todos los puntajes (útil para mantenimiento)
    case 'recalculate_all_users':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(["error" => "Método no permitido"]);
            exit();
        }

        $users_res = $conn->query("SELECT id FROM users");
        $errors = [];
        while ($u = $users_res->fetch_assoc()) {
            $uid = (int)$u['id'];
            if (!recalculate_user_score($conn, $uid)) {
                $errors[] = $uid;
            }
        }

        if (empty($errors)) {
            echo json_encode(["success" => true, "message" => "Todos los puntajes de usuarios fueron recalculados correctamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Hubo errores al recalcular algunos usuarios.", "failed_user_ids" => $errors]);
        }
        break;

    default:
        echo json_encode(["error" => "Acción no válida o no especificada."]);
        break;
}

$conn->close();
?>
