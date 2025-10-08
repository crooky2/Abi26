<form method="post" id="loginRegister" action="/db/loginRegister.php">
    <h2>Anmelden / Registrieren</h2>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <div id="usernameBlock">
        <label>Email:<br>
            <input type="email" name="email" id="email" required>
        </label><br><br>
    </div>

    <label>Passwort:<br>
        <input type="password" name="password" id="password" required>
    </label><br><br>

    <div id="confirmBlock" style="display:none;">
        <small>Neues Konto: Bitte Anzeigenamen setzen und Passwort bestätigen.</small><br>
        <label>Anzeigename:<br>
            <input type="text" name="displayname" id="displayname">
        </label><br><br>
        <label>Passwort bestätigen:<br>
            <input type="password" name="confirm_password" id="confirm_password">
        </label>
    </div>

    <button type="submit" name="action" value="login">Anmelden / Registrieren</button>

    <p><small>Passwort vergessen? Melde dich bei Chris oder Nour.</small></p>
</form>

<script>
document.getElementById('loginRegister').addEventListener('submit', async function(e) {
    const confirmBlock = document.getElementById('confirmBlock');
    const emailBlock = document.getElementById('usernameBlock');
    const isConfirmVisible = confirmBlock.style.display && confirmBlock.style.display !== 'none';
    if (!isConfirmVisible) {
        e.preventDefault();
        const email = document.getElementById('email').value.trim();
        if (!email) return; // let required attribute handle
        try {
            const resp = await fetch('/db/checkUser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `email=${encodeURIComponent(email)}`
            });
            const data = await resp.json();
            if (!data.exists) {
                confirmBlock.style.display = 'block';
                emailBlock.style.display = 'none';
                // set required attributes for registration
                document.getElementById('displayname').setAttribute('required', 'required');
                document.getElementById('confirm_password').setAttribute('required', 'required');
                // guide the user gently without alert spam
            } else {
                this.submit();
            }
        } catch (err) {
            // on error, just try to submit and handle on server
            this.submit();
        }
    }
});
</script>
