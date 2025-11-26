<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');
if ($email === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM accounts WHERE email = :email");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

echo json_encode(['exists' => (bool)$user]);
exit;
