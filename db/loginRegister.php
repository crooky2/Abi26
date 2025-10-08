<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $displayname = trim($_POST['displayname'] ?? '');

    if ($email === '' || $password === '') {
    $_SESSION['flash_error'] = 'Bitte E-Mail und Passwort eingeben.';
    header('Location: /account.php');
    exit;
    }

    $stmt = $pdo->prepare("SELECT id, password, displayname, email FROM accounts WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // LOGIN
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['displayname'] = $user['displayname'] ?? $user['email'];
            $_SESSION['loggedIn'] = true;
            header('Location: /');
            exit;
        } else {
            $_SESSION['flash_error'] = 'Falsches Passwort.';
            header('Location: /account.php');
            exit;
        }
    } else {
        // REGISTRATION
        if ($confirm === '') {
            $_SESSION['flash_error'] = 'Bitte Passwort bestätigen, um ein neues Konto zu erstellen.';
            header('Location: /account.php');
            exit;
        }
        if ($password !== $confirm) {
            $_SESSION['flash_error'] = 'Die Passwörter stimmen nicht überein.';
            header('Location: /account.php');
            exit;
        }
        if ($displayname === '') {
            $_SESSION['flash_error'] = 'Bitte Anzeigename eingeben.';
            header('Location: /account.php');
            exit;
        }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare("INSERT INTO accounts (email, password, displayname) VALUES (:e, :p, :d)");
    $insert->execute(['e' => $email, 'p' => $hash, 'd' => $displayname]);

    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['email'] = $email;
        $_SESSION['displayname'] = $displayname;
        $_SESSION['loggedIn'] = true;
        header('Location: /');
        exit;
    }
}