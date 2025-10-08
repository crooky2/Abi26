<?php
require_once 'db/db.php';

// --- Check permissions for viewing the admin panel ---
if (empty($_SESSION['user_id'])) {
    die("Access denied. You must be logged in.");
}

$stmt = $pdo->prepare("SELECT admin FROM accounts WHERE id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['admin']) {
    die("Access denied. Admins only.");
}
?>

<!-- --- Admin Panel HTML --- -->
<h2>🛠️ Admin-Panel: Neue Umfrage erstellen</h2>

<form method="post" id="createSurvey" class="panel panel-admin" action="/db/createSurvey.php">
    <div class="form-row">
        <label>Titel:<br>
            <input type="text" name="title" required class="input-field">
        </label>
    </div>

    <div class="form-row">
        <label>Beschreibung:<br>
            <textarea name="description" rows="3" class="input-field"></textarea>
        </label>
    </div>

    <div class="form-row">
        <label>Ablaufdatum (optional):<br>
            <input type="datetime-local" name="expires_at" class="input-field" style="max-width: 240px;">
        </label>
    </div>

    <h3>Fragen</h3>
    <div id="questions"></div>
    <button type="button" onclick="addQuestion()" class="btn btn-primary" style="margin-top:10px;">Frage hinzufügen</button>
    <br><br>

    <button type="submit" class="btn btn-primary">Umfrage erstellen</button>
</form>

<script>
let qCount = 0;
function addQuestion() {
    qCount++;
    const div = document.createElement('div');
    div.className = 'question-item';
    div.innerHTML = `
        <label>Fragetext ${qCount}:<br>
            <input type="text" name="questions[${qCount}][text]" required class="input-field">
        </label><br>
        <label>Fragetyp:<br>
            <select name="questions[${qCount}][type]" class="input-field">
                <option value="text">Text</option>
                <option value="single">Einzelauswahl</option>
                <option value="multiple">Mehrfachauswahl</option>
                <option value="number">Nummer</option>
            </select>
        </label><br>
        <label>Optionen (kommagetrennt, nur für Auswahlfragen):<br>
            <input type="text" name="questions[${qCount}][options]" placeholder="z.B. Ja,Nein,Vielleicht" class="input-field">
        </label>
        <hr class="divider">
    `;
    document.getElementById('questions').appendChild(div);
}
</script>
