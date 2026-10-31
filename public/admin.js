(() => {
    const tokenKey = 'neet-backend-admin-token';
    const state = { token: sessionStorage.getItem(tokenKey) || '', user: null, subjects: [], currentPage: 1, currentStatus: 'pending_review' };
    const loginScreen = document.getElementById('login-screen');
    const adminShell = document.getElementById('admin-shell');
    const notice = document.getElementById('notice');
    const titles = { dashboard: 'Overview', questions: 'Question bank', resources: 'Study resources', plans: 'Test plans', syllabus: 'Syllabus & weightage' };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
    const number = (value) => new Intl.NumberFormat('en-IN').format(Number(value || 0));
    const token = (value) => { state.token = value || ''; if (value) sessionStorage.setItem(tokenKey, value); else sessionStorage.removeItem(tokenKey); };

    async function api(path, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (state.token) headers.set('Authorization', `Bearer ${state.token}`);
        let body = options.body;
        if (body && !(body instanceof FormData) && typeof body !== 'string') {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(body);
        }
        const response = await fetch(`/api${path}`, { ...options, body, headers });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status === 401 && state.token) {
                token('');
                showLogin('Your admin session expired. Sign in again.');
            }
            const detail = Object.values(result.errors || {}).flat().join(' ');
            throw new Error(detail || result.message || `Request failed (${response.status})`);
        }
        return result;
    }

    function showLogin(message = '') {
        loginScreen.hidden = false;
        adminShell.hidden = true;
        const error = document.getElementById('login-error');
        error.textContent = message;
        error.hidden = !message;
    }

    function showAdmin(user) {
        state.user = user;
        loginScreen.hidden = true;
        adminShell.hidden = false;
        document.getElementById('admin-name').textContent = user.name || 'Administrator';
        document.getElementById('admin-email').textContent = user.email || '';
        document.getElementById('admin-avatar').textContent = (user.name || user.email || 'A').trim().charAt(0).toUpperCase();
        loadSubjects().finally(() => openPage('dashboard'));
    }

    function toast(message) {
        const element = document.getElementById('toast');
        element.textContent = message;
        element.classList.add('show');
        window.setTimeout(() => element.classList.remove('show'), 2600);
    }

    function reportError(error) {
        notice.textContent = error instanceof Error ? error.message : String(error);
        notice.className = 'notice error';
        notice.hidden = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearNotice() { notice.hidden = true; notice.className = 'notice'; }

    async function loadSubjects() {
        try {
            const result = await api('/syllabus');
            state.subjects = result.subjects || [];
        } catch (error) { reportError(error); }
    }

    async function openPage(name) {
        clearNotice();
        document.querySelectorAll('.page-section').forEach((section) => { section.hidden = section.id !== `page-${name}`; });
        document.querySelectorAll('.nav-item').forEach((button) => button.classList.toggle('active', button.dataset.page === name));
        document.getElementById('page-title').textContent = titles[name] || 'Overview';
        try {
            if (name === 'dashboard') await loadDashboard();
            if (name === 'questions') await loadQuestions();
            if (name === 'resources') await loadResources();
            if (name === 'plans') await loadPlans();
            if (name === 'syllabus') await loadSyllabus();
        } catch (error) { reportError(error); }
    }

    async function loadDashboard() {
        const target = document.getElementById('page-dashboard');
        target.innerHTML = '<div class="loading">Loading workspace summary…</div>';
        const [pending, approved, resources, plans] = await Promise.all([
            api('/admin/questions?status=pending_review&per_page=1'),
            api('/admin/questions?status=approved&per_page=1'),
            api('/admin/resources'),
            api('/admin/test-blueprints'),
        ]);
        const blueprintRows = plans.data || [];
        const activePlans = blueprintRows.filter((plan) => plan.is_active);
        const activeResources = (resources.data || []).filter((resource) => resource.is_active);
        target.innerHTML = `
            <div class="page-heading"><div><h2>Good to see you, ${escapeHtml(state.user?.name || 'Administrator')}</h2><p>Review the question bank and keep student preparation resources up to date.</p></div><span class="connection-pill"><i></i> Production API connected</span></div>
            <div class="metric-grid">
                ${metric('Pending review', pending.total, 'Questions waiting for an admin decision', '#a66a12')}
                ${metric('Approved questions', approved.total, 'Available to the student test generator', '#1c7a55')}
                ${metric('Active resources', activeResources.length, 'Links visible in the study catalog', '#2858b5')}
                ${metric('Active test plans', activePlans.length, `${blueprintRows.length} total plans configured`, '#6547a5')}
            </div>
            <div class="dashboard-grid">
                <section class="panel"><div class="panel-title"><div><h3>Admin workspace</h3><p>Open a section to review or update backend content.</p></div></div><div class="shortcut-list">
                    ${shortcut('Question bank', 'Approve sourced questions, add items or import a JSON batch.', 'questions', 'Review questions')}
                    ${shortcut('Study resources', 'Maintain official links, notes, videos and practice sources.', 'resources', `${activeResources.length} active resources`)}
                    ${shortcut('Test plans', 'Update titles and availability for student test plans.', 'plans', `${activePlans.length} active plans`)}
                    ${shortcut('Syllabus & weightage', 'Maintain topics and clearly labelled chapter estimates.', 'syllabus', `${state.subjects.length} subjects loaded`)}
                </div></section>
                <section class="panel"><div class="panel-title"><div><h3>Admin access</h3><p>You are signed in with the backend admin account.</p></div><span class="badge badge-approved">Verified</span></div><div class="note-box">Admin changes are stored in the backend database. New questions remain pending until reviewed. PYQ approval checks the paper source details.</div><a class="student-link" href="https://neetpro.vercel.app/" target="_blank" rel="noreferrer">Open the student workspace <span>↗</span></a></section>
            </div>`;
    }

    function metric(label, value, note, color) {
        return `<article class="metric-card"><div class="metric-label">${escapeHtml(label)}</div><div class="metric-value" style="color:${color}">${number(value)}</div><div class="metric-note">${escapeHtml(note)}</div></article>`;
    }

    function shortcut(title, description, page, note) {
        return `<button class="shortcut" data-page="${page}"><span><strong>${escapeHtml(title)}</strong><small>${escapeHtml(description)}</small></span><span class="shortcut-arrow">→<small>${escapeHtml(note)}</small></span></button>`;
    }

    async function loadQuestions(page = state.currentPage, status = state.currentStatus) {
        state.currentPage = page;
        state.currentStatus = status;
        const target = document.getElementById('page-questions');
        target.innerHTML = '<div class="loading">Loading question bank…</div>';
        const year = document.getElementById('question-year')?.value || '';
        const query = new URLSearchParams({ per_page: '50', page: String(page) });
        if (status) query.set('status', status);
        if (year) query.set('year', year);
        const result = await api(`/admin/questions?${query.toString()}`);
        const questions = result.data || [];
        target.innerHTML = `
            <div class="page-heading"><div><h2>Question bank</h2><p>Review imported or newly added questions before they appear in student tests.</p></div><span class="badge badge-pending">${number(result.total)} matching records</span></div>
            <section class="panel">
                <div class="toolbar">
                    <label>Review status<select id="question-status"><option value="pending_review" ${status === 'pending_review' ? 'selected' : ''}>Pending review</option><option value="approved" ${status === 'approved' ? 'selected' : ''}>Approved</option><option value="rejected" ${status === 'rejected' ? 'selected' : ''}>Rejected</option><option value="" ${status === '' ? 'selected' : ''}>All statuses</option></select></label>
                    <label>PYQ year<input id="question-year" type="number" min="1980" max="2100" value="${escapeHtml(year)}" placeholder="Any year"></label>
                    <button id="question-filter" class="button button-secondary" type="button">Apply filters</button>
                    <button id="question-clear" class="button button-secondary" type="button">Clear year</button>
                </div>
                <details class="details-form"><summary>Add one question</summary>${questionForm()}</details>
                <details class="details-form"><summary>Import a JSON question batch</summary><form id="question-import-form" class="form-grid"><label class="full">JSON file<input type="file" name="file" accept=".json,application/json" required></label><div class="full note-box">Use the question import JSON format with subject/chapter codes, four A–D options and a correct option. Imported questions enter pending review.</div><div class="form-actions full"><button class="button button-primary" type="submit">Import questions</button></div></form></details>
                <div class="table-wrap" style="margin-top:14px"><table><thead><tr><th>Question</th><th>Subject / chapter</th><th>Source</th><th>Status</th><th>Actions</th></tr></thead><tbody>${questions.length ? questions.map(questionRow).join('') : '<tr><td colspan="5" class="empty-state">No questions found for these filters.</td></tr>'}</tbody></table></div>
                <div class="pagination"><span>Page ${number(result.current_page || 1)} of ${number(result.last_page || 1)}</span><span class="row-actions"><button class="button-small" data-question-page="${Math.max(1, (result.current_page || 1) - 1)}" ${result.current_page <= 1 ? 'disabled' : ''}>Previous</button><button class="button-small" data-question-page="${Math.min(result.last_page || 1, (result.current_page || 1) + 1)}" ${result.current_page >= result.last_page ? 'disabled' : ''}>Next</button></span></div>
            </section>`;
    }

    function questionRow(question) {
        const source = question.is_pyq ? `${question.pyq_year || 'PYQ'} · ${question.source_label || 'Source missing'}` : 'Practice question';
        const badge = question.review_status === 'approved' ? 'badge-approved' : question.review_status === 'pending_review' ? 'badge-pending' : 'badge-rejected';
        const actions = question.review_status === 'approved'
            ? `<button class="button-small reject" data-review-id="${question.id}" data-review-status="rejected">Reject</button>`
            : `<button class="button-small approve" data-review-id="${question.id}" data-review-status="approved">Approve</button><button class="button-small reject" data-review-id="${question.id}" data-review-status="rejected">Reject</button>`;
        return `<tr><td><span class="question-stem">${escapeHtml(question.stem)}</span><span class="table-sub">${escapeHtml(question.topic_name || 'No topic')} · ${escapeHtml(question.difficulty || 'medium')}</span><details class="details-form"><summary>Edit question</summary>${questionForm(question)}</details></td><td><strong>${escapeHtml(question.subject?.name || question.subject?.code || '—')}</strong><span class="table-sub">${escapeHtml(question.chapter?.name || 'Chapter unavailable')}</span></td><td>${escapeHtml(source)}<span class="table-sub">${escapeHtml(question.source_page || '')}</span></td><td><span class="badge ${badge}">${escapeHtml(question.review_status.replace('_', ' '))}</span></td><td><div class="row-actions">${actions}</div></td></tr>`;
    }

    function subjectOptions(selected) {
        return state.subjects.map((subject) => `<option value="${subject.id}" ${String(selected) === String(subject.id) ? 'selected' : ''}>${escapeHtml(subject.name)}</option>`).join('');
    }

    function chapterOptions(subjectId, selected) {
        const subject = state.subjects.find((item) => String(item.id) === String(subjectId));
        return (subject?.chapters || []).map((chapter) => `<option value="${chapter.id}" ${String(selected) === String(chapter.id) ? 'selected' : ''}>${escapeHtml(chapter.name)}</option>`).join('');
    }

    function questionForm(question = null) {
        const options = Object.fromEntries((question?.options || []).map((option) => [option.id, option.text]));
        const subjectId = question?.subject_id || question?.subject?.id || state.subjects[0]?.id || '';
        const chapterId = question?.chapter_id || question?.chapter?.id || '';
        const optionInputs = ['A', 'B', 'C', 'D'].map((letter) => `<label>Option ${letter}<input name="option_${letter}" required maxlength="5000" value="${escapeHtml(options[letter] || '')}"></label>`).join('');
        return `<form class="form-grid" data-admin-form="question" data-id="${question?.id || ''}">
            <label class="full">Question stem<textarea name="stem" required maxlength="20000">${escapeHtml(question?.stem || '')}</textarea></label>
            <label>Subject<select name="subject_id" required>${subjectOptions(subjectId)}</select></label>
            <label>Chapter<select name="chapter_id" data-chapter-select required>${chapterOptions(subjectId, chapterId)}</select></label>
            <label>Topic<input name="topic_name" maxlength="255" value="${escapeHtml(question?.topic_name || '')}"></label>
            <label>Difficulty<select name="difficulty"><option value="easy" ${question?.difficulty === 'easy' ? 'selected' : ''}>Easy</option><option value="medium" ${!question || question.difficulty === 'medium' ? 'selected' : ''}>Medium</option><option value="hard" ${question?.difficulty === 'hard' ? 'selected' : ''}>Hard</option></select></label>
            ${optionInputs}
            <label>Correct option<select name="correct_option" required>${['A', 'B', 'C', 'D'].map((letter) => `<option value="${letter}" ${question?.correct_option === letter ? 'selected' : ''}>${letter}</option>`).join('')}</select></label>
            <label>Question type<select name="question_type"><option value="single_correct">Single correct</option><option value="assertion_reason" ${question?.question_type === 'assertion_reason' ? 'selected' : ''}>Assertion / reason</option><option value="statement_based" ${question?.question_type === 'statement_based' ? 'selected' : ''}>Statement based</option></select></label>
            <label class="full">Explanation<textarea name="explanation" maxlength="20000">${escapeHtml(question?.explanation || '')}</textarea></label>
            <label class="check-line full"><input type="checkbox" name="is_pyq" value="1" ${question?.is_pyq ? 'checked' : ''}> This is an actual previous-year question (PYQ)</label>
            <div class="pyq-fields ${question?.is_pyq ? 'show' : ''}">
                <label>PYQ year<input name="pyq_year" type="number" min="1980" max="2100" value="${escapeHtml(question?.pyq_year || '')}"></label>
                <label>Paper / set label<input name="source_label" maxlength="255" value="${escapeHtml(question?.source_label || '')}"></label>
                <label class="full">Official paper URL<input name="source_url" type="url" placeholder="https://neet.nta.nic.in/…" value="${escapeHtml(question?.source_url || '')}"></label>
                <label>PDF page<input name="source_page" maxlength="40" value="${escapeHtml(question?.source_page || '')}"></label>
                <label>Paper question number<input name="source_question_number" maxlength="40" value="${escapeHtml(question?.source_question_number || '')}"></label>
            </div>
            <div class="form-actions full"><button class="button button-primary" type="submit">${question ? 'Save question' : 'Add to review queue'}</button></div>
        </form>`;
    }

    async function loadResources() {
        const target = document.getElementById('page-resources');
        target.innerHTML = '<div class="loading">Loading resources…</div>';
        const result = await api('/admin/resources');
        const resources = result.data || [];
        target.innerHTML = `<div class="page-heading"><div><h2>Study resources</h2><p>Manage the links and materials shown in the student resource catalog.</p></div><span class="badge badge-active">${resources.filter((item) => item.is_active).length} active</span></div>
            <section class="panel"><details class="details-form" open><summary>Add a resource</summary>${resourceForm()}</details><div class="resource-grid" style="margin-top:14px">${resources.length ? resources.map(resourceCard).join('') : '<div class="empty-state">No resources found.</div>'}</div></section>`;
    }

    function resourceForm(resource = null) {
        const subjectId = resource?.subject_id || '';
        const chapterId = resource?.chapter_id || '';
        const types = ['reference', 'syllabus', 'textbook', 'practice', 'pyq_source', 'video', 'notes', 'course'];
        return `<form class="form-grid" data-admin-form="resource" data-id="${resource?.id || ''}">
            <label>Title<input name="title" required maxlength="255" value="${escapeHtml(resource?.title || '')}"></label>
            <label>Provider<input name="provider" required maxlength="255" value="${escapeHtml(resource?.provider || '')}"></label>
            <label class="full">HTTPS URL<input name="url" type="url" required placeholder="https://" value="${escapeHtml(resource?.url || '')}"></label>
            <label>Resource type<select name="resource_type">${types.map((type) => `<option value="${type}" ${resource?.resource_type === type ? 'selected' : ''}>${type.replace('_', ' ')}</option>`).join('')}</select></label>
            <label>Subject<select name="subject_id"><option value="">All subjects</option>${subjectOptions(subjectId)}</select></label>
            <label>Chapter<select name="chapter_id"><option value="">All chapters</option>${chapterOptions(subjectId, chapterId)}</select></label>
            <label>Display order<input name="sort_order" type="number" min="0" value="${escapeHtml(resource?.sort_order || 0)}"></label>
            <label class="full">Short description<textarea name="description" maxlength="5000">${escapeHtml(resource?.description || '')}</textarea></label>
            <label class="full">License / attribution note<input name="license_note" maxlength="1000" value="${escapeHtml(resource?.license_note || '')}"></label>
            <input type="hidden" name="is_official" value="0"><label class="check-line"><input type="checkbox" name="is_official" value="1" ${resource?.is_official ? 'checked' : ''}> Official source</label>
            ${resource ? `<input type="hidden" name="is_active" value="0"><label class="check-line"><input type="checkbox" name="is_active" value="1" ${resource.is_active ? 'checked' : ''}> Active</label>` : ''}
            <div class="form-actions full"><button class="button button-primary" type="submit">${resource ? 'Save resource' : 'Add resource'}</button></div>
        </form>`;
    }

    function resourceCard(resource) {
        return `<article class="data-card"><div class="panel-title"><h3>${escapeHtml(resource.title)}</h3><span class="badge ${resource.is_active ? 'badge-active' : 'badge-inactive'}">${resource.is_active ? 'Active' : 'Archived'}</span></div><p>${escapeHtml(resource.provider)} · ${escapeHtml(resource.resource_type.replace('_', ' '))}${resource.is_official ? ' · Official' : ''}</p><a href="${escapeHtml(resource.url)}" target="_blank" rel="noreferrer">${escapeHtml(resource.url)}</a><div class="inline-stats"><span class="inline-stat">${escapeHtml(resource.subject?.name || 'All subjects')}</span><span class="inline-stat">${escapeHtml(resource.chapter?.name || 'All chapters')}</span></div><details class="details-form"><summary>Edit details</summary>${resourceForm(resource)}</details>${resource.is_active ? `<button class="button button-small reject" data-archive-resource="${resource.id}" style="margin-top:9px">Archive resource</button>` : ''}</article>`;
    }

    async function loadPlans() {
        const target = document.getElementById('page-plans');
        target.innerHTML = '<div class="loading">Loading test plans…</div>';
        const result = await api('/admin/test-blueprints');
        const plans = result.data || [];
        const rows = plans.map((plan) => `<article class="blueprint-row"><div><h3>${escapeHtml(plan.title)}</h3><p>${escapeHtml(plan.cadence)} · ${number(plan.config?.question_count)} questions · ${number(plan.duration_minutes)} minutes · ${escapeHtml(plan.test_category.replaceAll('_', ' '))}${plan.pyq_only ? ' · PYQ only' : ''}</p><p>${Object.entries(plan.config?.subject_counts || {}).map(([key, value]) => `${escapeHtml(key)} ${number(value)}`).join(' · ') || 'Balanced across available subjects'}</p></div><span class="badge ${plan.is_active ? 'badge-active' : 'badge-inactive'}">${plan.is_active ? 'Active' : 'Inactive'}</span><details class="details-form blueprint-edit"><summary>Edit title or availability</summary>${blueprintForm(plan)}</details></article>`).join('');
        target.innerHTML = `<div class="page-heading"><div><h2>Test plans</h2><p>Update student-facing plan names and availability. Question distribution is preserved when a plan is edited.</p></div><span class="badge badge-active">${plans.filter((plan) => plan.is_active).length} active / ${plans.length} total</span></div><section class="panel"><details class="details-form"><summary>Create a balanced test plan</summary>${blueprintForm()}</details><div class="blueprint-list" style="margin-top:14px">${rows || '<div class="empty-state">No test plans configured yet.</div>'}</div></section>`;
    }

    function blueprintForm(plan = null) {
        const config = JSON.stringify(plan?.config || {});
        const categories = ['full_syllabus', 'minor_test', 'semi_major', 'major_test', 'subject_test', 'chapter_test', 'pyq_test'];
        return `<form class="form-grid" data-admin-form="blueprint" data-id="${plan?.id || ''}">
            <label class="full">Plan title<input name="title" required maxlength="255" value="${escapeHtml(plan?.title || '')}"></label>
            ${plan ? `<input type="hidden" name="slug" value="${escapeHtml(plan.slug)}"><input type="hidden" name="cadence" value="${escapeHtml(plan.cadence)}"><input type="hidden" name="test_category" value="${escapeHtml(plan.test_category)}"><input type="hidden" name="config_json" value="${escapeHtml(config)}"><input type="hidden" name="positive_marks" value="${plan.positive_marks}"><input type="hidden" name="negative_marks" value="${plan.negative_marks}"><input type="hidden" name="pyq_only" value="${plan.pyq_only ? '1' : '0'}"><input type="hidden" name="question_count" value="${plan.config?.question_count || 20}"><label>Cadence<input value="${escapeHtml(plan.cadence)}" disabled></label><label>Questions<input value="${number(plan.config?.question_count)} · locked distribution" disabled></label>` : `<label>Cadence<select name="cadence"><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="custom">Custom</option></select></label><label>Test category<select name="test_category">${categories.map((category) => `<option value="${category}">${category.replaceAll('_', ' ')}</option>`).join('')}</select></label><label>Question count<input name="question_count" type="number" min="4" max="180" step="4" value="40" required></label><label>Duration (minutes)<input name="duration_minutes" type="number" min="5" max="300" value="60" required></label><label>Positive marks<input name="positive_marks" type="number" min="0" max="10" value="4" required></label><label>Negative marks<input name="negative_marks" type="number" min="0" max="10" value="1" required></label><label class="check-line"><input type="checkbox" name="pyq_only" value="1"> PYQ questions only</label>`}
            ${plan ? `<label>Duration (minutes)<input name="duration_minutes" type="number" min="5" max="300" value="${plan.duration_minutes}" required></label><input type="hidden" name="is_active" value="0"><label class="check-line"><input type="checkbox" name="is_active" value="1" ${plan.is_active ? 'checked' : ''}> Available to students</label>` : ''}
            <div class="form-actions full"><button class="button button-primary" type="submit">${plan ? 'Save plan' : 'Create plan'}</button></div>
        </form>`;
    }

    async function loadSyllabus() {
        const target = document.getElementById('page-syllabus');
        target.innerHTML = '<div class="loading">Loading syllabus…</div>';
        if (!state.subjects.length) await loadSubjects();
        const subjectSections = state.subjects.map((subject) => `<section class="panel"><div class="panel-title"><div><h3>${escapeHtml(subject.name)}</h3><p>${(subject.chapters || []).length} syllabus chapters</p></div><span class="badge">${escapeHtml(subject.code)}</span></div><div class="chapter-list">${(subject.chapters || []).map((chapter) => chapterCard(subject, chapter)).join('')}</div></section>`).join('');
        target.innerHTML = `<div class="page-heading"><div><h2>Syllabus & chapter weightage</h2><p>Manage syllabus topics and your chapter estimates. Manual weightage remains labelled as an estimate.</p></div></div><div class="note-box">Verified PYQ frequency is calculated separately. A manually entered percentage is an admin estimate, not an official NTA prediction.</div><div class="shortcut-list">${subjectSections || '<div class="empty-state">Syllabus data is unavailable.</div>'}</div>`;
    }

    function chapterCard(subject, chapter) {
        const topics = (chapter.topics || []).map((topic) => `<form class="topic-row" data-admin-form="topic-edit" data-id="${topic.id}"><input name="name" value="${escapeHtml(topic.name)}" required maxlength="5000"><button class="button-small" type="submit">Save</button><button class="button-small reject" type="button" data-delete-topic="${topic.id}">Delete</button></form>`).join('');
        return `<details class="chapter-card"><summary class="chapter-summary"><span><strong>${escapeHtml(chapter.name)}</strong><small>${escapeHtml(chapter.class_level || '')} · ${escapeHtml(chapter.unit_name || '')}</small></span><span class="badge">${number(chapter.weightage_percent)}% estimate</span></summary><div class="chapter-body"><form class="form-grid" data-admin-form="weightage" data-id="${chapter.id}"><label>Weightage %<input name="weightage_percent" type="number" min="0" max="100" step="0.1" value="${escapeHtml(chapter.weightage_percent ?? 0)}" required></label><label>Expected questions<input name="expected_questions" type="number" min="0" max="45" value="${escapeHtml(chapter.expected_questions ?? 0)}"></label><label>Reference year<input name="reference_year" type="number" min="1980" max="2100" value="${escapeHtml(chapter.weightage_reference_year || '')}"></label><div class="form-actions"><button class="button button-secondary" type="submit">Save estimate</button></div></form><div class="topic-list">${topics || '<p class="table-sub">No topics listed yet.</p>'}</div><form class="toolbar" data-admin-form="topic-add"><input type="hidden" name="chapter_id" value="${chapter.id}"><label style="flex:1">Add syllabus topic<input name="name" required maxlength="5000" placeholder="Topic name"></label><button class="button button-secondary" type="submit">Add topic</button></form></div></details>`;
    }

    function formObject(form) {
        const object = Object.fromEntries(new FormData(form).entries());
        form.querySelectorAll('input[type="checkbox"]').forEach((input) => { object[input.name] = input.checked; });
        return object;
    }

    function questionPayload(form) {
        const data = formObject(form);
        data.is_pyq = Boolean(data.is_pyq);
        data.options = ['A', 'B', 'C', 'D'].map((id) => ({ id, text: data[`option_${id}`] || '' }));
        ['option_A', 'option_B', 'option_C', 'option_D'].forEach((key) => delete data[key]);
        if (!data.is_pyq) ['pyq_year', 'source_label', 'source_url', 'source_page', 'source_question_number'].forEach((key) => delete data[key]);
        return data;
    }

    async function submitAdminForm(form) {
        const type = form.dataset.adminForm;
        const id = form.dataset.id;
        if (type === 'question') {
            const method = id ? 'PUT' : 'POST';
            await api(`/admin/questions${id ? `/${id}` : ''}`, { method, body: questionPayload(form) });
            toast(id ? 'Question updated.' : 'Question added to pending review.');
            await loadQuestions(state.currentPage, state.currentStatus);
            return;
        }
        if (type === 'resource') {
            const data = formObject(form);
            ['subject_id', 'chapter_id'].forEach((key) => { if (!data[key]) data[key] = null; });
            data.sort_order = Number(data.sort_order || 0);
            await api(`/admin/resources${id ? `/${id}` : ''}`, { method: id ? 'PUT' : 'POST', body: data });
            toast(id ? 'Resource updated.' : 'Resource added.');
            await loadResources();
            return;
        }
        if (type === 'blueprint') {
            const data = formObject(form);
            let config;
            if (id) {
                config = JSON.parse(data.config_json || '{}');
                data.is_active = Boolean(data.is_active);
                delete data.config_json;
            } else {
                config = { question_count: Number(data.question_count), availability: 'anytime', retakes: 'unlimited', content_scope: 'approved_question_bank' };
                data.config = config;
                data.pyq_only = Boolean(data.pyq_only);
                data.is_active = true;
                delete data.question_count;
            }
            if (id) data.config = config;
            data.duration_minutes = Number(data.duration_minutes);
            data.positive_marks = Number(data.positive_marks || 4);
            data.negative_marks = Number(data.negative_marks || 1);
            if (data.slug === '') delete data.slug;
            delete data.config_json;
            await api(`/admin/test-blueprints${id ? `/${id}` : ''}`, { method: id ? 'PUT' : 'POST', body: data });
            toast(id ? 'Test plan updated.' : 'Test plan created.');
            await loadPlans();
            return;
        }
        if (type === 'weightage') {
            const data = formObject(form);
            data.weightage_percent = Number(data.weightage_percent);
            data.expected_questions = Number(data.expected_questions || 0);
            data.reference_year = data.reference_year ? Number(data.reference_year) : null;
            await api(`/admin/chapters/${id}/weightage`, { method: 'PATCH', body: data });
            toast('Chapter estimate saved.');
            await loadSyllabus();
            return;
        }
        if (type === 'topic-add') {
            const data = formObject(form);
            const chapterId = data.chapter_id;
            delete data.chapter_id;
            await api(`/admin/chapters/${chapterId}/topics`, { method: 'POST', body: data });
            toast('Topic added.');
            await loadSyllabus();
            return;
        }
        if (type === 'topic-edit') {
            await api(`/admin/topics/${id}`, { method: 'PUT', body: formObject(form) });
            toast('Topic updated.');
            await loadSyllabus();
        }
    }

    async function importQuestions(form) {
        const file = form.elements.file.files[0];
        if (!file) return;
        const parsed = JSON.parse(await file.text());
        const rows = Array.isArray(parsed) ? parsed : parsed.questions;
        if (!Array.isArray(rows) || rows.length < 1) throw new Error('Choose a JSON array of question records.');
        const result = await api('/admin/questions/import', { method: 'POST', body: { questions: rows } });
        toast(`Imported ${result.created} · duplicates ${result.duplicates} · invalid ${result.invalid}.`);
        await loadQuestions(1, 'pending_review');
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        event.preventDefault();
        clearNotice();
        try {
            if (form.id === 'login-form') {
                const credentials = Object.fromEntries(new FormData(form).entries());
                const result = await api('/auth/login', { method: 'POST', body: credentials });
                token(result.token);
                if (result.user?.role !== 'admin') {
                    await api('/auth/logout', { method: 'POST' }).catch(() => {});
                    token('');
                    throw new Error('This account has student access. Sign in with an admin account.');
                }
                history.replaceState({}, '', '/admin');
                showAdmin(result.user);
                return;
            }
            if (form.id === 'question-import-form') {
                await importQuestions(form);
                return;
            }
            if (form.matches('[data-admin-form]')) await submitAdminForm(form);
        } catch (error) {
            if (form.id === 'login-form') {
                const message = document.getElementById('login-error');
                message.textContent = error.message;
                message.hidden = false;
            } else { reportError(error); }
        }
    });

    document.addEventListener('click', async (event) => {
        const target = event.target.closest('button');
        if (!target) return;
        if (target.dataset.page) { await openPage(target.dataset.page); return; }
        if (target.id === 'logout-button') {
            await api('/auth/logout', { method: 'POST' }).catch(() => {});
            token(''); state.user = null; history.replaceState({}, '', '/admin/login'); showLogin('You have signed out.'); return;
        }
        if (target.id === 'refresh-button') { await openPage(document.querySelector('.nav-item.active')?.dataset.page || 'dashboard'); return; }
        if (target.id === 'question-filter') { await loadQuestions(1, document.getElementById('question-status').value); return; }
        if (target.id === 'question-clear') { document.getElementById('question-year').value = ''; await loadQuestions(1, document.getElementById('question-status').value); return; }
        if (target.dataset.questionPage) { await loadQuestions(Number(target.dataset.questionPage)); return; }
        if (target.dataset.reviewId) {
            target.disabled = true;
            try { await api(`/admin/questions/${target.dataset.reviewId}/review`, { method: 'PATCH', body: { review_status: target.dataset.reviewStatus } }); toast(`Question marked ${target.dataset.reviewStatus}.`); await loadQuestions(state.currentPage, state.currentStatus); }
            catch (error) { reportError(error); target.disabled = false; }
            return;
        }
        if (target.dataset.archiveResource) {
            if (!window.confirm('Archive this resource? It will be hidden from the active student resource catalog.')) return;
            try { await api(`/admin/resources/${target.dataset.archiveResource}`, { method: 'DELETE' }); toast('Resource archived.'); await loadResources(); } catch (error) { reportError(error); }
            return;
        }
        if (target.dataset.deleteTopic) {
            if (!window.confirm('Delete this syllabus topic?')) return;
            try { await api(`/admin/topics/${target.dataset.deleteTopic}`, { method: 'DELETE' }); toast('Topic deleted.'); await loadSyllabus(); } catch (error) { reportError(error); }
        }
    });

    document.addEventListener('change', (event) => {
        const element = event.target;
        if (!(element instanceof HTMLSelectElement)) return;
        if (element.name === 'subject_id') {
            const form = element.closest('form');
            const chapterSelect = form?.querySelector('[data-chapter-select]') || form?.querySelector('select[name="chapter_id"]');
            if (chapterSelect) chapterSelect.innerHTML = chapterOptions(element.value, '');
        }
        if (element.name === 'is_pyq') {
            const fields = element.closest('form')?.querySelector('.pyq-fields');
            fields?.classList.toggle('show', element.checked);
        }
    });

    document.addEventListener('change', (event) => {
        const element = event.target;
        if (element instanceof HTMLInputElement && element.name === 'is_pyq') {
            element.closest('form')?.querySelector('.pyq-fields')?.classList.toggle('show', element.checked);
        }
    });

    async function init() {
        if (!state.token) { showLogin(); return; }
        try {
            const result = await api('/auth/me');
            if (result.user?.role !== 'admin') { token(''); showLogin('This account does not have admin access.'); return; }
            history.replaceState({}, '', '/admin');
            showAdmin(result.user);
        } catch (error) {
            token('');
            showLogin('Your admin session expired. Sign in again.');
        }
    }

    init();
})();
