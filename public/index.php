<?php
// public/index.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Mengambil file koneksi database dari luar folder public
require_once __DIR__ . '/../config/db.php';

// Cek apakah fungsi formatRupiah belum dideklarasikan
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// Generate Token CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$edit_product = null;
$status = $_GET['status'] ?? '';

// 1. PROSES FORM (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        die("Validasi CSRF Token Gagal.");
    }

    $action = $_POST['action'] ?? '';

    // TAMBAH / EDIT PRODUK
    if ($action === 'create' || $action === 'update') {
        $name        = trim($_POST['name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $raw_price   = $_POST['price'] ?? '';
        $clean_price = preg_replace('/[^\d]/', '', $raw_price);
        $price       = $clean_price !== '' ? (float)$clean_price : false;
        
        $stock    = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
        $id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (mb_strlen($name) < 2) {
            $errors[] = "Nama produk minimal 2 karakter.";
        }
        if ($price === false || $price <= 0) {
            $errors[] = "Harga produk harus lebih dari 0.";
        }
        if ($stock === false || $stock < 0) {
            $errors[] = "Stok tidak boleh bernilai negatif.";
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name = :name");
                $stmt->execute(['name' => $name]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name = :name AND id != :id");
                $stmt->execute(['name' => $name, 'id' => $id]);
            }

            if ($stmt->fetch()) {
                $errors[] = "Nama produk ini sudah ada. Gunakan nama lain.";
            }
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO products (name, category, price, stock) VALUES (:name, :category, :price, :stock)");
                $stmt->execute(['name' => $name, 'category' => $category, 'price' => $price, 'stock' => $stock]);
                header("Location: index.php?status=success");
            } else {
                $stmt = $pdo->prepare("UPDATE products SET name = :name, category = :category, price = :price, stock = :stock WHERE id = :id");
                $stmt->execute(['name' => $name, 'category' => $category, 'price' => $price, 'stock' => $stock, 'id' => $id]);
                header("Location: index.php?status=updated");
            }
            exit;
        }
    }

    // HAPUS PRODUK
    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }
        header("Location: index.php?status=deleted");
        exit;
    }
}

// 2. DATA UNTUK EDIT
if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $edit_id]);
        $edit_product = $stmt->fetch();
    }
}

// 3. AMBIL DATA PRODUK & CARI/FILTER
$q = trim($_GET['q'] ?? '');
$cat_filter = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (name LIKE :q1 OR category LIKE :q2)";
    $params['q1'] = "%$q%";
    $params['q2'] = "%$q%";
}

if ($cat_filter !== '') {
    $sql .= " AND category = :cat";
    $params['cat'] = $cat_filter;
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// 4. STATISTIK & AMBIL SEMUA KATEGORI
$all_products = $pdo->query("SELECT price, stock, category FROM products")->fetchAll();
$total_count  = count($all_products);
$total_value  = 0;
$total_stock  = 0;
$low_stock    = 0;
$categories   = [];

foreach ($all_products as $item) {
    $total_value += ($item['price'] * $item['stock']);
    $total_stock += $item['stock'];
    if ($item['stock'] <= 5) {
        $low_stock++;
    }
    if (!empty($item['category']) && !in_array($item['category'], $categories)) {
        $categories[] = $item['category'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MahzaGlow Luxury Inventory</title>
    
    <!-- Google Fonts & Font Awesome Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg-gradient: linear-gradient(145deg, #e0e7ff 0%, #dbeafe 35%, #ecfeff 100%);
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.9);
            --card-bg: rgba(255, 255, 255, 0.92);
            --primary: #0284c7;
            --primary-gradient: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            --hero-glow: 0 16px 36px -6px rgba(37, 99, 235, 0.35);
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --radius-xl: 36px;
            --radius-lg: 24px;
            --radius-md: 16px;
            --shadow-soft: 0 12px 32px -10px rgba(148, 163, 184, 0.2);
            --shadow-hover: 0 22px 45px -10px rgba(14, 165, 233, 0.25);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed;
            color: var(--text-dark);
            padding: 40px 24px;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* LIGHTING GLOW AMBIENT BACKGROUND */
        body::before {
            content: '';
            position: fixed;
            top: -100px;
            left: 20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.18) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
            pointer-events: none;
        }

        .wrapper {
            max-width: 1320px;
            margin: 0 auto;
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: var(--radius-xl);
            padding: 40px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 35px 70px -15px rgba(15, 23, 42, 0.08);
        }

        /* HEADER BRANDING */
        .app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .brand-icon {
            width: 62px;
            height: 62px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 26px;
            box-shadow: var(--hero-glow);
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .brand-title {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        .header-tag {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--glass-border);
            padding: 10px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        /* HERO STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 36px;
        }

        @media (max-width: 1100px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: 1fr; } }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .stat-card.hero {
            background: var(--primary-gradient);
            border: none;
            color: white;
            box-shadow: var(--hero-glow);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-icon.hero { background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); color: white; }
        .stat-icon.cyan { background: #e0f2fe; color: #0284c7; }
        .stat-icon.blue { background: #dbeafe; color: #2563eb; }
        .stat-icon.amber { background: #fef3c7; color: #d97706; }

        .stat-value { font-size: 26px; font-weight: 800; letter-spacing: -0.6px; }
        .stat-card.hero .stat-value { font-size: 28px; }
        .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
        .stat-card.hero .stat-label { color: rgba(255, 255, 255, 0.9); }

        /* ALERT NOTIFICATION */
        .alert {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 16px 24px;
            border-radius: var(--radius-md);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(34, 197, 94, 0.08);
        }

        .alert.alert-danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #b91c1c;
        }

        /* MAIN LAYOUT GRID */
        .main-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 32px;
            align-items: start;
        }

        @media (max-width: 1024px) { .main-layout { grid-template-columns: 1fr; } }

        /* SIDEBAR FORM CARD */
        .card-form {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-soft);
        }

        .form-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 24px;
            color: var(--text-dark);
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .input-wrapper { position: relative; }
        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            transition: color 0.2s;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px 14px 48px;
            border-radius: var(--radius-md);
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            font-family: inherit;
            font-size: 14px;
            color: var(--text-dark);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-control:focus {
            outline: none;
            border-color: #0ea5e9;
            background: white;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15);
        }

        .form-control:focus + i { color: #0ea5e9; }

        .price-preview-tag {
            font-size: 12px;
            color: #0284c7;
            margin-top: 8px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* BUTTON STYLES */
        .btn {
            padding: 14px 24px;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }

        .btn-block { width: 100%; }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: var(--hero-glow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 36px -5px rgba(37, 99, 235, 0.5);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--text-dark);
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover { background: #e2e8f0; }

        /* SEARCH & CATEGORY FILTER BAR */
        .filter-section {
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .search-form { display: flex; gap: 12px; }
        .search-input-wrapper { flex-grow: 1; position: relative; }
        .search-input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input {
            width: 100%;
            padding: 14px 18px 14px 48px;
            border-radius: var(--radius-md);
            border: 1.5px solid #e2e8f0;
            background: var(--card-bg);
            font-family: inherit;
            font-size: 14px;
            box-shadow: var(--shadow-soft);
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12);
        }

        .category-chips {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 6px;
            scrollbar-width: none;
        }

        .category-chips::-webkit-scrollbar { display: none; }

        .chip {
            padding: 8px 18px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #e2e8f0;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .chip:hover, .chip.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 6px 16px rgba(14, 165, 233, 0.3);
        }

        /* PRODUCT CARDS CATALOG GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: var(--shadow-soft);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: #93c5fd;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .category-badge {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 10.5px;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .stock-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .stock-pill.safe { background: #dcfce7; color: #15803d; }
        .stock-pill.warning { background: #fef3c7; color: #b45309; }
        .stock-pill.danger { background: #fee2e2; color: #b91c1c; }

        .product-name {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 8px;
            color: var(--text-dark);
            line-height: 1.3;
        }

        .product-price {
            font-size: 22px;
            font-weight: 800;
            color: #0284c7;
            margin-bottom: 20px;
            letter-spacing: -0.4px;
        }

        .stock-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 20px;
        }

        .stock-box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 700;
        }

        .stock-progress {
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            margin-top: 10px;
            overflow: hidden;
        }

        .stock-fill { height: 100%; border-radius: 10px; transition: width 0.4s ease; }
        .stock-fill.safe { background: linear-gradient(90deg, #4ade80, #22c55e); }
        .stock-fill.warning { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
        .stock-fill.danger { background: linear-gradient(90deg, #f87171, #ef4444); }

        .card-actions {
            display: flex;
            gap: 10px;
        }

        .btn-edit {
            flex: 1;
            padding: 10px;
            font-size: 12.5px;
            background: #f8fafc;
            color: var(--text-dark);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .btn-edit:hover { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }

        .btn-delete {
            flex: 1;
            padding: 10px;
            font-size: 12.5px;
            background: #fff5f5;
            color: #ef4444;
            border: 1px solid #ffe3e3;
            border-radius: 12px;
        }

        .btn-delete:hover { background: #ef4444; color: white; border-color: #ef4444; }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border: 2px dashed #e2e8f0;
            border-radius: var(--radius-lg);
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- HEADER BRANDING -->
    <header class="app-header">
        <div class="brand-container">
            <div class="brand-icon">
                <!-- Tempat Logo Gambar MahzaGlow -->
                <!-- <img src="assets/logo.png" alt="MahzaGlow Logo"> -->
                <i class="fa-solid fa-sparkles"></i>
            </div>
            <div>
                <h1 class="brand-title">MahzaGlow</h1>
                <p class="brand-subtitle">Sistem manajemen stok & inventaris skincare real-time</p>
            </div>
        </div>

        <div class="header-tag">
            <i class="fa-solid fa-shield-halved"></i> Live Inventory Engine
        </div>
    </header>

    <!-- STATS CARDS -->
    <section class="stats-grid">
        <div class="stat-card hero">
            <div class="stat-icon hero"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <div class="stat-value"><?= formatRupiah($total_value) ?></div>
                <div class="stat-label">Total Nilai Inventaris</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon cyan"><i class="fa-solid fa-pump-soap"></i></div>
            <div>
                <div class="stat-value"><?= number_format($total_count) ?></div>
                <div class="stat-label">Total Jenis Skincare</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
                <div class="stat-value"><?= number_format($total_stock) ?></div>
                <div class="stat-label">Total Unit Stok</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="stat-value"><?= number_format($low_stock) ?></div>
                <div class="stat-label">Stok Menipis (≤ 5)</div>
            </div>
        </div>
    </section>

    <!-- NOTIFIKASI ALERT -->
    <?php if ($status === 'success'): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-check" style="font-size:20px;"></i>
            Data skincare berhasil disimpan!
        </div>
    <?php elseif ($status === 'updated'): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-check" style="font-size:20px;"></i>
            Data skincare berhasil diperbarui!
        </div>
    <?php elseif ($status === 'deleted'): ?>
        <div class="alert">
            <i class="fa-solid fa-circle-check" style="font-size:20px;"></i>
            Skincare berhasil dihapus.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation" style="font-size:20px;"></i>
            <div>
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err, ENT_QUOTES, "UTF-8") ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- SIDEBAR FORM -->
        <aside class="card-form">
            <h2 class="form-title">
                <i class="fa-solid <?= $edit_product ? 'fa-pen-to-square' : 'fa-plus-circle' ?>" style="color:#0ea5e9;"></i>
                <?= $edit_product ? 'Edit Skincare' : 'Tambah Skincare' ?>
            </h2>

            <form method="POST" action="index.php">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                <input type="hidden" name="action" value="<?= $edit_product ? 'update' : 'create' ?>">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Nama Produk</label>
                    <div class="input-wrapper">
                        <input type="text" id="name" name="name" class="form-control" placeholder="Skintific Niacinamide Serum" value="<?= htmlspecialchars($edit_product['name'] ?? '', ENT_QUOTES, "UTF-8") ?>" required>
                        <i class="fa-solid fa-tag"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category">Kategori</label>
                    <div class="input-wrapper">
                        <input type="text" id="category" name="category" class="form-control" placeholder="Serum / Sunscreen" value="<?= htmlspecialchars($edit_product['category'] ?? 'Serum', ENT_QUOTES, "UTF-8") ?>" required>
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="price">Harga Produk (Rupiah)</label>
                    <div class="input-wrapper">
                        <input type="text" id="price" name="price" class="form-control" placeholder="129.000" value="<?= isset($edit_product['price']) ? number_format($edit_product['price'], 0, ',', '.') : '' ?>" oninput="formatRupiahInput(this)" required>
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div class="price-preview-tag" id="pricePreview">
                        <i class="fa-solid fa-eye"></i> Format: <?= isset($edit_product['price']) ? formatRupiah($edit_product['price']) : 'Rp 0' ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="stock">Jumlah Stok</label>
                    <div class="input-wrapper">
                        <input type="number" id="stock" name="stock" class="form-control" placeholder="25" value="<?= htmlspecialchars($edit_product['stock'] ?? '', ENT_QUOTES, "UTF-8") ?>" required>
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 28px;">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= $edit_product ? 'Update Skincare' : 'Simpan Skincare' ?>
                    </button>
                    <?php if ($edit_product): ?>
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main>
            <!-- SEARCH & CATEGORY FILTER BAR -->
            <div class="filter-section">
                <form method="GET" action="index.php" class="search-form">
                    <?php if ($cat_filter !== ''): ?>
                        <input type="hidden" name="category" value="<?= htmlspecialchars($cat_filter, ENT_QUOTES, "UTF-8") ?>">
                    <?php endif; ?>
                    <div class="search-input-wrapper">
                        <input type="text" name="q" class="search-input" value="<?= htmlspecialchars($q, ENT_QUOTES, "UTF-8") ?>" placeholder="Cari nama produk skincare...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <button type="submit" class="btn btn-secondary">Cari</button>
                    <?php if ($q !== '' || $cat_filter !== ''): ?>
                        <a href="index.php" class="btn btn-secondary" title="Reset Filter"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>

                <!-- FILTER KATEGORI QUICK CHIPS -->
                <?php if (!empty($categories)): ?>
                    <div class="category-chips">
                        <a href="index.php<?= $q !== '' ? '?q='.urlencode($q) : '' ?>" class="chip <?= $cat_filter === '' ? 'active' : '' ?>">Semua Kategori</a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="index.php?category=<?= urlencode($cat) ?><?= $q !== '' ? '&q='.urlencode($q) : '' ?>" class="chip <?= $cat_filter === $cat ? 'active' : '' ?>">
                                <?= htmlspecialchars($cat, ENT_QUOTES, "UTF-8") ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PRODUCT CARDS GRID -->
            <div class="products-grid">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open" style="font-size: 48px; margin-bottom: 14px; color: #cbd5e1;"></i>
                        <p style="font-weight: 600;">Tidak ada produk skincare ditemukan.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($products as $p): ?>
                    <?php 
                        $stock_class = 'safe';
                        $stock_label = $p['stock'] . ' unit';
                        if ($p['stock'] == 0) {
                            $stock_class = 'danger';
                            $stock_label = 'Habis';
                        } elseif ($p['stock'] <= 5) {
                            $stock_class = 'warning';
                            $stock_label = 'Sisa ' . $p['stock'];
                        }

                        $stock_percentage = min(100, max(8, ($p['stock'] / 20) * 100));
                    ?>
                    <div class="product-card">
                        <div>
                            <div class="card-header">
                                <span class="category-badge"><?= htmlspecialchars($p['category'], ENT_QUOTES, "UTF-8") ?></span>
                                <span class="stock-pill <?= $stock_class ?>">
                                    <i class="fa-solid fa-circle" style="font-size:7px;"></i> <?= $stock_label ?>
                                </span>
                            </div>

                            <h3 class="product-name"><?= htmlspecialchars($p['name'], ENT_QUOTES, "UTF-8") ?></h3>
                            <div class="product-price"><?= formatRupiah($p['price']) ?></div>

                            <div class="stock-box">
                                <div class="stock-box-header">
                                    <span>Stok Tersedia</span>
                                    <strong style="color:var(--text-dark);"><?= number_format($p['stock']) ?> Unit</strong>
                                </div>
                                <div class="stock-progress">
                                    <div class="stock-fill <?= $stock_class ?>" style="width: <?= $stock_percentage ?>%;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="index.php?edit=<?= $p['id'] ?>" class="btn btn-edit">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </a>

                            <form method="POST" action="index.php" onsubmit="return confirm('Yakin mau menghapus produk ini?');" style="flex: 1;">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-delete btn-block">
                                    <i class="fa-solid fa-trash"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>

    </div>
</div>

<script>
    // Format Rupiah Input Listener
    function formatRupiahInput(input) {
        let rawValue = input.value.replace(/[^\d]/g, '');
        if (rawValue === '') {
            input.value = '';
            document.getElementById('pricePreview').innerHTML = '<i class="fa-solid fa-eye"></i> Format: Rp 0';
            return;
        }
        let formatted = new Intl.NumberFormat('id-ID').format(rawValue);
        input.value = formatted;
        document.getElementById('pricePreview').innerHTML = '<i class="fa-solid fa-eye"></i> Format: Rp ' + formatted;
    }
</script>

</body>
</html>