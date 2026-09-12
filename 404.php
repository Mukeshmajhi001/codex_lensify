<?php
require_once __DIR__ . '/app/bootstrap.php';
http_response_code(404);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="The Lensify page you requested could not be found.">
    <title>Page not found · Lensify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#151c27; --paper:#f9f9ff; --mist:#f0f3ff; --line:#e5e7eb; --muted:#697386; }
        * { box-sizing:border-box; }
        html, body { min-height:100%; }
        body { margin:0; background:var(--paper); color:var(--ink); font-family:Inter, sans-serif; }
        .page { min-height:100vh; display:flex; flex-direction:column; }
        .topbar { display:flex; justify-content:space-between; align-items:center; padding:24px clamp(20px, 5vw, 72px); border-bottom:1px solid var(--line); }
        .brand { color:var(--ink); font-size:1.25rem; font-weight:800; letter-spacing:-.08em; text-decoration:none; }
        .brand span { color:#8a93a5; }
        .top-link { color:var(--ink); font-size:.75rem; font-weight:700; text-decoration:none; text-transform:uppercase; letter-spacing:.12em; }
        .main { width:min(1120px, 100%); flex:1; display:grid; grid-template-columns:1fr 1fr; align-items:center; gap:clamp(32px, 8vw, 110px); margin:auto; padding:72px clamp(20px, 5vw, 72px); }
        .label { color:#697386; font-family:'DM Mono', monospace; font-size:.72rem; letter-spacing:.16em; text-transform:uppercase; }
        .code { margin:18px 0 0; color:#dfe4f1; font-family:'DM Mono', monospace; font-size:clamp(6rem, 16vw, 12rem); font-weight:500; letter-spacing:-.12em; line-height:.75; }
        h1 { max-width:470px; margin:30px 0 0; font-size:clamp(2rem, 4vw, 3.6rem); line-height:1.02; letter-spacing:-.075em; }
        p { max-width:440px; margin:20px 0 0; color:var(--muted); font-size:1rem; line-height:1.8; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:32px; }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:48px; padding:0 20px; border-radius:10px; font-size:.8rem; font-weight:800; text-decoration:none; }
        .button-primary { background:var(--ink); color:#fff; }
        .button-secondary { border:1px solid var(--line); color:var(--ink); background:#fff; }
        .visual { position:relative; display:grid; place-items:center; min-height:390px; border-radius:28px; background:var(--mist); overflow:hidden; }
        .visual::before, .visual::after { content:''; position:absolute; border:1px solid rgba(21,28,39,.09); border-radius:50%; }
        .visual::before { width:330px; height:330px; }
        .visual::after { width:220px; height:220px; }
        .glasses { position:relative; z-index:1; display:flex; gap:18px; transform:rotate(-8deg); }
        .lens { width:116px; height:82px; border:8px solid var(--ink); border-radius:44% 44% 48% 48%; background:rgba(255,255,255,.5); box-shadow:inset 0 0 0 8px rgba(255,255,255,.35); }
        .bridge { width:44px; height:10px; margin-top:34px; background:var(--ink); border-radius:99px; }
        .arm { position:absolute; top:34px; width:84px; height:8px; background:var(--ink); border-radius:99px; }
        .arm.left { left:-72px; transform:rotate(-12deg); }
        .arm.right { right:-72px; transform:rotate(12deg); }
        .visual-note { position:absolute; right:22px; bottom:20px; color:#9aa3b4; font-family:'DM Mono', monospace; font-size:.68rem; letter-spacing:.12em; text-transform:uppercase; }
        .footer { padding:24px clamp(20px, 5vw, 72px); color:#9aa3b4; font-family:'DM Mono', monospace; font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; }
        @media (max-width:760px) { .main { grid-template-columns:1fr; padding-top:48px; padding-bottom:48px; } .visual { order:-1; min-height:280px; } .lens { width:82px; height:60px; border-width:6px; } .bridge { width:32px; height:7px; margin-top:26px; } .arm { top:25px; width:60px; height:6px; } .arm.left { left:-52px; } .arm.right { right:-52px; } .visual::before { width:250px; height:250px; } .visual::after { width:170px; height:170px; } }
        @media (max-width:420px) { .topbar { padding:20px; } .top-link { font-size:.65rem; } .main { padding:36px 20px; } h1 { margin-top:22px; } .button { flex:1; } }
    </style>
</head>

<body>
    <div class="page">
        <header class="topbar">
            <a class="brand" href="<?= h(url()) ?>">lens<span>ify</span></a>
            <a class="top-link" href="<?= h(url('shop.php')) ?>">Browse frames</a>
        </header>
        <main class="main">
            <section>
                <div class="label">Lensify / error 404</div>
                <div class="code" aria-hidden="true">404</div>
                <h1>This page is out of focus.</h1>
                <p>The page you were looking for may have moved, expired, or never existed. Let’s get you back to frames that fit.</p>
                <div class="actions">
                    <a class="button button-primary" href="<?= h(url()) ?>">Back to home</a>
                    <a class="button button-secondary" href="<?= h(url('contact.php')) ?>">Contact us</a>
                </div>
            </section>
            <div class="visual" aria-label="Abstract eyeglasses illustration">
                <div class="glasses" aria-hidden="true">
                    <span class="arm left"></span><span class="lens"></span><span class="bridge"></span><span class="lens"></span><span class="arm right"></span>
                </div>
                <span class="visual-note">clear vision ahead</span>
            </div>
        </main>
        <footer class="footer">Premium eyewear for modern vision</footer>
    </div>
</body>

</html>
