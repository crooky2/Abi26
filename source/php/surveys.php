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

// Sort surveys: active & not answered first, then active & answered, then expired; within groups by created_at DESC
$surveysList = array_values($surveys);
usort($surveysList, function($a, $b) use ($userVoted) {
    $now = time();
    $aExpired = ($a['expires_at'] && strtotime($a['expires_at']) < $now);
    $bExpired = ($b['expires_at'] && strtotime($b['expires_at']) < $now);
    $aVoted = in_array($a['id'], $userVoted);
    $bVoted = in_array($b['id'], $userVoted);

    $rank = function($expired, $voted){
        if (!$expired && !$voted) return 0; // active + not answered
        if (!$expired && $voted)  return 1; // active + answered
        return 2;                            // expired (answered or not)
    };

    $ra = $rank($aExpired, $aVoted);
    $rb = $rank($bExpired, $bVoted);
    if ($ra !== $rb) return $ra <=> $rb;

    // Same group: newest first by created_at desc
    $ta = strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
    $tb = strtotime($b['created_at'] ?? '1970-01-01 00:00:00');
    return $tb <=> $ta;
});
?>

<!-- <h1>Umfragen</h1> -->
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>
<div class="survey-toolbar">
    <input
        type="search"
        id="surveySearch"
        class="survey-search input-field"
        placeholder="Umfragen durchsuchen..."
        aria-label="Umfragen durchsuchen"
    >
</div>

<div class="survey-list">
<?php if (empty($surveys)): ?>
    <p>Keine Umfragen vorhanden.</p>
<?php else: ?>
    <?php foreach ($surveysList as $s): ?>
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

            <?php if ($isExpired): ?>
                <!-- <p class="expired-text">Umfrage abgelaufen.</p> -->
            <?php elseif (empty($s['questions'])): ?>
                <p class="no-questions-text">Keine Fragen vorhanden.</p>
            <?php elseif ($hasVoted): ?>
                <p class="voted-text">Du hast bereits abgestimmt.</p>
                <form method="post" action="/db/deleteMyResponse.php" onsubmit="return confirm('Möchtest du deine Antwort wirklich löschen?');" style="margin-top:8px;">
                    <input type="hidden" name="survey_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn btn-secondary" style="border-color: rgba(239,68,68,0.35); color: #ad1111ff; background: rgba(255, 0, 0, 0.05);">
                        <span class="material-icons-outlined" aria-hidden="true">delete</span>
                        Meine Antwort löschen
                    </button>
                </form>
            <?php else: ?>
                <form method="post" action="/db/voteForSurvey.php">
                    <input type="hidden" name="survey_id" value="<?= (int)$s['id'] ?>">
                    <?php foreach ($s['questions'] as $q): ?>
                        <div class="question-block">
                            <input type="hidden" name="qids[]" value="<?= (int)$q['id'] ?>">
                            <label><strong><?= htmlspecialchars($q['text']) ?></strong></label>
                            <?php if ($q['type'] === 'text'): ?>
                                <input type="text" name="answers[<?= (int)$q['id'] ?>]" class="input-field" required>
                            <?php elseif ($q['type'] === 'number'): ?>
                                <input type="number" name="answers[<?= (int)$q['id'] ?>]" class="input-field" min="0" required>
                            <?php elseif ($q['type'] === 'single' && !empty($q['options'])): ?>
                                <?php foreach ($q['options'] as $idx => $opt): $optId = 'q'.$q['id'].'_opt'.$idx; ?>
                                    <label class="option-label" for="<?= htmlspecialchars($optId) ?>">
                                        <input type="radio" id="<?= htmlspecialchars($optId) ?>" name="answers[<?= (int)$q['id'] ?>]" value="<?= htmlspecialchars($opt) ?>" <?= $idx === 0 ? 'required' : '' ?>> <?= htmlspecialchars($opt) ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php elseif ($q['type'] === 'multiple' && !empty($q['options'])): ?>
                                <?php foreach ($q['options'] as $idx => $opt): $optId = 'q'.$q['id'].'_opt'.$idx; ?>
                                    <label class="option-label" for="<?= htmlspecialchars($optId) ?>">
                                        <input type="checkbox" id="<?= htmlspecialchars($optId) ?>" name="answers[<?= (int)$q['id'] ?>][]" value="<?= htmlspecialchars($opt) ?>" data-multi-group="<?= (int)$q['id'] ?>"> <?= htmlspecialchars($opt) ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <small class="text-muted">Unbekannter Fragetyp.</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="vote-btn">Abstimmen</button>
                </form>
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
        const cards = Array.from(container.querySelectorAll('.survey-card:not(.filtered-out)'));
        if (cards.length === 0) { container.style.height = '0px'; return; }

        const containerWidth = container.clientWidth;
        const gap = 20;
        const minColWidth = 500;
        let colCount = Math.max(1, Math.floor((containerWidth + gap) / (minColWidth + gap)));
        const colWidth = Math.floor((containerWidth - gap * (colCount - 1)) / colCount);

        container.style.position = 'relative';
        const colHeights = new Array(colCount).fill(0);

        cards.forEach(card => { card.style.width = colWidth + 'px'; });

        cards.forEach(card => {
            card.style.position = 'absolute';
            card.style.opacity = '1';
            const minIndex = colHeights.indexOf(Math.min(...colHeights));
            const left = (colWidth + gap) * minIndex;
            const top = colHeights[minIndex];
            card.style.left = left + 'px';
            card.style.top = top + 'px';
            const h = card.offsetHeight;
            colHeights[minIndex] = top + h + gap;
        });

        container.style.height = Math.max(...colHeights) - gap + 'px';
    }

    function debounce(fn, wait){ let t; return function(){ clearTimeout(t); t = setTimeout(fn, wait); }; }

    function applyFilter(q){
        const query = (q || '').toLowerCase().trim();
        const allCards = Array.from(document.querySelectorAll('.survey-card'));
        allCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (!query || text.indexOf(query) !== -1) card.classList.remove('filtered-out');
            else card.classList.add('filtered-out');
        });
        layoutMasonry();
    }

    const searchInput = document.getElementById('surveySearch');
    if (searchInput) {
        const debounced = debounce(() => applyFilter(searchInput.value), 120);
        searchInput.addEventListener('input', debounced);
    }

    function attachVoteValidation(){
        const forms = document.querySelectorAll('form[action="/db/voteForSurvey.php"]');
        forms.forEach(form => {
            form.addEventListener('submit', function(e){
                let firstInvalid = null;
                const qids = Array.from(form.querySelectorAll('input[name="qids[]"]')).map(i => i.value);
                qids.forEach(qid => {
                    const block = form.querySelector(`.question-block input[name="qids[]"][value="${qid}"]`)?.closest('.question-block');
                    const radios = form.querySelectorAll(`input[type="radio"][name="answers[${qid}]"]`);
                    const checks = form.querySelectorAll(`input[type="checkbox"][name="answers[${qid}][]"]`);
                    const text = form.querySelector(`input[type="text"][name="answers[${qid}]"]`);
                    const number = form.querySelector(`input[type="number"][name="answers[${qid}]"]`);

                    let valid = true;
                    if (radios.length > 0) {
                        valid = Array.from(radios).some(r => r.checked);
                    } else if (checks.length > 0) {
                        valid = Array.from(checks).some(c => c.checked);
                    } else if (text) {
                        valid = (text.value || '').trim() !== '';
                    } else if (number) {
                        valid = (number.value !== '');
                    }

                    if (!valid && !firstInvalid) {
                        firstInvalid = block || form;
                    }
                });

                if (firstInvalid) {
                    e.preventDefault();
                    let alert = document.querySelector('.alert.alert-error.inline-validation');
                    if (!alert) {
                        alert = document.createElement('div');
                        alert.className = 'alert alert-error inline-validation';
                        alert.style.marginTop = '10px';
                        alert.textContent = 'Bitte alle Fragen beantworten.';
                        const header = document.querySelector('h1');
                        if (header && header.parentNode) {
                            header.parentNode.insertBefore(alert, header.nextSibling);
                        } else {
                            document.body.prepend(alert);
                        }
                        setTimeout(() => { alert && alert.remove(); }, 4000);
                    } else {
                        alert.textContent = 'Bitte alle Fragen beantworten.';
                    }
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    }

    window.addEventListener('DOMContentLoaded', attachVoteValidation);

    window.addEventListener('load', layoutMasonry);
    window.addEventListener('resize', debounce(layoutMasonry, 100));
})();
</script>

 
