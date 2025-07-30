{{-- File: resources/views/asset-management/index.blade.php --}}
@extends('layouts.app')

@section('title')
Asset Management
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Asset Management /</span> Dashboard</h4>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-package ti-md"></i></span>
                    </div>
                    <span class="d-block mb-1 text-nowrap">Total Assets</span>
                    <h3 class="card-title mb-0" id="total-assets">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-success"><i class="ti ti-qrcode ti-md"></i></span>
                    </div>
                    <span class="d-block mb-1 text-nowrap">QR Generated</span>
                    <h3 class="card-title mb-0" id="qr-generated">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-warning"><i class="ti ti-users ti-md"></i></span>
                    </div>
                    <span class="d-block mb-1 text-nowrap">Active Debts</span>
                    <h3 class="card-title mb-0" id="active-debts">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded bg-label-info"><i class="ti ti-currency-dollar ti-md"></i></span>
                    </div>
                    <span class="d-block mb-1 text-nowrap">Total Debt Value</span>
                    <h3 class="card-title mb-0" id="total-debt-value">-</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <a href="{{ route('asset-management.checkout.index') }}" class="btn btn-primary w-100 mb-2">
                                <i class="ti ti-shopping-cart me-2"></i>Checkout Assets
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('asset-management.qr.scanner') }}" class="btn btn-success w-100 mb-2">
                                <i class="ti ti-camera me-2"></i>QR Scanner
                            </a>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#generateQRModal">
                                <i class="ti ti-qrcode me-2"></i>Generate QR Codes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assets Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Daftar Assets</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" id="batch-generate-qr">
                    <i class="ti ti-qrcode me-1"></i>Batch Generate QR
                </button>
                <button type="button" class="btn btn-outline-success" id="print-qr-labels">
                    <i class="ti ti-printer me-1"></i>Print Labels
                </button>
            </div>
        </div>
        <div class="card-datatable text-nowrap">
            <!-- Loading indicator -->
            <div id="loading-indicator" class="text-center p-5" style="display:none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Memuat data assets...</p>
            </div>
            
            <table id="assetsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>
                            <input type="checkbox" id="select-all-assets" class="form-check-input">
                        </th>
                        <th>QR Code</th>
                        <th>Asset Name</th>
                        <th>Category</th>
                        <th>Warehouse</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>QR Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="9" class="text-center">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Generate QR Modal -->
<div class="modal fade" id="generateQRModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate QR Codes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Pilih assets untuk generate QR codes:</p>
                <div id="qr-generation-progress" style="display: none;">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="text-center mt-2">Generating QR codes...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="start-qr-generation">Start Generation</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable untuk assets
    $('#loading-indicator').show();
    
    var assetsTable = $('#assetsTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 10,
        ajax: {
            url: '{{ route("asset-management.get-assets-data") }}',
            error: function(xhr, error, thrown) {
                $('#loading-indicator').hide();
                toastr.error('Error loading assets data: ' + thrown);
            }
        },
        columns: [
            { 
                data: 'id', 
                orderable: false, 
                searchable: false,
                render: function(data) {
                    return `<input type="checkbox" class="form-check-input asset-checkbox" value="${data}">`;
                }
            },
            { data: 'qr_code', name: 'qr_code' },
            { data: 'name', name: 'name' },
            { data: 'category_name', name: 'asset_category.name' },
            { data: 'warehouse_name', name: 'warehouse.name' },
            { data: 'formatted_price', name: 'standard_price' },
            { data: 'available_quantity', name: 'available_quantity' },
            { data: 'qr_status', name: 'qr_generated', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            sLengthMenu: '_MENU_',
            search: '',
            searchPlaceholder: 'Search Assets...',
            info: 'Displaying _START_ to _END_ of _TOTAL_ entries'
        },
        drawCallback: function() {
            $('#loading-indicator').hide();
        }
    });

    // Select all checkbox
    $('#select-all-assets').on('change', function() {
        $('.asset-checkbox').prop('checked', this.checked);
    });

    // Generate single QR
    $(document).on('click', '.generate-qr', function() {
        const assetId = $(this).data('id');
        
        $.post('{{ route("asset-management.qr.generate") }}', {
            asset_id: assetId,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                assetsTable.ajax.reload();
            } else {
                toastr.error(response.message);
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Gagal generate QR');
        });
    });

    // Batch generate QR
    $('#batch-generate-qr').on('click', function() {
        const selectedAssets = $('.asset-checkbox:checked').map(function() {
            return this.value;
        }).get();

        if (selectedAssets.length === 0) {
            toastr.warning('Pilih minimal 1 asset untuk generate QR');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Generating...');

        $.post('{{ route("asset-management.qr.generate-batch") }}', {
            asset_ids: selectedAssets,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(`Batch QR generation completed for ${selectedAssets.length} assets`);
                assetsTable.ajax.reload();
            } else {
                toastr.error(response.message);
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Gagal batch generate QR');
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="ti ti-qrcode me-1"></i>Batch Generate QR');
        });
    });

    // Print QR labels
    $('#print-qr-labels').on('click', function() {
        const selectedAssets = $('.asset-checkbox:checked').map(function() {
            return this.value;
        }).get();

        if (selectedAssets.length === 0) {
            toastr.warning('Pilih minimal 1 asset untuk print labels');
            return;
        }

        // Create form and submit untuk download PDF
        const form = $('<form>').attr({
            method: 'POST',
            action: '{{ route("asset-management.qr.print-labels") }}'
        });

        form.append($('<input>').attr({
            type: 'hidden',
            name: '_token',
            value: '{{ csrf_token() }}'
        }));

        selectedAssets.forEach(function(assetId) {
            form.append($('<input>').attr({
                type: 'hidden',
                name: 'asset_ids[]',
                value: assetId
            }));
        });

        $('body').append(form);
        form.submit();
        form.remove();

        toastr.success('QR labels sedang didownload...');
    });

    // Load stats
    function loadStats() {
        $.get('{{ route("asset-management.get-stats") }}')
        .done(function(response) {
            $('#total-assets').text(response.total_assets || 0);
            $('#qr-generated').text(response.qr_generated || 0);
            $('#active-debts').text(response.active_debts || 0);
            $('#total-debt-value').text(response.total_debt_value || 'Rp 0');
        });
    }

    // Initialize
    loadStats();
    
    // Configure toastr
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 5000
    };
});
</script>
@endpush