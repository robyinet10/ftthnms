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
    <title>SNMP Network Monitoring | FTTH NMS</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
    
    <style>
        .metric-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-online { border-left-color: #28a745; }
        .status-warning { border-left-color: #ffc107; }
        .status-offline { border-left-color: #dc3545; }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .device-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-indicator-online { background-color: #28a745; }
        .status-indicator-warning { background-color: #ffc107; }
        .status-indicator-offline { background-color: #dc3545; }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.1);
        }
        
        .refresh-icon {
            animation: none;
        }
        
        .refresh-icon.spinning {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .progress-thin {
            height: 8px;
        }
        
        .card-metric {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .card-metric .card-body {
            padding: 1.5rem;
        }
        
        .last-update {
            font-size: 0.75rem;
            color: #6c757d;
            font-style: italic;
        }
        
        /* Enhanced border styles for metric cards */
        .border-left-primary { border-left: 4px solid #007bff !important; }
        .border-left-success { border-left: 4px solid #28a745 !important; }
        .border-left-warning { border-left: 4px solid #ffc107 !important; }
        .border-left-danger { border-left: 4px solid #dc3545 !important; }
        .border-left-info { border-left: 4px solid #17a2b8 !important; }
        .border-left-secondary { border-left: 4px solid #6c757d !important; }
        
        /* Gradient backgrounds for info cards */
        .bg-gradient-primary {
            background: linear-gradient(45deg, #007bff, #0056b3) !important;
        }
        
        .bg-gradient-info {
            background: linear-gradient(45deg, #17a2b8, #138496) !important;
        }
        
        /* Enhanced metric value styling */
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        /* No data styling */
        .no-data-message {
            padding: 2rem;
            text-align: center;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 0.375rem;
            border: 2px dashed #dee2e6;
        }
        
        /* Loading animation for metrics */
        .metrics-loading {
            position: relative;
            overflow: hidden;
        }
        
        .metrics-loading::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.2), transparent);
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
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
                <a href="snmp_dashboard.php" class="nav-link font-weight-bold text-primary">SNMP Monitoring</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Auto Refresh Toggle -->
            <li class="nav-item">
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="autoRefreshToggle" checked>
                    <label class="custom-control-label" for="autoRefreshToggle">Auto Refresh</label>
                </div>
            </li>
            
            <!-- Refresh Button -->
            <li class="nav-item">
                <button class="btn btn-outline-primary btn-sm ml-2" onclick="refreshAllData()" id="refreshBtn">
                    <i class="fas fa-sync-alt refresh-icon" id="refreshIcon"></i> Refresh
                </button>
            </li>
            
            <!-- User Dropdown Menu -->
            <li class="nav-item dropdown ml-3">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                    <i class="fas fa-user-circle"></i>
                    <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="badge badge-<?php echo $is_admin ? 'danger' : 'info'; ?> ml-1">
                        <?php echo $is_admin ? 'Admin' : 'Teknisi'; ?>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="index.php">
                        <i class="fas fa-map mr-2"></i>Peta FTTH
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="#" onclick="logout()">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="index.php" class="brand-link">
            <i class="fas fa-chart-line brand-image img-circle elevation-3" style="opacity: .8; margin-left: 10px; color: white;"></i>
            <span class="brand-text font-weight-light">SNMP Monitor</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="nav-icon fas fa-map text-info"></i>
                            <p>Peta FTTH</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="snmp_dashboard.php" class="nav-link active">
                            <i class="nav-icon fas fa-chart-line text-success"></i>
                            <p>SNMP Monitoring</p>
                        </a>
                    </li>
                    
                    <li class="nav-header">DEVICE MONITORING</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="filterDevices('server')">
                            <i class="nav-icon fas fa-server" style="color: #8E44AD;"></i>
                            <p>Servers</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="filterDevices('olt')">
                            <i class="nav-icon fas fa-server" style="color: #FF6B6B;"></i>
                            <p>OLT</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="filterDevices('access_point')">
                            <i class="nav-icon fas fa-wifi" style="color: #3498DB;"></i>
                            <p>Access Points</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="filterDevices('ont')">
                            <i class="nav-icon fas fa-home" style="color: #FFA500;"></i>
                            <p>ONT</p>
                        </a>
                    </li>
                    
                    <li class="nav-header">ACTIONS</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="collectAllSNMP()">
                            <i class="nav-icon fas fa-download text-success"></i>
                            <p>Collect All Metrics</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" onclick="showSNMPConfiguration()">
                            <i class="nav-icon fas fa-cog text-warning"></i>
                            <p>SNMP Configuration</p>
                        </a>
                    </li>
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
                        <h1 class="m-0">
                            <i class="fas fa-chart-line text-success"></i>
                            SNMP Network Monitoring Dashboard
                            <span id="deviceFocusIndicator" class="badge badge-info ml-2" style="display: none;">
                                <i class="fas fa-crosshairs"></i> Focused Device
                            </span>
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active">SNMP Monitoring</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 id="deviceOnlineCount">0</h3>
                                <p>Online Devices</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3 id="deviceWarningCount">0</h3>
                                <p>Warning Devices</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3 id="deviceOfflineCount">0</h3>
                                <p>Offline Devices</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3 id="snmpEnabledCount">0</h3>
                                <p>SNMP Enabled</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Device List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-list mr-1"></i>
                                    SNMP Device Monitoring
                                </h3>
                                <div class="card-tools">
                                    <div class="input-group input-group-sm" style="width: 250px;">
                                        <input type="text" name="table_search" class="form-control float-right" placeholder="Search devices..." id="deviceSearch">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-default">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Device Name</th>
                                                <th>Type</th>
                                                <th>IP Address</th>
                                                <th>System Info</th>
                                                <th>Performance</th>
                                                <th>Last Update</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="deviceTableBody">
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="fas fa-spinner fa-spin"></i> Loading SNMP devices...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Last Update Info -->
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Last Updated:</strong> <span id="lastUpdateTime">Never</span> | 
                            <strong>Next Update:</strong> <span id="nextUpdateTime">-</span> |
                            <strong>Auto Refresh:</strong> <span id="autoRefreshStatus">Enabled</span>
                        </div>
                    </div>
                </div>
                
                <!-- SNMP Status Info -->
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-success" id="snmpStatusInfo" style="display: none;">
                            <i class="fas fa-check-circle"></i>
                            <strong>SNMP Ready:</strong> Sample devices with SNMP configuration are available for testing.
                            <button class="btn btn-sm btn-outline-primary ml-2" onclick="collectAllSNMP()">
                                <i class="fas fa-download"></i> Collect Sample Metrics
                            </button>
                        </div>
                        
                        <div class="alert alert-warning" id="noSNMPDevicesInfo" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No SNMP Devices:</strong> 
                            No devices with SNMP enabled found. 
                            <a href="index.php" class="btn btn-sm btn-primary ml-2">
                                <i class="fas fa-plus"></i> Add SNMP Device
                            </a>
                        </div>
                        
                        <div class="alert alert-warning" id="snmpExtensionWarning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>SNMP Extension Missing:</strong> 
                            PHP SNMP extension is not loaded. Please install php-snmp to enable SNMP monitoring.
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>Copyright &copy; 2025 <a href="#">FTTH Network Monitoring System</a> with SNMP Integration.</strong>
        Real-time network monitoring and performance analytics.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> 2.1.0 + SNMP
        </div>
    </footer>
</div>

<!-- Device Details Modal -->
<div class="modal fade" id="deviceDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="deviceModalTitle">
                    <i class="fas fa-chart-line"></i> Device Metrics
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="deviceModalBody">
                <!-- Device metrics will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="collectDeviceMetrics()">
                    <i class="fas fa-sync-alt"></i> Refresh Metrics
                </button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
// Global variables
let autoRefreshInterval;
let currentDeviceId = null;
let deviceData = [];

// Initialize dashboard
$(document).ready(function() {
    console.log('🚀 SNMP Dashboard initializing...');
    
    // Check if specific device ID is provided in URL
    const urlParams = new URLSearchParams(window.location.search);
    const deviceId = urlParams.get('device');
    
    // Load initial data
    loadSNMPDevices();
    
    // If device ID is provided, auto-open that device's details
    if (deviceId) {
        setTimeout(() => {
            console.log('🎯 Auto-opening device details for ID:', deviceId);
            showDeviceDetails(deviceId);
        }, 1500); // Wait for devices to load first
    }
    
    // Setup auto refresh
    setupAutoRefresh();
    
    // Setup search functionality
    setupDeviceSearch();
    
    // Setup auto refresh toggle
    $('#autoRefreshToggle').on('change', function() {
        if ($(this).is(':checked')) {
            setupAutoRefresh();
            $('#autoRefreshStatus').text('Enabled');
        } else {
            clearInterval(autoRefreshInterval);
            $('#autoRefreshStatus').text('Disabled');
        }
    });
    
    console.log('✅ SNMP Dashboard initialized');
});

// Load SNMP devices
function loadSNMPDevices() {
    console.log('📊 Loading SNMP devices...');
    
    $.ajax({
        url: 'api/snmp.php?action=status',
        method: 'GET',
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.success) {
                deviceData = response.data;
                displayDevices(deviceData);
                updateSummaryCards(deviceData);
                updateLastUpdateTime();
                updateStatusInfo(deviceData);
                console.log('✅ Loaded', deviceData.length, 'SNMP devices');
            } else {
                console.error('❌ Failed to load SNMP devices:', response.message);
                
                // Check if it's an SNMP extension issue
                if (response.message && response.message.includes('SNMP extension')) {
                    $('#snmpExtensionWarning').show();
                    $('#noSNMPDevicesInfo').hide();
                    $('#snmpStatusInfo').hide();
                } else {
                    showError('Failed to load SNMP devices: ' + response.message);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ SNMP devices load error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            console.error('Status Code:', xhr.status);
            
            let errorMessage = 'Cannot connect to SNMP monitoring service';
            if (xhr.status === 401) {
                errorMessage = 'Authentication required - redirecting to login';
                window.location.href = 'login.php';
                return;
            } else if (xhr.status === 403) {
                errorMessage = 'Access denied - insufficient permissions';
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    // Ignore parsing errors, use default message
                }
            }
            
            showError(errorMessage);
            
            // Show fallback data or offline message
            $('#snmpStatusInfo').hide();
            $('#noSNMPDevicesInfo').hide();
            $('#snmpExtensionWarning').show();
            $('#snmpExtensionWarning').html(`
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Connection Error:</strong> ${errorMessage}
                <button class="btn btn-sm btn-outline-primary ml-2" onclick="loadSNMPDevices()">
                    <i class="fas fa-sync"></i> Retry
                </button>
            `);
        }
    });
}

// Display devices in table
function displayDevices(devices) {
    const tbody = $('#deviceTableBody');
    tbody.empty();
    
    // Check if specific device is requested from URL
    const urlParams = new URLSearchParams(window.location.search);
    const focusDeviceId = urlParams.get('device');
    
    if (devices.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="8" class="text-center py-4">
                    <i class="fas fa-exclamation-circle text-warning"></i>
                    No SNMP-enabled devices found. Enable SNMP monitoring on devices first.
                </td>
            </tr>
        `);
        return;
    }
    
    devices.forEach(device => {
        const statusClass = getStatusClass(device);
        const statusIcon = getStatusIcon(device);
        const performanceInfo = getPerformanceInfo(device);
        const lastUpdate = device.last_snmp_time ? 
            formatDateTime(device.last_snmp_time) : 
            '<span class="text-muted">Never</span>';
        
        // Highlight focused device if specified in URL
        const isFocusedDevice = focusDeviceId && device.id == focusDeviceId;
        const rowClass = isFocusedDevice ? 'table-warning' : '';
        
        if (isFocusedDevice) {
            $('#deviceFocusIndicator').show().html(`<i class="fas fa-crosshairs"></i> Focused: ${device.name}`);
        }
        
        const row = `
            <tr class="${rowClass}">
                <td>
                    <span class="device-status ${statusClass}"></span>
                    ${statusIcon}
                    ${isFocusedDevice ? '<i class="fas fa-crosshairs text-warning ml-1" title="Focused Device"></i>' : ''}
                </td>
                <td>
                    <strong>${device.name}</strong>
                    <br>
                    <small class="text-muted">${device.device_name || 'Unknown'}</small>
                </td>
                <td>
                    <span class="badge badge-secondary">${device.item_type}</span>
                </td>
                <td>
                    <code>${device.ip_address}</code>
                    <br>
                    <small class="text-muted">Port: ${device.snmp_port || 161}</small>
                </td>
                <td>
                    ${getSystemInfo(device)}
                </td>
                <td>
                    ${performanceInfo}
                </td>
                <td>
                    <small>${lastUpdate}</small>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="showDeviceDetails(${device.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="collectDeviceMetrics(${device.id})" title="Refresh Metrics">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

// Helper functions
function getStatusClass(device) {
    if (device.cpu_usage_percent !== null || device.memory_usage_percent !== null) {
        return 'status-indicator-online';
    }
    return 'status-indicator-offline';
}

function getStatusIcon(device) {
    if (device.cpu_usage_percent !== null || device.memory_usage_percent !== null) {
        return '<span class="text-success">Online</span>';
    }
    return '<span class="text-danger">No Data</span>';
}

function getPerformanceInfo(device) {
    let info = '';
    let hasData = false;
    
    // CPU Usage with improved formatting
    if (device.cpu_usage_percent !== null && device.cpu_usage_percent !== undefined && device.cpu_usage_percent !== '') {
        hasData = true;
        const cpuValue = parseFloat(device.cpu_usage_percent);
        const cpuClass = cpuValue > 80 ? 'danger' : cpuValue > 60 ? 'warning' : 'success';
        const cpuIcon = cpuValue > 80 ? 'fa-exclamation-triangle' : cpuValue > 60 ? 'fa-exclamation-circle' : 'fa-check-circle';
        info += `<div class="mb-1">
                    <i class="fas fa-microchip text-${cpuClass}"></i>
                    <strong>CPU:</strong> 
                    <span class="text-${cpuClass} font-weight-bold">${cpuValue.toFixed(1)}%</span>
                 </div>`;
    }
    
    // Memory Usage with improved formatting and total memory info
    if (device.memory_usage_percent !== null && device.memory_usage_percent !== undefined && device.memory_usage_percent !== '') {
        hasData = true;
        const memValue = parseFloat(device.memory_usage_percent);
        const memClass = memValue > 80 ? 'danger' : memValue > 60 ? 'warning' : 'success';
        const memIcon = memValue > 80 ? 'fa-exclamation-triangle' : memValue > 60 ? 'fa-exclamation-circle' : 'fa-check-circle';
        info += `<div class="mb-1">
                    <i class="fas fa-memory text-${memClass}"></i>
                    <strong>Memory:</strong> 
                    <span class="text-${memClass} font-weight-bold">${memValue.toFixed(1)}%</span>`;
        
        // Add total memory info if available
        if (device.memory_total_mb && device.memory_used_mb) {
            const totalGB = (device.memory_total_mb / 1024).toFixed(1);
            const usedGB = (device.memory_used_mb / 1024).toFixed(1);
            info += `<br><small class="text-muted">${usedGB} / ${totalGB} GB</small>`;
        }
        info += `</div>`;
    }
    
    // Device Uptime with improved formatting
    if (device.device_uptime !== null && device.device_uptime !== undefined && device.device_uptime !== '') {
        hasData = true;
        const uptime = formatUptime(device.device_uptime);
        info += `<div class="mb-1">
                    <i class="fas fa-clock text-info"></i>
                    <strong>Uptime:</strong> 
                    <span class="text-info">${uptime}</span>
                 </div>`;
    }
    
    // Interface Status if available
    if (device.interface_status !== null && device.interface_status !== undefined && device.interface_status !== '') {
        hasData = true;
        const statusClass = device.interface_status === 'up' ? 'success' : 'danger';
        const statusIcon = device.interface_status === 'up' ? 'fa-link' : 'fa-unlink';
        info += `<div class="mb-1">
                    <i class="fas ${statusIcon} text-${statusClass}"></i>
                    <strong>Interface:</strong> 
                    <span class="text-${statusClass}">${device.interface_status.toUpperCase()}</span>
                 </div>`;
    }
    
    // Interface Speed if available
    if (device.interface_speed_mbps !== null && device.interface_speed_mbps !== undefined && device.interface_speed_mbps !== '') {
        hasData = true;
        const speedMbps = parseFloat(device.interface_speed_mbps);
        const speedFormatted = speedMbps >= 1000 ? (speedMbps/1000).toFixed(1) + ' Gbps' : speedMbps + ' Mbps';
        info += `<div class="mb-1">
                    <i class="fas fa-tachometer-alt text-primary"></i>
                    <strong>Speed:</strong> 
                    <span class="text-primary">${speedFormatted}</span>
                 </div>`;
    }
    
    return hasData ? info : '<span class="text-muted"><i class="fas fa-exclamation-circle"></i> No metrics data</span>';
}

function getSystemInfo(device) {
    let info = '';
    let hasSystemData = false;
    
    // Device name/hostname
    if (device.device_name && device.device_name.trim() !== '') {
        hasSystemData = true;
        info += `<div class="mb-1">
                    <i class="fas fa-server text-primary"></i>
                    <strong class="text-primary">${device.device_name}</strong>
                 </div>`;
    }
    
    // Device description if available
    if (device.device_description && device.device_description.trim() !== '' && device.device_description !== device.device_name) {
        hasSystemData = true;
        info += `<div class="mb-1">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        ${device.device_description.substring(0, 50)}${device.device_description.length > 50 ? '...' : ''}
                    </small>
                 </div>`;
    }
    
    // Device location if available
    if (device.device_location && device.device_location.trim() !== '') {
        hasSystemData = true;
        info += `<div class="mb-1">
                    <small class="text-info">
                        <i class="fas fa-map-marker-alt"></i>
                        ${device.device_location}
                    </small>
                 </div>`;
    }
    
    // Device contact if available
    if (device.device_contact && device.device_contact.trim() !== '') {
        hasSystemData = true;
        info += `<div class="mb-1">
                    <small class="text-secondary">
                        <i class="fas fa-user"></i>
                        ${device.device_contact}
                    </small>
                 </div>`;
    }
    
    return hasSystemData ? info : '<span class="text-muted"><i class="fas fa-question-circle"></i> No system info available</span>';
}

function updateSummaryCards(devices) {
    let onlineCount = 0;
    let warningCount = 0;
    let offlineCount = 0;
    let snmpEnabledCount = devices.length;
    
    // Improved device status calculation with better validation
    devices.forEach(device => {
        let hasMetrics = false;
        let hasWarnings = false;
        
        // Check if device has any SNMP metrics
        if ((device.cpu_usage_percent !== null && device.cpu_usage_percent !== undefined && device.cpu_usage_percent !== '') ||
            (device.memory_usage_percent !== null && device.memory_usage_percent !== undefined && device.memory_usage_percent !== '') ||
            (device.device_uptime !== null && device.device_uptime !== undefined && device.device_uptime !== '') ||
            (device.device_name !== null && device.device_name !== undefined && device.device_name !== '')) {
            hasMetrics = true;
        }
        
        // Check for warning conditions
        if (hasMetrics) {
            const cpuUsage = parseFloat(device.cpu_usage_percent) || 0;
            const memUsage = parseFloat(device.memory_usage_percent) || 0;
            
            if (cpuUsage > 80 || memUsage > 80) {
                hasWarnings = true;
            }
            
            // Check interface status
            if (device.interface_status === 'down') {
                hasWarnings = true;
            }
        }
        
        // Categorize device status
        if (!hasMetrics) {
            offlineCount++;
        } else if (hasWarnings) {
            warningCount++;
        } else {
            onlineCount++;
        }
    });
    
    // Update summary cards with animation
    animateCounterUpdate('#deviceOnlineCount', onlineCount);
    animateCounterUpdate('#deviceWarningCount', warningCount);
    animateCounterUpdate('#deviceOfflineCount', offlineCount);
    animateCounterUpdate('#snmpEnabledCount', snmpEnabledCount);
}

// Helper function to animate counter updates
function animateCounterUpdate(selector, newValue) {
    const element = $(selector);
    const currentValue = parseInt(element.text()) || 0;
    
    // Only animate if value changed
    if (currentValue !== newValue) {
        element.addClass('metrics-loading');
        
        setTimeout(() => {
            element.text(newValue);
            element.removeClass('metrics-loading');
            
            // Add a subtle bounce effect
            element.parent().addClass('animate__animated animate__pulse');
            setTimeout(() => {
                element.parent().removeClass('animate__animated animate__pulse');
            }, 600);
        }, 300);
    }
}

// Utility functions
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('id-ID');
}

function formatUptime(uptimeHundredths) {
    const seconds = Math.floor(uptimeHundredths / 100);
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    
    if (days > 0) {
        return `${days}d ${hours}h ${minutes}m`;
    } else if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
}

function updateLastUpdateTime() {
    const now = new Date().toLocaleString('id-ID');
    $('#lastUpdateTime').text(now);
    
    // Calculate next update time (30 seconds from now)
    const next = new Date(Date.now() + 30000).toLocaleString('id-ID');
    $('#nextUpdateTime').text(next);
}

function updateStatusInfo(devices) {
    // Hide all status alerts first
    $('#snmpStatusInfo').hide();
    $('#noSNMPDevicesInfo').hide();
    $('#snmpExtensionWarning').hide();
    
    if (devices.length === 0) {
        // No SNMP devices found
        $('#noSNMPDevicesInfo').show();
    } else {
        // SNMP devices found - show success status
        $('#snmpStatusInfo').show();
        
        // Check if any device has actual SNMP metrics
        const devicesWithMetrics = devices.filter(device => 
            device.cpu_usage_percent !== null || 
            device.memory_usage_percent !== null ||
            device.device_name !== null
        );
        
        if (devicesWithMetrics.length > 0) {
            $('#snmpStatusInfo').removeClass('alert-warning').addClass('alert-success');
            $('#snmpStatusInfo').find('strong').text('SNMP Active:');
            $('#snmpStatusInfo').find('strong').next().text(
                ` ${devicesWithMetrics.length} devices with active SNMP metrics. Real-time monitoring is operational.`
            );
        } else {
            $('#snmpStatusInfo').removeClass('alert-success').addClass('alert-warning');
            $('#snmpStatusInfo').find('strong').text('SNMP Configured:');
            $('#snmpStatusInfo').find('strong').next().text(
                ` ${devices.length} devices configured but no metrics collected yet. Click "Collect Sample Metrics" to start monitoring.`
            );
        }
    }
}

function setupAutoRefresh() {
    clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(function() {
        console.log('🔄 Auto-refreshing SNMP data...');
        loadSNMPDevices();
    }, 30000); // Refresh every 30 seconds
}

function setupDeviceSearch() {
    $('#deviceSearch').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        const filteredDevices = deviceData.filter(device => 
            device.name.toLowerCase().includes(searchTerm) ||
            device.ip_address.toLowerCase().includes(searchTerm) ||
            device.item_type.toLowerCase().includes(searchTerm) ||
            (device.device_name && device.device_name.toLowerCase().includes(searchTerm))
        );
        displayDevices(filteredDevices);
    });
}

function refreshAllData() {
    const refreshIcon = $('#refreshIcon');
    const refreshBtn = $('#refreshBtn');
    
    refreshIcon.addClass('spinning');
    refreshBtn.prop('disabled', true);
    
    loadSNMPDevices();
    
    setTimeout(() => {
        refreshIcon.removeClass('spinning');
        refreshBtn.prop('disabled', false);
    }, 2000);
}

function collectAllSNMP() {
    if (!confirm('Collect SNMP metrics from all enabled devices? This may take a few minutes.')) {
        return;
    }
    
    console.log('📊 Collecting SNMP metrics from all devices...');
    
    $.ajax({
        url: 'api/snmp.php',
        method: 'POST',
        data: { action: 'collect_all' },
        dataType: 'json',
        timeout: 120000, // 2 minutes timeout
        success: function(response) {
            if (response.success) {
                alert('SNMP metrics collection completed successfully!');
                loadSNMPDevices(); // Refresh the display
            } else {
                alert('SNMP collection failed: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ SNMP collection error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            console.error('Status Code:', xhr.status);
            
            let errorMessage = 'Cannot connect to server';
            if (xhr.status === 401) {
                errorMessage = 'Authentication required - please login again';
                window.location.href = 'login.php';
            } else if (xhr.status === 403) {
                errorMessage = 'Access denied - insufficient permissions';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error - check SNMP configuration';
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    errorMessage = 'Server error: ' + xhr.responseText.substring(0, 100);
                }
            }
            
            alert('SNMP collection failed: ' + errorMessage);
        }
    });
}

function collectDeviceMetrics(deviceId) {
    if (!deviceId && currentDeviceId) {
        deviceId = currentDeviceId;
    }
    
    if (!deviceId) {
        alert('No device selected');
        return;
    }
    
    console.log('📊 Collecting metrics for device:', deviceId);
    
    $.ajax({
        url: 'api/snmp.php',
        method: 'POST',
        data: { 
            action: 'collect',
            item_id: deviceId 
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success) {
                alert('Device metrics collected successfully!');
                loadSNMPDevices(); // Refresh the display
                if (currentDeviceId === deviceId) {
                    showDeviceDetails(deviceId); // Refresh modal if open
                }
            } else {
                alert('Metrics collection failed: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Device metrics collection error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            console.error('Status Code:', xhr.status);
            
            let errorMessage = 'Cannot connect to server';
            if (xhr.status === 401) {
                errorMessage = 'Authentication required - please login again';
                window.location.href = 'login.php';
            } else if (xhr.status === 403) {
                errorMessage = 'Access denied - insufficient permissions';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error - check SNMP configuration';
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    errorMessage = 'Server error: ' + xhr.responseText.substring(0, 100);
                }
            }
            
            alert('Metrics collection failed: ' + errorMessage);
        }
    });
}

function showDeviceDetails(deviceId) {
    currentDeviceId = deviceId;
    
    console.log('👁️ Showing details for device:', deviceId);
    
    // Find device in current data with validation
    const device = deviceData.find(d => d.id == deviceId);
    if (!device) {
        showError('Device tidak ditemukan dalam daftar saat ini');
        return;
    }
    
    // Validate device has required fields
    if (!device.name || !device.ip_address) {
        showError('Data device tidak lengkap');
        return;
    }
    
    $('#deviceModalTitle').html(`
        <i class="fas fa-chart-line"></i> 
        ${device.name} - Detail Metrics SNMP
        <small class="text-muted">(${device.ip_address})</small>
    `);
    
    // Enhanced loading state
    $('#deviceModalBody').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <h5 class="mt-3 text-muted">Memuat data metrics device...</h5>
            <p class="text-muted">Mohon tunggu sebentar</p>
        </div>
    `);
    
    $('#deviceDetailsModal').modal('show');
    
    // Load detailed metrics with enhanced error handling
    $.ajax({
        url: 'api/snmp.php?action=metrics&id=' + deviceId + '&limit=5',
        method: 'GET',
        dataType: 'json',
        timeout: 15000, // 15 second timeout
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                displayDeviceMetrics(response.data, device);
            } else {
                displayNoMetricsMessage(device);
            }
        },
        error: function(xhr, status, error) {
            displayMetricsError(xhr, status, error, device);
        }
    });
}

// Enhanced function to display no metrics message
function displayNoMetricsMessage(device) {
    const content = `
        <div class="no-data-message">
            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">Belum Ada Data Metrics</h4>
            <p class="text-muted mb-4">
                Device <strong>${device.name}</strong> belum memiliki data SNMP metrics yang tersimpan.
                <br>Silakan kumpulkan metrics terlebih dahulu.
            </p>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6><i class="fas fa-info-circle text-info"></i> Informasi Device</h6>
                            <div class="row">
                                <div class="col-6"><strong>Nama:</strong> ${device.name}</div>
                                <div class="col-6"><strong>Tipe:</strong> ${device.item_type}</div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6"><strong>IP:</strong> <code>${device.ip_address}</code></div>
                                <div class="col-6"><strong>SNMP Port:</strong> ${device.snmp_port || 161}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary" onclick="collectDeviceMetrics(${device.id})">
                    <i class="fas fa-download"></i> Kumpulkan Metrics Sekarang
                </button>
                <button class="btn btn-outline-secondary ml-2" onclick="testSNMPConnection(${device.id})">
                    <i class="fas fa-check"></i> Test Koneksi SNMP
                </button>
            </div>
        </div>
        <div class="mt-3">
            <div class="alert alert-info">
                <i class="fas fa-lightbulb"></i>
                <strong>Tips:</strong> Klik "Kumpulkan Metrics Sekarang" untuk mengambil data real dari device via SNMP. 
                Data akan diperbarui dengan informasi aktual seperti CPU usage, memory, interface status, 
                dan traffic statistics dari device yang sebenarnya.
            </div>
        </div>
    `;
    
    $('#deviceModalBody').html(content);
}

// Enhanced function to display metrics loading errors
function displayMetricsError(xhr, status, error, device) {
    let errorMessage = 'Gagal memuat data metrics';
    let errorDetails = error;
    let troubleshootingTips = '';
    
    // Provide specific error messages based on status
    if (xhr.status === 0) {
        errorMessage = 'Koneksi ke server terputus';
        errorDetails = 'Tidak dapat terhubung ke server';
        troubleshootingTips = 'Periksa koneksi internet Anda dan coba lagi.';
    } else if (xhr.status === 404) {
        errorMessage = 'API endpoint tidak ditemukan';
        errorDetails = 'SNMP API tidak tersedia';
        troubleshootingTips = 'Hubungi administrator sistem.';
    } else if (xhr.status === 500) {
        errorMessage = 'Server error';
        errorDetails = 'Error pada server saat memproses request SNMP';
        troubleshootingTips = 'Periksa konfigurasi SNMP atau hubungi administrator.';
    } else if (status === 'timeout') {
        errorMessage = 'Request timeout';
        errorDetails = 'Server tidak merespons dalam waktu yang ditentukan';
        troubleshootingTips = 'Device mungkin tidak merespons SNMP. Periksa status device.';
    }
    
    const content = `
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> ${errorMessage}</h5>
            <p class="mb-2"><strong>Detail Error:</strong> ${errorDetails}</p>
            ${troubleshootingTips ? `<p class="mb-2"><strong>Solusi:</strong> ${troubleshootingTips}</p>` : ''}
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <strong>Device:</strong> ${device.name}<br>
                    <strong>IP:</strong> ${device.ip_address}<br>
                    <strong>Status Code:</strong> ${xhr.status || 'N/A'}
                </div>
                <div class="col-md-6">
                    <button class="btn btn-outline-primary btn-sm" onclick="showDeviceDetails(${device.id})">
                        <i class="fas fa-sync"></i> Coba Lagi
                    </button>
                    <button class="btn btn-outline-secondary btn-sm ml-2" onclick="collectDeviceMetrics(${device.id})">
                        <i class="fas fa-download"></i> Kumpulkan Metrics
                    </button>
                </div>
            </div>
        </div>
    `;
    
    $('#deviceModalBody').html(content);
}

// Function to test SNMP connection
function testSNMPConnection(deviceId) {
    console.log('🔧 Testing SNMP connection for device:', deviceId);
    
    $.ajax({
        url: 'api/snmp.php?action=test&id=' + deviceId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('✅ Koneksi SNMP berhasil!\n\nDetail:\n' + 
                      'Version: ' + (response.data.version || 'N/A') + '\n' +
                      'Community: ' + (response.data.community || 'N/A') + '\n' +
                      'Tests Passed: ' + (response.data.tests_passed || 'N/A'));
            } else {
                alert('❌ Koneksi SNMP gagal!\n\nError: ' + response.message);
            }
        },
        error: function() {
            alert('❌ Gagal melakukan test koneksi SNMP');
        }
    });
}

function displayDeviceMetrics(metrics, device) {
    const latestMetric = metrics[0];
    
    // Helper function to safely get metric value
    const getMetricValue = (value, defaultVal = 'N/A') => {
        return (value !== null && value !== undefined && value !== '') ? value : defaultVal;
    };
    
    // Helper function to determine metric class based on value and thresholds
    const getMetricClass = (value, highThreshold = 80, mediumThreshold = 60) => {
        if (value === null || value === undefined || value === '') return 'secondary';
        const numValue = parseFloat(value);
        return numValue > highThreshold ? 'danger' : numValue > mediumThreshold ? 'warning' : 'success';
    };
    
    let content = `
        <div class="row">
            <div class="col-md-6">
                <div class="card bg-gradient-primary text-white">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> Device Information</h5>
                        <div class="row">
                            <div class="col-6">
                                <strong>Name:</strong><br>
                                <span class="h6">${device.name}</span>
                            </div>
                            <div class="col-6">
                                <strong>Type:</strong><br>
                                <span class="badge badge-light text-dark">${device.item_type}</span>
                            </div>
                        </div>
                        <hr class="bg-white">
                        <div class="row">
                            <div class="col-6">
                                <strong>IP Address:</strong><br>
                                <code class="text-white bg-transparent">${device.ip_address}</code>
                            </div>
                            <div class="col-6">
                                <strong>SNMP Port:</strong><br>
                                <span>${device.snmp_port || 161}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-gradient-info text-white">
                    <div class="card-body">
                        <h5><i class="fas fa-clock"></i> System Status</h5>
                        <p><strong>Last Update:</strong><br>
                           <span class="h6">${formatDateTime(latestMetric.metric_time)}</span></p>
                        <p><strong>System Name:</strong><br>
                           <span class="h6">${getMetricValue(latestMetric.device_name, 'Unknown')}</span></p>
                        ${latestMetric.device_description ? `<p><strong>Description:</strong><br>
                           <small>${latestMetric.device_description.substring(0, 100)}${latestMetric.device_description.length > 100 ? '...' : ''}</small></p>` : ''}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card border-left-${getMetricClass(latestMetric.cpu_usage_percent)} h-100">
                    <div class="card-body text-center">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="text-uppercase text-muted font-weight-bold mb-1">
                                    <i class="fas fa-microchip text-${getMetricClass(latestMetric.cpu_usage_percent)}"></i> CPU Usage
                                </h6>
                                <div class="metric-value text-${getMetricClass(latestMetric.cpu_usage_percent)} mb-0">
                                    ${latestMetric.cpu_usage_percent ? parseFloat(latestMetric.cpu_usage_percent).toFixed(1) + '%' : 'N/A'}
                                </div>
                                ${latestMetric.cpu_usage_percent ? `
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-${getMetricClass(latestMetric.cpu_usage_percent)}" 
                                         style="width: ${Math.min(parseFloat(latestMetric.cpu_usage_percent), 100)}%"></div>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-${getMetricClass(latestMetric.memory_usage_percent)} h-100">
                    <div class="card-body text-center">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="text-uppercase text-muted font-weight-bold mb-1">
                                    <i class="fas fa-memory text-${getMetricClass(latestMetric.memory_usage_percent)}"></i> Memory Usage
                                </h6>
                                <div class="metric-value text-${getMetricClass(latestMetric.memory_usage_percent)} mb-0">
                                    ${latestMetric.memory_usage_percent ? parseFloat(latestMetric.memory_usage_percent).toFixed(1) + '%' : 'N/A'}
                                </div>
                                ${latestMetric.memory_total_mb && latestMetric.memory_used_mb ? `
                                <small class="text-muted">
                                    ${(latestMetric.memory_used_mb / 1024).toFixed(1)} / ${(latestMetric.memory_total_mb / 1024).toFixed(1)} GB
                                </small>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-${getMetricClass(latestMetric.memory_usage_percent)}" 
                                         style="width: ${Math.min(parseFloat(latestMetric.memory_usage_percent || 0), 100)}%"></div>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-success h-100">
                    <div class="card-body text-center">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="text-uppercase text-muted font-weight-bold mb-1">
                                    <i class="fas fa-clock text-success"></i> System Uptime
                                </h6>
                                <div class="metric-value text-success mb-0">
                                    ${latestMetric.device_uptime ? formatUptime(latestMetric.device_uptime) : 'N/A'}
                                </div>
                                ${latestMetric.device_uptime ? `<small class="text-muted">Since last boot</small>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Network Interface Information - Enhanced Display
    let hasNetworkData = latestMetric.interface_status || latestMetric.bytes_in_total || latestMetric.bytes_out_total || latestMetric.interface_speed_mbps;
    if (hasNetworkData) {
        content += `
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card border-left-info">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-network-wired text-info"></i> Network Interface Metrics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">`;
        
        // Interface Status
        if (latestMetric.interface_status) {
            const statusClass = latestMetric.interface_status === 'up' ? 'success' : 'danger';
            const statusIcon = latestMetric.interface_status === 'up' ? 'fa-link' : 'fa-unlink';
            content += `
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="fas ${statusIcon} fa-2x text-${statusClass} mb-2"></i>
                                        <h6 class="text-uppercase text-muted font-weight-bold">Status</h6>
                                        <span class="badge badge-${statusClass} badge-pill px-3 py-2">
                                            ${latestMetric.interface_status.toUpperCase()}
                                        </span>
                                    </div>
                                </div>`;
        }
        
        // Interface Speed
        if (latestMetric.interface_speed_mbps) {
            const speedMbps = parseFloat(latestMetric.interface_speed_mbps);
            const speedFormatted = speedMbps >= 1000 ? 
                `${(speedMbps/1000).toFixed(1)} Gbps` : 
                `${speedMbps} Mbps`;
            content += `
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-tachometer-alt fa-2x text-primary mb-2"></i>
                                        <h6 class="text-uppercase text-muted font-weight-bold">Speed</h6>
                                        <div class="h5 text-primary mb-0">${speedFormatted}</div>
                                    </div>
                                </div>`;
        }
        
        // Bytes In
        if (latestMetric.bytes_in_total) {
            content += `
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-download fa-2x text-success mb-2"></i>
                                        <h6 class="text-uppercase text-muted font-weight-bold">Data In</h6>
                                        <div class="h6 text-success mb-0">${formatBytes(latestMetric.bytes_in_total)}</div>
                                    </div>
                                </div>`;
        }
        
        // Bytes Out
        if (latestMetric.bytes_out_total) {
            content += `
                                <div class="col-md-3 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-upload fa-2x text-warning mb-2"></i>
                                        <h6 class="text-uppercase text-muted font-weight-bold">Data Out</h6>
                                        <div class="h6 text-warning mb-0">${formatBytes(latestMetric.bytes_out_total)}</div>
                                    </div>
                                </div>`;
        }
        
        content += `
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
    }
    
    // Optical Power Information - Enhanced Display for OLT/ONT
    let hasOpticalData = latestMetric.optical_power_tx_dbm || latestMetric.optical_power_rx_dbm;
    if (hasOpticalData) {
        content += `
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card border-left-warning">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">
                                <i class="fas fa-eye text-warning"></i> Optical Power Levels
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row justify-content-center">`;
        
        // TX Power
        if (latestMetric.optical_power_tx_dbm) {
            const txPower = parseFloat(latestMetric.optical_power_tx_dbm);
            const txClass = txPower > -15 ? 'success' : txPower > -25 ? 'warning' : 'danger';
            content += `
                                <div class="col-md-6 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-arrow-up fa-2x text-${txClass} mb-2"></i>
                                        <h6 class="text-uppercase text-muted font-weight-bold">TX Power</h6>
                                        <div class="h4 text-${txClass} mb-0">${txPower.toFixed(2)} dBm</div>
                                        <small class="text-muted">Transmit Power Level</small>
                                    </div>
                                </div>`;
        }
        
        // RX Power
        if (latestMetric.optical_power_rx_dbm) {
            const rxPower = parseFloat(latestMetric.optical_power_rx_dbm);
            const rxClass = rxPower > -20 ? 'success' : rxPower > -30 ? 'warning' : 'danger';
            content += `
                                <div class="col-md-6 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-arrow-down fa-2x text-${rxClass} mb-2"></i>
                                        <h6 class="text-uppercase text-muted font-weight-bold">RX Power</h6>
                                        <div class="h4 text-${rxClass} mb-0">${rxPower.toFixed(2)} dBm</div>
                                        <small class="text-muted">Receive Power Level</small>
                                    </div>
                                </div>`;
        }
        
        content += `
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
    }
    
    // Add additional system information if available
    let hasAdditionalInfo = latestMetric.device_contact || latestMetric.device_location;
    if (hasAdditionalInfo) {
        content += `
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card border-left-secondary">
                        <div class="card-header bg-transparent">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle text-secondary"></i> Additional System Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                ${latestMetric.device_contact ? `
                                <div class="col-md-6">
                                    <strong><i class="fas fa-user text-secondary"></i> Contact:</strong><br>
                                    <span class="text-muted">${latestMetric.device_contact}</span>
                                </div>` : ''}
                                ${latestMetric.device_location ? `
                                <div class="col-md-6">
                                    <strong><i class="fas fa-map-marker-alt text-secondary"></i> Location:</strong><br>
                                    <span class="text-muted">${latestMetric.device_location}</span>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
    }
    
    $('#deviceModalBody').html(content);
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function filterDevices(deviceType) {
    const filteredDevices = deviceData.filter(device => 
        device.item_type.toLowerCase().includes(deviceType)
    );
    displayDevices(filteredDevices);
    
    // Clear search input
    $('#deviceSearch').val('');
}

function showError(message) {
    $('#deviceTableBody').html(`
        <tr>
            <td colspan="8" class="text-center py-4">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            </td>
        </tr>
    `);
}

function showSNMPConfiguration() {
    alert('SNMP Configuration panel coming soon! For now, configure SNMP settings in the device edit form.');
}

function logout() {
    if (confirm('Apakah Anda yakin ingin logout?')) {
        window.location.href = 'api/auth.php?action=logout';
    }
}

console.log('🚀 SNMP Dashboard script loaded successfully');
</script>

</body>
</html>
