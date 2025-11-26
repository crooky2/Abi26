<?php
session_start();
require_once dirname(__DIR__) . '/../config.php';
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo 'Method Not Allowed';
	exit;
}

if (empty($_SESSION['user_id'])) {
	$_SESSION['flash_error'] = 'Bitte zuerst anmelden.';
	header('Location: ' . base_url('account.php'));
	exit;
}

$displayname = trim($_POST['displayname'] ?? '');
if ($displayname === '') {
	$_SESSION['flash_error'] = 'Anzeigename darf nicht leer sein.';
	header('Location: ' . base_url('account.php'));
	exit;
}
if (mb_strlen($displayname) > 60) {
	$_SESSION['flash_error'] = 'Anzeigename ist zu lang (max. 60 Zeichen).';
	header('Location: ' . base_url('account.php'));
	exit;
}

try {
	$stmt = $pdo->prepare('UPDATE accounts SET displayname = :d WHERE id = :id');
	$stmt->execute(['d' => $displayname, 'id' => $_SESSION['user_id']]);
	$_SESSION['displayname'] = $displayname;
	$_SESSION['flash_success'] = 'Anzeigename aktualisiert.';
} catch (PDOException $e) {
	$_SESSION['flash_error'] = 'Fehler beim Aktualisieren: ' . htmlspecialchars($e->getMessage());
}

header('Location: ' . base_url('account.php'));
exit;
?>
