<?php
// public/create.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/../config/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price    = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock    = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    if (strlen($name) < 3) $errors[] = "Nama produk minimal 3 karakter.";
    if ($price === false || $price <= 0) $errors[] = "Harga harus lebih dari 0.";
    if ($stock === false || $stock < 0) $errors[] = "Stok tidak boleh negatif.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock) VALUES (:name, :category, :price, :stock)");
            $stmt->execute([
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'stock' => $stock
            ]);
            header("Location: index.php?status=created");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Nama produk sudah ada di database.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Produk Baru</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; padding: 40px 16px; }
        .form-card { max-width: 500px; margin: 0 auto; background: white; padding: 32px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #0072ff; color: white; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    </style>
</head>
<body>

<div class="form-card">
    <h2 style="margin-bottom: 20px;">Tambah Produk Baru</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <?php foreach ($errors as $err): ?>
                <div>• <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="create.php">
        <div class="form-group">
            <label>Nama Produk</label>
            <input type="text" name="name" required placeholder="Contoh: Niacinamide Serum">
        </div>
        <div class="form-group">
            <label>Kategori</label>
            <input type="text" name="category" required value="Skincare">
        </div>
        <div class="form-group">
            <label>Harga (Rp)</label>
            <input type="number" step="0.01" name="price" required placeholder="125000">
        </div>
        <div class="form-group">
            <label>Stok Initial</label>
            <input type="number" name="stock" required placeholder="10">
        </div>
        <div style="display: flex; gap: 10px; margin-top: 24px;">
            <button type="submit" class="btn btn-primary">Simpan Produk</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

</body>
</html>