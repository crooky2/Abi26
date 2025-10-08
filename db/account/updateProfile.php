<?php
session_start();
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo 'Method Not Allowed';
	exit;
}

if (empty($_SESSION['user_id'])) {
	$_SESSION['flash_error'] = 'Bitte zuerst anmelden.';
	header('Location: /account.php');
	exit;
}

$email = trim($_POST['email'] ?? '');
$displayname = trim($_POST['displayname'] ?? '');

if ($email === '' || $displayname === '') {
	$_SESSION['flash_error'] = 'E-Mail und Anzeigename dürfen nicht leer sein.';
	header('Location: /account.php');
	exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	$_SESSION['flash_error'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
	header('Location: /account.php');
	exit;
}

if (mb_strlen($displayname) > 60) {
	$_SESSION['flash_error'] = 'Anzeigename ist zu lang (max. 60 Zeichen).';
	header('Location: /account.php');
	exit;
}

try {
	// Ensure email uniqueness (except for current user)
	$check = $pdo->prepare('SELECT id FROM accounts WHERE email = :e AND id <> :id LIMIT 1');
	$check->execute(['e' => $email, 'id' => $_SESSION['user_id']]);
	if ($check->fetch()) {
		$_SESSION['flash_error'] = 'Diese E-Mail ist bereits vergeben.';
		header('Location: /account.php');
		exit;
	}

	$upd = $pdo->prepare('UPDATE accounts SET email = :e, displayname = :d WHERE id = :id');
	$upd->execute(['e' => $email, 'd' => $displayname, 'id' => $_SESSION['user_id']]);

	$_SESSION['email'] = $email;
	$_SESSION['displayname'] = $displayname;
	$_SESSION['flash_success'] = 'Profil aktualisiert.';
} catch (PDOException $e) {
	$_SESSION['flash_error'] = 'Fehler beim Aktualisieren: ' . htmlspecialchars($e->getMessage());
}

header('Location: /account.php');
exit;
