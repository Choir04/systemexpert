<?php
require_once __DIR__ . '/config/database.php';

$stFakta       = $pdo->query('SELECT COUNT(*) AS total FROM fakta WHERE aktif = 1');
$stPelanggaran = $pdo->query('SELECT COUNT(*) AS total FROM pelanggaran WHERE aktif = 1');
$stRules       = $pdo->query('SELECT COUNT(*) AS total FROM rules WHERE aktif = 1');
$stKonsultasi  = $pdo->query("SELECT COUNT(*) AS total FROM konsultasi WHERE status = 'selesai'");

$cFakta       = (int)$stFakta->fetchColumn();
$cPelanggaran = (int)$stPelanggaran->fetchColumn();
$cRules       = (int)$stRules->fetchColumn();
$cKonsultasi  = (int)$stKonsultasi->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sistem Pakar Pelanggaran Lalu Lintas</title>
<meta name="description" content="Sistem pakar berbasis backward chaining untuk mendeteksi pelanggaran lalu lintas.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0a0f1e;--card:#141c2e;--border:#1e2d45;
    --accent:#3b82f6;--accent2:#6366f1;--green:#10b981;
    --text:#f1f5f9;--muted:#64748b;--r:16px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;
    background-image:
        radial-gradient(ellipse 80% 50% at 50% -20%,rgba(59,130,246,.15) 0%,transparent 60%),
        radial-gradient(ellipse 60% 40% at 90% 110%,rgba(99,102,241,.1) 0%,transparent 50%);
}
nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:18px 40px;border-bottom:1px solid var(--border);
    background:rgba(10,15,30,.8);backdrop-filter:blur(12px);
    position:sticky;top:0;z-index:99;
}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1.05rem;text-decoration:none;color:var(--text)}
.brand-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:grid;place-items:center;font-size:17px}
.nav-links{display:flex;gap:6px;align-items:center}
.nav-links a{padding:8px 15px;border-radius:8px;font-size:.84rem;font-weight:500;color:var(--muted);text-decoration:none;transition:.2s}
.nav-links a:hover{color:var(--text);background:rgba(255,255,255,.06)}
.nav-links a.btn-nav{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:600}
.nav-links a.btn-nav:hover{opacity:.88}

.hero{max-width:860px;margin:80px auto 50px;text-align:center;padding:0 24px}
.hero-badge{
    display:inline-flex;align-items:center;gap:6px;padding:5px 16px;
    background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.25);
    border-radius:99px;font-size:.73rem;font-weight:700;color:#93c5fd;
    letter-spacing:.07em;text-transform:uppercase;margin-bottom:22px;
}
.hero h1{
    font-size:clamp(2rem,5vw,3.2rem);font-weight:800;line-height:1.15;
    background:linear-gradient(135deg,#fff 30%,#94a3b8);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;margin-bottom:18px;
}
.hero p{color:var(--muted);font-size:1.05rem;line-height:1.7;max-width:580px;margin:0 auto 36px}
.hero-actions{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap}
.hero-cta{
    display:inline-flex;align-items:center;gap:9px;padding:15px 32px;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#fff;font-family:inherit;font-size:1rem;font-weight:700;
    border:none;border-radius:12px;text-decoration:none;transition:.2s;cursor:pointer;
}
.hero-cta:hover{opacity:.87;transform:translateY(-3px);box-shadow:0 12px 32px rgba(59,130,246,.35)}
.hero-cta-outline{
    display:inline-flex;align-items:center;gap:9px;padding:15px 28px;
    background:transparent;color:var(--muted);font-family:inherit;font-size:1rem;
    font-weight:600;border:1.5px solid var(--border);border-radius:12px;
    text-decoration:none;transition:.2s;
}
.hero-cta-outline:hover{color:var(--text);border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.03)}

.stats{
    display:flex;justify-content:center;gap:0;margin:50px auto;max-width:760px;
    background:var(--card);border:1px solid var(--border);border-radius:var(--r);
    overflow:hidden;
}
.stat{flex:1;text-align:center;padding:26px 16px;border-right:1px solid var(--border)}
.stat:last-child{border-right:none}
.stat-num{
    font-size:1.9rem;font-weight:800;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;
}
.stat-lbl{font-size:.78rem;color:var(--muted);margin-top:4px;font-weight:500}

.features{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:16px;max-width:860px;margin:0 auto 60px;padding:0 24px;
}
.feat{
    background:var(--card);border:1px solid var(--border);border-radius:var(--r);
    padding:24px;position:relative;overflow:hidden;transition:.2s;
}
.feat:hover{border-color:rgba(59,130,246,.35);transform:translateY(-3px)}
.feat::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,rgba(59,130,246,.04) 0%,transparent 55%);
    pointer-events:none;
}
.feat-icon{font-size:1.8rem;margin-bottom:14px}
.feat-title{font-size:.97rem;font-weight:700;margin-bottom:6px}
.feat-desc{font-size:.82rem;color:var(--muted);line-height:1.55}

.sec-label{
    text-align:center;font-size:.72rem;font-weight:700;color:var(--muted);
    letter-spacing:.08em;text-transform:uppercase;margin-bottom:18px;
}
.admin-grid{
    display:flex;justify-content:center;gap:12px;flex-wrap:wrap;
    max-width:860px;margin:0 auto;padding:0 24px 80px;
}
.admin-btn{
    display:inline-flex;align-items:center;gap:8px;padding:11px 20px;
    background:var(--card);border:1px solid var(--border);border-radius:10px;
    color:var(--muted);text-decoration:none;font-size:.85rem;font-weight:500;transition:.2s;
}
.admin-btn:hover{color:var(--text);border-color:rgba(255,255,255,.15);background:rgba(255,255,255,.04)}
</style>
</head>
<body>
<nav>
    <a href="index.php" class="brand">
        <span class="brand-icon">⚖️</span>
        Sistem Pakar Lalu Lintas
    </a>
    <div class="nav-links">
        <a href="riwayat.php">Riwayat</a>
        <a href="admin/rules.php">Admin</a>
        <a href="konsultasi.php" class="btn-nav">+ Konsultasi Baru</a>
    </div>
</nav>

<div class="hero">
    <div class="hero-badge">⚡ Backward Chaining AI</div>
    <h1>Sistem Pakar<br>Pelanggaran Lalu Lintas</h1>
    <p>Identifikasi potensi pelanggaran lalu lintas secara terstruktur menggunakan metode backward chaining — mulai dari hipotesis pelanggaran, pengujian fakta lapangan, hingga verifikasi aturan hukum.</p>
    <div class="hero-actions">
        <a href="konsultasi.php" class="hero-cta">Mulai Konsultasi →</a>
        <a href="riwayat.php" class="hero-cta-outline">📂 Riwayat Konsultasi</a>
        <a href="admin/rules.php" class="hero-cta-outline">⚙️ Panel Admin</a>
    </div>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat-num"><?= $cFakta ?></div>
        <div class="stat-lbl">Fakta Aktif</div>
    </div>
    <div class="stat">
        <div class="stat-num"><?= $cPelanggaran ?></div>
        <div class="stat-lbl">Jenis Pelanggaran</div>
    </div>
    <div class="stat">
        <div class="stat-num"><?= $cRules ?></div>
        <div class="stat-lbl">Rule Aktif</div>
    </div>
    <div class="stat">
        <div class="stat-num"><?= $cKonsultasi ?></div>
        <div class="stat-lbl">Total Konsultasi</div>
    </div>
</div>

<div class="features">
    <div class="feat">
        <div class="feat-icon">🎯</div>
        <div class="feat-title">Backward Chaining</div>
        <div class="feat-desc">Sistem bekerja dari hipotesis ke fakta, memverifikasi prasyarat secara berurutan dan mengabaikan fakta yang tidak relevan.</div>
    </div>
    <div class="feat">
        <div class="feat-icon">💬</div>
        <div class="feat-title">Tanya Jawab Adaptif</div>
        <div class="feat-desc">Pertanyaan diajukan langkah demi langkah berdasarkan kondisi rule aktif, menghemat waktu proses konsultasi.</div>
    </div>
    <div class="feat">
        <div class="feat-icon">📜</div>
        <div class="feat-title">Referensi Hukum</div>
        <div class="feat-desc">Hasil analisis dilengkapi pasal hukum (UU LLAJ) sebagai rujukan informatif dan edukatif.</div>
    </div>
    <div class="feat">
        <div class="feat-icon">💾</div>
        <div class="feat-title">Tersimpan di MySQL</div>
        <div class="feat-desc">Setiap sesi, jawaban fakta, dan kesimpulan tersimpan aman dan dapat ditinjau kembali di riwayat.</div>
    </div>
</div>

<div class="sec-label">Panel Administrasi Basis Pengetahuan</div>
<div class="admin-grid">
    <a href="admin/fakta.php" class="admin-btn">📋 Kelola Fakta</a>
    <a href="admin/pelanggaran.php" class="admin-btn">🚨 Kelola Pelanggaran</a>
    <a href="admin/rules.php" class="admin-btn">⚖️ Kelola Rule</a>
</div>

</body>
</html>