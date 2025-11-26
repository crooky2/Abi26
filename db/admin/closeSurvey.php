<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT admin FROM accounts WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || empty($u['admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db', 'message' => $e->getMessage()]);
    exit;
}

$surveyId = isset($_POST['survey_id']) ? (int)$_POST['survey_id'] : 0;
if ($surveyId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

try {
    $nowMinus1 = date('Y-m-d H:i:s', time() - 60);
    $upd = $pdo->prepare('UPDATE surveys SET expires_at = :exp WHERE id = :id');
    $upd->execute(['exp' => $nowMinus1, 'id' => $surveyId]);

    echo json_encode(['ok' => true, 'expires_at' => $nowMinus1]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db', 'message' => $e->getMessage()]);
    exit;
}
