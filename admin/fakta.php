
<?php
require_once __DIR__ . '/../config/database.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    try {
        if ($aksi === 'tambah' || $aksi === 'edit') {
            $kode = strtoupper(trim($_POST['kode'] ?? ''));
            $pertanyaan = trim($_POST['pertanyaan'] ?? '');

            if (
                !preg_match('/^[A-Z0-9_-]{2,10}$/', $kode) ||
                $pertanyaan === '' ||
                strlen($pertanyaan) > 255
            ) {
                throw new RuntimeException(
                    'Kode atau pertanyaan tidak valid.'
                );
            }

            if ($aksi === 'tambah') {
                $stmt = $pdo->prepare(
                    'INSERT INTO fakta (kode, pertanyaan)
                     VALUES (:kode, :pertanyaan)'
                );
                $stmt->execute([
                    'kode' => $kode,
                    'pertanyaan' => $pertanyaan
                ]);
                $pesan = 'Fakta berhasil ditambahkan.';
            } else {
                $id = filter_input(
                    INPUT_POST, 'id', FILTER_VALIDATE_INT
                );

                if (!$id || $id < 1) {
                    throw new RuntimeException('ID fakta tidak valid.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE fakta
                     SET kode = :kode, pertanyaan = :pertanyaan
                     WHERE id = :id'
                );
                $stmt->execute([
                    'kode' => $kode,
                    'pertanyaan' => $pertanyaan,
                    'id' => $id
                ]);
                $pesan = 'Fakta berhasil diperbarui.';
            }
        } elseif ($aksi === 'hapus') {
            $id = filter_input(
                INPUT_POST, 'id', FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('ID fakta tidak valid.');
            }

            $stmt = $pdo->prepare(
                'DELETE FROM fakta WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);

            $pesan = 'Fakta berhasil dihapus.';
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            $error = 'Kode fakta sudah digunakan atau fakta sudah dipakai dalam rule/jawaban. Periksa relasi terlebih dahulu.';
        } else {
            error_log($ex->getMessage());
            $error = 'Operasi database gagal.';
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$editFakta = null;

if (isset($_GET['edit'])) {
    $idEdit = filter_input(
        INPUT_GET, 'edit', FILTER_VALIDATE_INT
    );

    if ($idEdit && $idEdit > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM fakta WHERE id = :id'
        );
        $stmt->execute(['id' => $idEdit]);
        $editFakta = $stmt->fetch() ?: null;
    }
}

$stmt = $pdo->query(
    'SELECT id, kode, pertanyaan, aktif
     FROM fakta ORDER BY id DESC'
);
$fakta = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Fakta</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #1f2937;
        }
        .container {
            max-width: 1000px;
            margin: auto;
        }
        .panel {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        h1, h2 { margin-top: 0; }
        label {
            display: block;
            margin: 12px 0 6px;
        }
        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }
        button, .btn {
            display: inline-block;
            padding: 9px 13px;
            border: 0;
            border-radius: 5px;
            background: #1d4ed8;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .hapus { background: #b91c1c; }
        .batal { background: #6b7280; }
        .pesan { color: #166534; }
        .error { color: #b91c1c; }
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        th { background: #f9fafb; }
        .aksi { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">

    <div class="panel">
        <h1>Manajemen Fakta</h1>
        <p>Kelola fakta dan pertanyaan untuk konsultasi sistem pakar.</p>
        <a class="btn batal" href="../index.php">Kembali ke Beranda</a>
    </div>

    <div class="panel">
        <h2><?= $editFakta ? 'Edit Fakta' : 'Tambah Fakta' ?></h2>

        <?php if ($pesan): ?>
            <p class="pesan"><?= e($pesan) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="aksi"
                value="<?= $editFakta ? 'edit' : 'tambah' ?>">

            <?php if ($editFakta): ?>
                <input type="hidden" name="id"
                    value="<?= (int)$editFakta['id'] ?>">
            <?php endif; ?>

            <label for="kode">Kode fakta</label>
            <input id="kode" name="kode" required maxlength="10"
                pattern="[A-Za-z0-9_-]{2,10}"
                placeholder="Contoh: F03"
                value="<?= e($editFakta['kode'] ?? '') ?>">

            <label for="pertanyaan">Pertanyaan</label>
            <input id="pertanyaan" name="pertanyaan" required
                maxlength="255"
                placeholder="Contoh: Apakah pengendara melawan arus?"
                value="<?= e($editFakta['pertanyaan'] ?? '') ?>">

            <br><br>
            <button type="submit">
                <?= $editFakta ? 'Simpan Perubahan' : 'Tambah Fakta' ?>
            </button>

            <?php if ($editFakta): ?>
                <a class="btn batal" href="fakta.php">Batal</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="panel">
        <h2>Daftar Fakta (<?= count($fakta) ?>)</h2>

        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Pertanyaan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$fakta): ?>
                <tr>
                    <td colspan="4">Belum ada data fakta.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($fakta as $item): ?>
                <tr>
                    <td><?= e($item['kode']) ?></td>
                    <td><?= e($item['pertanyaan']) ?></td>
                    <td><?= $item['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
                    <td class="aksi">
                        <a class="btn"
                           href="?edit=<?= (int)$item['id'] ?>">
                            Edit
                        </a>

                        <form method="post"
                              style="display:inline"
                              onsubmit="return confirm('Hapus fakta ini?')">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id"
                                value="<?= (int)$item['id'] ?>">
                            <button class="hapus" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>
</body>
</html>
