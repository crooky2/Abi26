<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo 'Method Not Allowed';
	exit;
}

if (empty($_SESSION['user_id'])) {
	$_SESSION['flash_error'] = 'Zugriff verweigert. Bitte zuerst anmelden.';
	header('Location: ' . base_url('account.php'));
	exit;
}

try {
	$stmt = $pdo->prepare('SELECT admin FROM accounts WHERE id = :id');
	$stmt->execute(['id' => $_SESSION['user_id']]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$user || !$user['admin']) {
		$_SESSION['flash_error'] = 'Zugriff verweigert. Nur für Admins.';
		header('Location: ' . base_url('account.php'));
		exit;
	}
} catch (PDOException $e) {
	$_SESSION['flash_error'] = 'Fehler bei der Berechtigungsprüfung: ' . htmlspecialchars($e->getMessage());
	header('Location: ' . base_url('account.php'));
	exit;
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$expires = $_POST['expires_at'] ?? null;
$questions = $_POST['questions'] ?? [];

if ($title === '') {
	$_SESSION['flash_error'] = 'Bitte Titel eingeben.';
	header('Location: ' . base_url('account.php'));
	exit;
}
if (empty($questions)) {
	$_SESSION['flash_error'] = 'Bitte mindestens eine Frage hinzufügen.';
	header('Location: ' . base_url('account.php'));
	exit;
}

try {
	$stmt = $pdo->prepare('INSERT INTO surveys (account_id, title, description, expires_at) VALUES (:a, :t, :d, :e)');
	$stmt->execute([
		'a' => $_SESSION['user_id'],
		't' => $title,
		'd' => $description,
		'e' => $expires ?: null
	]);
	$surveyId = $pdo->lastInsertId();

	$qStmt = $pdo->prepare('INSERT INTO survey_questions (survey_id, question_text, question_type, options) VALUES (:s, :q, :type, :opt)');
	foreach ($questions as $q) {
		$qText = trim($q['text'] ?? '');
		if ($qText === '') continue;
		$qType = $q['type'] ?? 'text';
		$rawOptions = $q['options'] ?? '';
		$opt = null;
		if ($qType !== 'text' && trim($rawOptions) !== '') {
			$opt = json_encode(array_map('trim', explode(',', $rawOptions)));
		}
		$qStmt->execute([
			's' => $surveyId,
			'q' => $qText,
			'type' => $qType,
			'opt' => $opt
		]);
	}

	$_SESSION['flash_success'] = 'Umfrage erfolgreich erstellt!';
	header('Location: ' . base_url('account.php'));
	exit;
} catch (PDOException $e) {
	$_SESSION['flash_error'] = 'Fehler beim Erstellen der Umfrage: ' . htmlspecialchars($e->getMessage());
	header('Location: ' . base_url('account.php'));
	exit;
}
?>
