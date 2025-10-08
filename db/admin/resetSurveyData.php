<?php
session_start();
require_once '../db.php';

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
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id FROM survey_responses WHERE survey_id = :sid');
    $stmt->execute(['sid' => $surveyId]);
    $responseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($responseIds && count($responseIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($responseIds), '?'));
        $delAns = $pdo->prepare("DELETE FROM survey_answers WHERE response_id IN ($placeholders)");
        $delAns->execute($responseIds);
    }

    $delResp = $pdo->prepare('DELETE FROM survey_responses WHERE survey_id = :sid');
    $delResp->execute(['sid' => $surveyId]);
    $deletedResponses = $delResp->rowCount();

    $pdo->commit();

    echo json_encode(['ok' => true, 'deleted_responses' => $deletedResponses]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'db', 'message' => $e->getMessage()]);
    exit;
}
