<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

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

$surveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
if ($surveyId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

try {
    $s = $pdo->prepare('SELECT s.id, s.title, s.description, s.expires_at, s.created_at, a.displayname AS creator
                        FROM surveys s JOIN accounts a ON s.account_id = a.id WHERE s.id = :id');
    $s->execute(['id' => $surveyId]);
    $survey = $s->fetch(PDO::FETCH_ASSOC);
    if (!$survey) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $q = $pdo->prepare('SELECT id, question_text, question_type, options FROM survey_questions WHERE survey_id = :sid ORDER BY id ASC');
    $q->execute(params: ['sid' => $surveyId]);
    $questions = $q->fetchAll(PDO::FETCH_ASSOC);

    $r = $pdo->prepare('SELECT r.id as response_id, acc.id as account_id, acc.displayname, acc.email
                        FROM survey_responses r LEFT JOIN accounts acc ON r.account_id = acc.id WHERE r.survey_id = :sid');
    $r->execute(['sid' => $surveyId]);
    $responses = $r->fetchAll(PDO::FETCH_ASSOC);
    $respIds = array_column($responses, 'response_id');

    $answersByQ = [];
    if ($respIds) {
        $in = implode(',', array_fill(0, count($respIds), '?'));
        $a = $pdo->prepare("SELECT sa.response_id, sa.question_id, sa.answer_text FROM survey_answers sa WHERE sa.response_id IN ($in)");
        $a->execute($respIds);
        while ($row = $a->fetch(PDO::FETCH_ASSOC)) {
            $qid = (int)$row['question_id'];
            $answersByQ[$qid][] = $row;
        }
    }

    $detail = [];
    foreach ($questions as $qrow) {
        $qid = (int)$qrow['id'];
        $type = $qrow['question_type'];
        $opts = [];
        if (!empty($qrow['options'])) {
            $decoded = json_decode($qrow['options'], true);
            if (is_array($decoded)) { $opts = $decoded; }
        }
        $counts = [];
        $votersByAnswer = [];

        $qAnswers = $answersByQ[$qid] ?? [];
        foreach ($qAnswers as $aRow) {
            $respId = (int)$aRow['response_id'];
            $val = trim((string)$aRow['answer_text']);
            $acc = null;
            foreach ($responses as $rr) {
                if ((int)$rr['response_id'] === $respId) { $acc = $rr; break; }
            }
            $voterName = $acc['displayname'] ?? 'Unbekannt';
            $voterEmail = $acc['email'] ?? '';

            if ($type === 'multiple') {
                $counts[$val] = ($counts[$val] ?? 0) + 1;
                $votersByAnswer[$val] = $votersByAnswer[$val] ?? [];
                $votersByAnswer[$val][] = ['name' => $voterName, 'email' => $voterEmail];
            } else if ($type === 'number') {
                $counts[$val] = ($counts[$val] ?? 0) + 1;
                $votersByAnswer[$val] = $votersByAnswer[$val] ?? [];
                $votersByAnswer[$val][] = ['name' => $voterName, 'email' => $voterEmail];
            } else if ($type === 'single' || $type === 'text') {
                $counts[$val] = ($counts[$val] ?? 0) + 1;
                $votersByAnswer[$val] = $votersByAnswer[$val] ?? [];
                $votersByAnswer[$val][] = ['name' => $voterName, 'email' => $voterEmail];
            }
        }

        foreach ($opts as $o) { if (!isset($counts[$o])) $counts[$o] = 0; }

        $detail[] = [
            'question_id' => $qid,
            'text' => $qrow['question_text'],
            'type' => $type,
            'options' => $opts,
            'counts' => $counts,
            'voters' => $votersByAnswer,
        ];
    }

    echo json_encode([
        'survey' => $survey,
        'responses_total' => count($responses),
        'detail' => $detail,
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db', 'message' => $e->getMessage()]);
    exit;
}