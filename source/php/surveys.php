<?php
require_once 'db/db.php';

$stmt = $pdo->query("
    SELECT 
        s.id AS survey_id, 
        s.title, 
        s.description, 
        s.expires_at, 
        s.created_at,
        a.displayname AS creator,
        q.id AS question_id, 
        q.question_text, 
        q.question_type, 
        q.options
    FROM surveys s
    JOIN accounts a ON s.account_id = a.id
    LEFT JOIN survey_questions q ON s.id = q.survey_id
    ORDER BY s.created_at DESC, q.id ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$surveys = [];
foreach ($rows as $row) {
    $sid = $row['survey_id'];
    if (!isset($surveys[$sid])) {
        $surveys[$sid] = [
            'id' => $sid,
            'title' => $row['title'],
            'description' => $row['description'],
            'creator' => $row['creator'],
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
            'questions' => []
        ];
    }

    if ($row['question_id']) {
        $surveys[$sid]['questions'][] = [
            'id' => $row['question_id'],
            'text' => $row['question_text'],
            'type' => $row['question_type'],
            'options' => $row['options'] ? json_decode($row['options'], true) : []
        ];
    }
}

$userVoted = [];
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT DISTINCT survey_id FROM survey_responses WHERE account_id = :uid");
    $stmt->execute(['uid' => $_SESSION['user_id']]);
    $userVoted = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'survey_id');
}
?>

<h1>📋 Verfügbare Umfragen</h1>

<div class="survey-list">
<?php if (empty($surveys)): ?>
    <p>Keine Umfragen vorhanden.</p>
<?php else: ?>
    <?php foreach ($surveys as $s): ?>
        <?php
            $isExpired   = ($s['expires_at'] && strtotime($s['expires_at']) < time());
            $statusClass = $isExpired ? 'expired' : 'active';
            $statusText  = $isExpired ? 'Abgelaufen' : 'Aktiv';
            $hasVoted    = in_array($s['id'], $userVoted);
        ?>
        <div class="survey-card">
            <h2><?= htmlspecialchars($s['title']) ?></h2>

            <?php if (!empty($s['description'])): ?>
                <p><?= nl2br(htmlspecialchars($s['description'])) ?></p>
            <?php endif; ?>

            <div class="survey-meta">
                Von: <strong><?= htmlspecialchars($s['creator']) ?></strong><br>
                Erstellt am: <?= date('d.m.Y H:i', strtotime($s['created_at'])) ?><br>
                <?php if ($s['expires_at']): ?>
                    Läuft ab: <?= date('d.m.Y H:i', strtotime($s['expires_at'])) ?><br>
                <?php endif; ?>
                <span class="status <?= $statusClass ?>"><?= $statusText ?></span>
            </div>

            <?php if ($hasVoted): ?>
                <p class="voted-text">Du hast bereits an dieser Umfrage teilgenommen.</p>

            <?php elseif (!$isExpired && !empty($s['questions'])): ?>
                <form action="db/voteForSurvey.php" method="post" class="vote-form">
                    <input type="hidden" name="survey_id" value="<?= (int)$s['id'] ?>">

                    <?php foreach ($s['questions'] as $q): ?>
                        <div class="question-block">
                            <label><strong><?= htmlspecialchars($q['text']) ?></strong></label><br>

                            <?php if ($q['type'] === 'text'): ?>
                                <input 
                                    type="text" 
                                    name="answers[<?= (int)$q['id'] ?>]" 
                                    class="input-field"
                                    placeholder="Antwort eingeben..."
                                >
                            <?php elseif (in_array($q['type'], ['single', 'multiple'])): ?>
                                <?php foreach ($q['options'] as $opt): ?>
                                    <label class="option-label">
                                        <input 
                                            type="<?= $q['type'] === 'single' ? 'radio' : 'checkbox' ?>"
                                            name="answers[<?= (int)$q['id'] ?>]<?= $q['type']==='multiple'?'[]':'' ?>"
                                            value="<?= htmlspecialchars($opt) ?>"
                                        >
                                        <?= htmlspecialchars($opt) ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php elseif ($q['type'] === 'number'): ?>
                                <input 
                                    type="number" 
                                    name="answers[<?= (int)$q['id'] ?>]" 
                                    class="input-field"
                                    min="0"
                                >
                            <?php endif; ?>
                        </div><br>
                    <?php endforeach; ?>

                    <button type="submit" class="vote-btn">Abstimmen</button>
                </form>

            <?php elseif ($isExpired): ?>
                <p class="expired-text">Umfrage abgelaufen.</p>

            <?php else: ?>
                <p class="no-questions-text">Keine Fragen vorhanden.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>
<script>
(function(){
    function layoutMasonry() {
        const container = document.querySelector('.survey-list');
        if (!container) return;
        const cards = Array.from(container.querySelectorAll('.survey-card'));
        if (cards.length === 0) return;

        const containerWidth = container.clientWidth;
        const gap = 20;
        const minColWidth = 500;
        let colCount = Math.max(1, Math.floor((containerWidth + gap) / (minColWidth + gap)));
        const colWidth = Math.floor((containerWidth - gap * (colCount - 1)) / colCount);

        container.style.position = 'relative';
        const colHeights = new Array(colCount).fill(0);

        cards.forEach(card => {
            card.style.width = colWidth + 'px';
        });

        cards.forEach(card => {
            card.style.position = 'absolute';
            const minIndex = colHeights.indexOf(Math.min(...colHeights));
            const left = (colWidth + gap) * minIndex;
            const top = colHeights[minIndex];
            card.style.left = left + 'px';
            card.style.top = top + 'px';
            card.style.opacity = '1';
            const h = card.offsetHeight;
            colHeights[minIndex] = top + h + gap;
        });

        container.style.height = Math.max(...colHeights) - gap + 'px';
    }

    function debounce(fn, wait){
        let t; return function(){ clearTimeout(t); t = setTimeout(fn, wait); };
    }

    window.addEventListener('load', layoutMasonry);
    window.addEventListener('resize', debounce(layoutMasonry, 100));
})();
</script>

 
