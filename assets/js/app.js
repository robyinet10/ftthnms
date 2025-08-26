// App.js - FTTH Planner Application Logic

// Global variables
let editingItemId = null;
let tempClickLatLng = null;

// Initialize application
$(document).ready(function() {
    loadFormData();
    initializeEventListeners();
    
    // Add core capacity change listener
    $(document).on('change input', '#totalCoreCapacity, #coreUsed', calculateCoreAvailable);
});

// Load form data (tube colors, splitters)
function loadFormData() {
    // Load tube colors
    $.ajax({
        url: 'api/tube_colors.php',
        method: 'GET',
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success) {
                // Populate tube color dropdown
                let tubeColorSelect = $('#tubeColor');
                tubeColorSelect.empty().append('<option value="">Pilih Warna Tube</option>');
                
                // Populate core color dropdown
                let coreColorSelect = $('#coreColor');
                coreColorSelect.empty().append('<option value="">Pilih Warna Core</option>');
                
                response.data.forEach(function(color) {
                    let option = `<option value="${color.id}" data-color="${color.hex_code}" style="border-left: 4px solid ${color.hex_code};">${color.color_name}</option>`;
                    tubeColorSelect.append(option);
                    coreColorSelect.append(option);
                });
            } else {
                console.error('Tube colors API error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Tube colors AJAX error:', error);
            if (xhr.status === 401) {
                console.warn('Tube colors loading failed: Authentication required');
            }
        }
    });
    
    // Load splitter types
    $.ajax({
        url: 'api/splitters.php',
        method: 'GET',
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success) {
                let mainSplitterSelect = $('#splitterMain');
                let odpSplitterSelect = $('#splitterOdp');
                
                mainSplitterSelect.empty().append('<option value="">Pilih Splitter Utama</option>');
                odpSplitterSelect.empty().append('<option value="">Pilih Splitter ODP</option>');
                
                response.data.forEach(function(splitter) {
                    let option = `<option value="${splitter.id}">${splitter.ratio}</option>`;
                    
                    if (splitter.type === 'main') {
                        mainSplitterSelect.append(option);
                    } else {
                        odpSplitterSelect.append(option);
                    }
                });
            } else {
                console.error('Splitters API error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Splitters AJAX error:', error);
            if (xhr.status === 401) {
                console.warn('Splitters loading failed: Authentication required');
            }
        }
    });
}

// Initialize event listeners
function initializeEventListeners() {
    // Item form submission
    $('#itemForm').on('submit', function(e) {
        e.preventDefault();
        saveItem();
    });
    
    // Modal events
    $('#itemModal').on('hidden.bs.modal', function() {
        resetForm();
    });
    
    // Tube color change event to show color preview
    $('#tubeColor').on('change', function() {
        updateColorPreview();
    });
}

// Show add item modal
function showAddItemModal(lat = null, lng = null) {
    tempClickLatLng = lat && lng ? {lat: lat, lng: lng} : null;
    
    $('#itemModalTitle').text('Tambah Item FTTH');
    $('#itemId').val('');
    $('#itemTypeName').val('');
    $('#itemPrice').val('');
    editingItemId = null;
    
    if (tempClickLatLng) {
        $('#itemLat').val(tempClickLatLng.lat);
        $('#itemLng').val(tempClickLatLng.lng);
    }
    
    $('#itemModal').modal('show');
    
    // Trigger form fields toggle after modal is shown
    setTimeout(() => {
        if (typeof toggleFormFields === 'function') {
            console.log('🔄 Triggering toggleFormFields from showAddItemModal');
            toggleFormFields();
        }
        
        // Additional fix for HTB
        if (typeof fixHTBForm === 'function') {
            console.log('🔄 Triggering fixHTBForm from showAddItemModal');
            fixHTBForm();
        }
    }, 100);
}

// Add new item (from sidebar)
function addNewItem(itemType) {
    console.log('🎯 addNewItem called with itemType:', itemType);
    showAddItemModal();
    
    // Set item type based on parameter
    let itemTypeId = getItemTypeId(itemType);
    console.log('🎯 getItemTypeId returned:', itemTypeId, 'for itemType:', itemType);
    
    if (itemTypeId) {
        console.log('🎯 Setting itemType dropdown to:', itemTypeId);
        $('#itemType').val(itemTypeId);
        
        // Verify the value was set correctly
        setTimeout(() => {
            const currentValue = $('#itemType').val();
            const currentText = $('#itemType option:selected').text();
            console.log('🎯 Current itemType value:', currentValue, 'text:', currentText);
        }, 50);
        
        // Trigger form fields toggle after setting item type
        setTimeout(() => {
            if (typeof toggleFormFields === 'function') {
                console.log('🔄 Triggering toggleFormFields for item type:', itemType, 'ID:', itemTypeId);
                toggleFormFields();
            }
            
            // Additional fix for HTB
            if (typeof fixHTBForm === 'function') {
                console.log('🔄 Triggering fixHTBForm for item type:', itemType, 'ID:', itemTypeId);
                fixHTBForm();
            }
        }, 100);
    } else {
        console.error('❌ getItemTypeId returned empty for itemType:', itemType);
    }
}

// Get item type ID from name
function getItemTypeId(typeName) {
    console.log('🔍 getItemTypeId called with typeName:', typeName);
    let result = '';
    switch(typeName) {
        case 'OLT': result = '1'; break;
        case 'Tiang Tumpu': result = '2'; break;
        case 'Tiang ODP': result = '3'; break;
        case 'ODC Pole Mounted': result = '4'; break;  // ✅ Updated name
        case 'Tiang Joint Closure': result = '5'; break;
        case 'ONT': result = '6'; break;
        case 'Server': result = '7'; break;
        case 'HTB': result = '11'; break;

        default: result = ''; break;
    }
    console.log('🔍 getItemTypeId returning:', result, 'for typeName:', typeName);
    return result;
}

// Edit existing item
function editItem(itemId) {
    editingItemId = itemId;
    
    $.ajax({
        url: 'api/items.php',
        method: 'GET',
        data: { id: itemId },
        dataType: 'json',
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success && response.data) {
                let item = response.data;
                
                $('#itemModalTitle').text('Edit Item FTTH');
                $('#itemId').val(item.id);
                $('#itemType').val(item.item_type_id);
                $('#itemTypeName').val(item.item_type || '');
                $('#itemPrice').val(item.item_price || '');
                $('#itemName').val(item.name);
                $('#itemDescription').val(item.description);
                $('#itemAddress').val(item.address);
                $('#itemLat').val(item.latitude);
                $('#itemLng').val(item.longitude);
                $('#tubeColor').val(item.tube_color_id);
                $('#coreColor').val(item.core_color_id);
                $('#cableType').val(item.item_cable_type || 'distribution');
                $('#totalCoreCapacity').val(item.total_core_capacity || 24);
                $('#coreUsed').val(item.core_used);
                $('#splitterMain').val(item.splitter_main_id);
                $('#splitterOdp').val(item.splitter_odp_id);
                $('#itemStatus').val(item.status);
                
                // Handle monitoring fields for ONT (6), Access Point (8) and Server (7)
                if (item.item_type_id == 6 || item.item_type_id == 8 || item.item_type_id == 7) {
                    $('#ipAddress').val(item.ip_address || '');
                    $('#portHttp').val(item.port_http || 80);
                    $('#portHttps').val(item.port_https || 443);
                    
                    // Load upstream interface for ONT (6) and Access Point (8)
                    if (item.item_type_id == 6 || item.item_type_id == 8) {
                        $('#upstreamInterface').val(item.upstream_interface_id || '');
                        console.log('🔌 Loading Upstream Interface for ONT/Access Point:', item.upstream_interface_id, 'into #upstreamInterface field');
                    }
                    
                    console.log('🔄 Loading IP for ONT/Access Point/Server:', item.ip_address, 'into #ipAddress field');
                    
                    // For Server, also load management IP
                    if (item.item_type_id == 7) {
                        $('#managementIp').val(item.ip_address || '');
                        $('#managementPort').val(item.port_http || 80);
                        $('#httpsPort').val(item.port_https || 443);
                        
                        console.log('🔄 Loading IP for Server:', item.ip_address, 'into #managementIp field');
                        
                        // Load VLAN configuration
                        if (item.vlan_config) {
                            try {
                                const vlans = JSON.parse(item.vlan_config);
                                setTimeout(() => setVlanData(vlans), 200);
                            } catch (e) {
                                console.error('Error parsing VLAN config:', e);
                                setTimeout(() => addVlanField(), 200);
                            }
                        } else {
                            setTimeout(() => addVlanField(), 200);
                        }
                    }
                }
                
                // Handle OLT fields for OLT (1)
                if (item.item_type_id == 1) {
                    $('#oltManagementIp').val(item.ip_address || '');
                    $('#oltManagementPort').val(item.port_http || 80);
                    $('#oltHttpsPort').val(item.port_https || 443);
                    $('#oltUpstreamInterface').val(item.upstream_interface_id || '');
                    
                    console.log('🔄 Loading IP for OLT:', item.ip_address, 'into #oltManagementIp field');
                    console.log('🔌 Loading Upstream Interface for OLT:', item.upstream_interface_id, 'into #oltUpstreamInterface field');
                    
                    // Load VLAN options first, then PON configuration
                    loadVlanOptions().then(() => {
                        console.log('✅ VLAN options loaded for OLT edit, now loading PON data');
                        
                        if (item.pon_config) {
                            try {
                                const pons = JSON.parse(item.pon_config);
                                setPonData(pons);
                            } catch (e) {
                                console.error('Error parsing PON config:', e);
                                addPonField();
                            }
                        } else {
                            addPonField();
                        }
                    }).catch(error => {
                        console.warn('⚠️ Failed to load VLAN options for OLT edit, continuing anyway:', error);
                        
                        if (item.pon_config) {
                            try {
                                const pons = JSON.parse(item.pon_config);
                                setPonData(pons);
                            } catch (e) {
                                console.error('Error parsing PON config:', e);
                                addPonField();
                            }
                        } else {
                            addPonField();
                        }
                    });
                }
                
                // Handle ODC fields for ODC (8)
                if (item.item_type_id == 8) {
                    $('#attenuationNotes').val(item.attenuation_notes || '');
                }
                
                // Handle ONT fields for ONT (6)
                if (item.item_type_id == 6) {
                    console.log('🔄 Loading ONT data for edit:', item);
                    
                    // Populate ONT-specific fields
                    $('#ontModel').val(item.ont_model || '');
                    $('#ontSerialNumber').val(item.ont_serial_number || '');
                    $('#ontInstallationType').val(item.ont_installation_type || 'indoor');
                    $('#ontCustomerName').val(item.ont_customer_name || '');
                    $('#ontCustomerAddress').val(item.ont_customer_address || '');
                    $('#ontServicePlan').val(item.ont_service_plan || '');
                    $('#ontConnectionStatus').val(item.ont_connection_status || 'connected');
                    
                    // Handle ONT-ODP connection
                    if (item.ont_connected_odp_id) {
                        $('#ontConnectedOdp').val(item.ont_connected_odp_id);
                        // Trigger change to load ports, then set the port value
                        setTimeout(() => {
                            updateOntOdpPorts();
                            setTimeout(() => {
                                $('#ontConnectedPort').val(item.ont_connected_port || '');
                            }, 500);
                        }, 200);
                    } else {
                        $('#ontConnectedOdp').val('');
                        $('#ontConnectedPort').val('');
                    }
                }
                
                // Handle ODP fields for ODP/Tiang ODP (3)
                if (item.item_type_id == 3) {
                    console.log('🔄 Loading ODP data for edit:', item);
                    
                    // Populate basic ODP fields
                    $('#odpType').val(item.odp_type || 'pole_mounted');
                    $('#odpCapacity').val(item.odp_capacity || 8);
                    $('#odpSplitterRatio').val(item.odp_splitter_ratio || '1:8');
                    $('#odpInputPorts').val(item.odp_input_ports || 1);
                    $('#odpOutputPorts').val(item.odp_output_ports || 8);
                    $('#odpPortsUsed').val(item.odp_ports_used || 0);
                    

                    
                    // Handle dynamic ODC connections from pon_config
                    if (item.pon_config) {
                        try {
                            const ponConfig = JSON.parse(item.pon_config);
                            console.log('📋 Parsing ODP pon_config:', ponConfig);
                            
                            // Clear existing dynamic fields
                            $('#odpOdcContainer').empty();
                            
                            // Load ODC connections if available
                            if (ponConfig.odp_odc_connections && ponConfig.odp_odc_connections.length > 0) {
                                ponConfig.odp_odc_connections.forEach((connection, index) => {
                                    addOdpOdcField();
                                    
                                    // ✅ ENHANCED: Handle both string and object formats
                                    let connectionValue = '';
                                    let cableLength = '';
                                    let attenuation = '';
                                    let description = '';
                                    
                                    if (typeof connection === 'string') {
                                        // New format: ["4770:1", "4770:2"]
                                        connectionValue = connection;
                                        if (ponConfig.cable_lengths && ponConfig.cable_lengths[index]) {
                                            cableLength = ponConfig.cable_lengths[index];
                                        }
                                        if (ponConfig.attenuations && ponConfig.attenuations[index]) {
                                            attenuation = ponConfig.attenuations[index];
                                        }
                                        if (ponConfig.descriptions && ponConfig.descriptions[index]) {
                                            description = ponConfig.descriptions[index];
                                        }
                                    } else if (typeof connection === 'object' && connection.odc_connection) {
                                        // Old format: [{"odc_connection":"4770:1","cable_length":"","attenuation":"","description":""}]
                                        connectionValue = connection.odc_connection;
                                        cableLength = connection.cable_length || '';
                                        attenuation = connection.attenuation || '';
                                        description = connection.description || '';
                                    }
                                    
                                    console.log(`   Loading connection ${index + 1}:`, {
                                        connectionValue,
                                        cableLength,
                                        attenuation,
                                        description
                                    });
                                    
                                    // Store values to set after dropdown population
                                    const lastFieldContainer = $('.odp-odc-field-container:last');
                                    lastFieldContainer.data('edit-connection', connectionValue);
                                    lastFieldContainer.data('edit-cable-length', cableLength);
                                    lastFieldContainer.data('edit-attenuation', attenuation);
                                    lastFieldContainer.data('edit-description', description);
                                });
                                
                                console.log('✅ Prepared', ponConfig.odp_odc_connections.length, 'ODC connections for loading');
                            }
                            
                            // Handle ONT mappings if available
                            if (ponConfig.ont_mappings && ponConfig.ont_mappings.length > 0) {
                                console.log('📡 Found ONT mappings:', ponConfig.ont_mappings.length);
                                // ONT mappings will be handled by the ONT port initialization
                            }
                            
                        } catch (e) {
                            console.error('❌ Error parsing ODP pon_config:', e);
                        }
                    }
                    
                    // ✅ ENHANCED: Proper Promise chaining for dropdown population
                    Promise.all([
                        // Ensure all dynamic ODC dropdowns are populated
                        ...Array.from($('.odp-odc-select')).map(select => {
                            return new Promise((resolve) => {
                                populateOdcDropdownForOdp($(select).attr('id'));
                                setTimeout(resolve, 200); // Allow time for population
                            });
                        })
                    ]).then(() => {
                        // Set values after all dropdowns are populated
                        $('.odp-odc-field-container').each(function() {
                            const container = $(this);
                            const connectionValue = container.data('edit-connection');
                            const cableLength = container.data('edit-cable-length');
                            const attenuation = container.data('edit-attenuation');
                            const description = container.data('edit-description');
                            
                            if (connectionValue) {
                                container.find('.odp-odc-select').val(connectionValue);
                                container.find('.odp-cable-length').val(cableLength);
                                container.find('.odp-attenuation').val(attenuation);
                                container.find('.odp-connection-desc').val(description);
                                
                                console.log('✅ Set ODC connection values:', {
                                    connectionValue,
                                    cableLength,
                                    attenuation,
                                    description
                                });
                            }
                        });
                        
                        console.log('✅ All ODC connections populated in edit mode');
                    }).catch(error => {
                        console.error('❌ Error populating ODC connections in edit mode:', error);
                    });
                    
                    console.log('✅ ODP edit data loaded successfully');
                }
                
                // Access Point (9) now handled together with ONT (6) above
                
                // Special handling for Tiang Tumpu (2) and HTB (10) - disable form inputs
                if (item.item_type_id == 2 || item.item_type_id == 10) {
                    // Disable all form inputs for Tiang Tumpu and HTB
                    $('#itemModal input, #itemModal select, #itemModal textarea').prop('disabled', true);
                    $('#itemModal .btn-primary').prop('disabled', true).text('Tidak Dapat Diedit');
                    
                    // Show warning message
                    const itemTypeName = item.item_type_id == 2 ? 'Tiang Tumpu' : 'HTB';
                    const warningId = item.item_type_id == 2 ? 'tiangTumpuWarning' : 'htbWarning';
                    
                    if (!$('#' + warningId).length) {
                        $('#itemModal .modal-body').prepend(`
                            <div id="${warningId}" class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>${itemTypeName} tidak dapat diedit.</strong> Data hanya dapat dilihat dalam format display-only.
                            </div>
                        `);
                    }
                    
                    // Hide save button and show close only
                    $('#itemModal .modal-footer .btn-primary').hide();
                    $('#itemModal .modal-footer .btn-secondary').text('Tutup').removeClass('btn-secondary').addClass('btn-primary');
                    
                    $('#itemModal').modal('show');
                    return; // Exit early for Tiang Tumpu and HTB
                }
                
                // Calculate and display core available
                setTimeout(() => calculateCoreAvailable(), 100);
                
                updateColorPreview();
                
                // Load SNMP data for supported device types (Server=7, OLT=1, Access Point=8, ONT=6)
                if ([1, 6, 7, 8].includes(parseInt(item.item_type_id))) {
                    const snmpData = {
                        snmp_enabled: item.snmp_enabled || 0,
                        snmp_version: item.snmp_version || '2c',
                        snmp_community: item.snmp_community || 'public',
                        snmp_port: item.snmp_port || 161,
                        snmp_username: item.snmp_username || '',
                        snmp_auth_protocol: item.snmp_auth_protocol || '',
                        snmp_auth_password: item.snmp_auth_password || '',
                        snmp_priv_protocol: item.snmp_priv_protocol || '',
                        snmp_priv_password: item.snmp_priv_password || ''
                    };
                    
                    console.log('📊 Loading SNMP data for edit:', snmpData);
                    setSNMPData(snmpData);
                }
                
                // Trigger form fields toggle after all data is loaded
                setTimeout(() => toggleFormFields(), 150);
                
                $('#itemModal').modal('show');
            } else {
                showNotification('Error loading item: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Edit item AJAX error:', error, xhr.responseText);
            let errorMessage = 'Error loading item data';
            if (xhr.status === 401) {
                errorMessage = 'Authentication required - please refresh and try again';
                console.warn('⚠️ Authentication error loading item - user should refresh page');
            }
            showNotification(errorMessage, 'error');
        }
    });
}

// Save item (create or update)
function saveItem() {
    let method = editingItemId ? 'PUT' : 'POST';
    
    // Validate required fields
    if (!$('#itemType').val() || !$('#itemName').val()) {
        showNotification('Harap isi semua field yang wajib', 'warning');
        return;
    }
    
    // If no coordinates provided and not editing, get from temp click
    if (!$('#itemLat').val() && !$('#itemLng').val() && tempClickLatLng) {
        $('#itemLat').val(tempClickLatLng.lat);
        $('#itemLng').val(tempClickLatLng.lng);
    }
    
    // Always use POST with FormData for compatibility
    let formData = new FormData($('#itemForm')[0]);
    
    // ✅ ENHANCED: Collect item-specific data before sending
    formData = enhancedSaveItemForODP(formData);
    formData = enhancedSaveItemForONT(formData);
    
    // DEBUG: Log FormData contents for upstream interface debugging
    console.log('🔧 FORM DEBUG - FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(`   ${key}: ${value}`);
    }
    
    // Check specific upstream interface fields
    const upstreamInterfaceMonitoring = $('#upstreamInterface').val();
    const upstreamInterfaceOLT = $('#oltUpstreamInterface').val();
    console.log('🔌 Upstream interface field values:');
    console.log('   upstreamInterface (monitoring):', upstreamInterfaceMonitoring);
    console.log('   oltUpstreamInterface (OLT):', upstreamInterfaceOLT);
    console.log('   Field visibility:');
    console.log('   - monitoringFields visible:', $('#monitoringFields').is(':visible'));
    console.log('   - oltFields visible:', $('#oltFields').is(':visible'));
    console.log('   - upstreamInterface enabled:', !$('#upstreamInterface').prop('disabled'));
    console.log('   - oltUpstreamInterface enabled:', !$('#oltUpstreamInterface').prop('disabled'));
    
    // IMPORTANT: Ensure IP address is included based on device type
    const itemTypeId = parseInt($('#itemType').val());
    let currentIpAddress = '';
    
    // Get IP address from the correct field based on device type
    if (itemTypeId === 7) { // Server
        currentIpAddress = $('#managementIp').val() || $('#ipAddress').val() || '';
    } else if (itemTypeId === 1) { // OLT  
        currentIpAddress = $('#oltManagementIp').val() || '';
    } else if (itemTypeId === 6 || itemTypeId === 8) { // ONT or Access Point
        currentIpAddress = $('#ipAddress').val() || '';
    }
    
    // Force set IP address to ensure it's not lost
    if (currentIpAddress) {
        formData.set('ip_address', currentIpAddress);
        console.log('🔧 Explicitly setting IP address:', currentIpAddress, 'for device type:', itemTypeId);
    }
    
    // IMPORTANT: Ensure upstream interface is included based on device type
    let currentUpstreamInterface = '';
    if (itemTypeId === 1) { // OLT
        currentUpstreamInterface = $('#oltUpstreamInterface').val() || '';
    } else if (itemTypeId === 6 || itemTypeId === 8) { // ONT or Access Point
        currentUpstreamInterface = $('#upstreamInterface').val() || '';
    }
    
    // Force set upstream interface to ensure it's not lost
    if (currentUpstreamInterface) {
        formData.set('upstream_interface_id', currentUpstreamInterface);
        console.log('🔌 Explicitly setting upstream interface:', currentUpstreamInterface, 'for device type:', itemTypeId);
    } else {
        console.log('⚠️ No upstream interface selected for device type:', itemTypeId);
    }
    
    // Add SNMP data to form submission
    const snmpData = getSNMPData();
    if (snmpData) {
        Object.keys(snmpData).forEach(key => {
            formData.set(key, snmpData[key]);
        });
        console.log('📊 SNMP data added to form:', snmpData);
    }
    
    // Log original method and current state
    console.log('🔧 SAVEITEM DEBUG:');
    console.log('Original method:', method);
    console.log('editingItemId:', editingItemId);
    console.log('Item ID field value:', $('#itemId').val());
    
    // For PUT requests, add _method parameter
    if (method === 'PUT') {
        formData.append('_method', 'PUT');
        
        // Ensure ID is included for PUT request
        if (editingItemId && !formData.get('id')) {
            formData.set('id', editingItemId);
        }
        
        // Also ensure we have the ID from the hidden field
        if ($('#itemId').val() && !formData.get('id')) {
            formData.set('id', $('#itemId').val());
        }
        
        // Log all data being sent
        console.log('🚀 PUT Data being sent (all fields):');
        for (let pair of formData.entries()) {
            console.log('  ' + pair[0] + ': ' + pair[1]);
        }
        
        // Specifically check IP address
        const ipFromForm = formData.get('ip_address');
        console.log('🎯 IP Address in FormData:', ipFromForm);
    } else {
        console.log('🚀 POST Data being sent (new item)');
    }
    
    // Force POST method with explicit type declaration
    let requestConfig = {
        url: 'api/items.php',
        type: 'POST',     // Use 'type' instead of 'method' for better compatibility
        method: 'POST',   // Also set method for newer jQuery versions
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        cache: false,     // Disable caching
        success: function(response) {
            if (response && response.success) {
                $('#itemModal').modal('hide');
                
                if (editingItemId) {
                    // Update existing marker
                    updateMarker(editingItemId, response.data);
                    showNotification('Item berhasil diupdate', 'success');
                } else {
                    // Add new marker
                    addMarkerToMap(response.data);
                    showNotification('Item berhasil ditambahkan', 'success');
                }
                
                updateStatistics();
            } else {
                showNotification(response?.message || 'Error saving item', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error, xhr.responseText);
            console.error('Response Text:', xhr.responseText);
            showNotification('Error saving item: ' + error, 'error');
        }
    };
    
    console.log('🚀 Final request config:', {
        url: requestConfig.url,
        type: requestConfig.type,
        method: requestConfig.method,
        dataType: requestConfig.dataType
    });
    
    // Ensure credentials are sent with request
    requestConfig.xhrFields = {
        withCredentials: true
    };
    
    $.ajax(requestConfig);
}

/**
 * ✅ ENHANCED: Collect ODP ODC connection data for PON config
 */
function collectOdpOdcConnections() {
    console.log('🔄 Collecting ODP ODC connection data...');
    
    const connections = [];
    const cableLengths = [];
    const attenuations = [];
    const descriptions = [];
    
    // Find all ODC connection fields in the form
    $('.odp-odc-select').each(function(index) {
        const connectionValue = $(this).val();
        if (connectionValue && connectionValue !== '') {
            connections.push(connectionValue);
            
            // Find related fields in the same container
            const container = $(this).closest('.odp-odc-field-container');
            
            const cableLength = container.find('.odp-cable-length').val() || '0';
            const attenuation = container.find('.odp-attenuation').val() || '0';
            const description = container.find('.odp-connection-desc').val() || '';
            
            cableLengths.push(parseFloat(cableLength));
            attenuations.push(parseFloat(attenuation));
            descriptions.push(description);
            
            console.log(`   Connection ${index + 1}:`, {
                connection: connectionValue,
                cableLength: cableLength + 'm',
                attenuation: attenuation + 'dB',
                description: description
            });
        }
    });
    
    // Build PON config structure for ODP
    const ponConfig = {
        odp_odc_connections: connections,
        cable_lengths: cableLengths,
        attenuations: attenuations,
        descriptions: descriptions,
        odp_ont_outputs: [] // Empty for now, will be populated when ONT connections are added
    };
    
    console.log('📋 Built PON config for ODP:', ponConfig);
    
    return ponConfig;
}



/**
 * ✅ ENHANCED: Process ODP-specific data before saving
 */
function enhancedSaveItemForODP(formData) {
    console.log('🚀 Enhanced save for ODP item...');
    
    const itemTypeId = parseInt($('#itemType').val());
    
    // Only process if this is an ODP item (type 3)
    if (itemTypeId === 3) {
        console.log('🔧 Processing ODP-specific data...');
        

        
        // Collect ODC connections and build PON config
        const ponConfig = collectOdpOdcConnections();
        if (ponConfig.odp_odc_connections.length > 0) {
            const ponConfigJson = JSON.stringify(ponConfig);
            formData.set('pon_config', ponConfigJson);
            console.log('✅ Set pon_config:', ponConfigJson);
        } else {
            console.log('⚠️ No ODC connections configured');
        }
        
        // Debug: Log all ODP-related FormData
        console.log('📋 ODP FormData contents:');
        for (let [key, value] of formData.entries()) {
            if (key.includes('odp') || key.includes('pon_config')) {
                console.log(`   ${key}: ${value}`);
            }
        }
    }
    
    return formData;
}

/**
 * ✅ ENHANCED: Process ONT-specific data before saving (FIXED VERSION)
 */
function enhancedSaveItemForONT(formData) {
    console.log('🚀 Enhanced save for ONT item (FIXED VERSION)...');
    
    const itemTypeId = parseInt($('#itemType').val());
    console.log('📋 Item Type ID detected:', itemTypeId);
    
    // Only process if this is an ONT item (type 6)
    if (itemTypeId === 6) {
        console.log('🔧 Processing ONT-specific data (COMPREHENSIVE)...');
        
        // Check if ONT fields are visible and accessible
        const ontFieldsVisible = $('#ontFields').is(':visible');
        console.log('📋 ONT Fields visible:', ontFieldsVisible);
        
        if (!ontFieldsVisible) {
            console.warn('⚠️ Warning: ONT fields not visible, forcing them to show');
            $('#ontFields').show();
        }
        
        // Collect ONT-ODP connection data with validation
        const ontConnectedOdp = $('#ontConnectedOdp').val() || '';
        const ontConnectedPort = $('#ontConnectedPort').val() || '';
        
        // Collect ONT technical data
        const ontModel = $('#ontModel').val() || '';
        const ontSerialNumber = $('#ontSerialNumber').val() || '';
        const ontInstallationType = $('#ontInstallationType').val() || 'indoor';
        const ontConnectionStatus = $('#ontConnectionStatus').val() || 'connected';
        
        // Collect ONT customer data
        const ontCustomerName = $('#ontCustomerName').val() || '';
        const ontCustomerAddress = $('#ontCustomerAddress').val() || '';
        const ontServicePlan = $('#ontServicePlan').val() || '';
        
        console.log('📋 ONT Data Collected:');
        console.log('  ODP ID:', ontConnectedOdp);
        console.log('  Port:', ontConnectedPort);
        console.log('  Model:', ontModel);
        console.log('  Serial:', ontSerialNumber);
        console.log('  Installation Type:', ontInstallationType);
        console.log('  Connection Status:', ontConnectionStatus);
        console.log('  Customer:', ontCustomerName);
        console.log('  Address:', ontCustomerAddress);
        console.log('  Service Plan:', ontServicePlan);
        
        // FORCE set all ONT fields - even if empty (API should handle nulls)
        formData.set('ont_connected_odp_id', ontConnectedOdp);
        formData.set('ont_connected_port', ontConnectedPort);
        formData.set('ont_model', ontModel);
        formData.set('ont_serial_number', ontSerialNumber);
        formData.set('ont_installation_type', ontInstallationType);
        formData.set('ont_connection_status', ontConnectionStatus);
        formData.set('ont_customer_name', ontCustomerName);
        formData.set('ont_customer_address', ontCustomerAddress);
        formData.set('ont_service_plan', ontServicePlan);
        
        console.log('✅ All ONT fields explicitly set to FormData');
        
        // Debug: Log all ONT-related FormData to verify
        console.log('📋 Final ONT FormData verification:');
        for (let [key, value] of formData.entries()) {
            if (key.includes('ont_')) {
                console.log(`   ${key}: "${value}"`);
            }
        }
        
        // Enhanced validation with user feedback
        if (ontConnectedOdp && !ontConnectedPort) {
            console.warn('⚠️ Warning: ODP selected but no port specified');
            showNotification('Peringatan: Anda memilih ODP tetapi belum memilih port!', 'warning');
        }
        if (ontConnectedPort && !ontConnectedOdp) {
            console.warn('⚠️ Warning: Port specified but no ODP selected');
            showNotification('Peringatan: Anda memilih port tetapi belum memilih ODP!', 'warning');
        }
        
        // Success indicators
        if (ontConnectedOdp && ontConnectedPort) {
            console.log('✅ ONT-ODP connection data is complete');
        }
        if (ontCustomerName) {
            console.log('✅ Customer information provided');
        }
        if (ontModel || ontSerialNumber) {
            console.log('✅ Technical information provided');
        }
        
        // Final verification count
        let fieldCount = 0;
        for (let [key, value] of formData.entries()) {
            if (key.includes('ont_') && value) {
                fieldCount++;
            }
        }
        console.log(`✅ Total ONT fields with data: ${fieldCount}/9`);
        
    } else {
        console.log('ℹ️ Not an ONT item, skipping ONT data collection');
    }
    
    return formData;
}

// Update marker on map
function updateMarker(itemId, itemData) {
    const existingMarker = markers[itemId];
    
    if (existingMarker) {
        // Update existing marker instead of removing and re-adding
        console.log(`🔄 Updating existing marker for item ${itemId}`);
        
        // Stop any ongoing animations
        if (existingMarker.pingAnimationInterval) {
            clearInterval(existingMarker.pingAnimationInterval);
            existingMarker.pingAnimationInterval = null;
        }
        
        // Update marker position if changed
        const newLatLng = [parseFloat(itemData.latitude), parseFloat(itemData.longitude)];
        const currentLatLng = existingMarker.getLatLng();
        
        if (Math.abs(currentLatLng.lat - newLatLng[0]) > 0.0001 || 
            Math.abs(currentLatLng.lng - newLatLng[1]) > 0.0001) {
            existingMarker.setLatLng(newLatLng);
            console.log(`📍 Updated marker position for item ${itemId}`);
        }
        
        // Update marker data
        existingMarker.options.itemData = itemData;
        existingMarker.options.itemId = itemData.id;
        existingMarker.options.itemType = itemData.item_type_name;
        
        // Update monitoring status
        const isMonitoringItem = itemData.ip_address && (itemData.item_type_id == 1 || itemData.item_type_id == 6 || itemData.item_type_id == 7 || itemData.item_type_id == 8);
        existingMarker.options.isMonitoring = isMonitoringItem;
        
        // Update popup content
        existingMarker.setPopupContent(createPopupContent(itemData));
        
        // Restart monitoring animations if applicable
        if (isMonitoringItem) {
            setupMarkerPingAnimation(existingMarker, itemData);
        }
        
        console.log(`✅ Updated marker for item ${itemId} without recreation`);
    } else {
        // Add new marker if it doesn't exist
        console.log(`➕ Adding new marker for item ${itemId}`);
        addMarkerToMap(itemData);
    }
}

// Delete item
function deleteItem(itemId) {
    if (confirm('Apakah Anda yakin ingin menghapus item ini?')) {
        $.ajax({
            url: 'api/items.php',
            method: 'DELETE',
            data: { id: itemId },
            success: function(response) {
                if (response.success) {
                    // Remove marker from map
                    if (markers[itemId]) {
                        map.removeLayer(markers[itemId]);
                        delete markers[itemId];
                    }
                    
                    // Remove any routes connected to this item
                    removeRoutesForItem(itemId);
                    
                    showNotification('Item berhasil dihapus', 'success');
                    updateStatistics();
                } else {
                    showNotification(response.message || 'Error deleting item', 'error');
                }
            },
            error: function() {
                showNotification('Error deleting item', 'error');
            }
        });
    }
}

// Remove routes connected to item
function removeRoutesForItem(itemId) {
    $.ajax({
        url: 'api/routes.php',
        method: 'DELETE',
        data: { item_id: itemId },
        success: function(response) {
            if (response.success && response.deleted_routes) {
                response.deleted_routes.forEach(function(routeId) {
                    if (routes[routeId]) {
                        map.removeLayer(routes[routeId]);
                        delete routes[routeId];
                    }
                });
            }
        }
    });
}

// Reset form
function resetForm() {
    $('#itemForm')[0].reset();
    $('#itemId').val('');
    editingItemId = null;
    tempClickLatLng = null;
    updateColorPreview();
    
    // Clear SNMP fields
    if (typeof clearSNMPFields === 'function') {
        clearSNMPFields();
    }
}

// Update color preview
function updateColorPreview() {
    let selectedColor = $('#tubeColor option:selected').data('color');
    if (selectedColor) {
        $('#tubeColor').css('border-left', `5px solid ${selectedColor}`);
    } else {
        $('#tubeColor').css('border-left', 'none');
    }
}

// Show item list
function showItemList() {
    $.ajax({
        url: 'api/items.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                let itemListHtml = generateItemListHtml(response.data);
                showModal('Daftar Item FTTH', itemListHtml, 'modal-xl');
            }
        },
        error: function() {
            showNotification('Error loading item list', 'error');
        }
    });
}

// Generate item list HTML
function generateItemListHtml(items) {
    let html = `
        <!-- Search Bar -->
        <div class="table-search-container">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="itemSearchName"><i class="fas fa-search"></i> Cari Nama</label>
                        <input type="text" class="form-control" id="itemSearchName" placeholder="Cari berdasarkan nama item..." onkeyup="filterItemTable()">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="itemSearchType"><i class="fas fa-filter"></i> Filter Jenis</label>
                        <select class="form-control" id="itemSearchType" onchange="filterItemTable()">
                            <option value="">Semua Jenis</option>
                            <option value="OLT">OLT</option>
                            <option value="Tiang Tumpu">Tiang Tumpu</option>
                            <option value="Tiang ODP">Tiang ODP</option>
                            <option value="Tiang ODC">Tiang ODC</option>
                            <option value="Tiang Joint Closure">Tiang Joint Closure</option>
                            <option value="ONT">ONT</option>
                            <option value="Server">Server</option>
                            <option value="HTB">HTB</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="itemSearchStatus"><i class="fas fa-toggle-on"></i> Filter Status</label>
                        <select class="form-control" id="itemSearchStatus" onchange="filterItemTable()">
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-outline-secondary btn-block" onclick="clearItemFilters()">
                            <i class="fas fa-times"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="table-toolbar">
            <div>
                <button id="deleteSelectedItems" class="btn btn-danger" onclick="deleteSelectedItems()" disabled>
                    <i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedItemCount">0</span>)
                </button>
            </div>
            <div class="table-total-count">
                <i class="fas fa-list"></i> Total: ${items.length} item
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover" id="itemTable">
                <thead class="table-dark">
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAllItems" onchange="toggleSelectAllItems(this)">
                        </th>
                        <th>Jenis</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>Koordinat</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    items.forEach(function(item) {
        html += `
            <tr>
                <td>
                    <input type="checkbox" class="item-checkbox" value="${item.id}" onchange="updateSelectedItemCount()">
                </td>
                <td>
                    <i class="${getItemIcon(item.item_type_name)}" style="color: ${getItemColor(item.item_type_name)};"></i>
                    ${item.item_type_name}
                </td>
                <td>${item.name}</td>
                <td>${item.address || '-'}</td>
                <td>${(isNaN(parseFloat(item.latitude)) || isNaN(parseFloat(item.longitude))) ? 'Koordinat tidak valid' : `${parseFloat(item.latitude).toFixed(6)}, ${parseFloat(item.longitude).toFixed(6)}`}</td>
                <td>
                    <span class="badge badge-${getStatusBadgeClass(item.status)}">
                        ${getStatusText(item.status)}
                    </span>
                </td>
                <td>
                    <div class="action-buttons-container">
                        <button class="btn btn-sm btn-primary" onclick="editItem(${item.id}); $('#genericModal').modal('hide');" title="Edit Item">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-info" onclick="focusOnItem(${item.id}); $('#genericModal').modal('hide');" title="Focus di Peta">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteItem(${item.id}); $('#genericModal').modal('hide');" title="Hapus Item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

// Get item icon
function getItemIcon(typeName) {
    switch(typeName) {
        case 'OLT': return 'fas fa-server';
        case 'Tiang Tumpu': return 'fas fa-tower-broadcast';
        case 'Tiang ODP':
        case 'ODP': return 'fas fa-project-diagram';
        case 'Tiang ODC':
        case 'ODC': return 'fas fa-network-wired';
        case 'Tiang Joint Closure': return 'fas fa-link';
        case 'ONT': return 'fas fa-home';
        case 'Server': return 'fas fa-server';
        case 'HTB': return 'fas fa-home';

        default: return 'fas fa-circle';
    }
}

// Get item color
function getItemColor(typeName) {
    switch(typeName) {
        case 'OLT': return '#FF6B6B';
        case 'Tiang Tumpu': return '#4ECDC4';
        case 'Tiang ODP': return '#45B7D1';
        case 'Tiang ODC': return '#96CEB4';
        case 'Tiang Joint Closure': return '#E74C3C';
        case 'ONT': return '#FFA500';
        case 'Server': return '#8E44AD';
        case 'HTB': return '#FF6B9D';

        default: return '#999';
    }
}

// Focus on item in map
function focusOnItem(itemId) {
    if (markers[itemId]) {
        let marker = markers[itemId];
        map.setView(marker.getLatLng(), 16);
        marker.openPopup();
    }
}

// Show route list
function showRouteList() {
    $.ajax({
        url: 'api/routes.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                let routeListHtml = generateRouteListHtml(response.data);
                showModal('Daftar Routing Kabel', routeListHtml, 'modal-xl');
            }
        },
        error: function() {
            showNotification('Error loading route list', 'error');
        }
    });
}

// Generate route list HTML
function generateRouteListHtml(routes) {
    let html = `
        <!-- Search Bar -->
        <div class="table-search-container">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="routeSearchFrom"><i class="fas fa-search"></i> Cari Dari</label>
                        <input type="text" class="form-control" id="routeSearchFrom" placeholder="Cari berdasarkan item asal..." onkeyup="filterRouteTable()">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="routeSearchTo"><i class="fas fa-search"></i> Cari Ke</label>
                        <input type="text" class="form-control" id="routeSearchTo" placeholder="Cari berdasarkan item tujuan..." onkeyup="filterRouteTable()">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="routeSearchCableType"><i class="fas fa-filter"></i> Tipe Kabel</label>
                        <select class="form-control" id="routeSearchCableType" onchange="filterRouteTable()">
                            <option value="">Semua Tipe</option>
                            <option value="Fiber Optic">Fiber Optic</option>
                            <option value="ADSS">ADSS</option>
                            <option value="OPGW">OPGW</option>
                            <option value="Armored">Armored</option>
                            <option value="Indoor">Indoor</option>
                            <option value="Outdoor">Outdoor</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="routeSearchStatus"><i class="fas fa-toggle-on"></i> Status</label>
                        <select class="form-control" id="routeSearchStatus" onchange="filterRouteTable()">
                            <option value="">Semua Status</option>
                            <option value="planned">Planned</option>
                            <option value="installed">Installed</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-outline-secondary btn-block" onclick="clearRouteFilters()">
                            <i class="fas fa-times"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="table-toolbar">
            <div>
                <button id="deleteSelectedRoutes" class="btn btn-danger" onclick="deleteSelectedRoutes()" disabled>
                    <i class="fas fa-trash"></i> Hapus Terpilih (<span id="selectedRouteCount">0</span>)
                </button>
            </div>
            <div class="table-total-count">
                <i class="fas fa-route"></i> Total: ${routes.length} routing
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover" id="routeTable">
                <thead class="table-dark">
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAllRoutes" onchange="toggleSelectAllRoutes(this)">
                        </th>
                        <th>Dari</th>
                        <th>Ke</th>
                        <th>Jarak</th>
                        <th>Tipe Kabel</th>
                        <th>Core</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    routes.forEach(function(route) {
        let distance = route.distance ? parseFloat(route.distance).toFixed(2) + ' m' : '-';
        
        html += `
            <tr>
                <td>
                    <input type="checkbox" class="route-checkbox" value="${route.id}" onchange="updateSelectedRouteCount()">
                </td>
                <td>${route.from_item_name || 'Unknown'}</td>
                <td>${route.to_item_name || 'Unknown'}</td>
                <td>${distance}</td>
                <td>${route.cable_type || '-'}</td>
                <td>${route.core_count || '-'}</td>
                <td>
                    <span class="badge badge-${getStatusBadgeClass(route.status)}">
                        ${getStatusText(route.status)}
                    </span>
                </td>
                <td>
                    <div class="action-buttons-container">
                        <button class="btn btn-sm btn-primary" onclick="editRoute(${route.id}); $('#genericModal').modal('hide');" title="Edit Route">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-info" onclick="focusOnRoute(${route.id}); $('#genericModal').modal('hide');" title="Lihat di Peta">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteRoute(${route.id}); $('#genericModal').modal('hide');" title="Hapus Route">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

// Focus on route in map
function focusOnRoute(routeId) {
    if (routes[routeId]) {
        let route = routes[routeId];
        map.fitBounds(route.getBounds());
        route.openPopup();
    }
}

// Delete route
function deleteRoute(routeId) {
    if (confirm('Apakah Anda yakin ingin menghapus route ini?')) {
        $.ajax({
            url: 'api/routes.php',
            method: 'DELETE',
            data: { id: routeId },
            success: function(response) {
                if (response.success) {
                    if (routes[routeId]) {
                        map.removeLayer(routes[routeId]);
                        delete routes[routeId];
                    }
                    showNotification('Route berhasil dihapus', 'success');
                } else {
                    showNotification(response.message || 'Error deleting route', 'error');
                }
            },
            error: function() {
                showNotification('Error deleting route', 'error');
            }
        });
    }
}

// Generic modal function
function showModal(title, content, size = 'modal-lg') {
    if (!$('#genericModal').length) {
        $('body').append(`
            <div class="modal fade" id="genericModal" tabindex="-1" role="dialog">
                <div class="modal-dialog ${size}" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="genericModalTitle"></h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="genericModalBody">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    $('#genericModalTitle').text(title);
    $('#genericModalBody').html(content);
    $('#genericModal').modal('show');
}

// Edit route function
function editRoute(routeId) {
    // Get route data first
    $.ajax({
        url: 'api/routes.php',
        method: 'GET',
        data: { id: routeId },
        success: function(response) {
            if (response.success && response.data) {
                let route = response.data;
                showEditRouteModal(route);
            } else {
                showNotification('Error loading route data', 'error');
            }
        },
        error: function() {
            showNotification('Error loading route data', 'error');
        }
    });
}

// Show edit route modal
function showEditRouteModal(route) {
    let modalHtml = `
        <form id="editRouteForm">
            <input type="hidden" id="editRouteId" value="${route.id}">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Dari Item</label>
                        <input type="text" class="form-control" value="${route.from_item_name}" readonly>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Ke Item</label>
                        <input type="text" class="form-control" value="${route.to_item_name}" readonly>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="editCableType">Tipe Kabel</label>
                        <select class="form-control" id="editCableType" name="cable_type">
                            <option value="Fiber Optic" ${route.cable_type === 'Fiber Optic' ? 'selected' : ''}>Fiber Optic</option>
                            <option value="ADSS" ${route.cable_type === 'ADSS' ? 'selected' : ''}>ADSS (All Dielectric Self-Supporting)</option>
                            <option value="OPGW" ${route.cable_type === 'OPGW' ? 'selected' : ''}>OPGW (Optical Ground Wire)</option>
                            <option value="Armored" ${route.cable_type === 'Armored' ? 'selected' : ''}>Armored Fiber</option>
                            <option value="Indoor" ${route.cable_type === 'Indoor' ? 'selected' : ''}>Indoor Fiber</option>
                            <option value="Outdoor" ${route.cable_type === 'Outdoor' ? 'selected' : ''}>Outdoor Fiber</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="editCoreCount">Jumlah Core</label>
                        <select class="form-control" id="editCoreCount" name="core_count">
                            <option value="2" ${route.core_count == 2 ? 'selected' : ''}>2 Core</option>
                            <option value="4" ${route.core_count == 4 ? 'selected' : ''}>4 Core</option>
                            <option value="6" ${route.core_count == 6 ? 'selected' : ''}>6 Core</option>
                            <option value="8" ${route.core_count == 8 ? 'selected' : ''}>8 Core</option>
                            <option value="12" ${route.core_count == 12 ? 'selected' : ''}>12 Core</option>
                            <option value="24" ${route.core_count == 24 ? 'selected' : ''}>24 Core</option>
                            <option value="48" ${route.core_count == 48 ? 'selected' : ''}>48 Core</option>
                            <option value="72" ${route.core_count == 72 ? 'selected' : ''}>72 Core</option>
                            <option value="96" ${route.core_count == 96 ? 'selected' : ''}>96 Core</option>
                            <option value="144" ${route.core_count == 144 ? 'selected' : ''}>144 Core</option>
                            <option value="216" ${route.core_count == 216 ? 'selected' : ''}>216 Core</option>
                            <option value="288" ${route.core_count == 288 ? 'selected' : ''}>288 Core</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="editRouteStatus">Status</label>
                        <select class="form-control" id="editRouteStatus" name="status">
                            <option value="planned" ${route.status === 'planned' ? 'selected' : ''}>Perencanaan</option>
                            <option value="installed" ${route.status === 'installed' ? 'selected' : ''}>Terpasang</option>
                            <option value="maintenance" ${route.status === 'maintenance' ? 'selected' : ''}>Maintenance</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Jarak</label>
                <input type="text" class="form-control" value="${route.distance ? parseFloat(route.distance).toFixed(2) + ' m' : 'N/A'}" readonly>
                <small class="text-muted">Jarak dihitung otomatis berdasarkan routing</small>
            </div>
            
            <div class="text-right">
                <button type="button" class="btn btn-secondary" onclick="$('#routeEditModal').modal('hide')">Batal</button>
                <button type="submit" class="btn btn-primary">Update Route</button>
            </div>
        </form>
    `;
    
    // Create modal if doesn't exist
    if (!$('#routeEditModal').length) {
        $('body').append(`
            <div class="modal fade" id="routeEditModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Edit Routing Kabel</h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="routeEditModalBody">
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    $('#routeEditModalBody').html(modalHtml);
    $('#routeEditModal').modal('show');
    
    // Handle form submission
    $('#editRouteForm').on('submit', function(e) {
        e.preventDefault();
        saveRouteEdit();
    });
}

// Save route edit
function saveRouteEdit() {
    let formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('id', $('#editRouteId').val());
    formData.append('cable_type', $('#editCableType').val());
    formData.append('core_count', $('#editCoreCount').val());
    formData.append('status', $('#editRouteStatus').val());
    
    console.log('🚀 Route Edit Data being sent:');
    for (let pair of formData.entries()) {
        console.log('  ' + pair[0] + ': ' + pair[1]);
    }
    
    $.ajax({
        url: 'api/routes.php',
        type: 'POST',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        cache: false,
        success: function(response) {
            console.log('✅ Route update response:', response);
            if (response.success) {
                $('#routeEditModal').modal('hide');
                showNotification('Route berhasil diupdate', 'success');
                
                // Refresh route list if open
                if ($('#genericModal').hasClass('show')) {
                    showRouteList();
                }
                
                // Update specific route on map instead of reloading all
                const routeId = $('#editRouteId').val();
                console.log('🔄 Calling updateRouteOnMap with routeId:', routeId);
                console.log('🔄 updateRouteOnMap function exists:', typeof updateRouteOnMap);
                
                if (typeof updateRouteOnMap === 'function') {
                    // Try updateRouteOnMap first
                    updateRouteOnMap(routeId);
                    
                    // Fallback: check if route is visible after 2 seconds
                    setTimeout(() => {
                        if (!routes[routeId]) {
                            console.log('⚠️ Route not visible after updateRouteOnMap, trying forceRefreshRoute');
                            if (typeof forceRefreshRoute === 'function') {
                                forceRefreshRoute(routeId);
                            } else {
                                console.error('❌ forceRefreshRoute function not found, falling back to loadRoutes');
                                loadRoutes();
                            }
                        }
                    }, 2000);
                } else {
                    console.error('❌ updateRouteOnMap function not found, trying forceRefreshRoute');
                    if (typeof forceRefreshRoute === 'function') {
                        forceRefreshRoute(routeId);
                    } else {
                        console.error('❌ forceRefreshRoute function not found, falling back to loadRoutes');
                        loadRoutes();
                    }
                }
            } else {
                console.error('❌ Route update failed:', response.message);
                showNotification(response.message || 'Error updating route', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ AJAX Error:', error, xhr.responseText);
            console.error('Response Text:', xhr.responseText);
            try {
                let errorResponse = JSON.parse(xhr.responseText);
                showNotification(errorResponse.message || 'Error updating route', 'error');
            } catch(e) {
                showNotification('Error updating route: ' + error, 'error');
            }
        }
    });
}

// Calculate core available
function calculateCoreAvailable() {
    let totalCapacity = parseInt($('#totalCoreCapacity').val()) || 0;
    let coreUsed = parseInt($('#coreUsed').val()) || 0;
    let coreAvailable = totalCapacity - coreUsed;
    
    $('#coreAvailable').val(coreAvailable + ' / ' + totalCapacity + ' Core');
    
    // Set color based on availability
    if (coreAvailable <= 0) {
        $('#coreAvailable').removeClass('text-success text-warning').addClass('text-danger');
    } else if (coreAvailable <= totalCapacity * 0.2) {
        $('#coreAvailable').removeClass('text-success text-danger').addClass('text-warning');
    } else {
        $('#coreAvailable').removeClass('text-danger text-warning').addClass('text-success');
    }
}

// Sync core usage from routes
function syncCoreUsageFromRoutes(itemId) {
    if (!itemId) return;
    
    $.ajax({
        url: 'api/routes.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                let totalCoreUsed = 0;
                
                response.data.forEach(function(route) {
                    if (route.from_item_id == itemId || route.to_item_id == itemId) {
                        totalCoreUsed += parseInt(route.core_count) || 0;
                    }
                });
                
                // Update core used in form
                $('#coreUsed').val(totalCoreUsed);
                calculateCoreAvailable();
                
                console.log(`📊 Core usage synced for item ${itemId}: ${totalCoreUsed} cores used`);
            }
        },
        error: function() {
            console.error('Failed to sync core usage from routes');
        }
    });
}

// Enhanced edit item to include core sync
function editItemEnhanced(itemId) {
    editItem(itemId);
    // Sync core usage after loading item data
    setTimeout(() => syncCoreUsageFromRoutes(itemId), 500);
}

// Show item detail
function showItemDetail(itemId) {
    $.ajax({
        url: 'api/items.php',
        method: 'GET',
        data: { id: itemId },
        success: function(response) {
            if (response.success && response.data) {
                let item = response.data;
                showItemDetailModal(item);
            } else {
                showNotification('Error loading item data', 'error');
            }
        },
        error: function() {
            showNotification('Error loading item data', 'error');
        }
    });
}

// Helper functions for monitoring status display
function getMonitoringStatusBadge(status) {
    switch (status) {
        case 'online': return 'success';
        case 'warning': return 'warning';
        case 'offline': return 'danger';
        default: return 'secondary';
    }
}

function getMonitoringStatusIcon(status) {
    switch (status) {
        case 'online': return 'check-circle';
        case 'warning': return 'exclamation-triangle';
        case 'offline': return 'times-circle';
        default: return 'question-circle';
    }
}

function getMonitoringStatusText(status) {
    switch (status) {
        case 'online': return 'Online';
        case 'warning': return 'Warning';
        case 'offline': return 'Offline';
        default: return 'Unknown';
    }
}

// Get specific detail information based on item type
function getDetailSpecificInfo(item) {
    if (item.item_type_id == 1) {
        // OLT - Show PON and VLAN info
        return getOltDetailInfo(item);
    } else if (item.item_type_id == 6) {
        // ONT - Show monitoring and upstream interface info
        return getOntDetailInfo(item);
    } else if (item.item_type_id == 7) {
        // Server/Router - Show VLAN and IP info
        return getServerDetailInfo(item);
    } else if (item.item_type_id == 8) {
        // Access Point - Show wireless and upstream interface info
        return getAccessPointDetailInfo(item);
    } else if (item.item_type_id == 4) {
        // ODC Pole Mounted (4) - Show detailed ODC info
        return getOdcDetailInfo(item);
    } else if (item.item_type_id == 3) {
        // Tiang ODP (3) - Show detailed ODP info
        return getOdpDetailInfo(item);
    } else {
        // Infrastructure items - Show Core & Splitter info
        return getInfrastructureDetailInfo(item);
    }
}

// Get Server/Router specific detail information
function getServerDetailInfo(item) {
    // Parse VLAN config if exists
    let vlanConfig = [];
    if (item.vlan_config) {
        try {
            vlanConfig = JSON.parse(item.vlan_config);
        } catch (e) {
            console.warn('Failed to parse VLAN config:', e);
        }
    }
    
    return `
        <div class="row">
            <!-- Server Management Information -->
            <div class="col-md-6">
                <h6 class="text-purple mb-3">
                    <i class="fas fa-server"></i> Informasi Management
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>IP Management:</strong></td>
                        <td>
                            ${item.ip_address ? `
                                <code>${item.ip_address}</code>
                                <button class="btn btn-sm btn-outline-secondary ml-2" onclick="copyToClipboard('${item.ip_address}')" title="Copy IP">
                                    <i class="fas fa-copy"></i>
                                </button>
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Port Management:</strong></td>
                        <td>
                            ${item.port_http ? `<span class="badge badge-primary">HTTP: ${item.port_http}</span>` : ''}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Port HTTPS:</strong></td>
                        <td>
                            ${item.port_https ? `<span class="badge badge-success">HTTPS: ${item.port_https}</span>` : ''}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Status Monitoring:</strong></td>
                        <td>
                            <span class="badge badge-${getMonitoringStatusBadge(item.monitoring_status)}">
                                <i class="fas fa-${getMonitoringStatusIcon(item.monitoring_status)}"></i>
                                ${getMonitoringStatusText(item.monitoring_status)}
                            </span>
                        </td>
                    </tr>
                    ${item.response_time_ms ? `
                    <tr>
                        <td><strong>Response Time:</strong></td>
                        <td>
                            <span class="badge badge-info">${item.response_time_ms} ms</span>
                        </td>
                    </tr>
                    ` : ''}
                </table>
            </div>
            
            <!-- VLAN Configuration -->
            <div class="col-md-6">
                <h6 class="text-info mb-3">
                    <i class="fas fa-network-wired"></i> Konfigurasi VLAN
                </h6>
                ${vlanConfig.length > 0 ? `
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>VLAN ID</th>
                                    <th>IP Address</th>
                                    <th>Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${vlanConfig.map(vlan => `
                                    <tr>
                                        <td>
                                            <span class="badge badge-primary">${vlan.vlan_id || '-'}</span>
                                        </td>
                                        <td>
                                            <code>${vlan.ip || '-'}</code>
                                            ${vlan.ip ? `
                                                <button class="btn btn-sm btn-outline-secondary ml-1" onclick="copyToClipboard('${vlan.ip}')" title="Copy IP">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            ` : ''}
                                        </td>
                                        <td>
                                            <small>${vlan.description || '-'}</small>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Belum ada konfigurasi VLAN yang tersimpan
                    </div>
                `}
                
                <h6 class="text-secondary mb-3 mt-4">
                    <i class="fas fa-clock"></i> Timestamp
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Dibuat:</strong></td>
                        <td>${formatDate(item.created_at)}</td>
                    </tr>
                    <tr>
                        <td><strong>Diupdate:</strong></td>
                        <td>${formatDate(item.updated_at)}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
}

// Get ONT specific detail information
function getOntDetailInfo(item) {
    return `
        <div class="row">
            <!-- IP Monitoring Information -->
            <div class="col-md-6">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-home"></i> ONT Information
                </h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>IP Address:</strong></td>
                        <td>
                            ${item.ip_address ? `<code>${item.ip_address}</code>` : '<span class="text-muted">Tidak diset</span>'}
                            ${item.ip_address ? `
                                <button class="btn btn-sm btn-outline-secondary ml-1" onclick="copyToClipboard('${item.ip_address}')" title="Copy IP">
                                    <i class="fas fa-copy"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Port HTTP:</strong></td>
                        <td>${item.port_http ? `<span class="badge badge-primary">HTTP: ${item.port_http}</span>` : '<span class="text-muted">Tidak diset</span>'}</td>
                    </tr>
                    <tr>
                        <td><strong>Port HTTPS:</strong></td>
                        <td>${item.port_https && item.port_https != 443 ? `<span class="badge badge-success">HTTPS: ${item.port_https}</span>` : '<span class="text-muted">Tidak diset</span>'}</td>
                    </tr>
                    <tr>
                        <td><strong>Status Monitoring:</strong></td>
                        <td>${getMonitoringStatusBadge(item.monitoring_status)}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Upstream Interface Information -->
            <div class="col-md-6">
                <h6 class="text-info mb-3">
                    <i class="fas fa-network-wired"></i> Upstream Server
                </h6>
                <div id="upstreamInterfaceDetail_${item.id}">
                    ${item.upstream_interface_id ? 
                        '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading interface info...</div>' :
                        '<p class="text-muted">No upstream interface configured</p>'
                    }
                </div>
            </div>
        </div>
        
        ${item.upstream_interface_id ? `
        <script>
            // Load upstream interface info for ONT detail
            loadUpstreamInterfaceDetail(${item.id}, ${item.upstream_interface_id});
        </script>
        ` : ''}
    `;
}

// Get Access Point specific detail information  
function getAccessPointDetailInfo(item) {
    return `
        <div class="row">
            <!-- Wireless AP Information -->
            <div class="col-md-6">
                <h6 class="text-info mb-3">
                    <i class="fas fa-wifi"></i> Access Point Information
                </h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>IP Address:</strong></td>
                        <td>
                            ${item.ip_address ? `<code>${item.ip_address}</code>` : '<span class="text-muted">Tidak diset</span>'}
                            ${item.ip_address ? `
                                <button class="btn btn-sm btn-outline-secondary ml-1" onclick="copyToClipboard('${item.ip_address}')" title="Copy IP">
                                    <i class="fas fa-copy"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Port HTTP:</strong></td>
                        <td>${item.port_http ? `<span class="badge badge-primary">HTTP: ${item.port_http}</span>` : '<span class="text-muted">Tidak diset</span>'}</td>
                    </tr>
                    <tr>
                        <td><strong>Port HTTPS:</strong></td>
                        <td>${item.port_https && item.port_https != 443 ? `<span class="badge badge-success">HTTPS: ${item.port_https}</span>` : '<span class="text-muted">Tidak diset</span>'}</td>
                    </tr>
                    <tr>
                        <td><strong>Device Type:</strong></td>
                        <td><span class="badge badge-info">Wireless Access Point</span></td>
                    </tr>
                    <tr>
                        <td><strong>Status Monitoring:</strong></td>
                        <td>${getMonitoringStatusBadge(item.monitoring_status)}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Upstream Interface Information -->
            <div class="col-md-6">
                <h6 class="text-success mb-3">
                    <i class="fas fa-network-wired"></i> Upstream Server
                </h6>
                <div id="upstreamInterfaceDetail_${item.id}">
                    ${item.upstream_interface_id ? 
                        '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading interface info...</div>' :
                        '<p class="text-muted">No upstream interface configured</p>'
                    }
                </div>
            </div>
        </div>
        
        ${item.upstream_interface_id ? `
        <script>
            // Load upstream interface info for Access Point detail
            loadUpstreamInterfaceDetail(${item.id}, ${item.upstream_interface_id});
        </script>
        ` : ''}
    `;
}

// Get OLT specific detail information
function getOltDetailInfo(item) {
    // Parse PON config if exists
    let ponConfig = [];
    if (item.pon_config) {
        try {
            ponConfig = JSON.parse(item.pon_config);
        } catch (e) {
            console.warn('Failed to parse PON config:', e);
        }
    }

    return `
        <div class="row">
            <!-- IP Management -->
            <div class="col-md-6">
                <h6 class="text-info mb-3">
                    <i class="fas fa-network-wired"></i> Management Information
                </h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>IP Management:</strong></td>
                        <td>
                            ${item.ip_address ? `<code>${item.ip_address}</code>` : '<span class="text-muted">Tidak diset</span>'}
                            ${item.ip_address ? `
                                <button class="btn btn-sm btn-outline-secondary ml-1" onclick="copyToClipboard('${item.ip_address}')" title="Copy IP">
                                    <i class="fas fa-copy"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Port Management:</strong></td>
                        <td>${item.port_http ? `<span class="badge badge-primary">HTTP: ${item.port_http}</span>` : ''}</td>
                    </tr>
                    <tr>
                        <td><strong>Port HTTPS:</strong></td>
                        <td>${item.port_https && item.port_https != 22 ? `<span class="badge badge-success">HTTPS: ${item.port_https}</span>` : ''}</td>
                    </tr>
                    <tr>
                        <td><strong>Status Monitoring:</strong></td>
                        <td>${getMonitoringStatusBadge(item.monitoring_status)}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Upstream Interface Information -->
            <div class="col-md-6">
                <h6 class="text-warning mb-3">
                    <i class="fas fa-network-wired"></i> Upstream Server
                </h6>
                <div id="upstreamInterfaceDetail_${item.id}">
                    ${item.upstream_interface_id ? 
                        '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading interface info...</div>' :
                        '<p class="text-muted">No upstream interface configured</p>'
                    }
                </div>
            </div>
        </div>

        <!-- PON Configuration Button -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="text-center">
                    <button type="button" class="btn btn-primary btn-lg" onclick="showPonConfigurationModal(${item.id})">
                        <i class="fas fa-project-diagram"></i> Detail Konfigurasi PON (Passive Optical Network)
                    </button>
                </div>
            </div>
        </div>
        
        ${item.upstream_interface_id ? `
        <script>
            // Load upstream interface info for OLT detail
            loadUpstreamInterfaceDetail(${item.id}, ${item.upstream_interface_id});
        </script>
        ` : ''}
    `;
}

// Get Tiang Tumpu and HTB specific detail information (display only, no inputs)
function getTiangTumpuDetailInfo(item) {
    const itemTypeName = item.item_type_id == 2 ? 'Tiang Tumpu' : 'HTB';
    
    return `
        <div class="row">
            <!-- Timestamp Information Only -->
            <div class="col-md-12">
                <h6 class="text-secondary mb-3">
                    <i class="fas fa-clock"></i> Timestamp
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead class="thead-light">
                            <tr>
                                <th>Parameter</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Dibuat</strong></td>
                                <td>${formatDate(item.created_at)}</td>
                            </tr>
                            <tr>
                                <td><strong>Diupdate</strong></td>
                                <td>${formatDate(item.updated_at)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
}

// Show PON Configuration Modal
function showPonConfigurationModal(itemId) {
    // Get item data from markers
    const marker = markers[itemId];
    if (!marker || !marker.options.itemData) {
        showNotification('Item tidak ditemukan', 'error');
        return;
    }
    
    const item = marker.options.itemData;
    
    // Parse PON config if exists
    let ponConfig = [];
    if (item.pon_config) {
        try {
            ponConfig = JSON.parse(item.pon_config);
        } catch (e) {
            console.warn('Failed to parse PON config:', e);
        }
    }
    
    let modalHtml = `
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-project-diagram"></i> 
                            Konfigurasi PON (Passive Optical Network)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>OLT:</strong> ${item.name} (${item.ip_address || 'IP tidak diset'})
                                </div>
                            </div>
                        </div>
                        
                        ${ponConfig.length > 0 ? `
                            <div class="row">
                                ${ponConfig.map((pon, index) => `
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-primary">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-plug text-primary"></i>
                                                    <strong>PON Port:</strong> ${pon.port || 'N/A'}
                                                    <span class="badge badge-secondary ml-2">${pon.vlans ? pon.vlans.length : 0} VLAN</span>
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                ${pon.vlans && pon.vlans.length > 0 ? `
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th>VLAN ID</th>
                                                                    <th>Deskripsi</th>
                                                                    <th>Server Source</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                ${pon.vlans.map(vlan => {
                                                                    // Extract server info from description if present
                                                                    const serverMatch = vlan.description ? vlan.description.match(/\[([^\]]+)\]$/) : null;
                                                                    const serverName = serverMatch ? serverMatch[1] : 'Unknown';
                                                                    const cleanDescription = vlan.description ? vlan.description.replace(/\s*\[([^\]]+)\]$/, '') : '-';
                                                                    
                                                                    return `
                                                                    <tr>
                                                                        <td>
                                                                            <span class="badge badge-primary">${vlan.vlan_id || '-'}</span>
                                                                        </td>
                                                                        <td>
                                                                            <small>${cleanDescription}</small>
                                                                        </td>
                                                                        <td>
                                                                            <small class="text-muted">
                                                                                <i class="fas fa-server"></i> ${serverName}
                                                                            </small>
                                                                        </td>
                                                                    </tr>
                                                                    `;
                                                                }).join('')}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                ` : `
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        VLAN belum dikonfigurasi untuk PON ini
                                                    </div>
                                                `}
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                PON belum dikonfigurasi untuk OLT ini
                            </div>
                        `}
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <button type="button" class="btn btn-secondary" onclick="$('#ponConfigurationModal').modal('hide')">
                                    <i class="fas fa-times"></i> Tutup
                                </button>
                                <button type="button" class="btn btn-primary" onclick="editItem(${item.id}); $('#ponConfigurationModal').modal('hide');">
                                    <i class="fas fa-edit"></i> Edit Item
                                </button>
                                <button type="button" class="btn btn-success" onclick="focusOnItem(${item.id}); $('#ponConfigurationModal').modal('hide');">
                                    <i class="fas fa-crosshairs"></i> Fokus di Peta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Create modal if doesn't exist
    if (!$('#ponConfigurationModal').length) {
        $('body').append(`
            <div class="modal fade" id="ponConfigurationModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">
                                <i class="fas fa-project-diagram"></i> Konfigurasi PON (Passive Optical Network)
                            </h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="ponConfigurationModalBody">
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    // Set modal content and show
    $('#ponConfigurationModalBody').html(modalHtml);
    $('#ponConfigurationModal').modal('show');
}

// Get ODC specific detail information
function getOdcDetailInfo(item) {
    const odcTypeName = 'ODC Pole Mounted';
    const odcTypeColor = item.item_type_id == 4 ? 'info' : 'warning';
    
    // Parse PON connection info
    let oltInfo = null;
    if (item.odc_pon_connection) {
        const [oltId, ponPort] = item.odc_pon_connection.split(':');
        oltInfo = { oltId, ponPort };
    }
    
    return `
        <div class="row">
            <!-- ODC Configuration Information -->
            <div class="col-md-6">
                <h6 class="text-${odcTypeColor} mb-3">
                    <i class="fas fa-network-wired"></i> Konfigurasi ODC
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Jenis ODC:</strong></td>
                        <td>
                            <span class="badge badge-${odcTypeColor}">${odcTypeName}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Tipe ODC:</strong></td>
                        <td>
                            <span class="badge badge-info">${item.odc_type === 'pole_mounted' ? 'Pole Mounted' : 'Ground Mounted'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Tipe Instalasi:</strong></td>
                        <td>
                            <span class="badge badge-secondary">${item.odc_installation_type || 'Pole'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Main Splitter:</strong></td>
                        <td>
                            <span class="badge badge-primary">${item.odc_main_splitter_ratio || '1:4'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>ODP Splitter:</strong></td>
                        <td>
                            <span class="badge badge-warning">${item.odc_odp_splitter_ratio || '1:8'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Input Ports:</strong></td>
                        <td>
                            <span class="badge badge-success">${item.odc_input_ports || 1}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Output Ports:</strong></td>
                        <td>
                            <span class="badge badge-info">${item.odc_output_ports || 4}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Kapasitas Customer:</strong></td>
                        <td>
                            <span class="badge badge-danger">${item.odc_capacity || 32}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Ports Used:</strong></td>
                        <td>
                            <span class="badge badge-secondary">${item.odc_ports_used || 0}</span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- OLT Connection Information -->
            <div class="col-md-6">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-plug"></i> PON Connection dari OLT
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>PON Connection:</strong></td>
                        <td>
                            ${item.odc_pon_connection ? `
                                <span class="badge badge-primary">${oltInfo ? oltInfo.ponPort : item.odc_pon_connection}</span>
                                <div id="oltConnectionDetail_${item.id}" class="mt-1">
                                    <small class="text-muted">Loading OLT info...</small>
                                </div>
                            ` : '<span class="text-muted">Belum terhubung ke OLT</span>'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>VLAN ID:</strong></td>
                        <td>
                            ${item.odc_vlan_id ? `
                                <span class="badge badge-secondary">VLAN ${item.odc_vlan_id}</span>
                            ` : '<span class="text-muted">Belum diset</span>'}
                        </td>
                    </tr>
                </table>
                
                <h6 class="text-success mb-3 mt-4">
                    <i class="fas fa-project-diagram"></i> Output ke ODP
                </h6>
                <div id="odcOutputInfo_${item.id}">
                    <small class="text-muted">Loading ODP connections...</small>
                </div>
            </div>
            
            <!-- Core & Cable Information -->
            <div class="col-md-6">
                <h6 class="text-warning mb-3">
                    <i class="fas fa-network-wired"></i> Informasi Core & Kabel
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Warna Tube:</strong></td>
                        <td>
                            ${item.tube_color_name ? `
                                <span class="color-box" style="background-color: ${item.hex_code}; width: 20px; height: 20px; display: inline-block; margin-right: 8px; border: 1px solid #ccc;"></span>
                                ${item.tube_color_name}
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Warna Core:</strong></td>
                        <td>
                            ${item.core_color_name ? `
                                <span class="color-box" style="background-color: ${item.core_hex_code}; width: 20px; height: 20px; display: inline-block; margin-right: 8px; border: 1px solid #ccc;"></span>
                                ${item.core_color_name}
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Jenis Kabel:</strong></td>
                        <td>
                            ${item.item_cable_type ? `
                                <span class="badge badge-${getCableTypeBadge(item.item_cable_type)}">
                                    ${getCableTypeText(item.item_cable_type)}
                                </span>
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Kapasitas Core:</strong></td>
                        <td>
                            <span class="badge badge-secondary">${item.total_core_capacity || 24} Core</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Core Digunakan:</strong></td>
                        <td>
                            <span class="badge badge-${getCoreUsageBadge(item.core_used, item.total_core_capacity)}">
                                ${item.core_used || 0} Core
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Core Tersedia:</strong></td>
                        <td>
                            <span class="badge badge-${getCoreUsageBadge(item.core_used, item.total_core_capacity)}">
                                ${(item.total_core_capacity || 24) - (item.core_used || 0)} Core
                            </span>
                        </td>
                    </tr>
                </table>
                
                <h6 class="text-danger mb-3 mt-4">
                    <i class="fas fa-project-diagram"></i> Informasi Splitter
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Splitter Utama:</strong></td>
                        <td>
                            ${item.splitter_main_ratio ? `
                                <span class="badge badge-info">${item.splitter_main_ratio}</span>
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Splitter ODP:</strong></td>
                        <td>
                            ${item.splitter_odp_ratio ? `
                                <span class="badge badge-warning">${item.splitter_odp_ratio}</span>
                            ` : '-'}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <!-- PON Connection & Attenuation -->
            <div class="col-md-6">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-plug"></i> Koneksi & Redaman
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>PON Connection:</strong></td>
                        <td>
                            ${item.odc_pon_connection ? `
                                <span class="badge badge-primary">${item.odc_pon_connection}</span>
                            ` : '<span class="text-muted">Belum terhubung</span>'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>VLAN ID:</strong></td>
                        <td>
                            ${item.odc_vlan_id ? `
                                <span class="badge badge-secondary">${item.odc_vlan_id}</span>
                            ` : '<span class="text-muted">Belum diset</span>'}
                        </td>
                    </tr>
                </table>
                
                ${item.attenuation_notes ? `
                <h6 class="text-warning mb-3 mt-4">
                    <i class="fas fa-chart-line"></i> Catatan Redaman
                </h6>
                <div class="alert alert-light border">
                    <div class="text-small text-muted" style="white-space: pre-line;">
                        ${item.attenuation_notes}
                    </div>
                </div>
                ` : ''}
            </div>
            
            <!-- Timestamp -->
            <div class="col-md-6">
                <h6 class="text-secondary mb-3">
                    <i class="fas fa-clock"></i> Timestamp
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Dibuat:</strong></td>
                        <td>${formatDate(item.created_at)}</td>
                    </tr>
                    <tr>
                        <td><strong>Diupdate:</strong></td>
                        <td>${formatDate(item.updated_at)}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        ${item.odc_pon_connection ? `
        <script>
            // Load OLT connection detail for ODC popup
            loadOltConnectionDetail(${item.id}, '${item.odc_pon_connection}');
        </script>
        ` : ''}
        
        <script>
            // Load ODC output detail for ODC popup
            loadOdcOutputDetail(${item.id});
        </script>
    `;
}

// Load OLT connection detail for ODC popup
function loadOltConnectionDetail(odcId, ponConnection) {
    const [oltId, ponPort] = ponConnection.split(':');
    
    fetch(`api/items.php?id=${oltId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const olt = data.data;
            const connectionHtml = `
                <div class="border rounded p-2 bg-light">
                    <strong>${olt.name}</strong><br>
                    <small class="text-muted">
                        <i class="fas fa-map-marker-alt"></i> ${olt.address || 'No address'}<br>
                        <i class="fas fa-network-wired"></i> IP: ${olt.ip_address || 'N/A'}<br>
                        <i class="fas fa-plug"></i> Port: ${ponPort}
                    </small>
                </div>
            `;
            document.getElementById(`oltConnectionDetail_${odcId}`).innerHTML = connectionHtml;
        }
    })
    .catch(error => {
        console.error('Error loading OLT connection detail:', error);
        document.getElementById(`oltConnectionDetail_${odcId}`).innerHTML = 
            '<small class="text-danger">Error loading OLT info</small>';
    });
}

// Load ODC output connections detail - ✅ ENHANCED: Sync dengan PON config
function loadOdcOutputDetail(odcId) {
    console.log('🔄 Loading connected ODP for ODC:', odcId);
    
    fetch(`api/sync_olt_odc_data.php?action=get_connected_odp_for_odc&odc_id=${odcId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        let outputHtml = '';
        
        if (data.success && data.data && data.data.length > 0) {
            console.log('✅ Connected ODP loaded:', data.data.length, 'items');
            
            outputHtml = `
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ODP Name</th>
                            <th>Port</th>
                            <th>Capacity</th>
                            <th>Used</th>
                            <th>Connection</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.data.map(odp => {
                            // Generate connection details for each ODP
                            const connectionRows = odp.connection_details.map(conn => `
                                <tr>
                                    <td>
                                        <strong>${odp.name}</strong><br>
                                        <small class="text-muted">${odp.description || 'No description'}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">Port ${conn.port}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">${odp.odp_capacity || 8}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-${(odp.odp_ports_used || 0) > 0 ? 'warning' : 'success'}">
                                            ${odp.odp_ports_used || 0}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            ${conn.cable_length !== '-' ? conn.cable_length + ' m' : ''} 
                                            ${conn.attenuation !== '-' ? conn.attenuation + ' dB' : ''}
                                            ${conn.description ? '<br>' + conn.description : ''}
                                        </small>
                                    </td>
                                </tr>
                            `);
                            return connectionRows.join('');
                        }).join('')}
                    </tbody>
                </table>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Menampilkan ${data.total} ODP yang terhubung via PON config
                    </small>
                </div>
            `;
        } else {
            outputHtml = `
                <div class="alert alert-info py-2">
                    <small>
                        <i class="fas fa-info-circle"></i> 
                        Belum ada ODP yang terhubung ke ODC ini
                    </small>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        ODP akan muncul di sini setelah dikonfigurasi dengan koneksi ODC di form ODP
                    </small>
                </div>
            `;
            console.log('ℹ️ No connected ODP found for ODC:', odcId);
        }
        
        document.getElementById(`odcOutputInfo_${odcId}`).innerHTML = outputHtml;
    })
    .catch(error => {
        console.error('❌ Error loading connected ODP:', error);
        document.getElementById(`odcOutputInfo_${odcId}`).innerHTML = 
            '<div class="alert alert-danger py-2"><small>Error loading ODP connections</small></div>';
    });
}

// Get ODP specific detail information
function getOdpDetailInfo(item) {
    return `
        <div class="row">
            <!-- ODP Configuration Information -->
            <div class="col-md-6">
                <h6 class="text-success mb-3">
                    <i class="fas fa-home"></i> Konfigurasi ODP
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Tipe ODP:</strong></td>
                        <td>
                            <span class="badge badge-info">${item.odp_type === 'pole_mounted' ? 'Pole Mounted' : item.odp_type === 'wall_mounted' ? 'Wall Mounted' : 'Underground'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Splitter Ratio:</strong></td>
                        <td>
                            <span class="badge badge-primary">${item.odp_splitter_ratio || '1:8'}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Kapasitas Customer:</strong></td>
                        <td>
                            <span class="badge badge-danger">${item.odp_capacity || 8}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Input Ports:</strong></td>
                        <td>
                            <span class="badge badge-success">${item.odp_input_ports || 1}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Output Ports:</strong></td>
                        <td>
                            <span class="badge badge-info">${item.odp_output_ports || 8}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Ports Used:</strong></td>
                        <td>
                            <span class="badge badge-secondary">${item.odp_ports_used || 0}</span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- ODC Connections Information -->
            <div class="col-md-6">
                <h6 class="text-info mb-3">
                    <i class="fas fa-project-diagram"></i> ODC Connections
                </h6>
                <div id="odpOdcConnections_${item.id}">
                    <small class="text-muted">Loading ODC connections...</small>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <!-- Connected ONT -->
            <div class="col-md-12">
                <h6 class="text-warning mb-3">
                    <i class="fas fa-home"></i> Connected ONT
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="thead-light">
                            <tr>
                                <th>Port</th>
                                <th>ONT Name</th>
                                <th>Serial Number</th>
                                <th>Customer</th>
                                <th>Paket Layanan</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="odpConnectedOntBody_${item.id}">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">
                                    <i class="fas fa-spinner fa-spin"></i> Loading connected ONT...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Timestamps -->
            <div class="col-md-6">
                <h6 class="text-secondary mb-3">
                    <i class="fas fa-clock"></i> Timestamp
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Dibuat:</strong></td>
                        <td>${formatDate(item.created_at)}</td>
                    </tr>
                    <tr>
                        <td><strong>Diupdate:</strong></td>
                        <td>${formatDate(item.updated_at)}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
            // Load ODC connections and ONT mappings for ODP detail
            loadOdpConnectionsDetail(${item.id});
            loadOdpConnectedOntList(${item.id});
        </script>
    `;
}



// Load ODP connections detail
function loadOdpConnectionsDetail(odpId) {
    fetch(`api/items.php?id=${odpId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const odp = data.data;
            let connectionsHtml = '';
            
            if (odp.pon_config) {
                try {
                    const ponConfig = JSON.parse(odp.pon_config);
                    
                    if (ponConfig.odp_odc_connections && ponConfig.odp_odc_connections.length > 0) {
                        // ✅ ENHANCED: Handle both object and string formats
                        const connectionsPromises = ponConfig.odp_odc_connections.map(async (conn, index) => {
                            let connectionValue = '';
                            let cableLength = '';
                            let attenuation = '';
                            let description = '';
                            
                            if (typeof conn === 'object' && conn.odc_connection) {
                                // Object format: {"odc_connection":"4770:1","cable_length":"30","attenuation":"2.5","description":""}
                                connectionValue = conn.odc_connection;
                                cableLength = conn.cable_length || '-';
                                attenuation = conn.attenuation || '-';
                                description = conn.description || '-';
                            } else if (typeof conn === 'string') {
                                // String format: "4770:1"
                                connectionValue = conn;
                                cableLength = ponConfig.cable_lengths ? ponConfig.cable_lengths[index] || '-' : '-';
                                attenuation = ponConfig.attenuations ? ponConfig.attenuations[index] || '-' : '-';
                                description = ponConfig.descriptions ? ponConfig.descriptions[index] || '-' : '-';
                            }
                            
                            // ✅ ENHANCED: Convert "4770:1" to "ODC Name - Port X"
                            let displayConnection = connectionValue;
                            if (connectionValue.includes(':')) {
                                const [odcId, port] = connectionValue.split(':');
                                try {
                                    const odcResponse = await fetch(`api/items.php?id=${odcId}`, {
                                        method: 'GET',
                                        credentials: 'include'
                                    });
                                    const odcData = await odcResponse.json();
                                    if (odcData.success && odcData.data) {
                                        displayConnection = `${odcData.data.name} - Port ${port}`;
                                    }
                                } catch (e) {
                                    console.warn('Could not load ODC name for ID:', odcId);
                                }
                            }
                            
                            return {
                                displayConnection,
                                cableLength,
                                attenuation,
                                description
                            };
                        });
                        
                        // Wait for all ODC names to be resolved
                        Promise.all(connectionsPromises).then(connections => {
                            const tableHtml = `
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>ODC Connection</th>
                                            <th>Cable Length</th>
                                            <th>Attenuation</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${connections.map(conn => `
                                            <tr>
                                                <td><span class="badge badge-primary">${conn.displayConnection}</span></td>
                                                <td><span class="badge badge-secondary">${conn.cableLength} ${conn.cableLength !== '-' ? 'm' : ''}</span></td>
                                                <td><span class="badge badge-warning">${conn.attenuation} ${conn.attenuation !== '-' ? 'dB' : ''}</span></td>
                                                <td><small class="text-muted">${conn.description}</small></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            `;
                            document.getElementById(`odpOdcConnections_${odpId}`).innerHTML = tableHtml;
                        }).catch(error => {
                            console.error('Error loading ODC connection details:', error);
                            connectionsHtml = '<div class="alert alert-warning py-2"><small>Error loading connection details</small></div>';
                            document.getElementById(`odpOdcConnections_${odpId}`).innerHTML = connectionsHtml;
                        });
                        
                        // Set temporary loading message
                        connectionsHtml = '<small class="text-muted">Loading connection details...</small>';
                    } else {
                        connectionsHtml = '<div class="alert alert-info py-2"><small>Belum ada koneksi ODC yang dikonfigurasi</small></div>';
                    }
                } catch (e) {
                    connectionsHtml = '<div class="alert alert-warning py-2"><small>Error parsing ODC connections data</small></div>';
                }
            } else {
                connectionsHtml = '<div class="alert alert-info py-2"><small>Belum ada konfigurasi koneksi</small></div>';
            }
            
            document.getElementById(`odpOdcConnections_${odpId}`).innerHTML = connectionsHtml;
        }
    })
    .catch(error => {
        console.error('Error loading ODP connections detail:', error);
        document.getElementById(`odpOdcConnections_${odpId}`).innerHTML = 
            '<div class="alert alert-danger py-2"><small>Error loading ODC connections</small></div>';
    });
}

// Load ODP ONT mappings
function loadOdpOntMappings(odpId) {
    fetch(`api/items.php?id=${odpId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const odp = data.data;
            let ontHtml = '';
            
            if (odp.pon_config) {
                try {
                    const ponConfig = JSON.parse(odp.pon_config);
                    
                    if (ponConfig.ont_mappings && ponConfig.ont_mappings.length > 0) {
                        ontHtml = `
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Port</th>
                                        <th>ONT Serial</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ponConfig.ont_mappings.map((ont, index) => `
                                        <tr>
                                            <td><span class="badge badge-info">${index + 1}</span></td>
                                            <td><code>${ont}</code></td>
                                            <td><span class="badge badge-success">Connected</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    } else {
                        ontHtml = '<div class="alert alert-info py-2"><small>Belum ada ONT yang terpasang</small></div>';
                    }
                } catch (e) {
                    ontHtml = '<div class="alert alert-warning py-2"><small>Error parsing ONT mappings data</small></div>';
                }
            } else {
                ontHtml = '<div class="alert alert-info py-2"><small>Belum ada konfigurasi ONT</small></div>';
            }
            
            document.getElementById(`odpOntMappings_${odpId}`).innerHTML = ontHtml;
        }
    })
    .catch(error => {
        console.error('Error loading ODP ONT mappings:', error);
        document.getElementById(`odpOntMappings_${odpId}`).innerHTML = 
            '<div class="alert alert-danger py-2"><small>Error loading ONT mappings</small></div>';
    });
}

// Get Infrastructure item detail information
function getInfrastructureDetailInfo(item) {
    // Special handling for Tiang Tumpu (item_type_id = 2) and HTB (item_type_id = 10)
    if (item.item_type_id == 2 || item.item_type_id == 10) {
        return getTiangTumpuDetailInfo(item);
    }
    
    return `
        <div class="row">
            <!-- Core & Cable Information -->
            <div class="col-md-6">
                <h6 class="text-warning mb-3">
                    <i class="fas fa-network-wired"></i> Informasi Core & Kabel
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Warna Tube:</strong></td>
                        <td>
                            ${item.tube_color_name ? `
                                <span class="color-box" style="background-color: ${item.hex_code}; width: 20px; height: 20px; display: inline-block; margin-right: 8px; border: 1px solid #ccc;"></span>
                                ${item.tube_color_name}
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Warna Core:</strong></td>
                        <td>
                            ${item.core_color_name ? `
                                <span class="color-box" style="background-color: ${item.core_hex_code}; width: 20px; height: 20px; display: inline-block; margin-right: 8px; border: 1px solid #ccc;"></span>
                                ${item.core_color_name}
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Jenis Kabel:</strong></td>
                        <td>
                            ${item.item_cable_type ? `
                                <span class="badge badge-${getCableTypeBadge(item.item_cable_type)}">
                                    ${getCableTypeText(item.item_cable_type)}
                                </span>
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Kapasitas Core:</strong></td>
                        <td>
                            <span class="badge badge-secondary">${item.total_core_capacity || 24} Core</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Core Digunakan:</strong></td>
                        <td>
                            <span class="badge badge-${getCoreUsageBadge(item.core_used, item.total_core_capacity)}">
                                ${item.core_used || 0} Core
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Core Tersedia:</strong></td>
                        <td>
                            <span class="badge badge-${getCoreUsageBadge(item.core_used, item.total_core_capacity)}">
                                ${(item.total_core_capacity || 24) - (item.core_used || 0)} Core
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Splitter Information -->
            <div class="col-md-6">
                <h6 class="text-danger mb-3">
                    <i class="fas fa-project-diagram"></i> Informasi Splitter
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Splitter Utama:</strong></td>
                        <td>
                            ${item.splitter_main_ratio ? `
                                <span class="badge badge-info">${item.splitter_main_ratio}</span>
                            ` : '-'}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Splitter ODP:</strong></td>
                        <td>
                            ${item.splitter_odp_ratio ? `
                                <span class="badge badge-warning">${item.splitter_odp_ratio}</span>
                            ` : '-'}
                        </td>
                    </tr>
                </table>
                
                <h6 class="text-secondary mb-3 mt-4">
                    <i class="fas fa-clock"></i> Timestamp
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Dibuat:</strong></td>
                        <td>${formatDate(item.created_at)}</td>
                    </tr>
                    <tr>
                        <td><strong>Diupdate:</strong></td>
                        <td>${formatDate(item.updated_at)}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
}

// Show item detail modal
function showItemDetailModal(item) {
    let modalHtml = `
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="${getItemIcon(item.item_type_name)}"></i> 
                            ${item.name}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle"></i> Informasi Dasar
                                </h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>ID:</strong></td>
                                        <td>${item.id}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Jenis Item:</strong></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <i class="${getItemIcon(item.item_type_name)}"></i> 
                                                ${item.item_type_name}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Type Item:</strong></td>
                                        <td>${item.item_type || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Harga Item:</strong></td>
                                        <td>${item.item_price ? 'Rp ' + formatNumber(item.item_price) : '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nama:</strong></td>
                                        <td>${item.name}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Deskripsi:</strong></td>
                                        <td>${item.description || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Alamat:</strong></td>
                                        <td>${item.address || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge badge-${getStatusBadgeClass(item.status)}">
                                                ${getStatusText(item.status)}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Location Information -->
                            <div class="col-md-6">
                                <h6 class="text-success mb-3">
                                    <i class="fas fa-map-marker-alt"></i> Informasi Lokasi
                                </h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Latitude:</strong></td>
                                        <td>${item.latitude}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Longitude:</strong></td>
                                        <td>${item.longitude}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Koordinat:</strong></td>
                                        <td>
                                            <code>${item.latitude}, ${item.longitude}</code>
                                            <button class="btn btn-sm btn-outline-secondary ml-2" onclick="copyToClipboard('${item.latitude}, ${item.longitude}')" title="Copy Koordinat">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Google Maps:</strong></td>
                                        <td>
                                            <a href="https://maps.google.com/?q=${item.latitude},${item.longitude}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt"></i> Buka di Maps
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <hr>
                        
                        ${getDetailSpecificInfo(item)}
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <button type="button" class="btn btn-secondary" onclick="$('#itemDetailModal').modal('hide')">
                                    <i class="fas fa-times"></i> Tutup
                                </button>
                                <button type="button" class="btn btn-primary" onclick="editItem(${item.id}); $('#itemDetailModal').modal('hide');">
                                    <i class="fas fa-edit"></i> Edit Item
                                </button>
                                <button type="button" class="btn btn-success" onclick="focusOnItem(${item.id}); $('#itemDetailModal').modal('hide');">
                                    <i class="fas fa-crosshairs"></i> Fokus di Peta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Create modal if doesn't exist
    if (!$('#itemDetailModal').length) {
        $('body').append(`
            <div class="modal fade" id="itemDetailModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">
                                <i class="fas fa-info-circle"></i> Detail Item FTTH
                            </h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="itemDetailModalBody">
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    $('#itemDetailModalBody').html(modalHtml);
    $('#itemDetailModal').modal('show');
}

// Helper function to copy text to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Koordinat disalin ke clipboard', 'success');
    }).catch(function() {
        showNotification('Gagal menyalin koordinat', 'error');
    });
}

// Format date helper
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Export functions to global scope
window.showAddItemModal = showAddItemModal;
window.addNewItem = addNewItem;
window.editItem = editItem;
window.editItemEnhanced = editItemEnhanced;
window.deleteItem = deleteItem;
window.showItemDetail = showItemDetail;
window.showItemList = showItemList;
window.showRouteList = showRouteList;
window.focusOnItem = focusOnItem;
window.focusOnRoute = focusOnRoute;
window.deleteRoute = deleteRoute;
window.editRoute = editRoute;
window.calculateCoreAvailable = calculateCoreAvailable;
window.syncCoreUsageFromRoutes = syncCoreUsageFromRoutes;
window.copyToClipboard = copyToClipboard;
window.toggleSelectAllItems = toggleSelectAllItems;
window.updateSelectedItemCount = updateSelectedItemCount;
window.deleteSelectedItems = deleteSelectedItems;
window.toggleSelectAllRoutes = toggleSelectAllRoutes;
window.updateSelectedRouteCount = updateSelectedRouteCount;
window.deleteSelectedRoutes = deleteSelectedRoutes;
window.showPonConfigurationModal = showPonConfigurationModal;

// Helper functions for display
function getCableTypeBadge(cableType) {
    switch(cableType) {
        case 'backbone': return 'danger';
        case 'distribution': return 'primary'; 
        case 'drop_core': return 'success';
        case 'feeder': return 'info';
        case 'branch': return 'warning';
        default: return 'secondary';
    }
}

// Multiple delete functions for items
function toggleSelectAllItems(checkboxElement) {
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    itemCheckboxes.forEach(checkbox => {
        checkbox.checked = checkboxElement.checked;
    });
    updateSelectedItemCount();
}

function updateSelectedItemCount() {
    const checkedItems = document.querySelectorAll('.item-checkbox:checked');
    const count = checkedItems.length;
    const countElement = document.getElementById('selectedItemCount');
    const deleteButton = document.getElementById('deleteSelectedItems');
    
    if (countElement) countElement.textContent = count;
    if (deleteButton) deleteButton.disabled = count === 0;
    
    // Update select all checkbox
    const selectAll = document.getElementById('selectAllItems');
    const totalItems = document.querySelectorAll('.item-checkbox');
    if (selectAll && totalItems.length > 0) {
        selectAll.checked = count === totalItems.length;
        selectAll.indeterminate = count > 0 && count < totalItems.length;
    }
}

function deleteSelectedItems() {
    const checkedItems = document.querySelectorAll('.item-checkbox:checked');
    const itemIds = Array.from(checkedItems).map(checkbox => checkbox.value);
    
    if (itemIds.length === 0) {
        showNotification('Tidak ada item yang dipilih', 'warning');
        return;
    }
    
    if (!confirm(`Apakah Anda yakin ingin menghapus ${itemIds.length} item yang dipilih?`)) {
        return;
    }
    
    // Disable button and show loading
    const deleteButton = document.getElementById('deleteSelectedItems');
    const originalText = deleteButton.innerHTML;
    deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
    deleteButton.disabled = true;
    
    Promise.all(itemIds.map(id => deleteItemById(id)))
        .then(results => {
            const successful = results.filter(r => r.success).length;
            const failed = results.length - successful;
            
            if (failed === 0) {
                showNotification(`Berhasil menghapus ${successful} item`, 'success');
            } else {
                showNotification(`${successful} item berhasil dihapus, ${failed} gagal`, 'warning');
            }
            
            // Refresh the list
            setTimeout(() => {
                showItemList();
            }, 1000);
        })
        .catch(error => {
            console.error('Error deleting multiple items:', error);
            showNotification('Error menghapus item', 'error');
            deleteButton.innerHTML = originalText;
            deleteButton.disabled = false;
        });
}

function deleteItemById(itemId) {
    return new Promise((resolve) => {
        $.ajax({
            url: 'api/items.php',
            method: 'DELETE',
            dataType: 'json',
            data: JSON.stringify({id: itemId}),
            contentType: 'application/json',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success) {
                    // Remove marker from map
                    if (markers[itemId]) {
                        map.removeLayer(markers[itemId]);
                        delete markers[itemId];
                    }
                    resolve({success: true, id: itemId});
                } else {
                    resolve({success: false, id: itemId, error: response.message});
                }
            },
            error: function() {
                resolve({success: false, id: itemId, error: 'Network error'});
            }
        });
    });
}

// Multiple delete functions for routes
function toggleSelectAllRoutes(checkboxElement) {
    const routeCheckboxes = document.querySelectorAll('.route-checkbox');
    routeCheckboxes.forEach(checkbox => {
        checkbox.checked = checkboxElement.checked;
    });
    updateSelectedRouteCount();
}

function updateSelectedRouteCount() {
    const checkedRoutes = document.querySelectorAll('.route-checkbox:checked');
    const count = checkedRoutes.length;
    const countElement = document.getElementById('selectedRouteCount');
    const deleteButton = document.getElementById('deleteSelectedRoutes');
    
    if (countElement) countElement.textContent = count;
    if (deleteButton) deleteButton.disabled = count === 0;
    
    // Update select all checkbox
    const selectAll = document.getElementById('selectAllRoutes');
    const totalRoutes = document.querySelectorAll('.route-checkbox');
    if (selectAll && totalRoutes.length > 0) {
        selectAll.checked = count === totalRoutes.length;
        selectAll.indeterminate = count > 0 && count < totalRoutes.length;
    }
}

function deleteSelectedRoutes() {
    const checkedRoutes = document.querySelectorAll('.route-checkbox:checked');
    const routeIds = Array.from(checkedRoutes).map(checkbox => checkbox.value);
    
    if (routeIds.length === 0) {
        showNotification('Tidak ada routing yang dipilih', 'warning');
        return;
    }
    
    if (!confirm(`Apakah Anda yakin ingin menghapus ${routeIds.length} routing yang dipilih?`)) {
        return;
    }
    
    // Disable button and show loading
    const deleteButton = document.getElementById('deleteSelectedRoutes');
    const originalText = deleteButton.innerHTML;
    deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
    deleteButton.disabled = true;
    
    Promise.all(routeIds.map(id => deleteRouteById(id)))
        .then(results => {
            const successful = results.filter(r => r.success).length;
            const failed = results.length - successful;
            
            if (failed === 0) {
                showNotification(`Berhasil menghapus ${successful} routing`, 'success');
            } else {
                showNotification(`${successful} routing berhasil dihapus, ${failed} gagal`, 'warning');
            }
            
            // Refresh the list
            setTimeout(() => {
                showRouteList();
            }, 1000);
        })
        .catch(error => {
            console.error('Error deleting multiple routes:', error);
            showNotification('Error menghapus routing', 'error');
            deleteButton.innerHTML = originalText;
            deleteButton.disabled = false;
        });
}

function deleteRouteById(routeId) {
    return new Promise((resolve) => {
        $.ajax({
            url: 'api/routes.php',
            method: 'DELETE',
            dataType: 'json',
            data: JSON.stringify({id: routeId}),
            contentType: 'application/json',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success) {
                    // Remove route from map
                    if (routes[routeId]) {
                        map.removeLayer(routes[routeId]);
                        delete routes[routeId];
                    }
                    resolve({success: true, id: routeId});
                } else {
                    resolve({success: false, id: routeId, error: response.message});
                }
            },
            error: function() {
                resolve({success: false, id: routeId, error: 'Network error'});
            }
        });
    });
}

function getCableTypeText(cableType) {
    switch(cableType) {
        case 'backbone': return 'Backbone';
        case 'distribution': return 'Distribution';
        case 'drop_core': return 'Drop Core';
        case 'feeder': return 'Feeder';
        case 'branch': return 'Branch';
        default: return '-';
    }
}

function getCoreUsageBadge(used, total) {
    if (!used || !total) return 'secondary';
    
    let percentage = (used / total) * 100;
    if (percentage >= 90) return 'danger';
    if (percentage >= 70) return 'warning';
    if (percentage >= 50) return 'info';
    return 'success';
}

// Format number with thousand separators
function formatNumber(num) {
    if (!num) return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Load upstream interface information for detail modal
function loadUpstreamInterfaceDetail(itemId, interfaceId) {
    console.log('🔌 Loading upstream interface detail for item:', itemId, 'interface:', interfaceId);
    
    $.ajax({
        url: 'api/server_interfaces.php?action=get_upstream_interface&interface_id=' + interfaceId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const iface = response.interface;
                console.log('✅ Upstream interface detail loaded:', iface);
                
                const upstreamDetailHtml = `
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-server"></i> ${iface.server_name}
                            </h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Interface:</strong></td>
                                    <td>
                                        <span class="badge badge-primary">${iface.interface_name}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td>${iface.interface_type}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge badge-${iface.oper_status === 'up' ? 'success' : 'danger'}">
                                            ${iface.oper_status.toUpperCase()}
                                        </span>
                                        ${iface.admin_status !== iface.oper_status ? 
                                            `<span class="badge badge-secondary ml-1">Admin: ${iface.admin_status.toUpperCase()}</span>` : ''
                                        }
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>IP Addresses:</strong></td>
                                    <td>
                                        ${iface.ip_addresses !== 'No IP' ? 
                                            `<code>${iface.ip_addresses}</code> <span class="badge badge-info badge-sm">${iface.ip_count} IP(s)</span>` : 
                                            '<span class="text-muted">No IP configured</span>'
                                        }
                                    </td>
                                </tr>
                                ${iface.speed_bps ? `
                                <tr>
                                    <td><strong>Speed:</strong></td>
                                    <td>
                                        <span class="badge badge-success">
                                            ${formatSpeed(iface.speed_bps)}
                                        </span>
                                    </td>
                                </tr>
                                ` : ''}
                                <tr>
                                    <td><strong>Server IP:</strong></td>
                                    <td>
                                        ${iface.server_ip ? 
                                            `<code>${iface.server_ip}</code>
                                             <button class="btn btn-sm btn-outline-secondary ml-1" onclick="copyToClipboard('${iface.server_ip}')" title="Copy Server IP">
                                                <i class="fas fa-copy"></i>
                                             </button>` : 
                                            '<span class="text-muted">No server IP</span>'
                                        }
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Action Buttons -->
                            <div class="text-center mt-3">
                                ${iface.server_ip ? `
                                <button class="btn btn-sm btn-outline-primary mr-2" onclick="pingHost('${iface.server_ip}')" title="Ping Server">
                                    <i class="fas fa-satellite-dish"></i> Ping Server
                                </button>
                                ` : ''}
                                <button class="btn btn-sm btn-outline-info" onclick="showInterfaceDetails(${iface.device_id})" title="View All Interfaces">
                                    <i class="fas fa-ethernet"></i> All Interfaces
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Update the detail content
                const detailElement = document.getElementById(`upstreamInterfaceDetail_${itemId}`);
                if (detailElement) {
                    detailElement.innerHTML = upstreamDetailHtml;
                }
            } else {
                console.warn('❌ Failed to load upstream interface detail:', response.message);
                const detailElement = document.getElementById(`upstreamInterfaceDetail_${itemId}`);
                if (detailElement) {
                    detailElement.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Unable to load interface details: ${response.message}
                        </div>
                    `;
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error loading upstream interface detail:', error);
            const detailElement = document.getElementById(`upstreamInterfaceDetail_${itemId}`);
            if (detailElement) {
                detailElement.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i>
                        Error loading interface details: ${error}
                    </div>
                `;
            }
        }
    });
}

// Format speed in human readable format
function formatSpeed(speedBps) {
    if (!speedBps) return 'Unknown';
    
    const units = [
        { name: 'Gbps', value: 1000000000 },
        { name: 'Mbps', value: 1000000 },
        { name: 'Kbps', value: 1000 },
        { name: 'bps', value: 1 }
    ];
    
    for (let unit of units) {
        if (speedBps >= unit.value) {
            return (speedBps / unit.value).toFixed(1) + ' ' + unit.name;
        }
    }
    
    return speedBps + ' bps';
}

// Export helper functions
window.getCableTypeBadge = getCableTypeBadge;
window.getCableTypeText = getCableTypeText;
window.getCoreUsageBadge = getCoreUsageBadge;
window.formatNumber = formatNumber;
window.loadUpstreamInterfaceDetail = loadUpstreamInterfaceDetail;
window.formatSpeed = formatSpeed;

/**
 * Load ONT-ODP connection detail for popup
 */
function loadOntOdpConnectionDetail(ontId) {
    fetch(`api/items.php?action=get&id=${ontId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const item = data.data;
            let connectionHtml = '';
            
            if (item.ont_connected_odp_id && item.ont_connected_port) {
                // Fetch ODP details
                fetch(`api/items.php?action=get&id=${item.ont_connected_odp_id}`, {
                    method: 'GET',
                    credentials: 'include'
                })
                .then(response => response.json())
                .then(odpData => {
                    if (odpData.success && odpData.data) {
                        const odp = odpData.data;
                        connectionHtml = `
                            <div class="alert alert-success py-2 mb-2">
                                <small>
                                    <i class="fas fa-check-circle"></i>
                                    <strong>Terhubung ke ODP:</strong>
                                </small>
                            </div>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>ODP Name:</strong></td>
                                    <td>
                                        <a href="#" onclick="showItemDetail(${odp.id})" class="text-primary">
                                            <i class="fas fa-project-diagram"></i> ${odp.name}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Port ODP:</strong></td>
                                    <td><span class="badge badge-primary">Port ${item.ont_connected_port}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Kapasitas ODP:</strong></td>
                                    <td><span class="badge badge-info">${odp.odp_capacity || 8} ports</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Alamat ODP:</strong></td>
                                    <td>${odp.address || '<span class="text-muted">Tidak diset</span>'}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status ODP:</strong></td>
                                    <td>
                                        <span class="badge badge-${odp.status === 'active' ? 'success' : 'secondary'}">
                                            ${odp.status || 'active'}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        `;
                    } else {
                        connectionHtml = `
                            <div class="alert alert-warning py-2">
                                <small>
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Error:</strong> ODP ID ${item.ont_connected_odp_id} tidak ditemukan
                                </small>
                            </div>
                        `;
                    }
                    
                    const container = document.getElementById(`ontOdpConnectionDetail_${ontId}`);
                    if (container) {
                        container.innerHTML = connectionHtml;
                    }
                })
                .catch(error => {
                    console.error('Error loading ODP details:', error);
                    const container = document.getElementById(`ontOdpConnectionDetail_${ontId}`);
                    if (container) {
                        container.innerHTML = `
                            <div class="alert alert-danger py-2">
                                <small>
                                    <i class="fas fa-times-circle"></i>
                                    <strong>Error:</strong> Gagal memuat detail ODP
                                </small>
                            </div>
                        `;
                    }
                });
            } else {
                connectionHtml = `
                    <div class="alert alert-secondary py-2">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            <strong>Info:</strong> ONT belum terhubung ke ODP
                        </small>
                    </div>
                    <p class="text-muted mb-0">
                        <small>Untuk menghubungkan ONT ke ODP, edit item ini dan pilih ODP serta port yang tersedia.</small>
                    </p>
                `;
                
                const container = document.getElementById(`ontOdpConnectionDetail_${ontId}`);
                if (container) {
                    container.innerHTML = connectionHtml;
                }
            }
        } else {
            console.error('Failed to load ONT details:', data.message);
        }
    })
    .catch(error => {
        console.error('Error loading ONT-ODP connection detail:', error);
        const container = document.getElementById(`ontOdpConnectionDetail_${ontId}`);
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger py-2">
                    <small>
                        <i class="fas fa-times-circle"></i>
                        <strong>Error:</strong> Gagal memuat informasi koneksi
                    </small>
                </div>
            `;
        }
    });
}

/**
 * Load connected ONT list for ODP popup
 */
function loadOdpConnectedOntList(odpId) {
    fetch(`api/items.php?action=list&item_type_id=6&status=active`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
            const allOnt = data.data;
            const connectedOnt = allOnt.filter(ont => 
                ont.ont_connected_odp_id && parseInt(ont.ont_connected_odp_id) === parseInt(odpId)
            );
            
            const tbody = document.getElementById(`odpConnectedOntBody_${odpId}`);
            if (!tbody) return;
            
            if (connectedOnt.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">
                            <i class="fas fa-info-circle"></i> Belum ada ONT yang terhubung ke ODP ini
                        </td>
                    </tr>
                `;
                return;
            }
            
            // Sort by port number
            connectedOnt.sort((a, b) => (a.ont_connected_port || 0) - (b.ont_connected_port || 0));
            
            let html = '';
            connectedOnt.forEach(ont => {
                const statusColor = 
                    ont.ont_connection_status === 'connected' ? 'success' : 
                    ont.ont_connection_status === 'disconnected' ? 'danger' :
                    ont.ont_connection_status === 'maintenance' ? 'warning' : 'secondary';
                
                const statusText = 
                    ont.ont_connection_status === 'connected' ? 'Connected' : 
                    ont.ont_connection_status === 'disconnected' ? 'Disconnected' :
                    ont.ont_connection_status === 'maintenance' ? 'Maintenance' : 
                    ont.ont_connection_status === 'suspended' ? 'Suspended' : 'Unknown';
                    
                html += `
                    <tr>
                        <td>
                            <span class="badge badge-primary">Port ${ont.ont_connected_port || 'N/A'}</span>
                        </td>
                        <td>
                            <strong>${ont.name}</strong>
                            ${ont.ont_model ? `<br><small class="text-muted">${ont.ont_model}</small>` : ''}
                        </td>
                        <td>
                            ${ont.ont_serial_number ? `<code class="small">${ont.ont_serial_number}</code>` : '<span class="text-muted">-</span>'}
                        </td>
                        <td>
                            ${ont.ont_customer_name || '<span class="text-muted">-</span>'}
                            ${ont.ont_customer_address ? `<br><small class="text-muted">${ont.ont_customer_address}</small>` : ''}
                        </td>
                        <td>
                            ${ont.ont_service_plan ? `<span class="badge badge-info">${ont.ont_service_plan}</span>` : '<span class="text-muted">-</span>'}
                        </td>
                        <td>
                            <span class="badge badge-${statusColor}">${statusText}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="showItemDetail(${ont.id})" title="Detail ONT">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary ml-1" onclick="editItem(${ont.id})" title="Edit ONT">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            console.log(`✅ Loaded ${connectedOnt.length} connected ONT for ODP ${odpId}`);
        } else {
            console.error('Failed to load ONT list:', data.message);
            const tbody = document.getElementById(`odpConnectedOntBody_${odpId}`);
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-3">
                            <i class="fas fa-times-circle"></i> Error memuat data ONT
                        </td>
                    </tr>
                `;
            }
        }
    })
    .catch(error => {
        console.error('Error loading connected ONT list:', error);
        const tbody = document.getElementById(`odpConnectedOntBody_${odpId}`);
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger py-3">
                        <i class="fas fa-times-circle"></i> Error memuat data ONT
                    </td>
                </tr>
            `;
        }
    });
}

// Export core functions for popup access
window.editItem = editItem;
window.showItemDetail = showItemDetail;
window.loadOntOdpConnectionDetail = loadOntOdpConnectionDetail;
window.loadOdpConnectedOntList = loadOdpConnectedOntList;

// ===================================
// TABLE FILTERING FUNCTIONS
// ===================================

// Filter item table
function filterItemTable() {
    const searchName = document.getElementById('itemSearchName').value.toLowerCase();
    const searchType = document.getElementById('itemSearchType').value;
    const searchStatus = document.getElementById('itemSearchStatus').value;
    
    const table = document.getElementById('itemTable');
    if (!table) return;
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const nameCell = row.cells[2]; // Nama column
        const typeCell = row.cells[1]; // Jenis column
        const statusCell = row.cells[5]; // Status column
        
        const name = nameCell.textContent.toLowerCase();
        const type = typeCell.textContent.trim();
        const status = statusCell.querySelector('.badge').textContent.toLowerCase();
        
        let showRow = true;
        
        // Filter by name
        if (searchName && !name.includes(searchName)) {
            showRow = false;
        }
        
        // Filter by type
        if (searchType && type !== searchType) {
            showRow = false;
        }
        
        // Filter by status
        if (searchStatus) {
            const statusMap = {
                'active': 'aktif',
                'inactive': 'tidak aktif',
                'maintenance': 'maintenance'
            };
            if (status !== statusMap[searchStatus]) {
                showRow = false;
            }
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Update total count
    const totalCountElement = document.querySelector('.table-total-count');
    if (totalCountElement) {
        totalCountElement.innerHTML = `<i class="fas fa-list"></i> Menampilkan: ${visibleCount} dari ${rows.length} item`;
    }
}

// Clear item filters
function clearItemFilters() {
    document.getElementById('itemSearchName').value = '';
    document.getElementById('itemSearchType').value = '';
    document.getElementById('itemSearchStatus').value = '';
    filterItemTable();
}

// Filter route table
function filterRouteTable() {
    const searchFrom = document.getElementById('routeSearchFrom').value.toLowerCase();
    const searchTo = document.getElementById('routeSearchTo').value.toLowerCase();
    const searchCableType = document.getElementById('routeSearchCableType').value;
    const searchStatus = document.getElementById('routeSearchStatus').value;
    
    const table = document.getElementById('routeTable');
    if (!table) return;
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const fromCell = row.cells[1]; // Dari column
        const toCell = row.cells[2]; // Ke column
        const cableTypeCell = row.cells[4]; // Tipe Kabel column
        const statusCell = row.cells[6]; // Status column
        
        const from = fromCell.textContent.toLowerCase();
        const to = toCell.textContent.toLowerCase();
        const cableType = cableTypeCell.textContent.trim();
        const status = statusCell.querySelector('.badge').textContent.toLowerCase();
        
        let showRow = true;
        
        // Filter by from item
        if (searchFrom && !from.includes(searchFrom)) {
            showRow = false;
        }
        
        // Filter by to item
        if (searchTo && !to.includes(searchTo)) {
            showRow = false;
        }
        
        // Filter by cable type
        if (searchCableType && cableType !== searchCableType) {
            showRow = false;
        }
        
        // Filter by status
        if (searchStatus) {
            const statusMap = {
                'planned': 'planned',
                'installed': 'installed',
                'maintenance': 'maintenance'
            };
            if (status !== statusMap[searchStatus]) {
                showRow = false;
            }
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Update total count
    const totalCountElement = document.querySelector('.table-total-count');
    if (totalCountElement) {
        totalCountElement.innerHTML = `<i class="fas fa-route"></i> Menampilkan: ${visibleCount} dari ${rows.length} routing`;
    }
}

// Clear route filters
function clearRouteFilters() {
    document.getElementById('routeSearchFrom').value = '';
    document.getElementById('routeSearchTo').value = '';
    document.getElementById('routeSearchCableType').value = '';
    document.getElementById('routeSearchStatus').value = '';
    filterRouteTable();
}

// Export filtering functions for global access
window.filterItemTable = filterItemTable;
window.clearItemFilters = clearItemFilters;
window.filterRouteTable = filterRouteTable;
window.clearRouteFilters = clearRouteFilters;