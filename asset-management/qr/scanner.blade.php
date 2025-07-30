{{-- resources/views/asset-management/qr/scanner.blade.php --}}
@extends('layouts.app')

@section('title', 'QR Code Scanner')

@push('before-styles')
<style>
    .scanner-container {
        position: relative;
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .scanner-viewfinder {
        position: relative;
        width: 100%;
        height: 300px;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .scanner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(
            to bottom,
            rgba(0,0,0,0.6) 20%,
            transparent 20%,
            transparent 80%,
            rgba(0,0,0,0.6) 80%
        ),
        linear-gradient(
            to right,
            rgba(0,0,0,0.6) 20%,
            transparent 20%,
            transparent 80%,
            rgba(0,0,0,0.6) 80%
        );
        pointer-events: none;
    }
    
    .scanner-frame {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 200px;
        height: 200px;
        border: 2px solid #ffffff;
        border-radius: 8px;
    }
    
    .scanner-frame::before,
    .scanner-frame::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        border: 3px solid #00ff00;
    }
    
    .scanner-frame::before {
        top: -3px;
        left: -3px;
        border-right: none;
        border-bottom: none;
    }
    
    .scanner-frame::after {
        bottom: -3px;
        right: -3px;
        border-left: none;
        border-top: none;
    }
    
    .scan-line {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, #00ff00, transparent);
        animation: scan 2s linear infinite;
    }
    
    @keyframes scan {
        0% { top: 0; }
        100% { top: 100%; }
    }
    
    .scanner-info {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .asset-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid #e3e6f0;
        transition: all 0.3s ease;
    }
    
    .asset-card:hover {
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .status-available {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .status-in-use {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .status-damaged {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .cable-progress {
        height: 8px;
        background-color: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin: 0.5rem 0;
    }
    
    .cable-progress-bar {
        height: 100%;
        transition: width 0.3s ease;
    }
    
    .cable-info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1rem;
    }
    
    .device-info-card {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1rem;
    }
    
    .scan-history-item {
        background: white;
        border-left: 4px solid #007bff;
        border-radius: 0 8px 8px 0;
        padding: 1rem;
        margin-bottom: 0.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    @media (max-width: 768px) {
        .scanner-viewfinder {
            height: 250px;
        }
        
        .scanner-frame {
            width: 150px;
            height: 150px;
        }
        
        .container-xxl {
            padding-left: 1rem;
            padding-right: 1rem;
        }
    }
    
    .floating-action-buttons {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        z-index: 1000;
    }
    
    .fab {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        transition: all 0.3s ease;
    }
    
    .fab:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold py-3 mb-2">
                <span class="text-muted fw-light">Asset Management /</span> QR Scanner
            </h4>
            <p class="text-muted mb-0">Scan QR codes untuk melihat detail aset dan update status</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary" id="toggleCamera">
                <i class="ti ti-camera" id="cameraIcon"></i>
                <span id="cameraText">Start Camera</span>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="manualInput">
                <i class="ti ti-keyboard"></i>
                Manual Input
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-scan ti-md"></i>
                        </span>
                    </div>
                    <span class="d-block mb-1">Today's Scans</span>
                    <h4 class="card-title mb-1" id="todayScans">0</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-check ti-md"></i>
                        </span>
                    </div>
                    <span class="d-block mb-1">Available Assets</span>
                    <h4 class="card-title mb-1" id="availableAssets">-</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="ti ti-user ti-md"></i>
                        </span>
                    </div>
                    <span class="d-block mb-1">In Use</span>
                    <h4 class="card-title mb-1" id="inUseAssets">-</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="ti ti-cable ti-md"></i>
                        </span>
                    </div>
                    <span class="d-block mb-1">Cable Length</span>
                    <h4 class="card-title mb-1" id="cableLength">-</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Scanner Section -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-qrcode me-2"></i>QR Code Scanner
                    </h5>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="ti ti-settings"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" id="switchCamera"><i class="ti ti-refresh me-2"></i>Switch Camera</a></li>
                            <li><a class="dropdown-item" href="#" id="fullscreen"><i class="ti ti-maximize me-2"></i>Fullscreen</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" id="scanHistory"><i class="ti ti-history me-2"></i>Scan History</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="scanner-container">
                        <div class="scanner-viewfinder" id="scannerViewfinder">
                            <video id="scannerVideo" width="100%" height="100%" style="object-fit: cover; display: none;"></video>
                            <div class="scanner-overlay"></div>
                            <div class="scanner-frame">
                                <div class="scan-line"></div>
                            </div>
                            <div class="position-absolute top-50 start-50 translate-middle text-white text-center" id="scannerPlaceholder">
                                <i class="ti ti-camera ti-4x mb-3"></i>
                                <p>Click "Start Camera" to begin scanning</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="scanner-info mt-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="badge bg-label-info" id="scannerStatus">Camera Off</span>
                                <small class="text-muted ms-2" id="scannerMessage">Ready to scan QR codes</small>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="flashToggle" style="display: none;">
                                    <i class="ti ti-bulb" id="flashIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Results Section -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-info-circle me-2"></i>Scan Results
                    </h5>
                </div>
                <div class="card-body">
                    <div id="scanResults">
                        <div class="text-center text-muted py-5">
                            <i class="ti ti-scan ti-4x mb-3"></i>
                            <p>Scan a QR code to view asset details</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Scans -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-history me-2"></i>Recent Scans
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearHistory">
                        <i class="ti ti-trash me-1"></i>Clear History
                    </button>
                </div>
                <div class="card-body">
                    <div id="recentScans">
                        <div class="text-center text-muted">
                            <p>No recent scans</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manual Input Modal -->
<div class="modal fade" id="manualInputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-keyboard me-2"></i>Manual QR Code Input
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="manualInputForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">QR Code</label>
                        <input type="text" class="form-control" id="manualQRCode" placeholder="Enter QR code manually..." required>
                        <div class="form-text">Enter the QR code text if you cannot scan it</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-search me-1"></i>Lookup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Asset Action Modal -->
<div class="modal fade" id="assetActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-tool me-2"></i>Asset Actions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assetActionContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Buttons -->
<div class="floating-action-buttons">
    <button type="button" class="fab btn btn-primary" id="fabScan" title="Quick Scan">
        <i class="ti ti-qrcode"></i>
    </button>
    <button type="button" class="fab btn btn-success" id="fabActions" title="Quick Actions" style="display: none;">
        <i class="ti ti-tool"></i>
    </button>
</div>
@endsection

@push('after-scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
$(document).ready(function() {
    let video = document.getElementById('scannerVideo');
    let canvas = document.createElement('canvas');
    let context = canvas.getContext('2d');
    let scanning = false;
    let stream = null;
    let scanHistory = JSON.parse(localStorage.getItem('qr_scan_history') || '[]');
    let currentAsset = null;
    
    // Initialize
    updateScanHistory();
    loadQuickStats();

    // Camera controls
    $('#toggleCamera').on('click', function() {
        if (scanning) {
            stopScanning();
        } else {
            startScanning();
        }
    });

    async function startScanning() {
        try {
            // Request camera permission
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment', // Use back camera on mobile
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            });
            
            video.srcObject = stream;
            video.style.display = 'block';
            $('#scannerPlaceholder').hide();
            
            // Wait for video to load
            video.onloadedmetadata = function() {
                video.play();
                scanning = true;
                updateScannerUI();
                requestAnimationFrame(scanQRCode);
            };
            
        } catch (err) {
            console.error('Error accessing camera:', err);
            toastr.error('Tidak dapat mengakses kamera: ' + err.message);
            $('#scannerMessage').text('Camera access denied');
        }
    }

    function stopScanning() {
        scanning = false;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.style.display = 'none';
        $('#scannerPlaceholder').show();
        updateScannerUI();
    }

    function updateScannerUI() {
        const isScanning = scanning;
        $('#toggleCamera').toggleClass('btn-outline-primary', !isScanning)
                          .toggleClass('btn-danger', isScanning);
        $('#cameraIcon').toggleClass('ti-camera', !isScanning)
                       .toggleClass('ti-camera-off', isScanning);
        $('#cameraText').text(isScanning ? 'Stop Camera' : 'Start Camera');
        $('#scannerStatus').toggleClass('bg-label-info', !isScanning)
                          .toggleClass('bg-label-success', isScanning)
                          .text(isScanning ? 'Scanning...' : 'Camera Off');
        $('#scannerMessage').text(isScanning ? 'Point camera at QR code' : 'Ready to scan QR codes');
        $('#flashToggle').toggle(isScanning);
    }

    function scanQRCode() {
        if (!scanning || !video.videoWidth || !video.videoHeight) {
            if (scanning) requestAnimationFrame(scanQRCode);
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        
        if (code) {
            handleQRCodeDetected(code.data);
        }
        
        if (scanning) {
            requestAnimationFrame(scanQRCode);
        }
    }

    function handleQRCodeDetected(qrData) {
        console.log('QR Code detected:', qrData);
        
        // Prevent duplicate scans
        if (currentAsset && currentAsset.qr_code === qrData) {
            return;
        }
        
        // Vibrate if supported
        if (navigator.vibrate) {
            navigator.vibrate(200);
        }
        
        // Visual feedback
        $('#scannerMessage').text('QR Code detected! Processing...');
        
        // Look up asset
        lookupAsset(qrData);
        
        // Add to scan history
        addToScanHistory(qrData);
    }

    function lookupAsset(qrCode) {
        $.ajax({
            url: '{{ route("asset-management.qr.lookup") }}',
            method: 'POST',
            data: {
                qr_code: qrCode,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                displayAssetInfo(response.tracked_asset);
                currentAsset = response.tracked_asset;
                $('#fabActions').show();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'QR code tidak ditemukan');
                $('#scanResults').html(`
                    <div class="alert alert-warning">
                        <i class="ti ti-alert-triangle me-2"></i>
                        QR Code tidak ditemukan: <code>${qrCode}</code>
                    </div>
                `);
            }
        });
    }

    function displayAssetInfo(asset) {
        let statusBadge = getStatusBadge(asset.current_status);
        let assetTypeIcon = getAssetTypeIcon(asset.asset.asset_sub_type);
        
        let lengthInfo = '';
        if (asset.initial_length) {
            const percentage = ((asset.current_length / asset.initial_length) * 100).toFixed(1);
            const progressColor = percentage > 80 ? 'success' : (percentage > 50 ? 'warning' : 'danger');
            
            lengthInfo = `
                <div class="cable-info-card">
                    <h6><i class="ti ti-cable me-2"></i>Cable Information</h6>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4>${formatNumber(asset.current_length)}m</h4>
                                <small>Current Length</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4>${formatNumber(asset.initial_length)}m</h4>
                                <small>Initial Length</small>
                            </div>
                        </div>
                    </div>
                    <div class="cable-progress mt-2">
                        <div class="cable-progress-bar bg-${progressColor}" style="width: ${percentage}%"></div>
                    </div>
                    <div class="text-center mt-1">
                        <small>${percentage}% remaining • ${formatNumber(asset.initial_length - asset.current_length)}m used</small>
                    </div>
                </div>
            `;
        }
        
        let deviceInfo = '';
        if (asset.serial_number || asset.mac_address) {
            deviceInfo = `
                <div class="device-info-card">
                    <h6><i class="ti ti-device-desktop me-2"></i>Device Information</h6>
                    ${asset.serial_number ? `<p><strong>Serial Number:</strong> ${asset.serial_number}</p>` : ''}
                    ${asset.mac_address ? `<p><strong>MAC Address:</strong> ${asset.mac_address}</p>` : ''}
                </div>
            `;
        }

        const html = `
            <div class="asset-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-1">
                                <i class="ti ${assetTypeIcon} me-2"></i>
                                ${asset.asset.name}
                            </h5>
                            <p class="text-muted mb-0">${asset.asset.asset_code}</p>
                        </div>
                        <div class="text-end">
                            ${statusBadge}
                            <div class="mt-1">
                                <small class="text-muted">QR: ${asset.qr_code}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Warehouse</small>
                            <div class="fw-semibold">${asset.current_warehouse?.name || 'Unknown'}</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Category</small>
                            <div class="fw-semibold">${asset.asset.asset_category?.name || '-'}</div>
                        </div>
                    </div>
                    
                    ${lengthInfo}
                    ${deviceInfo}
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary btn-sm me-2" onclick="showAssetActions('${asset.id}')">
                            <i class="ti ti-tool me-1"></i>Actions
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewFullDetails('${asset.id}')">
                            <i class="ti ti-eye me-1"></i>Full Details
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('#scanResults').html(html);
    }

    function addToScanHistory(qrCode) {
        const scan = {
            qr_code: qrCode,
            timestamp: new Date().toISOString(),
            location: 'Scanner'
        };
        
        scanHistory.unshift(scan);
        scanHistory = scanHistory.slice(0, 10); // Keep last 10 scans
        
        localStorage.setItem('qr_scan_history', JSON.stringify(scanHistory));
        updateScanHistory();
        
        // Update today's scan count
        $('#todayScans').text(scanHistory.filter(s => 
            new Date(s.timestamp).toDateString() === new Date().toDateString()
        ).length);
    }

    function updateScanHistory() {
        if (scanHistory.length === 0) {
            $('#recentScans').html('<div class="text-center text-muted"><p>No recent scans</p></div>');
            return;
        }

        let html = '';
        scanHistory.forEach(function(scan) {
            const time = new Date(scan.timestamp).toLocaleTimeString();
            const date = new Date(scan.timestamp).toLocaleDateString();
            
            html += `
                <div class="scan-history-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <code class="text-primary">${scan.qr_code}</code>
                            <small class="text-muted d-block">${scan.location}</small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">${time}</small>
                            <small class="text-muted d-block">${date}</small>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#recentScans').html(html);
    }

    // Manual input
    $('#manualInput').on('click', function() {
        $('#manualInputModal').modal('show');
    });

    $('#manualInputForm').on('submit', function(e) {
        e.preventDefault();
        const qrCode = $('#manualQRCode').val().trim();
        if (qrCode) {
            lookupAsset(qrCode);
            addToScanHistory(qrCode);
            $('#manualInputModal').modal('hide');
            $('#manualQRCode').val('');
        }
    });

    // Clear history
    $('#clearHistory').on('click', function() {
        scanHistory = [];
        localStorage.removeItem('qr_scan_history');
        updateScanHistory();
        $('#todayScans').text('0');
        toastr.success('Scan history cleared');
    });

    // Floating Action Buttons
    $('#fabScan').on('click', function() {
        if (!scanning) {
            startScanning();
        }
    });

    $('#fabActions').on('click', function() {
        if (currentAsset) {
            showAssetActions(currentAsset.id);
        }
    });

    // Global functions
    window.showAssetActions = function(assetId) {
        $('#assetActionModal').modal('show');
        // Load action options based on asset status
        loadAssetActions(assetId);
    };

    window.viewFullDetails = function(assetId) {
        // Open full asset details in new tab or modal
        window.open(`{{ route('asset-management.inventory.tracked-asset-detail', '') }}/${assetId}`, '_blank');
    };

    function loadAssetActions(assetId) {
        // Implementation for asset action modal
        $('#assetActionContent').html('<div class="text-center p-3"><i class="spinner-border"></i> Loading actions...</div>');
        
        // This would load available actions based on asset status
        // For now, show placeholder
        setTimeout(() => {
            $('#assetActionContent').html(`
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary">
                        <i class="ti ti-edit me-2"></i>Update Status
                    </button>
                    <button type="button" class="btn btn-outline-success">
                        <i class="ti ti-truck me-2"></i>Move to Warehouse
                    </button>
                    <button type="button" class="btn btn-outline-warning">
                        <i class="ti ti-tool me-2"></i>Mark for Maintenance
                    </button>
                    <button type="button" class="btn btn-outline-info">
                        <i class="ti ti-printer me-2"></i>Print QR Label
                    </button>
                </div>
            `);
        }, 1000);
    }

    function loadQuickStats() {
        // Load quick statistics
        // This would be an API call to get current stats
        $('#availableAssets').text('Loading...');
        $('#inUseAssets').text('Loading...');
        $('#cableLength').text('Loading...');
    }

    // Utility functions
    function getStatusBadge(status) {
        const statusConfig = {
            'available': { class: 'status-available', icon: 'ti-check', text: 'Available' },
            'loaned': { class: 'status-in-use', icon: 'ti-user', text: 'With Technician' },
            'installed': { class: 'status-in-use', icon: 'ti-home', text: 'Installed' },
            'damaged': { class: 'status-damaged', icon: 'ti-alert-circle', text: 'Damaged' }
        };
        
        const config = statusConfig[status] || { class: 'status-available', icon: 'ti-help', text: status };
        return `<span class="status-badge ${config.class}"><i class="ti ${config.icon} me-1"></i>${config.text}</span>`;
    }

    function getAssetTypeIcon(subType) {
        if (!subType) return 'ti-package';
        if (subType.includes('cable')) return 'ti-cable';
        if (subType.includes('router') || subType.includes('switch')) return 'ti-router';
        if (subType.includes('ont')) return 'ti-device-desktop';
        return 'ti-package';
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    // Toast configuration
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 3000
    };
});
</script>
@endpush