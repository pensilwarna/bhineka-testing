{{-- resources/views/asset-management/receipts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Penerimaan Aset')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold py-3 mb-2">
                <span class="text-muted fw-light">Asset Management /</span> Penerimaan Aset
            </h4>
            <p class="text-muted mb-0">Kelola penerimaan aset dari supplier dengan tracking QR code</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="card-header-elements">
                <span class="badge bg-label-info me-2">
                    <i class="ti ti-package me-1"></i>
                    <span id="totalReceiptsCount">{{ App\Models\AssetReceipt::count() }}</span> Total Receipts
                </span>
                <span class="badge bg-label-primary me-2">
                    <i class="ti ti-qrcode me-1"></i>
                    <span id="totalTrackedAssets">{{ App\Models\TrackedAsset::count() }}</span> Tracked Assets
                </span>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createReceiptModal">
                <i class="ti ti-plus me-2"></i>Buat Penerimaan Baru
            </button>
        </div>
    </div>

    <!-- Enhanced Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-check ti-md"></i>
                        </span>
                    </div>
                    <span class="d-block mb-1 text-nowrap">QR Generated</span>
                    <h4 class="card-title mb-1">{{ App\Models\TrackedAsset::where('qr_generated', true)->count() }}</h4>
                    <small class="text-muted">Assets with QR</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="ti ti-clock ti-md"></i>
                        </span>
                    </div>
                    <span class="d-block mb-1 text-nowrap">QR Pending</span>
                    <h4 class="card-title mb-1">{{ App\Models\TrackedAsset::where('qr_generated', false)->count() }}</h4>
                    <small class="text-muted">Need QR codes</small>
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
                    <span class="d-block mb-1 text-nowrap">Cable Assets</span>
                    <h4 class="card-title mb-1">{{ App\Models\TrackedAsset::whereHas('asset', function($q) { $q->where('asset_sub_type', 'like', '%cable%'); })->count() }}</h4>
                    <small class="text-muted">Length tracked</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-package ti-md"></i>
                        </span>
                    </div>
                    <span class="d-block mb-1 text-nowrap">This Month</span>
                    <h4 class="card-title mb-1">{{ App\Models\AssetReceipt::whereMonth('receipt_date', now()->month)->count() }}</h4>
                    <small class="text-muted">New receipts</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Supplier</label>
                    <select class="form-select" id="filterSupplier">
                        <option value="">Semua Supplier</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="filterStartDate">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="filterEndDate">
                </div>
                <div class="col-md-2">
                    <label class="form-label">QR Status</label>
                    <select class="form-select" id="filterQRStatus">
                        <option value="">Semua Status</option>
                        <option value="complete">QR Complete</option>
                        <option value="partial">QR Partial</option>
                        <option value="pending">QR Pending</option>
                        <option value="no_tracking">No Tracking</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary me-2" id="applyFilters">
                        <i class="ti ti-filter me-1"></i>Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary me-2" id="resetFilters">
                        <i class="ti ti-refresh me-1"></i>Reset
                    </button>
                    <button type="button" class="btn btn-outline-success" id="bulkGenerateQR">
                        <i class="ti ti-qrcode me-1"></i>Bulk QR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Receipts Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Daftar Penerimaan Aset</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="refreshTable">
                    <i class="ti ti-refresh me-1"></i>Refresh
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="ti ti-download me-1"></i>Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" id="exportExcel"><i class="ti ti-file-excel me-2"></i>Excel</a></li>
                        <li><a class="dropdown-item" href="#" id="exportPdf"><i class="ti ti-file-pdf me-2"></i>PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="receiptsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>Receipt Number</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>PO Number</th>
                        <th>Items</th>
                        <th>QR Status</th>
                        <th>Total Value</th>
                        <th>Diterima Oleh</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- QR Summary Modal -->
<div class="modal fade" id="qrSummaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-qrcode me-2"></i>QR Code Management Summary
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="qrSummaryContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="generateAllQR">
                    <i class="ti ti-qrcode me-1"></i>Generate All Missing QR
                </button>
                <button type="button" class="btn btn-success" id="printAllQRLabels">
                    <i class="ti ti-printer me-1"></i>Print All QR Labels
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Label Options Modal -->
<div class="modal fade" id="qrLabelOptionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-printer me-2"></i>QR Label Print Options
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="qrLabelOptionsForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Label Size</label>
                        <select class="form-select" name="label_size">
                            <option value="small">Small (45x30mm)</option>
                            <option value="medium" selected>Medium (60x40mm)</option>
                            <option value="large">Large (80x55mm)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Include Asset Details</label>
                        <select class="form-select" name="include_text">
                            <option value="1" selected>Yes - Include asset name, S/N, etc.</option>
                            <option value="0">No - QR code only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Group Labels</label>
                        <select class="form-select" name="group_by_asset">
                            <option value="0" selected>Mixed - All labels together</option>
                            <option value="1">Grouped - Group by asset type</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="printQRLabelsBtn">
                        <i class="ti ti-printer me-1"></i>Print Labels
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ... include all existing modals from previous code ... -->

@endsection

@push('after-scripts')
<script>
$(document).ready(function() {
    let itemCounter = 0;
    let receiptsTable;
    let currentReceiptId = null;

    // Initialize Enhanced DataTable
    receiptsTable = $('#receiptsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("asset-management.receipts.get-data") }}',
            data: function(d) {
                d.supplier_id = $('#filterSupplier').val();
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
                d.qr_status = $('#filterQRStatus').val();
            }
        },
        columns: [
            { data: 'receipt_number', name: 'receipt_number' },
            { data: 'receipt_date', name: 'receipt_date' },
            { data: 'supplier_name', name: 'supplier.name' },
            { data: 'purchase_order_number', name: 'purchase_order_number' },
            { data: 'items_count', name: 'items_count', orderable: false },
            { data: 'tracking_status', name: 'tracking_status', orderable: false },
            { data: 'total_value', name: 'total_value' },
            { data: 'received_by_name', name: 'receivedBy.name' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        dom: '<"row"<"col-md-6"l><"col-md-6"f>><"table-responsive"t><"row"<"col-md-6"i><"col-md-6"p>>',
    });

    // QR Summary Modal
    $(document).on('click', '.view-qr-summary', function() {
        const receiptId = $(this).data('id');
        currentReceiptId = receiptId;
        loadQRSummary(receiptId);
    });

    function loadQRSummary(receiptId) {
        $('#qrSummaryContent').html('<div class="text-center p-4"><i class="spinner-border"></i> Loading...</div>');
        $('#qrSummaryModal').modal('show');
        
        $.get(`{{ url('asset-management/receipts') }}/${receiptId}/qr-summary`)
            .done(function(response) {
                displayQRSummary(response.summary);
            })
            .fail(function(xhr) {
                $('#qrSummaryContent').html('<div class="alert alert-danger">Gagal memuat QR summary</div>');
            });
    }

    function displayQRSummary(summary) {
        let itemsHtml = '';
        
        summary.items_detail.forEach(function(item, index) {
            let statusBadge = '';
            let actionButtons = '';
            
            if (item.requires_tracking) {
                if (item.qr_pending > 0) {
                    statusBadge = `<span class="badge bg-label-warning">Pending: ${item.qr_pending}</span>`;
                    actionButtons = `<button class="btn btn-sm btn-outline-primary generate-item-qr" data-item-index="${index}">Generate QR</button>`;
                } else {
                    statusBadge = `<span class="badge bg-label-success">Complete: ${item.qr_generated}</span>`;
                }
                
                actionButtons += ` <button class="btn btn-sm btn-outline-success print-item-qr" data-item-index="${index}">Print QR</button>`;
            } else {
                statusBadge = '<span class="badge bg-label-secondary">No Tracking</span>';
            }
            
            let trackedAssetsHtml = '';
            if (item.tracked_assets && item.tracked_assets.length > 0) {
                trackedAssetsHtml = '<div class="mt-2"><small class="text-muted">Tracked Assets:</small><div class="mt-1">';
                
                item.tracked_assets.forEach(function(tracked) {
                    const badgeClass = tracked.qr_generated ? 'bg-label-success' : 'bg-label-warning';
                    const icon = tracked.qr_generated ? 'ti-check' : 'ti-clock';
                    
                    trackedAssetsHtml += `
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-1">
                            <div>
                                <span class="badge ${badgeClass}"><i class="ti ${icon}"></i> ${tracked.qr_code}</span>
                                ${tracked.serial_number ? `<small class="text-muted ms-2">S/N: ${tracked.serial_number}</small>` : ''}
                                ${tracked.initial_length ? `<small class="text-muted ms-2">Length: ${tracked.initial_length}m</small>` : ''}
                            </div>
                            <div>
                                ${!tracked.qr_generated ? `<button class="btn btn-xs btn-outline-primary generate-single-qr" data-tracked-id="${tracked.id}">Gen QR</button>` : ''}
                                <button class="btn btn-xs btn-outline-success print-single-qr" data-tracked-id="${tracked.id}">Print</button>
                            </div>
                        </div>
                    `;
                });
                
                trackedAssetsHtml += '</div></div>';
            }
            
            itemsHtml += `
                <div class="card border mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${item.asset_name} <small class="text-muted">(${item.asset_code})</small></h6>
                                <div class="mb-2">
                                    <span class="badge bg-label-info">Qty: ${item.quantity}</span>
                                    ${statusBadge}
                                    ${item.asset_sub_type ? `<span class="badge bg-label-secondary">${item.asset_sub_type}</span>` : ''}
                                </div>
                                ${trackedAssetsHtml}
                            </div>
                            <div class="text-end">
                                ${actionButtons}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        const summaryHtml = `
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Receipt Information</h6>
                    <table class="table table-sm">
                        <tr><td>Receipt Number:</td><td><strong>${summary.receipt_number}</strong></td></tr>
                        <tr><td>Date:</td><td>${summary.receipt_date}</td></tr>
                        <tr><td>Supplier:</td><td>${summary.supplier}</td></tr>
                        <tr><td>Total Items:</td><td>${summary.total_items}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>QR Status Summary</h6>
                    <table class="table table-sm">
                        <tr><td>Tracked Items:</td><td><strong>${summary.tracked_items}</strong></td></tr>
                        <tr><td>Total Tracked Assets:</td><td><strong>${summary.total_tracked_assets}</strong></td></tr>
                        <tr><td>QR Generated:</td><td><strong class="text-success">${summary.qr_generated_count}</strong></td></tr>
                        <tr><td>QR Pending:</td><td><strong class="text-warning">${summary.qr_pending_count}</strong></td></tr>
                    </table>
                </div>
            </div>
            
            <h6>Items Detail</h6>
            ${itemsHtml}
        `;

        $('#qrSummaryContent').html(summaryHtml);
        
        // Update modal footer buttons
        $('#generateAllQR').toggle(summary.qr_pending_count > 0);
        $('#printAllQRLabels').toggle(summary.qr_generated_count > 0);
    }

    // Generate QR for receipt
    $(document).on('click', '.generate-receipt-qr', function() {
        const receiptId = $(this).data('id');
        generateReceiptQR(receiptId);
    });

    $('#generateAllQR').on('click', function() {
        if (currentReceiptId) {
            generateReceiptQR(currentReceiptId);
        }
    });

    function generateReceiptQR(receiptId) {
        Swal.fire({
            title: 'Generating QR Codes...',
            html: 'Sedang generate QR codes untuk tracked assets',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: `{{ url('asset-management/receipts') }}/${receiptId}/generate-qr`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                Swal.close();
                toastr.success(response.message);
                receiptsTable.ajax.reload();
                
                if (currentReceiptId === receiptId) {
                    loadQRSummary(receiptId); // Refresh summary
                }
            },
            error: function(xhr) {
                Swal.close();
                toastr.error(xhr.responseJSON?.message || 'Gagal generate QR codes');
            }
        });
    }

    // Print QR Labels
    $(document).on('click', '.print-qr-labels', function() {
        const receiptId = $(this).data('id');
        currentReceiptId = receiptId;
        $('#qrLabelOptionsModal').modal('show');
    });

    $('#printAllQRLabels').on('click', function() {
        if (currentReceiptId) {
            $('#qrLabelOptionsModal').modal('show');
        }
    });

    $('#qrLabelOptionsForm').on('submit', function(e) {
        e.preventDefault();
        
        if (!currentReceiptId) return;
        
        const formData = new FormData(this);
        
        Swal.fire({
            title: 'Generating QR Labels...',
            html: 'Sedang memproses label QR code',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: `{{ url('asset-management/receipts') }}/${currentReceiptId}/print-qr-labels`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                Swal.close();
                $('#qrLabelOptionsModal').modal('hide');
                
                if (response.success) {
                    toastr.success(response.message);
                    
                    // Open print dialog
                    if (response.download_url) {
                        window.open(response.download_url, '_blank');
                    }
                } else {
                    toastr.error(response.message || 'Gagal generate QR labels');
                }
            },
            error: function(xhr) {
                Swal.close();
                $('#qrLabelOptionsModal').modal('hide');
                toastr.error(xhr.responseJSON?.message || 'Gagal generate QR labels');
            }
        });
    });

    // Refresh table
    $('#refreshTable').on('click', function() {
        receiptsTable.ajax.reload();
        updateQuickStats();
    });



    // Filters
    $('#applyFilters').click(function() {
        receiptsTable.ajax.reload();
    });
    
    $('#resetFilters').click(function() {
        $('#filterSupplier, #filterStartDate, #filterEndDate, #filterQRStatus').val('');
        receiptsTable.ajax.reload();
    });

    // Bulk Generate QR for filtered results
    $('#bulkGenerateQR').on('click', function() {
        Swal.fire({
            title: 'Bulk Generate QR Codes?',
            text: 'Generate QR codes untuk semua tracked assets yang belum punya QR dari hasil filter saat ini',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Generate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Implementation for bulk QR generation
                toastr.info('Bulk QR generation akan diimplementasikan');
            }
        });
    });

    // ... include all existing JavaScript from previous receipt code ...

    // Toast configuration
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 5000
    };
});
</script>
@endpush