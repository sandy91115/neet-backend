<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f4f6f9">
    <title>NEET Prep Pro · Admin Studio</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <script src="{{ asset('admin.js') }}" defer></script>
</head>
<body>
    <div id="toast" class="toast" role="status" aria-live="polite"></div>

    <main id="login-screen" class="login-screen">
        <section class="login-card">
            <a class="brand" href="/admin" aria-label="NEET Prep Pro admin home">
                <span class="brand-mark">N</span>
                <span><strong>NEET PREP PRO</strong><small>ADMIN STUDIO</small></span>
            </a>
            <div class="login-copy">
                <span class="eyebrow">SECURE ADMIN ACCESS</span>
                <h1>Manage your preparation workspace.</h1>
                <p>Sign in with the administrator account created for this backend.</p>
            </div>
            <form id="login-form" class="stack-form">
                <label>Email address<input type="email" name="email" autocomplete="username" required placeholder="admin@example.com"></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" required placeholder="Enter your password"></label>
                <button class="button button-primary button-wide" type="submit">Sign in to Admin Studio <span aria-hidden="true">→</span></button>
                <p id="login-error" class="form-error" hidden></p>
            </form>
            <p class="login-foot">Admin accounts are created by the site owner. Student registration does not grant admin access.</p>
        </section>
        <aside class="login-aside">
            <div class="aside-orbit orbit-one"></div><div class="aside-orbit orbit-two"></div>
            <div class="aside-content">
                <span class="eyebrow eyebrow-light">YOUR CONTROL ROOM</span>
                <h2>One place for questions, resources and test plans.</h2>
                <div class="aside-points"><span>01 <b>Review sourced questions</b></span><span>02 <b>Maintain the study catalog</b></span><span>03 <b>Keep test plans ready</b></span></div>
            </div>
            <span class="aside-caption">NEET PREP PRO · ADMINISTRATION</span>
        </aside>
    </main>

    <div id="admin-shell" class="admin-shell" hidden>
        <aside class="sidebar">
            <a class="brand sidebar-brand" href="/admin">
                <span class="brand-mark">N</span>
                <span><strong>NEET PREP PRO</strong><small>ADMIN STUDIO</small></span>
            </a>
            <div class="sidebar-label">WORKSPACE</div>
            <nav class="side-nav" aria-label="Admin sections">
                <button class="nav-item active" data-page="dashboard"><span class="nav-icon">⌂</span>Overview</button>
                <button class="nav-item" data-page="questions"><span class="nav-icon">▤</span>Question bank</button>
                <button class="nav-item" data-page="resources"><span class="nav-icon">▧</span>Study resources</button>
                <button class="nav-item" data-page="plans"><span class="nav-icon">▦</span>Test plans</button>
                <button class="nav-item" data-page="syllabus"><span class="nav-icon">▥</span>Syllabus & weightage</button>
            </nav>
            <div class="sidebar-bottom">
                <a class="student-link" href="https://neetpro.vercel.app/" target="_blank" rel="noreferrer">Open student workspace <span>↗</span></a>
                <div class="admin-mini"><span class="avatar" id="admin-avatar">A</span><span class="admin-mini-copy"><strong id="admin-name">Administrator</strong><small id="admin-email">Connected account</small></span><button id="logout-button" class="icon-button" type="button" aria-label="Sign out" title="Sign out">↪</button></div>
            </div>
        </aside>

        <div class="main-column">
            <header class="topbar">
                <div><span class="topbar-kicker">NEET PREP PRO / ADMIN</span><h1 id="page-title">Overview</h1></div>
                <div class="topbar-actions"><span class="connection-pill"><i></i> Backend connected</span><button id="refresh-button" class="button button-secondary" type="button">Refresh data</button></div>
            </header>
            <div id="notice" class="notice" hidden></div>
            <main class="content-area">
                <section id="page-dashboard" class="page-section"></section>
                <section id="page-questions" class="page-section" hidden></section>
                <section id="page-resources" class="page-section" hidden></section>
                <section id="page-plans" class="page-section" hidden></section>
                <section id="page-syllabus" class="page-section" hidden></section>
            </main>
            <footer class="main-footer"><span>NEET Prep Pro · Backend Admin Studio</span><span>Changes are saved to the backend database.</span></footer>
        </div>
    </div>
</body>
</html>
