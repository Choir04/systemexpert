<?php
/**
 * riwayat.php
 * Menampilkan semua riwayat konsultasi yang tersimpan di MySQL.
 */
require_once __DIR__ . '/config/database.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Pagination sederhana
$limit  = 15;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$total = (int)$pdo->query(
    "SELECT COUNT(*) FROM konsultasi WHERE status = 'selesai'"
)->fetchColumn();

$totalPage = max(1, (int)ceil($total / $limit));
$page      = min($page, $totalPage);

$rows = $pdo->prepare(
    "SELECT k.id, k.hasil, k.rule_kode, k.waktu,
            p.kode AS pel_kode, p.nama AS pel_nama,
            (SELECT COUNT(*) FROM jawaban WHERE konsultasi_id = k.id) AS jml_jwb
     FROM konsultasi k
     LEFT JOIN pelanggaran p ON p.id = k.pelanggaran_id
     WHERE k.status = 'selesai'
     ORDER BY k.waktu DESC
     LIMIT ? OFFSET ?"
);
$rows->execute([$limit, $offset]);
$riwayat = $rows->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Riwayat Konsultasi – Sistem Pakar Lalu Lintas</title>
<meta name="description" content="Riwayat semua sesi konsultasi sistem pakar lalu lintas.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0a0f1e;--card:#141c2e;--border:#1e2d45;
    --accent:#3b82f6;--accent2:#6366f1;
    --green:#10b981;--red:#ef4444;
    --text:#f1f5f9;--sub:#94a3b8;--muted:#64748b;--r:16px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;
    background-image:radial-gradient(ellipse 80% 50% at 50% -20%,rgba(59,130,246,.13) 0%,transparent 60%);
}
nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:15px 36px;border-bottom:1px solid var(--border);
    background:rgba(10,15,30,.82);backdrop-filter:blur(14px);position:sticky;top:0;z-index:99;
}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1rem;text-decoration:none;color:var(--text)}
.brand-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:8px;display:grid;place-items:center;font-size:15px}
.nav-right{display:flex;gap:8px}
.nav-link{padding:7px 14px;border-radius:8px;font-size:.83rem;font-weight:500;color:var(--muted);text-decoration:none;transition:.2s}
.nav-link:hover{color:var(--text);background:rgba(255,255,255,.06)}
.nav-link.pri{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-weight:600}
.nav-link.pri:hover{opacity:.86}
.wrap{max-width:900px;margin:50px auto 90px;padding:0 20px}
.page-head{margin-bottom:28px}
.page-head h1{font-size:1.7rem;font-weight:800}
.page-head p{color:var(--muted);font-size:.88rem;margin-top:5px}
.stats-bar{
    display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;
}
.stat-chip{
    display:flex;align-items:center;gap:8px;
    padding:10px 16px;background:var(--card);
    border:1px solid var(--border);border-radius:10px;
    font-size:.84rem;
}
.stat-chip strong{color:var(--text);font-weight:700}
.stat-chip span{color:var(--muted)}
/* Table */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{
    padding:12px 16px;background:rgba(255,255,255,.03);
    font-size:.71rem;font-weight:700;color:var(--muted);
    text-align:left;letter-spacing:.06em;text-transform:uppercase;
    border-bottom:1px solid var(--border);
}
tbody td{padding:13px 16px;font-size:.85rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr{transition:.15s}
tbody tr:hover{background:rgba(255,255,255,.025)}
.id-cell{font-weight:700;color:var(--muted);font-size:.8rem}
.kode-badge{
    display:inline-flex;align-items:center;
    padding:3px 10px;background:rgba(59,130,246,.11);
    border:1px solid rgba(59,130,246,.22);border-radius:99px;
    font-size:.72rem;font-weight:700;color:#93c5fd;
}
.nama-cell{font-weight:500;color:var(--text);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hasil-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:.72rem;font-weight:700;white-space:nowrap}
.hasil-pill.terbukti{background:rgba(239,68,68,.13);color:#fca5a5;border:1px solid rgba(239,68,68,.25)}
.hasil-pill.tidak{background:rgba(16,185,129,.12);color:#6ee7b7;border:1px solid rgba(16,185,129,.25)}
.rule-cell{font-size:.75rem;color:var(--accent);font-weight:600}
.waktu-cell{font-size:.77rem;color:var(--muted);white-space:nowrap}
.link-detail{
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 12px;background:rgba(59,130,246,.1);
    border:1px solid rgba(59,130,246,.22);border-radius:7px;
    font-size:.77rem;font-weight:600;color:#93c5fd;
    text-decoration:none;transition:.2s;white-space:nowrap;
}
.link-detail:hover{background:rgba(59,130,246,.2)}
/* Empty */
.empty{text-align:center;padding:60px 20px}
.empty-icon{font-size:3rem;margin-bottom:14px}
.empty-title{font-size:1.1rem;font-weight:700;margin-bottom:8px}
.empty-desc{color:var(--muted);font-size:.87rem;margin-bottom:20px}
.btn-cta{
    display:inline-flex;align-items:center;gap:8px;
    padding:12px 24px;background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#fff;font-family:inherit;font-size:.9rem;font-weight:700;
    border:none;border-radius:10px;text-decoration:none;cursor:pointer;transition:.2s;
}
.btn-cta:hover{opacity:.85;transform:translateY(-2px)}
/* Pagination */
.pagination{display:flex;justify-content:center;gap:6px;margin-top:24px;flex-wrap:wrap}
.pg{
    display:inline-flex;align-items:center;justify-content:center;
    width:36px;height:36px;border-radius:8px;font-size:.83rem;font-weight:600;
    text-decoration:none;color:var(--muted);background:var(--card);border:1px solid var(--border);
    transition:.2s;
}
.pg:hover{color:var(--text);border-color:rgba(255,255,255,.15)}
.pg.active{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border-color:transparent}
</style>
</head>
<body>
<nav>
    <a href="index.php" class="brand">
        <span class="brand-icon">⚖️</span>
        Sistem Pakar Lalu Lintas
    </a>
    <div class="nav-right">
        <a href="index.php"      class="nav-link">Beranda</a>
        <a href="konsultasi.php" class="nav-link pri">+ Konsultasi Baru</a>
    </div>
</nav>

<div class="wrap">
    <div class="page-head">
        <h1>📂 Riwayat Konsultasi</h1>
        <p>Semua sesi konsultasi yang telah selesai dan tersimpan di database.</p>
    </div>

    <div class="stats-bar">
        <div class="stat-chip"><strong><?= $total ?></strong> <span>sesi selesai</span></div>
        <div class="stat-chip">
            <strong>
            <?php
                $jmlTerbukti = (int)$pdo->query(
                    "SELECT COUNT(*) FROM konsultasi WHERE status='selesai' AND hasil='terbukti'"
                )->fetchColumn();
                echo $jmlTerbukti;
            ?>
            </strong>
            <span>indikasi terbukti</span>
        </div>
        <div class="stat-chip">
            <strong><?= $total - $jmlTerbukti ?></strong>
            <span>tidak terbukti</span>
        </div>
    </div>

    <div class="card">
        <?php if (!$riwayat): ?>
        <div class="empty">
            <div class="empty-icon">🗂️</div>
            <div class="empty-title">Belum ada riwayat konsultasi</div>
            <div class="empty-desc">Lakukan konsultasi pertama Anda untuk melihat hasilnya di sini.</div>
            <a href="konsultasi.php" class="btn-cta">Mulai Konsultasi</a>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Hipotesis</th>
                        <th>Hasil</th>
                        <th>Rule</th>
                        <th>Fakta</th>
                        <th>Waktu</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($riwayat as $r):
                    $terbukti = $r['hasil'] === 'terbukti';
                    $waktu    = new DateTime($r['waktu']);
                ?>
                <tr>
                    <td class="id-cell"><?= $r['id'] ?></td>
                    <td>
                        <?php if ($r['pel_kode']): ?>
                        <div class="kode-badge"><?= e($r['pel_kode']) ?></div><br>
                        <span class="nama-cell" title="<?= e($r['pel_nama']) ?>"><?= e($r['pel_nama']) ?></span>
                        <?php else: ?>
                        <span style="color:var(--muted)">–</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="hasil-pill <?= $terbukti ? 'terbukti' : 'tidak' ?>">
                            <?= $terbukti ? '⚠️ Terbukti' : '✅ Tidak Terbukti' ?>
                        </span>
                    </td>
                    <td class="rule-cell"><?= $r['rule_kode'] ? e($r['rule_kode']) : '–' ?></td>
                    <td style="color:var(--sub);font-weight:600"><?= (int)$r['jml_jwb'] ?></td>
                    <td class="waktu-cell"><?= $waktu->format('d M Y H:i') ?></td>
                    <td>
                        <a href="hasil.php?id=<?= (int)$r['id'] ?>" class="link-detail">
                            Lihat →
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPage > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPage; $i++): ?>
            <a href="?page=<?= $i ?>" class="pg <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
