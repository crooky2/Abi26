<?php
// Shows user info and logout when logged in, otherwise nothing (parent includes login form)
?>
<div class="content">
	<?php if (!empty($_SESSION['flash_success'])): ?>
		<div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
	<?php endif; ?>
	<?php if (!empty($_SESSION['flash_error'])): ?>
		<div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
	<?php endif; ?>
	<h2>Dein Account</h2>
	<p>Angemeldet als: <strong><?= htmlspecialchars($_SESSION['displayname'] ?? $_SESSION['email'] ?? ''); ?></strong></p>
	<p>
		<a href="/db/logout.php">Abmelden</a>
	</p>

    <?php include "admin.php"; ?>
</div>
