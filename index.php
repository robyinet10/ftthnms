<?php
session_start();

// Include database connection
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'];
$is_admin = ($user_role === 'admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FTTH Schematic Network Management System | Dashboard</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Leaflet CSS (Latest Version) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet Fullscreen Plugin -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-fullscreen@1.0.1/dist/leaflet.fullscreen.css" />
    <!-- Leaflet Routing Machine -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
    <!-- Dashboard Custom CSS -->
    <link rel="stylesheet" href="assets/css/dashboard-custom.css?v=<?php echo time(); ?>">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Preloader -->
    <div class="preloader flex-column justify-content-center align-items-center">
        <i class="fas fa-network-wired fa-3x text-primary"></i>
        <h4 class="mt-3">FTTH Schematic Network Management System</h4>
    </div>

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="index.php" class="nav-link">Dashboard</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- User Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                    <i class="fas fa-user-circle"></i>
                    <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="badge badge-<?php echo $is_admin ? 'danger' : 'info'; ?> ml-1">
                        <?php echo $is_admin ? 'Admin' : 'Teknisi'; ?>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <div class="dropdown-header">
                        <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username']); ?></small>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" onclick="showProfile()">
                        <i class="fas fa-user mr-2"></i>Profil
                    </a>
                    <?php if ($is_admin): ?>
                    <a class="dropdown-item" href="#" onclick="showUserManagement()">
                        <i class="fas fa-users mr-2"></i>Kelola User
                    </a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="#" onclick="logout()">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="index.php" class="brand-link">
            <i class="fas fa-network-wired brand-image img-circle elevation-3" style="opacity: .8; margin-left: 10px; color: white;"></i>
            <span class="brand-text font-weight-light">FTTH NMS</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon fas fa-map"></i>
                            <p>Peta FTTH</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showItemList()">
                            <i class="nav-icon fas fa-list"></i>
                            <p>Daftar Item</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showRouteList()">
                            <i class="nav-icon fas fa-route"></i>
                            <p>Routing Kabel</p>
                        </a>
                    </li>

                    <li class="nav-header">NAVIGASI PETA</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="zoomToItems()">
                            <i class="nav-icon fas fa-expand-arrows-alt" style="color: #17a2b8;"></i>
                            <p>Zoom Semua Item</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-search-location" style="color: #6f42c1;"></i>
                            <p>
                                Zoom ke Item
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" class="nav-link" onclick="zoomToItemType('OLT')">
                                    <i class="fas fa-server nav-icon" style="color: #FF6B6B;"></i>
                                    <p>Zoom ke OLT</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" onclick="zoomToItemType('Tiang Tumpu')">
                                    <i class="fas fa-tower-broadcast nav-icon" style="color: #4ECDC4;"></i>
                                    <p>Zoom ke Tiang</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" onclick="zoomToItemType('Tiang ODP')">
                                    <i class="fas fa-project-diagram nav-icon" style="color: #45B7D1;"></i>
                                    <p>Zoom ke Tiang ODP</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" onclick="zoomToItemType('Tiang ODC')">
                                    <i class="fas fa-network-wired nav-icon" style="color: #96CEB4;"></i>
                                    <p>Zoom ke Tiang ODC</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" onclick="zoomToItemType('Tiang Joint Closure')">
                                    <i class="fas fa-link nav-icon" style="color: #E74C3C;"></i>
                                    <p>Zoom ke Joint Closure</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" onclick="zoomToItemType('Server')">
                                    <i class="fas fa-server nav-icon" style="color: #8E44AD;"></i>
                                    <p>Zoom ke Server/Router</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" onclick="zoomToItemType('ONT')">
                                    <i class="fas fa-home nav-icon" style="color: #FFA500;"></i>
                                    <p>Zoom ke ONT</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="locateUser()">
                            <i class="nav-icon fas fa-location-arrow" style="color: #dc3545;"></i>
                            <p>Cari Lokasi Saya</p>
                        </a>
                    </li>
                    <li class="nav-header">KONTROL ROUTES</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="refreshRoutes()">
                            <i class="nav-icon fas fa-sync-alt" style="color: #007bff;"></i>
                            <p>Refresh Routes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="toggleRoutes()">
                            <i class="nav-icon fas fa-eye" style="color: #6c757d;"></i>
                            <p>Tampilkan/Sembunyikan</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="clearAllRoutes()">
                            <i class="nav-icon fas fa-eraser" style="color: #dc3545;"></i>
                            <p>Hapus Semua Routes</p>
                        </a>
                    </li>
                    <li class="nav-header">IMPORT / EXPORT DATA</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showImportKMZModal()">
                            <i class="nav-icon fas fa-upload" style="color: #ffc107;"></i>
                            <p>Import KMZ</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="exportToKMZ()">
                            <i class="nav-icon fas fa-download" style="color: #28a745;"></i>
                            <p>Export ke KMZ</p>
                        </a>
                    </li>

                    <?php if ($is_admin): ?>
                    <li class="nav-header">ITEM FTTH</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('OLT')">
                            <i class="nav-icon fas fa-server" style="color: #FF6B6B;"></i>
                            <p>Tambah OLT</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('Tiang Tumpu')">
                            <i class="nav-icon fas fa-tower-broadcast" style="color: #4ECDC4;"></i>
                            <p>Tambah Tiang Tumpu</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('Tiang ODP')">
                            <i class="nav-icon fas fa-project-diagram" style="color: #45B7D1;"></i>
                            <p>Tambah Tiang ODP</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('Tiang ODC')">
                            <i class="nav-icon fas fa-network-wired" style="color: #96CEB4;"></i>
                            <p>Tambah Tiang ODC</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('Tiang Joint Closure')">
                            <i class="nav-icon fas fa-link" style="color: #E74C3C;"></i>
                            <p>Tambah Joint Closure</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('Server')">
                            <i class="nav-icon fas fa-server" style="color: #8E44AD;"></i>
                            <p>Tambah Server/Router</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('ONT')">
                            <i class="nav-icon fas fa-home" style="color: #FFA500;"></i>
                            <p>Tambah ONT</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="addNewItem('HTB')">
                            <i class="nav-icon fas fa-home" style="color: #FF6B9D;"></i>
                            <p>Tambah HTB</p>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
        <h1 class="m-0">Dashboard</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Map Container -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-map mr-1"></i>
                                    Peta Infrastruktur FTTH
                                </h3>
                                <div class="card-tools">
                                    <?php if ($is_admin): ?>
                                    <a href="tutorial.html" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-book"></i> Tutorial
                                    </a>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showAddItemModal()">
                                        <i class="fas fa-plus"></i> Tambah Item
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" onclick="showRoutingMode()">
                                        <i class="fas fa-route"></i> Mode Routing
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="showImportKMZModal()">
                                        <i class="fas fa-upload"></i> Import KMZ
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-success btn-sm" onclick="exportToKMZ()">
                                        <i class="fas fa-download"></i> Export KMZ
                                    </button>
                                    <a href="accounting.php" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-calculator"></i> Accounting
                                    </a>

                                    
                                    <!-- Map Zoom Controls -->
                                    <div class="map-zoom-controls">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="zoomToItems()" title="Zoom ke Semua Item">
                                            <i class="fas fa-expand-arrows-alt"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="locateUser()" title="Cari Lokasi Saya">
                                            <i class="fas fa-location-arrow"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="map.setView([-2.5, 118], 5)" title="Zoom ke Indonesia">
                                            <i class="fas fa-home"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="map.zoomIn()" title="Zoom In (+)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="map.zoomOut()" title="Zoom Out (-)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="map" style="height: 750px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards - Horizontal Layout -->
                <div class="row mb-2">
                    <!-- Server/Router -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-purple" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-server" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">Server</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- OLT -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-info" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-olt" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">OLT</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tiang -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-success" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-tiang" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">Tiang</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-tower-broadcast"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ODP -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-warning" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-odp" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">ODP</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ODC -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-danger" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-odc" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">ODC</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-network-wired"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ONT -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-primary" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-ont" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">ONT</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-home"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Routes -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-secondary" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-routes" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">Routes</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-route"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Joint Closure -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box bg-dark" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-joint-closure" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">Joint</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-link"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- HTB -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box" style="background-color: #FF6B9D; min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-htb" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">HTB</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-home"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Access Point -->
                    <div class="col-lg-1 col-md-2 col-sm-3 col-6">
                        <div class="small-box" style="background-color: #3498DB; min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-access-point" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">Access</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-wifi"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Items -->
                    <div class="col-lg-1 col-md-3 col-sm-4 col-12">
                        <div class="small-box bg-success" style="min-height: 65px;">
                            <div class="inner text-center" style="padding: 8px 4px;">
                                <h5 id="stat-total-items" style="margin: 0; font-size: 1.1rem;">0</h5>
                                <p style="margin: 0; font-size: 0.7rem; white-space: nowrap;">Total</p>
                            </div>
                            <div class="icon" style="font-size: 1rem; top: 5px; right: 5px;">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright &copy; 2025 <a href="#" onclick="showCopyrightPopup()">FTTH Schematic Network Management System</a> by Saputra Budi. Semua hak dilindungi undang-undang.</strong>
        <div class="float-right d-none d-sm-inline-block">
            <b>Versi</b> 2.0.0
        </div>
    </footer>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="itemModalTitle">Tambah Item FTTH</h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="itemForm">
                <div class="modal-body">
                    <input type="hidden" id="itemId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="itemType">Jenis Item</label>
                                <select class="form-control" id="itemType" name="item_type" required>
                                    <option value="">Pilih Jenis Item</option>
                                    <?php
                                    try {
                                        $database = new Database();
                                        $db = $database->getConnection();
                                        
                                        $query = "SELECT id, name FROM item_types ORDER BY id";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute();
                                        $item_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($item_types as $type) {
                                            echo "<option value=\"{$type['id']}\">{$type['name']}</option>";
                                        }
                                    } catch (Exception $e) {
                                        // Fallback ke hardcoded jika ada error
                                        echo '<option value="1">OLT</option>';
                                        echo '<option value="2">Tiang Tumpu</option>';
                                        echo '<option value="3">Tiang ODP</option>';
                                        echo '<option value="4">ODC Pole Mounted</option>';
                                        echo '<option value="5">Tiang Joint Closure</option>';
                                        echo '<option value="6">ONT</option>';
                                        echo '<option value="7">Server/Router</option>';

                                        echo '<option value="9">Access Point</option>';
                                        echo '<option value="10">HTB</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="itemName">Nama Item</label>
                                <input type="text" class="form-control" id="itemName" name="name" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="itemTypeName">Type Item</label>
                                <input type="text" class="form-control" id="itemTypeName" name="item_type_name" placeholder="Contoh: Huawei MA5800-X2, Tiang Beton 9m">
                                <small class="form-text text-muted">Type/Model dari item</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="itemPrice">Harga Item (Rp)</label>
                                <input type="number" class="form-control" id="itemPrice" name="item_price" placeholder="Contoh: 15000000" min="0" step="1000">
                                <small class="form-text text-muted">Harga dalam Rupiah</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="itemDescription">Deskripsi</label>
                        <textarea class="form-control" id="itemDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="itemAddress">Alamat</label>
                        <textarea class="form-control" id="itemAddress" name="address" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="itemLat">Latitude <span class="text-danger">*</span></label>
                                <input type="number" step="any" class="form-control" id="itemLat" name="latitude" placeholder="Contoh: -6.2088">
                                <small class="form-text text-muted">Koordinat Lintang (Latitude)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="itemLng">Longitude <span class="text-danger">*</span></label>
                                <input type="number" step="any" class="form-control" id="itemLng" name="longitude" placeholder="Contoh: 106.8456">
                                <small class="form-text text-muted">Koordinat Bujur (Longitude)</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>Cara menentukan lokasi:</strong>
                        <br>• Klik tombol "Pilih di Peta" di bawah ini
                        <br>• Atau tutup dialog ini dan klik langsung di peta, lalu pilih "Tambah Item"
                    </div>

                    <div class="text-center mb-3">
                        <button type="button" class="btn btn-outline-primary" onclick="selectLocationOnMap()">
                            <i class="fas fa-map-marker-alt"></i> Pilih Lokasi di Peta
                        </button>
                    </div>

                    <!-- Network Infrastructure Fields (Hidden for ONT & Server) -->
                    <div id="networkFields">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tubeColor">Warna Tube</label>
                                    <select class="form-control" id="tubeColor" name="tube_color_id">
                                        <option value="">Pilih Warna Tube</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="coreColor">Warna Core</label>
                                    <select class="form-control" id="coreColor" name="core_color_id">
                                        <option value="">Pilih Warna Core</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cableType">Jenis Kabel</label>
                                    <select class="form-control" id="cableType" name="item_cable_type">
                                        <option value="">Pilih Jenis Kabel</option>
                                        <option value="backbone">Backbone</option>
                                        <option value="distribution">Distribution</option>
                                        <option value="drop_core">Drop Core</option>
                                        <option value="feeder">Feeder</option>
                                        <option value="branch">Branch</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="totalCoreCapacity">Kapasitas Core Total</label>
                                    <select class="form-control" id="totalCoreCapacity" name="total_core_capacity">
                                        <option value="2">2 Core</option>
                                        <option value="4">4 Core</option>
                                        <option value="6">6 Core</option>
                                        <option value="8">8 Core</option>
                                        <option value="12">12 Core</option>
                                        <option value="24" selected>24 Core</option>
                                        <option value="48">48 Core</option>
                                        <option value="72">72 Core</option>
                                        <option value="96">96 Core</option>
                                        <option value="144">144 Core</option>
                                        <option value="216">216 Core</option>
                                        <option value="288">288 Core</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="coreUsed">Core yang Digunakan</label>
                                    <input type="number" class="form-control" id="coreUsed" name="core_used" min="0" max="288">
                                    <small class="form-text text-muted">Otomatis terisi dari perhitungan routing</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Core Tersedia</label>
                                    <input type="text" class="form-control" id="coreAvailable" readonly>
                                    <small class="form-text text-muted">Kapasitas Total - Core Digunakan</small>
                                </div>
                            </div>
                        </div>


                    </div>

                    <!-- Monitoring Fields (Only for ONT & Server) -->
                    <div id="monitoringFields" style="display: none;">
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle"></i>
                            <strong>Konfigurasi Monitoring:</strong> Field ini digunakan untuk monitoring real-time status koneksi.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="ipAddress">IP Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ipAddress" name="ip_address" placeholder="Contoh: 192.168.1.100 atau 2001:db8::1">
                                    <small class="form-text text-muted">IP Address untuk monitoring (IPv4 atau IPv6)</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="upstreamInterface">Interface Upstream Server</label>
                                    <select class="form-control" id="upstreamInterface" name="upstream_interface_id">
                                        <option value="">Pilih Interface Server...</option>
                                    </select>
                                    <small class="form-text text-muted">Pilih interface server yang terhubung ke device ini</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="portHttp">Port HTTP</label>
                                    <input type="number" class="form-control" id="portHttp" name="port_http" value="80" min="1" max="65535">
                                    <small class="form-text text-muted">Port untuk akses HTTP (default: 80)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="portHttps">Port HTTPS</label>
                                    <input type="number" class="form-control" id="portHttps" name="port_https" value="443" min="1" max="65535">
                                    <small class="form-text text-muted">Port untuk akses HTTPS (default: 443)</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Status Monitoring</label>
                                    <div class="d-flex align-items-center">
                                        <span id="monitoringStatus" class="badge badge-secondary mr-2">Belum Dimonitor</span>
                                        <small class="text-muted">Status akan diupdate secara real-time setelah item disimpan</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ping Status for Access Point -->
                        <div id="accessPointPingSection" style="display: none;">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Ping Status</label>
                                        <div class="d-flex align-items-center">
                                            <span id="pingStatus" class="badge badge-secondary mr-2">Tidak Diketahui</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary mr-2" id="testPingBtn" onclick="testPingStatus()">
                                                <i class="fas fa-satellite-dish"></i> Test Ping
                                            </button>
                                            <small class="text-muted">Status konektivitas real-time</small>
                                        </div>
                                        <div id="pingDetails" class="mt-2" style="display: none;">
                                            <small class="text-muted">
                                                <span id="pingResponseTime"></span>
                                                <span id="pingTimestamp"></span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Server/Router Fields (Only for Server) -->
                    <div id="serverFields" style="display: none;">
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-server"></i>
                            <strong>Konfigurasi Server/Router:</strong> Field ini digunakan untuk pencatatan VLAN dan IP Management.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="managementIp">IP Management <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="managementIp" name="ip_address" placeholder="Contoh: 192.168.1.10 atau 10.0.0.1">
                                    <small class="form-text text-muted">IP Address untuk management access (IPv4 atau IPv6)</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="managementPort">Port Management</label>
                                    <input type="number" class="form-control" id="managementPort" name="port_http" value="80" min="1" max="65535">
                                    <small class="form-text text-muted">Port untuk management interface (default: 80)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="httpsPort">Port HTTPS</label>
                                    <input type="number" class="form-control" id="httpsPort" name="port_https" value="443" min="1" max="65535">
                                    <small class="form-text text-muted">Port untuk HTTPS access (default: 443)</small>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic VLAN Fields -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-network-wired"></i> Konfigurasi VLAN
                                    <button type="button" class="btn btn-sm btn-success float-right" onclick="addVlanField()">
                                        <i class="fas fa-plus"></i> Tambah VLAN
                                    </button>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="vlanContainer">
                                    <!-- VLAN fields will be added dynamically -->
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Gunakan tombol "Tambah VLAN" untuk menambahkan konfigurasi VLAN baru
                                </small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Status Monitoring</label>
                                    <div class="d-flex align-items-center">
                                        <span id="serverMonitoringStatus" class="badge badge-secondary mr-2">Belum Dimonitor</span>
                                        <small class="text-muted">Status akan diupdate secara real-time setelah item disimpan</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- OLT Fields (Only for OLT) -->
                    <div id="oltFields" style="display: none;">
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-server"></i>
                            <strong>Konfigurasi OLT (Optical Line Terminal):</strong> Field ini digunakan untuk pencatatan PON dan multiple VLAN configuration.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="oltManagementIp">IP Management OLT <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="oltManagementIp" name="ip_address" placeholder="Contoh: 192.168.1.20 atau 10.0.0.20">
                                    <small class="form-text text-muted">IP Address untuk management access OLT (IPv4 atau IPv6)</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="oltUpstreamInterface">Interface Upstream Server</label>
                                    <select class="form-control" id="oltUpstreamInterface" name="upstream_interface_id">
                                        <option value="">Pilih Interface Server...</option>
                                    </select>
                                    <small class="form-text text-muted">Pilih interface server yang terhubung ke OLT ini</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="oltManagementPort">Port Management</label>
                                    <input type="number" class="form-control" id="oltManagementPort" name="port_http" value="80" min="1" max="65535">
                                    <small class="form-text text-muted">Port untuk management interface (default: 80)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="oltHttpsPort">Port HTTPS</label>
                                    <input type="number" class="form-control" id="oltHttpsPort" name="port_https" value="443" min="1" max="65535">
                                    <small class="form-text text-muted">Port untuk HTTPS access (default: 443)</small>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic PON Fields -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-project-diagram"></i> Konfigurasi PON (Passive Optical Network)
                                    <div class="float-right">
                                        <button type="button" class="btn btn-sm btn-success mr-1" onclick="refreshVlanOptions()" title="Refresh VLAN Options dari Server">
                                            <i class="fas fa-sync-alt"></i> Refresh VLAN
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="addPonField()">
                                            <i class="fas fa-plus"></i> Tambah PON
                                        </button>
                                    </div>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="ponContainer">
                                    <!-- PON fields will be added dynamically -->
                                </div>
                                <div class="alert alert-info py-2 mb-0">
                                    <small>
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>VLAN Integration:</strong> VLAN options dimuat otomatis dari Server/Router yang sudah terdaftar. 
                                        Pilih VLAN yang sesuai untuk setiap PON port. 
                                        <br>
                                        <i class="fas fa-lightbulb"></i> 
                                        <strong>Tip:</strong> Jika VLAN baru ditambahkan di Server, click "Refresh VLAN" untuk memperbarui pilihan.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Status Monitoring</label>
                                    <div class="d-flex align-items-center">
                                        <span id="oltMonitoringStatus" class="badge badge-secondary mr-2">Belum Dimonitor</span>
                                        <small class="text-muted">Status akan diupdate secara real-time setelah item disimpan</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced ODC Fields -->
                    <div id="odcFields" style="display: none;">
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-network-wired"></i>
                            <strong>Optical Distribution Cabinet (ODC) Configuration</strong><br>
                            Konfigurasi lengkap untuk ODC dengan port management dan PON integration.
                        </div>
                        
                        <!-- ODC Type Configuration -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="odcType">Tipe ODC</label>
                                    <select class="form-control" id="odcType" name="odc_type">
                                        <option value="pole_mounted">Pole Mounted (Passive)</option>
                                        <option value="ground_mounted">Ground Mounted (Active)</option>
                                    </select>
                                    <small class="text-muted">Pilih tipe instalasi ODC</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="odcInstallationType">Tipe Instalasi</label>
                                    <select class="form-control" id="odcInstallationType" name="odc_installation_type">
                                        <option value="pole">Pole Mounted</option>
                                        <option value="ground">Ground Mounted</option>
                                        <option value="wall">Wall Mounted</option>
                                    </select>
                                    <small class="text-muted">Metode instalasi fisik</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Splitter Configuration -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="odcMainSplitterRatio">Main Splitter Ratio</label>
                                    <select class="form-control" id="odcMainSplitterRatio" name="odc_main_splitter_ratio">
                                        <option value="1:2">1:2</option>
                                        <option value="1:4" selected>1:4</option>
                                        <option value="1:8">1:8</option>
                                        <option value="1:16">1:16</option>
                                    </select>
                                    <small class="text-muted">Ratio splitter utama</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="odcOdpSplitterRatio">ODP Splitter Ratio</label>
                                    <select class="form-control" id="odcOdpSplitterRatio" name="odc_odp_splitter_ratio">
                                        <option value="1:2">1:2</option>
                                        <option value="1:4">1:4</option>
                                        <option value="1:8" selected>1:8</option>
                                        <option value="1:16">1:16</option>
                                    </select>
                                    <small class="text-muted">Ratio splitter ODP</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Port Configuration -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odcInputPorts">Input Ports</label>
                                    <input type="number" class="form-control" id="odcInputPorts" name="odc_input_ports" value="1" min="1" max="4">
                                    <small class="text-muted">Jumlah port input dari backbone</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odcOutputPorts">Output Ports</label>
                                    <input type="number" class="form-control" id="odcOutputPorts" name="odc_output_ports" value="4" min="1" max="16">
                                    <small class="text-muted">Jumlah port output ke ODP</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odcCapacity">Kapasitas Customer</label>
                                    <input type="number" class="form-control" id="odcCapacity" name="odc_capacity" value="32" readonly>
                                    <small class="text-muted">Otomatis dihitung dari splitter ratio</small>
                                </div>
                            </div>
                        </div>
                        
                                                
                        <!-- PON Connection from OLT -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-network-wired"></i> PON Connection dari OLT
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="odcPonConnection">PON Connection <span class="text-danger">*</span></label>
                                            <select class="form-control" id="odcPonConnection" name="odc_pon_connection" onchange="updateOdcVlanFromPon()" required>
                                                <option value="">Pilih PON Connection dari OLT...</option>
                                            </select>
                                            <small class="text-muted">Pilih PON port dari OLT yang akan terhubung</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="odcVlanId">VLAN ID</label>
                                            <select class="form-control" id="odcVlanId" name="odc_vlan_id">
                                                <option value="">Auto-filled from PON...</option>
                                            </select>
                                            <small class="text-muted">VLAN ID otomatis dari PON yang dipilih</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info py-2 mb-0">
                                    <small>
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>PON Connection:</strong> Data diambil dari konfigurasi PON pada OLT yang sudah terdaftar di sistem.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Enhanced ODP Outputs -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-project-diagram"></i> Output ke ODP (Optical Distribution Point)
                                    <div class="float-right">
                                        <button type="button" class="btn btn-sm btn-success" onclick="addOdcOdpField()">
                                            <i class="fas fa-plus"></i> Tambah Output ODP
                                        </button>
                                </div>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="odcOdpContainer">
                                    <!-- ODP output fields will be added dynamically -->
                                </div>
                                <div class="alert alert-success py-2 mb-0">
                                    <small>
                                        <i class="fas fa-lightbulb"></i> 
                                        <strong>Output Configuration:</strong> Konfigurasi output port ke ODP dengan detail cable length dan attenuation.
                                        Maksimal output sesuai dengan kapasitas ODC yang dikonfigurasi.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attenuation Notes -->
                        <div class="form-group">
                            <label for="attenuationNotes">Catatan Redaman (dB)</label>
                            <textarea class="form-control" id="attenuationNotes" name="attenuation_notes" rows="4" 
                                      placeholder="Contoh:&#10;Input: 3.2 dB&#10;Output Port 1: 4.1 dB&#10;Output Port 2: 4.3 dB&#10;Output Port 3: 4.0 dB&#10;&#10;Catatan: Pengukuran dilakukan pada tanggal 2024-01-15"></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Masukkan nilai redaman untuk setiap port input dan output. Format bebas, gunakan baris baru untuk setiap port.
                            </small>
                        </div>
                        
                        <!-- Capacity Calculator -->
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-calculator"></i>
                            <strong>Capacity Calculator</strong><br>
                            <span id="capacityInfo">Total Capacity: 32 customers (4 ODP × 8 customers)</span>
                        </div>
                    </div>

                    <!-- Enhanced ODP Fields -->
                    <div id="odpFields" style="display: none;">
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-project-diagram"></i>
                            <strong>Optical Distribution Point (ODP) Configuration</strong><br>
                            Konfigurasi lengkap untuk ODP dengan input dari ODC dan output port untuk ONT/Customer.
                        </div>
                        
                        <!-- ODP Type Configuration -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odpType">Tipe ODP</label>
                                    <select class="form-control" id="odpType" name="odp_type">
                                        <option value="pole_mounted">Pole Mounted</option>
                                        <option value="wall_mounted">Wall Mounted</option>
                                        <option value="underground">Underground</option>
                                    </select>
                                    <small class="text-muted">Pilih tipe instalasi ODP</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odpSplitterRatio">Splitter Ratio</label>
                                    <select class="form-control" id="odpSplitterRatio" name="odp_splitter_ratio" onchange="updateOdpCapacity()">
                                        <option value="1:4">1:4 (4 customers)</option>
                                        <option value="1:8" selected>1:8 (8 customers)</option>
                                        <option value="1:16">1:16 (16 customers)</option>
                                        <option value="1:32">1:32 (32 customers)</option>
                                    </select>
                                    <small class="text-muted">Ratio splitter ODP</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odpCapacity">Kapasitas Customer</label>
                                    <input type="number" class="form-control" id="odpCapacity" name="odp_capacity" value="8" min="1" max="32" readonly>
                                    <small class="text-muted">Otomatis dihitung dari splitter ratio</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Port Configuration -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odpInputPorts">Input Ports</label>
                                    <input type="number" class="form-control" id="odpInputPorts" name="odp_input_ports" value="1" min="1" max="4">
                                    <small class="text-muted">Jumlah port input dari ODC</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="odpOutputPorts">Output Ports</label>
                                    <input type="number" class="form-control" id="odpOutputPorts" name="odp_output_ports" value="8" min="1" max="16">
                                    <small class="text-muted">Jumlah port output ke ONT</small>
                                </div>
                            </div>

                        </div>
                        
                        <!-- Enhanced ODC Inputs -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-plug"></i> Input dari ODC (Optical Distribution Cabinet)
                                    <div class="float-right">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="addOdpOdcField()">
                                            <i class="fas fa-plus"></i> Tambah Input ODC
                                        </button>
                                    </div>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="odpOdcContainer">
                                    <!-- ODC input fields will be added dynamically -->
                                </div>
                                <div class="alert alert-info py-2 mb-0">
                                    <small>
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>ODC Integration:</strong> Pilih ODC dan output port yang terhubung ke ODP ini (optional). 
                                        Multiple ODC dapat terhubung untuk redundancy atau ekspansi kapasitas.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Enhanced ONT Output Ports -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-network-wired"></i> Output Port untuk ONT/Customer
                                    <div class="float-right">
                                        <button type="button" class="btn btn-sm btn-success" onclick="refreshOntPorts()">
                                            <i class="fas fa-sync-alt"></i> Refresh Ports
                                        </button>
                                    </div>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="odpOntPortsContainer">
                                    <!-- ONT output ports will be populated automatically -->
                                </div>
                                <div class="alert alert-warning py-2 mb-0">
                                    <small>
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        <strong>Port Management:</strong> Port output diisi otomatis berdasarkan kapasitas splitter. 
                                        Gunakan untuk tracking koneksi ke customer/ONT dan status setiap port.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ODP Port Configuration -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="odpInputPorts">Input Ports</label>
                                    <input type="number" class="form-control" id="odpInputPorts" name="odp_input_ports" value="1" min="1" max="4">
                                    <small class="text-muted">Jumlah port input dari ODC</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="odpOutputPorts">Output Ports</label>
                                    <input type="number" class="form-control" id="odpOutputPorts" name="odp_output_ports" value="8" readonly>
                                    <small class="text-muted">Jumlah port output (otomatis dari splitter)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Capacity & Status Info -->
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-calculator"></i>
                            <strong>Status ODP</strong><br>
                            <div class="row">
                                <div class="col-md-6">
                                    <span id="odpStatusInfo">Kapasitas: 8 ports | Terpakai: 0 ports | Tersedia: 8 ports</span>
                                </div>
                                <div class="col-md-6">
                                    <span id="odpPortUsage" class="badge badge-success">0% Usage</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ONT Specific Fields -->
                    <div id="ontFields" style="display: none;">
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-home"></i>
                            <strong>Konfigurasi ONT (Optical Network Terminal):</strong> Field ini digunakan untuk pencatatan customer dan koneksi ke ODP.
                        </div>
                        
                        <!-- ONT Technical Info -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-microchip text-primary"></i> Informasi Teknis ONT
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="ontModel">Model ONT</label>
                                            <input type="text" class="form-control" id="ontModel" name="ont_model" placeholder="Contoh: Huawei HG8245H5, ZTE F670L">
                                            <small class="form-text text-muted">Model/tipe perangkat ONT</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="ontSerialNumber">Serial Number ONT</label>
                                            <input type="text" class="form-control" id="ontSerialNumber" name="ont_serial_number" placeholder="Contoh: HWTC12345678">
                                            <small class="form-text text-muted">Serial number untuk identifikasi ONT</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="ontInstallationType">Tipe Instalasi</label>
                                            <select class="form-control" id="ontInstallationType" name="ont_installation_type">
                                                <option value="indoor">Indoor (dalam ruangan)</option>
                                                <option value="outdoor">Outdoor (luar ruangan)</option>
                                                <option value="wall_mount">Wall Mount (dinding)</option>
                                                <option value="desktop">Desktop (meja)</option>
                                            </select>
                                            <small class="form-text text-muted">Tipe instalasi perangkat ONT</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="ontConnectionStatus">Status Koneksi</label>
                                            <select class="form-control" id="ontConnectionStatus" name="ont_connection_status">
                                                <option value="connected">Connected (terhubung)</option>
                                                <option value="disconnected">Disconnected (terputus)</option>
                                                <option value="maintenance">Maintenance (pemeliharaan)</option>
                                                <option value="suspended">Suspended (ditangguhkan)</option>
                                            </select>
                                            <small class="form-text text-muted">Status koneksi ONT saat ini</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ODP Connection -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-project-diagram text-success"></i> Koneksi ke ODP
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="ontConnectedOdp">ODP Terhubung</label>
                                            <select class="form-control" id="ontConnectedOdp" name="ont_connected_odp_id" onchange="updateOntOdpPorts()">
                                                <option value="">Pilih ODP...</option>
                                            </select>
                                            <small class="form-text text-muted">Pilih ODP yang terhubung ke ONT ini</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="ontConnectedPort">Port ODP</label>
                                            <select class="form-control" id="ontConnectedPort" name="ont_connected_port">
                                                <option value="">Pilih ODP dulu...</option>
                                            </select>
                                            <small class="form-text text-muted">Port di ODP yang digunakan</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="odpConnectionInfo" class="alert alert-light py-2 mt-2" style="display: none;">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Info ODP:</strong> <span id="odpInfoText">Pilih ODP untuk melihat informasi</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-user text-info"></i> Informasi Customer
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="ontCustomerName">Nama Customer</label>
                                            <input type="text" class="form-control" id="ontCustomerName" name="ont_customer_name" placeholder="Nama lengkap customer">
                                            <small class="form-text text-muted">Nama customer yang menggunakan ONT</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="ontServicePlan">Paket Layanan</label>
                                            <input type="text" class="form-control" id="ontServicePlan" name="ont_service_plan" placeholder="Contoh: 100Mbps, 50Mbps Premium">
                                            <small class="form-text text-muted">Paket layanan internet customer</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="ontCustomerAddress">Alamat Customer</label>
                                            <textarea class="form-control" id="ontCustomerAddress" name="ont_customer_address" rows="2" placeholder="Alamat lengkap customer"></textarea>
                                            <small class="form-text text-muted">Alamat detail instalasi ONT</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SNMP Configuration Fields (for Server, OLT, Access Point, ONT) -->
                    <div id="snmpFields" style="display: none;">
                        <div class="card mt-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="fas fa-chart-line text-success"></i> 
                                    <strong>SNMP Monitoring Configuration</strong>
                                    <div class="custom-control custom-switch float-right">
                                        <input type="checkbox" class="custom-control-input" id="snmpEnabled" name="snmp_enabled" value="1">
                                        <label class="custom-control-label" for="snmpEnabled">Enable SNMP</label>
                                    </div>
                                </h6>
                            </div>
                            <div class="card-body" id="snmpConfigBody" style="display: none;">
                                <div class="alert alert-info py-2">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>SNMP Monitoring:</strong> Memungkinkan monitoring real-time CPU, memory, bandwidth, temperature, dan metrics lainnya.
                                </div>
                                
                                <!-- Basic SNMP Configuration -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="snmpVersion">SNMP Version</label>
                                            <select class="form-control" id="snmpVersion" name="snmp_version">
                                                <option value="1">SNMPv1</option>
                                                <option value="2c" selected>SNMPv2c</option>
                                                <option value="3" disabled>SNMPv3 (Coming Soon)</option>
                                            </select>
                                            <small class="text-muted">Pilih versi SNMP yang didukung device</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="snmpPort">SNMP Port</label>
                                            <input type="number" class="form-control" id="snmpPort" name="snmp_port" value="161" min="1" max="65535">
                                            <small class="text-muted">Default SNMP port: 161</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- SNMPv1/v2c Configuration -->
                                <div id="snmpV2cConfig">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="snmpCommunity">Community String</label>
                                                <input type="text" class="form-control" id="snmpCommunity" name="snmp_community" value="public" placeholder="public">
                                                <small class="text-muted">
                                                    <i class="fas fa-shield-alt text-warning"></i>
                                                    Community string untuk akses SNMP (contoh: public, private, monitoring)
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SNMPv3 Configuration (placeholder) -->
                                <div id="snmpV3Config" style="display: none;">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-construction"></i>
                                        <strong>SNMPv3 Coming Soon:</strong> Secure authentication dengan username, password, dan encryption akan tersedia di update mendatang.
                                    </div>
                                </div>

                                <!-- SNMP Test Connection -->
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Test SNMP Connection</label>
                                            <div class="d-flex align-items-center">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="testSNMPConnection" onclick="testSNMPConnection()">
                                                    <i class="fas fa-plug"></i> Test Connection
                                                </button>
                                                <span id="snmpTestResult" class="ml-3"></span>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Test koneksi SNMP sebelum menyimpan konfigurasi
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Monitoring Schedule -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Monitoring Schedule</label>
                                            <div class="alert alert-success py-2">
                                                <i class="fas fa-clock"></i>
                                                <strong>Auto-Monitoring:</strong> SNMP metrics akan dikumpulkan setiap 5 menit secara otomatis untuk device yang enabled.
                                                <br>
                                                <small>Metrics disimpan selama 30 hari untuk analisa trend dan troubleshooting.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="itemStatus">Status</label>
                        <select class="form-control" id="itemStatus" name="status">
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveItem()">
                        <i class="fas fa-save"></i> Simpan Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Leaflet JS (Latest Version) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet Fullscreen Plugin -->
<script src="https://unpkg.com/leaflet-fullscreen@1.0.1/dist/leaflet.fullscreen.js"></script>
<!-- Leaflet Routing Machine -->
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<!-- JSZip for KMZ compression and import -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<!-- FileSaver for download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/map.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/kmz-export.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/kmz-import.js?v=<?php echo time(); ?>"></script>

<script>
// Setup global AJAX defaults for all requests
$.ajaxSetup({
    xhrFields: {
        withCredentials: true
    },
    beforeSend: function(xhr, settings) {
        // Always send credentials for same-origin requests
        if (!settings.crossDomain) {
            xhr.withCredentials = true;
        }
    }
});

// Global variables for user session
const currentUser = {
    id: <?php echo $_SESSION['user_id']; ?>,
    username: '<?php echo htmlspecialchars($_SESSION['username']); ?>',
    role: '<?php echo htmlspecialchars($_SESSION['role']); ?>',
    fullName: '<?php echo htmlspecialchars($user_name); ?>',
    isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>
};

// Authentication functions
function logout() {
    if (confirm('Apakah Anda yakin ingin logout?')) {
        $.ajax({
            url: 'api/auth.php?action=logout',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                alert('Logout berhasil!');
                window.location.href = 'login.php';
            },
            error: function(xhr, status, error) {
                console.error('Logout error:', error);
                // Force redirect even if logout fails
                window.location.href = 'login.php';
            }
        });
    }
}

function showProfile() {
    alert('Fitur profil akan segera tersedia');
}

function showUserManagement() {
    if (!currentUser.isAdmin) {
        alert('Akses ditolak: Fitur ini hanya untuk admin');
        return;
    }
    window.location.href = 'users.php';
}

// Role-based permission checking
function checkAdminPermission(action = 'melakukan aksi ini') {
    if (!currentUser.isAdmin) {
        alert(`Akses ditolak: Hanya admin yang dapat ${action}`);
        return false;
    }
    return true;
}

// Override functions that require admin permission
if (!currentUser.isAdmin) {
    // Disable admin-only functions for teknisi
    const originalShowAddItemModal = window.showAddItemModal;
    window.showAddItemModal = function() {
        checkAdminPermission('menambah item baru');
    };
    
    const originalShowRoutingMode = window.showRoutingMode;
    window.showRoutingMode = function() {
        checkAdminPermission('menggunakan mode routing');
    };
    
    const originalShowImportKMZModal = window.showImportKMZModal;
    window.showImportKMZModal = function() {
        checkAdminPermission('import KMZ');
    };
    
    const originalAddNewItem = window.addNewItem;
    window.addNewItem = function() {
        checkAdminPermission('menambah item baru');
    };
}

// Session timeout warning (30 minutes)
let sessionTimeout;
function resetSessionTimeout() {
    clearTimeout(sessionTimeout);
    sessionTimeout = setTimeout(function() {
        if (confirm('Sesi Anda akan berakhir dalam 5 menit. Ingin melanjutkan?')) {
            // Reset timeout for another 30 minutes
            resetSessionTimeout();
        } else {
            logout();
        }
    }, 25 * 60 * 1000); // 25 minutes (warn 5 minutes before expiry)
}

// Start session timeout
resetSessionTimeout();

// Reset timeout on user activity
$(document).on('click keypress mousemove', function() {
    resetSessionTimeout();
});

        console.log('🔐 FTTH Schematic Network Management System - Authenticated as:', currentUser.role.toUpperCase());

// VLAN Management Functions for Server/Router
let vlanFieldCounter = 0;

function addVlanField() {
    vlanFieldCounter++;
    const vlanId = 'vlan_' + vlanFieldCounter;
    
    const vlanHtml = `
        <div class="vlan-group border rounded p-3 mb-3" id="${vlanId}">
            <div class="row">
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="${vlanId}_id">VLAN ID</label>
                                <input type="number" class="form-control vlan-id" name="vlan_ids[]" 
                                       placeholder="Contoh: 100" min="1" max="4094">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="${vlanId}_ip">IP VLAN</label>
                                <input type="text" class="form-control vlan-ip" name="vlan_ips[]" 
                                       placeholder="Contoh: 192.168.100.1/24">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="${vlanId}_desc">Deskripsi</label>
                                <input type="text" class="form-control vlan-desc" name="vlan_descriptions[]" 
                                       placeholder="Contoh: VLAN Management">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeVlanField('${vlanId}')" 
                            title="Hapus VLAN">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    $('#vlanContainer').append(vlanHtml);
}

function removeVlanField(vlanId) {
    if ($('#vlanContainer .vlan-group').length > 1) {
        $(`#${vlanId}`).remove();
    } else {
        alert('Minimal harus ada satu VLAN yang terdefinisi');
    }
}

function clearVlanFields() {
    $('#vlanContainer').empty();
    vlanFieldCounter = 0;
}

function getVlanData() {
    const vlans = [];
    $('#vlanContainer .vlan-group').each(function() {
        const vlanId = $(this).find('.vlan-id').val();
        const vlanIp = $(this).find('.vlan-ip').val();
        const vlanDesc = $(this).find('.vlan-desc').val();
        
        if (vlanId || vlanIp || vlanDesc) {
            vlans.push({
                vlan_id: vlanId,
                ip: vlanIp,
                description: vlanDesc
            });
        }
    });
    return vlans;
}

function setVlanData(vlans) {
    clearVlanFields();
    
    if (vlans && vlans.length > 0) {
        vlans.forEach(function(vlan) {
            addVlanField();
            const lastGroup = $('#vlanContainer .vlan-group').last();
            lastGroup.find('.vlan-id').val(vlan.vlan_id || '');
            lastGroup.find('.vlan-ip').val(vlan.ip || '');
            lastGroup.find('.vlan-desc').val(vlan.description || '');
        });
    } else {
        // Add one empty field if no data
        addVlanField();
    }
}

// PON Management Functions for OLT
let ponFieldCounter = 0;
let availableVlans = []; // Cache for VLAN options from servers

function addPonField() {
    ponFieldCounter++;
    const ponId = 'pon_' + ponFieldCounter;
    
    const ponHtml = `
        <div class="pon-group border rounded p-3 mb-3" id="${ponId}" style="background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%);">
            <div class="row">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="${ponId}_port">PON Port</label>
                                <input type="text" class="form-control pon-port" name="pon_ports[]" 
                                       placeholder="Contoh: PON1/1/1 atau 0/1/1">
                                <small class="text-muted">Format: slot/port/pon atau pon/board/port</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="${ponId}_interface">Server Interface</label>
                                <select class="form-control pon-interface" name="pon_interfaces[]">
                                    <option value="">Pilih Interface Server...</option>
                                </select>
                                <small class="text-muted">Interface server yang terhubung ke PON ini</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="${ponId}_desc">Deskripsi PON</label>
                                <input type="text" class="form-control pon-desc" name="pon_descriptions[]" 
                                       placeholder="Contoh: PON untuk Area A">
                            </div>
                        </div>
                    </div>
                    
                    <!-- VLAN Configuration for this PON -->
                    <div class="card mt-2">
                        <div class="card-header py-2">
                            <h6 class="mb-0">
                                <i class="fas fa-network-wired text-primary"></i> VLAN untuk PON ini
                                <button type="button" class="btn btn-xs btn-success float-right" onclick="addPonVlanField('${ponId}')">
                                    <i class="fas fa-plus"></i> Tambah VLAN
                                </button>
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="pon-vlan-container" id="${ponId}_vlans">
                                <!-- VLAN fields for this PON will be added here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-right mt-2">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removePonField('${ponId}')" 
                                title="Hapus PON">
                            <i class="fas fa-trash"></i> Hapus PON
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#ponContainer').append(ponHtml);
    
    // Load interface options for the new PON field
    loadPonInterfaceOptions(ponId);
    
    // Add one VLAN field by default
    addPonVlanField(ponId);
}

function removePonField(ponId) {
    if ($('#ponContainer .pon-group').length > 1) {
        $(`#${ponId}`).remove();
    } else {
        alert('Minimal harus ada satu PON yang terdefinisi');
    }
}

function addPonVlanField(ponId) {
    const vlanFieldId = ponId + '_vlan_' + Date.now();
    
    console.log('🔨 Creating VLAN field for PON:', ponId);
    console.log('📊 Available VLANs count:', availableVlans.length);
    
    const vlanOptionsHtml = getVlanOptionsHtml();
    console.log('🎯 Generated VLAN options HTML:', vlanOptionsHtml);
    
    const vlanHtml = `
        <div class="row pon-vlan-row mb-2" id="${vlanFieldId}">
            <div class="col-md-4">
                <select class="form-control pon-vlan-id" name="pon_vlan_ids[${ponId}][]">
                    <option value="">Pilih VLAN Server</option>
                    ${vlanOptionsHtml}
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" class="form-control pon-vlan-desc" name="pon_vlan_descriptions[${ponId}][]" 
                       placeholder="Deskripsi untuk PON ini" readonly>
                <small class="text-muted">Otomatis diisi dari Server VLAN</small>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-danger" onclick="removePonVlanField('${vlanFieldId}')" 
                        title="Hapus VLAN">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    
    $(`#${ponId}_vlans`).append(vlanHtml);
    
    // Bind change event for the new select
    $(`#${vlanFieldId} .pon-vlan-id`).change(function() {
        updateVlanDescription(this);
    });
}

function removePonVlanField(vlanFieldId) {
    $(`#${vlanFieldId}`).remove();
}

function loadPonInterfaceOptions(ponId) {
    console.log('🔄 Loading interface options for PON:', ponId);
    
    // Get the interface dropdown for this PON
    const interfaceSelect = $(`#${ponId} .pon-interface`);
    
    if (interfaceSelect.length === 0) {
        console.warn('Interface select not found for PON:', ponId);
        return;
    }
    
    // Use existing interface data if available, otherwise load fresh
    if (window.availableInterfaces && window.availableInterfaces.length > 0) {
        populatePonInterfaceOptions(interfaceSelect, window.availableInterfaces);
    } else {
        loadInterfaceOptions().then(() => {
            if (window.availableInterfaces) {
                populatePonInterfaceOptions(interfaceSelect, window.availableInterfaces);
            }
        }).catch(error => {
            console.error('Failed to load interfaces for PON:', error);
        });
    }
}

function populatePonInterfaceOptions(selectElement, interfaces) {
    selectElement.empty().append('<option value="">Pilih Interface Server...</option>');
    
    interfaces.forEach(function(interface) {
        const option = `<option value="${interface.id}" 
                               data-server="${interface.server_name}" 
                               data-ip="${interface.ip_addresses}">
            ${interface.display_name}
        </option>`;
        selectElement.append(option);
    });
    
    console.log('✅ Interface options loaded for PON:', selectElement.closest('.pon-group').attr('id'));
}

function clearPonFields() {
    $('#ponContainer').empty();
    ponFieldCounter = 0;
}

function getPonData() {
    const pons = [];
    $('#ponContainer .pon-group').each(function() {
        const ponPort = $(this).find('.pon-port').val();
        const ponInterface = $(this).find('.pon-interface').val();
        const ponDesc = $(this).find('.pon-desc').val();
        const vlans = [];
        
        $(this).find('.pon-vlan-row').each(function() {
            const vlanId = $(this).find('.pon-vlan-id').val();
            const vlanDesc = $(this).find('.pon-vlan-desc').val();
            
            if (vlanId || vlanDesc) {
                vlans.push({
                    vlan_id: vlanId,
                    description: vlanDesc
                });
            }
        });
        
        if (ponPort || ponDesc || vlans.length > 0) {
            pons.push({
                port: ponPort,
                interface_id: ponInterface,
                description: ponDesc,
                vlans: vlans
            });
        }
    });
    return pons;
}

function setPonData(pons) {
    clearPonFields();
    
    if (pons && pons.length > 0) {
        pons.forEach(function(pon) {
            addPonField();
            const lastGroup = $('#ponContainer .pon-group').last();
            const ponId = lastGroup.attr('id');
            
            lastGroup.find('.pon-port').val(pon.port || '');
            lastGroup.find('.pon-interface').val(pon.interface_id || '');
            lastGroup.find('.pon-desc').val(pon.description || '');
            
            // Clear default VLAN field and add PON's VLANs
            $(`#${ponId}_vlans`).empty();
            if (pon.vlans && pon.vlans.length > 0) {
                pon.vlans.forEach(function(vlan) {
                    addPonVlanField(ponId);
                    const lastVlan = $(`#${ponId}_vlans .pon-vlan-row`).last();
                    const vlanSelect = lastVlan.find('.pon-vlan-id');
                    
                    // Check if VLAN options are available
                    const vlanId = vlan.vlan_id || '';
                    console.log(`🔍 Setting VLAN ID: ${vlanId} for PON ${pon.port || 'unknown'}`);
                    
                    if (availableVlans.length > 0) {
                        // VLAN options are loaded, try to set value
                        vlanSelect.val(vlanId);
                        
                        // Check if the value was actually set (option exists)
                        if (vlanSelect.val() === vlanId) {
                            console.log(`✅ VLAN ${vlanId} found in options and selected`);
                            updateVlanDescription(vlanSelect[0]);
                        } else {
                            console.warn(`⚠️ VLAN ${vlanId} not found in server options, using stored description`);
                            // VLAN not found in options, show custom description with warning
                            const customDesc = `${vlan.description || 'Custom VLAN'} (VLAN ${vlanId} - Not in server list)`;
                            lastVlan.find('.pon-vlan-desc').val(customDesc).addClass('text-warning');
                        }
                    } else {
                        console.warn(`⚠️ No VLAN options available, using stored data for VLAN ${vlanId}`);
                        // No VLAN options loaded yet, use stored description
                        const customDesc = `${vlan.description || 'Loading...'} (VLAN ${vlanId})`;
                        lastVlan.find('.pon-vlan-desc').val(customDesc).addClass('text-muted');
                        
                        // Try to refresh VLAN options and retry setting
                        refreshVlanOptions().then(() => {
                            setTimeout(() => {
                                vlanSelect.val(vlanId);
                                if (vlanSelect.val() === vlanId) {
                                    console.log(`✅ VLAN ${vlanId} set after refresh`);
                                    updateVlanDescription(vlanSelect[0]);
                                    lastVlan.find('.pon-vlan-desc').removeClass('text-muted text-warning');
                                }
                            }, 100);
                        });
                    }
                });
            } else {
                // Add one empty VLAN field
                addPonVlanField(ponId);
            }
        });
    } else {
        // Add one empty PON field if no data
        addPonField();
    }
}

// VLAN Integration Functions for OLT PON
function loadVlanOptions() {
    return new Promise((resolve, reject) => {
        if (availableVlans.length > 0) {
            console.log('✅ Using cached VLAN options:', availableVlans.length, 'VLANs');
            resolve(availableVlans);
            return;
        }
        
        console.log('🔄 Loading VLAN options from server...');
        console.log('📍 Making AJAX request to: api/vlans.php');
        
        $.ajax({
            url: 'api/vlans.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000, // 10 second timeout
            xhrFields: {
                withCredentials: true
            },
            beforeSend: function(xhr) {
                console.log('📤 AJAX request starting...');
            },
            success: function(response) {
                console.log('📥 AJAX response received:', response);
                if (response.success && response.data && Array.isArray(response.data)) {
                    availableVlans = response.data;
                    console.log('✅ Loaded', availableVlans.length, 'VLAN options from servers');
                    
                    if (availableVlans.length === 0) {
                        console.warn('⚠️ No VLANs found in servers. Make sure servers have VLAN configuration.');
                    }
                    
                    resolve(availableVlans);
                } else {
                    const message = response.message || 'Unknown error loading VLANs';
                    console.warn('⚠️ VLAN API returned error:', message);
                    availableVlans = [];
                    
                    // Still resolve with empty array to continue operation
                    resolve([]);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Unknown error';
                
                if (xhr.status === 401) {
                    errorMessage = 'Authentication issue loading VLANs';
                    console.warn('⚠️ Authentication error loading VLANs - continuing without VLAN data');
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied to VLAN data';
                    console.warn('⚠️ Access denied loading VLANs - continuing without VLAN data');
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error loading VLAN data';
                    console.error('❌ Server error loading VLANs:', xhr.responseText);
                } else if (status === 'timeout') {
                    errorMessage = 'Timeout loading VLAN data';
                    console.warn('⚠️ Timeout loading VLANs - continuing without VLAN data');
                } else {
                    errorMessage = `Network error: ${error}`;
                    console.warn('⚠️ Network error loading VLANs:', error);
                }
                
                availableVlans = [];
                
                // Always resolve with empty array to continue operation
                // Don't interrupt the main form flow for VLAN loading issues
                console.warn('⚠️ Continuing with empty VLAN list due to error:', errorMessage);
                resolve([]);
            }
        });
    });
}

function getVlanOptionsHtml() {
    console.log('🎨 getVlanOptionsHtml called, availableVlans.length:', availableVlans.length);
    
    if (availableVlans.length === 0) {
        console.warn('⚠️ No VLANs available, returning default options');
        return `
            <option value="" disabled>No VLANs available</option>
            <option value="" disabled>Make sure servers have VLAN config</option>
        `;
    }
    
    console.log('📋 Building VLAN options for', availableVlans.length, 'VLANs');
    let optionsHtml = '';
    availableVlans.forEach((vlan, index) => {
        console.log(`  VLAN ${index}: ID=${vlan.vlan_id}, Desc="${vlan.description}", Servers=${vlan.servers.length}`);
        
        const serverNames = vlan.servers.map(s => s.server_name).join(', ');
        const displayText = `VLAN ${vlan.vlan_id} - ${vlan.description || 'No Description'}`;
        const titleText = `Server: ${serverNames}${vlan.ip ? ' | IP: ' + vlan.ip : ''}`;
        
        optionsHtml += `<option value="${vlan.vlan_id}" title="${titleText}" data-description="${vlan.description || ''}" data-ip="${vlan.ip || ''}" data-servers="${serverNames}">
            ${displayText}
        </option>`;
    });
    
    console.log('✅ Generated options HTML length:', optionsHtml.length);
    return optionsHtml;
}

function updateVlanDescription(selectElement) {
    const selectedOption = $(selectElement).find('option:selected');
    const description = selectedOption.data('description') || '';
    const ip = selectedOption.data('ip') || '';
    const servers = selectedOption.data('servers') || '';
    
    // Find the description input in the same row
    const descInput = $(selectElement).closest('.pon-vlan-row').find('.pon-vlan-desc');
    
    if (selectedOption.val()) {
        let fullDesc = description;
        if (ip) {
            fullDesc += ` (IP: ${ip})`;
        }
        if (servers) {
            fullDesc += ` [${servers}]`;
        }
        descInput.val(fullDesc);
    } else {
        descInput.val('');
    }
}

function refreshVlanOptions() {
    availableVlans = []; // Clear cache
    return loadVlanOptions().then(() => {
        // Update all existing VLAN selects
        $('.pon-vlan-id').each(function() {
            const currentValue = $(this).val();
            const newOptionsHtml = '<option value="">Pilih VLAN Server</option>' + getVlanOptionsHtml();
            $(this).html(newOptionsHtml);
            if (currentValue) {
                $(this).val(currentValue);
            }
        });
        console.log('✅ VLAN options refreshed successfully');
        return availableVlans;
    }).catch(error => {
        console.error('❌ Failed to refresh VLAN options:', error);
        throw error;
    });
}

// Function to check session before making API calls
function checkSessionAndCall(callback) {
    $.ajax({
        url: 'api/auth.php?action=check',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.authenticated) {
                console.log('✅ Session verified, calling callback');
                callback();
            } else {
                console.warn('❌ Session not authenticated, redirecting to login');
                window.location.href = 'login.php';
            }
        },
        error: function(xhr, status, error) {
            console.error('Session check failed:', error);
            if (xhr.status === 401 || xhr.status === 403) {
                window.location.href = 'login.php';
            } else {
                // Proceed anyway for network errors
                callback();
            }
        }
    });
}

// Enhanced loadItems with session check
function safeLoadItems() {
    checkSessionAndCall(function() {
        if (typeof loadItems === 'function') {
            loadItems();
        }
    });
}

// Form conditional logic for item types
function toggleFormFields() {
    const itemType = $('#itemType').val();
    const itemTypeName = $('#itemType option:selected').text();
    
    console.log('🔄 toggleFormFields called - Item Type:', itemType, 'Name:', itemTypeName);
    
    // Hide all conditional fields first
    $('#networkFields').hide();
    $('#monitoringFields').hide();
    $('#serverFields').hide();
    $('#oltFields').hide();
    $('#ontFields').hide(); // Hide ONT fields
    $('#odcFields').hide();
    $('#odpFields').hide(); // Hide ODP fields
    $('#accessPointPingSection').hide(); // Hide ping section for all types initially
    
    if (itemType == '1') {
        // OLT - Show OLT-specific fields
        console.log('🏗️ Showing OLT fields, item type:', itemType);
        $('#oltFields').show();
        $('#snmpFields').show(); // Show SNMP fields for OLT
        $('#oltManagementIp').prop('required', true);
        loadInterfaceOptions(); // Load server interfaces for dropdown
        
        console.log('📡 Starting VLAN options loading for OLT...');
        // Load VLAN options before adding PON fields
        loadVlanOptions().then(() => {
            console.log('✅ VLAN options loaded successfully, adding PON field');
            // Initialize with one PON field if container is empty
            if ($('#ponContainer').children().length === 0) {
                addPonField();
            }
        }).catch(error => {
            console.warn('Failed to load VLAN options, continuing without them:', error);
            // Still add PON field even if VLAN loading fails
            if ($('#ponContainer').children().length === 0) {
                addPonField();
            }
        });
    } else if (itemType == '6') {
        // ONT - Show monitoring fields and ONT-specific fields
        console.log('🏠 ONT detected - showing ONT and monitoring fields');
        $('#monitoringFields').show();
        $('#ontFields').show(); // Show ONT-specific fields
        $('#snmpFields').show(); // Show SNMP fields for ONT
        $('#ipAddress').prop('required', true);
        loadInterfaceOptions(); // Load server interfaces for dropdown
        loadAvailableOdpForOnt(); // Load ODP options for ONT connection
    } else if (itemType == '7') {
        // Server/Router - Show server-specific fields
        $('#serverFields').show();
        $('#snmpFields').show(); // Show SNMP fields for Server
        $('#managementIp').prop('required', true);
        
        // Initialize with one VLAN field if container is empty
        if ($('#vlanContainer').children().length === 0) {
            addVlanField();
        }
    } else if (itemType == '4') {
        // ODC Pole Mounted - Show ODC-specific fields
        console.log('🏗️ ODC Pole Mounted detected - passive ODC');
        $('#networkFields').show();
        $('#odcFields').show();
        $('#ipAddress').prop('required', false);
        $('#managementIp').prop('required', false);
        $('#oltManagementIp').prop('required', false);
        
        // Set default values for pole mounted
        $('#odcType').val('pole_mounted');
        $('#odcInstallationType').val('pole');
        
        // Load OLT PON data for connection dropdown
        loadOltPonData();
        
        // Initialize with one ODP output field if container is empty
        if ($('#odcOdpContainer').children().length === 0) {
            addOdcOdpField();
        }
        
        // Calculate capacity
        calculateOdcCapacity();

    } else if (itemType == '8') {
        // Access Point - Show monitoring fields only (same as ONT)
        console.log('📡 Showing Access Point fields (same as ONT), item type:', itemType);
        $('#monitoringFields').show();
        $('#snmpFields').show(); // Show SNMP fields for Access Point
        $('#ipAddress').prop('required', true);
        loadInterfaceOptions(); // Load server interfaces for dropdown
    } else if (itemType == '3') {
        // ODP (Optical Distribution Point) - Show ODP-specific fields
        console.log('🏗️ ODP detected - showing ODP fields');
        $('#networkFields').show();
        $('#odpFields').show();
        $('#ipAddress').prop('required', false);
        $('#managementIp').prop('required', false);
        $('#oltManagementIp').prop('required', false);
        
        // Initialize with one ODC input field if container is empty
        if ($('#odpOdcContainer').children().length === 0) {
            addOdpOdcField();
        }
        
        // Initialize ONT output ports based on capacity
        initializeOdpOntPorts();
        
        // Force refresh ODC dropdowns after a short delay to ensure they're populated
        setTimeout(() => {
            console.log('🔄 Force refreshing ODC dropdowns for ODP form');
            $('.odp-odc-select').each(function() {
                const selectId = $(this).attr('id');
                if (selectId) {
                    populateOdcDropdownForOdp(selectId);
                }
            });
        }, 500);
        
    } else if (itemType == '2') {
        // Tiang Tumpu - Hide all network fields (display only)
        $('#networkFields').hide();
        $('#ipAddress').prop('required', false);
        $('#managementIp').prop('required', false);
        $('#oltManagementIp').prop('required', false);
    } else if (itemType == '11') {
        // HTB - Hide all network fields (display only, same as Tiang Tumpu)
        console.log('🏠 HTB detected - hiding network fields');
        
        // Check if networkFields element exists
        const networkFields = $('#networkFields');
        console.log('🔍 networkFields element found:', networkFields.length);
        console.log('🔍 networkFields current visibility:', networkFields.is(':visible'));
        
        // Hide network fields
        networkFields.hide();
        
        // Double check if hidden
        setTimeout(() => {
            console.log('🔍 networkFields visibility after hide:', $('#networkFields').is(':visible'));
        }, 50);
        
        $('#ipAddress').prop('required', false);
        $('#managementIp').prop('required', false);
        $('#oltManagementIp').prop('required', false);

    } else {
        // Infrastructure items - Show network fields
        $('#networkFields').show();
        $('#ipAddress').prop('required', false);
        $('#managementIp').prop('required', false);
        $('#oltManagementIp').prop('required', false);
    }
}

// ODC Management Functions


function calculateOdcCapacity() {
    let mainSplitter = $('#odcMainSplitterRatio').val();
    let odpSplitter = $('#odcOdpSplitterRatio').val();
    
    if (mainSplitter && odpSplitter) {
        let mainRatio = parseInt(mainSplitter.split(':')[1]);
        let odpRatio = parseInt(odpSplitter.split(':')[1]);
        let totalCapacity = mainRatio * odpRatio;
        
        $('#odcCapacity').val(totalCapacity);
        $('#capacityInfo').text(`Total Capacity: ${totalCapacity} customers (${mainRatio} ODP × ${odpRatio} customers)`);
        
        console.log('🧮 ODC Capacity calculated:', totalCapacity, 'customers');
    }
}

// Enhanced ODC ODP Management Functions
let odcOdpFieldCounter = 0;



function addOdcOdpField() {
    odcOdpFieldCounter++;
    const odpId = 'odc_odp_' + odcOdpFieldCounter;
    
    const odpHtml = `
        <div class="odc-odp-group border rounded p-3 mb-3" id="${odpId}" style="background: linear-gradient(135deg, #fff8e1 0%, #f3e5f5 100%);">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="${odpId}_port">Output Port</label>
                        <input type="number" class="form-control odc-output-port" name="odc_output_ports[]" 
                               min="1" max="16" placeholder="1">
                        <small class="text-muted">Port output ODC (1-16)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="${odpId}_name">Nama ODP</label>
                        <input type="text" class="form-control odc-odp-name" name="odc_odp_names[]" 
                               placeholder="ODP-Area-A-01">
                        <small class="text-muted">Nama identifikasi ODP</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="${odpId}_cable">Cable Length (m)</label>
                        <input type="number" class="form-control odc-cable-length" name="odc_cable_lengths[]" 
                               min="1" max="2000" placeholder="100">
                        <small class="text-muted">Panjang kabel dalam meter</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="${odpId}_atten">Attenuation (dB)</label>
                        <input type="number" class="form-control odc-attenuation" name="odc_attenuations[]" 
                               step="0.1" min="0" max="20" placeholder="3.5">
                        <small class="text-muted">Total attenuation</small>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-10">
                    <div class="form-group">
                        <label for="${odpId}_desc">Deskripsi/Lokasi ODP</label>
                        <input type="text" class="form-control odc-odp-desc" name="odc_odp_descriptions[]" 
                               placeholder="Contoh: ODP untuk area perumahan blok A, dekat pos satpam">
                        <small class="text-muted">Deskripsi lokasi atau detail ODP</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-danger btn-block" onclick="removeOdcOdpField('${odpId}')" 
                                title="Hapus ODP Output">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#odcOdpContainer').append(odpHtml);
    
    console.log('✅ ODC ODP field added:', odpId);
}

function removeOdcOdpField(odpId) {
    $(`#${odpId}`).remove();
}

// OLT-ODC-ODP Synchronization Functions

/**
 * Load OLT PON data for ODC PON Connection dropdown
 */
function loadOltPonData() {
    console.log('🔄 Loading OLT PON data for ODC form...');
    
    fetch('api/sync_olt_odc_data.php?action=get_olt_pon_data', {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ OLT PON data loaded:', data.data.length, 'options');
            populateOdcPonDropdown(data.data);
        } else {
            console.error('❌ Failed to load OLT PON data:', data.message);
        }
    })
    .catch(error => {
        console.error('❌ Error loading OLT PON data:', error);
    });
}

/**
 * Populate ODC PON Connection dropdown with OLT data
 */
function populateOdcPonDropdown(ponData) {
    const dropdown = $('#odcPonConnection');
    dropdown.empty();
    dropdown.append('<option value="">Pilih PON Connection dari OLT...</option>');
    
    if (ponData && ponData.length > 0) {
        ponData.forEach(pon => {
            dropdown.append(`
                <option value="${pon.connection_value}" 
                        data-olt-id="${pon.olt_id}"
                        data-olt-name="${pon.olt_name}"
                        data-olt-ip="${pon.olt_ip}"
                        data-pon-port="${pon.pon_port}"
                        data-vlans='${JSON.stringify(pon.vlans)}'>
                    ${pon.display_text}
                </option>
            `);
        });
        
        console.log('📋 ODC PON dropdown populated with', ponData.length, 'options');
    } else {
        dropdown.append('<option value="" disabled>Tidak ada OLT PON tersedia</option>');
        console.warn('⚠️ No OLT PON data available');
    }
}

/**
 * Update ODC VLAN dropdown when PON connection changes
 */
function updateOdcVlanFromPon() {
    const ponSelect = $('#odcPonConnection');
    const vlanSelect = $('#odcVlanId');
    const selectedOption = ponSelect.find('option:selected');
    
    vlanSelect.empty();
    vlanSelect.append('<option value="">Pilih VLAN ID...</option>');
    
    if (selectedOption.val()) {
        const vlansData = selectedOption.data('vlans');
        
        console.log('🔄 Updating VLAN dropdown from PON:', selectedOption.val());
        console.log('📋 Available VLANs:', vlansData);
        
        if (vlansData && vlansData.length > 0) {
            vlansData.forEach(vlan => {
                vlanSelect.append(`
                    <option value="${vlan.vlan_id}">
                        VLAN ${vlan.vlan_id} - ${vlan.description}
                    </option>
                `);
            });
            
            // Auto-select first VLAN if only one available
            if (vlansData.length === 1) {
                vlanSelect.val(vlansData[0].vlan_id);
                console.log('✅ Auto-selected VLAN:', vlansData[0].vlan_id);
            }
        } else {
            vlanSelect.append('<option value="" disabled>Tidak ada VLAN tersedia untuk PON ini</option>');
        }
    }
}





/**
 * Populate ODC dropdown for dynamic ODP fields
 */
function populateOdcDropdownForOdp(selectId) {
    const dropdown = $('#' + selectId);
    
    console.log('🔄 Loading ODC data for dropdown:', selectId);
    
    // Show loading state
    dropdown.empty();
    dropdown.append('<option value="">Loading ODC data...</option>');
    
    fetch('api/sync_olt_odc_data.php?action=get_odc_output_data', {
        method: 'GET',
        credentials: 'include',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        console.log('📥 API Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('📊 ODC API Response:', data);
        
        dropdown.empty();
        dropdown.append('<option value="">Pilih ODC dan Output Port...</option>');
        
        if (data.success && data.data && data.data.length > 0) {
            data.data.forEach(odc => {
                console.log('📋 Processing ODC:', odc);
                
                // Generate options for each output port
                const totalPorts = odc.total_ports || odc.odc_output_ports || 4;
                const availablePorts = odc.available_ports || totalPorts;
                
                for (let port = 1; port <= totalPorts; port++) {
                    const isAvailable = port <= availablePorts;
                    const optionValue = `${odc.odc_id}:${port}`;
                    const optionText = `${odc.odc_name} - Port ${port}${!isAvailable ? ' (Terpakai)' : ''}`;
                    
                    dropdown.append(`
                        <option value="${optionValue}" 
                                data-odc-id="${odc.odc_id}"
                                data-odc-name="${odc.odc_name}"
                                data-port="${port}"
                                ${!isAvailable ? 'disabled' : ''}>
                            ${optionText}
                        </option>
                    `);
                }
            });
            
            console.log('✅ ODC dropdown populated for', selectId, 'with', data.data.length, 'ODCs');
        } else {
            console.warn('⚠️ No ODC data available');
            dropdown.append('<option value="" disabled>Tidak ada ODC tersedia</option>');
            
            // Add debug info
            if (data.message) {
                console.error('❌ API Error:', data.message);
            }
        }
    })
    .catch(error => {
        console.error('❌ Error loading ODC data for dropdown:', error);
        dropdown.empty();
        dropdown.append('<option value="" disabled>Error loading ODC data</option>');
        dropdown.append('<option value="" disabled>Check console for details</option>');
    });
}



/**
 * Refresh dropdown data when needed
 */
function refreshSyncData() {
    console.log('🔄 Refreshing sync data...');
    
    // Check which form is currently visible and refresh accordingly
    if ($('#odcFields').is(':visible')) {
        loadOltPonData();
    }
    
    if ($('#ontFields').is(':visible')) {
        loadAvailableOdpForOnt();
    }
    

}

/**
 * Load available ODP for ONT connection
 */
function loadAvailableOdpForOnt() {
    console.log('🔄 Loading available ODP for ONT connection...');
    
    fetch('api/items.php?action=list&item_type_id=3&status=active', {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Available ODP data loaded:', data.data.length, 'options');
            populateOntOdpDropdown(data.data);
        } else {
            console.error('❌ Failed to load available ODP:', data.message);
        }
    })
    .catch(error => {
        console.error('❌ Error loading available ODP:', error);
    });
}

/**
 * Populate ONT ODP dropdown
 */
function populateOntOdpDropdown(odpData) {
    const dropdown = $('#ontConnectedOdp');
    dropdown.empty();
    dropdown.append('<option value="">Pilih ODP...</option>');
    
    if (odpData && odpData.length > 0) {
        // Filter to ensure only "Tiang ODP" items are shown
        const validOdpData = odpData.filter(odp => {
            // Check if item_type_name is "Tiang ODP" or item_type_id is 3
            return (odp.item_type_name === 'Tiang ODP' || odp.item_type_id == 3) && 
                   odp.status === 'active';
        });
        
        if (validOdpData.length > 0) {
            validOdpData.forEach(odp => {
                const capacity = odp.odp_capacity || 8;
                dropdown.append(`
                    <option value="${odp.id}" 
                            data-odp-name="${odp.name}"
                            data-odp-capacity="${capacity}"
                            data-odp-description="${odp.description || ''}"
                            data-odp-address="${odp.address || ''}"
                            data-odp-type="${odp.item_type_name || 'Tiang ODP'}">
                        ${odp.name} (${capacity} ports)
                    </option>
                `);
            });
            
            console.log('📋 ONT ODP dropdown populated with', validOdpData.length, 'valid Tiang ODP options');
        } else {
            dropdown.append('<option value="" disabled>Tidak ada Tiang ODP tersedia</option>');
            console.warn('⚠️ No valid Tiang ODP items found');
        }
    } else {
        dropdown.append('<option value="" disabled>Tidak ada ODP tersedia</option>');
        console.warn('⚠️ No available ODP for ONT');
    }
}

/**
 * Update ONT ODP ports when ODP selection changes
 */
function updateOntOdpPorts() {
    const odpSelect = $('#ontConnectedOdp');
    const portSelect = $('#ontConnectedPort');
    const infoDiv = $('#odpConnectionInfo');
    const infoText = $('#odpInfoText');
    
    const selectedOdpId = odpSelect.val();
    
    if (!selectedOdpId) {
        portSelect.empty().append('<option value="">Pilih ODP dulu...</option>');
        infoDiv.hide();
        return;
    }
    
    const selectedOption = odpSelect.find('option:selected');
    const odpName = selectedOption.data('odp-name');
    const odpCapacity = selectedOption.data('odp-capacity');
    const odpDescription = selectedOption.data('odp-description');
    const odpAddress = selectedOption.data('odp-address');
    
    // Update info display
    infoText.html(`
        <strong>${odpName}</strong><br>
        Kapasitas: ${odpCapacity} ports<br>
        ${odpDescription ? `Deskripsi: ${odpDescription}<br>` : ''}
        ${odpAddress ? `Alamat: ${odpAddress}` : ''}
    `);
    infoDiv.show();
    
    // Load available ports for this ODP
    console.log('🔄 Loading available ports for ODP:', selectedOdpId);
    
    fetch(`api/sync_olt_odc_data.php?action=get_available_ont_ports&odp_id=${selectedOdpId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        portSelect.empty();
        portSelect.append('<option value="">Pilih Port...</option>');
        
        if (data.success && data.available_ports) {
            for (let port = 1; port <= odpCapacity; port++) {
                const isOccupied = data.occupied_ports.includes(port);
                const optionText = isOccupied ? `Port ${port} (Terpakai)` : `Port ${port}`;
                const optionElement = `<option value="${port}" ${isOccupied ? 'disabled' : ''}>${optionText}</option>`;
                portSelect.append(optionElement);
            }
            console.log('✅ Ports loaded:', data.available_ports, 'available of', odpCapacity);
        } else {
            for (let port = 1; port <= odpCapacity; port++) {
                portSelect.append(`<option value="${port}">Port ${port}</option>`);
            }
            console.warn('⚠️ Could not load port status, showing all ports');
        }
    })
    .catch(error => {
        console.error('❌ Error loading port status:', error);
        // Fallback: show all ports
        for (let port = 1; port <= odpCapacity; port++) {
            portSelect.append(`<option value="${port}">Port ${port}</option>`);
        }
    });
}

// Enhanced ODP Management Functions
let odpOdcFieldCounter = 0;

function addOdpOdcField() {
    odpOdcFieldCounter++;
    const odcId = 'odp_odc_' + odpOdcFieldCounter;
    
    const odcHtml = `
        <div class="odp-odc-group odp-odc-field-container border rounded p-3 mb-3" id="${odcId}" style="background: linear-gradient(135deg, #e8f5e8 0%, #fff3e0 100%);">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="${odcId}_select">ODC dan Output Port</label>
                        <select class="form-control odp-odc-select" name="odp_odc_connections[]" id="${odcId}_select">
                            <option value="">Pilih ODC dan Output Port...</option>
                        </select>
                        <small class="text-muted">Pilih ODC dan output port (optional)</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="${odcId}_cable">Cable Length (m)</label>
                        <input type="number" class="form-control odp-cable-length" name="odp_cable_lengths[]" 
                               min="1" max="1000" placeholder="50">
                        <small class="text-muted">Panjang kabel dalam meter</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="${odcId}_atten">Attenuation (dB)</label>
                        <input type="number" class="form-control odp-attenuation" name="odp_attenuations[]" 
                               step="0.1" min="0" max="15" placeholder="2.5">
                        <small class="text-muted">Total attenuation</small>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-10">
                    <div class="form-group">
                        <label for="${odcId}_desc">Deskripsi Koneksi</label>
                        <input type="text" class="form-control odp-connection-desc" name="odp_connection_descriptions[]" 
                               placeholder="Contoh: Primary connection from ODC-Central-01 port 3">
                        <small class="text-muted">Deskripsi untuk koneksi ODC ini</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-danger btn-block" onclick="removeOdpOdcField('${odcId}')" 
                                title="Hapus ODC Connection">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#odpOdcContainer').append(odcHtml);
    
    // Populate ODC dropdown for this field
    populateOdcDropdownForOdp(odcId + '_select');
    
    console.log('✅ ODP ODC field added:', odcId);
}

function removeOdpOdcField(odcId) {
    if ($('#odpOdcContainer .odp-odc-group').length > 1) {
        $(`#${odcId}`).remove();
    } else {
        alert('Minimal harus ada satu koneksi ODC yang terdefinisi');
    }
}

function updateOdpCapacity() {
    const splitterRatio = $('#odpSplitterRatio').val();
    if (splitterRatio) {
        const capacity = parseInt(splitterRatio.split(':')[1]);
        $('#odpCapacity').val(capacity);
        $('#odpOutputPorts').val(capacity);
        
        // Update status info
        updateOdpStatusInfo();
        
        // Refresh ONT ports
        initializeOdpOntPorts();
        
        console.log('🧮 ODP Capacity updated:', capacity, 'customers');
    }
}

function initializeOdpOntPorts() {
    const capacity = parseInt($('#odpCapacity').val()) || 8;
    const container = $('#odpOntPortsContainer');
    
    container.empty();
    
    // Create port rows (2 columns for better layout)
    let portsHtml = '<div class="row">';
    
    for (let i = 1; i <= capacity; i++) {
        const portHtml = `
            <div class="col-md-6 mb-3">
                <div class="card port-card">
                    <div class="card-header py-2">
                        <h6 class="mb-0">
                            <i class="fas fa-plug text-primary"></i> Port ${i}
                            <span class="badge badge-secondary float-right port-status" id="port_${i}_status">Available</span>
                        </h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="form-group mb-2">
                            <label for="port_${i}_ont" class="small">ONT Serial Number</label>
                            <input type="text" class="form-control form-control-sm ont-serial" 
                                   name="ont_serials[]" id="port_${i}_ont"
                                   placeholder="Contoh: HWTC12345678">
                        </div>
                        <div class="form-group mb-2">
                            <label for="port_${i}_customer" class="small">Customer Info</label>
                            <input type="text" class="form-control form-control-sm customer-info" 
                                   name="customer_infos[]" id="port_${i}_customer"
                                   placeholder="Nama customer/alamat">
                        </div>
                        <div class="form-group mb-0">
                            <label for="port_${i}_status_select" class="small">Status Port</label>
                            <select class="form-control form-control-sm port-status-select" 
                                    name="port_statuses[]" id="port_${i}_status_select" 
                                    onchange="updatePortStatus(${i})">
                                <option value="available">Available</option>
                                <option value="connected">Connected</option>
                                <option value="reserved">Reserved</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        portsHtml += portHtml;
        
        // Create new row every 2 ports
        if (i % 2 === 0 && i < capacity) {
            portsHtml += '</div><div class="row">';
        }
    }
    
    portsHtml += '</div>';
    container.html(portsHtml);
    
    updateOdpStatusInfo();
    
    console.log('✅ ODP ONT ports initialized:', capacity, 'ports');
}

function updatePortStatus(portNumber) {
    const statusSelect = $(`#port_${portNumber}_status_select`);
    const statusBadge = $(`#port_${portNumber}_status`);
    const status = statusSelect.val();
    
    // Update badge text and color
    statusBadge.text(status.charAt(0).toUpperCase() + status.slice(1));
    statusBadge.removeClass('badge-secondary badge-success badge-warning badge-danger badge-info');
    
    switch(status) {
        case 'available':
            statusBadge.addClass('badge-secondary');
            break;
        case 'connected':
            statusBadge.addClass('badge-success');
            break;
        case 'reserved':
            statusBadge.addClass('badge-warning');
            break;
        case 'maintenance':
            statusBadge.addClass('badge-danger');
            break;
    }
    
    updateOdpStatusInfo();
}

function updateOdpStatusInfo() {
    const totalPorts = parseInt($('#odpCapacity').val()) || 8;
    const connectedPorts = $('.port-status-select option[value="connected"]:selected').length;
    const reservedPorts = $('.port-status-select option[value="reserved"]:selected').length;
    const usedPorts = connectedPorts + reservedPorts;
    const availablePorts = totalPorts - usedPorts;
    const usagePercent = Math.round((usedPorts / totalPorts) * 100);
    
    $('#odpStatusInfo').text(`Kapasitas: ${totalPorts} ports | Terpakai: ${usedPorts} ports | Tersedia: ${availablePorts} ports`);
    
    const usageBadge = $('#odpPortUsage');
    usageBadge.text(`${usagePercent}% Usage`);
    usageBadge.removeClass('badge-success badge-warning badge-danger');
    
    if (usagePercent < 50) {
        usageBadge.addClass('badge-success');
    } else if (usagePercent < 80) {
        usageBadge.addClass('badge-warning');
    } else {
        usageBadge.addClass('badge-danger');
    }
}

function refreshOntPorts() {
    initializeOdpOntPorts();
}

function loadAvailableOdcOptions() {
    console.log('🔄 Loading available ODC options...');
    
    // Load ODC items and their available ports
    $.ajax({
        url: 'api/items.php',
        method: 'GET',
        data: { action: 'get_available_odc_ports' },
        dataType: 'json',
        xhrFields: { withCredentials: true },
        success: function(response) {
            if (response.success && response.data) {
                populateOdcOptions(response.data);
            } else {
                console.error('❌ Failed to load ODC options:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error loading ODC options:', error);
        }
    });
}

function populateOdcOptions(odcData) {
    $('.odp-odc-select').each(function() {
        const select = $(this);
        select.empty().append('<option value="">Pilih ODC dan Output Port...</option>');
        
        odcData.forEach(function(odc) {
            for (let port = 1; port <= odc.available_ports; port++) {
                const option = `<option value="${odc.odc_id}:${port}">
                    ${odc.odc_name} - Port ${port} (${odc.available_ports} ports tersedia)
                </option>`;
                select.append(option);
            }
        });
    });
    
    console.log('✅ ODC options populated for ODP');
}

// ODC Event Listeners
$(document).ready(function() {
    // ODC splitter ratio change
    $('#odcMainSplitterRatio, #odcOdpSplitterRatio').on('change', function() {
        calculateOdcCapacity();
    });
    
    // ODC type change
    $('#odcType').on('change', function() {
        let odcType = $(this).val();
        if (odcType === 'pole_mounted') {
            $('#odcInstallationType').val('pole');
        } else if (odcType === 'ground_mounted') {
            $('#odcInstallationType').val('ground');
        }
    });
    
    // PON Connection change - load VLAN info
    $('#odcPonConnection').on('change', function() {
        let selectedPon = $(this).val();
        if (selectedPon) {
            loadPonVlanInfo(selectedPon);
        } else {
            $('#odcVlanId').val('');
            $('#ponVlanInfo').hide();
        }
    });
});

// Load VLAN information for selected PON port
function loadPonVlanInfo(ponPort) {
    console.log('🔄 Loading VLAN info for PON:', ponPort);
    
    $.ajax({
        url: 'api/olt_pons.php',
        method: 'GET',
        data: { pon_port: ponPort },
        dataType: 'json',
        xhrFields: { withCredentials: true },
        success: function(response) {
            if (response.success && response.vlans) {
                let vlanSelect = $('#odcVlanId');
                vlanSelect.empty().append('<option value="">Pilih VLAN ID</option>');
                
                response.vlans.forEach(function(vlan) {
                    let option = `<option value="${vlan.vlan_id}">${vlan.vlan_id} - ${vlan.description}</option>`;
                    vlanSelect.append(option);
                });
                
                $('#ponVlanInfo').show();
                console.log('✅ VLAN info loaded for PON:', ponPort);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Failed to load VLAN info:', error);
        }
    });
}

// Debug functions accessible from browser console
window.debugVlan = {
    checkVlans: function() {
        console.log('🔍 VLAN Debug Info:');
        console.log('Available VLANs:', availableVlans);
        console.log('VLAN count:', availableVlans.length);
        console.log('Generated HTML:', getVlanOptionsHtml());
    },
    
    loadVlans: function() {
        console.log('🔄 Manually loading VLANs...');
        availableVlans = []; // Clear cache
        return loadVlanOptions();
    },
    
    testApi: function() {
        console.log('🧪 Testing API directly...');
        $.ajax({
            url: 'api/vlans.php',
            method: 'GET',
            dataType: 'json',
            xhrFields: { withCredentials: true },
            success: function(response) {
                console.log('✅ API Response:', response);
            },
            error: function(xhr, status, error) {
                console.error('❌ API Error:', {xhr, status, error});
            }
        });
    },
    
    refreshDropdowns: function() {
        console.log('🔄 Refreshing all VLAN dropdowns...');
        $('.pon-vlan-id').each(function() {
            const currentValue = $(this).val();
            const newOptionsHtml = '<option value="">Pilih VLAN Server</option>' + getVlanOptionsHtml();
            $(this).html(newOptionsHtml);
            if (currentValue) {
                $(this).val(currentValue);
            }
        });
    }
};

// ODC Debug functions
window.debugOdc = {
    testOdcApi: function() {
        console.log('🧪 Testing ODC API directly...');
        fetch('api/sync_olt_odc_data.php?action=get_odc_output_data', {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('✅ ODC API Response:', data);
            if (data.debug) {
                console.log('🔍 Debug Info:', data.debug);
            }
        })
        .catch(error => {
            console.error('❌ ODC API Error:', error);
        });
    },
    
    refreshOdcDropdowns: function() {
        console.log('🔄 Manually refreshing ODC dropdowns...');
        $('.odp-odc-select').each(function() {
            const selectId = $(this).attr('id');
            if (selectId) {
                console.log('🔄 Refreshing dropdown:', selectId);
                populateOdcDropdownForOdp(selectId);
            }
        });
    },
    
    checkOdcData: function() {
        console.log('🔍 Checking ODC data in database...');
        fetch('api/items.php?action=list&item_type_id=4&status=active', {
            method: 'GET',
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            console.log('📊 ODC Items in database:', data);
        })
        .catch(error => {
            console.error('❌ Error checking ODC data:', error);
        });
    }
};

console.log('🛠️ Debug functions available:');
console.log('  VLAN: debugVlan.checkVlans(), debugVlan.loadVlans(), debugVlan.testApi(), debugVlan.refreshDropdowns()');
console.log('  ODC: debugOdc.testOdcApi(), debugOdc.refreshOdcDropdowns(), debugOdc.checkOdcData()');

// SNMP Management Functions
function toggleSNMPFields() {
    const snmpEnabled = $('#snmpEnabled').is(':checked');
    const snmpConfigBody = $('#snmpConfigBody');
    
    if (snmpEnabled) {
        snmpConfigBody.slideDown(300);
        console.log('📊 SNMP monitoring enabled');
    } else {
        snmpConfigBody.slideUp(300);
        console.log('📊 SNMP monitoring disabled');
    }
}

function testSNMPConnection() {
    const itemId = $('#itemId').val();
    const ipAddress = $('#ipAddress').val() || $('#managementIp').val() || $('#oltManagementIp').val();
    const snmpCommunity = $('#snmpCommunity').val() || 'public';
    const snmpPort = $('#snmpPort').val() || 161;
    const snmpVersion = $('#snmpVersion').val() || '2c';
    
    if (!ipAddress) {
        alert('IP Address harus diisi terlebih dahulu');
        return;
    }
    
    const testButton = $('#testSNMPConnection');
    const resultSpan = $('#snmpTestResult');
    
    // Show loading state
    testButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
    resultSpan.html('<span class="text-info"><i class="fas fa-clock"></i> Connecting...</span>');
    
    console.log('🧪 Testing SNMP connection:', { ipAddress, snmpCommunity, snmpPort, snmpVersion });
    
    // Create a temporary item data for testing
    const testData = {
        action: 'configure',
        item_id: itemId || 0, // Use 0 for new items
        ip_address: ipAddress,
        snmp_enabled: 1,
        snmp_version: snmpVersion,
        snmp_community: snmpCommunity,
        snmp_port: snmpPort
    };
    
    // If this is a new item, we need to test with a different approach
    if (!itemId) {
        // For new items, we'll call the test API with the IP directly
        $.ajax({
            url: 'test_snmp_connection.php', // We'll create this helper file
            method: 'POST',
            data: {
                ip_address: ipAddress,
                snmp_version: snmpVersion,
                snmp_community: snmpCommunity,
                snmp_port: snmpPort
            },
            dataType: 'json',
            timeout: 15000,
            success: function(response) {
                console.log('✅ SNMP test response:', response);
                
                if (response.success) {
                    resultSpan.html(`
                        <span class="text-success">
                            <i class="fas fa-check-circle"></i> 
                            Connection Success
                            ${response.system_name ? `<br><small>Device: ${response.system_name}</small>` : ''}
                        </span>
                    `);
                } else {
                    resultSpan.html(`
                        <span class="text-danger">
                            <i class="fas fa-times-circle"></i> 
                            Connection Failed
                            <br><small>${response.message}</small>
                        </span>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ SNMP test error:', error);
                resultSpan.html(`
                    <span class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Test Error
                        <br><small>Cannot connect to server</small>
                    </span>
                `);
            },
            complete: function() {
                testButton.prop('disabled', false).html('<i class="fas fa-plug"></i> Test Connection');
            }
        });
    } else {
        // For existing items, use the main SNMP API
        $.ajax({
            url: 'api/snmp.php?action=test&id=' + itemId,
            method: 'GET',
            dataType: 'json',
            timeout: 15000,
            success: function(response) {
                console.log('✅ SNMP test response:', response);
                
                if (response.success && response.data.success) {
                    resultSpan.html(`
                        <span class="text-success">
                            <i class="fas fa-check-circle"></i> 
                            Connection Success
                            ${response.data.system_name ? `<br><small>Device: ${response.data.system_name}</small>` : ''}
                        </span>
                    `);
                } else {
                    const message = response.data ? response.data.message : response.message;
                    resultSpan.html(`
                        <span class="text-danger">
                            <i class="fas fa-times-circle"></i> 
                            Connection Failed
                            <br><small>${message}</small>
                        </span>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ SNMP test error:', error);
                resultSpan.html(`
                    <span class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Test Error
                        <br><small>Cannot connect to server</small>
                    </span>
                `);
            },
            complete: function() {
                testButton.prop('disabled', false).html('<i class="fas fa-plug"></i> Test Connection');
            }
        });
    }
}

function getSNMPData() {
    const snmpData = {};
    
    if ($('#snmpEnabled').is(':checked')) {
        snmpData.snmp_enabled = 1;
        snmpData.snmp_version = $('#snmpVersion').val();
        snmpData.snmp_community = $('#snmpCommunity').val();
        snmpData.snmp_port = $('#snmpPort').val();
    } else {
        snmpData.snmp_enabled = 0;
    }
    
    return snmpData;
}

function setSNMPData(snmpData) {
    if (snmpData && snmpData.snmp_enabled) {
        $('#snmpEnabled').prop('checked', true);
        $('#snmpVersion').val(snmpData.snmp_version || '2c');
        $('#snmpCommunity').val(snmpData.snmp_community || 'public');
        $('#snmpPort').val(snmpData.snmp_port || 161);
        toggleSNMPFields();
    } else {
        $('#snmpEnabled').prop('checked', false);
        toggleSNMPFields();
    }
}

function clearSNMPFields() {
    $('#snmpEnabled').prop('checked', false);
    $('#snmpVersion').val('2c');
    $('#snmpCommunity').val('public');
    $('#snmpPort').val(161);
    $('#snmpTestResult').html('');
    toggleSNMPFields();
}

// Make functions available globally
window.toggleSNMPFields = toggleSNMPFields;
window.testSNMPConnection = testSNMPConnection;
window.getSNMPData = getSNMPData;
window.setSNMPData = setSNMPData;
window.clearSNMPFields = clearSNMPFields;

// Make toggleFormFields available globally
window.toggleFormFields = toggleFormFields;

// Manual function to force hide network fields for HTB
window.forceHideNetworkFields = function() {
    console.log('🔧 Force hiding network fields');
    $('#networkFields').hide();
    $('#networkFields').css('display', 'none');
    console.log('🔍 networkFields visibility after force hide:', $('#networkFields').is(':visible'));
};

// Manual function to check and fix HTB form
window.fixHTBForm = function() {
    const itemType = $('#itemType').val();
    const itemTypeText = $('#itemType option:selected').text();
    console.log('🔧 Fixing HTB form, current item type:', itemType, 'text:', itemTypeText);
    
    if (itemType == '11') {
        console.log('🔧 HTB detected, forcing hide of network fields');
        forceHideNetworkFields();

    } else {
        console.log('🔧 Other item type detected:', itemType, 'text:', itemTypeText);
    }
};

// Initialize form field toggling when item type changes
$(document).ready(function() {
    $('#itemType').change(function() {
        console.log('🔄 Item type changed to:', $(this).val());
        toggleFormFields();
    });
    
    // Also trigger when modal is shown with existing data
    $('#itemModal').on('shown.bs.modal', function() {
        console.log('🔄 Modal shown, triggering toggleFormFields');
        toggleFormFields();
    });
    
    // Additional trigger after a short delay
    $('#itemModal').on('shown.bs.modal', function() {
        setTimeout(() => {
            console.log('🔄 Delayed toggleFormFields call');
            toggleFormFields();
        }, 200);
    });
    
    // Reset form when modal is hidden
    $('#itemModal').on('hidden.bs.modal', function() {
        $('#itemForm')[0].reset();
        clearVlanFields();
        clearPonFields();
        clearSNMPFields();
        // Reset all conditional field requirements
        $('#ipAddress').prop('required', false);
        $('#managementIp').prop('required', false);
        $('#oltManagementIp').prop('required', false);
    });
    
    // SNMP toggle event listener
    $('#snmpEnabled').on('change', function() {
        toggleSNMPFields();
    });
});

// Load server interface options for dropdown
function loadInterfaceOptions() {
    console.log('🔌 Loading server interface options...');
    
    $.ajax({
        url: 'api/server_interfaces.php?action=get_all_interfaces',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                console.log('✅ Interface options loaded:', response.interfaces.length);
                
                // Populate OLT upstream interface dropdown
                const oltSelect = $('#oltUpstreamInterface');
                const monitoringSelect = $('#upstreamInterface');
                
                // Clear existing options except the first one
                oltSelect.find('option:not(:first)').remove();
                monitoringSelect.find('option:not(:first)').remove();
                
                // Add interface options
                response.interfaces.forEach(function(iface) {
                    const option = $('<option></option>')
                        .attr('value', iface.id)
                        .text(iface.display_name)
                        .data('server-id', iface.device_id)
                        .data('interface-name', iface.interface_name)
                        .data('ips', iface.ip_addresses);
                    
                    oltSelect.append(option.clone());
                    monitoringSelect.append(option.clone());
                });
                
                console.log('📋 Interface dropdowns populated');
            } else {
                console.warn('❌ Failed to load interface options:', response.message);
                showNotification('Failed to load server interfaces: ' + response.message, 'warning');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error loading interface options:', error);
            showNotification('Error loading server interfaces', 'error');
        }
    });
}

// Copyright popup function
function showCopyrightPopup() {
    // Create modal for PDF viewer
    const modal = $('<div class="modal fade" id="copyrightModal" tabindex="-1" role="dialog">' +
        '<div class="modal-dialog modal-lg" role="document">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<h5 class="modal-title"><i class="fas fa-copyright"></i> Surat Ciptaan - FTTH Schematic Network Management System</h5>' +
        '<button type="button" class="close" data-dismiss="modal">' +
        '<span>&times;</span>' +
        '</button>' +
        '</div>' +
        '<div class="modal-body">' +
        '<div class="text-center">' +
        '<p><strong>Surat Ciptaan FTTH Schematic Network Management System</strong></p>' +
        '<p>dari <strong>Direktorat Jenderal Kekayaan Intelektual</strong></p>' +
        '<div class="mt-3">' +
        '<a href="SuratCiptaan_SaputraBudi.pdf" target="_blank" class="btn btn-primary btn-lg">' +
        '<i class="fas fa-file-pdf"></i> Buka PDF Surat Ciptaan' +
        '</a>' +
        '</div>' +
        '<div class="mt-3">' +
        '<small class="text-muted">Klik tombol di atas untuk membuka file PDF Surat Ciptaan dalam tab baru</small>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>');
    
    // Remove existing modal if any
    $('#copyrightModal').remove();
    
    // Add modal to body and show
    $('body').append(modal);
    $('#copyrightModal').modal('show');
    
    // Remove modal from DOM when hidden
    $('#copyrightModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

// Test ping status for Access Point
function testPingStatus() {
    const ipAddress = $('#ipAddress').val();
    const testBtn = $('#testPingBtn');
    const pingStatus = $('#pingStatus');
    const pingDetails = $('#pingDetails');
    const pingResponseTime = $('#pingResponseTime');
    const pingTimestamp = $('#pingTimestamp');
    
    if (!ipAddress) {
        showNotification('Harap isi IP Address terlebih dahulu', 'warning');
        return;
    }
    
    // Disable button and show loading
    testBtn.prop('disabled', true);
    testBtn.html('<i class="fas fa-spinner fa-spin"></i> Testing...');
    pingStatus.removeClass('badge-success badge-danger badge-warning').addClass('badge-info').text('Testing...');
    
    console.log('🏓 Testing ping for IP:', ipAddress);
    
    $.ajax({
        url: 'api/ping_monitor.php?action=ping_single&host=' + encodeURIComponent(ipAddress),
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const result = response.ping_result;
                console.log('🏓 Ping result:', result);
                
                // Update status badge
                if (result.status === 'up') {
                    pingStatus.removeClass('badge-info badge-danger badge-warning')
                             .addClass('badge-success')
                             .text('Online');
                    
                    // Show response time if available
                    if (result.response_time !== null) {
                        pingResponseTime.text(`Response: ${result.response_time}ms | `);
                    } else {
                        pingResponseTime.text('Response: <1ms | ');
                    }
                } else {
                    pingStatus.removeClass('badge-info badge-success badge-warning')
                             .addClass('badge-danger')
                             .text('Offline');
                    pingResponseTime.text('No response | ');
                }
                
                // Show timestamp
                pingTimestamp.text(`Last test: ${result.timestamp}`);
                pingDetails.show();
                
                showNotification(`Ping test completed: ${result.status}`, 
                    result.status === 'up' ? 'success' : 'warning');
            } else {
                console.error('❌ Ping test failed:', response.message);
                pingStatus.removeClass('badge-info badge-success badge-warning')
                         .addClass('badge-danger')
                         .text('Error');
                showNotification('Ping test failed: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Ping test error:', error);
            pingStatus.removeClass('badge-info badge-success badge-warning')
                     .addClass('badge-danger')
                     .text('Error');
            showNotification('Ping test error: ' + error, 'error');
        },
        complete: function() {
            // Re-enable button
            testBtn.prop('disabled', false);
            testBtn.html('<i class="fas fa-satellite-dish"></i> Test Ping');
        }
    });
}
</script>

</body>
</html>