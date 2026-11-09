<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SchoolPilot brings school administration, academics and student records together in one simple platform.">
    <title>SchoolPilot | Smarter School Management</title>
    <style>
        :root { color-scheme: light; --navy:#10243d; --blue:#1768a8; --teal:#12a89d; --ink:#172b43; --muted:#64748b; --line:#e5edf4; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; color:var(--ink); background:#f7fafc; }
        a { color:inherit; text-decoration:none; }
        .wrap { width:min(1120px, calc(100% - 40px)); margin:auto; }
        header { background:#fff; border-bottom:1px solid var(--line); }
        .nav { min-height:76px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
        .brand { display:flex; align-items:center; gap:11px; font-size:1.2rem; font-weight:800; color:var(--navy); letter-spacing:-.04em; }
        .mark { width:38px; height:38px; display:grid; place-items:center; border-radius:12px; color:#fff; font-weight:900; background:linear-gradient(135deg,var(--blue),var(--teal)); }
        .nav-link { color:var(--muted); font-size:.94rem; font-weight:650; }
        .nav-cta,.button { display:inline-flex; align-items:center; justify-content:center; border-radius:10px; padding:13px 19px; font-weight:750; background:var(--blue); color:#fff; box-shadow:0 8px 20px #1768a826; }
        .hero { padding:82px 0 72px; background:radial-gradient(circle at 85% 15%,#d9f5f0 0,transparent 27%),linear-gradient(135deg,#f8fbff,#edf6fb); }
        .hero-grid { display:grid; grid-template-columns:1.08fr .92fr; align-items:center; gap:54px; }
        .eyebrow { display:inline-flex; padding:8px 12px; border-radius:999px; color:#087f78; background:#dff7f2; font-size:.78rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        h1 { max-width:650px; margin:20px 0; color:var(--navy); font-size:clamp(2.7rem,5.3vw,4.6rem); line-height:1.04; letter-spacing:-.065em; }
        .lead { max-width:590px; color:var(--muted); font-size:1.13rem; line-height:1.8; }
        .actions { display:flex; flex-wrap:wrap; align-items:center; gap:15px; margin-top:30px; }
        .secondary { color:var(--blue); font-weight:750; padding:12px 4px; }
        .preview { padding:24px; border:1px solid #e1eaf1; border-radius:22px; background:#fff; box-shadow:0 28px 70px #183b5a18; }
        .preview-top { display:flex; align-items:center; justify-content:space-between; padding-bottom:18px; border-bottom:1px solid var(--line); }
        .preview-title { font-weight:800; color:var(--navy); }
        .live { color:#087f65; background:#e2f8ef; padding:6px 10px; border-radius:999px; font-size:.72rem; font-weight:800; }
        .stats { display:grid; grid-template-columns:repeat(2,1fr); gap:13px; margin:20px 0; }
        .stat { padding:18px; border-radius:14px; background:#f4f8fb; }
        .stat strong { display:block; font-size:1.65rem; color:var(--navy); }
        .stat span { color:var(--muted); font-size:.82rem; }
        .activity { display:flex; gap:12px; align-items:center; padding:13px 0; border-top:1px solid var(--line); color:var(--muted); font-size:.88rem; }
        .dot { width:9px; height:9px; border-radius:50%; background:var(--teal); flex:none; }
        .features { padding:72px 0 82px; background:#fff; }
        .section-head { max-width:650px; margin:0 auto 35px; text-align:center; }
        h2 { margin:0 0 12px; color:var(--navy); font-size:clamp(1.8rem,3vw,2.5rem); letter-spacing:-.045em; }
        .section-head p { color:var(--muted); line-height:1.7; }
        .cards { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; }
        .card { padding:25px; border:1px solid var(--line); border-radius:16px; background:#fff; }
        .card-icon { width:42px; height:42px; display:grid; place-items:center; border-radius:12px; background:#e9f5fb; color:var(--blue); font-size:1.2rem; }
        .card h3 { margin:17px 0 8px; color:var(--navy); }
        .card p { margin:0; color:var(--muted); line-height:1.7; font-size:.93rem; }
        .bottom { padding:55px 0; background:var(--navy); color:#fff; text-align:center; }
        .bottom h2 { color:#fff; }
        .bottom p { color:#c4d2df; margin:10px 0 24px; }
        .bottom .button { background:var(--teal); box-shadow:none; }
        footer { padding:22px 0; color:#8da0b3; background:#0b1b2d; font-size:.82rem; text-align:center; }
        @media(max-width:760px) { .hero { padding:58px 0; } .hero-grid { grid-template-columns:1fr; gap:35px; } .cards { grid-template-columns:1fr; } .nav { min-height:68px; } .nav-link { display:none; } }
    </style>
</head>
<body>
<header>
    <div class="wrap nav">
        <a class="brand" href="/" aria-label="SchoolPilot home"><span class="mark">S</span>SchoolPilot</a>
        <a class="nav-link" href="#features">Platform features</a>
        <a class="nav-cta" href="/school/greenfield/login">Demo school login</a>
    </div>
</header>
<main>
    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow">School management, made clearer</span>
                <h1>Spend less time managing. More time helping students grow.</h1>
                <p class="lead">SchoolPilot brings the everyday work of running a school into one organised place — from student and staff records to classes, academic sessions, scores and results.</p>
                <div class="actions">
                    <a class="button" href="/school/greenfield/login">Enter Demo School <span aria-hidden="true">&nbsp;→</span></a>
                    <a class="secondary" href="#features">Explore the platform</a>
                </div>
            </div>
            <div class="preview" aria-label="Illustrative SchoolPilot dashboard preview">
                <div class="preview-top"><span class="preview-title">School overview</span><span class="live">DEMO PREVIEW</span></div>
                <div class="stats">
                    <div class="stat"><strong>Students</strong><span>Central student records</span></div>
                    <div class="stat"><strong>Staff</strong><span>People and role management</span></div>
                    <div class="stat"><strong>Classes</strong><span>Academic organisation</span></div>
                    <div class="stat"><strong>Results</strong><span>Scores and reporting</span></div>
                </div>
                <div class="activity"><span class="dot"></span> Manage school information in one workspace</div>
                <div class="activity"><span class="dot"></span> Organise academic sessions and terms</div>
                <div class="activity"><span class="dot"></span> Record scores and prepare results</div>
            </div>
        </div>
    </section>
    <section class="features" id="features">
        <div class="wrap">
            <div class="section-head">
                <h2>One platform for essential school operations</h2>
                <p>A connected workspace designed to make school administration more organised and everyday academic tasks easier to follow.</p>
            </div>
            <div class="cards">
                <article class="card"><div class="card-icon" aria-hidden="true">▣</div><h3>Student and staff records</h3><p>Keep key school records organised and make it easier for authorised staff to manage information.</p></article>
                <article class="card"><div class="card-icon" aria-hidden="true">▦</div><h3>Academic management</h3><p>Set up sessions, terms, classes and subjects in a structured school workspace.</p></article>
                <article class="card"><div class="card-icon" aria-hidden="true">✓</div><h3>Scores and results</h3><p>Support score entry, review workflows and class result reporting from one place.</p></article>
            </div>
        </div>
    </section>
    <section class="bottom">
        <div class="wrap">
            <h2>Ready to explore SchoolPilot?</h2>
            <p>Open the Greenfield Demo School portal and take a look around.</p>
            <a class="button" href="/school/greenfield/login">Enter Demo School →</a>
        </div>
    </section>
</main>
<footer><div class="wrap">© {{ date('Y') }} SchoolPilot. School management made clearer.</div></footer>
</body>
</html>
