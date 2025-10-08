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

$userId = (int)$_SESSION['user_id'];

try {
	$pdo->beginTransaction();

	$stmt = $pdo->prepare('SELECT id FROM survey_responses WHERE account_id = :uid');
	$stmt->execute(['uid' => $userId]);
	$responseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

	if ($responseIds && count($responseIds) > 0) {
		$placeholders = implode(',', array_fill(0, count($responseIds), '?'));
		$delAns = $pdo->prepare("DELETE FROM survey_answers WHERE response_id IN ($placeholders)");
		$delAns->execute($responseIds);
	}

	$delResp = $pdo->prepare('DELETE FROM survey_responses WHERE account_id = :uid');
	$delResp->execute(['uid' => $userId]);

	$pdo->commit();

	$_SESSION['flash_success'] = 'Deine Umfragedaten wurden zurückgesetzt.';
} catch (PDOException $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	$_SESSION['flash_error'] = 'Fehler beim Zurücksetzen: ' . htmlspecialchars($e->getMessage());
}

header('Location: /account.php');
exit;
?>
