<?php
require_once 'db/db.php';

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
<?php
$surveyRows = [];
try {
    $sStmt = $pdo->query("SELECT s.id, s.title, s.description, s.expires_at, s.created_at, a.displayname AS creator
                          FROM surveys s
                          JOIN accounts a ON s.account_id = a.id
                          ORDER BY s.created_at DESC");
    $surveyRows = $sStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $surveyRows = [];
}

$latestSurveyId = $surveyRows ? (int)$surveyRows[0]['id'] : null;

$qCounts = [];
try {
    $qc = $pdo->query('SELECT survey_id, COUNT(*) AS cnt FROM survey_questions GROUP BY survey_id');
    foreach ($qc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $qCounts[(int)$r['survey_id']] = (int)$r['cnt'];
    }
} catch (PDOException $e) {
}


$rCounts = [];
try {
    $rc = $pdo->query('SELECT survey_id, COUNT(*) AS cnt FROM survey_responses GROUP BY survey_id');
    foreach ($rc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rCounts[(int)$r['survey_id']] = (int)$r['cnt'];
    }
} catch (PDOException $e) {
}

$questionsBySurvey = [];
try {
    $qs = $pdo->query('SELECT survey_id, id, question_text, question_type, options FROM survey_questions ORDER BY id ASC');
    foreach ($qs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['survey_id'];
        $opts = [];
        if (!empty($row['options'])) {
            $decoded = json_decode($row['options'], true);
            if (is_array($decoded)) {
                $opts = $decoded;
            }
        }
        $questionsBySurvey[$sid][] = [
            'id' => (int)$row['id'],
            'text' => $row['question_text'],
            'type' => $row['question_type'],
            'options' => $opts,
        ];
    }
} catch (PDOException $e) {
}

$surveysData = [];
foreach ($surveyRows as $s) {
    $id = (int)$s['id'];
    $surveysData[] = [
        'id' => $id,
        'title' => $s['title'],
        'description' => $s['description'],
        'creator' => $s['creator'],
        'created_at' => $s['created_at'],
        'expires_at' => $s['expires_at'],
        'question_count' => $qCounts[$id] ?? 0,
        'response_count' => $rCounts[$id] ?? 0,
        'questions' => $questionsBySurvey[$id] ?? [],
    ];
}
?>

<div class="admin-grid" style="display:grid; grid-template-columns: minmax(320px,1fr) minmax(520px,1.4fr); gap:16px; align-items:start; margin: 16px 0;">
    <div class="panel" style="padding:16px;">
        <h2 style="margin-top:0;">Umfragen-Info</h2>
        <div style="display:flex; gap:8px; align-items:center;">
            <input type="search" id="adminSurveySearch" class="input-field" placeholder="Umfragen durchsuchen..." aria-label="Umfragen durchsuchen" style="flex:1;">
        </div>
        <div id="adminSurveyResults" style="margin-top:10px; max-height: 240px; overflow:auto; border:1px solid var(--border); border-radius: 10px; padding:6px;"></div>

        <?php
        $latest = $surveysData[0] ?? null;
        $isExpired = $latest && !empty($latest['expires_at']) && strtotime($latest['expires_at']) < time();
        ?>
        <div id="adminSurveyDetails" style="margin-top:14px;">
            <?php if ($latest): ?>
                <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
                    <?= htmlspecialchars($latest['title']) ?>
                    <?php if (!empty($latest['expires_at'])): ?>
                        <span class="status <?= $isExpired ? 'expired' : 'active' ?>">
                            <?= $isExpired ? 'Abgelaufen' : 'Aktiv' ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div class="survey-meta" style="margin-top:4px;">
                    Von <?= htmlspecialchars($latest['creator']) ?> · Erstellt am <?= htmlspecialchars(date('d.m.Y H:i', strtotime($latest['created_at']))) ?>
                    <?php if (!empty($latest['expires_at'])): ?> · Läuft ab am <?= htmlspecialchars(date('d.m.Y H:i', strtotime($latest['expires_at']))) ?><?php endif; ?>
                </div>
                <?php if (!empty($latest['description'])): ?>
                    <p style="margin-top:10px;"><?= nl2br(htmlspecialchars($latest['description'])) ?></p>
                <?php endif; ?>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="status">Fragen: <?= (int)$latest['question_count'] ?></span>
                    <span class="status">Antworten: <?= (int)$latest['response_count'] ?></span>
                </div>
                <?php if (!empty($latest['questions'])): ?>
                    <div style="margin-top:10px;">
                        <?php foreach ($latest['questions'] as $qi => $q): ?>
                            <div class="question-block">
                                <strong><?= ($qi + 1) ?>. <?= htmlspecialchars($q['text']) ?></strong>
                                <small style="margin-left:6px; color: var(--text-muted);">(<?= htmlspecialchars($q['type']) ?>)</small>
                                <?php if (!empty($q['options'])): ?>
                                    <div style="margin-top:4px; display:flex; gap:6px; flex-wrap:wrap;">
                                        <?php foreach ($q['options'] as $opt): ?>
                                            <span class="status" style="font-size: .75rem;"><?= htmlspecialchars($opt) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" id="adminDetailsBtn" class="btn btn-secondary">Details anzeigen</button>
                    <?php if (!$isExpired): ?>
                        <button type="button" id="adminCloseBtn" class="btn btn-primary" data-id="<?= (int)$latest['id'] ?>">Umfrage schließen</button>
                        <button type="button" id="adminResetBtn" class="btn btn-secondary" data-id="<?= (int)$latest['id'] ?>">Daten zurücksetzen</button>
                    <?php endif; ?>
                    <button type="button" id="adminDeleteBtn" class="icon-btn icon-btn-danger" data-id="<?= (int)$latest['id'] ?>">
                        <span class="material-icons-outlined" aria-hidden="true">delete</span>
                        Umfrage löschen
                    </button>
                </div>
            <?php else: ?>
                <p class="survey-meta">Noch keine Umfragen vorhanden.</p>
            <?php endif; ?>
        </div>

        <script>
            window.adminSurveyData = <?php echo json_encode($surveysData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            window.latestSurveyId = <?php echo json_encode($latestSurveyId); ?>;
        </script>
    </div>
    <div class="admin-create">

        <form method="post" id="createSurvey" class="panel panel-admin" action="/db/createSurvey.php">
            <div class="form-row">
                <label>Titel:<br>
                    <input type="text" name="title" required class="input-field">
                </label>
            </div>

            <div class="form-row">
                <label>Beschreibung (optional):<br>
                    <textarea name="description" rows="6" class="input-field" style="resize:none;"></textarea>
                </label>
            </div>

            <div class="form-row">
                <label>Ablaufdatum (optional):<br>
                    <input type="datetime-local" name="expires_at" id="expiresAt" class="input-field" style="max-width: 260px;">
                </label>
            </div>

            <h3 style="margin-top:18px;">Fragen</h3>
            <div id="questions" style="display:flex; flex-direction:column; gap:12px; margin-top:8px;"></div>
            <div style="display:flex; gap:8px; margin-top:10px;">
                <button type="button" id="addQuestionBtn" class="btn btn-primary">Frage hinzufügen</button>
                <button type="submit" class="btn btn-primary" style="margin-left:auto;">Umfrage erstellen</button>
            </div>
        </form>

        <script>
            (function() {
                let qCounter = 0;

                const form = document.getElementById('createSurvey');
                const list = document.getElementById('questions');
                const addBtn = document.getElementById('addQuestionBtn');

                const expires = document.getElementById('expiresAt');
                if (expires) {
                    const pad = (n) => String(n).padStart(2, '0');
                    const d = new Date();
                    const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
                    const iso = local.toISOString().slice(0, 16);
                    expires.min = iso;
                }

                function createQuestionCard() {
                    const idx = qCounter++;
                    const card = document.createElement('div');
                    card.className = 'question-card panel';
                    card.style.padding = '16px';
                    card.style.border = '1px solid var(--border)';
                    card.style.background = 'var(--surface)';
                    card.style.boxShadow = 'var(--shadow-sm)';
                    card.setAttribute('draggable', 'true');

                    card.innerHTML = `
            <div class="q-header" style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="material-icons-outlined q-handle" title="Ziehen zum Sortieren" aria-hidden="true" style="cursor:grab;">drag_indicator</span>
                    <strong class="q-title">Frage</strong>
                </div>
                <div class="q-actions" style="display:flex; gap:6px;">
                    <button type="button" class="icon-btn" data-action="duplicate" title="Duplizieren"><span class="material-icons-outlined" aria-hidden="true">content_copy</span></button>
                    <button type="button" class="icon-btn" data-action="move-up" title="Nach oben"><span class="material-icons-outlined" aria-hidden="true">keyboard_arrow_up</span></button>
                    <button type="button" class="icon-btn" data-action="move-down" title="Nach unten"><span class="material-icons-outlined" aria-hidden="true">keyboard_arrow_down</span></button>
                    <button type="button" class="icon-btn icon-btn-danger" data-action="remove" title="Entfernen"><span class="material-icons-outlined" aria-hidden="true">delete</span></button>
                </div>
            </div>
            <div class="q-body" style="display:grid; grid-template-columns: 1fr 220px; gap:12px; align-items:start;">
                <label style="grid-column: 1 / span 1;">Fragetext:<br>
                    <input type="text" class="q-text input-field" required placeholder="Fragetext">
                </label>
                <label>Fragetyp:<br>
                    <select class="q-type input-field">
                        <option value="text">Text</option>
                        <option value="single">Einzelauswahl</option>
                        <option value="multiple">Mehrfachauswahl</option>
                        <option value="number">Nummer</option>
                    </select>
                </label>
                <div class="q-options" style="grid-column: 1 / span 2; display:none;">
                    <div class="options-list" style="display:flex; flex-direction:column; gap:6px; margin-top:6px;"></div>
                    <button type="button" class="btn btn-secondary add-option" style="margin-top:6px; width:max-content;">Option hinzufügen</button>
                    <input type="hidden" class="q-options-hidden">
                </div>
            </div>
        `;

                    wireCardEvents(card);
                    return card;
                }

                function wireCardEvents(card) {
                    const typeSel = card.querySelector('.q-type');
                    const optionsWrap = card.querySelector('.q-options');
                    const addOptBtn = card.querySelector('.add-option');
                    const textInput = card.querySelector('.q-text');

                    function ensureOptionsVisible() {
                        const t = typeSel.value;
                        const show = (t === 'single' || t === 'multiple');
                        optionsWrap.style.display = show ? '' : 'none';
                    }

                    typeSel.addEventListener('change', ensureOptionsVisible);
                    ensureOptionsVisible();

                    addOptBtn.addEventListener('click', () => addOptionRow(card));

                    card.querySelector('.q-actions').addEventListener('click', (e) => {
                        const btn = e.target.closest('button');
                        if (!btn) return;
                        const action = btn.getAttribute('data-action');
                        if (action === 'remove') {
                            card.remove();
                            renumberQuestions();
                        } else if (action === 'duplicate') {
                            const clone = card.cloneNode(true);
                            wireCardEvents(clone);
                            list.insertBefore(clone, card.nextSibling);
                            renumberQuestions();
                        } else if (action === 'move-up') {
                            const prev = card.previousElementSibling;
                            if (prev) {
                                list.insertBefore(card, prev);
                                renumberQuestions();
                            }
                        } else if (action === 'move-down') {
                            const next = card.nextElementSibling;
                            if (next) {
                                list.insertBefore(next, card);
                                renumberQuestions();
                            }
                        }
                    });

                    card.addEventListener('dragstart', (e) => {
                        card.classList.add('dragging');
                        e.dataTransfer.effectAllowed = 'move';
                    });
                    card.addEventListener('dragend', () => {
                        card.classList.remove('dragging');
                        renumberQuestions();
                    });
                    list.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        const dragging = list.querySelector('.dragging');
                        if (!dragging) return;
                        const after = getDragAfterElement(list, e.clientY);
                        if (after == null) {
                            list.appendChild(dragging);
                        } else {
                            list.insertBefore(dragging, after);
                        }
                    });

                    const title = card.querySelector('.q-title');
                    textInput.addEventListener('input', () => {
                        const val = textInput.value.trim();
                        title.textContent = val ? val : title.textContent.replace(/^Frage \d+$/, title.textContent) || 'Frage';
                    });
                }

                function getDragAfterElement(container, y) {
                    const cards = [...container.querySelectorAll('.question-card:not(.dragging)')];
                    return cards.reduce((closest, child) => {
                        const box = child.getBoundingClientRect();
                        const offset = y - box.top - box.height / 2;
                        if (offset < 0 && offset > closest.offset) {
                            return {
                                offset,
                                element: child
                            };
                        } else {
                            return closest;
                        }
                    }, {
                        offset: Number.NEGATIVE_INFINITY
                    }).element;
                }

                function addOptionRow(card, value = '') {
                    const listEl = card.querySelector('.options-list');
                    const row = document.createElement('div');
                    row.style.display = 'flex';
                    row.style.alignItems = 'center';
                    row.style.gap = '6px';
                    row.innerHTML = `
            <input type="text" class="option-input input-field" placeholder="Option" value="${value.replace(/"/g,'&quot;')}">
            <button type="button" class="icon-btn icon-btn-danger" title="Option entfernen"><span class="material-icons-outlined">close</span></button>
        `;
                    listEl.appendChild(row);
                    row.querySelector('button').addEventListener('click', () => {
                        row.remove();
                    });
                }

                function renumberQuestions() {
                    const cards = list.querySelectorAll('.question-card');
                    let i = 1;
                    cards.forEach((card) => {
                        card.dataset.index = String(i);
                        const title = card.querySelector('.q-title');
                        if (/^Frage( \d+)?$/.test(title.textContent) || !title.textContent.trim()) {
                            title.textContent = `Frage ${i}`;
                        }

                        const qText = card.querySelector('.q-text');
                        const qType = card.querySelector('.q-type');
                        const qOptsHidden = card.querySelector('.q-options-hidden');

                        qText.name = `questions[${i}][text]`;
                        qType.name = `questions[${i}][type]`;
                        qOptsHidden.name = `questions[${i}][options]`;
                        i++;
                    });
                }

                function showAlert(msg) {
                    let alert = form.querySelector('.alert.alert-error.inline-validation');
                    if (!alert) {
                        alert = document.createElement('div');
                        alert.className = 'alert alert-error inline-validation';
                        alert.style.marginTop = '10px';
                        form.insertBefore(alert, form.firstChild);
                    }
                    alert.textContent = msg;
                    clearTimeout(showAlert._t);
                    showAlert._t = setTimeout(() => {
                        alert && alert.remove();
                    }, 5000);
                }

                function compileOptionsIntoHidden(card) {
                    const vals = [...card.querySelectorAll('.option-input')]
                        .map(i => i.value.trim())
                        .filter(v => v !== '');
                    card.querySelector('.q-options-hidden').value = vals.join(',');
                }

                function addQuestion(prefill) {
                    const card = createQuestionCard();
                    if (prefill) {
                        card.querySelector('.q-text').value = prefill.text || '';
                        card.querySelector('.q-type').value = prefill.type || 'text';
                        card.querySelector('.q-type').dispatchEvent(new Event('change'));
                        const opts = prefill.options || [];
                        opts.forEach(o => addOptionRow(card, o));
                    }
                    list.appendChild(card);
                    renumberQuestions();
                    return card;
                }

                addBtn.addEventListener('click', () => addQuestion());

                form.addEventListener('submit', (e) => {
                    const cards = [...list.querySelectorAll('.question-card')];
                    if (cards.length === 0) {
                        e.preventDefault();
                        showAlert('Bitte mindestens eine Frage hinzufügen.');
                        return;
                    }
                    for (const card of cards) {
                        const text = card.querySelector('.q-text').value.trim();
                        const type = card.querySelector('.q-type').value;
                        if (!text) {
                            e.preventDefault();
                            showAlert('Bitte Fragetext ausfüllen.');
                            card.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                            return;
                        }
                        if (type === 'single' || type === 'multiple') {
                            const optionVals = [...card.querySelectorAll('.option-input')].map(i => i.value.trim()).filter(Boolean);
                            if (optionVals.length < 2) {
                                e.preventDefault();
                                showAlert('Für Auswahlfragen bitte mindestens zwei Optionen angeben.');
                                card.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                return;
                            }
                        }
                        compileOptionsIntoHidden(card);
                    }
                });

                addQuestion();
            })();
        </script>

        <script>
            // Info panel: search and details rendering
            (function() {
                const data = Array.isArray(window.adminSurveyData) ? window.adminSurveyData : [];
                const latestId = window.latestSurveyId || (data[0] ? data[0].id : null);
                const listEl = document.getElementById('adminSurveyResults');
                const searchEl = document.getElementById('adminSurveySearch');
                const detailsEl = document.getElementById('adminSurveyDetails');

                function fmtDate(dt) {
                    try {
                        const d = new Date(dt.replace(' ', 'T'));
                        return d.toLocaleString();
                    } catch {
                        return dt;
                    }
                }

                function isExpired(s) {
                    return s.expires_at && (new Date(s.expires_at.replace(' ', 'T')).getTime() < Date.now());
                }

                function renderResults(items) {
                    if (!listEl) return;
                    if (!items || items.length === 0) {
                        listEl.innerHTML = '<div class="survey-meta">Keine Treffer.</div>';
                        return;
                    }
                    listEl.innerHTML = items.map(s => {
                        const expired = isExpired(s);
                        return `
                <div class="result-item" data-id="${s.id}" style="padding:6px; border-radius:8px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <div>
                        <div style="font-weight:600;">${escapeHtml(s.title)}</div>
                        <div class="survey-meta">von ${escapeHtml(s.creator)} • ${escapeHtml(fmtDate(s.created_at))}</div>
                    </div>
                    <span class="status ${expired ? 'expired' : 'active'}" title="Status">${expired ? 'Abgelaufen' : 'Aktiv'}</span>
                </div>
            `;
                    }).join('');
                }

                function escapeHtml(str) {
                    return String(str ?? '').replace(/[&<>"]/g, s => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;'
                    } [s]));
                }

                function renderDetailsById(id) {
                    const s = data.find(x => x.id == id);
                    if (!s || !detailsEl) return;
                    const expired = isExpired(s);
                    detailsEl.innerHTML = `
            <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
                ${escapeHtml(s.title)}
                ${s.expires_at ? `<span class="status ${expired ? 'expired' : 'active'}">${expired ? 'Abgelaufen' : 'Aktiv'}</span>` : ''}
            </h3>
            <div class="survey-meta" style="margin-top:4px;">
                Von ${escapeHtml(s.creator)} · Erstellt am ${escapeHtml(fmtDate(s.created_at))}
                ${s.expires_at ? ` · Läuft ab am ${escapeHtml(fmtDate(s.expires_at))}` : ''}
            </div>
            ${s.description ? `<p style="margin-top:10px;">${escapeHtml(s.description)}</p>` : ''}
                        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" id="adminDetailsBtn" class="btn btn-secondary" data-id="${s.id}">Details anzeigen</button>
                            ${!expired ? `
                                <button type=\"button\" id=\"adminCloseBtn\" class=\"btn btn-primary\" data-id=\"${s.id}\">Umfrage schließen</button>
                                <button type=\"button\" id=\"adminResetBtn\" class=\"btn btn-secondary\" data-id=\"${s.id}\">Daten zurücksetzen</button>
                            ` : ''}
                            <button type="button" id="adminDeleteBtn" class="icon-btn icon-btn-danger" data-id="${s.id}" style="border-color: rgba(239,68,68,0.35); color: #ad1111ff; background: rgba(255, 0, 0, 0.05);">
                                <span class="material-icons-outlined" aria-hidden="true">delete</span>
                                Umfrage löschen
                            </button>
                        </div>
            ${Array.isArray(s.questions) && s.questions.length ? `
                <div style="margin-top:10px;">
                    ${s.questions.map((q, i) => `
                        <div class="question-block">
                            <strong>${i+1}. ${escapeHtml(q.text)}</strong>
                            <small style="margin-left:6px; color: var(--text-muted);">(${escapeHtml(q.type)})</small>
                            ${Array.isArray(q.options) && q.options.length ? `
                                <div style=\"margin-top:4px; display:flex; gap:6px; flex-wrap:wrap;\">
                                    ${q.options.map(o => `<span class=\"status\" style=\"font-size: .75rem;\">${escapeHtml(o)}</span>`).join('')}
                                </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            ` : ''}  
        `;
                    [...listEl.querySelectorAll('.result-item')].forEach(el => el.style.background = el.dataset.id == id ? 'rgba(255,255,255,0.04)' : 'transparent');

                    const btn = detailsEl.querySelector('#adminDetailsBtn');
                    if (btn) btn.addEventListener('click', ()=> loadDetails(s.id));

                    const closeBtn = detailsEl.querySelector('#adminCloseBtn');
                    if (closeBtn) closeBtn.addEventListener('click', ()=> closeSurvey(s.id));

                    const resetBtn = detailsEl.querySelector('#adminResetBtn');
                    if (resetBtn) resetBtn.addEventListener('click', ()=> resetSurvey(s.id));

                    const deleteBtn = detailsEl.querySelector('#adminDeleteBtn');
                    if (deleteBtn) deleteBtn.addEventListener('click', ()=> deleteSurvey(s.id));
                }

                if (listEl) {
                    listEl.addEventListener('click', (e) => {
                        const item = e.target.closest('.result-item');
                        if (!item) return;
                        const id = item.getAttribute('data-id');
                        renderDetailsById(id);
                    });
                }

                if (searchEl) {
                    searchEl.addEventListener('input', () => {
                        const q = searchEl.value.trim().toLowerCase();
                        const items = !q ? data.slice(0, 20) : data.filter(s =>
                            String(s.title || '').toLowerCase().includes(q) ||
                            String(s.creator || '').toLowerCase().includes(q)
                        ).slice(0, 50);
                        renderResults(items);
                    });
                }

                // Initial render
                const initial = data.slice(0, 20);
                renderResults(initial);
                if (latestId) renderDetailsById(latestId);

                async function closeSurvey(id){
                    if (!confirm('Umfrage jetzt schließen? Danach kann niemand mehr abstimmen.')) return;
                    try {
                        const res = await fetch('/db/admin/closeSurvey.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                            body: 'survey_id=' + encodeURIComponent(id)
                        });
                        const text = await res.text();
                        let payload = null;
                        try { payload = text ? JSON.parse(text) : null; } catch(parseErr) {
                            console.error('Close: Non-JSON response', { status: res.status, text });
                            throw new Error(`Unerwartete Antwort (Status ${res.status}).`);
                        }
                        if (!res.ok || (payload && payload.error)) {
                            const msg = payload && payload.message ? payload.message : (payload && payload.error ? payload.error : `HTTP ${res.status}`);
                            console.error('Close: Server error', { status: res.status, payload });
                            throw new Error(msg);
                        }
                        // Update local cache and re-render
                        const idx = data.findIndex(x => x.id == id);
                        if (idx !== -1) {
                            data[idx].expires_at = payload.expires_at || new Date(Date.now()-60000).toISOString().slice(0,19).replace('T',' ');
                        }
                        // Refresh list rendering and details
                        const q = (searchEl && searchEl.value || '').trim().toLowerCase();
                        const items = !q ? data.slice(0, 20) : data.filter(s =>
                            String(s.title || '').toLowerCase().includes(q) || String(s.creator || '').toLowerCase().includes(q)
                        ).slice(0, 50);
                        renderResults(items);
                        renderDetailsById(id);
                    } catch(e) {
                        showInlineError(`Fehler beim Schließen der Umfrage: ${escapeHtml(e.message || String(e))}`);
                    }
                }

                async function resetSurvey(id){
                    if (!confirm('Alle Antworten dieser Umfrage löschen? Das kann nicht rückgängig gemacht werden.')) return;
                    try {
                        const res = await fetch('/db/admin/resetSurveyData.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                            body: 'survey_id=' + encodeURIComponent(id)
                        });
                        const text = await res.text();
                        let payload = null;
                        try { payload = text ? JSON.parse(text) : null; } catch(parseErr) {
                            console.error('Reset: Non-JSON response', { status: res.status, text });
                            throw new Error(`Unerwartete Antwort (Status ${res.status}).`);
                        }
                        if (!res.ok || (payload && payload.error)) {
                            const msg = payload && payload.message ? payload.message : (payload && payload.error ? payload.error : `HTTP ${res.status}`);
                            console.error('Reset: Server error', { status: res.status, payload });
                            throw new Error(msg);
                        }
                        // Update local cache: set response_count to 0
                        const idx = data.findIndex(x => x.id == id);
                        if (idx !== -1) {
                            data[idx].response_count = 0;
                        }
                        // Rerender the list (preserve search) and details
                        const q = (searchEl && searchEl.value || '').trim().toLowerCase();
                        const items = !q ? data.slice(0, 20) : data.filter(s =>
                            String(s.title || '').toLowerCase().includes(q) || String(s.creator || '').toLowerCase().includes(q)
                        ).slice(0, 50);
                        renderResults(items);
                        renderDetailsById(id);
                    } catch(e) {
                        showInlineError(`Fehler beim Zurücksetzen: ${escapeHtml(e.message || String(e))}`);
                    }
                }

                async function deleteSurvey(id){
                    if (!confirm('Diese Umfrage vollständig löschen? Alle Fragen und Antworten werden dauerhaft entfernt.')) return;
                    try {
                        const res = await fetch('/db/admin/deleteSurvey.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                            body: 'survey_id=' + encodeURIComponent(id)
                        });
                        const text = await res.text();
                        let payload = null;
                        try { payload = text ? JSON.parse(text) : null; } catch(parseErr) {
                            console.error('Delete: Non-JSON response', { status: res.status, text });
                            throw new Error(`Unerwartete Antwort (Status ${res.status}).`);
                        }
                        if (!res.ok || (payload && payload.error)) {
                            const msg = payload && payload.message ? payload.message : (payload && payload.error ? payload.error : `HTTP ${res.status}`);
                            console.error('Delete: Server error', { status: res.status, payload });
                            throw new Error(msg);
                        }
                        // Remove from local data and refresh UI
                        const idx = data.findIndex(x => x.id == id);
                        if (idx !== -1) { data.splice(idx, 1); }
                        // Re-render results based on current search
                        const q = (searchEl && searchEl.value || '').trim().toLowerCase();
                        const items = !q ? data.slice(0, 20) : data.filter(s =>
                            String(s.title || '').toLowerCase().includes(q) || String(s.creator || '').toLowerCase().includes(q)
                        ).slice(0, 50);
                        renderResults(items);
                        // Clear details if the deleted survey was shown, or show next available
                        const next = data[0] ? data[0].id : null;
                        if (next) {
                            renderDetailsById(next);
                        } else if (detailsEl) {
                            detailsEl.innerHTML = '<p class="survey-meta">Noch keine Umfragen vorhanden.</p>';
                        }
                    } catch(e) {
                        showInlineError(`Fehler beim Löschen: ${escapeHtml(e.message || String(e))}`);
                    }
                }

                async function loadDetails(id){
                    const url = `/db/admin/surveyDetails.php?survey_id=${encodeURIComponent(id)}`;
                    try {
                        const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }});
                        const text = await res.text();
                        let payload = null;
                        try { payload = text ? JSON.parse(text) : null; } catch(parseErr) {
                            console.error('Details: Non-JSON response', { status: res.status, text });
                            throw new Error(`Unerwartete Antwort (Status ${res.status}).`);
                        }
                        if (!res.ok || (payload && payload.error)) {
                            const msg = payload && payload.message ? payload.message : (payload && payload.error ? payload.error : `HTTP ${res.status}`);
                            console.error('Details: Server error', { status: res.status, payload });
                            throw new Error(msg);
                        }
                        renderDetailOverlay(payload);
                    } catch (e) {
                        console.error('Fehler beim Laden der Details', e);
                        showInlineError(`Fehler beim Laden der Details: ${escapeHtml(e.message || String(e))}`);
                    }
                }

                function showInlineError(msg){
                    const host = document.getElementById('adminSurveyDetails');
                    if (!host) { alert(msg); return; }
                    let el = host.querySelector('.alert.inline-debug');
                    if (!el) {
                        el = document.createElement('div');
                        el.className = 'alert alert-error inline-debug';
                        el.style.marginTop = '10px';
                        host.prepend(el);
                    }
                    el.textContent = msg;
                }

                function renderDetailOverlay(data){
                    const overlay = document.createElement('div');
                    overlay.style.position = 'fixed';
                    overlay.style.inset = '0';
                    overlay.style.background = 'rgba(0,0,0,.55)';
                    overlay.style.zIndex = '2000';

                    const panel = document.createElement('div');
                    panel.className = 'panel';
                    panel.style.position = 'absolute';
                    panel.style.top = '50%';
                    panel.style.left = '50%';
                    panel.style.transform = 'translate(-50%, -50%)';
                    panel.style.maxWidth = '900px';
                    panel.style.width = '90%';
                    panel.style.maxHeight = '80vh';
                    panel.style.overflow = 'auto';
                    panel.style.padding = '16px';

                    const s = data.survey;
                    const header = document.createElement('div');
                    header.style.display = 'flex';
                    header.style.justifyContent = 'space-between';
                    header.style.alignItems = 'center';
                    header.style.gap = '8px';
                    header.innerHTML = `
                        <h3 style="margin:0;">${escapeHtml(s.title)} – Details</h3>
                        <button class="icon-btn" aria-label="Schließen" id="detailCloseBtn"><span class="material-icons-outlined">close</span></button>
                    `;

                    const body = document.createElement('div');
                    body.innerHTML = `
                        <div class="survey-meta">Antworten gesamt: ${Number(data.responses_total||0)}</div>
                        ${data.detail.map(q => `
                            <div class="question-block" style="margin-top:10px;">
                                <strong>${escapeHtml(q.text)}</strong>
                                <small style="margin-left:6px; color: var(--text-muted);">(${escapeHtml(q.type)})</small>
                                ${Object.keys(q.counts||{}).length ? `
                                    <div style=\"margin-top:6px;\">
                                        ${Object.entries(q.counts).map(([ans, cnt]) => `
                                            <div style=\"margin-top:4px;\">
                                                <span class=\"status\">${escapeHtml(ans)}</span>
                                                <span class=\"survey-meta\" style=\"margin-left:6px;\">${cnt} Stimmen</span>
                                                ${Array.isArray(q.voters?.[ans]) && q.voters[ans].length ? `
                                                    <div class=\"survey-meta\" style=\"margin-top:2px;\">
                                                        ${q.voters[ans].map(v => escapeHtml(v.name || v.email || 'Unbekannt')).join(', ')}
                                                    </div>
                                                ` : ''}
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<div class="survey-meta" style="margin-top:6px;">Keine Antworten.</div>'}
                            </div>
                        `).join('')}
                    `;

                    panel.appendChild(header);
                    panel.appendChild(body);
                    overlay.appendChild(panel);
                    document.body.appendChild(overlay);

                    function close(){ overlay.remove(); }
                    overlay.addEventListener('click', (e)=>{ if (e.target === overlay) close(); });
                    const closeBtn = panel.querySelector('#detailCloseBtn');
                    closeBtn && closeBtn.addEventListener('click', close);
                    document.addEventListener('keydown', function onKey(e){ if(e.key==='Escape'){ close(); document.removeEventListener('keydown', onKey); }});
                }
            })();
        </script>

    </div>
</div>