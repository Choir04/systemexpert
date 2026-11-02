
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/engine/BackwardChaining.php';

$mesin = new BackwardChaining($pdo);

// Ganti ID ini jika P01 bukan ID 1 di database.
$hasil = $mesin->periksa(1, []);

echo '<pre>';
print_r($hasil);
echo '</pre>';
