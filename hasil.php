<?php
/**
 * hasil.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Menampilkan hasil konsultasi berdasarkan ID yang tersimpan di MySQL.
 * URL: hasil.php?id={konsultasi_id}
 */
require_once __DIR__ . '/config/database.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ── Validasi ID ───────────────────────────────────────────────────────────────
$konsId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$konsId || $konsId < 1) {
    header('Location: konsultasi.php');
    exit;
}

// ── Ambil data konsultasi + pelanggaran ───────────────────────────────────────
$stKons = $pdo->prepare(
    'SELECT k.id, k.hasil, k.rule_id, k.rule_kode, k.waktu,
            p.id AS pel_id, p.kode AS pel_kode, p.nama AS pel_nama,
            p.deskripsi AS pel_desc, p.referensi_hukum
     FROM konsultasi k
     LEFT JOIN pelanggaran p ON p.id = k.pelanggaran_id
     WHERE k.id = ? AND k.status = ?'
);
$stKons->execute([$konsId, 'selesai']);
$kons = $stKons->fetch();

if (!$kons) {
    // Konsultasi tidak ditemukan atau masih proses
    header('Location: konsultasi.php');
    exit;
}

// ── Ambil semua jawaban sesi ini ──────────────────────────────────────────────
$stJwb = $pdo->prepare(
    'SELECT j.nilai, f.kode, f.pertanyaan
     FROM jawaban j JOIN fakta f ON f.id = j.fakta_id
     WHERE j.konsultasi_id = ?
     ORDER BY j.urutan ASC'
);
$stJwb->execute([$konsId]);
$jawaban = $stJwb->fetchAll();

$status   = $kons['hasil'] ?? 'tidak_terbukti';
$terbukti = $status === 'terbukti';
$waktu    = new DateTime($kons['waktu']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hasil Konsultasi #<?= $konsId ?> – Sistem Pakar Lalu Lintas</title>
<meta name="description" content="Hasil konsultasi sistem pakar pelanggaran lalu lintas, ID <?= $konsId ?>.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Base ─────────────────────────────────────────────────────────────── */
:root{
    --bg:#0a0f1e; --card:#141c2e; --card2:#1a2236;
    --border:#1e2d45; --border2:#243550;
    --accent:#3b82f6; --accent2:#6366f1;
    --green:#10b981; --red:#ef4444; --yellow:#f59e0b;
    --text:#f1f5f9; --sub:#94a3b8; --muted:#64748b;
    --r:16px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;
    background-image:
        <?= $terbukti
            ? 'radial-gradient(ellipse 80% 50% at 50% -20%,rgba(16,185,129,.13) 0%,transparent 60%),radial-gradient(ellipse 50% 40% at 90% 110%,rgba(59,130,246,.08) 0%,transparent 50%)'
            : 'radial-gradient(ellipse 80% 50% at 50% -20%,rgba(239,68,68,.10) 0%,transparent 60%),radial-gradient(ellipse 50% 40% at 90% 110%,rgba(245,158,11,.06) 0%,transparent 50%)' ?>;
}

/* ── Nav ──────────────────────────────────────────────────────────────── */
nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:15px 36px;border-bottom:1px solid var(--border);
    background:rgba(10,15,30,.82);backdrop-filter:blur(14px);
}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1rem;text-decoration:none;color:var(--text)}
.brand-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:8px;display:grid;place-items:center;font-size:15px}
.nav-right{display:flex;gap:8px}
.nav-link{padding:7px 14px;border-radius:8px;font-size:.83rem;font-weight:500;color:var(--muted);text-decoration:none;transition:.2s}
.nav-link:hover{color:var(--text);background:rgba(255,255,255,.06)}
.nav-link.pri{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:600}
.nav-link.pri:hover{opacity:.86}

/* ── Wrapper ──────────────────────────────────────────────────────────── */
.wrap{max-width:720px;margin:56px auto 100px;padding:0 20px}

/* ── Verdict hero ─────────────────────────────────────────────────────── */
.verdict{text-align:center;margin-bottom:42px;animation:fadeUp .45s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.verdict-icon{font-size:4rem;margin-bottom:16px;animation:pop .5s .12s cubic-bezier(.175,.885,.32,1.275) both;display:block}
@keyframes pop{from{transform:scale(0)}to{transform:scale(1)}}
.verdict-badge{
    display:inline-flex;align-items:center;gap:8px;
    padding:5px 18px;border-radius:99px;
    font-size:.75rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;margin-bottom:14px;
}
.verdict-badge.ok{background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.35);color:#6ee7b7}
.verdict-badge.no{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.28);color:#fca5a5}
.verdict h1{
    font-size:clamp(1.9rem,4vw,2.7rem);font-weight:800;line-height:1.15;
    background:linear-gradient(135deg,#fff 30%,#94a3b8);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.verdict p{margin-top:11px;color:var(--muted);font-size:.93rem;line-height:1.65;max-width:500px;margin-inline:auto}

/* ── Meta row ─────────────────────────────────────────────────────────── */
.meta-row{
    display:flex;align-items:center;justify-content:center;
    gap:18px;flex-wrap:wrap;margin-top:14px;
}
.meta-item{display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--muted)}
.meta-item span{color:var(--sub);font-weight:600}

/* ── Card ─────────────────────────────────────────────────────────────── */
.card{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);padding:28px;margin-bottom:16px;
    position:relative;overflow:hidden;
    animation:fadeUp .45s ease both;
}
.card:nth-child(1){animation-delay:.08s}
.card:nth-child(2){animation-delay:.16s}
.card:nth-child(3){animation-delay:.24s}
.card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(59,130,246,.03) 0%,transparent 50%);pointer-events:none}
.card.glow-green::before{background:linear-gradient(135deg,rgba(16,185,129,.06) 0%,transparent 50%)}
.card.glow-red::before{background:linear-gradient(135deg,rgba(239,68,68,.06) 0%,transparent 50%)}

/* ── Card header ──────────────────────────────────────────────────────── */
.card-head{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.card-icon{width:42px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:1.2rem;flex-shrink:0}
.ci-green{background:rgba(16,185,129,.14)}
.ci-red{background:rgba(239,68,68,.12)}
.ci-blue{background:rgba(59,130,246,.12)}
.ci-yellow{background:rgba(245,158,11,.12)}
.card-head-title{font-size:1rem;font-weight:700}
.card-head-sub{font-size:.79rem;color:var(--muted);margin-top:2px}
.divider{height:1px;background:var(--border);margin:16px 0}

/* ── Pelanggaran detail ───────────────────────────────────────────────── */
.pel-kode{
    display:inline-flex;align-items:center;padding:3px 11px;
    background:rgba(59,130,246,.11);border:1px solid rgba(59,130,246,.22);
    border-radius:99px;font-size:.73rem;font-weight:700;color:#93c5fd;
    letter-spacing:.05em;margin-bottom:11px;
}
.pel-nama{font-size:1.2rem;font-weight:700;margin-bottom:8px}
.pel-desc{font-size:.86rem;color:var(--muted);line-height:1.6;margin-bottom:14px}
.hukum-box{
    background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);
    border-radius:10px;padding:14px 16px;
}
.hukum-label{font-size:.71rem;font-weight:700;color:#fcd34d;letter-spacing:.07em;text-transform:uppercase;margin-bottom:6px}
.hukum-teks{font-size:.85rem;color:#fde68a;line-height:1.55}
.rule-chip{
    display:inline-flex;align-items:center;gap:6px;
    padding:4px 12px;background:rgba(99,102,241,.12);
    border:1px solid rgba(99,102,241,.25);border-radius:99px;
    font-size:.76rem;font-weight:700;color:#a5b4fc;
}

/* ── Disclaimer ───────────────────────────────────────────────────────── */
.disclaimer{
    display:flex;align-items:flex-start;gap:11px;
    padding:14px 16px;background:rgba(245,158,11,.07);
    border:1px solid rgba(245,158,11,.22);border-radius:10px;
    font-size:.81rem;color:#fde68a;line-height:1.55;margin-bottom:16px;
}
.disclaimer strong{color:#fcd34d}

/* ── Jawaban table ────────────────────────────────────────────────────── */
.jwb-table{width:100%;border-collapse:collapse}
.jwb-table th{
    font-size:.71rem;font-weight:700;color:var(--muted);
    letter-spacing:.06em;text-transform:uppercase;
    padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);
}
.jwb-table td{
    padding:10px 12px;font-size:.84rem;
    border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;
}
.jwb-table tr:last-child td{border-bottom:none}
.kode-cell{font-size:.73rem;font-weight:700;color:var(--accent);white-space:nowrap}
.nil-pill{
    display:inline-flex;align-items:center;gap:5px;
    padding:2px 9px;border-radius:99px;
    font-size:.71rem;font-weight:700;
}
.nil-pill.ya{background:rgba(16,185,129,.15);color:#6ee7b7}
.nil-pill.tidak{background:rgba(239,68,68,.15);color:#fca5a5}

/* ── Actions ──────────────────────────────────────────────────────────── */
.actions{display:flex;gap:12px;margin-top:24px;flex-wrap:wrap}
.btn-new{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    flex:1;padding:14px 22px;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#fff;font-family:inherit;font-size:.93rem;font-weight:700;
    border:none;border-radius:10px;cursor:pointer;text-decoration:none;transition:.2s;
}
.btn-new:hover{opacity:.86;transform:translateY(-2px)}
.btn-outline{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    flex:1;padding:14px 22px;background:transparent;
    color:var(--muted);font-family:inherit;font-size:.93rem;font-weight:600;
    border:1.5px solid var(--border);border-radius:10px;text-decoration:none;transition:.2s;
}
.btn-outline:hover{color:var(--text);border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.04)}
.btn-riwayat{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:14px 22px;background:transparent;
    color:var(--muted);font-family:inherit;font-size:.93rem;font-weight:600;
    border:1.5px solid var(--border);border-radius:10px;text-decoration:none;transition:.2s;
    width:100%;margin-top:8px;
}
.btn-riwayat:hover{color:var(--text);border-color:rgba(255,255,255,.15)}
</style>
</head>
<body>

<nav>
    <a href="index.php" class="brand">
        <span class="brand-icon">⚖️</span>
        Sistem Pakar Lalu Lintas
    </a>
    <div class="nav-right">
        <a href="index.php" class="nav-link">Beranda</a>
        <a href="konsultasi.php" class="nav-link pri">+ Konsultasi Baru</a>
    </div>
</nav>

<div class="wrap">

    <!-- ═══ VERDICT HERO ════════════════════════════════════════════════════ -->
    <div class="verdict">
        <span class="verdict-icon"><?= $terbukti ? '⚠️' : '✅' ?></span>

        <?php if ($terbukti): ?>
        <div class="verdict-badge ok">⚠️ Indikasi Pelanggaran Terdeteksi</div>
        <h1>Hipotesis Terbukti</h1>
        <p>Berdasarkan jawaban yang diberikan, sistem mendeteksi <strong>indikasi</strong> pelanggaran berikut. Bukan merupakan keputusan hukum resmi.</p>
        <?php else: ?>
        <div class="verdict-badge no">✅ Tidak Ada Indikasi</div>
        <h1>Hipotesis Tidak Terbukti</h1>
        <p>Berdasarkan jawaban yang diberikan, tidak ada rule yang terpenuhi untuk membuktikan hipotesis pelanggaran ini.</p>
        <?php endif; ?>

        <div class="meta-row">
            <div class="meta-item">🕐 <span><?= $waktu->format('d M Y, H:i') ?> WIB</span></div>
            <div class="meta-item">#<span><?= $konsId ?></span></div>
            <?php if ($kons['rule_kode']): ?>
            <div class="meta-item">Rule: <span><?= e($kons['rule_kode']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ DISCLAIMER ══════════════════════════════════════════════════════ -->
    <div class="disclaimer">
        ⚠️&nbsp;
        <span>
            Hasil ini adalah <strong>indikasi</strong> berdasarkan basis pengetahuan sistem, bukan keputusan atau penilaian hukum resmi. Aturan dan dasar hukumnya perlu diverifikasi kepada pihak berwenang sebelum dijadikan acuan.
        </span>
    </div>

    <!-- ═══ DETAIL PELANGGARAN / HIPOTESIS ══════════════════════════════════ -->
    <?php if ($kons['pel_id']): ?>
    <div class="card <?= $terbukti ? 'glow-green' : 'glow-red' ?>">
        <div class="card-head">
            <div class="card-icon <?= $terbukti ? 'ci-green' : 'ci-red' ?>"><?= $terbukti ? '⚠️' : '🚫' ?></div>
            <div>
                <div class="card-head-title"><?= $terbukti ? 'Detail Indikasi Pelanggaran' : 'Hipotesis yang Diuji' ?></div>
                <div class="card-head-sub"><?= $terbukti ? 'Kondisi rule terpenuhi' : 'Tidak ada rule yang cocok' ?></div>
            </div>
        </div>

        <div class="pel-kode"><?= e($kons['pel_kode']) ?></div>
        <div class="pel-nama"><?= e($kons['pel_nama']) ?></div>

        <?php if (!empty($kons['pel_desc'])): ?>
        <div class="pel-desc"><?= e($kons['pel_desc']) ?></div>
        <?php endif; ?>

        <?php if ($terbukti && !empty($kons['referensi_hukum'])): ?>
        <div class="hukum-box">
            <div class="hukum-label">📜 Referensi Hukum</div>
            <div class="hukum-teks"><?= e($kons['referensi_hukum']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($terbukti && $kons['rule_kode']): ?>
        <div class="divider"></div>
        <div class="rule-chip">⚙️ Rule aktif: <?= e($kons['rule_kode']) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ REKAP JAWABAN ════════════════════════════════════════════════════ -->
    <?php if ($jawaban): ?>
    <div class="card">
        <div class="card-head">
            <div class="card-icon ci-blue">📋</div>
            <div>
                <div class="card-head-title">Rekap Jawaban Konsultasi</div>
                <div class="card-head-sub"><?= count($jawaban) ?> fakta telah diverifikasi</div>
            </div>
        </div>

        <table class="jwb-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Pertanyaan Fakta</th>
                    <th>Jawaban</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jawaban as $j): ?>
            <tr>
                <td class="kode-cell"><?= e($j['kode']) ?></td>
                <td><?= e($j['pertanyaan']) ?></td>
                <td>
                    <span class="nil-pill <?= $j['nilai'] ?>">
                        <?= $j['nilai'] === 'ya' ? '✓ Ya' : '✗ Tidak' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ═══ TOMBOL AKSI ══════════════════════════════════════════════════════ -->
    <div class="actions">
        <a href="konsultasi.php" class="btn-new">🔍 Konsultasi Baru</a>
        <a href="index.php"      class="btn-outline">🏠 Beranda</a>
    </div>
    <a href="riwayat.php" class="btn-riwayat">📂 Lihat Semua Riwayat Konsultasi</a>

</div>
</body>
</html>