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
<br><br><br>
<h2>Admin</h2>

<form method="post" id="createSurvey" class="panel panel-admin" action="/db/createSurvey.php">
    <div class="form-row">
        <label>Titel:<br>
            <input type="text" name="title" required class="input-field">
        </label>
    </div>

    <div class="form-row">
        <label>Beschreibung (optional):<br>
            <textarea name="description" rows="3" class="input-field"></textarea>
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
(function(){
    let qCounter = 0;

    const form = document.getElementById('createSurvey');
    const list = document.getElementById('questions');
    const addBtn = document.getElementById('addQuestionBtn');

    const expires = document.getElementById('expiresAt');
    if (expires) {
        const pad = (n)=> String(n).padStart(2,'0');
        const d = new Date();
        const local = new Date(d.getTime() - d.getTimezoneOffset()*60000);
        const iso = local.toISOString().slice(0,16);
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
        card.setAttribute('draggable','true');

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

    function wireCardEvents(card){
        const typeSel = card.querySelector('.q-type');
        const optionsWrap = card.querySelector('.q-options');
        const addOptBtn = card.querySelector('.add-option');
        const textInput = card.querySelector('.q-text');

        function ensureOptionsVisible(){
            const t = typeSel.value;
            const show = (t === 'single' || t === 'multiple');
            optionsWrap.style.display = show ? '' : 'none';
        }

        typeSel.addEventListener('change', ensureOptionsVisible);
        ensureOptionsVisible();

        addOptBtn.addEventListener('click', ()=> addOptionRow(card));

        card.querySelector('.q-actions').addEventListener('click', (e)=>{
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
                if (prev) { list.insertBefore(card, prev); renumberQuestions(); }
            } else if (action === 'move-down') {
                const next = card.nextElementSibling;
                if (next) { list.insertBefore(next, card); renumberQuestions(); }
            }
        });

        card.addEventListener('dragstart', (e)=>{
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', ()=>{
            card.classList.remove('dragging');
            renumberQuestions();
        });
        list.addEventListener('dragover', (e)=>{
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
        textInput.addEventListener('input', ()=>{
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
                return { offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function addOptionRow(card, value = ''){
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
        row.querySelector('button').addEventListener('click', ()=>{ row.remove(); });
    }

    function renumberQuestions(){
        const cards = list.querySelectorAll('.question-card');
        let i = 1;
        cards.forEach((card)=>{
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

    function showAlert(msg){
        let alert = form.querySelector('.alert.alert-error.inline-validation');
        if (!alert) {
            alert = document.createElement('div');
            alert.className = 'alert alert-error inline-validation';
            alert.style.marginTop = '10px';
            form.insertBefore(alert, form.firstChild);
        }
        alert.textContent = msg;
        clearTimeout(showAlert._t);
        showAlert._t = setTimeout(()=>{ alert && alert.remove(); }, 5000);
    }

    function compileOptionsIntoHidden(card){
        const vals = [...card.querySelectorAll('.option-input')]
            .map(i => i.value.trim())
            .filter(v => v !== '');
        card.querySelector('.q-options-hidden').value = vals.join(',');
    }

    function addQuestion(prefill){
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

    addBtn.addEventListener('click', ()=> addQuestion());

    form.addEventListener('submit', (e)=>{
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
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (type === 'single' || type === 'multiple') {
                const optionVals = [...card.querySelectorAll('.option-input')].map(i => i.value.trim()).filter(Boolean);
                if (optionVals.length < 2) {
                    e.preventDefault();
                    showAlert('Für Auswahlfragen bitte mindestens zwei Optionen angeben.');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
            }
            compileOptionsIntoHidden(card);
        }
    });

    addQuestion();
})();
</script>