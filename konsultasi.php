<?php
/**
 * konsultasi.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Wizard konsultasi sistem pakar lalu lintas – Backward Chaining
 * Alur:
 *   1. GET  → tampilkan pilihan hipotesis
 *   2. POST aksi=pilih  → buat sesi + baris konsultasi di DB
 *   3. POST aksi=jawab  → simpan jawaban, jalankan mesin, lanjut/selesai
 *   4. Redirect ke hasil.php?id={konsultasi_id} bila selesai
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/engine/BackwardChaining.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

session_start();

// ── Reset paksa ───────────────────────────────────────────────────────────────
if (isset($_GET['reset'])) {
    unset($_SESSION['konsultasi']);
    header('Location: konsultasi.php');
    exit;
}

// ── Init sesi ─────────────────────────────────────────────────────────────────
if (!isset($_SESSION['konsultasi']) || !is_array($_SESSION['konsultasi'])) {
    $_SESSION['konsultasi'] = [
        'konsultasi_id'  => null,
        'pelanggaran_id' => null,
        'jawaban'        => [],   // [fakta_id => 'ya'|'tidak']
        'urutan'         => 0,
        'tahap'          => 'pilih_hipotesis',
    ];
} else {
    $_SESSION['konsultasi'] = array_merge([
        'konsultasi_id'  => null,
        'pelanggaran_id' => null,
        'jawaban'        => [],
        'urutan'         => 0,
        'tahap'          => 'pilih_hipotesis',
    ], $_SESSION['konsultasi']);
}
$sesi = &$_SESSION['konsultasi'];
$mesin = new BackwardChaining($pdo);

// ── Daftar hipotesis aktif ────────────────────────────────────────────────────
$pelanggaran = $pdo->query(
    'SELECT id, kode, nama, deskripsi
     FROM pelanggaran WHERE aktif = 1 ORDER BY kode'
)->fetchAll();

$error = '';

// ── Proses POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // ── 1. Pilih hipotesis ────────────────────────────────────────────────────
    if ($aksi === 'pilih') {
        $pelId = filter_input(INPUT_POST, 'pelanggaran_id', FILTER_VALIDATE_INT);
        if (!$pelId || $pelId < 1) {
            $error = 'Pilih salah satu jenis pelanggaran.';
        } else {
            // Buat baris konsultasi di DB (status = proses)
            $ins = $pdo->prepare(
                'INSERT INTO konsultasi (pelanggaran_id, waktu, status)
                 VALUES (?, NOW(), ?)'
            );
            $ins->execute([$pelId, 'proses']);
            $konsId = (int)$pdo->lastInsertId();

            $sesi['konsultasi_id']  = $konsId;
            $sesi['pelanggaran_id'] = $pelId;
            $sesi['jawaban']        = [];
            $sesi['urutan']         = 0;
            $sesi['tahap']          = 'tanya';
        }
    }

    // ── 2. Jawab pertanyaan ───────────────────────────────────────────────────
    elseif ($aksi === 'jawab') {
        $faktaId = filter_input(INPUT_POST, 'fakta_id', FILTER_VALIDATE_INT);
        $nilai   = $_POST['nilai'] ?? '';

        if (!$faktaId || !in_array($nilai, ['ya', 'tidak'], true)) {
            $error = 'Jawaban tidak valid.';
        } elseif (!$sesi['konsultasi_id']) {
            $error = 'Sesi tidak valid. Silakan mulai ulang.';
        } else {
            // Simpan ke tabel jawaban
            $insJwb = $pdo->prepare(
                'INSERT INTO jawaban (konsultasi_id, fakta_id, nilai, urutan)
                 VALUES (?, ?, ?, ?)'
            );
            $sesi['urutan']++;
            $insJwb->execute([
                $sesi['konsultasi_id'],
                $faktaId,
                $nilai,
                $sesi['urutan'],
            ]);

            $sesi['jawaban'][$faktaId] = $nilai;
        }
    }
}

// ── Jalankan mesin Backward Chaining ─────────────────────────────────────────
$pertanyaanAktif  = null;
$progressPersen   = 0;
$pelanggaranAktif = null;

if ($sesi['tahap'] === 'tanya' && $sesi['pelanggaran_id'] && $sesi['konsultasi_id']) {

    // Cari data hipotesis aktif
    foreach ($pelanggaran as $p) {
        if ((int)$p['id'] === (int)$sesi['pelanggaran_id']) {
            $pelanggaranAktif = $p;
            break;
        }
    }

    $hasilMesin = $mesin->periksa((int)$sesi['pelanggaran_id'], $sesi['jawaban']);

    // ── Selesai: terbukti atau tidak terbukti ─────────────────────────────────
    if (in_array($hasilMesin['status'], ['terbukti', 'tidak_terbukti'], true)) {

        $pdo->prepare(
            'UPDATE konsultasi
             SET status   = ?,
                 hasil    = ?,
                 rule_id  = ?,
                 rule_kode= ?
             WHERE id = ?'
        )->execute([
            'selesai',
            $hasilMesin['status'],
            $hasilMesin['rule_id'],
            $hasilMesin['rule_kode'] ?? null,
            $sesi['konsultasi_id'],
        ]);

        $konsId = $sesi['konsultasi_id'];
        unset($_SESSION['konsultasi']);
        header("Location: hasil.php?id=$konsId");
        exit;
    }

    // ── Masih perlu fakta ─────────────────────────────────────────────────────
    $pertanyaanAktif = $hasilMesin['fakta_belum_diketahui'][0] ?? null;

    // Hitung progress
    $stTotal = $pdo->prepare(
        'SELECT COUNT(DISTINCT rf.fakta_id)
         FROM rule_fakta rf JOIN rules r ON r.id = rf.rule_id
         WHERE r.pelanggaran_id = ? AND r.aktif = 1'
    );
    $stTotal->execute([$sesi['pelanggaran_id']]);
    $total   = (int)$stTotal->fetchColumn();
    $dijawab = count($sesi['jawaban']);
    $progressPersen = $total > 0 ? min(95, (int)round($dijawab / $total * 100)) : 0;
}

// ── Ambil riwayat jawaban lengkap dari DB untuk ditampilkan ──────────────────
$riwayatJawaban = [];
if (!empty($sesi['konsultasi_id']) && !empty($sesi['jawaban'])) {
    $stRiwayat = $pdo->prepare(
        'SELECT j.nilai, f.kode, f.pertanyaan
         FROM jawaban j JOIN fakta f ON f.id = j.fakta_id
         WHERE j.konsultasi_id = ?
         ORDER BY j.urutan ASC'
    );
    $stRiwayat->execute([$sesi['konsultasi_id']]);
    $riwayatJawaban = $stRiwayat->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Konsultasi – Sistem Pakar Lalu Lintas</title>
<meta name="description" content="Konsultasi sistem pakar pelanggaran lalu lintas berbasis backward chaining.">
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
    font-family:'Inter',sans-serif;
    background:var(--bg);color:var(--text);
    min-height:100vh;
    background-image:
        radial-gradient(ellipse 80% 50% at 50% -20%,rgba(59,130,246,.15) 0%,transparent 60%),
        radial-gradient(ellipse 60% 40% at 90% 110%,rgba(99,102,241,.10) 0%,transparent 50%);
}

/* ── Nav ──────────────────────────────────────────────────────────────── */
nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:15px 36px;border-bottom:1px solid var(--border);
    background:rgba(10,15,30,.82);backdrop-filter:blur(14px);
    position:sticky;top:0;z-index:99;
}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1rem;text-decoration:none;color:var(--text)}
.brand-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:8px;display:grid;place-items:center;font-size:15px}
.nav-right{display:flex;align-items:center;gap:8px}
.nav-link{padding:7px 14px;border-radius:8px;font-size:.83rem;font-weight:500;color:var(--muted);text-decoration:none;transition:.2s}
.nav-link:hover{color:var(--text);background:rgba(255,255,255,.06)}
.nav-link.warn{color:#fcd34d;border:1px solid rgba(245,158,11,.3)}
.nav-link.warn:hover{background:rgba(245,158,11,.09)}

/* ── Wrapper ──────────────────────────────────────────────────────────── */
.wrap{max-width:700px;margin:52px auto 100px;padding:0 20px}

/* ── Page header ──────────────────────────────────────────────────────── */
.page-head{text-align:center;margin-bottom:40px}
.badge-pill{
    display:inline-flex;align-items:center;gap:6px;
    padding:4px 14px;background:rgba(59,130,246,.12);
    border:1px solid rgba(59,130,246,.25);border-radius:99px;
    font-size:.71rem;font-weight:700;color:#93c5fd;
    letter-spacing:.08em;text-transform:uppercase;margin-bottom:15px;
}
.page-head h1{
    font-size:clamp(1.8rem,4vw,2.6rem);font-weight:800;line-height:1.13;
    background:linear-gradient(135deg,#fff 30%,#94a3b8);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.page-head p{margin-top:11px;color:var(--muted);font-size:.93rem;line-height:1.65}

/* ── Step indicator ───────────────────────────────────────────────────── */
.steps{display:flex;align-items:center;justify-content:center;margin-bottom:36px}
.step{display:flex;flex-direction:column;align-items:center;gap:5px}
.sc{
    width:36px;height:36px;border-radius:50%;
    display:grid;place-items:center;
    font-size:.8rem;font-weight:700;
    border:2px solid var(--border);color:var(--muted);background:var(--card);
    transition:.3s;
}
.step.active .sc{background:linear-gradient(135deg,var(--accent),var(--accent2));border-color:transparent;color:#fff}
.step.done .sc{background:rgba(16,185,129,.15);border-color:var(--green);color:var(--green)}
.sl{font-size:.67rem;font-weight:600;color:var(--muted);white-space:nowrap}
.step.active .sl{color:var(--accent)} .step.done .sl{color:var(--green)}
.sline{width:52px;height:2px;background:var(--border);margin-bottom:17px;flex-shrink:0}
.sline.done{background:var(--green)}

/* ── Card ─────────────────────────────────────────────────────────────── */
.card{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);padding:28px;
    position:relative;overflow:hidden;
}
.card::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,rgba(59,130,246,.04) 0%,transparent 50%);
    pointer-events:none;
}
.card-title{font-size:1.05rem;font-weight:700;margin-bottom:4px}
.card-sub{font-size:.84rem;color:var(--muted);margin-bottom:24px;line-height:1.5}

/* ── Alert ────────────────────────────────────────────────────────────── */
.alert-err{
    display:flex;align-items:center;gap:9px;
    padding:12px 16px;background:rgba(239,68,68,.1);
    border:1px solid rgba(239,68,68,.25);border-radius:10px;
    color:#fca5a5;font-size:.85rem;margin-bottom:18px;
}

/* ── Disclaimer ───────────────────────────────────────────────────────── */
.disclaimer{
    display:flex;align-items:flex-start;gap:11px;
    padding:14px 16px;background:rgba(245,158,11,.07);
    border:1px solid rgba(245,158,11,.22);border-radius:10px;
    font-size:.81rem;color:#fde68a;line-height:1.55;margin-bottom:22px;
}
.disclaimer-icon{font-size:1rem;flex-shrink:0;margin-top:1px}

/* ── Hipotesis grid ───────────────────────────────────────────────────── */
.h-grid{display:flex;flex-direction:column;gap:10px}
.h-radio{display:none}
.h-lbl{
    display:flex;align-items:center;gap:14px;
    padding:15px 18px;border:1.5px solid var(--border);
    border-radius:12px;cursor:pointer;
    transition:border-color .2s,background .2s;
    background:rgba(255,255,255,.018);
}
.h-lbl:hover{border-color:rgba(59,130,246,.45);background:rgba(59,130,246,.05)}
.h-radio:checked+.h-lbl{border-color:var(--accent);background:rgba(59,130,246,.09)}
.h-badge{
    width:44px;height:44px;border-radius:10px;flex-shrink:0;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    display:grid;place-items:center;font-weight:800;font-size:.75rem;letter-spacing:.02em;
}
.h-body{flex:1}
.h-nama{font-weight:600;font-size:.93rem}
.h-desc{font-size:.78rem;color:var(--muted);margin-top:3px;line-height:1.4}
.h-dot{width:20px;height:20px;border:2px solid var(--border);border-radius:50%;display:grid;place-items:center;transition:.2s;flex-shrink:0}
.h-radio:checked+.h-lbl .h-dot{border-color:var(--accent);background:var(--accent)}
.h-radio:checked+.h-lbl .h-dot::after{content:'';width:8px;height:8px;background:#fff;border-radius:50%}

/* ── Primary button ───────────────────────────────────────────────────── */
.btn-primary{
    display:flex;align-items:center;justify-content:center;gap:8px;
    width:100%;padding:14px;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#fff;font-family:inherit;font-size:.95rem;font-weight:700;
    border:none;border-radius:10px;cursor:pointer;margin-top:20px;transition:.2s;
}
.btn-primary:hover{opacity:.86;transform:translateY(-2px)}

/* ── Hipotesis chip ───────────────────────────────────────────────────── */
.chip{
    display:inline-flex;align-items:center;gap:7px;
    padding:5px 13px;background:rgba(59,130,246,.1);
    border:1px solid rgba(59,130,246,.23);border-radius:99px;
    font-size:.77rem;font-weight:600;color:#93c5fd;margin-bottom:20px;
}

/* ── Progress ─────────────────────────────────────────────────────────── */
.prog-wrap{margin-bottom:24px}
.prog-hdr{display:flex;justify-content:space-between;margin-bottom:7px}
.prog-lbl{font-size:.77rem;color:var(--muted);font-weight:500}
.prog-pct{font-size:.77rem;color:var(--accent);font-weight:700}
.prog-bar{height:5px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:99px;transition:width .5s ease}

/* ── Fakta counter ────────────────────────────────────────────────────── */
.fakta-counter{
    display:flex;align-items:center;gap:6px;
    font-size:.77rem;color:var(--muted);margin-bottom:16px;
}
.fakta-counter strong{color:var(--sub)}

/* ── Pertanyaan box ───────────────────────────────────────────────────── */
.q-box{
    background:rgba(255,255,255,.04);border:1px solid var(--border2);
    border-radius:12px;padding:22px;margin-bottom:20px;
    animation:fadeIn .3s ease both;
}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.q-kode{font-size:.7rem;font-weight:700;color:var(--accent);letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px}
.q-teks{font-size:1.1rem;font-weight:600;line-height:1.55;color:var(--text)}
.q-hint{font-size:.78rem;color:var(--muted);margin-top:8px;line-height:1.45}

/* ── Jawaban buttons ──────────────────────────────────────────────────── */
.ans-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ans-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;
    padding:16px;border:1.5px solid var(--border);border-radius:12px;
    font-family:inherit;font-size:.97rem;font-weight:700;cursor:pointer;
    transition:.2s;background:rgba(255,255,255,.025);color:var(--text);
}
.ans-btn.ya{border-color:rgba(16,185,129,.35);color:#6ee7b7}
.ans-btn.ya:hover{background:rgba(16,185,129,.13);border-color:var(--green);transform:translateY(-2px)}
.ans-btn.tidak{border-color:rgba(239,68,68,.35);color:#fca5a5}
.ans-btn.tidak:hover{background:rgba(239,68,68,.13);border-color:var(--red);transform:translateY(-2px)}

/* ── Riwayat ──────────────────────────────────────────────────────────── */
.riwayat{margin-top:26px;border-top:1px solid var(--border);padding-top:20px}
.riwayat-title{font-size:.71rem;font-weight:700;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px}
.riwayat-list{display:flex;flex-direction:column;gap:5px}
.riwayat-item{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:9px 12px;background:rgba(255,255,255,.025);border-radius:8px;font-size:.81rem;
}
.r-kode{font-size:.72rem;font-weight:700;color:var(--accent);flex-shrink:0;min-width:36px}
.r-teks{color:var(--muted);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.r-nilai{font-weight:700;font-size:.72rem;padding:2px 9px;border-radius:99px;flex-shrink:0}
.r-nilai.ya{background:rgba(16,185,129,.15);color:#6ee7b7}
.r-nilai.tidak{background:rgba(239,68,68,.15);color:#fca5a5}

/* ── Empty ────────────────────────────────────────────────────────────── */
.empty{text-align:center;padding:48px 20px}
.empty-icon{font-size:2.8rem;margin-bottom:13px}
.empty-title{font-size:1.1rem;font-weight:700;margin-bottom:6px}
.empty-desc{color:var(--muted);font-size:.86rem}
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
        <?php if ($sesi['tahap'] !== 'pilih_hipotesis'): ?>
        <a href="konsultasi.php?reset=1" class="nav-link warn">↺ Mulai Ulang</a>
        <?php endif; ?>
    </div>
</nav>

<div class="wrap">

    <!-- Header -->
    <div class="page-head">
        <div class="badge-pill">🔍 Backward Chaining</div>
        <h1>Konsultasi Pelanggaran<br>Lalu Lintas</h1>
        <p>Sistem mengajukan pertanyaan berdasarkan hipotesis yang Anda pilih,<br>kemudian memverifikasi apakah kondisi rule terpenuhi.</p>
    </div>

    <!-- Steps -->
    <div class="steps">
        <div class="step <?= $sesi['tahap'] === 'pilih_hipotesis' ? 'active' : 'done' ?>">
            <div class="sc"><?= $sesi['tahap'] === 'pilih_hipotesis' ? '1' : '✓' ?></div>
            <span class="sl">Pilih Hipotesis</span>
        </div>
        <div class="sline <?= $sesi['tahap'] !== 'pilih_hipotesis' ? 'done' : '' ?>"></div>
        <div class="step <?= $sesi['tahap'] === 'tanya' ? 'active' : '' ?>">
            <div class="sc">2</div>
            <span class="sl">Verifikasi Fakta</span>
        </div>
        <div class="sline"></div>
        <div class="step"><div class="sc">3</div><span class="sl">Kesimpulan</span></div>
    </div>

    <?php if ($error): ?>
    <div class="alert-err">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════ PILIH HIPOTESIS ═══ -->
    <?php if ($sesi['tahap'] === 'pilih_hipotesis'): ?>
    <div class="card">
        <div class="card-title">Pilih Dugaan Pelanggaran</div>
        <div class="card-sub">Tentukan hipotesis yang ingin dibuktikan. Sistem akan mengajukan pertanyaan untuk memverifikasinya.</div>

        <div class="disclaimer">
            <span class="disclaimer-icon">⚠️</span>
            <span>
                <strong>Catatan:</strong> Hasil konsultasi ini merupakan <strong>indikasi</strong> berdasarkan basis pengetahuan sistem, bukan keputusan atau penilaian hukum resmi. Verifikasi aturan dan dasar hukum kepada pihak berwenang sebelum menggunakannya sebagai acuan.
            </span>
        </div>

        <?php if (!$pelanggaran): ?>
        <div class="empty">
            <div class="empty-icon">📋</div>
            <div class="empty-title">Belum ada data pelanggaran</div>
            <div class="empty-desc">Tambahkan pelanggaran dan rule di panel admin terlebih dahulu.</div>
        </div>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="aksi" value="pilih">
            <div class="h-grid">
                <?php foreach ($pelanggaran as $p): ?>
                <input type="radio" class="h-radio" name="pelanggaran_id"
                       id="p<?= $p['id'] ?>" value="<?= (int)$p['id'] ?>" required>
                <label class="h-lbl" for="p<?= $p['id'] ?>">
                    <div class="h-badge"><?= e($p['kode']) ?></div>
                    <div class="h-body">
                        <div class="h-nama"><?= e($p['nama']) ?></div>
                        <?php if (!empty($p['deskripsi'])): ?>
                        <div class="h-desc"><?= e(mb_strimwidth($p['deskripsi'], 0, 90, '…')) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="h-dot"></div>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn-primary">Mulai Konsultasi →</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════════════ TANYA JAWAB ══════ -->
    <?php elseif ($sesi['tahap'] === 'tanya' && $pertanyaanAktif): ?>
    <div class="card">
        <!-- Chip hipotesis -->
        <div class="chip">
            🎯 Hipotesis: <?= e($pelanggaranAktif['kode'] . ' – ' . $pelanggaranAktif['nama']) ?>
        </div>

        <!-- Progress -->
        <div class="prog-wrap">
            <div class="prog-hdr">
                <span class="prog-lbl">Progres Verifikasi</span>
                <span class="prog-pct"><?= $progressPersen ?>%</span>
            </div>
            <div class="prog-bar">
                <div class="prog-fill" style="width:<?= $progressPersen ?>%"></div>
            </div>
        </div>

        <!-- Counter -->
        <div class="fakta-counter">
            📝 Pertanyaan ke-<strong><?= count($sesi['jawaban']) + 1 ?></strong>
            &nbsp;·&nbsp; <?= count($sesi['jawaban']) ?> fakta telah dijawab
        </div>

        <!-- Pertanyaan -->
        <div class="q-box">
            <div class="q-kode">📌 Fakta <?= e($pertanyaanAktif['kode']) ?></div>
            <div class="q-teks"><?= e($pertanyaanAktif['pertanyaan']) ?></div>
            <div class="q-hint">Jawab berdasarkan kondisi yang Anda ketahui.</div>
        </div>

        <!-- Tombol jawab -->
        <form method="post">
            <input type="hidden" name="aksi"     value="jawab">
            <input type="hidden" name="fakta_id" value="<?= (int)$pertanyaanAktif['id'] ?>">
            <div class="ans-row">
                <button type="submit" name="nilai" value="ya"    class="ans-btn ya">✅ Ya</button>
                <button type="submit" name="nilai" value="tidak" class="ans-btn tidak">❌ Tidak</button>
            </div>
        </form>

        <!-- Riwayat jawaban (dari DB) -->
        <?php if ($riwayatJawaban): ?>
        <div class="riwayat">
            <div class="riwayat-title">📋 Riwayat jawaban</div>
            <div class="riwayat-list">
                <?php foreach ($riwayatJawaban as $r): ?>
                <div class="riwayat-item">
                    <span class="r-kode"><?= e($r['kode']) ?></span>
                    <span class="r-teks" title="<?= e($r['pertanyaan']) ?>"><?= e($r['pertanyaan']) ?></span>
                    <span class="r-nilai <?= $r['nilai'] ?>"><?= $r['nilai'] === 'ya' ? '✓ Ya' : '✗ Tidak' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>