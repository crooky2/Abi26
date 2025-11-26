<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}

$surveyId = isset($_POST['survey_id']) ? (int)$_POST['survey_id'] : 0;
$answers  = $_POST['answers'] ?? [];

if ($surveyId <= 0 || empty($answers)) {
    $_SESSION['flash_error'] = 'Fehler: Keine gültigen Antworten oder Umfrage-ID.';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = :id");
$stmt->execute(['id' => $surveyId]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$survey) {
    $_SESSION['flash_error'] = 'Diese Umfrage existiert nicht.';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}

if ($survey['expires_at'] && strtotime($survey['expires_at']) < time()) {
    $_SESSION['flash_error'] = 'Diese Umfrage ist abgelaufen.';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}

$qStmt = $pdo->prepare('SELECT id, question_type FROM survey_questions WHERE survey_id = :sid');
$qStmt->execute(['sid' => $surveyId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($questions as $q) {
    $qid = (string)$q['id'];
    if (!array_key_exists($qid, $answers)) {
        $_SESSION['flash_error'] = 'Bitte alle Fragen beantworten.';
        header('Location: ' . (BASE_PATH ?: '/') );
        exit;
    }
    $val = $answers[$qid];
    if (is_array($val)) {
        if (count(array_filter($val, fn($v) => trim((string)$v) !== '')) === 0) {
            $_SESSION['flash_error'] = 'Bitte alle Fragen beantworten.';
            header('Location: ' . (BASE_PATH ?: '/') );
            exit;
        }
    } else {
        if (trim((string)$val) === '') {
            $_SESSION['flash_error'] = 'Bitte alle Fragen beantworten.';
            header('Location: ' . (BASE_PATH ?: '/') );
            exit;
        }
    }
}

$accountId = $_SESSION['user_id'] ?? null;
if ($accountId) {
    $check = $pdo->prepare("SELECT id FROM survey_responses WHERE survey_id = :sid AND account_id = :aid");
    $check->execute(['sid' => $surveyId, 'aid' => $accountId]);
    if ($check->fetch()) {
        $_SESSION['flash_error'] = 'Du hast bereits an dieser Umfrage teilgenommen.';
        header('Location: ' . (BASE_PATH ?: '/') );
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO survey_responses (survey_id, account_id)
        VALUES (:sid, :aid)
    ");
    $stmt->execute([
        'sid' => $surveyId,
        'aid' => $accountId
    ]);
    $responseId = $pdo->lastInsertId();

    $insert = $pdo->prepare("
        INSERT INTO survey_answers (response_id, question_id, answer_text)
        VALUES (:rid, :qid, :txt)
    ");

    foreach ($answers as $questionId => $value) {
        if (is_array($value)) {
            $value = implode(', ', array_map('trim', $value));
        }
        $insert->execute([
            'rid' => $responseId,
            'qid' => (int)$questionId,
            'txt' => trim((string)$value)
        ]);
    }

    $pdo->commit();

    $_SESSION['flash_success'] = 'Danke fürs Abstimmen!';
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['flash_error'] = 'Fehler beim Speichern: ' . htmlspecialchars($e->getMessage());
    header('Location: ' . (BASE_PATH ?: '/') );
    exit;
}
