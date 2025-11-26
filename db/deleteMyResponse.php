<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}

if (empty($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = 'Bitte zuerst anmelden.';
    header('Location: ' . (BASE_PATH ?: '/') . 'account.php');
    exit;
}

$surveyId = isset($_POST['survey_id']) ? (int)$_POST['survey_id'] : 0;
if ($surveyId <= 0) {
    $_SESSION['flash_error'] = 'Fehlende Umfrage-ID.';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}

try {
    $s = $pdo->prepare('SELECT id FROM surveys WHERE id = :id');
    $s->execute(['id' => $surveyId]);
    if (!$s->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['flash_error'] = 'Diese Umfrage existiert nicht.';
        header('Location: ' . (BASE_PATH ?: '/') );
        exit;
    }

    $r = $pdo->prepare('SELECT id FROM survey_responses WHERE survey_id = :sid AND account_id = :aid');
    $r->execute(['sid' => $surveyId, 'aid' => $_SESSION['user_id']]);
    $respIds = $r->fetchAll(PDO::FETCH_COLUMN);

    if (!$respIds) {
        $_SESSION['flash_error'] = 'Keine Antwort gefunden, die gelöscht werden kann.';
        header('Location: ' . (BASE_PATH ?: '/') );
        exit;
    }

    $pdo->beginTransaction();

    $placeholders = implode(',', array_fill(0, count($respIds), '?'));
    $delAns = $pdo->prepare("DELETE FROM survey_answers WHERE response_id IN ($placeholders)");
    $delAns->execute($respIds);

    $delResp = $pdo->prepare("DELETE FROM survey_responses WHERE id IN ($placeholders)");
    $delResp->execute($respIds);

    $pdo->commit();

    $_SESSION['flash_success'] = 'Deine Antwort wurde gelöscht.';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['flash_error'] = 'Fehler beim Löschen: ' . htmlspecialchars($e->getMessage());
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}
