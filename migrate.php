<?php
require 'config/database.php';

echo $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";

// Helper: cek apakah kolom sudah ada
function columnExists(PDO $pdo, string $table, string $col): bool {
    $r = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
    return $r !== false;
}

function fkExists(PDO $pdo, string $table, string $fkName): bool {
    $r = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '$table'
           AND CONSTRAINT_NAME = '$fkName'"
    )->fetch();
    return $r !== false;
}

$steps = [];

// Tabel konsultasi: tambah kolom pelanggaran_id
if (!columnExists($pdo, 'konsultasi', 'pelanggaran_id')) {
    $pdo->exec("ALTER TABLE `konsultasi` ADD COLUMN `pelanggaran_id` INT NULL AFTER `id`");
    $steps[] = "konsultasi.pelanggaran_id added";
}

if (!columnExists($pdo, 'konsultasi', 'rule_id')) {
    $pdo->exec("ALTER TABLE `konsultasi` ADD COLUMN `rule_id` INT NULL AFTER `pelanggaran_id`");
    $steps[] = "konsultasi.rule_id added";
}

if (!columnExists($pdo, 'konsultasi', 'hasil')) {
    $pdo->exec("ALTER TABLE `konsultasi` ADD COLUMN `hasil` ENUM('terbukti','tidak_terbukti') NULL AFTER `status`");
    $steps[] = "konsultasi.hasil added";
}

if (!columnExists($pdo, 'konsultasi', 'catatan')) {
    $pdo->exec("ALTER TABLE `konsultasi` ADD COLUMN `catatan` TEXT NULL AFTER `hasil`");
    $steps[] = "konsultasi.catatan added";
}

if (!columnExists($pdo, 'konsultasi', 'rule_kode')) {
    $pdo->exec("ALTER TABLE `konsultasi` ADD COLUMN `rule_kode` VARCHAR(10) NULL AFTER `rule_id`");
    $steps[] = "konsultasi.rule_kode added";
}

// Tabel jawaban: tambah kolom urutan
if (!columnExists($pdo, 'jawaban', 'urutan')) {
    $pdo->exec("ALTER TABLE `jawaban` ADD COLUMN `urutan` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `nilai`");
    $steps[] = "jawaban.urutan added";
}

// FK (optional – skip bila gagal karena FK constraint engine)
if (!fkExists($pdo, 'konsultasi', 'fk_konsultasi_pelanggaran')) {
    try {
        $pdo->exec("ALTER TABLE `konsultasi` ADD CONSTRAINT `fk_konsultasi_pelanggaran`
                    FOREIGN KEY (`pelanggaran_id`) REFERENCES `pelanggaran`(`id`) ON DELETE SET NULL");
        $steps[] = "FK konsultasi->pelanggaran added";
    } catch (PDOException $e) {
        $steps[] = "FK konsultasi->pelanggaran skipped: " . $e->getMessage();
    }
}

if (!fkExists($pdo, 'konsultasi', 'fk_konsultasi_rule')) {
    try {
        $pdo->exec("ALTER TABLE `konsultasi` ADD CONSTRAINT `fk_konsultasi_rule`
                    FOREIGN KEY (`rule_id`) REFERENCES `rules`(`id`) ON DELETE SET NULL");
        $steps[] = "FK konsultasi->rules added";
    } catch (PDOException $e) {
        $steps[] = "FK konsultasi->rules skipped: " . $e->getMessage();
    }
}

foreach ($steps as $s) echo "  $s\n";

echo "\nFinal schema:\n";
foreach (['konsultasi', 'jawaban'] as $t) {
    $cols = $pdo->query("DESCRIBE `$t`")->fetchAll();
    echo "  [$t]: " . implode(', ', array_column($cols, 'Field')) . "\n";
}
