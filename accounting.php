<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

// Get accounting data
$database = new Database();
$db = $database->getConnection();

try {
    // Get summary by item type
    $summary_query = "SELECT 
                        it.name as item_type_name,
                        it.icon,
                        it.color,
                        COUNT(i.id) as total_items,
                        SUM(COALESCE(i.item_price, 0)) as total_price,
                        AVG(COALESCE(i.item_price, 0)) as avg_price,
                        MIN(COALESCE(i.item_price, 0)) as min_price,
                        MAX(COALESCE(i.item_price, 0)) as max_price
                     FROM item_types it
                     LEFT JOIN ftth_items i ON it.id = i.item_type_id
                     GROUP BY it.id, it.name, it.icon, it.color
                     ORDER BY it.id";
    
    $stmt = $db->prepare($summary_query);
    $stmt->execute();
    $summary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed items
    $items_query = "SELECT 
                        i.id,
                        i.name,
                        i.item_type,
                        i.item_price,
                        i.status,
                        i.created_at,
                        it.name as item_type_name,
                        it.icon,
                        it.color
                     FROM ftth_items i
                     LEFT JOIN item_types it ON i.item_type_id = it.id
                     ORDER BY i.created_at DESC";
    
    $stmt = $db->prepare($items_query);
    $stmt->execute();
    $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate grand total
    $grand_total = array_sum(array_column($summary_data, 'total_price'));
    $total_items = array_sum(array_column($summary_data, 'total_items'));
    
} catch (Exception $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $summary_data = [];
    $items_data = [];
    $grand_total = 0;
    $total_items = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FTTH Schematic Network Management System - Accounting</title>
    
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
    
    <style>
        .accounting-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .item-type-card {
            transition: transform 0.2s;
        }
        .item-type-card:hover {
            transform: translateY(-2px);
        }
        .price-badge {
            font-size: 0.9em;
            padding: 0.5em 0.8em;
        }
        .export-buttons {
            margin-bottom: 20px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .btn-export {
            margin-right: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">
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
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="accounting.php" class="nav-link active">Accounting</a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php" role="button">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <!-- Brand Logo -->
            <a href="index.php" class="brand-link">
                <i class="fas fa-network-wired brand-image img-circle elevation-3" style="opacity: .8"></i>
                <span class="brand-text font-weight-light">FTTH NMS</span>
            </a>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                        <li class="nav-item">
                            <a href="index.php" class="nav-link">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="accounting.php" class="nav-link active">
                                <i class="nav-icon fas fa-calculator"></i>
                                <p>Accounting</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1><i class="fas fa-calculator"></i> Accounting & Inventory</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Accounting</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <!-- Summary Cards -->
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="small-box summary-card">
                                <div class="inner">
                                    <h3><?= number_format($total_items) ?></h3>
                                    <p>Total Items</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-boxes"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>Rp <?= number_format($grand_total, 0, ',', '.') ?></h3>
                                    <p>Total Investment</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?= count($summary_data) ?></h3>
                                    <p>Item Categories</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>Rp <?= $total_items > 0 ? number_format($grand_total / $total_items, 0, ',', '.') : '0' ?></h3>
                                    <p>Average Price</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Default Pricing Management -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card accounting-card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-tags"></i> Default Pricing Management
                                    </h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-success btn-sm" onclick="savePricingSettings()">
                                            <i class="fas fa-save"></i> Save Settings
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="defaultTiangTumpuPrice">
                                                    <i class="fas fa-tower-broadcast text-info"></i>
                                                    Default Harga Tiang Tumpu (Rp)
                                                </label>
                                                <input type="number" class="form-control" id="defaultTiangTumpuPrice" 
                                                       placeholder="e.g., 750000" min="0" step="1000" 
                                                       value="<?= number_format(750000, 0, '', '') ?>">
                                                <small class="form-text text-muted">
                                                    Harga default untuk auto-generate tiang tumpu (per unit)
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="autoCalculateCost">
                                                    <i class="fas fa-calculator text-warning"></i>
                                                    Auto Calculate Total Cost
                                                </label>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="autoCalculateCost" checked>
                                                    <label class="custom-control-label" for="autoCalculateCost">
                                                        Aktifkan perhitungan otomatis total biaya saat generate tiang tumpu
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Cost Estimation Preview -->
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <h5><i class="fas fa-info-circle"></i> Preview Estimasi Biaya Auto-Generate</h5>
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <strong>Route 100m:</strong><br>
                                                        ~3 Tiang Tumpu<br>
                                                        <span class="text-success" id="costPreview100">Rp 2,250,000</span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Route 200m:</strong><br>
                                                        ~6 Tiang Tumpu<br>
                                                        <span class="text-success" id="costPreview200">Rp 4,500,000</span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Route 500m:</strong><br>
                                                        ~16 Tiang Tumpu<br>
                                                        <span class="text-success" id="costPreview500">Rp 12,000,000</span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Route 1km:</strong><br>
                                                        ~33 Tiang Tumpu<br>
                                                        <span class="text-success" id="costPreview1000">Rp 24,750,000</span>
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-lightbulb"></i>
                                                    Estimasi berdasarkan interval 30m + tiang di tikungan. Biaya actual tergantung kompleksitas route.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Buttons -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-download"></i> Export Data
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="export-buttons">
                                        <button type="button" class="btn btn-success btn-export" onclick="exportToExcel()">
                                            <i class="fas fa-file-excel"></i> Export to Excel
                                        </button>
                                        <button type="button" class="btn btn-info btn-export" onclick="exportToCSV()">
                                            <i class="fas fa-file-csv"></i> Export to CSV
                                        </button>
                                        <button type="button" class="btn btn-warning btn-export" onclick="generatePowerShell()">
                                            <i class="fab fa-microsoft"></i> PowerShell Script
                                        </button>
                                        <button type="button" class="btn btn-primary btn-export" onclick="printReport()">
                                            <i class="fas fa-print"></i> Print Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary by Item Type -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card accounting-card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-chart-pie"></i> Summary by Item Type
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped" id="summaryTable">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th>Item Type</th>
                                                    <th>Total Items</th>
                                                    <th>Total Price</th>
                                                    <th>Average Price</th>
                                                    <th>Min Price</th>
                                                    <th>Max Price</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($summary_data as $summary): ?>
                                                <tr>
                                                    <td>
                                                        <i class="<?= $summary['icon'] ?>" style="color: <?= $summary['color'] ?>"></i>
                                                        <?= htmlspecialchars($summary['item_type_name']) ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-primary"><?= $summary['total_items'] ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-success price-badge">
                                                            Rp <?= number_format($summary['total_price'], 0, ',', '.') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        Rp <?= number_format($summary['avg_price'], 0, ',', '.') ?>
                                                    </td>
                                                    <td>
                                                        Rp <?= number_format($summary['min_price'], 0, ',', '.') ?>
                                                    </td>
                                                    <td>
                                                        Rp <?= number_format($summary['max_price'], 0, ',', '.') ?>
                                                    </td>
                                                    <td>
                                                        <?= $grand_total > 0 ? number_format(($summary['total_price'] / $grand_total) * 100, 1) : '0' ?>%
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Items Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card accounting-card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-list"></i> Detailed Items
                                    </h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="addCustomItem()">
                                            <i class="fas fa-plus"></i> Add Custom Item
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped" id="itemsTable">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Item Type</th>
                                                    <th>Name</th>
                                                    <th>Type/Model</th>
                                                    <th>Price</th>
                                                    <th>Status</th>
                                                    <th>Created Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items_data as $item): ?>
                                                <tr>
                                                    <td><?= $item['id'] ?></td>
                                                    <td>
                                                        <i class="<?= $item['icon'] ?>" style="color: <?= $item['color'] ?>"></i>
                                                        <?= htmlspecialchars($item['item_type_name']) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                                    <td><?= htmlspecialchars($item['item_type'] ?: '-') ?></td>
                                                    <td>
                                                        <span class="badge badge-success price-badge">
                                                            Rp <?= number_format($item['item_price'] ?: 0, 0, ',', '.') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?= $item['status'] === 'active' ? 'success' : ($item['status'] === 'inactive' ? 'secondary' : 'warning') ?>">
                                                            <?= ucfirst($item['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-info" onclick="viewItem(<?= $item['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" onclick="editItem(<?= $item['id'] ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <footer class="main-footer">
            <div class="float-right d-none d-sm-inline">
                FTTH Schematic Network Management System
            </div>
            <strong>Copyright &copy; 2025 <a href="#" onclick="showCopyrightPopup()">FTTH Schematic Network Management System</a> by Saputra Budi. Semua hak dilindungi undang-undang.</strong>
        </footer>
    </div>

    <!-- Custom Item Modal -->
    <div class="modal fade" id="customItemModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus"></i> Add Custom Item
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="customItemForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="customItemType">Item Type</label>
                            <input type="text" class="form-control" id="customItemType" name="item_type" placeholder="e.g., Kabel, Tools, Equipment" required>
                        </div>
                        <div class="form-group">
                            <label for="customItemName">Item Name</label>
                            <input type="text" class="form-control" id="customItemName" name="name" placeholder="e.g., Kabel Fiber Optic 12 Core" required>
                        </div>
                        <div class="form-group">
                            <label for="customItemModel">Type/Model</label>
                            <input type="text" class="form-control" id="customItemModel" name="item_type_name" placeholder="e.g., FO-12C-100m">
                        </div>
                        <div class="form-group">
                            <label for="customItemPrice">Price (Rp)</label>
                            <input type="number" class="form-control" id="customItemPrice" name="item_price" placeholder="e.g., 500000" min="0" step="1000" required>
                        </div>
                        <div class="form-group">
                            <label for="customItemStatus">Status</label>
                            <select class="form-control" id="customItemStatus" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#summaryTable').DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "pageLength": 10,
                "order": [[2, "desc"]], // Sort by total price
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"
                }
            });

            $('#itemsTable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 25,
                "order": [[0, "desc"]], // Sort by ID
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"
                }
            });

            // Custom item form submission
            $('#customItemForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('item_type_id', '10'); // Custom type (akan diupdate sesuai ID yang ada)
                formData.append('latitude', '0');
                formData.append('longitude', '0');
                
                $.ajax({
                    url: 'api/items.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Custom item added successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Network error occurred');
                    }
                });
            });
        });

        // Export functions
        function exportToExcel() {
            const table = $('#itemsTable').DataTable();
            table.button('.buttons-excel').trigger();
        }

        function exportToCSV() {
            const table = $('#itemsTable').DataTable();
            const data = table.data().toArray();
            
            let csv = 'ID,Item Type,Name,Type/Model,Price,Status,Created Date\n';
            
            data.forEach(function(row) {
                const price = row[4].replace(/[^\d]/g, '');
                csv += `${row[0]},${row[1].replace(/<[^>]*>/g, '')},${row[2]},${row[3]},${price},${row[5].replace(/<[^>]*>/g, '')},${row[6]}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ftth_accounting_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
        }

        function generatePowerShell() {
            const table = $('#itemsTable').DataTable();
            const data = table.data().toArray();
            
            let psScript = '# FTTH Accounting Data Export - PowerShell Script\n';
            psScript += '# Generated on: ' + new Date().toLocaleString() + '\n\n';
            psScript += '$data = @(\n';
            
            data.forEach(function(row, index) {
                const price = row[4].replace(/[^\d]/g, '');
                psScript += `    [PSCustomObject]@{\n`;
                psScript += `        ID = ${row[0]}\n`;
                psScript += `        ItemType = "${row[1].replace(/<[^>]*>/g, '').trim()}"\n`;
                psScript += `        Name = "${row[2]}"\n`;
                psScript += `        TypeModel = "${row[3]}"\n`;
                psScript += `        Price = ${price}\n`;
                psScript += `        Status = "${row[5].replace(/<[^>]*>/g, '').trim()}"\n`;
                psScript += `        CreatedDate = "${row[6]}"\n`;
                psScript += `    }`;
                if (index < data.length - 1) psScript += ',';
                psScript += '\n';
            });
            
            psScript += ')\n\n';
            psScript += '# Export to CSV\n';
            psScript += '$data | Export-Csv -Path "ftth_accounting_export.csv" -NoTypeInformation -Encoding UTF8\n\n';
            psScript += '# Display summary\n';
            psScript += '$totalPrice = ($data | Measure-Object -Property Price -Sum).Sum\n';
            psScript += '$totalItems = $data.Count\n';
            psScript += 'Write-Host "Total Items: $totalItems"\n';
            psScript += 'Write-Host "Total Price: $totalPrice"\n';
            psScript += 'Write-Host "Average Price: $($totalPrice / $totalItems)"\n';
            
            const blob = new Blob([psScript], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ftth_accounting_powershell_' + new Date().toISOString().split('T')[0] + '.ps1';
            a.click();
        }

        function printReport() {
            window.print();
        }

        function addCustomItem() {
            $('#customItemModal').modal('show');
        }

        function viewItem(id) {
            window.open('index.php?view_item=' + id, '_blank');
        }

        function editItem(id) {
            window.open('index.php?edit_item=' + id, '_blank');
        }

        // Default Pricing Management Functions
        function savePricingSettings() {
            const tiangTumpuPrice = $('#defaultTiangTumpuPrice').val();
            const autoCalculate = $('#autoCalculateCost').is(':checked');
            
            if (!tiangTumpuPrice || tiangTumpuPrice <= 0) {
                alert('Harga tiang tumpu harus diisi dan lebih dari 0');
                return;
            }
            
            const data = {
                action: 'save_default_pricing',
                tiang_tumpu_price: tiangTumpuPrice,
                auto_calculate_cost: autoCalculate ? 1 : 0
            };
            
            $.ajax({
                url: 'api/pricing.php',
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        alert('Pengaturan harga berhasil disimpan!');
                        updateCostPreviews();
                        
                        // Store in localStorage for auto-generate use
                        localStorage.setItem('defaultTiangTumpuPrice', tiangTumpuPrice);
                        localStorage.setItem('autoCalculateCost', autoCalculate);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Network error occurred');
                }
            });
        }
        
        function loadPricingSettings() {
            $.ajax({
                url: 'api/pricing.php',
                method: 'GET',
                data: { action: 'get_default_pricing' },
                success: function(response) {
                    if (response.success && response.data) {
                        $('#defaultTiangTumpuPrice').val(response.data.tiang_tumpu_price || 750000);
                        $('#autoCalculateCost').prop('checked', response.data.auto_calculate_cost == 1);
                        updateCostPreviews();
                        
                        // Store in localStorage
                        localStorage.setItem('defaultTiangTumpuPrice', response.data.tiang_tumpu_price || 750000);
                        localStorage.setItem('autoCalculateCost', response.data.auto_calculate_cost == 1);
                    }
                },
                error: function() {
                    console.log('Could not load pricing settings, using defaults');
                    updateCostPreviews();
                }
            });
        }
        
        function updateCostPreviews() {
            const pricePerUnit = parseInt($('#defaultTiangTumpuPrice').val()) || 750000;
            const interval = 30; // meters
            
            // Calculate estimated tiang tumpu count for different distances
            const distances = [100, 200, 500, 1000];
            
            distances.forEach(function(distance) {
                const estimatedTiangCount = Math.ceil(distance / interval);
                const totalCost = estimatedTiangCount * pricePerUnit;
                
                $('#costPreview' + distance).text('Rp ' + new Intl.NumberFormat('id-ID').format(totalCost));
            });
        }
        
        // Real-time price update
        $(document).on('input', '#defaultTiangTumpuPrice', function() {
            updateCostPreviews();
        });
        
        // Load pricing settings on page load
        $(document).ready(function() {
            loadPricingSettings();
        });
        
        // Export function with pricing info
        function exportPricingReport() {
            const tiangTumpuPrice = $('#defaultTiangTumpuPrice').val();
            const autoCalculate = $('#autoCalculateCost').is(':checked');
            
            let report = 'FTTH Network - Pricing Configuration Report\n';
            report += '===========================================\n\n';
            report += 'Generated: ' + new Date().toLocaleString() + '\n\n';
            report += 'Default Pricing Settings:\n';
            report += '- Tiang Tumpu Price: Rp ' + new Intl.NumberFormat('id-ID').format(tiangTumpuPrice) + '\n';
            report += '- Auto Calculate Cost: ' + (autoCalculate ? 'Enabled' : 'Disabled') + '\n\n';
            report += 'Cost Estimation Examples:\n';
            report += '- Route 100m: ~3 units = Rp ' + new Intl.NumberFormat('id-ID').format(3 * tiangTumpuPrice) + '\n';
            report += '- Route 200m: ~6 units = Rp ' + new Intl.NumberFormat('id-ID').format(6 * tiangTumpuPrice) + '\n';
            report += '- Route 500m: ~16 units = Rp ' + new Intl.NumberFormat('id-ID').format(16 * tiangTumpuPrice) + '\n';
            report += '- Route 1km: ~33 units = Rp ' + new Intl.NumberFormat('id-ID').format(33 * tiangTumpuPrice) + '\n\n';
            report += 'Note: Estimasi berdasarkan interval 30m + tiang di tikungan.\n';
            report += 'Biaya actual tergantung kompleksitas route dan kondisi lapangan.\n';
            
            const blob = new Blob([report], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ftth_pricing_config_' + new Date().toISOString().split('T')[0] + '.txt';
            a.click();
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
    </script>
</body>
</html>
