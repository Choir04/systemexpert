<?php
require_once __DIR__ . '/../config/database.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pesan = '';
$error = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    try {
        if ($aksi === 'simpan' || $aksi === 'edit') {
            $kode = strtoupper(trim($_POST['kode'] ?? ''));
            $pelanggaranId = filter_var($_POST['pelanggaran_id'] ?? null, FILTER_VALIDATE_INT);
            $faktaIds = $_POST['fakta_id'] ?? [];
            $nilaiFakta = $_POST['nilai'] ?? [];

            if (!preg_match('/^[A-Z0-9_-]{2,10}$/', $kode)) {
                throw new RuntimeException('Kode rule tidak valid.');
            }

            if (!$pelanggaranId || $pelanggaranId < 1) {
                throw new RuntimeException('Pilih kesimpulan pelanggaran.');
            }

            if (!is_array($faktaIds) || !is_array($nilaiFakta)) {
                throw new RuntimeException('Kondisi rule tidak valid.');
            }

            if (count($faktaIds) < 1 || count($faktaIds) > 50) {
                throw new RuntimeException('Rule harus memiliki 1 sampai 50 kondisi.');
            }

            if (count($faktaIds) !== count($nilaiFakta)) {
                throw new RuntimeException('Kondisi rule tidak cocok.');
            }

            $kondisi = [];
            foreach ($faktaIds as $i => $faktaId) {
                $fid = filter_var($faktaId, FILTER_VALIDATE_INT);
                $nilai = $nilaiFakta[$i] ?? '';

                if (!$fid || $fid < 1 || !in_array($nilai, ['ya', 'tidak'], true)) {
                    throw new RuntimeException('Fakta atau nilai kondisi tidak valid.');
                }

                if (isset($kondisi[$fid])) {
                    throw new RuntimeException('Fakta yang sama tidak boleh dipilih dua kali.');
                }

                $kondisi[$fid] = $nilai;
            }

            $pdo->beginTransaction();

            $cek = $pdo->prepare(
                'SELECT id FROM pelanggaran
                 WHERE id = ? AND aktif = 1',
            );
            $cek->execute([$pelanggaranId]);

            if (!$cek->fetch()) {
                throw new RuntimeException('Pelanggaran tidak ditemukan atau nonaktif.');
            }

            $idEdit = null;

            if ($aksi === 'edit') {
                $idEdit = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

                if (!$idEdit || $idEdit < 1) {
                    throw new RuntimeException('ID rule tidak valid.');
                }

                $cek = $pdo->prepare('SELECT id FROM rules WHERE id = ?');
                $cek->execute([$idEdit]);

                if (!$cek->fetch()) {
                    throw new RuntimeException('Rule tidak ditemukan.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE rules
                     SET kode = ?, pelanggaran_id = ?
                     WHERE id = ?',
                );
                $stmt->execute([$kode, $pelanggaranId, $idEdit]);

                $ruleId = $idEdit;

                $stmt = $pdo->prepare('DELETE FROM rule_fakta WHERE rule_id = ?');
                $stmt->execute([$ruleId]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO rules (kode, pelanggaran_id)
                     VALUES (?, ?)',
                );
                $stmt->execute([$kode, $pelanggaranId]);

                $ruleId = (int) $pdo->lastInsertId();
            }

            $cekFakta = $pdo->prepare(
                'SELECT id FROM fakta
                 WHERE id = ? AND aktif = 1',
            );

            $insert = $pdo->prepare(
                'INSERT INTO rule_fakta (rule_id, fakta_id, nilai)
                 VALUES (?, ?, ?)',
            );

            foreach ($kondisi as $fid => $nilai) {
                $cekFakta->execute([$fid]);

                if (!$cekFakta->fetch()) {
                    throw new RuntimeException('Salah satu fakta tidak ditemukan atau nonaktif.');
                }

                $insert->execute([$ruleId, $fid, $nilai]);
            }

            $pdo->commit();
            $pesan = 'Rule berhasil disimpan.';
        } elseif ($aksi === 'hapus') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('ID rule tidak valid.');
            }

            $stmt = $pdo->prepare('DELETE FROM rules WHERE id = ?');
            $stmt->execute([$id]);

            $pesan = 'Rule berhasil dihapus.';
        } elseif ($aksi === 'status') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('ID rule tidak valid.');
            }

            $stmt = $pdo->prepare(
                'UPDATE rules
                 SET aktif = IF(aktif = 1, 0, 1)
                 WHERE id = ?',
            );
            $stmt->execute([$id]);

            $pesan = 'Status rule berhasil diperbarui.';
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($ex instanceof PDOException && $ex->getCode() === '23000') {
            $error = 'Kode rule sudah digunakan atau terdapat data yang tidak valid.';
        } elseif ($ex instanceof RuntimeException) {
            $error = $ex->getMessage();
        } else {
            error_log($ex->getMessage());
            $error = 'Terjadi kesalahan saat menyimpan rule.';
        }
    }
}

if (isset($_GET['edit'])) {
    $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($id && $id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM rules WHERE id = ?');
        $stmt->execute([$id]);
        $edit = $stmt->fetch() ?: null;
    }
}

$pelanggaran = $pdo
    ->query(
        'SELECT id, kode, nama FROM pelanggaran
     WHERE aktif = 1 ORDER BY kode',
    )
    ->fetchAll();

$fakta = $pdo
    ->query(
        'SELECT id, kode, pertanyaan FROM fakta
     WHERE aktif = 1 ORDER BY kode',
    )
    ->fetchAll();

$kondisiEdit = [];

if ($edit) {
    $stmt = $pdo->prepare(
        'SELECT fakta_id, nilai FROM rule_fakta
         WHERE rule_id = ? ORDER BY id',
    );
    $stmt->execute([$edit['id']]);
    $kondisiEdit = $stmt->fetchAll();
}

$rules = $pdo
    ->query(
        'SELECT r.id, r.kode, r.aktif,
            p.kode AS pelanggaran_kode,
            p.nama AS pelanggaran_nama
     FROM rules r
     JOIN pelanggaran p ON p.id = r.pelanggaran_id
     ORDER BY r.id DESC',
    )
    ->fetchAll();

$stmtKondisi = $pdo->prepare(
    'SELECT f.kode, rf.nilai
     FROM rule_fakta rf
     JOIN fakta f ON f.id = rf.fakta_id
     WHERE rf.rule_id = ?
     ORDER BY rf.id',
);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Rule</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            padding: 20px
        }

        main {
            max-width: 1100px;
            margin: auto
        }

        section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px
        }

        input,
        select {
            padding: 10px;
            max-width: 100%;
            width: 100%;
            margin: 5px 0 12px;
            box-sizing: border-box
        }

        button,
        .btn {
            display: inline-block;
            padding: 9px 12px;
            border: 0;
            border-radius: 5px;
            background: #1d4ed8;
            color: white;
            text-decoration: none;
            cursor: pointer
        }

        .hapus {
            background: #b91c1c
        }

        .sekunder {
            background: #6b7280
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        td,
        th {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top
        }

        .wrap {
            overflow-x: auto
        }

        .kondisi {
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 7px;
            margin: 10px 0
        }

        .pesan {
            color: #166534
        }

        .error {
            color: #b91c1c
        }

        form.inline {
            display: inline
        }
    </style>
</head>

<body>
    <main>

        <section>
            <h1>Manajemen Rule</h1>
            <p>Susun aturan IF–THEN menggunakan fakta dalam database.</p>
            <a class="btn sekunder" href="../index.php">Beranda</a>
            <a class="btn sekunder" href="fakta.php">Kelola Fakta</a>
            <a class="btn sekunder" href="pelanggaran.php">Kelola Pelanggaran</a>
        </section>

        <section>
            <h2><?= $edit ? 'Edit Rule' : 'Tambah Rule' ?></h2>

            <?php if ($pesan): ?>
            <p class="pesan"><?= e($pesan) ?></p>
            <?php endif; ?>

            <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
            <?php endif; ?>

            <?php if (!$pelanggaran || !$fakta): ?>
            <p>Tambahkan minimal satu pelanggaran dan satu fakta aktif terlebih dahulu.</p>
            <?php else: ?>

            <form method="post">
                <input type="hidden" name="aksi" value="<?= $edit ? 'edit' : 'simpan' ?>">

                <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                <?php endif; ?>

                <label>Kode Rule</label>
                <input name="kode" required maxlength="10" pattern="[A-Za-z0-9_-]{2,10}"
                    value="<?= e($edit['kode'] ?? '') ?>" placeholder="R02">

                <label>Kesimpulan pelanggaran (THEN)</label>
                <select name="pelanggaran_id" required>
                    <option value="">-- Pilih pelanggaran --</option>
                    <?php foreach ($pelanggaran as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"
                        <?= isset($edit['pelanggaran_id']) && (int) $edit['pelanggaran_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['kode'] . ' - ' . $p['nama']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <h3>Kondisi fakta (IF)</h3>
                <p>Semua kondisi di bawah ini harus terpenuhi (AND).</p>

                <div id="kondisi-container">
                    <?php
$barisAwal = $kondisiEdit ?: [
    ['fakta_id' => '', 'nilai' => 'ya']
];

foreach ($barisAwal as $k):
?>
                    <div class="kondisi">
                        <label>Fakta</label>
                        <select name="fakta_id[]" required>
                            <option value="">-- Pilih fakta --</option>
                            <?php foreach ($fakta as $f): ?>
                            <option value="<?= (int) $f['id'] ?>"
                                <?= (string) $k['fakta_id'] === (string) $f['id'] ? 'selected' : '' ?>>
                                <?= e($f['kode'] . ' - ' . $f['pertanyaan']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Nilai yang harus terpenuhi</label>
                        <select name="nilai[]" required>
                            <option value="ya" <?= $k['nilai'] === 'ya' ? 'selected' : '' ?>>Ya</option>
                            <option value="tidak" <?= $k['nilai'] === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                        </select>

                        <button type="button" class="hapus" onclick="hapusKondisi(this)">Hapus kondisi</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="sekunder" onclick="tambahKondisi()">+ Tambah Kondisi</button>
                <br><br>
                <button type="submit">Simpan Rule</button>
                <?php if ($edit): ?>
                <a class="btn sekunder" href="rules.php">Batal</a>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </section>

        <section>
            <h2>Daftar Rule</h2>
            <div class="wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Aturan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                        <tr>
                            <td><?= e($r['kode']) ?></td>
                            <td>
                                <?php
                                $stmtKondisi->execute([$r['id']]);
                                $daftarKondisi = $stmtKondisi->fetchAll();
                                
                                if (!$daftarKondisi) {
                                    echo 'Kondisi belum diatur';
                                } else {
                                    $teks = [];
                                    foreach ($daftarKondisi as $k) {
                                        $teks[] = $k['kode'] . ' = ' . strtoupper($k['nilai']);
                                    }
                                    echo e('IF ' . implode(' AND ', $teks));
                                    echo '<br>';
                                }
                                ?>
                                <strong>THEN <?= e($r['pelanggaran_kode']) ?></strong>
                                - <?= e($r['pelanggaran_nama']) ?>
                            </td>
                            <td><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
                            <td>
                                <a class="btn" href="?edit=<?= (int) $r['id'] ?>">Edit</a>

                                <form method="post" class="inline">
                                    <input type="hidden" name="aksi" value="status">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="sekunder" type="submit">
                                        <?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>

                                <form method="post" class="inline" onsubmit="return confirm('Hapus rule ini?')">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="hapus" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$rules): ?>
                        <tr>
                            <td colspan="4">Belum ada rule.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        function tambahKondisi() {
            const container = document.getElementById('kondisi-container');
            const pertama = container.querySelector('.kondisi');
            const baru = pertama.cloneNode(true);

            baru.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0;
            });

            container.appendChild(baru);
        }

        function hapusKondisi(button) {
            const container = document.getElementById('kondisi-container');

            if (container.querySelectorAll('.kondisi').length <= 1) {
                alert('Rule harus memiliki minimal satu kondisi.');
                return;
            }

            button.closest('.kondisi').remove();
        }
    </script>
</body>

</html>
