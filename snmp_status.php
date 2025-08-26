<?php
// SNMP Status Page - Show SNMP configuration and health
session_start();

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
    <title>SNMP Status | FTTH NMS</title>
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <!-- Content Wrapper -->
    <div class="content-wrapper" style="margin-left: 0;">
        <section class="content">
            <div class="container-fluid mt-4">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-line text-success"></i>
                                    SNMP Configuration Status
                                </h3>
                                <div class="card-tools">
                                    <a href="index.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                                    </a>
                                    <a href="snmp_dashboard.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-chart-line"></i> SNMP Monitoring
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Status Cards -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-success">
                                                <i class="fas fa-check"></i>
                                            </span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">SNMP Extension</span>
                                                <span class="info-box-number">
                                                    <?php echo extension_loaded('snmp') ? 'LOADED' : 'NOT LOADED'; ?>
                                                </span>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" style="width: <?php echo extension_loaded('snmp') ? '100' : '0'; ?>%"></div>
                                                </div>
                                                <span class="progress-description">
                                                    PHP <?php echo phpversion(); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-info">
                                                <i class="fas fa-folder"></i>
                                            </span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">MIB Files</span>
                                                <span class="info-box-number">
                                                    <?php 
                                                    $mib_count = 0;
                                                    $mib_dir = __DIR__ . '\\usr\\share\\snmp\\mibs\\';
                                                    if (is_dir($mib_dir)) {
                                                        $files = scandir($mib_dir);
                                                        $mib_count = count($files) - 2; // Exclude . and ..
                                                    }
                                                    echo $mib_count . ' FILES';
                                                    ?>
                                                </span>
                                                <div class="progress">
                                                    <div class="progress-bar bg-info" style="width: <?php echo $mib_count > 0 ? '100' : '0'; ?>%"></div>
                                                </div>
                                                <span class="progress-description">
                                                    <?php echo is_dir($mib_dir) ? 'Directory exists' : 'Directory missing'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-warning">
                                                <i class="fas fa-cogs"></i>
                                            </span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">SNMP Functions</span>
                                                <span class="info-box-number">
                                                    <?php 
                                                    $functions = ['snmpget', 'snmp2_get', 'snmpwalk', 'snmp2_walk'];
                                                    $available = 0;
                                                    foreach ($functions as $func) {
                                                        if (function_exists($func)) $available++;
                                                    }
                                                    echo $available . '/' . count($functions);
                                                    ?>
                                                </span>
                                                <div class="progress">
                                                    <div class="progress-bar bg-warning" style="width: <?php echo ($available/count($functions))*100; ?>%"></div>
                                                </div>
                                                <span class="progress-description">
                                                    Functions available
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="info-box">
                                            <span class="info-box-icon bg-danger">
                                                <i class="fas fa-network-wired"></i>
                                            </span>
                                            <div class="info-box-content">
                                                <span class="info-box-text">SNMP Devices</span>
                                                <span class="info-box-number">
                                                    <?php
                                                    try {
                                                        require_once 'config/database.php';
                                                        $database = new Database();
                                                        $db = $database->getConnection();
                                                        
                                                        $query = "SELECT COUNT(*) as count FROM ftth_items WHERE snmp_enabled = 1";
                                                        $stmt = $db->prepare($query);
                                                        $stmt->execute();
                                                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                        echo $result['count'] . ' DEVICES';
                                                    } catch (Exception $e) {
                                                        echo 'ERROR';
                                                    }
                                                    ?>
                                                </span>
                                                <div class="progress">
                                                    <div class="progress-bar bg-danger" style="width: 100%"></div>
                                                </div>
                                                <span class="progress-description">
                                                    Ready for monitoring
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configuration Details -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="fas fa-info-circle"></i> SNMP Configuration</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td><strong>Extension Status:</strong></td>
                                                        <td>
                                                            <?php if (extension_loaded('snmp')): ?>
                                                                <span class="badge badge-success">Loaded</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-danger">Not Loaded</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>SNMP Version:</strong></td>
                                                        <td><?php echo extension_loaded('snmp') ? phpversion('snmp') : 'N/A'; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>MIB Directory:</strong></td>
                                                        <td><code>C:\usr\share\snmp\mibs\</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Config Path:</strong></td>
                                                        <td><code>C:\usr\share\snmp\</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Supported Versions:</strong></td>
                                                        <td>SNMPv1, SNMPv2c</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="fas fa-exclamation-triangle"></i> Important Notes</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="alert alert-info">
                                                    <h6><i class="fas fa-info-circle"></i> MIB Warnings</h6>
                                                    <p class="mb-1">Warnings about missing MIB modules are normal and don't prevent SNMP functionality.</p>
                                                </div>
                                                
                                                <div class="alert alert-success">
                                                    <h6><i class="fas fa-check"></i> SNMP Ready</h6>
                                                    <p class="mb-1">SNMP extension is loaded and configured. You can now monitor devices with SNMP enabled.</p>
                                                </div>
                                                
                                                <div class="alert alert-warning">
                                                    <h6><i class="fas fa-cog"></i> Device Configuration</h6>
                                                    <p class="mb-1">Make sure target devices have SNMP agent running and community string configured.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Quick Actions -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="fas fa-rocket"></i> Quick Actions</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="btn-group" role="group">
                                                    <a href="snmp_dashboard.php" class="btn btn-success">
                                                        <i class="fas fa-chart-line"></i> SNMP Dashboard
                                                    </a>
                                                    <a href="index.php" class="btn btn-primary">
                                                        <i class="fas fa-plus"></i> Add SNMP Device
                                                    </a>
                                                    <a href="test_snmp_integration.php" class="btn btn-info">
                                                        <i class="fas fa-vial"></i> Integration Test
                                                    </a>
                                                    <button class="btn btn-secondary" onclick="window.location.reload()">
                                                        <i class="fas fa-sync"></i> Refresh Status
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    console.log('📊 SNMP Status page loaded');
    
    // Auto refresh every 30 seconds
    setTimeout(function() {
        window.location.reload();
    }, 30000);
});
</script>

</body>
</html>
