// Map.js - FTTH Planner Map Functionality

let map;
let markers = {};
let routes = {};
let isRoutingMode = false;
let routingFromItem = null;
let isLoadingItems = false; // Flag to prevent simultaneous loads
let routingType = 'road'; // 'road' or 'straight'
let currentRoutes = [];
// Auto generate tiang tumpu settings
window.autoGenerateTiangTumpu = false;
window.tiangTumpuInterval = 30; // meters
window.generateAtTurns = true;

// Initialize map
function initMap() {
    try {
        console.log('🗺️ Starting map initialization...');
        
        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            console.error('❌ Leaflet library not loaded!');
            alert('Error: Leaflet library tidak ditemukan. Silakan refresh halaman.');
            return;
        }
        
        // Check if map container exists
        const mapContainer = document.getElementById('map');
        if (!mapContainer) {
            console.error('❌ Map container not found!');
            alert('Error: Map container tidak ditemukan.');
            return;
        }
        
        console.log('✅ Leaflet loaded, map container found');
        
        // Create map with enhanced options
        map = L.map('map', {
            center: [-6.2088, 118], // Jakarta, Indonesia
            zoom: 11,
            minZoom: 5,
            maxZoom: 20,
            zoomControl: false, // We'll add custom zoom control
            fullscreenControl: true,
            fullscreenControlOptions: {
                position: 'topleft'
            }
        });
        
        console.log('✅ Map object created successfully');

    // Define multiple tile layers
    const tileLayers = {
        "OpenStreetMap": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }),
        
        "CartoDB Positron": L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 20
        }),
        
        "CartoDB Dark": L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 20
        }),
        
        "Satellite": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            maxZoom: 20
        }),
        
        "Terrain": L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
            maxZoom: 17
        }),
        
        "Google Hybrid": L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            attribution: '&copy; Google',
            maxZoom: 20
        })
    };
    
    // Add default layer (OpenStreetMap)
    tileLayers["OpenStreetMap"].addTo(map);
    
    // Add layer control
    L.control.layers(tileLayers, null, {
        position: 'topright',
        collapsed: false
    }).addTo(map);
    
    // Add custom zoom control with home button
    const zoomControl = L.control.zoom({
        position: 'topleft'
    }).addTo(map);
    
    // Add home button to zoom control
    const homeControl = L.Control.extend({
        options: {
            position: 'topleft'
        },
        onAdd: function(map) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
            container.style.backgroundColor = 'white';
            container.style.backgroundImage = 'none';
            container.style.width = '26px';
            container.style.height = '26px';
            container.style.cursor = 'pointer';
            container.innerHTML = '<i class="fas fa-home" style="font-size: 14px; line-height: 26px; text-align: center; width: 26px; display: block;"></i>';
            container.title = 'Zoom to Indonesia';
            
            container.onclick = function() {
                map.setView([-2.5, 118], 5); // Indonesia overview
            };
            
            return container;
        }
    });
    
    new homeControl().addTo(map);
    
    // Add scale control
    L.control.scale({
        position: 'bottomright',
        metric: true,
        imperial: false
    }).addTo(map);
    
    // Add coordinates display
    const coordsControl = L.control({position: 'bottomleft'});
    coordsControl.onAdd = function(map) {
        this._div = L.DomUtil.create('div', 'leaflet-control-coords');
        this._div.style.background = 'rgba(255,255,255,0.8)';
        this._div.style.padding = '5px';
        this._div.style.margin = '0';
        this._div.style.fontSize = '11px';
        this._div.innerHTML = 'Move mouse over map';
        return this._div;
    };
    coordsControl.update = function(lat, lng) {
        this._div.innerHTML = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
    };
    coordsControl.addTo(map);
    
    // Update coordinates on mouse move
    map.on('mousemove', function(e) {
        coordsControl.update(e.latlng.lat, e.latlng.lng);
    });
    
    // Enhanced zoom behavior
    map.on('zoomend', function() {
        const zoom = map.getZoom();
        if (zoom < 10) {
            // Hide detailed markers at low zoom
            Object.values(markers).forEach(marker => {
                if (marker._icon) {
                    marker._icon.style.opacity = '0.7';
                }
            });
        } else {
            // Show detailed markers at high zoom
            Object.values(markers).forEach(marker => {
                if (marker._icon) {
                    marker._icon.style.opacity = '1';
                }
            });
        }
    });

    // Add map click event for adding new items
    map.on('click', function(e) {
        if (!isRoutingMode) {
            showAddItemModal(e.latlng.lat, e.latlng.lng);
        }
    });

    // Load existing items with delay to ensure session is ready
    setTimeout(function() {
        console.log('🕐 Loading items after session initialization...');
        loadItems();
        loadRoutes();
    }, 500);
    
    // Add legend
    addMapLegend();
    
    console.log('🗺️ Enhanced map initialized with multiple tile layers and zoom controls');
    
    // Add loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'map-loading';
    loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading map...';
    mapContainer.appendChild(loadingDiv);
    
    // Hide loading indicator after tiles load
    map.on('tilesloaded', function() {
        if (loadingDiv.parentNode) {
            loadingDiv.parentNode.removeChild(loadingDiv);
        }
    });
    
    console.log('🎉 Map initialization completed successfully!');
    
    } catch (error) {
        console.error('❌ Error during map initialization:', error);
        alert('Error initializing map: ' + error.message + '\n\nSilakan refresh halaman atau cek console untuk detail.');
        
        // Try to show error in map container
        const errorMapContainer = document.getElementById('map');
        if (errorMapContainer) {
            errorMapContainer.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; color: #6c757d; flex-direction: column;">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <h5>Error Loading Map</h5>
                    <p>Silakan refresh halaman atau hubungi administrator</p>
                    <small>Error: ${error.message}</small>
                </div>
            `;
        }
    }
}

// Create custom marker icon
function createCustomIcon(itemType, color, isMonitoring = false, item = null) {
    let iconClass = 'fas fa-circle';
    
    switch(itemType) {
        case 'OLT':
        case '1':
            iconClass = 'fas fa-server';
            break;
        case 'Tiang Tumpu':
        case '2':
            iconClass = 'fas fa-tower-broadcast';
            break;
        case 'Tiang ODP':
        case 'ODP':
        case '3':
            iconClass = 'fas fa-project-diagram';
            break;
        case 'Tiang ODC':
        case '4':
            iconClass = 'fas fa-network-wired';
            break;
        case 'Tiang Joint Closure':
        case '5':
            iconClass = 'fas fa-link';
            break;
        case 'ONT':
        case '6':
            iconClass = 'fas fa-home';
            break;
        case 'Server':
        case '7':
            iconClass = 'fas fa-server';
            break;
        case 'ODC':
        case '8':
            iconClass = 'fas fa-box';
            break;
        case 'Access Point':
        case '9':
            iconClass = 'fas fa-wifi';
            break;
        case 'HTB':
        case '10':
            iconClass = 'fas fa-home';
            break;
        case '11':
            iconClass = 'fas fa-box';
            break;
    }

    // Create base marker classes
    let markerClasses = ['custom-marker', `marker-${itemType.toLowerCase().replace(' ', '')}`];
    
    // Add monitoring classes if applicable
    if (isMonitoring) {
        markerClasses.push('marker-monitoring-item');
        if (item && item.monitoring_status) {
            markerClasses.push(`monitoring-${item.monitoring_status}`);
        }
    }
    
    // Enhanced marker style for monitoring items
    let markerStyle = `
        background-color: ${color}; 
        width: 30px; 
        height: 30px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border: 3px solid white; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        position: relative;
        transition: all 0.3s ease;
    `;
    
    // Add monitoring enhancement
    if (isMonitoring) {
        markerStyle += `
            border-width: 4px;
            cursor: pointer;
        `;
        
        // Add status-specific border color
        if (item && item.monitoring_status) {
            switch (item.monitoring_status) {
                case 'online':
                    markerStyle += `border-color: #28a745;`;
                    break;
                case 'offline':
                    markerStyle += `border-color: #dc3545;`;
                    break;
                case 'warning':
                    markerStyle += `border-color: #ffc107;`;
                    break;
                default:
                    markerStyle += `border-color: #6c757d;`;
            }
        }
    }
    
    // Create ping indicator HTML for monitoring items
    let pingIndicatorHTML = '';
    if (isMonitoring && item) {
        const indicatorClass = getPingIndicatorClass(item.monitoring_status || 'offline');
        pingIndicatorHTML = `<div class="ping-indicator ${indicatorClass}" id="ping-indicator-${item.id}"></div>`;
    }
    
    const markerHTML = `
        <div class="${markerClasses.join(' ')}" style="${markerStyle}" data-item-id="${item ? item.id : ''}">
            <i class="${iconClass}" style="color: white; font-size: 14px;"></i>
            ${pingIndicatorHTML}
        </div>
    `;

    return L.divIcon({
        className: 'custom-div-icon',
        html: markerHTML,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -15]
    });
}

// Create popup content for item
function createPopupContent(item) {
    let tubeColorName = item.tube_color_name || 'Tidak ada';
    let splitterMain = item.splitter_main_ratio || 'Tidak ada';
    let splitterOdp = item.splitter_odp_ratio || 'Tidak ada';
    
    // Check if item is monitoring enabled (Pelanggan, Server, OLT, or Access Point)
    const isMonitoringItem = (item.item_type_id == 6 || item.item_type_id == 7 || item.item_type_id == 1 || item.item_type_id == 8); // Pelanggan, Server, OLT, Access Point
    const isOLT = (item.item_type_id == 1);
    const isODC = (item.item_type_id == 4); // ODC Pole Mounted (4)
    const isAccessPoint = (item.item_type_id == 8);
    
    let content = `
        <div>
            <h5><i class="${getItemIcon(item.item_type_name)}"></i> ${item.name}</h5>
            <div class="popup-info">
                <div class="info-row">
                    <span class="info-label">Jenis:</span> ${item.item_type_name}
                </div>
                ${item.description ? `<div class="info-row"><span class="info-label">Deskripsi:</span> ${item.description}</div>` : ''}
                ${item.address ? `<div class="info-row"><span class="info-label">Alamat:</span> ${item.address}</div>` : ''}`;
    
    if (isMonitoringItem && !isODC) {
        // Show monitoring fields for Pelanggan, Server, and OLT (but not ODC)
        const statusColor = getMonitoringStatusColor(item.monitoring_status || 'offline');
        const statusText = getMonitoringStatusText(item.monitoring_status || 'offline');
        
        content += `
                <div class="info-row">
                    <span class="info-label">IP Address:</span> 
                    <span class="badge badge-info">${item.ip_address || 'Belum di-set'}</span>
                </div>
                ${item.port_http ? `<div class="info-row"><span class="info-label">Port Management:</span> <span class="badge badge-primary">${item.port_http}</span></div>` : ''}
                ${item.port_https && item.port_https != 22 ? `<div class="info-row"><span class="info-label">Port HTTPS:</span> <span class="badge badge-success">${item.port_https}</span></div>` : ''}
                <div class="info-row">
                    <span class="info-label">Status Monitoring:</span> 
                    <span class="badge" style="background-color: ${statusColor}; color: white;">
                        <i class="fas ${getMonitoringStatusIcon(item.monitoring_status || 'offline')}"></i> 
                        ${statusText}
                    </span>
                    ${item.response_time_ms ? `<small class="ml-1 text-success">(Ping: ${item.response_time_ms}ms)</small>` : ''}
                </div>
                ${item.last_ping_time ? `<div class="info-row"><span class="info-label">Last Ping:</span> <small class="text-muted">${new Date(item.last_ping_time).toLocaleString()}</small></div>` : ''}`;
        
        // Add SNMP status for SNMP enabled devices
        if (item.snmp_enabled == 1) {
            const snmpStatusColor = item.snmp_status === 'success' ? '#28a745' : 
                                  item.snmp_status === 'failed' ? '#dc3545' : '#6c757d';
            const snmpStatusText = item.snmp_status === 'success' ? 'SNMP Active' : 
                                 item.snmp_status === 'failed' ? 'SNMP Failed' : 'SNMP Config';
            
            content += `
                <div class="info-row">
                    <span class="info-label">SNMP Status:</span> 
                    <span class="badge" style="background-color: ${snmpStatusColor}; color: white;">
                        <i class="fas fa-chart-line"></i> ${snmpStatusText}
                    </span>
                    <small class="ml-1">(v${item.snmp_version || '2c'}, ${item.snmp_community || 'public'})</small>
                </div>`;
                
            // Show SNMP metrics if available
            if (item.cpu_usage !== null || item.memory_usage !== null) {
                content += `
                <div class="info-row">
                    <span class="info-label">Performance:</span>`;
                    
                if (item.cpu_usage !== null && item.cpu_usage !== undefined) {
                    const cpuColor = item.cpu_usage > 80 ? 'danger' : 
                                   item.cpu_usage > 60 ? 'warning' : 'success';
                    content += ` <span class="badge badge-${cpuColor}">CPU: ${parseFloat(item.cpu_usage).toFixed(1)}%</span>`;
                }
                
                if (item.memory_usage !== null && item.memory_usage !== undefined) {
                    const memColor = item.memory_usage > 80 ? 'danger' : 
                                   item.memory_usage > 60 ? 'warning' : 'success';
                    content += ` <span class="badge badge-${memColor}">RAM: ${parseFloat(item.memory_usage).toFixed(1)}%</span>`;
                }
                
                content += `
                </div>`;
            }
            
            // Add Enhanced Interface Monitoring Section for SNMP enabled devices
            if (item.snmp_enabled == 1 || item.ip_address) {
                let interfaceInfo = '';
                
                // Interface status removed as requested
                
                // Show interface speed if available
                if (item.interface_speed_mbps !== null && item.interface_speed_mbps !== undefined) {
                    const speedMbps = parseFloat(item.interface_speed_mbps);
                    const speedFormatted = speedMbps >= 1000 ? 
                        `${(speedMbps/1000).toFixed(1)} Gbps` : 
                        `${speedMbps} Mbps`;
                    interfaceInfo += `<div class="mb-1"><span class="badge badge-info"><i class="fas fa-tachometer-alt"></i> ${speedFormatted}</span></div>`;
                }
                
                // Show network traffic if available
                if (item.bytes_in_total !== null && item.bytes_out_total !== null) {
                    const totalBytes = parseFloat(item.bytes_in_total || 0) + parseFloat(item.bytes_out_total || 0);
                    if (totalBytes > 0) {
                        const trafficFormatted = formatBytesHelper(totalBytes);
                        interfaceInfo += `<div class="mb-1"><span class="badge badge-secondary"><i class="fas fa-exchange-alt"></i> Total: ${trafficFormatted}</span></div>`;
                    }
                }
                
                content += `
                <div class="info-row mt-2">
                    ${interfaceInfo ? `<div class="interface-status mb-2">${interfaceInfo}</div>` : ''}
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-info" onclick="showInterfaceDetails(${item.id})" id="btnInterfaces${item.id}">
                            <i class="fas fa-network-wired"></i> Interfaces
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="discoverInterfaces(${item.id})" id="btnDiscover${item.id}">
                            <i class="fas fa-search"></i> Discover
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="openSNMPDashboard(${item.id})" id="btnSNMP${item.id}">
                            <i class="fas fa-chart-line"></i> SNMP
                        </button>
                    </div>
                    <small class="d-block text-muted mt-1">Enhanced interface monitoring dengan SNMP metrics</small>
                </div>
                <div id="interfaceData${item.id}" class="interface-data mt-2" style="display: none;">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Loading interface data...
                    </div>
                </div>`;
            }
        }
        
        // For OLT, show PON configuration summary
        if (isOLT && item.pon_config) {
            try {
                const ponConfig = JSON.parse(item.pon_config);
                if (ponConfig && ponConfig.length > 0) {
                    const totalVlans = ponConfig.reduce((sum, pon) => sum + (pon.vlans ? pon.vlans.length : 0), 0);
                    content += `
                        <div class="info-row">
                            <span class="info-label">PON Ports:</span> 
                            <span class="badge badge-info">${ponConfig.length} configured</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total VLANs:</span> 
                            <span class="badge badge-secondary">${totalVlans} VLANs</span>
                        </div>`;
                }
            } catch (e) {
                console.warn('Failed to parse PON config in popup:', e);
            }
        }
        
        // For Access Point, show additional device info only
        if (isAccessPoint) {
            content += `
                <div class="info-row">
                    <span class="info-label">Device Type:</span> 
                    <span class="badge badge-info">Wireless Access Point</span>
                </div>`;
        }
        
        // For ONT and Access Point, show upstream interface info
        if (item.item_type_id == 6 || item.item_type_id == 8) { // ONT or Access Point
            // Show upstream interface if available
            if (item.upstream_interface_id) {
                content += `
                    <div class="info-row">
                        <span class="info-label">Upstream Server:</span>
                        <span class="text-info" id="upstreamInfo_${item.id}">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </span>
                    </div>`;
                // Load upstream interface info asynchronously
                loadUpstreamInterfaceInfo(item.id, item.upstream_interface_id);
            }
        }
    } else {
        // Show network fields for infrastructure items
        content += `
                <div class="info-row">
                    <span class="info-label">Warna Tube:</span> ${tubeColorName}
                </div>
                ${item.core_used ? `<div class="info-row"><span class="info-label">Core Digunakan:</span> ${item.core_used}</div>` : ''}
                <div class="info-row">
                    <span class="info-label">Splitter Utama:</span> ${splitterMain}
                </div>
                <div class="info-row">
                    <span class="info-label">Splitter ODP:</span> ${splitterOdp}
                </div>`;
        
        // For ODC, show detailed ODC information
        if (isODC) {
            const odcTypeName = 'ODC Pole Mounted';
            const odcTypeColor = item.item_type_id == 4 ? 'info' : 'warning';
            
            content += `
                <div class="info-row">
                    <span class="info-label">Jenis ODC:</span> 
                    <span class="badge badge-${odcTypeColor}">${odcTypeName}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tipe ODC:</span> 
                    <span class="badge badge-info">${item.odc_type === 'pole_mounted' ? 'Pole Mounted' : 'Ground Mounted'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tipe Instalasi:</span> 
                    <span class="badge badge-secondary">${item.odc_installation_type || 'Pole'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Main Splitter:</span> 
                    <span class="badge badge-primary">${item.odc_main_splitter_ratio || '1:4'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">ODP Splitter:</span> 
                    <span class="badge badge-warning">${item.odc_odp_splitter_ratio || '1:8'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Input Ports:</span> 
                    <span class="badge badge-success">${item.odc_input_ports || 1}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Output Ports:</span> 
                    <span class="badge badge-info">${item.odc_output_ports || 4}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kapasitas Customer:</span> 
                    <span class="badge badge-danger">${item.odc_capacity || 32}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ports Used:</span> 
                    <span class="badge badge-secondary">${item.odc_ports_used || 0}</span>
                </div>`;
            
            if (item.odc_pon_connection) {
                content += `
                <div class="info-row">
                    <span class="info-label">PON Connection:</span> 
                    <span class="badge badge-primary">${item.odc_pon_connection}</span>
                </div>`;
            }
            
            if (item.attenuation_notes) {
                content += `
                <div class="info-row">
                    <span class="info-label">Catatan Redaman:</span>
                    <div class="text-small text-muted" style="margin-top: 2px; padding: 5px; background: #f8f9fa; border-radius: 3px;">
                        ${item.attenuation_notes.replace(/\n/g, '<br>')}
                    </div>
                </div>`;
            }
        }
    }
    
    content += `
                <div class="info-row">
                    <span class="info-label">Status:</span> 
                    <span class="badge badge-${getStatusBadgeClass(item.status)}">${getStatusText(item.status)}</span>
                </div>
            </div>`;
    
    // Add special buttons for monitoring items (but not ODC)
    if (isMonitoringItem && !isODC && item.ip_address) {
        content += `
            <div class="monitoring-actions mb-2">`;
        
        // For OLT (item_type_id = 1) - show OLT Management buttons
        if (item.item_type_id == 1) {
            content += `<div class="btn-group w-100 mb-1" role="group">`;
            
            if (item.port_http && item.port_http != 0) {
                content += `<button class="btn btn-sm btn-primary" onclick="openHttpPage('${item.ip_address}', ${item.port_http}, false)" title="OLT Management Interface">
                            <i class="fas fa-network-wired"></i> OLT Mgmt
                        </button>`;
            }
            
            if (item.port_https && item.port_https != 0 && item.port_https != 22) {
                content += `<button class="btn btn-sm btn-success" onclick="openHttpPage('${item.ip_address}', ${item.port_https}, true)" title="OLT HTTPS Access">
                        <i class="fas fa-shield-alt"></i> HTTPS
                    </button>`;
            }
            
            content += `<button class="btn btn-sm btn-info" onclick="pingItem(${item.id})" title="Ping OLT">
                            <i class="fas fa-satellite-dish"></i> Ping
                        </button>
                    </div>`;
        // Access Point (item_type_id = 8) now uses same buttons as ONT below
        } else if (item.item_type_id == 7) {
            // For Server/Router (item_type_id = 7) - show Management and HTTPS buttons
            content += `<div class="btn-group w-100 mb-1" role="group">`;
            
            if (item.port_http && item.port_http != 0) {
                content += `<button class="btn btn-sm btn-primary" onclick="openHttpPage('${item.ip_address}', ${item.port_http}, false)" title="Management Interface">
                            <i class="fas fa-cog"></i> Management
                        </button>`;
            }
            
            if (item.port_https && item.port_https != 0 && item.port_https != 22) {
                content += `<button class="btn btn-sm btn-success" onclick="openHttpPage('${item.ip_address}', ${item.port_https}, true)" title="HTTPS Access">
                        <i class="fas fa-lock"></i> HTTPS
                    </button>`;
            }
            
            content += `<button class="btn btn-sm btn-info" onclick="pingItem(${item.id})" title="Ping Test">
                            <i class="fas fa-wifi"></i> Ping
                        </button>
                    </div>`;
        } else {
            // For ONT (6), Access Point (8) and Pelanggan - show HTTP, HTTPS, and Ping buttons
            content += `<div class="btn-group w-100" role="group">`;
            
            if (item.port_http && item.port_http != 0) {
                content += `<button class="btn btn-sm btn-primary" onclick="openHttpPage('${item.ip_address}', ${item.port_http}, false)" title="Buka HTTP">
                            <i class="fas fa-external-link-alt"></i> HTTP
                        </button>`;
            }
            
            if (item.port_https && item.port_https != 0 && item.port_https != 22) {
                content += `<button class="btn btn-sm btn-success" onclick="openHttpPage('${item.ip_address}', ${item.port_https}, true)" title="Buka HTTPS">
                            <i class="fas fa-external-link-alt"></i> HTTPS
                        </button>`;
            }
            
            content += `<button class="btn btn-sm btn-info" onclick="pingItem(${item.id})" title="Ping Manual">
                            <i class="fas fa-wifi"></i> Ping
                        </button>
                    </div>`;
        }
        
        content += `</div>`;
    }
    
    content += `
            <div class="popup-actions">
                <button class="btn btn-info btn-sm" onclick="showItemDetail(${item.id})" title="Lihat Detail Lengkap">
                    <i class="fas fa-info-circle"></i> Detail
                </button>`;
    
    // Special handling for Tiang Tumpu and HTB - no edit button
    if (item.item_type_id != 2 && item.item_type_id != 10) {
        content += `
                <button class="btn btn-primary btn-sm" onclick="editItem(${item.id})" title="Edit Item">
                    <i class="fas fa-edit"></i> Edit
                </button>`;
    } else {
        const itemTypeName = item.item_type_id == 2 ? 'Tiang Tumpu' : 'HTB';
        content += `
                <button class="btn btn-secondary btn-sm" disabled title="${itemTypeName} tidak dapat diedit">
                    <i class="fas fa-lock"></i> Tidak Dapat Diedit
                </button>`;
    }
    
    // Show routing buttons for ALL items (infrastructure, ODC, and monitoring items)
    content += `
                <button class="btn btn-success btn-sm" onclick="startRoadRouting(${item.id})" title="Buat Routing Mengikuti Jalan">
                    <i class="fas fa-route"></i> Route Jalan
                </button>
                <button class="btn btn-warning btn-sm" onclick="startStraightLineRouting(${item.id})" title="Buat Routing Garis Lurus">
                    <i class="fas fa-minus"></i> Garis Lurus
                </button>`;
    
    content += `
                <button class="btn btn-danger btn-sm" onclick="deleteItem(${item.id})" title="Hapus Item">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </div>
        </div>
    `;
    
    return content;
}

// Get item icon based on type (moved to bottom for global export)

// Get status badge class
function getStatusBadgeClass(status) {
    switch(status) {
        case 'active': return 'success';
        case 'inactive': return 'secondary';
        case 'maintenance': return 'warning';
        default: return 'primary';
    }
}

// Get status text
function getStatusText(status) {
    switch(status) {
        // Item status
        case 'active': return 'Aktif';
        case 'inactive': return 'Tidak Aktif';
        case 'maintenance': return 'Maintenance';
        // Route status
        case 'planned': return 'Perencanaan';
        case 'installed': return 'Terpasang';
        case 'maintenance': return 'Maintenance';
        default: return status || 'Unknown';
    }
}

// Clear all existing markers from map
function clearAllMarkers() {
    Object.keys(markers).forEach(itemId => {
        const marker = markers[itemId];
        if (marker) {
            // Stop any ongoing animations
            if (marker.pingAnimationInterval) {
                clearInterval(marker.pingAnimationInterval);
                marker.pingAnimationInterval = null;
            }
            
            // Remove marker from map
            map.removeLayer(marker);
        }
    });
    
    // Clear markers object
    markers = {};
    console.log('🧹 All markers cleared from map');
}

// Refresh single marker data from server
function refreshMarker(itemId) {
    console.log(`🔄 Refreshing marker data for item ${itemId}`);
    
    $.ajax({
        url: 'api/items.php',
        method: 'GET',
        data: { id: itemId },
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response && response.success && response.data) {
                updateMarker(itemId, response.data);
                console.log(`✅ Refreshed marker for item ${itemId}`);
            } else {
                console.warn(`⚠️ Failed to refresh marker for item ${itemId}:`, response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error(`❌ Error refreshing marker for item ${itemId}:`, error);
        }
    });
}

// Load items from database
function loadItems(retryCount = 0) {
    // Prevent simultaneous loads
    if (isLoadingItems) {
        console.log('⏳ Already loading items, skipping duplicate request');
        return;
    }
    
    console.log('🔄 Loading items... (attempt ' + (retryCount + 1) + ')');
    isLoadingItems = true;
    
    // Clear existing markers first to prevent duplicates
    clearAllMarkers();
    
    $.ajax({
        url: 'api/items.php',
        method: 'GET',
        dataType: 'json',
        // Note: xhrFields removed to avoid conflict with global ajaxSetup
        beforeSend: function(xhr) {
            console.log('📤 Sending request to api/items.php');
            console.log('Session cookie:', document.cookie);
        },
        success: function(response, status, xhr) {
            isLoadingItems = false; // Reset loading flag
            
            console.log('📥 Items API Response:', response);
            console.log('Response status:', xhr.status);
            
            if (response && response.success) {
                console.log('✅ Items loaded successfully:', response.data.length, 'items');
                response.data.forEach(function(item) {
                    addMarkerToMap(item);
                });
                updateStatistics();
            } else {
                console.error('❌ Load items API error:', response ? response.message : 'No response');
                showNotification('Error loading items: ' + (response ? response.message : 'Invalid response'), 'error');
            }
        },
        error: function(xhr, status, error) {
            isLoadingItems = false; // Reset loading flag
            
            console.error('💥 Load items AJAX error:');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response Text:', xhr.responseText);
            console.error('Status Code:', xhr.status);
            console.error('Response Headers:', xhr.getAllResponseHeaders());
            
            // Retry up to 2 times for 401 errors (session issues)
            if (xhr.status === 401 && retryCount < 2) {
                console.warn('🔄 Retrying loadItems due to auth error...');
                setTimeout(function() {
                    loadItems(retryCount + 1);
                }, 1000 * (retryCount + 1)); // Exponential backoff
                return;
            }
            
            let errorMessage = 'Error loading items';
            if (xhr.status === 401) {
                errorMessage = 'Authentication required - please login again';
                console.warn('🔒 Authentication failed, redirecting to login...');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000);
            } else if (xhr.status === 0) {
                errorMessage = 'Network error - cannot connect to server';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = 'Error loading items: ' + xhr.responseJSON.message;
            } else {
                errorMessage = `Error loading items (Status: ${xhr.status})`;
            }
            
            // Only show notification on final attempt
            if (retryCount >= 2 || xhr.status !== 401) {
                showNotification(errorMessage, 'error');
            }
        },
        complete: function() {
            // Start continuous ping animations for all monitoring markers after items are loaded
            setTimeout(() => {
                restartAllMonitoringAnimations();
            }, 2000);
        }
    });
}

// Add marker to map
function addMarkerToMap(item) {
    // Check if marker already exists to prevent duplicates
    if (markers[item.id]) {
        console.warn(`⚠️ Marker for item ${item.id} already exists, skipping duplicate`);
        return;
    }
    
    let itemTypeColors = {
        'OLT': '#FF6B6B',
        'Tiang Tumpu': '#4ECDC4',
        'Tiang ODP': '#45B7D1',
        'Tiang ODC': '#96CEB4',
        'Tiang Joint Closure': '#E74C3C',
        'Pelanggan': '#FFA500',
        'Server': '#8E44AD',
        'ODC': '#F39C12',
        'Access Point': '#3498DB'
    };
    
    let color = itemTypeColors[item.item_type_name] || '#999';
    
    // Check if this is a monitoring item (has IP address)
    const isMonitoringItem = item.ip_address && (item.item_type_id == 1 || item.item_type_id == 6 || item.item_type_id == 7 || item.item_type_id == 8); // OLT, Pelanggan, Server, Access Point
    
    let icon = createCustomIcon(item.item_type_name, color, isMonitoringItem, item);
    
    let marker = L.marker([item.latitude, item.longitude], {
        icon: icon,
        draggable: true,
        itemType: item.item_type_name,
        itemId: item.id,
        itemData: item,
        isMonitoring: isMonitoringItem
    }).addTo(map);
    
    marker.bindPopup(createPopupContent(item));
    
    // Add drag event with debouncing to prevent multiple calls
    let dragTimeout;
    marker.on('dragend', function(e) {
        // Clear any pending drag updates
        if (dragTimeout) {
            clearTimeout(dragTimeout);
        }
        
        // Debounce the drag update to prevent multiple calls
        dragTimeout = setTimeout(() => {
            let newPos = e.target.getLatLng();
            updateItemPosition(item.id, newPos.lat, newPos.lng);
        }, 300); // 300ms debounce
    });
    
    // Add click event for routing mode
    marker.on('click', function(e) {
        if (isRoutingMode) {
            handleRoutingClick(item);
            e.originalEvent.stopPropagation();
        }
    });
    
    // Add real-time ping animation for monitoring items
    if (isMonitoringItem) {
        setupMarkerPingAnimation(marker, item);
    }
    
    markers[item.id] = marker;
}

// Update item position
function updateItemPosition(itemId, lat, lng) {
    // Use FormData with method override for consistency
    let formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('id', itemId);
    formData.append('latitude', lat);
    formData.append('longitude', lng);
    
    $.ajax({
        url: 'api/items.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response && response.success) {
                showNotification('Posisi item berhasil dipindahkan', 'success');
                
                // Update marker data with new position (no need to reload all items)
                const marker = markers[itemId];
                if (marker && marker.options.itemData) {
                    marker.options.itemData.latitude = lat;
                    marker.options.itemData.longitude = lng;
                    console.log(`📍 Updated position for item ${itemId}: ${lat}, ${lng}`);
                }
                
                // Update routes connected to this item
                updateRoutesConnectedToItem(itemId, lat, lng);
            } else {
                showNotification(response?.message || 'Error updating position', 'error');
                
                // Revert marker position if update failed
                const marker = markers[itemId];
                if (marker && marker.options.itemData) {
                    const originalLat = marker.options.itemData.latitude;
                    const originalLng = marker.options.itemData.longitude;
                    marker.setLatLng([originalLat, originalLng]);
                    console.log(`↩️ Reverted marker position for item ${itemId}`);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Position update error:', error, xhr.responseText);
            showNotification('Error updating position: ' + error, 'error');
        }
    });
}

// Update routes connected to a moved item
function updateRoutesConnectedToItem(movedItemId, newLat, newLng) {
    console.log(`🔄 Updating routes connected to item ${movedItemId} with new position [${newLat}, ${newLng}]`);
    console.log(`📍 Total routes on map: ${Object.keys(routes).length}`);
    
    let updatedRoutesCount = 0;
    let roadRoutesToRecalculate = [];
    
    // Check all existing routes
    Object.keys(routes).forEach(routeId => {
        const routeLine = routes[routeId];
        
        if (routeLine && routeLine.routeData) {
            const routeData = routeLine.routeData;
            const routeType = routeData.route_type || 'straight';
            let isConnected = false;
            let isFromItem = false;
            let isToItem = false;
            
            console.log(`🔍 Checking route ${routeId}: type=${routeType}, status=${routeData.status}`);
            console.log(`🔍 Route from_item_id: ${routeData.from_item_id}, to_item_id: ${routeData.to_item_id}`);
            console.log(`🔍 Moved item ID: ${movedItemId}`);
            
            // Check if this route is connected to the moved item
            if (routeData.from_item_id == movedItemId) {
                isConnected = true;
                isFromItem = true;
                console.log(`📍 Route ${routeId}: Connected as from_item (${routeType})`);
            }
            
            if (routeData.to_item_id == movedItemId) {
                isConnected = true;
                isToItem = true;
                console.log(`📍 Route ${routeId}: Connected as to_item (${routeType})`);
            }
            
            if (isConnected) {
                console.log(`✅ Route ${routeId} is connected to moved item ${movedItemId}`);
                console.log(`📍 Route type: ${routeType}, Status: ${routeData.status}`);
                
                if (routeType === 'road') {
                    // For road routes, queue for recalculation using routing engine
                    roadRoutesToRecalculate.push({
                        routeId: routeId,
                        routeLine: routeLine,
                        routeData: routeData,
                        isFromItem: isFromItem,
                        isToItem: isToItem,
                        newLat: newLat,
                        newLng: newLng
                    });
                    console.log(`🛣️ Route ${routeId}: Queued for road recalculation`);
                } else {
                    // For straight lines, simply update endpoints
                    console.log(`📏 Route ${routeId}: Updating straight line endpoints (status: ${routeData.status})`);
                    updateStraightRouteEndpoints(routeId, routeLine, routeData, isFromItem, isToItem, newLat, newLng);
                    updatedRoutesCount++;
                    console.log(`✅ Route ${routeId}: Updated straight line endpoints`);
                }
            } else {
                console.log(`❌ Route ${routeId}: Not connected to moved item ${movedItemId}`);
            }
        } else {
            console.warn(`⚠️ Route ${routeId}: Missing routeData`);
        }
    });
    
    // Process road route recalculations
    if (roadRoutesToRecalculate.length > 0) {
        console.log(`🛣️ Recalculating ${roadRoutesToRecalculate.length} road routes...`);
        
        roadRoutesToRecalculate.forEach((routeInfo, index) => {
            // Add slight delay to prevent overwhelming the routing service
            setTimeout(() => {
                recalculateRoadRoute(routeInfo);
            }, index * 500); // 500ms delay between requests
        });
        
        updatedRoutesCount += roadRoutesToRecalculate.length;
    }
    
    if (updatedRoutesCount > 0) {
        console.log(`🔄 Updated ${updatedRoutesCount} routes connected to item ${movedItemId}`);
        showNotification(`${updatedRoutesCount} routes diperbarui mengikuti item`, 'info');
    } else {
        console.log(`ℹ️ No routes connected to item ${movedItemId}`);
    }
}

// Update straight route endpoints
function updateStraightRouteEndpoints(routeId, routeLine, routeData, isFromItem, isToItem, newLat, newLng) {
    console.log(`🔧 Updating straight route endpoints for route ${routeId}`);
    console.log(`📍 Route status: ${routeData.status}, Route type: ${routeData.route_type}`);
    console.log(`📍 Is from item: ${isFromItem}, Is to item: ${isToItem}`);
    console.log(`📍 New position: [${newLat}, ${newLng}]`);
    console.log(`📍 Current coordinates:`, routeData.coordinates);
    
    // Test before update
    console.log(`🧪 Before update test:`);
    testRouteUpdate(routeId);
    
    let newCoordinates = [...routeData.coordinates]; // Copy existing coordinates
    
    if (isFromItem) {
        // Update start point
        newCoordinates[0] = [newLat, newLng];
        console.log(`📍 Updated start point to [${newLat}, ${newLng}]`);
    }
    
    if (isToItem) {
        // Update end point
        const lastIndex = newCoordinates.length - 1;
        newCoordinates[lastIndex] = [newLat, newLng];
        console.log(`📍 Updated end point to [${newLat}, ${newLng}]`);
    }
    
    console.log(`📍 New coordinates:`, newCoordinates);
    
    // Update route line visual
    routeLine.setLatLngs(newCoordinates);
    console.log(`✅ Route line visual updated`);
    
    // Verify visual update
    const updatedLatLngs = routeLine.getLatLngs();
    console.log(`📍 Updated route line coordinates:`, updatedLatLngs);
    
    // Update route data
    routeData.coordinates = newCoordinates;
    routeLine.routeData = routeData;
    console.log(`✅ Route data updated`);
    
    // Recalculate distance
    const newDistance = calculateRouteDistance(newCoordinates);
    routeData.distance = newDistance;
    console.log(`📍 New distance: ${newDistance} meters`);
    
    // Update popup content with new distance
    updateRoutePopupContent(routeLine, routeData, routeId);
    console.log(`✅ Popup content updated`);
    
    // Test after update
    console.log(`🧪 After update test:`);
    testRouteUpdate(routeId);
    
    console.log(`✅ Straight route ${routeId} endpoints updated successfully`);
}

// Recalculate road route using routing engine
function recalculateRoadRoute(routeInfo) {
    const { routeId, routeLine, routeData, isFromItem, isToItem, newLat, newLng } = routeInfo;
    
    // Get current endpoint positions
    let fromLat, fromLng, toLat, toLng;
    
    if (isFromItem) {
        fromLat = newLat;
        fromLng = newLng;
        // Get to item position from marker or route data
        const toMarker = markers[routeData.to_item_id];
        if (toMarker) {
            const toPos = toMarker.getLatLng();
            toLat = toPos.lat;
            toLng = toPos.lng;
        } else {
            // Fallback to last coordinate in route
            const lastCoord = routeData.coordinates[routeData.coordinates.length - 1];
            toLat = lastCoord[0];
            toLng = lastCoord[1];
        }
    } else if (isToItem) {
        toLat = newLat;
        toLng = newLng;
        // Get from item position from marker or route data
        const fromMarker = markers[routeData.from_item_id];
        if (fromMarker) {
            const fromPos = fromMarker.getLatLng();
            fromLat = fromPos.lat;
            fromLng = fromPos.lng;
        } else {
            // Fallback to first coordinate in route
            const firstCoord = routeData.coordinates[0];
            fromLat = firstCoord[0];
            fromLng = firstCoord[1];
        }
    }
    
    console.log(`🛣️ Recalculating road route ${routeId} from [${fromLat}, ${fromLng}] to [${toLat}, ${toLng}]`);
    
    // Check if Leaflet Routing Machine is available
    if (typeof L.Routing === 'undefined' || typeof L.Routing.control === 'undefined') {
        console.warn('⚠️ Leaflet Routing Machine not available, falling back to straight line for route', routeId);
        updateStraightRouteEndpoints(routeId, routeLine, routeData, isFromItem, isToItem, newLat, newLng);
        return;
    }
    
    try {
        // Use routing machine to recalculate route
        let routing = L.Routing.control({
            waypoints: [
                L.latLng(fromLat, fromLng),
                L.latLng(toLat, toLng)
            ],
            routeWhileDragging: false,
            show: false,
            createMarker: function() { return null; }, // Don't create markers
            lineOptions: {
                styles: [{ opacity: 0 }] // Invisible route line, we'll create our own
            }
        });
        
        routing.on('routesfound', function(e) {
            const route = e.routes[0];
            const newCoordinates = route.coordinates.map(coord => [coord.lat, coord.lng]);
            
            console.log(`✅ Road route ${routeId} recalculated with ${newCoordinates.length} waypoints`);
            
            // Update route line visual
            routeLine.setLatLngs(newCoordinates);
            
            // Update route data
            routeData.coordinates = newCoordinates;
            routeData.distance = route.summary.totalDistance;
            routeLine.routeData = routeData;
            
            // Update popup content
            updateRoutePopupContent(routeLine, routeData, routeId);
            
            // Remove temporary routing control
            map.removeControl(routing);
            
            console.log(`🔄 Road route ${routeId} successfully updated`);
        });
        
        routing.on('routingerror', function(e) {
            console.error(`❌ Road route ${routeId} recalculation error:`, e.error);
            
            // Fallback to straight line update
            console.log(`↩️ Falling back to straight line for route ${routeId}`);
            updateStraightRouteEndpoints(routeId, routeLine, routeData, isFromItem, isToItem, newLat, newLng);
            
            // Remove temporary routing control
            map.removeControl(routing);
        });
        
        // Add routing control temporarily (hidden)
        routing.addTo(map);
        
    } catch (error) {
        console.error(`❌ Error recalculating road route ${routeId}:`, error);
        
        // Fallback to straight line update
        console.log(`↩️ Falling back to straight line for route ${routeId}`);
        updateStraightRouteEndpoints(routeId, routeLine, routeData, isFromItem, isToItem, newLat, newLng);
    }
}

// Update route popup content helper
function updateRoutePopupContent(routeLine, routeData, routeId) {
    const distanceKm = (routeData.distance / 1000).toFixed(2);
    const routeTypeLabel = getRouteTypeLabel(routeData.route_type);
    
    const popupContent = `
        <div>
            <h6><i class="fas fa-route"></i> Route Kabel (${routeTypeLabel})</h6>
            <div class="route-info">
                <p><strong>Dari:</strong> ${routeData.from_item_name}</p>
                <p><strong>Ke:</strong> ${routeData.to_item_name}</p>
                <p><strong>Jarak:</strong> ${distanceKm} km</p>
                <p><strong>Tipe Kabel:</strong> ${routeData.cable_type || 'Fiber Optic'}</p>
                <p><strong>Jumlah Core:</strong> ${routeData.core_count || 24}</p>
                <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(routeData.status)}">${getStatusText(routeData.status)}</span></p>
            </div>
            <div class="route-actions mt-2">
                <button class="btn btn-sm btn-info" onclick="focusOnRoute(${routeId})" title="Focus pada route ini">
                    <i class="fas fa-search"></i> Focus
                </button>
                <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${routeId})" title="Simpan ke database">
                    <i class="fas fa-save"></i> Update
                </button>
                <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${routeId})" title="Hapus route">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </div>
        </div>`;
    
    routeLine.setPopupContent(popupContent);
}

// Get route type label helper
function getRouteTypeLabel(routeType) {
    switch (routeType) {
        case 'road': return 'Jalur Jalan';
        case 'straight': return 'Garis Lurus';
        case 'direct': return 'Direct';
        default: return 'Unknown';
    }
}

// Calculate route distance from coordinates
function calculateRouteDistance(coordinates) {
    if (!coordinates || coordinates.length < 2) {
        return 0;
    }
    
    let totalDistance = 0;
    
    for (let i = 0; i < coordinates.length - 1; i++) {
        const point1 = coordinates[i];
        const point2 = coordinates[i + 1];
        
        // Calculate distance between two points using Haversine formula
        const R = 6371000; // Earth's radius in meters
        const lat1 = point1[0] * Math.PI / 180;
        const lat2 = point2[0] * Math.PI / 180;
        const deltaLat = (point2[0] - point1[0]) * Math.PI / 180;
        const deltaLng = (point2[1] - point1[1]) * Math.PI / 180;
        
        const a = Math.sin(deltaLat/2) * Math.sin(deltaLat/2) +
                  Math.cos(lat1) * Math.cos(lat2) *
                  Math.sin(deltaLng/2) * Math.sin(deltaLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        
        totalDistance += R * c;
    }
    
    return totalDistance;
}

// Update route coordinates in database
function updateRouteInDatabase(routeId) {
    const routeLine = routes[routeId];
    
    if (!routeLine || !routeLine.routeData) {
        console.error(`❌ Route ${routeId} data not found`);
        showNotification('Route data tidak ditemukan', 'error');
        return;
    }
    
    const routeData = routeLine.routeData;
    
    console.log(`💾 Updating route ${routeId} in database...`);
    
    $.ajax({
        url: 'api/routes.php',
        method: 'PUT',
        data: {
            id: routeId,
            route_coordinates: JSON.stringify(routeData.coordinates),
            distance: routeData.distance
        },
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success) {
                showNotification('Route berhasil disimpan ke database', 'success');
                console.log(`✅ Route ${routeId} updated in database`);
            } else {
                showNotification('Error menyimpan route: ' + (response.message || 'Unknown error'), 'error');
                console.error(`❌ Route ${routeId} update failed:`, response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error(`❌ Route ${routeId} database update error:`, error);
            showNotification('Error updating route in database: ' + error, 'error');
        }
    });
}

// Start routing mode
function startRouting(itemId) {
    showRoutingTypeModal(itemId);
}

// Show routing type selection modal
function showRoutingTypeModal(itemId) {
    let modalHtml = `
        <div class="row">
            <div class="col-md-12">
                <p class="mb-4">Pilih jenis routing yang ingin digunakan untuk membuat jalur kabel:</p>
                
                <div class="routing-options">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-route fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Routing Jalan</h5>
                                    <p class="card-text">Membuat jalur mengikuti jalan dan rute yang tersedia. Cocok untuk instalasi yang mengikuti infrastruktur jalan.</p>
                                    <button class="btn btn-primary btn-block" onclick="startRoadRouting(${itemId})">
                                        <i class="fas fa-road"></i> Gunakan Routing Jalan
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-arrows-alt fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Garis Lurus</h5>
                                    <p class="card-text">Membuat jalur garis lurus langsung antar titik. Cocok untuk instalasi udara atau jalur khusus.</p>
                                    <button class="btn btn-success btn-block" onclick="startStraightLineRouting(${itemId})">
                                        <i class="fas fa-minus"></i> Gunakan Garis Lurus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Create modal if doesn't exist
    if (!$('#routingTypeModal').length) {
        $('body').append(`
            <div class="modal fade" id="routingTypeModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">
                                <i class="fas fa-route"></i> Pilih Jenis Routing
                            </h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="routingTypeModalBody">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    $('#routingTypeModalBody').html(modalHtml);
    $('#routingTypeModal').modal('show');
}

// Start road routing mode
function startRoadRouting(itemId) {
    isRoutingMode = true;
    routingFromItem = itemId;
    routingType = 'road'; // Set routing type
    map.getContainer().style.cursor = 'crosshair';
    $('#routingTypeModal').modal('hide');
                        showNotification('Pilih item tujuan untuk membuat route mengikuti jalan', 'info');
    showRoutingOptionsModal(itemId);
}

// Start straight line routing mode
function startStraightLineRouting(itemId) {
    isRoutingMode = true;
    routingFromItem = itemId;
    routingType = 'straight'; // Set routing type
    map.getContainer().style.cursor = 'crosshair';
    $('#routingTypeModal').modal('hide');
                        showNotification('Pilih item tujuan untuk membuat route garis lurus', 'info');
    showRoutingOptionsModal(itemId);
}

// Handle routing click
function handleRoutingClick(toItem) {
    if (routingFromItem && routingFromItem !== toItem.id) {
        createRoute(routingFromItem, toItem.id);
        exitRoutingMode();
    }
}

// Exit routing mode
function exitRoutingMode() {
    isRoutingMode = false;
    routingFromItem = null;
    routingType = 'road'; // Reset to default
    map.getContainer().style.cursor = '';
}

// Create route between two items
function createRoute(fromItemId, toItemId) {
    let fromMarker = markers[fromItemId];
    let toMarker = markers[toItemId];
    
    if (!fromMarker || !toMarker) {
        showNotification('Marker tidak ditemukan', 'error');
        return;
    }
    
    let fromPos = fromMarker.getLatLng();
    let toPos = toMarker.getLatLng();
    
    console.log('Creating route from', fromPos, 'to', toPos, 'using', routingType, 'routing');
    
    // If straight line routing is selected, create simple route directly
    if (routingType === 'straight') {
        createSimpleRoute(fromItemId, toItemId, fromPos, toPos);
        return;
    }
    
    // Check if Leaflet Routing Machine is available for road routing
    if (typeof L.Routing === 'undefined') {
        console.log('Leaflet Routing Machine not available, falling back to simple line');
        createSimpleRoute(fromItemId, toItemId, fromPos, toPos);
        return;
    }
    
    try {
        // Use routing machine to create route following roads
        let routing = L.Routing.control({
            waypoints: [fromPos, toPos],
            routeWhileDragging: false,
            show: false,
            createMarker: function() { return null; }, // Don't create default markers
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: false
        });
        
        routing.on('routesfound', function(e) {
            console.log('Route found:', e.routes[0]);
            let route = e.routes[0];
            let coordinates = route.coordinates;
            
            // Save route to database
            $.ajax({
                url: 'api/routes.php',
                method: 'POST',
                        data: {
            from_item_id: fromItemId,
            to_item_id: toItemId,
            route_coordinates: JSON.stringify(coordinates),
            distance: route.summary.totalDistance,
            cable_type: 'Fiber Optic',
            core_count: 24,
            route_type: 'road',
            status: 'planned'
        },
                success: function(response) {
                    console.log('Route save response:', response);
                    if (response.success) {
                        // Generate tiang tumpu if option is enabled
                        if (window.autoGenerateTiangTumpu) {
                            generateTiangTumpuForRoute(response.route_id, coordinates, route.summary.totalDistance);
                        }
                        
                        // Add route line to map
                        let routeLine = L.polyline(coordinates, {
                            color: '#ffc107',
                            weight: 4,
                            opacity: 0.8,
                            dashArray: '10, 5'
                        }).addTo(map);
                        
                        // Store route data for future updates
                        routeLine.routeData = {
                            id: response.route_id,
                            from_item_id: fromItemId,
                            to_item_id: toItemId,
                            from_item_name: markers[fromItemId]?.options?.itemData?.name || 'Unknown',
                            to_item_name: markers[toItemId]?.options?.itemData?.name || 'Unknown',
                            coordinates: coordinates,
                            distance: route.summary.totalDistance,
                            cable_type: 'Fiber Optic',
                            core_count: 24,
                            route_type: 'road',
                            status: 'planned'
                        };
                        
                        // Add popup to route
                        routeLine.bindPopup(`
                            <div>
                                <h6>Route Kabel</h6>
                                <p><strong>Jarak:</strong> ${(route.summary.totalDistance / 1000).toFixed(2)} km</p>
                                <p><strong>Tipe Kabel:</strong> Fiber Optic</p>
                                <p><strong>Jumlah Core:</strong> 24</p>
                                <p><strong>Status:</strong> Perencanaan</p>
                                <div class="route-actions mt-2">
                                    <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${response.route_id})" title="Simpan perubahan ke database">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                    <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${response.route_id})" title="Hapus route">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        `);
                        
                        routes[response.route_id] = routeLine;
                        showNotification('Route berhasil dibuat', 'success');
                        
                        // Remove routing control
                        map.removeControl(routing);
                    } else {
                        showNotification(response.message || 'Gagal menyimpan route', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error saving route:', error);
                    showNotification('Error menyimpan route: ' + error, 'error');
                }
            });
        });
        
        routing.on('routingerror', function(e) {
            console.error('Routing error:', e.error);
            showNotification('Error routing: ' + e.error.message, 'error');
            // Fallback to simple line
            createSimpleRoute(fromItemId, toItemId, fromPos, toPos);
        });
        
        routing.addTo(map);
        
    } catch (error) {
        console.error('Error creating route:', error);
        showNotification('Error creating route, using simple line', 'warning');
        createSimpleRoute(fromItemId, toItemId, fromPos, toPos);
    }
}

// Create simple straight line route (fallback or intentional)
function createSimpleRoute(fromItemId, toItemId, fromPos, toPos) {
    let coordinates = [[fromPos.lat, fromPos.lng], [toPos.lat, toPos.lng]];
    let distance = fromPos.distanceTo(toPos);
    let isIntentionalStraight = routingType === 'straight';
    
    $.ajax({
        url: 'api/routes.php',
        method: 'POST',
        data: {
            from_item_id: fromItemId,
            to_item_id: toItemId,
            route_coordinates: JSON.stringify(coordinates),
            distance: distance,
            cable_type: 'Fiber Optic',
            core_count: 24,
            route_type: isIntentionalStraight ? 'straight' : 'direct',
            status: 'planned'
        },
        success: function(response) {
            if (response.success) {
                // Different styling for intentional straight line vs fallback
                let routeStyle = {
                    weight: 4,
                    opacity: 0.8
                };
                
                if (isIntentionalStraight) {
                    // Solid green line for intentional straight routing
                    routeStyle.color = '#28a745';
                    routeStyle.dashArray = null;
                } else {
                    // Dashed yellow line for fallback routing
                    routeStyle.color = '#ffc107';
                    routeStyle.dashArray = '10, 5';
                }
                
                let routeLine = L.polyline(coordinates, routeStyle).addTo(map);
                
                // Store route data for future updates
                routeLine.routeData = {
                    id: response.route_id,
                    from_item_id: fromItemId,
                    to_item_id: toItemId,
                    from_item_name: markers[fromItemId]?.options?.itemData?.name || 'Unknown',
                    to_item_name: markers[toItemId]?.options?.itemData?.name || 'Unknown',
                    coordinates: coordinates,
                    distance: distance,
                    cable_type: 'Fiber Optic',
                    core_count: 24,
                    route_type: isIntentionalStraight ? 'straight' : 'direct',
                    status: 'planned'
                };
                
                let routeTypeLabel = isIntentionalStraight ? 'Garis Lurus' : 'Direct (Fallback)';
                let routeDescription = isIntentionalStraight ? 
                    'Jalur garis lurus langsung antar titik' : 
                    'Jalur sederhana (routing jalan tidak tersedia)';
                
                routeLine.bindPopup(`
                    <div>
                        <h6>Route Kabel (${routeTypeLabel})</h6>
                        <p class="text-muted small">${routeDescription}</p>
                        <p><strong>Jarak:</strong> ${(distance / 1000).toFixed(2)} km</p>
                        <p><strong>Tipe Kabel:</strong> Fiber Optic</p>
                        <p><strong>Jumlah Core:</strong> 24</p>
                        <p><strong>Status:</strong> Perencanaan</p>
                        <div class="route-actions mt-2">
                            <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${response.route_id})" title="Simpan perubahan ke database">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${response.route_id})" title="Hapus route">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>
                `);
                
                routes[response.route_id] = routeLine;
                
                let successMessage = isIntentionalStraight ? 
                    'Route garis lurus berhasil dibuat' : 
                    'Route sederhana berhasil dibuat';
                showNotification(successMessage, 'success');
            }
        },
        error: function() {
            showNotification('Error menyimpan route', 'error');
        }
    });
}

// Clear all existing routes from map
function clearAllRoutes() {
    Object.keys(routes).forEach(routeId => {
        const route = routes[routeId];
        if (route) {
            map.removeLayer(route);
        }
    });
    routes = {};
    console.log('🧹 All routes cleared from map');
}

// Load routes from database
function loadRoutes() {
    console.log('📍 Loading routes from database...');
    
    // Clear existing routes first
    clearAllRoutes();
    
    $.ajax({
        url: 'api/routes.php',
        method: 'GET',
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            console.log('📍 Routes API response:', response);
            
            if (response.success) {
                console.log(`📍 Found ${response.data.length} routes in database`);
                
                response.data.forEach(function(route) {
                    console.log(`📍 Processing route ${route.id}: ${route.from_item_name} -> ${route.to_item_name}`);
                    console.log(`📍 Route coordinates:`, route.route_coordinates);
                    console.log(`📍 Route status:`, route.status);
                    
                    let routeAdded = false;
                    
                    // Try to load route with coordinates first
                    if (route.route_coordinates) {
                        try {
                            let coordinates = JSON.parse(route.route_coordinates);
                            console.log(`📍 Route ${route.id} parsed coordinates:`, coordinates);
                            
                            // Validate coordinates format
                            if (Array.isArray(coordinates) && coordinates.length > 0) {
                                // Check if coordinates are in correct format [lat, lng]
                                const firstCoord = coordinates[0];
                                if (Array.isArray(firstCoord) && firstCoord.length >= 2) {
                                    let color = getRouteColor(route.status);
                                    let dashArray = route.status === 'installed' ? null : '10, 5';
                                    
                                    console.log(`📍 Creating polyline for route ${route.id} with color ${color} and status ${route.status}`);
                                    
                                    let routeLine = L.polyline(coordinates, {
                                        color: color,
                                        weight: 4,
                                        opacity: 0.8,
                                        dashArray: dashArray
                                    }).addTo(map);
                                    
                                    // Store route data in the polyline object for updates
                                    routeLine.routeData = {
                                        id: route.id,
                                        from_item_id: route.from_item_id,
                                        to_item_id: route.to_item_id,
                                        from_item_name: route.from_item_name,
                                        to_item_name: route.to_item_name,
                                        coordinates: coordinates,
                                        distance: route.distance,
                                        cable_type: route.cable_type,
                                        core_count: route.core_count,
                                        route_type: route.route_type || 'straight',
                                        status: route.status
                                    };
                                    
                                    const distanceKm = (route.distance / 1000).toFixed(2);
                                    const popupContent = `
                                        <div>
                                            <h6><i class="fas fa-route"></i> Route Kabel ${route.route_type === 'road' ? '(Jalur Jalan)' : '(Garis Lurus)'}</h6>
                                            <div class="route-info">
                                                <p><strong>Dari:</strong> ${route.from_item_name}</p>
                                                <p><strong>Ke:</strong> ${route.to_item_name}</p>
                                                <p><strong>Jarak:</strong> ${distanceKm} km</p>
                                                <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                                                <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                                                <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
                                            </div>
                                            <div class="route-actions mt-2">
                                                <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                                                    <i class="fas fa-search"></i> Focus
                                                </button>
                                                <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                                                    <i class="fas fa-save"></i> Update
                                                </button>
                                                <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </div>`;
                                    
                                    routeLine.bindPopup(popupContent);
                                    routes[route.id] = routeLine;
                                    
                                    console.log(`✅ Route ${route.id} successfully added to map with coordinates`);
                                    routeAdded = true;
                                } else {
                                    console.warn(`⚠️ Route ${route.id} coordinates not in [lat, lng] format:`, firstCoord);
                                }
                            } else {
                                console.warn(`⚠️ Route ${route.id} has invalid coordinates format:`, coordinates);
                            }
                        } catch (e) {
                            console.error(`❌ Error parsing coordinates for route ${route.id}:`, e);
                            console.error(`❌ Route coordinates:`, route.route_coordinates);
                        }
                    }
                    
                    // Fallback: Create route based on type if no coordinates or parsing failed
                    if (!routeAdded) {
                        console.log(`🔄 Creating fallback route for route ${route.id} with type: ${route.route_type || 'straight'}`);
                        
                        // Get item coordinates
                        const fromLat = parseFloat(route.from_lat);
                        const fromLng = parseFloat(route.from_lng);
                        const toLat = parseFloat(route.to_lat);
                        const toLng = parseFloat(route.to_lng);
                        
                        if (fromLat && fromLng && toLat && toLng) {
                            let coordinates;
                            let routeTypeText;
                            
                            // Different handling based on route type
                            if (route.route_type === 'road') {
                                // For road routes, use Leaflet Routing Machine to follow actual roads
                                console.log(`📍 Creating road route fallback for route ${route.id} using routing engine`);
                                
                                // Check if Leaflet Routing Machine is available
                                if (typeof L.Routing !== 'undefined' && typeof L.Routing.control !== 'undefined') {
                                    // Use routing machine to create route following roads
                                    let routing = L.Routing.control({
                                        waypoints: [
                                            L.latLng(fromLat, fromLng),
                                            L.latLng(toLat, toLng)
                                        ],
                                        routeWhileDragging: false,
                                        show: false,
                                        createMarker: function() { return null; }, // Don't create markers
                                        lineOptions: {
                                            styles: [{ opacity: 0 }] // Invisible route line, we'll create our own
                                        }
                                    });
                                    
                                    routing.on('routesfound', function(e) {
                                        const routeData = e.routes[0];
                                        const roadCoordinates = routeData.coordinates.map(coord => [coord.lat, coord.lng]);
                                        
                                        console.log(`✅ Road route ${route.id} created with ${roadCoordinates.length} waypoints using routing engine`);
                                        
                                        let color = getRouteColor(route.status);
                                        let dashArray = route.status === 'installed' ? null : '10, 5';
                                        
                                        let routeLine = L.polyline(roadCoordinates, {
                                            color: color,
                                            weight: 4,
                                            opacity: 0.8,
                                            dashArray: dashArray
                                        }).addTo(map);
                                        
                                        // Store route data with original route_type
                                        routeLine.routeData = {
                                            id: route.id,
                                            from_item_id: route.from_item_id,
                                            to_item_id: route.to_item_id,
                                            from_item_name: route.from_item_name,
                                            to_item_name: route.to_item_name,
                                            coordinates: roadCoordinates,
                                            distance: routeData.summary.totalDistance,
                                            cable_type: route.cable_type,
                                            core_count: route.core_count,
                                            route_type: route.route_type || 'road',
                                            status: route.status
                                        };
                                        
                                        const distanceKm = (routeData.summary.totalDistance / 1000).toFixed(2);
                                        const popupContent = `
                                            <div>
                                                <h6><i class="fas fa-route"></i> Route Kabel (Jalur Jalan)</h6>
                                                <div class="route-info">
                                                    <p><strong>Dari:</strong> ${route.from_item_name}</p>
                                                    <p><strong>Ke:</strong> ${route.to_item_name}</p>
                                                    <p><strong>Jarak:</strong> ${distanceKm} km</p>
                                                    <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                                                    <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                                                    <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
                                                </div>
                                                <div class="route-actions mt-2">
                                                    <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                                                        <i class="fas fa-search"></i> Focus
                                                    </button>
                                                    <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                                                        <i class="fas fa-save"></i> Update
                                                    </button>
                                                    <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </button>
                                                </div>
                                            </div>`;
                                        
                                        routeLine.bindPopup(popupContent);
                                        routes[route.id] = routeLine;
                                        
                                        console.log(`✅ Route ${route.id} successfully added to map with routing engine (${route.route_type})`);
                                        
                                        // Remove temporary routing control
                                        map.removeControl(routing);
                                    });
                                    
                                    routing.on('routingerror', function(e) {
                                        console.error(`❌ Road route ${route.id} routing error:`, e.error);
                                        console.log(`↩️ Falling back to multi-point route for route ${route.id}`);
                                        
                                        // Fallback to multi-point route
                                        createMultiPointRoadRoute(route, fromLat, fromLng, toLat, toLng);
                                        
                                        // Remove temporary routing control
                                        map.removeControl(routing);
                                    });
                                    
                                    // Add routing control temporarily (hidden)
                                    routing.addTo(map);
                                    
                                } else {
                                    console.warn('⚠️ Leaflet Routing Machine not available, using multi-point fallback for route', route.id);
                                    // Fallback to multi-point route
                                    createMultiPointRoadRoute(route, fromLat, fromLng, toLat, toLng);
                                }
                                
                                routeTypeText = '(Jalur Jalan)';
                            } else {
                                // For straight routes, use direct line
                                coordinates = [[fromLat, fromLng], [toLat, toLng]];
                                routeTypeText = '(Garis Lurus)';
                                console.log(`📍 Creating straight line fallback for route ${route.id}`);
                                
                                let color = getRouteColor(route.status);
                                let dashArray = route.status === 'installed' ? null : '10, 5';
                                
                                console.log(`📍 Creating fallback polyline for route ${route.id} with coordinates:`, coordinates);
                                
                                let routeLine = L.polyline(coordinates, {
                                    color: color,
                                    weight: 4,
                                    opacity: 0.8,
                                    dashArray: dashArray
                                }).addTo(map);
                                
                                // Store route data with original route_type
                                routeLine.routeData = {
                                    id: route.id,
                                    from_item_id: route.from_item_id,
                                    to_item_id: route.to_item_id,
                                    from_item_name: route.from_item_name,
                                    to_item_name: route.to_item_name,
                                    coordinates: coordinates,
                                    distance: route.distance,
                                    cable_type: route.cable_type,
                                    core_count: route.core_count,
                                    route_type: route.route_type || 'straight', // Preserve original route type
                                    status: route.status
                                };
                                
                                const distanceKm = (route.distance / 1000).toFixed(2);
                                const popupContent = `
                                    <div>
                                        <h6><i class="fas fa-route"></i> Route Kabel ${routeTypeText}</h6>
                                        <div class="route-info">
                                            <p><strong>Dari:</strong> ${route.from_item_name}</p>
                                            <p><strong>Ke:</strong> ${route.to_item_name}</p>
                                            <p><strong>Jarak:</strong> ${distanceKm} km</p>
                                            <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                                            <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                                            <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
                                        </div>
                                        <div class="route-actions mt-2">
                                            <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                                                <i class="fas fa-search"></i> Focus
                                            </button>
                                            <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                                                <i class="fas fa-save"></i> Update
                                            </button>
                                            <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </div>
                                    </div>`;
                                
                                routeLine.bindPopup(popupContent);
                                routes[route.id] = routeLine;
                                
                                console.log(`✅ Route ${route.id} successfully added to map with fallback (${route.route_type || 'straight'})`);
                            }
                        } else {
                            console.error(`❌ Cannot create fallback for route ${route.id}: Missing item coordinates`);
                            console.error(`❌ From: ${fromLat}, ${fromLng} | To: ${toLat}, ${toLng}`);
                        }
                    }
                });
                
                const loadedCount = Object.keys(routes).length;
                console.log(`✅ Successfully loaded ${loadedCount} routes to map`);
                
                if (loadedCount > 0) {
                    showNotification(`${loadedCount} routes dimuat di peta`, 'success');
                }
                
            } else {
                console.error('❌ Load routes API error:', response.message);
                showNotification('Error loading routes: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Load routes AJAX error:', error, xhr.responseText);
            
            if (xhr.status === 401) {
                console.warn('⚠️ Routes loading failed: Authentication required');
                showNotification('Routes tidak dapat dimuat: Authentication required', 'warning');
            } else {
                console.error('❌ Routes loading failed:', status, error);
                showNotification('Error loading routes: ' + error, 'error');
            }
        }
    });
}

// Get route color based on status
function getRouteColor(status) {
    switch(status) {
        case 'installed': return '#28a745';   // Green
        case 'planned': return '#ffc107';     // Yellow
        case 'maintenance': return '#dc3545'; // Red
        default: return '#6c757d';           // Gray
    }
}

// Get route status class for badges
function getRouteStatusClass(status) {
    switch(status) {
        case 'installed': return 'success';
        case 'planned': return 'warning';
        case 'maintenance': return 'danger';
        default: return 'secondary';
    }
}

// Focus on specific route
function focusOnRoute(routeId) {
    const route = routes[routeId];
    if (route) {
        const bounds = route.getBounds();
        map.fitBounds(bounds, { padding: [20, 20] });
        
        // Briefly highlight the route
        const originalColor = route.options.color;
        route.setStyle({ color: '#ff0000', weight: 6 });
        
        setTimeout(() => {
            route.setStyle({ color: originalColor, weight: 4 });
        }, 2000);
        
        // Open popup if closed
        setTimeout(() => {
            route.openPopup();
        }, 500);
        
        console.log(`🔍 Focused on route ${routeId}`);
    } else {
        console.warn(`⚠️ Route ${routeId} not found on map`);
        showNotification('Route tidak ditemukan di peta', 'warning');
    }
}

// Delete route
function deleteRoute(routeId) {
    if (confirm('Apakah Anda yakin ingin menghapus route ini?')) {
        $.ajax({
            url: 'api/routes.php',
            method: 'DELETE',
            data: { id: routeId },
            dataType: 'json',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success) {
                    // Remove route from map
                    const route = routes[routeId];
                    if (route) {
                        map.removeLayer(route);
                        delete routes[routeId];
                    }
                    
                    showNotification('Route berhasil dihapus', 'success');
                    console.log(`✅ Route ${routeId} deleted successfully`);
                } else {
                    showNotification('Error menghapus route: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Delete route error:', error);
                showNotification('Error menghapus route: ' + error, 'error');
            }
        });
    }
}

// Refresh routes manually
function refreshRoutes() {
    console.log('🔄 Manually refreshing routes...');
    loadRoutes();
}

// Show/hide all routes
function toggleRoutes() {
    const routeKeys = Object.keys(routes);
    
    if (routeKeys.length === 0) {
        showNotification('Tidak ada routes untuk ditampilkan', 'info');
        return;
    }
    
    const firstRoute = routes[routeKeys[0]];
    const isVisible = map.hasLayer(firstRoute);
    
    routeKeys.forEach(routeId => {
        const route = routes[routeId];
        if (route) {
            if (isVisible) {
                map.removeLayer(route);
            } else {
                map.addLayer(route);
            }
        }
    });
    
    const action = isVisible ? 'disembunyikan' : 'ditampilkan';
    showNotification(`Routes ${action}`, 'info');
    console.log(`👁️ Routes ${action}`);
}

// Add map legend
function addMapLegend() {
    let legend = L.control({position: 'bottomleft'});
    
    legend.onAdd = function(map) {
        let div = L.DomUtil.create('div', 'map-legend');
        div.innerHTML = `
            <h6 style="margin-bottom: 10px; font-weight: bold;">Legend</h6>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #FF6B6B;"><i class="fas fa-server" style="color: white; font-size: 10px;"></i></div>
                <span>OLT</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #4ECDC4;"><i class="fas fa-tower-broadcast" style="color: white; font-size: 10px;"></i></div>
                <span>Tiang Tumpu</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #45B7D1;"><i class="fas fa-project-diagram" style="color: white; font-size: 10px;"></i></div>
                <span>Tiang ODP</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #96CEB4;"><i class="fas fa-network-wired" style="color: white; font-size: 10px;"></i></div>
                <span>Tiang ODC</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #E74C3C;"><i class="fas fa-link" style="color: white; font-size: 10px;"></i></div>
                <span>Joint Closure</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #8E44AD;"><i class="fas fa-server" style="color: white; font-size: 10px;"></i></div>
                <span>Server/Router</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #FFA500;"><i class="fas fa-home" style="color: white; font-size: 10px;"></i></div>
                <span>ONT</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: #FF6B9D;"><i class="fas fa-home" style="color: white; font-size: 10px;"></i></div>
                <span>HTB</span>
            </div>

            <hr style="margin: 10px 0;">
            <div style="font-size: 12px;">
                <div><span style="border-bottom: 3px solid #28a745; padding-bottom: 1px;">━━━</span> Terpasang</div>
                <div><span style="border-bottom: 3px dashed #ffc107; padding-bottom: 1px;">┅┅┅</span> Perencanaan</div>
                <div><span style="border-bottom: 3px dashed #dc3545; padding-bottom: 1px;">┅┅┅</span> Maintenance</div>
                <hr style="margin: 8px 0;">
                <div style="font-size: 11px; color: #666;">
                    <div><span style="border-bottom: 2px solid #28a745; padding-bottom: 1px;">━━</span> Garis Lurus</div>
                    <div><span style="border-bottom: 2px solid #007bff; padding-bottom: 1px;">━━</span> Routing Jalan</div>
                </div>
            </div>
        `;
        return div;
    };
    
    legend.addTo(map);
}

// Update statistics
function updateStatistics() {
    $.ajax({
        url: 'api/statistics.php',
        method: 'GET',
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success) {
                console.log('Statistics data received:', response.data);
                
                // Update statistics cards with proper fallbacks
                $('#stat-server').text(response.data.server || 0);
                $('#stat-olt').text(response.data.olt || 0);
                $('#stat-tiang').text(response.data.tiang_tumpu || 0);
                $('#stat-odp').text(response.data.tiang_odp || 0);
                $('#stat-odc').text(response.data.tiang_odc || 0);
                $('#stat-ont').text(response.data.ont || 0);
                $('#stat-routes').text(response.data.total_routes || 0);
                $('#stat-joint-closure').text(response.data.tiang_joint_closure || 0);
                $('#stat-htb').text(response.data.htb || 0);
                $('#stat-access-point').text(response.data.access_point || 0);
                $('#stat-total-items').text(response.data.total_items || 0);
                
                console.log('Statistics updated successfully');
            } else {
                console.error('Statistics API error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Statistics AJAX error:', error);
            if (xhr.status === 401) {
                console.warn('Statistics loading failed: Authentication required');
            }
        }
    });
}

// Show notification
function showNotification(message, type) {
    let alertClass = 'alert-info';
    switch(type) {
        case 'success': alertClass = 'alert-success'; break;
        case 'error': alertClass = 'alert-danger'; break;
        case 'warning': alertClass = 'alert-warning'; break;
    }
    
    let notification = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    $('body').append(notification);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

// Show routing mode
function showRoutingMode() {
    if (isRoutingMode) {
        exitRoutingMode();
        showNotification('Mode routing dinonaktifkan', 'info');
    } else {
        isRoutingMode = true;
        map.getContainer().style.cursor = 'crosshair';
        showNotification('Mode routing aktif. Klik dua item untuk membuat route.', 'info');
    }
}

// Zoom to specific bounds
function zoomToItems() {
    if (Object.keys(markers).length > 0) {
        const group = new L.featureGroup(Object.values(markers));
        map.fitBounds(group.getBounds().pad(0.1));
    } else {
        showNotification('Tidak ada item untuk di-zoom', 'warning');
    }
}

// Zoom to specific item type
function zoomToItemType(itemType) {
    const filteredMarkers = Object.values(markers).filter(marker => {
        return marker.options && marker.options.itemType === itemType;
    });
    
    if (filteredMarkers.length > 0) {
        const group = new L.featureGroup(filteredMarkers);
        map.fitBounds(group.getBounds().pad(0.1));
        
        // Highlight markers of this type temporarily
        filteredMarkers.forEach(marker => {
            if (marker._icon) {
                marker._icon.style.transform += ' scale(1.3)';
                marker._icon.style.zIndex = '1000';
                setTimeout(() => {
                    marker._icon.style.transform = marker._icon.style.transform.replace(' scale(1.3)', '');
                    marker._icon.style.zIndex = '';
                }, 2000);
            }
        });
        
        showNotification(`Menampilkan ${filteredMarkers.length} ${itemType}`, 'success');
    } else {
        showNotification(`Tidak ada ${itemType} ditemukan`, 'info');
    }
}

// Enhanced locate user function
function locateUser() {
    if (navigator.geolocation) {
        map.locate({
            setView: true,
            maxZoom: 16,
            enableHighAccuracy: true,
            timeout: 10000
        });
        
        map.on('locationfound', function(e) {
            L.circle(e.latlng, e.accuracy).addTo(map)
                .bindPopup('Anda berada di sekitar area ini').openPopup();
            showNotification('Lokasi berhasil ditemukan', 'success');
        });
        
        map.on('locationerror', function(e) {
            showNotification('Gagal menemukan lokasi: ' + e.message, 'error');
        });
    } else {
        showNotification('Geolocation tidak didukung browser ini', 'error');
    }
}

// Add keyboard shortcuts for zoom
function addKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea') {
            return; // Don't interfere with form inputs
        }
        
        switch(e.key) {
            case '+':
            case '=':
                map.zoomIn();
                break;
            case '-':
                map.zoomOut();
                break;
            case 'h':
            case 'H':
                map.setView([-2.5, 118], 5); // Home to Indonesia
                break;
            case 'f':
            case 'F':
                if (map.isFullscreen && map.isFullscreen()) {
                    map.toggleFullscreen();
                } else if (map.toggleFullscreen) {
                    map.toggleFullscreen();
                }
                break;
            case 'l':
            case 'L':
                locateUser();
                break;
            case 'a':
            case 'A':
                zoomToItems();
                break;
        }
    });
}

// Enhanced map ready function
function onMapReady() {
    addKeyboardShortcuts();
    
    // Add help tooltip
    const helpControl = L.control({position: 'bottomright'});
    helpControl.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'leaflet-control-help');
        div.innerHTML = '<i class="fas fa-question-circle" title="Shortcuts: +/- zoom, H home, F fullscreen, L locate, A zoom to all"></i>';
        div.style.background = 'rgba(255,255,255,0.8)';
        div.style.padding = '5px';
        div.style.borderRadius = '3px';
        div.style.cursor = 'help';
        return div;
    };
    helpControl.addTo(map);
    
    console.log('🎮 Map keyboard shortcuts enabled: +/- zoom, H home, F fullscreen, L locate, A zoom to all');
}

// Helper functions for monitoring
function getMonitoringStatusColor(status) {
    switch(status) {
        case 'online': return '#28a745'; // Green
        case 'warning': return '#ffc107'; // Yellow  
        case 'offline': return '#dc3545'; // Red
        default: return '#6c757d'; // Gray
    }
}

function getMonitoringStatusText(status) {
    switch(status) {
        case 'online': return 'Online';
        case 'warning': return 'Warning';
        case 'offline': return 'Offline';
        default: return 'Unknown';
    }
}

function getMonitoringStatusIcon(status) {
    switch(status) {
        case 'online': return 'fa-check-circle';
        case 'warning': return 'fa-exclamation-triangle';
        case 'offline': return 'fa-times-circle';
        default: return 'fa-question-circle';
    }
}

// Function to open HTTP/HTTPS page in new tab
function openHttpPage(ip, port, isHttps) {
    const protocol = isHttps ? 'https' : 'http';
    const url = `${protocol}://${ip}:${port}`;
    window.open(url, '_blank');
}





// Function to manually ping an item
function pingItem(itemId) {
    console.log('🏓 Manual ping for item:', itemId);
    
    // Find and animate the specific marker
    const marker = markers[itemId];
    if (marker && marker.options.isMonitoring) {
        // Start checking animation for this marker
        const markerElement = marker.getElement();
        if (markerElement) {
            const markerDiv = markerElement.querySelector('.custom-marker');
            const indicator = markerElement.querySelector('.ping-indicator');
            
            if (markerDiv) {
                markerDiv.classList.add('ping-active');
            }
            
            if (indicator) {
                indicator.classList.remove('ping-indicator-online', 'ping-indicator-offline', 'ping-indicator-warning');
                indicator.classList.add('ping-indicator-checking');
            }
        }
    }
    
    // Show loading state
    showNotification('Memulai ping...', 'info');
    
    $.ajax({
        url: 'api/monitoring.php?action=ping&id=' + itemId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const result = response.data;
                const statusColor = getMonitoringStatusColor(result.status);
                const statusText = getMonitoringStatusText(result.status);
                
                console.log(`🏓 Manual ping result for ${result.name}: ${result.status.toUpperCase()} ${result.response_time_ms ? `(${result.response_time_ms}ms)` : ''}`);
                
                // Update marker with animation
                updateMarkerPingStatus(itemId, result.status, result.response_time_ms);
                
                let message = `<strong>${result.name}</strong><br>`;
                message += `Status: <span style="color: ${statusColor};">${statusText}</span><br>`;
                if (result.response_time_ms) {
                    message += `Response Time: ${result.response_time_ms}ms`;
                }
                if (result.error_message) {
                    message += `<br>Error: ${result.error_message}`;
                }
                
                showNotification(message, result.status === 'online' ? 'success' : 'warning');
                
                // Update marker popup content instead of reloading all items
                setTimeout(() => {
                    const marker = markers[itemId];
                    if (marker && marker.options.itemData) {
                        // Update marker data
                        marker.options.itemData.monitoring_status = newStatus;
                        if (responseTime) {
                            marker.options.itemData.response_time_ms = responseTime;
                        }
                        
                        // Update popup content
                        marker.setPopupContent(createPopupContent(marker.options.itemData));
                        console.log(`🔄 Updated popup content for item ${itemId}`);
                    }
                }, 500);
                
            } else {
                console.error('❌ Manual ping failed:', response.message);
                showNotification('Gagal melakukan ping: ' + response.message, 'error');
                
                // Remove checking animation on failure
                if (marker && marker.options.isMonitoring) {
                    const markerElement = marker.getElement();
                    if (markerElement) {
                        const markerDiv = markerElement.querySelector('.custom-marker');
                        const indicator = markerElement.querySelector('.ping-indicator');
                        
                        if (markerDiv) {
                            markerDiv.classList.remove('ping-active');
                        }
                        
                        if (indicator) {
                            indicator.classList.remove('ping-indicator-checking');
                            indicator.classList.add('ping-indicator-offline');
                        }
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Manual ping error:', error);
            showNotification('Error saat ping: ' + error, 'error');
            
            // Remove checking animation on error
            if (marker && marker.options.isMonitoring) {
                const markerElement = marker.getElement();
                if (markerElement) {
                    const markerDiv = markerElement.querySelector('.custom-marker');
                    const indicator = markerElement.querySelector('.ping-indicator');
                    
                    if (markerDiv) {
                        markerDiv.classList.remove('ping-active');
                    }
                    
                    if (indicator) {
                        indicator.classList.remove('ping-indicator-checking');
                        indicator.classList.add('ping-indicator-offline');
                    }
                }
            }
        }
    });
}

// Variables for monitoring control
let monitoringInterval = null;
let monitoringCountdown = null;
let nextMonitoringTime = null;

// Function to start automatic monitoring every 5 minutes
function startMonitoring() {
    console.log('🔄 Starting automatic monitoring (every 5 minutes)...');
    
    // Clear existing interval if any
    if (monitoringInterval) {
        clearInterval(monitoringInterval);
    }
    
    // Run initial monitoring
    performMonitoring();
    
    // Set interval for every 5 minutes (300000ms)
    monitoringInterval = setInterval(function() {
        performMonitoring();
    }, 300000); // 5 minutes interval
    
    // Start countdown display
    startMonitoringCountdown();
}

// Function to perform actual monitoring
function performMonitoring() {
    console.log('🔍 Performing automatic monitoring check...');
    
    // Show monitoring indicator
    showMonitoringIndicator();
    
    // Start checking animation for all monitoring markers
    animateAllMonitoringMarkers();
    
    $.ajax({
        url: 'api/monitoring.php',
        method: 'POST',
        data: { action: 'ping_all' },
        dataType: 'json',
        beforeSend: function() {
            updateMonitoringStatus('Checking all devices...', 'info');
        },
        success: function(response) {
            if (response.success) {
                const itemCount = response.data.length;
                const onlineCount = response.data.filter(item => item.status === 'online').length;
                const offlineCount = response.data.filter(item => item.status === 'offline').length;
                const warningCount = response.data.filter(item => item.status === 'warning').length;
                
                console.log(`✅ Auto monitoring completed: ${itemCount} items checked`);
                console.log(`📊 Results: ${onlineCount} online, ${warningCount} warning, ${offlineCount} offline`);
                
                // Display detailed results in console
                console.group('📋 Detailed Monitoring Results');
                response.data.forEach(function(result) {
                    const statusIcon = result.status === 'online' ? '🟢' : 
                                     result.status === 'warning' ? '🟡' : '🔴';
                    const responseTime = result.response_time_ms ? `(${result.response_time_ms}ms)` : '';
                    console.log(`${statusIcon} ${result.name} [${result.ip_address}]: ${result.status.toUpperCase()} ${responseTime}`);
                });
                console.groupEnd();
                
                // Update status display
                updateMonitoringStatus(
                    `Monitoring completed: ${onlineCount} online, ${offlineCount} offline, ${warningCount} warning`,
                    'success'
                );
                
                // Update markers with new ping status and trigger animations
                response.data.forEach(function(result) {
                    // Update marker ping status with animation
                    updateMarkerPingStatus(result.item_id, result.status, result.response_time_ms);
                });
                
                // Update all marker popup contents instead of reloading all items
                setTimeout(() => {
                    Object.keys(markers).forEach(itemId => {
                        const marker = markers[itemId];
                        if (marker && marker.options.itemData && marker.options.itemData.ip_address) {
                            // Find result for this item
                            const itemResult = response.data.find(r => r.item_id == itemId);
                            if (itemResult) {
                                // Update marker data
                                marker.options.itemData.monitoring_status = itemResult.status;
                                if (itemResult.response_time_ms) {
                                    marker.options.itemData.response_time_ms = itemResult.response_time_ms;
                                }
                                
                                // Update popup content
                                marker.setPopupContent(createPopupContent(marker.options.itemData));
                            }
                        }
                    });
                    console.log('🔄 Updated all marker popup contents after batch ping');
                }, 500);
                
                // Update statistics
                updateStatistics();
                
            } else {
                console.error('❌ Auto monitoring failed:', response.message);
                updateMonitoringStatus('Monitoring failed: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.warn('❌ Auto monitoring error:', error);
            updateMonitoringStatus('Monitoring error: ' + error, 'error');
        },
        complete: function() {
            // Hide monitoring indicator after 3 seconds
            setTimeout(hideMonitoringIndicator, 3000);
        }
    });
    
    // Update next monitoring time
    nextMonitoringTime = new Date(Date.now() + 300000); // 5 minutes from now
}

// Function to start monitoring countdown
function startMonitoringCountdown() {
    // Clear existing countdown
    if (monitoringCountdown) {
        clearInterval(monitoringCountdown);
    }
    
    // Update countdown every second
    monitoringCountdown = setInterval(function() {
        updateMonitoringCountdown();
    }, 1000);
}

// Function to update monitoring countdown display
function updateMonitoringCountdown() {
    if (!nextMonitoringTime) return;
    
    const now = new Date();
    const timeLeft = nextMonitoringTime - now;
    
    if (timeLeft <= 0) {
        nextMonitoringTime = new Date(Date.now() + 300000); // Reset for next cycle
        return;
    }
    
    const minutes = Math.floor(timeLeft / 60000);
    const seconds = Math.floor((timeLeft % 60000) / 1000);
    
    // Update countdown in UI (if element exists)
    const countdownElement = document.getElementById('monitoring-countdown');
    if (countdownElement) {
        countdownElement.textContent = `Next check in: ${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}

// Function to show monitoring indicator
function showMonitoringIndicator() {
    // Create or update monitoring indicator
    let indicator = document.getElementById('monitoring-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'monitoring-indicator';
        indicator.style.cssText = `
            position: fixed;
            top: 70px;
            right: 20px;
            background: #17a2b8;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            z-index: 9999;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        document.body.appendChild(indicator);
    }
    
    indicator.innerHTML = `
        <i class="fas fa-wifi fa-spin"></i>
        <span>Monitoring devices...</span>
    `;
    indicator.style.display = 'flex';
}

// Function to hide monitoring indicator
function hideMonitoringIndicator() {
    const indicator = document.getElementById('monitoring-indicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
}

// Function to update monitoring status
function updateMonitoringStatus(message, type) {
    // Show notification
    showNotification(message, type);
    
    // Update status in console
    const timestamp = new Date().toLocaleTimeString();
    console.log(`[${timestamp}] Monitoring: ${message}`);
}

// Function to add monitoring controls to UI
function addMonitoringControls() {
    // Create monitoring control panel
    const controlPanel = document.createElement('div');
    controlPanel.id = 'monitoring-controls';
    controlPanel.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        z-index: 9998;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        min-width: 250px;
        font-family: 'Source Sans Pro', sans-serif;
    `;
    
    controlPanel.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <h6 style="margin: 0; color: #495057;">
                <i class="fas fa-wifi"></i> Auto Monitoring
            </h6>
            <button id="toggle-monitoring-panel" class="btn btn-sm btn-outline-secondary" style="padding: 2px 8px;">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        <div id="monitoring-panel-content">
            <div style="margin-bottom: 10px;">
                <small class="text-muted">Status:</small><br>
                <span id="monitoring-status" class="badge badge-success">Active (5 min interval)</span>
            </div>
            <div style="margin-bottom: 10px;">
                <small class="text-muted" id="monitoring-countdown">Next check in: --:--</small>
            </div>
            <div class="btn-group w-100" role="group">
                <button id="manual-monitoring" class="btn btn-sm btn-primary" onclick="performMonitoring()">
                    <i class="fas fa-sync"></i> Check Now
                </button>
                <button id="toggle-monitoring" class="btn btn-sm btn-secondary" onclick="toggleMonitoring()">
                    <i class="fas fa-pause"></i> Pause
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(controlPanel);
    
    // Add toggle functionality
    document.getElementById('toggle-monitoring-panel').addEventListener('click', function() {
        const content = document.getElementById('monitoring-panel-content');
        const icon = this.querySelector('i');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.className = 'fas fa-minus';
        } else {
            content.style.display = 'none';
            icon.className = 'fas fa-plus';
        }
    });
    
    console.log('✅ Monitoring controls added to UI');
}

// Function to toggle monitoring on/off
function toggleMonitoring() {
    const button = document.getElementById('toggle-monitoring');
    const statusBadge = document.getElementById('monitoring-status');
    
    if (monitoringInterval) {
        // Stop monitoring
        clearInterval(monitoringInterval);
        clearInterval(monitoringCountdown);
        monitoringInterval = null;
        monitoringCountdown = null;
        
        button.innerHTML = '<i class="fas fa-play"></i> Resume';
        button.className = 'btn btn-sm btn-success';
        statusBadge.textContent = 'Paused';
        statusBadge.className = 'badge badge-warning';
        
        document.getElementById('monitoring-countdown').textContent = 'Monitoring paused';
        
        console.log('⏸️ Monitoring paused');
        showNotification('Automatic monitoring paused', 'warning');
        
    } else {
        // Start monitoring
        startMonitoring();
        
        button.innerHTML = '<i class="fas fa-pause"></i> Pause';
        button.className = 'btn btn-sm btn-secondary';
        statusBadge.textContent = 'Active (5 min interval)';
        statusBadge.className = 'badge badge-success';
        
        console.log('▶️ Monitoring resumed');
        showNotification('Automatic monitoring resumed', 'success');
    }
}

// Function to manually trigger monitoring
window.performMonitoring = performMonitoring;
window.toggleMonitoring = toggleMonitoring;

// ================================
// REAL-TIME PING ANIMATION FUNCTIONS
// ================================

// Function to get ping indicator class based on status
function getPingIndicatorClass(status) {
    switch (status) {
        case 'online':
            return 'ping-indicator-online';
        case 'offline':
            return 'ping-indicator-offline';
        case 'warning':
            return 'ping-indicator-warning';
        default:
            return 'ping-indicator-checking';
    }
}

// Function to setup marker ping animation
function setupMarkerPingAnimation(marker, item) {
    // Store ping animation interval
    marker.pingAnimationInterval = null;
    
    // Start continuous ping animation based on status
    startContinuousPingAnimation(marker, item);
    
    console.log(`🎯 Setup ping animation for ${item.name} [${item.ip_address}] - Status: ${item.monitoring_status}`);
}

// Function to start continuous ping animation
function startContinuousPingAnimation(marker, item) {
    // Clear existing interval
    if (marker.pingAnimationInterval) {
        clearInterval(marker.pingAnimationInterval);
    }
    
    // Set animation interval based on status
    let animationInterval;
    switch (item.monitoring_status) {
        case 'online':
            animationInterval = 8000; // 8 seconds for online
            break;
        case 'warning':
            animationInterval = 5000; // 5 seconds for warning
            break;
        case 'offline':
            animationInterval = 12000; // 12 seconds for offline
            break;
        default:
            animationInterval = 10000; // 10 seconds default
    }
    
    // Start animation loop
    marker.pingAnimationInterval = setInterval(() => {
        triggerMarkerPingAnimation(marker, item.monitoring_status);
    }, animationInterval);
    
    // Trigger first animation immediately
    setTimeout(() => {
        triggerMarkerPingAnimation(marker, item.monitoring_status);
    }, Math.random() * 2000); // Random delay 0-2 seconds
}

// Function to trigger marker ping animation
function triggerMarkerPingAnimation(marker, status) {
    const markerElement = marker.getElement();
    if (!markerElement) return;
    
    const markerDiv = markerElement.querySelector('.custom-marker');
    if (!markerDiv) return;
    
    // Remove existing animation classes
    markerDiv.classList.remove('ping-active', 'ping-success', 'ping-failed', 'ping-warning');
    
    // Add ping-active animation for 1 second
    markerDiv.classList.add('ping-active');
    
    // Create ripple effect
    createPingRipple(marker, status);
    
    // After 1 second, add result animation based on status
    setTimeout(() => {
        markerDiv.classList.remove('ping-active');
        
        switch (status) {
            case 'online':
                markerDiv.classList.add('ping-success');
                break;
            case 'offline':
                markerDiv.classList.add('ping-failed');
                break;
            case 'warning':
                markerDiv.classList.add('ping-warning');
                break;
        }
        
        // Remove result animation after 2 seconds
        setTimeout(() => {
            markerDiv.classList.remove('ping-success', 'ping-failed', 'ping-warning');
        }, 2000);
        
    }, 1000);
}

// Function to create ping ripple effect
function createPingRipple(marker, status) {
    const markerElement = marker.getElement();
    if (!markerElement) return;
    
    // Create ripple element
    const ripple = document.createElement('div');
    const rippleClass = status === 'online' ? 'ping-ripple' : 
                      status === 'warning' ? 'ping-ripple-warning' : 'ping-ripple-fail';
    
    ripple.className = rippleClass;
    ripple.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        width: 30px;
        height: 30px;
        margin: -15px 0 0 -15px;
        border-radius: 50%;
        pointer-events: none;
        z-index: 1000;
    `;
    
    // Add to marker
    markerElement.appendChild(ripple);
    
    // Remove after animation completes
    setTimeout(() => {
        if (ripple.parentNode) {
            ripple.parentNode.removeChild(ripple);
        }
    }, 1500);
}

// Function to create ping wave effect
function createPingWave(marker, status) {
    const markerElement = marker.getElement();
    if (!markerElement) return;
    
    const wave = document.createElement('div');
    const waveClass = `ping-wave ping-wave-${status === 'online' ? 'success' : status === 'warning' ? 'warning' : 'fail'}`;
    
    wave.className = waveClass;
    wave.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        width: 35px;
        height: 35px;
        margin: -17.5px 0 0 -17.5px;
        border-radius: 50%;
        pointer-events: none;
        z-index: 999;
    `;
    
    markerElement.appendChild(wave);
    
    setTimeout(() => {
        if (wave.parentNode) {
            wave.parentNode.removeChild(wave);
        }
    }, 2000);
}

// Function to update marker ping status
function updateMarkerPingStatus(itemId, newStatus, responseTime = null) {
    const marker = markers[itemId];
    if (!marker || !marker.options.isMonitoring) return;
    
    console.log(`🔄 Updating ping status for item ${itemId}: ${newStatus} ${responseTime ? `(${responseTime}ms)` : ''}`);
    
    // Update marker data
    marker.options.itemData.monitoring_status = newStatus;
    if (responseTime) {
        marker.options.itemData.response_time_ms = responseTime;
    }
    
    // Update ping indicator
    updatePingIndicator(marker, newStatus);
    
    // Trigger immediate animation
    triggerMarkerPingAnimation(marker, newStatus);
    
    // Restart continuous animation with new status
    const item = marker.options.itemData;
    startContinuousPingAnimation(marker, item);
}

// Function to update ping indicator
function updatePingIndicator(marker, status) {
    const markerElement = marker.getElement();
    if (!markerElement) return;
    
    const indicator = markerElement.querySelector('.ping-indicator');
    if (!indicator) return;
    
    // Remove old classes
    indicator.classList.remove('ping-indicator-online', 'ping-indicator-offline', 
                             'ping-indicator-warning', 'ping-indicator-checking');
    
    // Add new class
    const newClass = getPingIndicatorClass(status);
    indicator.classList.add(newClass);
}

// Function to animate all monitoring markers during batch ping
function animateAllMonitoringMarkers() {
    Object.keys(markers).forEach(itemId => {
        const marker = markers[itemId];
        if (marker && marker.options.isMonitoring) {
            // Add checking animation
            const markerElement = marker.getElement();
            if (markerElement) {
                const markerDiv = markerElement.querySelector('.custom-marker');
                const indicator = markerElement.querySelector('.ping-indicator');
                
                if (markerDiv) {
                    markerDiv.classList.add('ping-active');
                }
                
                if (indicator) {
                    indicator.classList.remove('ping-indicator-online', 'ping-indicator-offline', 'ping-indicator-warning');
                    indicator.classList.add('ping-indicator-checking');
                }
            }
        }
    });
}

// Function to stop ping animation for marker
function stopMarkerPingAnimation(marker) {
    if (marker.pingAnimationInterval) {
        clearInterval(marker.pingAnimationInterval);
        marker.pingAnimationInterval = null;
    }
}

// Function to restart all monitoring animations
function restartAllMonitoringAnimations() {
    Object.keys(markers).forEach(itemId => {
        const marker = markers[itemId];
        if (marker && marker.options.isMonitoring) {
            const item = marker.options.itemData;
            startContinuousPingAnimation(marker, item);
        }
    });
    
    console.log('🔄 Restarted all monitoring animations');
}



// Helper functions needed by detail modal
function getItemIcon(typeName) {
    switch(typeName) {
        case 'OLT': return 'fas fa-server';
        case 'Tiang Tumpu': return 'fas fa-tower-broadcast';
        case 'Tiang ODP':
        case 'ODP': return 'fas fa-project-diagram';
        case 'Tiang ODC': return 'fas fa-network-wired';
        case 'Tiang Joint Closure': return 'fas fa-link';
        case 'ONT': return 'fas fa-home';
        case 'Server': return 'fas fa-server';
        case 'ODC': return 'fas fa-box';
        case 'Access Point': return 'fas fa-wifi';
        case 'HTB': return 'fas fa-home';

        default: return 'fas fa-circle';
    }
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'active': return 'success';
        case 'inactive': return 'secondary';
        case 'maintenance': return 'warning';
        default: return 'secondary';
    }
}

// Export functions to global scope for button access
window.zoomToItems = zoomToItems;
window.zoomToItemType = zoomToItemType;
window.locateUser = locateUser;
window.loadRoutes = loadRoutes;
window.refreshRoutes = refreshRoutes;
window.toggleRoutes = toggleRoutes;
window.focusOnRoute = focusOnRoute;
window.deleteRoute = deleteRoute;
window.clearAllRoutes = clearAllRoutes;
window.updateRouteInDatabase = updateRouteInDatabase;
window.updateRoutesConnectedToItem = updateRoutesConnectedToItem;
window.updateRouteOnMap = updateRouteOnMap;
window.forceRefreshRoute = forceRefreshRoute;
window.testRouteLoading = testRouteLoading;
window.getItemIcon = getItemIcon;
window.getStatusBadgeClass = getStatusBadgeClass;
window.getStatusText = getStatusText;
window.startRouting = startRouting;
window.startRoadRouting = startRoadRouting;
window.startStraightLineRouting = startStraightLineRouting;

// Initialize map and monitoring when document is ready
$(document).ready(function() {
    console.log('🚀 Initializing FTTH Network Monitoring System...');
    
    // Initialize map first
    initMap();
    setTimeout(onMapReady, 1000); // Wait for map to fully initialize
    
    // Start monitoring after 5 seconds to allow page to fully load
    setTimeout(function() {
        console.log('🚀 Starting FTTH Network Monitoring System...');
        console.log('⏰ Automatic monitoring interval: 5 minutes');
        console.log('🔍 Monitoring will check all devices with IP addresses');
        console.log('🎨 Real-time ping animations enabled for monitoring items');
        console.log('📡 Ping indicators: 🟢 Online, 🔴 Offline, 🟡 Warning, 🔵 Checking');
        startMonitoring();
    }, 5000);
    
    // Add monitoring controls to UI after map is ready
    setTimeout(addMonitoringControls, 6000);
    
    console.log('✅ Initialization completed');
});

// Update specific route on map after edit
function updateRouteOnMap(routeId) {
    console.log(`🔄 Updating route ${routeId} on map...`);
    console.log(`📍 Current routes on map:`, Object.keys(routes));
    
    // Get updated route data from database
    $.ajax({
        url: 'api/routes.php',
        method: 'GET',
        data: { id: routeId },
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            console.log('🔄 Route update response:', response);
            console.log('🔄 Response success:', response.success);
            console.log('🔄 Response data:', response.data);
            
            if (response.success && response.data) {
                const route = response.data;
                console.log('🔄 Route data received:', route);
                console.log('🔄 Route coordinates:', route.route_coordinates);
                console.log('🔄 Route status:', route.status);
                
                // Remove existing route from map
                if (routes[routeId]) {
                    console.log(`🗑️ Removing existing route ${routeId} from map`);
                    map.removeLayer(routes[routeId]);
                    delete routes[routeId];
                    console.log(`🗑️ Removed existing route ${routeId} from map`);
                } else {
                    console.log(`⚠️ Route ${routeId} not found in current routes object`);
                }
                
                // Re-add route with updated data
                if (route.route_coordinates) {
                    try {
                        let coordinates = JSON.parse(route.route_coordinates);
                        console.log(`📍 Route ${route.id} updated coordinates:`, coordinates);
                        console.log(`📍 Coordinates type:`, typeof coordinates);
                        console.log(`📍 Coordinates length:`, coordinates.length);
                        
                        // Validate coordinates format
                        if (!Array.isArray(coordinates) || coordinates.length === 0) {
                            console.warn(`⚠️ Route ${route.id} has invalid coordinates format`);
                            console.warn(`⚠️ Coordinates:`, coordinates);
                            return;
                        }
                        
                        // Check if coordinates are in correct format [lat, lng] or need conversion
                        const firstCoord = coordinates[0];
                        if (!Array.isArray(firstCoord) || firstCoord.length < 2) {
                            console.warn(`⚠️ Route ${route.id} coordinates not in [lat, lng] format`);
                            console.warn(`⚠️ First coordinate:`, firstCoord);
                            return;
                        }
                        
                        let color = getRouteColor(route.status);
                        let dashArray = route.status === 'installed' ? null : '10, 5';
                        
                        console.log(`📍 Recreating polyline for route ${route.id} with color ${color} and status ${route.status}`);
                        console.log(`📍 Dash array:`, dashArray);
                        
                        let routeLine = L.polyline(coordinates, {
                            color: color,
                            weight: 4,
                            opacity: 0.8,
                            dashArray: dashArray
                        });
                        
                        console.log(`📍 Polyline created:`, routeLine);
                        routeLine.addTo(map);
                        console.log(`📍 Polyline added to map`);
                        
                        // Store updated route data
                        routeLine.routeData = {
                            id: route.id,
                            from_item_id: route.from_item_id,
                            to_item_id: route.to_item_id,
                            from_item_name: route.from_item_name,
                            to_item_name: route.to_item_name,
                            coordinates: coordinates,
                            distance: route.distance,
                            cable_type: route.cable_type,
                            core_count: route.core_count,
                            route_type: route.route_type || 'straight',
                            status: route.status
                        };
                        
                        const distanceKm = (route.distance / 1000).toFixed(2);
                        const popupContent = `
                            <div>
                                <h6><i class="fas fa-route"></i> Route Kabel ${route.route_type === 'road' ? '(Jalur Jalan)' : '(Garis Lurus)'}</h6>
                                <div class="route-info">
                                    <p><strong>Dari:</strong> ${route.from_item_name}</p>
                                    <p><strong>Ke:</strong> ${route.to_item_name}</p>
                                    <p><strong>Jarak:</strong> ${distanceKm} km</p>
                                    <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                                    <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                                    <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
                                </div>
                                <div class="route-actions mt-2">
                                    <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                                        <i class="fas fa-search"></i> Focus
                                    </button>
                                    <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                    <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>`;
                        
                        routeLine.bindPopup(popupContent);
                        routes[route.id] = routeLine;
                        
                        console.log(`✅ Route ${route.id} successfully updated on map with new status: ${route.status}`);
                        console.log(`📍 Routes on map after update:`, Object.keys(routes));
                        
                        // Briefly highlight the updated route
                        const originalColor = routeLine.options.color;
                        routeLine.setStyle({ color: '#ff0000', weight: 6 });
                        
                        setTimeout(() => {
                            routeLine.setStyle({ color: originalColor, weight: 4 });
                        }, 2000);
                        
                    } catch (e) {
                        console.error(`❌ Error parsing coordinates for updated route ${route.id}:`, e);
                        console.error(`❌ Route coordinates:`, route.route_coordinates);
                    }
                } else {
                    console.warn(`⚠️ Updated route ${route.id} has no coordinates`);
                    console.warn(`⚠️ Route data:`, route);
                }
                
            } else {
                console.error('❌ Failed to get updated route data:', response.message);
                console.error('❌ Full response:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error updating route on map:', error, xhr.responseText);
            console.error('❌ XHR status:', xhr.status);
            console.error('❌ XHR statusText:', xhr.statusText);
        }
    });
}

// Force refresh route on map (fallback function)
function forceRefreshRoute(routeId) {
    console.log(`🔄 Force refreshing route ${routeId} on map...`);
    
    // First try to get route data
    $.ajax({
        url: 'api/routes.php',
        method: 'GET',
        data: { id: routeId },
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            console.log('🔄 Force refresh response:', response);
            
            if (response.success && response.data) {
                const route = response.data;
                
                // Remove route if exists
                if (routes[routeId]) {
                    map.removeLayer(routes[routeId]);
                    delete routes[routeId];
                }
                
                // Create route based on type if no coordinates
                if (!route.route_coordinates) {
                    console.log(`⚠️ Route ${routeId} has no coordinates, creating fallback with type: ${route.route_type || 'straight'}`);
                    
                    // Get item coordinates
                    const fromLat = parseFloat(route.from_lat);
                    const fromLng = parseFloat(route.from_lng);
                    const toLat = parseFloat(route.to_lat);
                    const toLng = parseFloat(route.to_lng);
                    
                    if (fromLat && fromLng && toLat && toLng) {
                        let coordinates;
                        let routeTypeText;
                        
                        // Different handling based on route type
                        if (route.route_type === 'road') {
                            // For road routes, use Leaflet Routing Machine to follow actual roads
                            console.log(`📍 Creating road route fallback for route ${route.id} using routing engine`);
                            
                            // Check if Leaflet Routing Machine is available
                            if (typeof L.Routing !== 'undefined' && typeof L.Routing.control !== 'undefined') {
                                // Use routing machine to create route following roads
                                let routing = L.Routing.control({
                                    waypoints: [
                                        L.latLng(fromLat, fromLng),
                                        L.latLng(toLat, toLng)
                                    ],
                                    routeWhileDragging: false,
                                    show: false,
                                    createMarker: function() { return null; }, // Don't create markers
                                    lineOptions: {
                                        styles: [{ opacity: 0 }] // Invisible route line, we'll create our own
                                    }
                                });
                                
                                routing.on('routesfound', function(e) {
                                    const routeData = e.routes[0];
                                    const roadCoordinates = routeData.coordinates.map(coord => [coord.lat, coord.lng]);
                                    
                                    console.log(`✅ Road route ${route.id} created with ${roadCoordinates.length} waypoints using routing engine`);
                                    
                                    let color = getRouteColor(route.status);
                                    let dashArray = route.status === 'installed' ? null : '10, 5';
                                    
                                    let routeLine = L.polyline(roadCoordinates, {
                                        color: color,
                                        weight: 4,
                                        opacity: 0.8,
                                        dashArray: dashArray
                                    }).addTo(map);
                                    
                                    // Store route data with original route_type
                                    routeLine.routeData = {
                                        id: route.id,
                                        from_item_id: route.from_item_id,
                                        to_item_id: route.to_item_id,
                                        from_item_name: route.from_item_name,
                                        to_item_name: route.to_item_name,
                                        coordinates: roadCoordinates,
                                        distance: routeData.summary.totalDistance,
                                        cable_type: route.cable_type,
                                        core_count: route.core_count,
                                        route_type: route.route_type || 'road',
                                        status: route.status
                                    };
                                    
                                    const distanceKm = (routeData.summary.totalDistance / 1000).toFixed(2);
                                    const popupContent = `
                                        <div>
                                            <h6><i class="fas fa-route"></i> Route Kabel (Jalur Jalan)</h6>
                                            <div class="route-info">
                                                <p><strong>Dari:</strong> ${route.from_item_name}</p>
                                                <p><strong>Ke:</strong> ${route.to_item_name}</p>
                                                <p><strong>Jarak:</strong> ${distanceKm} km</p>
                                                <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                                                <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                                                <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
                                            </div>
                                            <div class="route-actions mt-2">
                                                <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                                                    <i class="fas fa-search"></i> Focus
                                                </button>
                                                <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                                                    <i class="fas fa-save"></i> Update
                                                </button>
                                                <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </div>`;
                                    
                                    routeLine.bindPopup(popupContent);
                                    routes[route.id] = routeLine;
                                    
                                    console.log(`✅ Route ${route.id} successfully added to map with routing engine (${route.route_type})`);
                                    
                                    // Remove temporary routing control
                                    map.removeControl(routing);
                                });
                                
                                routing.on('routingerror', function(e) {
                                    console.error(`❌ Road route ${route.id} routing error:`, e.error);
                                    console.log(`↩️ Falling back to multi-point route for route ${route.id}`);
                                    
                                    // Fallback to multi-point route
                                    createMultiPointRoadRoute(route, fromLat, fromLng, toLat, toLng);
                                    
                                    // Remove temporary routing control
                                    map.removeControl(routing);
                                });
                                
                                // Add routing control temporarily (hidden)
                                routing.addTo(map);
                                
                            } else {
                                console.warn('⚠️ Leaflet Routing Machine not available, using multi-point fallback for route', route.id);
                                // Fallback to multi-point route
                                createMultiPointRoadRoute(route, fromLat, fromLng, toLat, toLng);
                            }
                            
                            routeTypeText = '(Jalur Jalan)';
                        } else {
                            // For straight routes, use direct line
                            coordinates = [[fromLat, fromLng], [toLat, toLng]];
                            routeTypeText = '(Garis Lurus)';
                            console.log(`📍 Creating straight line fallback for route ${route.id}`);
                            
                            let color = getRouteColor(route.status);
                            let dashArray = route.status === 'installed' ? null : '10, 5';
                            
                            console.log(`📍 Creating fallback polyline for route ${route.id} with coordinates:`, coordinates);
                            
                            let routeLine = L.polyline(coordinates, {
                                color: color,
                                weight: 4,
                                opacity: 0.8,
                                dashArray: dashArray
                            }).addTo(map);
                            
                            // Store route data with original route_type
                            routeLine.routeData = {
                                id: route.id,
                                from_item_id: route.from_item_id,
                                to_item_id: route.to_item_id,
                                from_item_name: route.from_item_name,
                                to_item_name: route.to_item_name,
                                coordinates: coordinates,
                                distance: route.distance,
                                cable_type: route.cable_type,
                                core_count: route.core_count,
                                route_type: route.route_type || 'straight', // Preserve original route type
                                status: route.status
                            };
                            
                            const distanceKm = (route.distance / 1000).toFixed(2);
                            const popupContent = `
                                <div>
                                    <h6><i class="fas fa-route"></i> Route Kabel ${routeTypeText}</h6>
                                    <div class="route-info">
                                        <p><strong>Dari:</strong> ${route.from_item_name}</p>
                                        <p><strong>Ke:</strong> ${route.to_item_name}</p>
                                        <p><strong>Jarak:</strong> ${distanceKm} km</p>
                                        <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                                        <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                                        <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
                                    </div>
                                    <div class="route-actions mt-2">
                                        <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                                            <i class="fas fa-search"></i> Focus
                                        </button>
                                        <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                                            <i class="fas fa-save"></i> Update
                                        </button>
                                        <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>`;
                            
                            routeLine.bindPopup(popupContent);
                            routes[route.id] = routeLine;
                            
                            console.log(`✅ Route ${route.id} successfully added to map with fallback (${route.route_type || 'straight'})`);
                        }
                    } else {
                        console.error(`❌ Cannot create fallback for route ${route.id}: Missing item coordinates`);
                        console.error(`❌ From: ${fromLat}, ${fromLng} | To: ${toLat}, ${toLng}`);
                    }
                }
            } else {
                // Try to parse coordinates
                try {
                    let coordinates = JSON.parse(route.route_coordinates);
                    
                    if (Array.isArray(coordinates) && coordinates.length > 0) {
                        let color = getRouteColor(route.status);
                        let dashArray = route.status === 'installed' ? null : '10, 5';
                        
                        let routeLine = L.polyline(coordinates, {
                            color: color,
                            weight: 4,
                            opacity: 0.8,
                            dashArray: dashArray
                        }).addTo(map);
                        
                        // Store route data with original route_type
                        routeLine.routeData = {
                            id: route.id,
                            from_item_id: route.from_item_id,
                            to_item_id: route.to_item_id,
                            from_item_name: route.from_item_name,
                            to_item_name: route.to_item_name,
                            coordinates: coordinates,
                            distance: route.distance,
                            cable_type: route.cable_type,
                            core_count: route.core_count,
                            route_type: route.route_type || 'straight', // Preserve original route type
                            status: route.status
                        };
                        
                        const distanceKm = (route.distance / 1000).toFixed(2);
                        const routeTypeText = route.route_type === 'road' ? '(Jalur Jalan)' : '(Garis Lurus)';
                        const popupContent = `
                            <div>
                                <h6><i class="fas fa-route"></i> Route Kabel ${routeTypeText}</h6>
                                <div class="route-info">
                                    <p><strong>Dari:</strong> ${route.from_item_name}</p>
                                    <p><strong>Ke:</strong> ${route.to_item_name}</p>
                                    <p><strong>Jarak:</strong> ${distanceKm} km</p>
                                    <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                                    <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                                    <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
                                </div>
                                <div class="route-actions mt-2">
                                    <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                                        <i class="fas fa-search"></i> Focus
                                    </button>
                                    <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                    <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>`;
                        
                        routeLine.bindPopup(popupContent);
                        routes[route.id] = routeLine;
                        
                        console.log(`✅ Route ${route.id} force refreshed successfully with type: ${route.route_type || 'straight'}`);
                    }
                } catch (e) {
                    console.error(`❌ Error parsing coordinates for force refresh:`, e);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Force refresh error:', error);
        }
    });
}

// Test function to verify route loading
function testRouteLoading() {
    console.log('🧪 Testing route loading...');
    console.log('🧪 Current routes on map:', Object.keys(routes));
    
    // Test API call
    $.ajax({
        url: 'api/routes.php',
        method: 'GET',
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            console.log('🧪 API Response:', response);
            
            if (response.success && response.data) {
                console.log(`🧪 Found ${response.data.length} routes in database`);
                
                response.data.forEach(function(route, index) {
                    console.log(`🧪 Route ${index + 1}:`, {
                        id: route.id,
                        from: route.from_item_name,
                        to: route.to_item_name,
                        status: route.status,
                        has_coordinates: !!route.route_coordinates,
                        from_lat: route.from_lat,
                        from_lng: route.from_lng,
                        to_lat: route.to_lat,
                        to_lng: route.to_lng
                    });
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('🧪 API Error:', error);
        }
    });
}

// Helper function to create multi-point road route as fallback
function createMultiPointRoadRoute(route, fromLat, fromLng, toLat, toLng) {
    console.log(`📍 Creating multi-point road route fallback for route ${route.id}`);
    
    // Create a route with multiple points to simulate road following
    const midLat = (fromLat + toLat) / 2;
    const midLng = (fromLng + toLng) / 2;
    
    // Create a route with multiple points to simulate road following
    const coordinates = [
        [fromLat, fromLng],
        [midLat + 0.0001, midLng + 0.0001], // Add slight offset to simulate road curve
        [midLat - 0.0001, midLng - 0.0001], // Add another offset
        [toLat, toLng]
    ];
    
    let color = getRouteColor(route.status);
    let dashArray = route.status === 'installed' ? null : '10, 5';
    
    let routeLine = L.polyline(coordinates, {
        color: color,
        weight: 4,
        opacity: 0.8,
        dashArray: dashArray
    }).addTo(map);
    
    // Store route data with original route_type
    routeLine.routeData = {
        id: route.id,
        from_item_id: route.from_item_id,
        to_item_id: route.to_item_id,
        from_item_name: route.from_item_name,
        to_item_name: route.to_item_name,
        coordinates: coordinates,
        distance: route.distance,
        cable_type: route.cable_type,
        core_count: route.core_count,
        route_type: route.route_type || 'road', // Preserve original route type
        status: route.status
    };
    
    const distanceKm = (route.distance / 1000).toFixed(2);
    const popupContent = `
        <div>
            <h6><i class="fas fa-route"></i> Route Kabel (Jalur Jalan)</h6>
            <div class="route-info">
                <p><strong>Dari:</strong> ${route.from_item_name}</p>
                <p><strong>Ke:</strong> ${route.to_item_name}</p>
                <p><strong>Jarak:</strong> ${distanceKm} km</p>
                <p><strong>Tipe Kabel:</strong> ${route.cable_type || 'Fiber Optic'}</p>
                <p><strong>Jumlah Core:</strong> ${route.core_count || 24}</p>
                <p><strong>Status:</strong> <span class="badge badge-${getRouteStatusClass(route.status)}">${getStatusText(route.status)}</span></p>
            </div>
            <div class="route-actions mt-2">
                <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id})" title="Focus pada route ini">
                    <i class="fas fa-search"></i> Focus
                </button>
                <button class="btn btn-sm btn-warning ml-1" onclick="updateRouteInDatabase(${route.id})" title="Simpan perubahan ke database">
                    <i class="fas fa-save"></i> Update
                </button>
                <button class="btn btn-sm btn-danger ml-1" onclick="deleteRoute(${route.id})" title="Hapus route">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </div>
        </div>`;
    
    routeLine.bindPopup(popupContent);
    routes[route.id] = routeLine;
    
    console.log(`✅ Route ${route.id} successfully added to map with multi-point fallback (${route.route_type})`);
}

// Test function untuk memverifikasi route update
function testRouteUpdate(routeId) {
    console.log(`🧪 Testing route update for route ${routeId}`);
    
    const routeLine = routes[routeId];
    if (!routeLine) {
        console.error(`❌ Route ${routeId} not found in routes object`);
        return;
    }
    
    if (!routeLine.routeData) {
        console.error(`❌ Route ${routeId} has no routeData`);
        return;
    }
    
    const routeData = routeLine.routeData;
    console.log(`📍 Route ${routeId} data:`, routeData);
    console.log(`📍 Route coordinates:`, routeData.coordinates);
    console.log(`📍 Route status: ${routeData.status}, type: ${routeData.route_type}`);
    
    // Check if route is visible on map
    const isOnMap = map.hasLayer(routeLine);
    console.log(`📍 Route visible on map: ${isOnMap}`);
    
    // Check route line coordinates
    const currentLatLngs = routeLine.getLatLngs();
    console.log(`📍 Current route line coordinates:`, currentLatLngs);
    
    // Check if coordinates match routeData
    const coordinatesMatch = JSON.stringify(currentLatLngs) === JSON.stringify(routeData.coordinates);
    console.log(`📍 Coordinates match: ${coordinatesMatch}`);
    
    return {
        routeId: routeId,
        routeData: routeData,
        isVisible: isOnMap,
        currentLatLngs: currentLatLngs,
        coordinatesMatch: coordinatesMatch
    };
}

// Enhanced update function dengan verification
function updateStraightRouteEndpoints(routeId, routeLine, routeData, isFromItem, isToItem, newLat, newLng) {
    console.log(`🔧 Updating straight route endpoints for route ${routeId}`);
    console.log(`📍 Route status: ${routeData.status}, Route type: ${routeData.route_type}`);
    console.log(`📍 Is from item: ${isFromItem}, Is to item: ${isToItem}`);
    console.log(`📍 New position: [${newLat}, ${newLng}]`);
    console.log(`📍 Current coordinates:`, routeData.coordinates);
    
    // Test before update
    console.log(`🧪 Before update test:`);
    testRouteUpdate(routeId);
    
    let newCoordinates = [...routeData.coordinates]; // Copy existing coordinates
    
    if (isFromItem) {
        // Update start point
        newCoordinates[0] = [newLat, newLng];
        console.log(`📍 Updated start point to [${newLat}, ${newLng}]`);
    }
    
    if (isToItem) {
        // Update end point
        const lastIndex = newCoordinates.length - 1;
        newCoordinates[lastIndex] = [newLat, newLng];
        console.log(`📍 Updated end point to [${newLat}, ${newLng}]`);
    }
    
    console.log(`📍 New coordinates:`, newCoordinates);
    
    // Update route line visual
    routeLine.setLatLngs(newCoordinates);
    console.log(`✅ Route line visual updated`);
    
    // Verify visual update
    const updatedLatLngs = routeLine.getLatLngs();
    console.log(`📍 Updated route line coordinates:`, updatedLatLngs);
    
    // Update route data
    routeData.coordinates = newCoordinates;
    routeLine.routeData = routeData;
    console.log(`✅ Route data updated`);
    
    // Recalculate distance
    const newDistance = calculateRouteDistance(newCoordinates);
    routeData.distance = newDistance;
    console.log(`📍 New distance: ${newDistance} meters`);
    
    // Update popup content with new distance
    updateRoutePopupContent(routeLine, routeData, routeId);
    console.log(`✅ Popup content updated`);
    
    // Test after update
    console.log(`🧪 After update test:`);
    testRouteUpdate(routeId);
    
    console.log(`✅ Straight route ${routeId} endpoints updated successfully`);
}

// ===== INTERFACE MONITORING FUNCTIONS =====

// Show interface details modal for SNMP enabled devices
function showInterfaceDetails(itemId) {
    console.log('🌐 Opening Interface Details Modal for device:', itemId);
    
    // Create interface details modal
    const modalHTML = `
        <div class="modal fade" id="interfaceDetailsModal" tabindex="-1" role="dialog" aria-labelledby="interfaceDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-info text-white">
                        <h4 class="modal-title" id="interfaceDetailsModalLabel">
                            <i class="fas fa-network-wired"></i>
                            Interface Discovery & Monitoring
                            <small class="ml-2" id="deviceIDTitle">Device ID: ${itemId}</small>
                        </h4>
                        <button type="button" class="close text-white" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="interfaceDetailsBody">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <h5 class="mt-3 text-muted">Loading interface discovery data...</h5>
                            <p class="text-muted">Please wait...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Close
                        </button>
                        <button type="button" class="btn btn-warning" onclick="discoverInterfacesFromModal(${itemId})">
                            <i class="fas fa-search"></i> Discover Interfaces
                        </button>
                        <button type="button" class="btn btn-success" onclick="refreshInterfaceData(${itemId})">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal
    $('#interfaceDetailsModal').remove();
    
    // Add modal to body
    $('body').append(modalHTML);
    
    // Show modal
    $('#interfaceDetailsModal').modal('show');
    
    // Load interface discovery data
    loadInterfaceDiscoveryModalData(itemId);
}

// Load interface discovery data into modal
function loadInterfaceDiscoveryModalData(itemId) {
    console.log('📊 Loading interface discovery data for device:', itemId);
    
    // First get device discovery stats
    $.ajax({
        url: 'api/snmp_interfaces_enhanced.php',
        method: 'GET',
        data: {
            action: 'enhanced_comprehensive',
            id: itemId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayInterfaceDiscoveryData(response.data, itemId);
            } else {
                showInterfaceError('Failed to load interface data: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Interface discovery data load error:', error);
            showInterfaceError('Connection error: Cannot load interface data');
        }
    });
}

// Display interface discovery data in modal
function displayInterfaceDiscoveryData(data, itemId) {
    console.log('🔍 Interface Discovery Data Received:', data); // Debug log
    
    const device = data.device;
    const storedInterfaces = data.stored_interfaces || [];
    const topologyConnections = data.topology_connections || [];
    const realTimeTraffic = data.real_time_traffic || [];
    
    console.log('📊 Stored Interfaces:', storedInterfaces); // Debug log
    console.log('🗺️ Topology Connections:', topologyConnections); // Debug log
    
    // Store data globally for button functions
    window.currentInterfaceData = {
        device: device,
        interfaces: storedInterfaces,
        topology: topologyConnections,
        itemId: itemId
    };
    
    // Calculate stats
    const stats = {
        discovered: storedInterfaces.length,
        stored: storedInterfaces.length,
        ip_addresses: storedInterfaces.reduce((sum, iface) => {
            // Ensure we get a number, not string concatenation
            const ipCount = parseInt(iface.ip_count) || 0;
            return sum + ipCount;
        }, 0),
        topology_links: topologyConnections.length
    };
    
    // Get latest SNMP metrics for performance display
    $.ajax({
        url: 'api/snmp.php',
        method: 'GET',
        data: {
            action: 'metrics',
            id: itemId,
            limit: 1
        },
        dataType: 'json',
        success: function(snmpResponse) {
            let performanceData = {};
            if (snmpResponse.success && snmpResponse.data && snmpResponse.data.length > 0) {
                performanceData = snmpResponse.data[0];
            }
            
            // Create interface discovery display
            const content = createInterfaceDiscoveryContent(device, stats, storedInterfaces, topologyConnections, performanceData, itemId);
            $('#interfaceDetailsBody').html(content);
        },
        error: function() {
            // Continue without SNMP performance data
            const content = createInterfaceDiscoveryContent(device, stats, storedInterfaces, topologyConnections, {}, itemId);
            $('#interfaceDetailsBody').html(content);
        }
    });
}

// Create interface discovery content
function createInterfaceDiscoveryContent(device, stats, interfaces, topology, performance, itemId) {
    const cpuUsage = performance.cpu_usage_percent ? parseFloat(performance.cpu_usage_percent).toFixed(1) : 'N/A';
    const interfaceStatus = stats.discovered > 0 ? 'UP' : 'DOWN';
    const interfaceStatusClass = stats.discovered > 0 ? 'success' : 'danger';
    const discoveryTime = interfaces.length > 0 ? interfaces[0].last_seen : 'Never';
    
    return `
        <div class="interface-discovery-container">
            <!-- Performance Header -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="performance-info">
                        <span class="performance-label">Performance:</span>
                        <span class="performance-value">CPU: ${cpuUsage}%</span>
                    </div>
                </div>
                <div class="col-md-6 text-right">
                    <span class="badge badge-${interfaceStatusClass} interface-status-badge">
                        Interface ${interfaceStatus}
                    </span>
                </div>
            </div>
            
            <!-- Discovery Status Card -->
            <div class="card discovery-status-card mb-3">
                <div class="card-body text-center">
                    <div class="discovery-icon mb-3">
                        <i class="fas fa-network-wired fa-3x text-primary"></i>
                    </div>
                    <h5 class="discovery-title">Interface Discovery Complete</h5>
                    
                    <!-- Statistics -->
                    <div class="row discovery-stats">
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <div class="stat-number text-primary">${stats.discovered}</div>
                                <div class="stat-label">Discovered</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <div class="stat-number text-success">${stats.stored}</div>
                                <div class="stat-label">Stored</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <div class="stat-number text-info">${stats.ip_addresses}</div>
                                <div class="stat-label">IP Addresses</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <div class="stat-number text-warning">${stats.topology_links}</div>
                                <div class="stat-label">Topology Links</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="discovery-actions mt-3">
                        <button class="btn btn-primary btn-lg" onclick="showDetailedInterfaceView(${itemId})">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Discovery Info -->
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Discovery completed: ${discoveryTime}
                            </small>
                        </div>
                        <div class="col-md-6 text-right">
                            <span class="badge badge-success">Status: All</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hidden detailed interface table -->
        <div id="detailedInterfaceView${itemId}" class="detailed-interface-view mt-3" style="display: none;">
            ${createDetailedInterfaceTable(interfaces, topology)}
        </div>
        
        <!-- Hidden topology visualization -->
        <div id="topologyVisualization${itemId}" class="topology-visualization mt-3" style="display: none;">
            ${createTopologyVisualization(topology, device)}
        </div>
    `;
}

// Show detailed interface view in popup window
function showDetailedInterfaceView(itemId) {
    console.log('👁️ Opening Interface Details Popup for device:', itemId);
    
    try {
        // Add visual feedback
        const button = event.target;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Opening Popup...';
        button.disabled = true;
        
        // Reset button after 3 seconds
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.disabled = false;
        }, 3000);
        
        // Get device info first
        let deviceName = 'Unknown Device';
        let deviceIP = 'Unknown IP';
        
        // Try to get device info from current data or fetch it
        if (window.currentInterfaceData && window.currentInterfaceData.itemId == itemId) {
            deviceName = window.currentInterfaceData.device.name || 'Unknown Device';
            deviceIP = window.currentInterfaceData.device.ip || 'Unknown IP';
            
            console.log('📊 Using cached data for popup');
            openInterfaceDetailsPopup(itemId, deviceName, deviceIP, window.currentInterfaceData.interfaces);
        } else {
            console.log('🔄 Loading fresh data for popup');
            
            // Load device info first
            $.ajax({
                url: 'api/items.php',
                method: 'GET',
                data: { action: 'read_single', id: itemId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        deviceName = response.data.name || 'Unknown Device';
                        deviceIP = response.data.ip_address || 'Unknown IP';
                    }
                    
                    console.log('📋 Device info loaded:', deviceName, deviceIP);
                    
                    // Then load interface data
                    loadInterfaceDetailsData(itemId, function(data) {
                        const interfaces = data ? data.interfaces : [];
                        console.log('🔌 Interface data loaded:', interfaces.length, 'interfaces');
                        openInterfaceDetailsPopup(itemId, deviceName, deviceIP, interfaces);
                    });
                },
                error: function(xhr, status, error) {
                    console.error('❌ Failed to load device info:', error);
                    
                    // Load with default info
                    loadInterfaceDetailsData(itemId, function(data) {
                        const interfaces = data ? data.interfaces : [];
                        openInterfaceDetailsPopup(itemId, deviceName, deviceIP, interfaces);
                    });
                }
            });
        }
    } catch (error) {
        console.error('❌ Error in showDetailedInterfaceView:', error);
        alert('Error opening interface details popup. Please check console for details.');
    }
}

// Open interface details in popup window
function openInterfaceDetailsPopup(itemId, deviceName, deviceIP, interfaces) {
    console.log('🪟 Attempting to open popup window for interface details');
    console.log('📋 Device:', deviceName, '(' + deviceIP + ')');
    console.log('🔌 Interfaces:', interfaces ? interfaces.length : 0);
    
    try {
        // Create popup window
        const popupWidth = 1000;
        const popupHeight = 700;
        const left = (screen.width - popupWidth) / 2;
        const top = (screen.height - popupHeight) / 2;
        
        console.log('📏 Popup dimensions:', popupWidth + 'x' + popupHeight);
        console.log('📍 Popup position:', left + ',' + top);
        
        const popupFeatures = `width=${popupWidth},height=${popupHeight},left=${left},top=${top},scrollbars=yes,resizable=yes,status=yes,toolbar=no,menubar=no`;
        
        console.log('🎯 Opening popup with features:', popupFeatures);
        
        const popup = window.open('', `interfaceDetails_${itemId}`, popupFeatures);
        
        if (!popup) {
            console.error('❌ Popup was blocked by browser');
            alert('⚠️ Popup Blocked!\n\nPlease:\n1. Allow popups for this site\n2. Disable popup blocker\n3. Try again\n\nOr check browser console for details.');
            return;
        }
        
        if (popup.closed) {
            console.error('❌ Popup was closed immediately');
            alert('❌ Popup window was closed. Please allow popups and try again.');
            return;
        }
        
        console.log('✅ Popup window created successfully');
        
        // Create HTML content for popup
        console.log('📝 Creating popup content...');
        const popupContent = createInterfaceDetailsPopupContent(itemId, deviceName, deviceIP, interfaces);
        
        // Write content to popup
        console.log('📄 Writing content to popup...');
        popup.document.open();
        popup.document.write(popupContent);
        popup.document.close();
        
        // Focus popup window
        popup.focus();
        
        console.log('✅ Interface details popup opened and focused successfully');
        
        // Test if popup is still accessible
        setTimeout(() => {
            if (popup && !popup.closed) {
                console.log('✅ Popup window is still open and accessible');
            } else {
                console.warn('⚠️ Popup window was closed or became inaccessible');
            }
        }, 1000);
        
    } catch (error) {
        console.error('❌ Error opening interface details popup:', error);
        alert('❌ Error opening popup window:\n\n' + error.message + '\n\nPlease check browser console for details.');
    }
}

// Create HTML content for interface details popup
function createInterfaceDetailsPopupContent(itemId, deviceName, deviceIP, interfaces) {
    const hasInterfaces = interfaces && interfaces.length > 0;
    
    // Calculate statistics
    const stats = {
        total: interfaces.length,
        up: interfaces.filter(i => i.oper_status === 'up').length,
        down: interfaces.filter(i => i.oper_status === 'down').length,
        withIP: interfaces.filter(i => i.ip_addresses && i.ip_addresses !== 'None').length
    };
    
    const lastUpdate = hasInterfaces ? 
        new Date(interfaces[0].last_seen || Date.now()).toLocaleString() : 
        'Never';
    
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interface Details - ${deviceName}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
        }
        .container-fluid {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 0;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h3 {
            margin: 0;
            font-weight: 600;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .stats-row {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #2196F3;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .interface-table {
            margin: 20px;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table thead th {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        .table tbody td {
            vertical-align: middle;
            border-color: #e9ecef;
        }
        .badge {
            font-size: 10px;
            padding: 4px 8px;
        }
        .status-up { background: #28a745; }
        .status-down { background: #dc3545; }
        .status-unknown { background: #6c757d; }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .no-data i {
            font-size: 3em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .refresh-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 50px;
            padding: 8px 15px;
            font-size: 12px;
        }
        .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        .interface-row:hover {
            background: #f8f9fa;
        }
        .ip-badge {
            background: #17a2b8;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            margin: 1px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header position-relative">
            <button class="refresh-btn" onclick="refreshInterfaceData()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <h3><i class="fas fa-network-wired"></i> Interface Details</h3>
            <p><strong>${deviceName}</strong> (${deviceIP})</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="row">
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-number text-primary">${stats.total}</div>
                        <div class="stat-label">Total Interfaces</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-number text-success">${stats.up}</div>
                        <div class="stat-label">UP</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-number text-danger">${stats.down}</div>
                        <div class="stat-label">DOWN</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-number text-info">${stats.withIP}</div>
                        <div class="stat-label">With IP</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Interface Table -->
        <div class="interface-table">
            ${hasInterfaces ? createInterfaceTableHTML(interfaces) : createNoDataHTML(itemId)}
        </div>
        
        <!-- Footer -->
        <div class="text-center p-3 border-top">
            <small class="text-muted">
                <i class="fas fa-clock"></i> Last Update: ${lastUpdate}
            </small>
        </div>
    </div>
    
    <script>
        function refreshInterfaceData() {
            window.location.reload();
        }
        
        // Auto refresh every 30 seconds
        setInterval(function() {
            const btn = document.querySelector('.refresh-btn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auto Refresh';
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }, 30000);
    </script>
</body>
</html>`;
}

// Create interface table HTML for popup
function createInterfaceTableHTML(interfaces) {
    let tableHTML = `
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Index</th>
                    <th>Interface</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>IP Addresses</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    interfaces.forEach(iface => {
        const statusClass = iface.oper_status === 'up' ? 'status-up' : 
                           iface.oper_status === 'down' ? 'status-down' : 'status-unknown';
        const ipAddresses = iface.ip_addresses || 'None';
        
        // Format IP addresses
        let ipDisplay = '';
        if (ipAddresses && ipAddresses !== 'None') {
            const ips = ipAddresses.split(', ');
            ipDisplay = ips.map(ip => `<span class="ip-badge">${ip.trim()}</span>`).join(' ');
        } else {
            ipDisplay = '<span class="text-muted">No IP</span>';
        }
        
        tableHTML += `
            <tr class="interface-row">
                <td><strong>${iface.interface_index}</strong></td>
                <td>
                    <i class="fas fa-ethernet text-primary"></i>
                    <strong>${iface.interface_name}</strong>
                </td>
                <td><span class="badge badge-secondary">${iface.interface_type}</span></td>
                <td>
                    <span class="badge ${statusClass}">
                        ${iface.oper_status.toUpperCase()}
                    </span>
                </td>
                <td>${ipDisplay}</td>
            </tr>
        `;
    });
    
    tableHTML += `
            </tbody>
        </table>
    `;
    
    return tableHTML;
}

// Create no data HTML for popup
function createNoDataHTML(itemId) {
    return `
        <div class="no-data">
            <i class="fas fa-network-wired"></i>
            <h5>No Interface Data Available</h5>
            <p>No interface information found in database.</p>
            <p class="text-muted">Please run interface discovery to scan device via SNMP.</p>
            <button class="btn btn-primary" onclick="window.opener.discoverInterfacesFromModal(${itemId}); window.close();">
                <i class="fas fa-search"></i> Run Discovery
            </button>
        </div>
    `;
}

// Load interface details data from API
function loadInterfaceDetailsData(itemId, callback) {
    console.log('🔄 Loading interface details data for device:', itemId);
    
    $.ajax({
        url: 'api/snmp_interfaces_enhanced.php',
        method: 'GET',
        data: {
            action: 'stored_interfaces',
            id: itemId
        },
        dataType: 'json',
        success: function(response) {
            console.log('📊 Interface details API response:', response);
            if (response.success) {
                callback({
                    interfaces: response.data || [],
                    itemId: itemId
                });
            } else {
                console.error('❌ API error:', response.message);
                callback(null);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Interface details load error:', error);
            callback(null);
        }
    });
}

// Create enhanced interface table with detailed information
function createEnhancedInterfaceTable(interfaces, itemId) {
    console.log('📋 Creating enhanced interface table with', interfaces.length, 'interfaces');
    
    if (!interfaces || interfaces.length === 0) {
        return `
            <div class="interface-table-container">
                <div class="text-center py-4">
                    <i class="fas fa-network-wired text-warning fa-3x mb-3"></i>
                    <h5 class="mt-2">No Interfaces Found</h5>
                    <p class="text-muted">No interface data available in database.</p>
                    <p class="text-muted">Click "Discover Interfaces" to scan device via SNMP.</p>
                    <button class="btn btn-warning mt-2" onclick="discoverInterfacesFromModal(${itemId})">
                        <i class="fas fa-search"></i> Discover Interfaces
                    </button>
                </div>
            </div>
        `;
    }
    
    let tableHTML = `
        <div class="interface-table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-list text-primary"></i> Interface Details</h6>
                <button class="btn btn-sm btn-outline-primary" onclick="refreshInterfaceDetailsTable(${itemId})">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead class="thead-dark">
                        <tr>
                            <th>Index</th>
                            <th>Interface Name</th>
                            <th>Type</th>
                            <th>Speed</th>
                            <th>Status</th>
                            <th>MAC Address</th>
                            <th>IP Addresses</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    interfaces.forEach(iface => {
        const speedFormatted = formatSpeedHelper(iface.speed_bps);
        const statusClass = iface.oper_status === 'up' ? 'success' : 
                           iface.oper_status === 'down' ? 'danger' : 'warning';
        const ipAddresses = iface.ip_addresses || 'None';
        const lastSeen = iface.last_seen ? new Date(iface.last_seen).toLocaleString() : 'Unknown';
        const macAddress = iface.mac_address || 'N/A';
        
        // Parse IP addresses if it's a comma-separated string
        let ipDisplay = ipAddresses;
        if (ipAddresses && ipAddresses !== 'None') {
            const ips = ipAddresses.split(', ');
            if (ips.length > 1) {
                ipDisplay = `
                    <div class="ip-list">
                        ${ips.map(ip => `<span class="badge badge-info mr-1 mb-1">${ip.trim()}</span>`).join('')}
                    </div>
                `;
            } else {
                ipDisplay = `<span class="badge badge-info">${ipAddresses}</span>`;
            }
        } else {
            ipDisplay = '<span class="text-muted">No IP</span>';
        }
        
        tableHTML += `
            <tr>
                <td><strong>${iface.interface_index}</strong></td>
                <td>
                    <i class="fas fa-ethernet text-primary"></i>
                    <strong>${iface.interface_name}</strong>
                </td>
                <td>
                    <span class="badge badge-secondary">${iface.interface_type}</span>
                </td>
                <td>
                    <span class="speed-info">${speedFormatted}</span>
                </td>
                <td>
                    <span class="badge badge-${statusClass}">
                        <i class="fas fa-${statusClass === 'success' ? 'arrow-up' : statusClass === 'danger' ? 'arrow-down' : 'exclamation-triangle'}"></i>
                        ${iface.oper_status.toUpperCase()}
                    </span>
                </td>
                <td>
                    <code class="mac-address">${macAddress}</code>
                </td>
                <td>
                    ${ipDisplay}
                </td>
                <td>
                    <small class="text-muted">${lastSeen}</small>
                </td>
            </tr>
        `;
    });
    
    tableHTML += `
                    </tbody>
                </table>
            </div>
            
            <!-- Interface Summary -->
            <div class="mt-3">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-2 bg-light rounded">
                            <strong class="text-primary">${interfaces.length}</strong>
                            <br><small class="text-muted">Total Interfaces</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-2 bg-light rounded">
                            <strong class="text-success">${interfaces.filter(i => i.oper_status === 'up').length}</strong>
                            <br><small class="text-muted">UP Interfaces</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-2 bg-light rounded">
                            <strong class="text-danger">${interfaces.filter(i => i.oper_status === 'down').length}</strong>
                            <br><small class="text-muted">DOWN Interfaces</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-2 bg-light rounded">
                            <strong class="text-info">${interfaces.filter(i => i.ip_addresses && i.ip_addresses !== 'None').length}</strong>
                            <br><small class="text-muted">With IP</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    return tableHTML;
}

// Refresh interface details table
function refreshInterfaceDetailsTable(itemId) {
    console.log('🔄 Refreshing interface details table for device:', itemId);
    
    const detailView = $(`#detailedInterfaceView${itemId}`);
    
    detailView.html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Refreshing...</span>
            </div>
            <h6 class="mt-3">Refreshing Interface Details...</h6>
        </div>
    `);
    
    loadInterfaceDetailsData(itemId, function(data) {
        if (data && data.interfaces) {
            const interfaceTable = createEnhancedInterfaceTable(data.interfaces, itemId);
            detailView.html(interfaceTable);
        } else {
            detailView.html(`
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <h5>Unable to Load Interface Data</h5>
                    <p>Please try running interface discovery first.</p>
                </div>
            `);
        }
    });
}

// Show topology visualization in popup window
function showTopologyVisualization(itemId) {
    console.log('🗺️ Opening Topology Popup for device:', itemId);
    
    // Get device info first
    let deviceName = 'Unknown Device';
    let deviceIP = 'Unknown IP';
    
    // Try to get device info from current data or fetch it
    if (window.currentInterfaceData && window.currentInterfaceData.itemId == itemId) {
        deviceName = window.currentInterfaceData.device.name || 'Unknown Device';
        deviceIP = window.currentInterfaceData.device.ip || 'Unknown IP';
        openTopologyPopup(itemId, deviceName, deviceIP, window.currentInterfaceData.topology);
    } else {
        // Load device info first
        $.ajax({
            url: 'api/items.php',
            method: 'GET',
            data: { action: 'read_single', id: itemId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    deviceName = response.data.name || 'Unknown Device';
                    deviceIP = response.data.ip_address || 'Unknown IP';
                }
                
                // Then load topology data
                loadTopologyData(itemId, function(data) {
                    const topology = data ? data.topology : [];
                    openTopologyPopup(itemId, deviceName, deviceIP, topology);
                });
            },
            error: function() {
                // Load with default info
                loadTopologyData(itemId, function(data) {
                    const topology = data ? data.topology : [];
                    openTopologyPopup(itemId, deviceName, deviceIP, topology);
                });
            }
        });
    }
}

// Open topology in popup window
function openTopologyPopup(itemId, deviceName, deviceIP, topology) {
    console.log('🪟 Opening topology popup window');
    
    // Create popup window
    const popupWidth = 1200;
    const popupHeight = 800;
    const left = (screen.width - popupWidth) / 2;
    const top = (screen.height - popupHeight) / 2;
    
    const popup = window.open('', `topologyView_${itemId}`, 
        `width=${popupWidth},height=${popupHeight},left=${left},top=${top},scrollbars=yes,resizable=yes`);
    
    if (!popup) {
        alert('Popup blocked! Please allow popups for this site.');
        return;
    }
    
    // Create HTML content for popup
    const popupContent = createTopologyPopupContent(itemId, deviceName, deviceIP, topology);
    
    // Write content to popup
    popup.document.write(popupContent);
    popup.document.close();
    
    // Focus popup window
    popup.focus();
    
    console.log('✅ Topology popup opened successfully');
}

// Create HTML content for topology popup
function createTopologyPopupContent(itemId, deviceName, deviceIP, topology) {
    const hasConnections = topology && topology.length > 0;
    
    // Calculate statistics
    const stats = {
        total: topology.length,
        high: topology.filter(t => t.confidence_level === 'high').length,
        medium: topology.filter(t => t.confidence_level === 'medium').length,
        low: topology.filter(t => t.confidence_level === 'low').length,
        types: [...new Set(topology.map(t => t.connection_type))].length
    };
    
    const lastUpdate = hasConnections ? 
        new Date(topology[0].last_seen || Date.now()).toLocaleString() : 
        'Never';
    
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Topology - ${deviceName}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            margin: 0;
            padding: 20px;
        }
        .container-fluid {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 0;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h3 {
            margin: 0;
            font-weight: 600;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .stats-row {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #17a2b8;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .topology-content {
            margin: 20px;
        }
        .connection-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .connection-card:hover {
            border-color: #17a2b8;
            box-shadow: 0 4px 15px rgba(23,162,184,0.3);
            transform: translateY(-2px);
        }
        .connection-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .connection-body {
            padding: 20px;
        }
        .device-box {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .device-icon {
            font-size: 2em;
            margin-bottom: 10px;
            color: #17a2b8;
        }
        .connection-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .connection-arrow i {
            font-size: 2em;
            color: #28a745;
        }
        .connection-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
        }
        .badge-confidence-high { background: #28a745; }
        .badge-confidence-medium { background: #ffc107; color: #212529; }
        .badge-confidence-low { background: #dc3545; }
        .no-data {
            text-align: center;
            padding: 60px;
            color: #6c757d;
        }
        .no-data i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .refresh-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 50px;
            padding: 8px 15px;
            font-size: 12px;
        }
        .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header position-relative">
            <button class="refresh-btn" onclick="refreshTopologyData()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <h3><i class="fas fa-project-diagram"></i> Network Topology</h3>
            <p><strong>${deviceName}</strong> (${deviceIP})</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="row">
                <div class="col-2">
                    <div class="stat-card">
                        <div class="stat-number text-info">${stats.total}</div>
                        <div class="stat-label">Total Connections</div>
                    </div>
                </div>
                <div class="col-2">
                    <div class="stat-card">
                        <div class="stat-number text-success">${stats.high}</div>
                        <div class="stat-label">High Confidence</div>
                    </div>
                </div>
                <div class="col-2">
                    <div class="stat-card">
                        <div class="stat-number text-warning">${stats.medium}</div>
                        <div class="stat-label">Medium Confidence</div>
                    </div>
                </div>
                <div class="col-2">
                    <div class="stat-card">
                        <div class="stat-number text-danger">${stats.low}</div>
                        <div class="stat-label">Low Confidence</div>
                    </div>
                </div>
                <div class="col-2">
                    <div class="stat-card">
                        <div class="stat-number text-primary">${stats.types}</div>
                        <div class="stat-label">Connection Types</div>
                    </div>
                </div>
                <div class="col-2">
                    <div class="stat-card">
                        <div class="stat-number text-secondary">${hasConnections ? topology.filter(t => t.discovery_method === 'ip_subnet').length : 0}</div>
                        <div class="stat-label">IP Subnet</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Topology Content -->
        <div class="topology-content">
            ${hasConnections ? createTopologyConnectionsHTML(topology, deviceName) : createNoTopologyHTML(itemId)}
        </div>
        
        <!-- Footer -->
        <div class="text-center p-3 border-top">
            <small class="text-muted">
                <i class="fas fa-clock"></i> Last Update: ${lastUpdate}
            </small>
        </div>
    </div>
    
    <script>
        function refreshTopologyData() {
            window.location.reload();
        }
        
        // Auto refresh every 60 seconds
        setInterval(function() {
            const btn = document.querySelector('.refresh-btn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auto Refresh';
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }, 60000);
    </script>
</body>
</html>`;
}

// Create topology connections HTML for popup
function createTopologyConnectionsHTML(topology, deviceName) {
    let html = '';
    
    topology.forEach(connection => {
        const confidenceClass = connection.confidence_level === 'high' ? 'badge-confidence-high' :
                               connection.confidence_level === 'medium' ? 'badge-confidence-medium' : 'badge-confidence-low';
        
        const connectionIcon = getConnectionTypeIcon(connection.connection_type);
        const discoveryMethod = connection.discovery_method || 'unknown';
        const sharedNetwork = connection.shared_network || 'N/A';
        const lastSeen = connection.last_seen ? new Date(connection.last_seen).toLocaleString() : 'Unknown';
        
        html += `
            <div class="connection-card">
                <div class="connection-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">
                                ${connectionIcon}
                                Connection: ${connection.connection_type.toUpperCase()}
                            </h6>
                            <small class="text-muted">Method: ${discoveryMethod}</small>
                        </div>
                        <span class="badge ${confidenceClass}">
                            ${connection.confidence_level.toUpperCase()}
                        </span>
                    </div>
                </div>
                <div class="connection-body">
                    <div class="row">
                        <!-- Source Device -->
                        <div class="col-md-4">
                            <div class="device-box">
                                <div class="device-icon">
                                    <i class="fas fa-server"></i>
                                </div>
                                <h6>${connection.source_device_name || deviceName}</h6>
                                <small class="text-muted">
                                    <i class="fas fa-ethernet"></i>
                                    ${connection.source_interface_name || 'Unknown Interface'}
                                </small>
                            </div>
                        </div>
                        
                        <!-- Connection Arrow -->
                        <div class="col-md-4">
                            <div class="connection-arrow">
                                <i class="fas fa-arrows-alt-h"></i>
                            </div>
                            <div class="text-center">
                                <small class="badge badge-info">${connection.connection_type}</small>
                            </div>
                        </div>
                        
                        <!-- Target Device -->
                        <div class="col-md-4">
                            <div class="device-box">
                                <div class="device-icon">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <h6>${connection.target_device_name}</h6>
                                <small class="text-muted">
                                    <i class="fas fa-ethernet"></i>
                                    ${connection.target_interface_name || 'Unknown Interface'}
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connection Info -->
                    <div class="connection-info">
                        <div class="row">
                            <div class="col-md-6">
                                <small><strong>Shared Network:</strong> ${sharedNetwork}</small>
                            </div>
                            <div class="col-md-6 text-right">
                                <small><strong>Last Seen:</strong> ${lastSeen}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    return html;
}

// Create no topology HTML for popup
function createNoTopologyHTML(itemId) {
    return `
        <div class="no-data">
            <i class="fas fa-project-diagram"></i>
            <h5>No Network Connections Found</h5>
            <p>No topology connections have been discovered for this device.</p>
            <p class="text-muted">Try running interface discovery to analyze network connections and find connected devices.</p>
            <button class="btn btn-info btn-lg" onclick="window.opener.discoverInterfacesFromModal(${itemId}); window.close();">
                <i class="fas fa-search"></i> Run Interface Discovery
            </button>
        </div>
    `;
}

// Load topology data from API
function loadTopologyData(itemId, callback) {
    console.log('🔄 Loading topology data for device:', itemId);
    
    $.ajax({
        url: 'api/snmp_interfaces_enhanced.php',
        method: 'GET',
        data: {
            action: 'topology_map',
            id: itemId
        },
        dataType: 'json',
        success: function(response) {
            console.log('🗺️ Topology API response:', response);
            if (response.success) {
                // Get device info as well
                $.ajax({
                    url: 'api/items.php',
                    method: 'GET',
                    data: { action: 'read_single', id: itemId },
                    dataType: 'json',
                    success: function(deviceResponse) {
                        callback({
                            topology: response.data || [],
                            device: deviceResponse.success ? deviceResponse.data : { id: itemId, name: 'Unknown Device' },
                            itemId: itemId
                        });
                    },
                    error: function() {
                        callback({
                            topology: response.data || [],
                            device: { id: itemId, name: 'Unknown Device' },
                            itemId: itemId
                        });
                    }
                });
            } else {
                console.error('❌ Topology API error:', response.message);
                callback(null);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Topology load error:', error);
            callback(null);
        }
    });
}

// Create enhanced topology visualization
function createEnhancedTopologyVisualization(topology, device, itemId) {
    console.log('🗺️ Creating enhanced topology visualization with', topology.length, 'connections');
    
    if (!topology || topology.length === 0) {
        return `
            <div class="topology-container">
                <div class="text-center py-4">
                    <i class="fas fa-project-diagram text-info fa-3x mb-3"></i>
                    <h5 class="mt-2">No Network Connections</h5>
                    <p class="text-muted">No topology connections discovered for this device.</p>
                    <p class="text-muted">Network devices may be isolated or using different subnets.</p>
                    <button class="btn btn-info mt-2" onclick="discoverInterfacesFromModal(${itemId})">
                        <i class="fas fa-search"></i> Scan for Connections
                    </button>
                </div>
            </div>
        `;
    }
    
    let topologyHTML = `
        <div class="topology-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-project-diagram text-info"></i> Network Topology</h6>
                <button class="btn btn-sm btn-outline-info" onclick="refreshTopologyData(${itemId})">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <!-- Topology Statistics -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="text-center p-2 bg-light rounded">
                        <strong class="text-info">${topology.length}</strong>
                        <br><small class="text-muted">Total Connections</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-2 bg-light rounded">
                        <strong class="text-success">${topology.filter(t => t.confidence_level === 'high').length}</strong>
                        <br><small class="text-muted">High Confidence</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-2 bg-light rounded">
                        <strong class="text-primary">${[...new Set(topology.map(t => t.connection_type))].length}</strong>
                        <br><small class="text-muted">Connection Types</small>
                    </div>
                </div>
            </div>
            
            <!-- Connection List -->
            <div class="topology-connections">
    `;
    
    topology.forEach(connection => {
        const connectionIcon = getConnectionTypeIcon(connection.connection_type);
        const confidenceClass = getConfidenceClass(connection.confidence_level);
        const discoveryMethod = connection.discovery_method || 'unknown';
        const sharedNetwork = connection.shared_network || 'N/A';
        const lastSeen = connection.last_seen ? new Date(connection.last_seen).toLocaleString() : 'Unknown';
        
        topologyHTML += `
            <div class="connection-item card mb-2">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-center">
                            ${connectionIcon}
                        </div>
                        <div class="col-md-4">
                            <div class="connection-source">
                                <strong>${connection.source_device_name || device.name}</strong>
                                <br><small class="text-muted">
                                    <i class="fas fa-ethernet"></i> ${connection.source_interface_name || 'Unknown Interface'}
                                </small>
                            </div>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="connection-link">
                                <i class="fas fa-arrows-alt-h text-primary fa-lg"></i>
                                <br><small class="badge badge-secondary mt-1">${connection.connection_type}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="connection-target">
                                <strong>${connection.target_device_name}</strong>
                                <br><small class="text-muted">
                                    <i class="fas fa-ethernet"></i> ${connection.target_interface_name || 'Unknown Interface'}
                                </small>
                            </div>
                        </div>
                        <div class="col-md-1 text-center">
                            <span class="badge badge-${confidenceClass} mb-1">${connection.confidence_level}</span>
                            <br><small class="text-muted">${discoveryMethod}</small>
                        </div>
                    </div>
                    
                    <!-- Additional Connection Details -->
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-network-wired"></i> 
                                <strong>Network:</strong> ${sharedNetwork}
                            </small>
                        </div>
                        <div class="col-md-6 text-right">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> 
                                <strong>Last Seen:</strong> ${lastSeen}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    topologyHTML += `
            </div>
            
            <!-- Topology Legend -->
            <div class="mt-3">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <small class="text-muted">
                            <strong>Legend:</strong>
                            <span class="badge badge-success ml-2">High</span> = Strong evidence |
                            <span class="badge badge-warning ml-1">Medium</span> = Moderate evidence |
                            <span class="badge badge-danger ml-1">Low</span> = Weak evidence
                        </small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    return topologyHTML;
}

// Refresh topology data
function refreshTopologyData(itemId) {
    console.log('🔄 Refreshing topology data for device:', itemId);
    
    const topologyView = $(`#topologyVisualization${itemId}`);
    
    topologyView.html(`
        <div class="text-center py-4">
            <div class="spinner-border text-info" role="status">
                <span class="sr-only">Refreshing...</span>
            </div>
            <h6 class="mt-3">Refreshing Network Topology...</h6>
        </div>
    `);
    
    loadTopologyData(itemId, function(data) {
        if (data && data.topology) {
            const topologyDisplay = createEnhancedTopologyVisualization(data.topology, data.device, itemId);
            topologyView.html(topologyDisplay);
        } else {
            topologyView.html(`
                <div class="alert alert-info text-center">
                    <i class="fas fa-project-diagram fa-2x mb-2"></i>
                    <h5>Unable to Load Topology Data</h5>
                    <p>Please try running interface discovery to analyze connections.</p>
                </div>
            `);
        }
    });
}

// Create detailed interface table
function createDetailedInterfaceTable(interfaces, topology) {
    if (!interfaces || interfaces.length === 0) {
        return `
            <div class="text-center py-4">
                <i class="fas fa-exclamation-circle text-warning fa-2x"></i>
                <h5 class="mt-2">No Interfaces Found</h5>
                <p class="text-muted">No interface data available. Try running interface discovery first.</p>
            </div>
        `;
    }
    
    let tableHTML = `
        <div class="interface-table-container">
            <h6><i class="fas fa-list"></i> Interface Details</h6>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Index</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Speed</th>
                            <th>Status</th>
                            <th>MAC Address</th>
                            <th>IP Addresses</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    interfaces.forEach(iface => {
        const speedFormatted = formatSpeedHelper(iface.speed_bps);
        const statusClass = iface.oper_status === 'up' ? 'success' : 'danger';
        const ipAddresses = iface.ip_addresses || 'None';
        
        tableHTML += `
            <tr>
                <td>${iface.interface_index}</td>
                <td><strong>${iface.interface_name}</strong></td>
                <td><span class="badge badge-secondary">${iface.interface_type}</span></td>
                <td>${speedFormatted}</td>
                <td><span class="badge badge-${statusClass}">${iface.oper_status.toUpperCase()}</span></td>
                <td><code>${iface.mac_address || 'N/A'}</code></td>
                <td><small>${ipAddresses}</small></td>
            </tr>
        `;
    });
    
    tableHTML += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    return tableHTML;
}

// Create topology visualization
function createTopologyVisualization(topology, device) {
    if (!topology || topology.length === 0) {
        return `
            <div class="text-center py-4">
                <i class="fas fa-project-diagram text-info fa-2x"></i>
                <h5 class="mt-2">No Topology Connections</h5>
                <p class="text-muted">No network topology connections discovered. Devices may be isolated or use different subnets.</p>
            </div>
        `;
    }
    
    let topologyHTML = `
        <div class="topology-container">
            <h6><i class="fas fa-project-diagram"></i> Network Topology</h6>
            <div class="topology-connections">
    `;
    
    topology.forEach(connection => {
        const connectionIcon = getConnectionTypeIcon(connection.connection_type);
        const confidenceClass = getConfidenceClass(connection.confidence_level);
        
        topologyHTML += `
            <div class="connection-item card mb-2">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-center">
                            ${connectionIcon}
                        </div>
                        <div class="col-md-4">
                            <strong>${connection.source_device_name || device.name}</strong>
                            <br><small class="text-muted">${connection.source_interface_name || 'Unknown Interface'}</small>
                        </div>
                        <div class="col-md-2 text-center">
                            <i class="fas fa-arrows-alt-h text-primary"></i>
                            <br><small class="text-muted">${connection.connection_type}</small>
                        </div>
                        <div class="col-md-4">
                            <strong>${connection.target_device_name}</strong>
                            <br><small class="text-muted">${connection.target_interface_name || 'Unknown Interface'}</small>
                        </div>
                        <div class="col-md-1 text-center">
                            <span class="badge badge-${confidenceClass}">${connection.confidence_level}</span>
                        </div>
                    </div>
                    ${connection.shared_network ? `<small class="text-muted">Network: ${connection.shared_network}</small>` : ''}
                </div>
            </div>
        `;
    });
    
    topologyHTML += `
            </div>
        </div>
    `;
    
    return topologyHTML;
}

// Helper function to format speed
function formatSpeedHelper(speedBps) {
    if (!speedBps || speedBps == 0) return 'Unknown';
    
    if (speedBps >= 1000000000) {
        return (speedBps / 1000000000).toFixed(1) + ' Gbps';
    } else if (speedBps >= 1000000) {
        return (speedBps / 1000000).toFixed(1) + ' Mbps';
    } else if (speedBps >= 1000) {
        return (speedBps / 1000).toFixed(1) + ' Kbps';
    } else {
        return speedBps + ' bps';
    }
}

// Helper function to get connection type icon
function getConnectionTypeIcon(connectionType) {
    const icons = {
        'direct': '<i class="fas fa-link text-success"></i>',
        'routed': '<i class="fas fa-route text-primary"></i>',
        'switched': '<i class="fas fa-network-wired text-info"></i>',
        'wireless': '<i class="fas fa-wifi text-warning"></i>',
        'vpn': '<i class="fas fa-shield-alt text-danger"></i>',
        'tunnel': '<i class="fas fa-tunnel text-secondary"></i>'
    };
    return icons[connectionType] || '<i class="fas fa-question text-muted"></i>';
}

// Helper function to get confidence level class
function getConfidenceClass(confidenceLevel) {
    const classes = {
        'high': 'success',
        'medium': 'warning',
        'low': 'danger'
    };
    return classes[confidenceLevel] || 'secondary';
}

// Functions for modal actions
function discoverInterfacesFromModal(itemId) {
    console.log('🔍 Running interface discovery from modal for device:', itemId);
    
    const modalBody = $('#interfaceDetailsBody');
    modalBody.html(`
        <div class="text-center py-5">
            <div class="spinner-border text-warning" role="status">
                <span class="sr-only">Discovering...</span>
            </div>
            <h5 class="mt-3 text-muted">Discovering Interfaces...</h5>
            <p class="text-muted">Scanning device interfaces via SNMP...</p>
        </div>
    `);
    
    // Call interface discovery API
    $.ajax({
        url: 'api/snmp_interfaces_enhanced.php',
        method: 'GET',
        data: {
            action: 'discover_store',
            id: itemId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Reload the interface data
                loadInterfaceDiscoveryModalData(itemId);
            } else {
                showInterfaceError('Interface discovery failed: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Interface discovery error:', error);
            showInterfaceError('Discovery failed: Connection error');
        }
    });
}

function showTopologyFromModal(itemId) {
    console.log('🗺️ Showing topology from modal for device:', itemId);
    
    // Switch to topology view
    showTopologyVisualization(itemId);
}

function refreshInterfaceData(itemId) {
    console.log('🔄 Refreshing interface data for device:', itemId);
    
    // Reload interface discovery data
    loadInterfaceDiscoveryModalData(itemId);
}

function showInterfaceError(message) {
    $('#interfaceDetailsBody').html(`
        <div class="alert alert-danger text-center">
            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
            <h5>Error</h5>
            <p>${message}</p>
        </div>
    `);
}

// Load enhanced interface data dengan database storage
function loadInterfaceData(itemId) {
    $.ajax({
        url: 'api/snmp_interfaces_enhanced.php',
        method: 'GET',
        data: { 
            action: 'enhanced_comprehensive',
            id: itemId 
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success && response.data) {
                displayEnhancedInterfaceData(itemId, response.data);
            } else {
                displayInterfaceError(itemId, response.message || 'Failed to load interface data');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Enhanced interface data load error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            
            let errorMessage = 'Cannot connect to device';
            if (xhr.status === 401) {
                errorMessage = 'Authentication required';
            } else if (xhr.status === 500) {
                errorMessage = 'SNMP connection failed';
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    errorMessage = 'Network error: ' + error;
                }
            }
            
            displayInterfaceError(itemId, errorMessage);
        },
        complete: function() {
            // Reset button
            const button = document.getElementById(`btnInterfaces${itemId}`);
            if (button) {
                button.innerHTML = '<i class="fas fa-network-wired"></i> Hide Interfaces';
                button.disabled = false;
            }
        }
    });
}

// Discover interfaces dan store ke database
function discoverInterfaces(itemId) {
    console.log('🔍 Discovering interfaces untuk device:', itemId);
    
    const button = document.getElementById(`btnDiscover${itemId}`);
    const interfaceDiv = document.getElementById(`interfaceData${itemId}`);
    
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Discovering...';
        button.disabled = true;
    }
    
    if (interfaceDiv) {
        interfaceDiv.style.display = 'block';
        interfaceDiv.innerHTML = `
        <div class="discovery-progress text-center py-3">
            <h6><i class="fas fa-search text-primary"></i> Interface Discovery</h6>
            <div class="progress mb-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                    Discovering interfaces via SNMP...
                </div>
            </div>
            <small class="text-muted">This may take a few moments...</small>
        </div>`;
    }
    
    $.ajax({
        url: 'api/snmp_interfaces_enhanced.php',
        method: 'GET',
        data: { 
            action: 'discover_store',
            id: itemId 
        },
        dataType: 'json',
        timeout: 60000, // Longer timeout for discovery
        success: function(response) {
            if (response.success && response.data) {
                displayDiscoveryResults(itemId, response.data);
            } else {
                displayInterfaceError(itemId, response.message || 'Discovery failed');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Interface discovery error:', error);
            
            let errorMessage = 'Discovery failed';
            if (xhr.status === 401) {
                errorMessage = 'Authentication required';
            } else if (xhr.status === 500) {
                errorMessage = 'SNMP connection failed';
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    errorMessage = 'Network error: ' + error;
                }
            }
            
            displayInterfaceError(itemId, errorMessage);
        },
        complete: function() {
            // Reset button
            if (button) {
                button.innerHTML = '<i class="fas fa-search"></i> Discover';
                button.disabled = false;
            }
        }
    });
}

// Show topology mapping
function showTopology(itemId) {
    console.log('🗺️ Loading topology mapping untuk device:', itemId);
    
    const button = document.getElementById(`btnTopology${itemId}`);
    const interfaceDiv = document.getElementById(`interfaceData${itemId}`);
    
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        button.disabled = true;
    }
    
    if (interfaceDiv) {
        interfaceDiv.style.display = 'block';
        interfaceDiv.innerHTML = `
        <div class="text-center py-2">
            <i class="fas fa-spinner fa-spin"></i> Loading topology mapping...
        </div>`;
    }
    
    $.ajax({
        url: 'api/snmp_interfaces_enhanced.php',
        method: 'GET',
        data: { 
            action: 'topology_map',
            id: itemId 
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success && response.data) {
                displayTopologyMapping(itemId, response.data);
            } else {
                displayInterfaceError(itemId, response.message || 'No topology data found');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Topology mapping error:', error);
            
            let errorMessage = 'Failed to load topology';
            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    errorMessage = 'Network error: ' + error;
                }
            }
            
            displayInterfaceError(itemId, errorMessage);
        },
        complete: function() {
            // Reset button
            if (button) {
                button.innerHTML = '<i class="fas fa-project-diagram"></i> Topology';
                button.disabled = false;
            }
        }
    });
}

// Display interface data in popup
function displayInterfaceData(itemId, data) {
    const interfaceDiv = document.getElementById(`interfaceData${itemId}`);
    if (!interfaceDiv) return;
    
    let html = `
    <div class="interface-summary mb-2">
        <h6><i class="fas fa-network-wired text-primary"></i> Network Interfaces</h6>
        <div class="row">
            <div class="col-6">
                <small class="text-muted">Total: </small>
                <span class="badge badge-info">${data.summary.total_interfaces}</span>
            </div>
            <div class="col-6">
                <small class="text-muted">Active: </small>
                <span class="badge badge-success">${data.summary.active_interfaces}</span>
            </div>
        </div>
        <div class="row mt-1">
            <div class="col-12">
                <small class="text-muted">Total Traffic: </small>
                <span class="badge badge-secondary">${data.summary.total_traffic_formatted}</span>
            </div>
        </div>
    </div>
    
    <div class="interface-list">`;
    
    // Display interfaces
    if (data.interfaces && data.interfaces.length > 0) {
        data.interfaces.forEach(function(interface) {
            const statusIcon = getInterfaceStatusIcon(interface.oper_status);
            const speedBadge = getSpeedBadgeClass(interface.speed);
            
            html += `
            <div class="interface-item border-bottom pb-2 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="interface-header">
                        <strong>${interface.name}</strong>
                        <span class="badge badge-outline-secondary ml-1">${interface.type}</span>
                    </div>
                    <div class="interface-status">
                        <i class="fas ${statusIcon}" style="color: ${interface.status_color}"></i>
                        <span class="badge ${speedBadge} ml-1">${interface.speed}</span>
                    </div>
                </div>`;
                
            // Show IP addresses if available
            if (interface.details && interface.details.ip_addresses && interface.details.ip_addresses.length > 0) {
                html += `
                <div class="interface-ips mt-1">
                    <small class="text-muted">IP: </small>`;
                interface.details.ip_addresses.forEach(function(ip, index) {
                    if (index > 0) html += ', ';
                    html += `<span class="badge badge-info">${ip.ip}/${ip.cidr}</span>`;
                });
                html += `</div>`;
            }
            
            // Show traffic statistics
            if (interface.details && interface.details.traffic) {
                const traffic = interface.details.traffic;
                html += `
                <div class="interface-traffic mt-1">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-success">
                                <i class="fas fa-download"></i> ${traffic.in_octets_formatted}
                            </small>
                        </div>
                        <div class="col-6">
                            <small class="text-primary">
                                <i class="fas fa-upload"></i> ${traffic.out_octets_formatted}
                            </small>
                        </div>
                    </div>`;
                    
                // Show error counters if any
                if (traffic.in_errors > 0 || traffic.out_errors > 0 || traffic.in_discards > 0 || traffic.out_discards > 0) {
                    html += `
                    <div class="row mt-1">
                        <div class="col-12">
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Errors: ${parseInt(traffic.in_errors) + parseInt(traffic.out_errors)}, 
                                Discards: ${parseInt(traffic.in_discards) + parseInt(traffic.out_discards)}
                            </small>
                        </div>
                    </div>`;
                }
                
                html += `</div>`;
            }
            
            html += `</div>`;
        });
    } else {
        html += `
        <div class="text-center text-muted py-2">
            <i class="fas fa-info-circle"></i> No interfaces found
        </div>`;
    }
    
    html += `
    </div>
    <div class="interface-footer mt-2">
        <small class="text-muted">
            <i class="fas fa-clock"></i> Updated: ${new Date().toLocaleTimeString()}
            <button class="btn btn-xs btn-outline-primary ml-2" onclick="loadInterfaceData(${itemId})">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </small>
    </div>`;
    
    interfaceDiv.innerHTML = html;
}

// Display interface error
function displayInterfaceError(itemId, errorMessage) {
    const interfaceDiv = document.getElementById(`interfaceData${itemId}`);
    if (!interfaceDiv) return;
    
    interfaceDiv.innerHTML = `
    <div class="interface-error text-center py-2">
        <div class="text-danger mb-2">
            <i class="fas fa-exclamation-triangle"></i> 
            ${errorMessage}
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="loadInterfaceData(${itemId})">
            <i class="fas fa-retry"></i> Retry
        </button>
    </div>`;
}

// Helper functions for interface display
function getInterfaceStatusIcon(status) {
    switch (status.toLowerCase()) {
        case 'up': return 'fa-check-circle';
        case 'down': return 'fa-times-circle';
        case 'testing': return 'fa-question-circle';
        default: return 'fa-minus-circle';
    }
}

function getSpeedBadgeClass(speed) {
    if (speed.includes('Gbps')) return 'badge-success';
    if (speed.includes('Mbps')) return 'badge-primary';
    if (speed.includes('Kbps')) return 'badge-warning';
    return 'badge-secondary';
}

// ===== ENHANCED DISPLAY FUNCTIONS =====

// Display enhanced interface data dengan database + real-time traffic
function displayEnhancedInterfaceData(itemId, data) {
    const interfaceDiv = document.getElementById(`interfaceData${itemId}`);
    if (!interfaceDiv) return;
    
    let html = `
    <div class="enhanced-interface-summary mb-3">
        <h6><i class="fas fa-network-wired text-primary"></i> Enhanced Interface Monitoring</h6>
        <div class="row">
            <div class="col-12">
                <span class="badge badge-info">Device: ${data.device.name}</span>
                <span class="badge badge-secondary ml-1">IP: ${data.device.ip}</span>
            </div>
        </div>
    </div>
    
    <div class="interface-tabs">
        <ul class="nav nav-pills nav-sm mb-2">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#stored-interfaces-${itemId}">
                    <i class="fas fa-database"></i> Stored (${data.stored_interfaces.length})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#realtime-traffic-${itemId}">
                    <i class="fas fa-chart-line"></i> Real-time (${data.real_time_traffic.length})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#topology-connections-${itemId}">
                    <i class="fas fa-project-diagram"></i> Topology (${data.topology_connections.length})
                </a>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Stored Interfaces Tab -->
            <div class="tab-pane fade show active" id="stored-interfaces-${itemId}">
                ${generateStoredInterfacesContent(data.stored_interfaces)}
            </div>
            
            <!-- Real-time Traffic Tab -->
            <div class="tab-pane fade" id="realtime-traffic-${itemId}">
                ${generateRealTimeTrafficContent(data.real_time_traffic)}
            </div>
            
            <!-- Topology Connections Tab -->
            <div class="tab-pane fade" id="topology-connections-${itemId}">
                ${generateTopologyConnectionsContent(data.topology_connections)}
            </div>
        </div>
    </div>
    
    <div class="interface-footer mt-2">
        <small class="text-muted">
            <i class="fas fa-clock"></i> Updated: ${new Date().toLocaleTimeString()}
            <button class="btn btn-xs btn-outline-primary ml-2" onclick="loadInterfaceData(${itemId})">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </small>
    </div>`;
    
    interfaceDiv.innerHTML = html;
}

// Generate stored interfaces content
function generateStoredInterfacesContent(stored_interfaces) {
    if (!stored_interfaces || stored_interfaces.length === 0) {
        return `
        <div class="text-center text-muted py-3">
            <i class="fas fa-info-circle"></i> No stored interfaces found<br>
            <small>Click "Discover" to scan and store interface data</small>
        </div>`;
    }
    
    let html = `<div class="stored-interfaces-list">`;
    
    stored_interfaces.forEach(function(interface) {
        const statusIcon = getInterfaceStatusIcon(interface.oper_status);
        const statusColor = getInterfaceStatusColor(interface.oper_status);
        const speedFormatted = formatInterfaceSpeed(interface.speed_bps);
        
        html += `
        <div class="interface-item border-bottom pb-2 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="interface-header">
                    <strong>${interface.interface_name}</strong>
                    <span class="badge badge-outline-secondary ml-1">${interface.interface_type}</span>
                </div>
                <div class="interface-status">
                    <i class="fas ${statusIcon}" style="color: ${statusColor}"></i>
                    <span class="badge badge-info ml-1">${speedFormatted}</span>
                </div>
            </div>`;
            
        // Show IP addresses if available
        if (interface.ip_addresses) {
            html += `
            <div class="interface-ips mt-1">
                <small class="text-muted">IP: </small>
                <span class="badge badge-success">${interface.ip_addresses}</span>
            </div>`;
        }
        
        // Show additional details
        if (interface.mac_address || interface.mtu) {
            html += `
            <div class="interface-details mt-1">
                <small class="text-muted">`;
            if (interface.mac_address) {
                html += `MAC: ${interface.mac_address} `;
            }
            if (interface.mtu) {
                html += `MTU: ${interface.mtu}`;
            }
            html += `</small>
            </div>`;
        }
        
        html += `
            <div class="interface-meta mt-1">
                <small class="text-muted">
                    <i class="fas fa-clock"></i> Last seen: ${formatTimestamp(interface.last_seen)}
                </small>
            </div>
        </div>`;
    });
    
    html += `</div>`;
    return html;
}

// Generate real-time traffic content
function generateRealTimeTrafficContent(real_time_traffic) {
    if (!real_time_traffic || real_time_traffic.length === 0) {
        return `
        <div class="text-center text-muted py-3">
            <i class="fas fa-info-circle"></i> No real-time traffic data available
        </div>`;
    }
    
    let html = `<div class="realtime-traffic-list">`;
    
    real_time_traffic.forEach(function(traffic) {
        const totalTraffic = traffic.in_octets + traffic.out_octets;
        const totalFormatted = formatBytesHelper(totalTraffic);
        
        html += `
        <div class="traffic-item border-bottom pb-2 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="traffic-header">
                    <strong>${traffic.interface_name}</strong>
                    <span class="badge badge-secondary ml-1">Total: ${totalFormatted}</span>
                </div>
                <div class="traffic-timestamp">
                    <small class="text-muted">
                        <i class="fas fa-clock"></i> ${new Date(traffic.timestamp * 1000).toLocaleTimeString()}
                    </small>
                </div>
            </div>
            
            <div class="traffic-stats mt-2">
                <div class="row">
                    <div class="col-6">
                        <div class="text-success">
                            <i class="fas fa-download"></i> <strong>IN:</strong> ${traffic.in_formatted}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-primary">
                            <i class="fas fa-upload"></i> <strong>OUT:</strong> ${traffic.out_formatted}
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    });
    
    html += `</div>`;
    return html;
}

// Generate topology connections content
function generateTopologyConnectionsContent(topology_connections) {
    if (!topology_connections || topology_connections.length === 0) {
        return `
        <div class="text-center text-muted py-3">
            <i class="fas fa-info-circle"></i> No topology connections found<br>
            <small>Connections will be discovered automatically based on IP subnets</small>
        </div>`;
    }
    
    let html = `<div class="topology-connections-list">`;
    
    topology_connections.forEach(function(connection) {
        const confidenceColor = getConfidenceColor(connection.confidence_level);
        const connectionIcon = getConnectionTypeIcon(connection.connection_type);
        
        html += `
        <div class="topology-item border-bottom pb-2 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="topology-header">
                    <i class="fas ${connectionIcon} text-primary"></i>
                    <strong>${connection.connection_type.toUpperCase()}</strong>
                    <span class="badge badge-${confidenceColor} ml-1">${connection.confidence_level}</span>
                </div>
                <div class="topology-method">
                    <small class="text-muted">${connection.discovery_method}</small>
                </div>
            </div>
            
            <div class="topology-details mt-2">
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">Source:</small><br>
                        <strong>${connection.source_device_name}</strong><br>
                        <small>${connection.source_interface_name}</small>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Target:</small><br>
                        <strong>${connection.target_device_name || 'Unknown'}</strong><br>
                        <small>${connection.target_interface_name || 'Unknown'}</small>
                    </div>
                </div>
                
                ${connection.shared_network ? `
                <div class="mt-2">
                    <small class="text-muted">Shared Network:</small>
                    <span class="badge badge-info">${connection.shared_network}</span>
                </div>` : ''}
            </div>
        </div>`;
    });
    
    html += `</div>`;
    return html;
}

// Display discovery results
function displayDiscoveryResults(itemId, results) {
    const interfaceDiv = document.getElementById(`interfaceData${itemId}`);
    if (!interfaceDiv) return;
    
    let html = `
    <div class="discovery-results">
        <h6><i class="fas fa-check-circle text-success"></i> Interface Discovery Complete</h6>
        
        <div class="discovery-summary mb-3">
            <div class="row">
                <div class="col-6">
                    <div class="text-center">
                        <h4 class="text-primary">${results.interfaces_discovered}</h4>
                        <small class="text-muted">Discovered</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-center">
                        <h4 class="text-success">${results.interfaces_stored}</h4>
                        <small class="text-muted">Stored</small>
                    </div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-6">
                    <div class="text-center">
                        <h4 class="text-info">${parseInt(results.ip_addresses_stored) || 0}</h4>
                        <small class="text-muted">IP Addresses</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-center">
                        <h4 class="text-warning">${results.topology_links}</h4>
                        <small class="text-muted">Topology Links</small>
                    </div>
                </div>
            </div>
        </div>
        
        ${results.errors && results.errors.length > 0 ? `
        <div class="discovery-errors mb-3">
            <h6 class="text-warning"><i class="fas fa-exclamation-triangle"></i> Warnings</h6>
            <ul class="list-unstyled">
                ${results.errors.map(error => `<li><small class="text-warning">• ${error}</small></li>`).join('')}
            </ul>
        </div>` : ''}
        
        <div class="discovery-actions text-center">
            <button class="btn btn-sm btn-primary" onclick="loadInterfaceData(${itemId})">
                <i class="fas fa-eye"></i> View Details
            </button>
        </div>
        
        <div class="discovery-meta mt-2">
            <small class="text-muted">
                <i class="fas fa-clock"></i> Discovery completed: ${results.discovery_time}
            </small>
        </div>
    </div>`;
    
    interfaceDiv.innerHTML = html;
}

// Display topology mapping
function displayTopologyMapping(itemId, connections) {
    const interfaceDiv = document.getElementById(`interfaceData${itemId}`);
    if (!interfaceDiv) return;
    
    if (!connections || connections.length === 0) {
        interfaceDiv.innerHTML = `
        <div class="topology-empty text-center py-3">
            <i class="fas fa-project-diagram text-muted" style="font-size: 2em;"></i>
            <h6 class="mt-2">No Topology Connections</h6>
            <p class="text-muted">No network topology connections have been discovered yet.</p>
            <button class="btn btn-sm btn-primary" onclick="discoverInterfaces(${itemId})">
                <i class="fas fa-search"></i> Discover Interfaces
            </button>
        </div>`;
        return;
    }
    
    let html = `
    <div class="topology-mapping">
        <h6><i class="fas fa-project-diagram text-primary"></i> Network Topology Mapping</h6>
        
        <div class="topology-summary mb-3">
            <span class="badge badge-info">Total Connections: ${connections.length}</span>
            <span class="badge badge-success ml-1">
                Verified: ${connections.filter(c => c.verified == 1).length}
            </span>
        </div>
        
        <div class="topology-list">
            ${generateTopologyConnectionsContent(connections)}
        </div>
        
        <div class="topology-actions text-center mt-3">
            <button class="btn btn-sm btn-outline-primary" onclick="loadInterfaceData(${itemId})">
                <i class="fas fa-network-wired"></i> View Interfaces
            </button>
            <button class="btn btn-sm btn-outline-success ml-2" onclick="discoverInterfaces(${itemId})">
                <i class="fas fa-sync"></i> Re-discover
            </button>
        </div>
    </div>`;
    
    interfaceDiv.innerHTML = html;
}

// Helper functions untuk display
function getInterfaceStatusColor(status) {
    switch (status.toLowerCase()) {
        case 'up': return '#28a745';
        case 'down': return '#dc3545';
        case 'testing': return '#ffc107';
        default: return '#6c757d';
    }
}

function formatInterfaceSpeed(speed_bps) {
    if (!speed_bps || speed_bps == 0) return 'Unknown';
    
    if (speed_bps >= 1000000000) {
        return (speed_bps / 1000000000).toFixed(1) + ' Gbps';
    } else if (speed_bps >= 1000000) {
        return (speed_bps / 1000000).toFixed(1) + ' Mbps';
    } else if (speed_bps >= 1000) {
        return (speed_bps / 1000).toFixed(1) + ' Kbps';
    } else {
        return speed_bps + ' bps';
    }
}

function formatTimestamp(timestamp) {
    if (!timestamp) return 'Unknown';
    try {
        return new Date(timestamp).toLocaleString();
    } catch (e) {
        return timestamp;
    }
}

function getConfidenceColor(confidence) {
    switch (confidence.toLowerCase()) {
        case 'high': return 'success';
        case 'medium': return 'warning';
        case 'low': return 'secondary';
        default: return 'light';
    }
}

// Removed duplicate function - using the one from line 5485

// Export functions to global scope for debugging
window.testRouteUpdate = testRouteUpdate;
window.updateStraightRouteEndpoints = updateStraightRouteEndpoints;
window.forceRefreshRoute = forceRefreshRoute;
window.showInterfaceDetails = showInterfaceDetails;
window.discoverInterfaces = discoverInterfaces;
window.showTopology = showTopology;

// Helper function to format bytes similar to SNMP dashboard
function formatBytesHelper(bytes) {
    if (!bytes || bytes == 0) return '0 B';
    
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const bytesValue = Math.max(bytes, 0);
    const pow = Math.floor((bytesValue ? Math.log(bytesValue) : 0) / Math.log(1024));
    const powClamped = Math.min(pow, units.length - 1);
    
    const result = bytesValue / (1 << (10 * powClamped));
    
    return Math.round(result * 100) / 100 + ' ' + units[powClamped];
}

// Function to open SNMP dashboard for specific device
function openSNMPDashboard(deviceId) {
    const url = `snmp_dashboard.php?device=${deviceId}`;
    window.open(url, '_blank');
}

// Test function for popup debugging
function testPopupWindows() {
    console.log('🧪 Testing popup windows functionality...');
    
    // Test basic popup capability
    console.log('1️⃣ Testing basic popup...');
    const testPopup = window.open('', 'test', 'width=400,height=300');
    
    if (!testPopup) {
        console.error('❌ Basic popup blocked by browser');
        alert('❌ Popup Blocked!\n\nYour browser is blocking popups. Please:\n1. Click the popup blocker icon in address bar\n2. Allow popups for this site\n3. Try again');
        return false;
    }
    
    testPopup.document.write('<h1>Test Popup</h1><p>If you see this, popups are working!</p>');
    testPopup.document.close();
    
    setTimeout(() => {
        testPopup.close();
        console.log('✅ Basic popup test successful');
        
        // Test interface details popup
        console.log('2️⃣ Testing interface details popup...');
        testInterfaceDetailsPopup();
    }, 2000);
    
    return true;
}

function testInterfaceDetailsPopup() {
    console.log('🔌 Testing interface details popup with sample data...');
    
    const sampleInterfaces = [
        {
            interface_index: 1,
            interface_name: 'ether1',
            interface_type: 'ethernet',
            oper_status: 'down',
            ip_addresses: 'None',
            last_seen: new Date().toISOString()
        },
        {
            interface_index: 2,
            interface_name: 'ether2',
            interface_type: 'ethernet',
            oper_status: 'up',
            ip_addresses: '192.168.100.57',
            last_seen: new Date().toISOString()
        },
        {
            interface_index: 4,
            interface_name: 'ether4',
            interface_type: 'ethernet',
            oper_status: 'down',
            ip_addresses: '10.5.50.1/24',
            last_seen: new Date().toISOString()
        }
    ];
    
    openInterfaceDetailsPopup(4736, 'MikroTik Test', '192.168.100.57', sampleInterfaces);
}

// Make functions globally available
window.formatBytesHelper = formatBytesHelper;
window.showDetailedInterfaceView = showDetailedInterfaceView;
window.showTopologyVisualization = showTopologyVisualization;
window.discoverInterfacesFromModal = discoverInterfacesFromModal;
window.showTopologyFromModal = showTopologyFromModal;
window.refreshInterfaceData = refreshInterfaceData;
window.openInterfaceDetailsPopup = openInterfaceDetailsPopup;
window.openTopologyPopup = openTopologyPopup;

// Export test functions
window.testPopupWindows = testPopupWindows;
window.testInterfaceDetailsPopup = testInterfaceDetailsPopup;

// Auto-ping Access Points functionality
function startAccessPointMonitoring() {
    console.log('🏓 Starting Access Point ping monitoring...');
    
    // Ping all Access Points every 60 seconds
    setInterval(function() {
        pingAllAccessPoints();
    }, 60000); // 60 seconds
    
    // Initial ping
    setTimeout(function() {
        pingAllAccessPoints();
    }, 5000); // 5 seconds after page load
}

function pingAllAccessPoints() {
    console.log('🏓 Pinging all Access Points...');
    
    // Find all Access Point markers
    Object.keys(markers).forEach(function(itemId) {
        const marker = markers[itemId];
        if (marker && marker.options.item && marker.options.item.item_type_id == 9) {
            // This is an Access Point
            pingAccessPointQuiet(itemId);
        }
    });
}

function pingAccessPointQuiet(itemId) {
    const marker = markers[itemId];
    if (!marker || !marker.options.item) return;
    
    const item = marker.options.item;
    if (!item.ip_address) return;
    
    console.log('🏓 Quiet ping for Access Point:', item.name, item.ip_address);
    
    // Update marker to show checking state
    updateAccessPointPingIndicator(itemId, 'checking');
    
    $.ajax({
        url: 'api/ping_monitor.php?action=ping_single&host=' + encodeURIComponent(item.ip_address),
        method: 'GET',
        dataType: 'json',
        timeout: 10000, // 10 second timeout
        success: function(response) {
            if (response.success) {
                const result = response.ping_result;
                const status = result.status === 'up' ? 'online' : 'offline';
                
                console.log('🏓 Access Point ping result:', item.name, status, result.response_time + 'ms');
                
                // Update marker indicator
                updateAccessPointPingIndicator(itemId, status, result.response_time);
                
                // Update marker tooltip with status
                updateAccessPointTooltip(itemId, status, result.response_time);
            } else {
                console.warn('🏓 Access Point ping failed:', item.name, response.message);
                updateAccessPointPingIndicator(itemId, 'offline');
            }
        },
        error: function(xhr, status, error) {
            console.warn('🏓 Access Point ping error:', item.name, error);
            updateAccessPointPingIndicator(itemId, 'offline');
        }
    });
}

function updateAccessPointPingIndicator(itemId, status, responseTime) {
    const marker = markers[itemId];
    if (!marker) return;
    
    const markerElement = marker.getElement();
    if (!markerElement) return;
    
    let indicator = markerElement.querySelector('.ping-indicator');
    
    // Create indicator if it doesn't exist
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'ping-indicator';
        markerElement.appendChild(indicator);
    }
    
    // Remove all status classes
    indicator.classList.remove('ping-indicator-online', 'ping-indicator-offline', 'ping-indicator-checking', 'ping-indicator-warning');
    
    // Add appropriate status class
    switch (status) {
        case 'online':
            indicator.classList.add('ping-indicator-online');
            indicator.title = `Online${responseTime ? ` (${responseTime}ms)` : ''}`;
            break;
        case 'offline':
            indicator.classList.add('ping-indicator-offline');
            indicator.title = 'Offline - No response';
            break;
        case 'checking':
            indicator.classList.add('ping-indicator-checking');
            indicator.title = 'Checking connection...';
            break;
        default:
            indicator.classList.add('ping-indicator-warning');
            indicator.title = 'Unknown status';
    }
}

function updateAccessPointTooltip(itemId, status, responseTime) {
    const marker = markers[itemId];
    if (!marker || !marker.options.item) return;
    
    const item = marker.options.item;
    const statusText = status === 'online' ? 'Online' : 'Offline';
    const responseText = responseTime ? ` (${responseTime}ms)` : '';
    
    // Update the marker's popup content to include ping status
    const pingStatusHtml = `
        <div class="ping-status-info mt-2">
            <small class="text-muted">
                <i class="fas fa-satellite-dish"></i> Ping Status: 
                <span class="badge badge-${status === 'online' ? 'success' : 'danger'}">
                    ${statusText}${responseText}
                </span>
            </small>
        </div>
    `;
    
    // Store ping status in marker for popup generation
    marker.options.item.ping_status = status;
    marker.options.item.ping_response_time = responseTime;
    marker.options.item.ping_last_check = new Date().toLocaleTimeString();
}

// Load upstream interface information for popup display
function loadUpstreamInterfaceInfo(itemId, interfaceId) {
    console.log('🔌 Loading upstream interface info for item:', itemId, 'interface:', interfaceId);
    
    $.ajax({
        url: 'api/server_interfaces.php?action=get_upstream_interface&interface_id=' + interfaceId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const iface = response.interface;
                console.log('✅ Upstream interface loaded:', iface);
                
                const upstreamInfo = `
                    <div>
                        <strong>${iface.server_name}</strong> - ${iface.interface_name}
                        <br>
                        <small class="text-muted">
                            ${iface.interface_type} | 
                            <span class="badge badge-${iface.oper_status === 'up' ? 'success' : 'danger'} badge-sm">
                                ${iface.oper_status.toUpperCase()}
                            </span>
                            ${iface.ip_addresses !== 'No IP' ? ' | ' + iface.ip_addresses : ''}
                        </small>
                    </div>
                `;
                
                // Update the popup content
                const upstreamElement = document.getElementById(`upstreamInfo_${itemId}`);
                if (upstreamElement) {
                    upstreamElement.innerHTML = upstreamInfo;
                }
            } else {
                console.warn('❌ Failed to load upstream interface:', response.message);
                const upstreamElement = document.getElementById(`upstreamInfo_${itemId}`);
                if (upstreamElement) {
                    upstreamElement.innerHTML = '<span class="text-muted">Unable to load interface info</span>';
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error loading upstream interface:', error);
            const upstreamElement = document.getElementById(`upstreamInfo_${itemId}`);
            if (upstreamElement) {
                upstreamElement.innerHTML = '<span class="text-muted">Error loading interface info</span>';
            }
        }
    });
}

// Start monitoring when map is ready
window.addEventListener('load', function() {
    // Wait for map to be fully initialized
    setTimeout(function() {
        if (typeof markers !== 'undefined' && Object.keys(markers).length > 0) {
            startAccessPointMonitoring();
        } else {
            // Retry after a bit more time
            setTimeout(function() {
                if (typeof markers !== 'undefined') {
                    startAccessPointMonitoring();
                }
            }, 10000);
        }
    }, 3000);
});

// Show routing options modal
function showRoutingOptionsModal(itemId) {
    if ($('#routingOptionsModal').length === 0) {
        $('body').append(`
            <div class="modal fade" id="routingOptionsModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">
                                <i class="fas fa-cog"></i> Opsi Routing Kabel
                            </h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="autoGenerateTiangTumpuCheck">
                                    <label class="custom-control-label" for="autoGenerateTiangTumpuCheck">
                                        <strong>Auto Generate Tiang Tumpu</strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    Otomatis membuat tiang tumpu setiap interval tertentu dan di tikungan
                                </small>
                            </div>
                            
                            <div id="tiangTumpuOptions" style="display: none;">
                                <div class="form-group">
                                    <label for="tiangTumpuInterval">Interval Jarak (meter)</label>
                                    <input type="number" class="form-control" id="tiangTumpuInterval" value="30" min="10" max="100">
                                    <small class="form-text text-muted">
                                        Jarak antar tiang tumpu dalam meter (10-100m)
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="generateAtTurnsCheck" checked>
                                        <label class="custom-control-label" for="generateAtTurnsCheck">
                                            Generate di tikungan
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Tambahkan tiang tumpu otomatis di titik tikungan jalur kabel
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                            <button type="button" class="btn btn-primary" onclick="saveRoutingOptions()">
                                <i class="fas fa-save"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    // Load current settings
    $('#autoGenerateTiangTumpuCheck').prop('checked', window.autoGenerateTiangTumpu);
    $('#tiangTumpuInterval').val(window.tiangTumpuInterval);
    $('#generateAtTurnsCheck').prop('checked', window.generateAtTurns);
    
    // Toggle options visibility
    $('#autoGenerateTiangTumpuCheck').on('change', function() {
        if ($(this).is(':checked')) {
            $('#tiangTumpuOptions').slideDown();
        } else {
            $('#tiangTumpuOptions').slideUp();
        }
    });
    
    // Show options if already enabled
    if (window.autoGenerateTiangTumpu) {
        $('#tiangTumpuOptions').show();
    }
    
    $('#routingOptionsModal').modal('show');
}

// Save routing options
function saveRoutingOptions() {
    window.autoGenerateTiangTumpu = $('#autoGenerateTiangTumpuCheck').is(':checked');
    window.tiangTumpuInterval = parseInt($('#tiangTumpuInterval').val()) || 30;
    window.generateAtTurns = $('#generateAtTurnsCheck').is(':checked');
    
    $('#routingOptionsModal').modal('hide');
    
    let message = window.autoGenerateTiangTumpu ? 
        `Auto generate tiang tumpu AKTIF (setiap ${window.tiangTumpuInterval}m${window.generateAtTurns ? ' + tikungan' : ''})` :
        'Auto generate tiang tumpu NONAKTIF';
    
    showNotification(message, 'info');
}

// Generate tiang tumpu for route
function generateTiangTumpuForRoute(routeId, coordinates, totalDistance) {
    if (!window.autoGenerateTiangTumpu || !coordinates || coordinates.length < 2) {
        return;
    }
    
    console.log(`🏗️ Generating tiang tumpu for route ${routeId}, distance: ${totalDistance}m`);
    
    let tiangTumpuPositions = [];
    let intervalDistance = window.tiangTumpuInterval;
    
    // Calculate positions for interval-based tiang tumpu
    let accumulatedDistance = 0;
    let nextTiangDistance = intervalDistance;
    
    for (let i = 0; i < coordinates.length - 1; i++) {
        let currentPoint = L.latLng(coordinates[i]);
        let nextPoint = L.latLng(coordinates[i + 1]);
        let segmentDistance = currentPoint.distanceTo(nextPoint);
        
        // Check if we need to place tiang tumpu in this segment
        while (nextTiangDistance <= accumulatedDistance + segmentDistance) {
            let distanceInSegment = nextTiangDistance - accumulatedDistance;
            let ratio = distanceInSegment / segmentDistance;
            
            let tiangLat = currentPoint.lat + (nextPoint.lat - currentPoint.lat) * ratio;
            let tiangLng = currentPoint.lng + (nextPoint.lng - currentPoint.lng) * ratio;
            
            tiangTumpuPositions.push({
                lat: tiangLat,
                lng: tiangLng,
                type: 'interval',
                distance_from_start: nextTiangDistance
            });
            
            nextTiangDistance += intervalDistance;
        }
        
        accumulatedDistance += segmentDistance;
    }
    
    // Add turn-based tiang tumpu if enabled
    if (window.generateAtTurns && coordinates.length > 2) {
        for (let i = 1; i < coordinates.length - 1; i++) {
            let prevPoint = L.latLng(coordinates[i - 1]);
            let currentPoint = L.latLng(coordinates[i]);
            let nextPoint = L.latLng(coordinates[i + 1]);
            
            // Calculate angle change
            let angle1 = Math.atan2(currentPoint.lat - prevPoint.lat, currentPoint.lng - prevPoint.lng);
            let angle2 = Math.atan2(nextPoint.lat - currentPoint.lat, nextPoint.lng - currentPoint.lng);
            let angleDiff = Math.abs(angle2 - angle1);
            
            // If angle change is significant (> 30 degrees), add tiang tumpu
            if (angleDiff > Math.PI / 6) {
                // Check if there's already a tiang tumpu near this position
                let hasNearbyTiang = tiangTumpuPositions.some(pos => {
                    let distance = L.latLng(pos.lat, pos.lng).distanceTo(currentPoint);
                    return distance < 10; // Within 10 meters
                });
                
                if (!hasNearbyTiang) {
                    tiangTumpuPositions.push({
                        lat: currentPoint.lat,
                        lng: currentPoint.lng,
                        type: 'turn',
                        angle_change: angleDiff * 180 / Math.PI
                    });
                }
            }
        }
    }
    
    console.log(`🏗️ Generated ${tiangTumpuPositions.length} tiang tumpu positions`);
    
    // Create tiang tumpu items in database
    if (tiangTumpuPositions.length > 0) {
        createTiangTumpuItems(routeId, tiangTumpuPositions);
    }
}

// Create tiang tumpu items in database
function createTiangTumpuItems(routeId, positions) {
    // Get default pricing
    const defaultPrice = parseFloat(localStorage.getItem('defaultTiangTumpuPrice')) || 750000;
    const autoCalculateCost = localStorage.getItem('autoCalculateCost') === 'true';
    const totalCount = positions.length;
    const estimatedCost = totalCount * defaultPrice;
    
    console.log(`💰 Cost estimation: ${totalCount} tiang × Rp ${defaultPrice.toLocaleString()} = Rp ${estimatedCost.toLocaleString()}`);
    
    // Show cost confirmation if auto-calculate is enabled
    if (autoCalculateCost && totalCount > 0) {
        const confirmMessage = `Auto-Generate Tiang Tumpu\n\n` +
                             `Jumlah tiang tumpu: ${totalCount} unit\n` +
                             `Harga per unit: Rp ${defaultPrice.toLocaleString()}\n` +
                             `Estimasi total biaya: Rp ${estimatedCost.toLocaleString()}\n\n` +
                             `Lanjutkan generate tiang tumpu?`;
        
        if (!confirm(confirmMessage)) {
            console.log('User cancelled auto-generate due to cost');
            return;
        }
    }
    
    $.ajax({
        url: 'api/items.php',
        method: 'POST',
        data: {
            action: 'generate_tiang_tumpu',
            route_id: routeId,
            positions: JSON.stringify(positions),
            interval_meters: window.tiangTumpuInterval,
            generate_at_turns: window.generateAtTurns,
            default_price: defaultPrice,
            auto_calculate_cost: autoCalculateCost ? 1 : 0,
            estimated_total_cost: estimatedCost
        },
        success: function(response) {
            console.log('Tiang tumpu generation response:', response);
            if (response.success) {
                const costInfo = response.formatted_cost ? 
                    ` (Total biaya: ${response.formatted_cost})` : '';
                showNotification(`${response.generated_count} tiang tumpu berhasil di-generate${costInfo}`, 'success');
                
                // Reload items to show new tiang tumpu
                loadItems();
                
                // Update statistics
                updateStatistics();
            } else {
                showNotification('Gagal generate tiang tumpu: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Generate tiang tumpu error:', error);
            showNotification('Error generate tiang tumpu: ' + error, 'error');
        }
    });
}

// Make tiang tumpu functions globally available
window.showRoutingOptionsModal = showRoutingOptionsModal;
window.saveRoutingOptions = saveRoutingOptions;
window.generateTiangTumpuForRoute = generateTiangTumpuForRoute;
window.createTiangTumpuItems = createTiangTumpuItems;
window.openSNMPDashboard = openSNMPDashboard;