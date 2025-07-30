@extends('layouts.app')

@section('title')
QR Code Scanner
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Asset Management /</span> QR Scanner</h4>

    <div class="row">
        <!-- Scanner Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📱 QR Code Scanner</h5>
                </div>
                <div class="card-body">
                    <!-- Camera Scanner -->
                    <div id="qr-reader" style="width: 100%; height: 300px; border: 2px dashed #ccc; border-radius: 8px; position: relative;">
                        <div id="scanner-placeholder" class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                            <i class="ti ti-camera ti-lg mb-2"></i>
                            <p class="mb-2">Click "Start Scanner" to begin</p>
                            <small>Position QR code within the scanning area</small>
                        </div>
                    </div>
                    
                    <!-- Scanner Controls -->
                    <div class="mt-3 d-flex gap-2 justify-content-center">
                        <button id="start-scanner" class="btn btn-primary">
                            <i class="ti ti-camera me-1"></i>Start Scanner
                        </button>
                        <button id="stop-scanner" class="btn btn-secondary" style="display: none;">
                            <i class="ti ti-camera-off me-1"></i>Stop Scanner
                        </button>
                        <button id="switch-camera" class="btn btn-outline-primary" style="display: none;">
                            <i class="ti ti-refresh me-1"></i>Switch Camera
                        </button>
                    </div>

                    <!-- Manual Input Fallback -->
                    <hr class="my-4">
                    <div class="manual-input">
                        <h6>Manual QR Input</h6>
                        <div class="input-group">
                            <input type="text" id="manual-qr" class="form-control" placeholder="AST-XXX-999" maxlength="11">
                            <button class="btn btn-outline-success" type="button" onclick="processManualQR()">
                                <i class="ti ti-search"></i>
                            </button>
                        </div>
                        <small class="text-muted">Format: AST-RTR-001, AST-CBL-045, etc.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Results Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🔍 Scan Results</h5>
                </div>
                <div class="card-body">
                    <!-- Initial State -->
                    <div id="scan-initial" class="text-center py-5">
                        <i class="ti ti-scan ti-xl text-muted mb-3"></i>
                        <p class="text-muted">No asset scanned yet</p>
                        <small>Scan a QR code to view asset details</small>
                    </div>

                    <!-- Asset Details -->
                    <div id="asset-details" style="display: none;">
                        <div class="row">
                            <div class="col-12">
                                <div class="asset-info mb-3">
                                    <h6 id="asset-name" class="mb-1">-</h6>
                                    <small id="asset-category" class="text-muted">-</small>
                                </div>
                                
                                <div class="asset-meta">
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">QR Code:</small>
                                            <div id="asset-qr-code" class="fw-semibold">-</div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Price:</small>
                                            <div id="asset-price" class="fw-semibold text-success">-</div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <small class="text-muted">Stock:</small>
                                            <div id="asset-stock" class="fw-semibold">-</div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Warehouse:</small>
                                            <div id="asset-warehouse" class="fw-semibold">-</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="mt-4">
                                    <button id="add-to-checkout" class="btn btn-success w-100 mb-2" style="display: none;">
                                        <i class="ti ti-shopping-cart me-1"></i>Add to Checkout
                                    </button>
                                    <button id="view-asset-details" class="btn btn-outline-primary w-100" style="display: none;">
                                        <i class="ti ti-eye me-1"></i>View Full Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error State -->
                    <div id="scan-error" style="display: none;" class="text-center py-4">
                        <i class="ti ti-alert-circle ti-xl text-danger mb-3"></i>
                        <p id="error-message" class="text-danger mb-2">-</p>
                        <button class="btn btn-outline-primary btn-sm" onclick="resetScanner()">
                            <i class="ti ti-refresh me-1"></i>Try Again
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Scans -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">📋 Recent Scans</h6>
                </div>
                <div class="card-body">
                    <ul id="recent-scans" class="list-unstyled mb-0">
                        <li class="text-muted text-center py-2">
                            <small>No recent scans</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add to Checkout Modal -->
<div class="modal fade" id="addToCheckoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add to Checkout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="add-checkout-form">
                    <input type="hidden" id="checkout-asset-id">
                    <div class="mb-3">
                        <label class="form-label">Asset</label>
                        <input type="text" id="checkout-asset-name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="checkout-quantity" class="form-control" min="0.001" step="0.001" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Technician</label>
                        <select id="checkout-technician" class="form-select">
                            <option value="">Select Technician...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitCheckout()">Add to Checkout</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
$(document).ready(function() {
    let html5QrcodeScanner = null;
    let currentCameraId = null;
    let cameras = [];
    let currentCameraIndex = 0;
    let recentScans = [];

    // Initialize scanner
    function initializeScanner() {
        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                cameras = devices;
                $('#start-scanner').prop('disabled', false);
                if (devices.length > 1) {
                    $('#switch-camera').show();
                }
            } else {
                toastr.error('No cameras found');
            }
        }).catch(err => {
            console.error('Camera access error:', err);
            toastr.error('Camera access denied or not available');
        });
    }

    // Start scanner
    $('#start-scanner').on('click', function() {
        if (cameras.length === 0) {
            toastr.error('No cameras available');
            return;
        }

        currentCameraId = cameras[currentCameraIndex].id;
        html5QrcodeScanner = new Html5Qrcode("qr-reader");
        
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };

        html5QrcodeScanner.start(
            currentCameraId,
            config,
            onScanSuccess,
            onScanFailure
        ).then(() => {
            $('#start-scanner').hide();
            $('#stop-scanner, #switch-camera').show();
            $('#scanner-placeholder').hide();
        }).catch(err => {
            console.error('Scanner start error:', err);
            toastr.error('Failed to start scanner');
        });
    });

    // Stop scanner
    $('#stop-scanner').on('click', function() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                resetScannerUI();
            });
        }
    });

    // Switch camera
    $('#switch-camera').on('click', function() {
        if (cameras.length <= 1) return;
        
        currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
        
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                setTimeout(() => {
                    $('#start-scanner').click();
                }, 500);
            });
        }
    });

    // Reset scanner UI
    function resetScannerUI() {
        $('#start-scanner').show();
        $('#stop-scanner, #switch-camera').hide();
        $('#scanner-placeholder').show();
        html5QrcodeScanner = null;
    }

    // Scan success handler
    function onScanSuccess(decodedText, decodedResult) {
        processQRCode(decodedText);
        
        // Stop scanner after successful scan
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                resetScannerUI();
            });
        }
    }

    // Scan failure handler (ignore)
    function onScanFailure(error) {
        // Ignore scan failures - they're normal
    }

    // Process QR code
    function processQRCode(qrCode) {
        // Add to recent scans
        addToRecentScans(qrCode);
        
        // Fetch asset details
        $.post('{{ route("asset-management.qr.get-asset-by-qr") }}', {
            qr_code: qrCode,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                displayAssetDetails(response.asset);
            } else {
                showError(response.message);
            }
        })
        .fail(function(xhr) {
            showError(xhr.responseJSON?.message || 'Failed to fetch asset details');
        });
    }

    // Manual QR processing
    window.processManualQR = function() {
        const qrCode = $('#manual-qr').val().trim().toUpperCase();
        
        if (!qrCode) {
            toastr.warning('Please enter QR code');
            return;
        }

        // Validate format
        if (!/^AST-[A-Z]{3}-\d{3}$/.test(qrCode)) {
            toastr.error('Invalid QR format. Use: AST-XXX-999');
            return;
        }

        processQRCode(qrCode);
        $('#manual-qr').val('');
    };

    // Display asset details
    function displayAssetDetails(asset) {
        $('#scan-initial, #scan-error').hide();
        $('#asset-details').show();
        
        $('#asset-name').text(asset.name);
        $('#asset-category').text(asset.category);
        $('#asset-qr-code').text(asset.qr_code);
        $('#asset-price').text(asset.formatted_price);
        $('#asset-stock').text(asset.available_stock + ' units');
        $('#asset-warehouse').text(asset.warehouse);
        
        // Store asset data for checkout
        $('#add-to-checkout').data('asset', asset).show();
        $('#view-asset-details').show();
        
        toastr.success('Asset found: ' + asset.name);
    }

    // Show error
    function showError(message) {
        $('#scan-initial, #asset-details').hide();
        $('#scan-error').show();
        $('#error-message').text(message);
        
        toastr.error(message);
    }

    // Reset scanner
    window.resetScanner = function() {
        $('#scan-error, #asset-details').hide();
        $('#scan-initial').show();
    };

    // Add to recent scans
    function addToRecentScans(qrCode) {
        recentScans.unshift({
            qr_code: qrCode,
            timestamp: new Date().toLocaleTimeString()
        });
        
        // Keep only last 5 scans
        if (recentScans.length > 5) {
            recentScans = recentScans.slice(0, 5);
        }
        
        updateRecentScansList();
    }

    // Update recent scans display
    function updateRecentScansList() {
        const list = $('#recent-scans');
        
        if (recentScans.length === 0) {
            list.html('<li class="text-muted text-center py-2"><small>No recent scans</small></li>');
            return;
        }
        
        let html = '';
        recentScans.forEach(scan => {
            html += `
                <li class="d-flex justify-content-between align-items-center py-1">
                    <span class="fw-semibold">${scan.qr_code}</span>
                    <small class="text-muted">${scan.timestamp}</small>
                </li>
            `;
        });
        
        list.html(html);
    }

    // Add to checkout
    $('#add-to-checkout').on('click', function() {
        const asset = $(this).data('asset');
        
        $('#checkout-asset-id').val(asset.id);
        $('#checkout-asset-name').val(asset.name);
        $('#checkout-quantity').val(1);
        
        // Load technicians
        if ($('#checkout-technician option').length <= 1) {
            loadTechnicians();
        }
        
        $('#addToCheckoutModal').modal('show');
    });

    // Load technicians
    function loadTechnicians() {
        $.get('{{ route("asset-management.checkout.get-technicians") }}')
        .done(function(response) {
            if (response.success) {
                const select = $('#checkout-technician');
                select.find('option:not(:first)').remove();
                
                response.technicians.forEach(tech => {
                    select.append(`<option value="${tech.id}">${tech.name}</option>`);
                });
            }
        });
    }

    // Submit checkout
    window.submitCheckout = function() {
        const assetId = $('#checkout-asset-id').val();
        const quantity = parseFloat($('#checkout-quantity').val());
        const technicianId = $('#checkout-technician').val();
        
        if (!technicianId) {
            toastr.error('Please select a technician');
            return;
        }
        
        if (!quantity || quantity <= 0) {
            toastr.error('Please enter valid quantity');
            return;
        }
        
        // Redirect to checkout page with pre-filled data
        const checkoutUrl = '{{ route("asset-management.checkout.index") }}';
        const params = new URLSearchParams({
            technician_id: technicianId,
            asset_id: assetId,
            quantity: quantity
        });
        
        window.location.href = `${checkoutUrl}?${params.toString()}`;
    };

    // Manual QR input on Enter
    $('#manual-qr').on('keypress', function(e) {
        if (e.which === 13) {
            processManualQR();
        }
    });

    // Initialize
    initializeScanner();
    
    // Configure toastr
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 3000
    };
});
</script>
@endpush