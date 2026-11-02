<?php
require_once __DIR__ . '/../config/database.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    try {
        if ($aksi === 'tambah' || $aksi === 'edit') {
            $kode = strtoupper(trim($_POST['kode'] ?? ''));
            $nama = trim($_POST['nama'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $hukum = trim($_POST['referensi_hukum'] ?? '');

            if (!preg_match('/^[A-Z0-9_-]{2,10}$/', $kode) || $nama === '' || strlen($nama) > 150) {
                throw new RuntimeException('Data tidak valid.');
            }

            if ($aksi === 'tambah') {
                $stmt = $pdo->prepare(
                    'INSERT INTO pelanggaran
                    (kode, nama, deskripsi, referensi_hukum)
                    VALUES (?, ?, ?, ?)',
                );
                $stmt->execute([$kode, $nama, $deskripsi, $hukum]);
                $pesan = 'Pelanggaran berhasil ditambahkan.';
            } else {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

                if (!$id || $id < 1) {
                    throw new RuntimeException('ID tidak valid.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE pelanggaran
                     SET kode=?, nama=?, deskripsi=?, referensi_hukum=?
                     WHERE id=?',
                );
                $stmt->execute([$kode, $nama, $deskripsi, $hukum, $id]);
                $pesan = 'Pelanggaran berhasil diperbarui.';
            }
        } elseif ($aksi === 'hapus') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('ID tidak valid.');
            }

            $stmt = $pdo->prepare('DELETE FROM pelanggaran WHERE id=?');
            $stmt->execute([$id]);
            $pesan = 'Pelanggaran berhasil dihapus.';
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            $error = 'Kode sudah digunakan atau data masih dipakai oleh rule/hasil konsultasi.';
        } else {
            error_log($ex->getMessage());
            $error = 'Operasi database gagal.';
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($id && $id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM pelanggaran WHERE id=?');
        $stmt->execute([$id]);
        $edit = $stmt->fetch() ?: null;
    }
}

$data = $pdo->query('SELECT * FROM pelanggaran ORDER BY id DESC')->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Pelanggaran</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            padding: 24px;
            color: #1f2937
        }

        main {
            max-width: 1000px;
            margin: auto
        }

        section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px
        }

        input,
        textarea {
            display: block;
            width: 100%;
            padding: 10px;
            margin: 6px 0 14px;
            box-sizing: border-box
        }

        button,
        a {
            padding: 9px 12px;
            cursor: pointer
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        td,
        th {
            text-align: left;
            border-bottom: 1px solid #ddd;
            padding: 10px
        }

        .error {
            color: #b91c1c
        }

        .pesan {
            color: #166534
        }
    </style>
</head>

<body>
    <main>
        <section>
            <h1>Manajemen Pelanggaran</h1>
            <a href="../index.php">Beranda</a>
            <a href="fakta.php">Kelola Fakta</a>
        </section>

        <section>
            <h2><?= $edit ? 'Edit Pelanggaran' : 'Tambah Pelanggaran' ?></h2>

            <?php if ($pesan): ?><p class="pesan"><?= e($pesan) ?></p><?php endif; ?>
            <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

            <form method="post">
                <input type="hidden" name="aksi" value="<?= $edit ? 'edit' : 'tambah' ?>">
                <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                <?php endif; ?>

                <label>Kode</label>
                <input name="kode" maxlength="10" required value="<?= e($edit['kode'] ?? '') ?>" placeholder="P02">

                <label>Nama pelanggaran</label>
                <input name="nama" maxlength="150" required value="<?= e($edit['nama'] ?? '') ?>">

                <label>Deskripsi</label>
                <textarea name="deskripsi"><?= e($edit['deskripsi'] ?? '') ?></textarea>

                <label>Referensi hukum</label>
                <textarea name="referensi_hukum"><?= e($edit['referensi_hukum'] ?? '') ?></textarea>

                <button type="submit">Simpan</button>
                <?php if ($edit): ?><a href="pelanggaran.php">Batal</a><?php endif; ?>
            </form>
        </section>

        <section>
            <h2>Daftar Pelanggaran</h2>
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $item): ?>
                    <tr>
                        <td><?= e($item['kode']) ?></td>
                        <td><?= e($item['nama']) ?></td>
                        <td>
                            <a href="?edit=<?= (int) $item['id'] ?>">Edit</a>
                            <form method="post" style="display:inline"
                                onsubmit="return confirm('Hapus jenis pelanggaran ini?')">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>

</html>
