<div class="content">
	<?php if (!empty($_SESSION['flash_success'])): ?>
		<div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
	<?php endif; ?>
	<?php if (!empty($_SESSION['flash_error'])): ?>
		<div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
	<?php endif; ?>

	<div class="panel" style="margin-top:16px;">
		<div class="panel-title-wrap">
			<h3 style="margin:0; display:flex; align-items:center; gap:8px;">
				<span class="material-icons-outlined" aria-hidden="true">account_circle</span>
				Profil
			</h3>
			<div class="panel-actions">
				<button type="button" id="discardEdit" class="icon-btn icon-btn-danger" aria-label="Änderungen verwerfen" style="display:none;">
					<span class="material-icons-outlined" aria-hidden="true">close</span>
				</button>
				<button type="button" id="editToggle" class="icon-btn" aria-label="Profil bearbeiten">
					<span class="material-icons-outlined" aria-hidden="true">edit</span>
				</button>
			</div>
		</div>

		<form id="profileForm" method="post" action="/db/account/updateProfile.php" style="margin-top:12px;">
			<div class="profile-row">
				<label for="email">E-Mail</label>
				<div class="profile-value">
					<span class="value-display" id="emailDisplay"><?= htmlspecialchars($_SESSION['email'] ?? ''); ?></span>
					<input class="input-field value-input" type="email" id="email" name="email" value="<?= htmlspecialchars($_SESSION['email'] ?? ''); ?>" required aria-label="E-Mail" style="display:none;">
				</div>
			</div>
			<div class="profile-row">
				<label for="displayname">Anzeigename</label>
				<div class="profile-value">
					<span class="value-display" id="displaynameDisplay"><?= htmlspecialchars($_SESSION['displayname'] ?? ''); ?></span>
					<input class="input-field value-input" type="text" id="displayname" name="displayname" value="<?= htmlspecialchars($_SESSION['displayname'] ?? ''); ?>" required aria-label="Anzeigename" style="display:none;">
				</div>
			</div>
		</form>

		<div style="margin-top:14px; display:flex; gap:8px;">
			<a class="btn btn-secondary" href="/db/logout.php">
				<span class="material-icons-outlined" aria-hidden="true">logout</span>
				Abmelden
			</a>
			<form method="post" action="/db/account/resetData.php" onsubmit="return confirm('Bist du sicher? Dies löscht deine Antworten auf Umfragen dauerhaft.');">
				<button type="submit" class="btn" style="border-color: rgba(239,68,68,0.35); color: #ad1111ff; background: rgba(255, 0, 0, 0.05);">
					<span class="material-icons-outlined" aria-hidden="true">delete</span>
					Eigene Umfragedaten löschen
				</button>
			</form>
		</div>
	</div>

	<?php 
	$isAdmin = false;
	if (!empty($_SESSION['user_id'])) {
		require_once 'db/db.php';
		try {
			$stmt = $pdo->prepare('SELECT admin FROM accounts WHERE id = :id');
			$stmt->execute(['id' => $_SESSION['user_id']]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$isAdmin = $row && !empty($row['admin']);
		} catch (PDOException $e) {
			$isAdmin = false;
		}
	}
	if ($isAdmin) { include "admin.php"; }
	?>
</div>

<script>
(function(){
	const editBtn = document.getElementById('editToggle');
	const discardBtn = document.getElementById('discardEdit');
	const form = document.getElementById('profileForm');
	const displays = Array.from(document.querySelectorAll('.value-display'));
	const inputs = Array.from(document.querySelectorAll('.value-input'));
	let editing = false;

	function setMode(isEditing){
		editing = isEditing;
		displays.forEach(el => el.style.display = isEditing ? 'none' : '');
		inputs.forEach(el => el.style.display = isEditing ? '' : 'none');
		const icon = editBtn.querySelector('.material-icons-outlined');
		if (icon) icon.textContent = isEditing ? 'save' : 'edit';
		editBtn.setAttribute('aria-label', isEditing ? 'Profil speichern' : 'Profil bearbeiten');
		discardBtn && (discardBtn.style.display = isEditing ? '' : 'none');
		if (isEditing) {
			const first = inputs[0];
			first && first.focus();
		}
	}

	editBtn && editBtn.addEventListener('click', function(){
		if (!editing) {
			setMode(true);
		} else {
			form && form.submit();
		}
	});

	discardBtn && discardBtn.addEventListener('click', function(){
		if (!editing) return;
		inputs.forEach(inp => { inp.value = inp.defaultValue; });
		setMode(false);
	});

	document.addEventListener('keydown', function(e){
		if (editing && e.key === 'Escape') {
			inputs.forEach(inp => { inp.value = inp.defaultValue; });
			setMode(false);
		}
	});

	setMode(false);
})();
</script>
