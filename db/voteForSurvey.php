<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Ungültige Anfrage.');
}

$surveyId = isset($_POST['survey_id']) ? (int)$_POST['survey_id'] : 0;
$answers  = $_POST['answers'] ?? [];

if ($surveyId <= 0 || empty($answers)) {
    die('Fehler: Keine gültigen Antworten oder Umfrage-ID.');
}

$stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = :id");
$stmt->execute(['id' => $surveyId]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$survey) {
    die('Diese Umfrage existiert nicht.');
}

if ($survey['expires_at'] && strtotime($survey['expires_at']) < time()) {
    die('Diese Umfrage ist abgelaufen.');
}

$accountId = $_SESSION['user_id'] ?? null;
if ($accountId) {
    $check = $pdo->prepare("SELECT id FROM survey_responses WHERE survey_id = :sid AND account_id = :aid");
    $check->execute(['sid' => $surveyId, 'aid' => $accountId]);
    if ($check->fetch()) {
        die('Du hast bereits an dieser Umfrage teilgenommen.');
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

    echo "<p>Danke fürs Abstimmen!</p>";

} catch (PDOException $e) {
    $pdo->rollBack();
    die("Fehler beim Speichern: " . htmlspecialchars($e->getMessage()));
}
