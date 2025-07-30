{{-- resources/views/asset-management/inventory/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Inventory Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold py-3 mb-2">
                <span class="text-muted fw-light">Asset Management /</span> Inventory & Cable Tracking
            </h4>
            <p class="text-muted mb-0">Kelola inventory dengan tracking detail per unit dan monitoring panjang kabel</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary" id="refreshInventory">
                <i class="ti ti-refresh me-1"></i>Refresh
            </button>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="ti ti-download me-1"></i>Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" id="exportExcel"><i class="ti ti-file-excel me-2"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="#" id="exportPdf"><i class="ti ti-file-pdf me-2"></i>PDF</a></li>
                    <li><a class="dropdown-item" href="#" id="exportCsv"><i class="ti ti-file-csv me-2"></i>CSV</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkQRModal">
                <i class="ti ti-qrcode me-1"></i>Bulk QR Print
            </button>
        </div>
    </div>

    <!-- Enhanced Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading">Total Aset</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2">{{ number_format($stats['total_asset_types']) }}</h4>
                            </div>
                            <small class="mb-0">Jenis berbeda</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-package ti-26px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading">Total Unit</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2">{{ number_format($stats['total_units']) }}</h4>
                            </div>
                            <small class="mb-0">Unit fisik</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="ti ti-stack ti-26px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading">Kabel Tersedia</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2 text-success">{{ number_format($stats['available_cable_length']/1000, 1) }}km</h4>
                            </div>
                            <small class="mb-0">Panjang total</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-cable ti-26px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading">Tersedia</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2 text-success">{{ number_format($stats['total_available']) }}</h4>
                            </div>
                            <small class="mb-0">Siap pakai</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-check ti-26px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading">Digunakan</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2 text-warning">{{ number_format($stats['with_technicians'] + $stats['installed_at_customers']) }}</h4>
                            </div>
                            <small class="mb-0">Teknisi + Customer</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ti ti-users ti-26px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading">Nilai Aset</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2 text-primary">{{ number_format($stats['total_value'] / 1000000, 1) }}M</h4>
                            </div>
                            <small class="mb-0">Total value (Rp)</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-coins ti-26px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cable Overview Chart -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title mb-0">Asset & Cable Utilization</h5>
                    <small class="text-muted">{{ $stats['utilization_rate'] }}% utilized</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="progress-wrapper mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Available Units</span>
                                    <span>{{ number_format($stats['total_available']) }} units</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: {{ $stats['total_units'] > 0 ? ($stats['total_available'] / $stats['total_units']) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                            
                            <div class="progress-wrapper mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Available Cable</span>
                                    <span>{{ number_format($stats['available_cable_length']/1000, 1) }}km / {{ number_format($stats['total_cable_length']/1000, 1) }}km</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: {{ $stats['total_cable_length'] > 0 ? ($stats['available_cable_length'] / $stats['total_cable_length']) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                            
                            <div class="progress-wrapper mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>With Technicians</span>
                                    <span>{{ number_format($stats['with_technicians']) }} units</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: {{ $stats['total_units'] > 0 ? ($stats['with_technicians'] / $stats['total_units']) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                            
                            <div class="progress-wrapper">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Installed at Customers</span>
                                    <span>{{ number_format($stats['installed_at_customers']) }} units</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: {{ $stats['total_units'] > 0 ? ($stats['installed_at_customers'] / $stats['total_units']) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="d-flex flex-column h-100 justify-content-center">
                                <div class="text-center">
                                    <h3 class="text-primary">{{ $stats['utilization_rate'] }}%</h3>
                                    <p class="mb-2">Utilization Rate</p>
                                    <small class="text-muted">
                                        {{ number_format($stats['total_units'] - $stats['total_available']) }} / {{ number_format($stats['total_units']) }} units in use
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stockAdjustmentModal">
                            <i class="ti ti-adjustments me-2"></i>Stock Adjustment
                        </button>
                        <button type="button" class="btn btn-outline-success" id="generateMissingQR">
                            <i class="ti ti-qrcode me-2"></i>Generate Missing QR
                        </button>
                        <button type="button" class="btn btn-outline-info" id="viewCableAssets">
                            <i class="ti ti-cable me-2"></i>View Cable Assets
                        </button>
                        <button type="button" class="btn btn-outline-warning" id="viewLowStock">
                            <i class="ti ti-alert-triangle me-2"></i>View Low Stock Items
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Gudang</label>
                    <select class="form-select" id="filterWarehouse">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kategori</label>
                    <select class="form-select" id="filterCategory">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipe Aset</label>
                    <select class="form-select" id="filterAssetType">
                        <option value="">Semua Tipe</option>
                        <option value="consumable">Consumable</option>
                        <option value="fixed">Fixed Asset</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tracking</label>
                    <select class="form-select" id="filterTrackingType">
                        <option value="">Semua</option>
                        <option value="tracked">QR Tracked</option>
                        <option value="simple">Simple</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status Stok</label>
                    <select class="form-select" id="filterStockStatus">
                        <option value="">Semua Status</option>
                        <option value="in_stock">Tersedia</option>
                        <option value="low_stock">Menipis</option>
                        <option value="out_of_stock">Habis</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-primary flex-fill" id="applyFilters">
                            <i class="ti ti-filter"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                            <i class="ti ti-refresh"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Inventory Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Inventory Management</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllForQR">
                    <i class="ti ti-check-all me-1"></i>Select Tracked
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="printSelectedQR" disabled>
                    <i class="ti ti-printer me-1"></i>Print QR (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="inventoryTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th width="30">
                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                        </th>
                        <th>Kode</th>
                        <th>Nama Aset</th>
                        <th>Kategori</th>
                        <th>Gudang</th>
                        <th>Tersedia</th>
                        <th>Total</th>
                        <th>Digunakan</th>
                        <th>Rusak</th>
                        <th>Status</th>
                        <th>Tracking</th>
                        <th>Panjang Kabel</th>
                        <th>Harga</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cable Details Modal -->
<div class="modal fade" id="cableDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-cable me-2"></i>Detail Kabel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cableDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="exportCableReport">
                    <i class="ti ti-download me-1"></i>Export Report
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Tracked Units Modal -->
<div class="modal fade" id="trackedUnitsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-qrcode me-2"></i>Tracked Units Detail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter Status</label>
                        <select class="form-select" id="trackedStatusFilter">
                            <option value="">Semua Status</option>
                            <option value="available">Available</option>
                            <option value="loaned">With Technician</option>
                            <option value="installed">Installed</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter Gudang</label>
                        <select class="form-select" id="trackedWarehouseFilter">
                            <option value="">Semua Gudang</option>
                            @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-primary" id="applyTrackedFilters">
                            <i class="ti ti-filter me-1"></i>Apply
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="trackedUnitsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>QR Code</th>
                                <th>Asset</th>
                                <th>Gudang</th>
                                <th>Status</th>
                                <th>Device Info</th>
                                <th>Length Info</th>
                                <th>Current Usage</th>
                                <th>Received</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Asset Detail Modal -->
<div class="modal fade" id="assetDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-info-circle me-2"></i>Asset Detail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assetDetailContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Include existing modals from previous code -->

@endsection

@push('after-scripts')
<script>
$(document).ready(function() {
    let inventoryTable;
    let trackedUnitsTable;
    let selectedAssets = new Set();
    let currentAssetId = null;

    // Initialize enhanced inventory table
    inventoryTable = $('#inventoryTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("asset-management.inventory.get-data") }}',
            data: function(d) {
                d.warehouse_id = $('#filterWarehouse').val();
                d.category_id = $('#filterCategory').val();
                d.asset_type = $('#filterAssetType').val();
                d.tracking_type = $('#filterTrackingType').val();
                d.stock_status = $('#filterStockStatus').val();
            }
        },
        columns: [
            { 
                data: 'id', 
                name: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    if (row.requires_qr_tracking) {
                        return `<input type="checkbox" class="form-check-input asset-checkbox" value="${data}" data-asset-name="${row.name}">`;
                    }
                    return '';
                }
            },
            { data: 'asset_code', name: 'asset_code' },
            { data: 'name', name: 'name' },
            { data: 'category_name', name: 'asset_category.name' },
            { data: 'warehouse_name', name: 'warehouse.name' },
            { data: 'available_stock', name: 'available_stock', className: 'text-center' },
            { data: 'total_stock', name: 'total_stock', className: 'text-center' },
            { data: 'in_use_stock', name: 'in_use_stock', className: 'text-center' },
            { data: 'damaged_stock', name: 'damaged_stock', className: 'text-center' },
            { data: 'stock_status', name: 'stock_status', orderable: false },
            { data: 'tracking_type', name: 'tracking_type', orderable: false },
            { data: 'length_summary', name: 'length_summary', orderable: false, className: 'text-center' },
            { data: 'formatted_price', name: 'standard_price' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[2, 'asc']],
        pageLength: 15,
        dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        buttons: [
            {
                extend: 'collection',
                className: 'btn btn-label-secondary btn-sm dropdown-toggle waves-effect waves-light mx-2',
                text: '<i class="ti ti-download me-1 ti-xs"></i>Export',
                buttons: [
                    { extend: 'excel', text: '<i class="ti ti-file-excel me-2"></i>Excel', className: 'dropdown-item' },
                    { extend: 'pdf', text: '<i class="ti ti-file-pdf me-2"></i>PDF', className: 'dropdown-item' },
                    { extend: 'csv', text: '<i class="ti ti-file-csv me-2"></i>CSV', className: 'dropdown-item' }
                ]
            }
        ]
    });

    // Cable Details Modal
    $(document).on('click', '.view-cable-details', function() {
        const assetId = $(this).data('id');
        loadCableDetails(assetId);
    });

    function loadCableDetails(assetId) {
        $('#cableDetailsContent').html('<div class="text-center p-4"><i class="spinner-border"></i> Loading cable details...</div>');
        $('#cableDetailsModal').modal('show');
        
        $.get('{{ route("asset-management.inventory.get-cable-details") }}', { asset_id: assetId })
            .done(function(response) {
                displayCableDetails(response);
            })
            .fail(function(xhr) {
                $('#cableDetailsContent').html('<div class="alert alert-danger">Gagal memuat detail kabel</div>');
            });
    }

    function displayCableDetails(data) {
        const { asset, summary, warehouse_breakdown, low_rolls, cable_data } = data;
        
        let warehouseHtml = '';
        warehouse_breakdown.forEach(function(wh) {
            warehouseHtml += `
                <div class="col-md-6 mb-3">
                    <div class="card border">
                        <div class="card-body">
                            <h6 class="card-title">${wh.warehouse_name}</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h5 class="mb-1">${wh.rolls_count}</h5>
                                        <small class="text-muted">Rolls</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h5 class="mb-1">${formatNumber(wh.total_length)}m</h5>
                                    <small class="text-muted">Total Length</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        let lowRollsHtml = '';
        if (low_rolls.length > 0) {
            low_rolls.forEach(function(roll) {
                const percentage = ((roll.current_length / roll.initial_length) * 100).toFixed(1);
                lowRollsHtml += `
                    <tr>
                        <td><span class="badge bg-label-warning">${roll.qr_code}</span></td>
                        <td>${roll.currentWarehouse?.name || 'Unknown'}</td>
                        <td>${formatNumber(roll.current_length)}m / ${formatNumber(roll.initial_length)}m</td>
                        <td><span class="badge bg-label-danger">${percentage}%</span></td>
                    </tr>
                `;
            });
        } else {
            lowRollsHtml = '<tr><td colspan="4" class="text-center text-muted">Tidak ada roll yang menipis</td></tr>';
        }

        const detailHtml = `
            <div class="row mb-4">
                <div class="col-md-8">
                    <h6>Summary - ${asset.name}</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-label-primary">
                                <div class="card-body text-center">
                                    <h4 class="card-title text-primary">${summary.total_rolls}</h4>
                                    <p class="card-text">Total Rolls</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-label-success">
                                <div class="card-body text-center">
                                    <h4 class="card-title text-success">${summary.available_rolls}</h4>
                                    <p class="card-text">Available</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-label-info">
                                <div class="card-body text-center">
                                    <h4 class="card-title text-info">${formatNumber(summary.total_length/1000, 1)}km</h4>
                                    <p class="card-text">Total Length</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-label-warning">
                                <div class="card-body text-center">
                                    <h4 class="card-title text-warning">${summary.usage_percentage}%</h4>
                                    <p class="card-text">Used</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <h6>Length Distribution</h6>
                    <table class="table table-sm">
                        <tr><td>Available Length:</td><td><strong class="text-success">${formatNumber(summary.available_length)}m</strong></td></tr>
                        <tr><td>Used Length:</td><td><strong class="text-warning">${formatNumber(summary.used_length)}m</strong></td></tr>
                        <tr><td>Avg Roll Length:</td><td>${formatNumber(summary.average_roll_length)}m</td></tr>
                        <tr><td>Longest Roll:</td><td>${formatNumber(summary.longest_roll)}m</td></tr>
                        <tr><td>Shortest Roll:</td><td>${formatNumber(summary.shortest_roll)}m</td></tr>
                    </table>
                </div>
            </div>
            
            <h6>Breakdown per Gudang</h6>
            <div class="row mb-4">
                ${warehouseHtml}
            </div>
            
            <h6>Rolls yang Menipis (<20%)</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>QR Code</th>
                            <th>Gudang</th>
                            <th>Length</th>
                            <th>Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lowRollsHtml}
                    </tbody>
                </table>
            </div>
        `;

        $('#cableDetailsContent').html(detailHtml);
    }

    // Tracked Units Modal
    $(document).on('click', '.view-tracked-units', function() {
        const assetId = $(this).data('id');
        currentAssetId = assetId;
        loadTrackedUnits(assetId);
    });

    function loadTrackedUnits(assetId) {
        $('#trackedUnitsModal').modal('show');
        
        // Initialize or reload tracked units table
        if (trackedUnitsTable) {
            trackedUnitsTable.destroy();
        }
        
        trackedUnitsTable = $('#trackedUnitsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("asset-management.inventory.get-tracked-units-data") }}',
                data: function(d) {
                    d.asset_id = assetId;
                    d.status = $('#trackedStatusFilter').val();
                    d.warehouse_id = $('#trackedWarehouseFilter').val();
                }
            },
            columns: [
                { data: 'qr_code_display', name: 'qr_code' },
                { data: 'asset_name', name: 'asset.name' },
                { data: 'warehouse_name', name: 'currentWarehouse.name' },
                { data: 'status_badge', name: 'current_status' },
                { data: 'device_info', name: 'device_info', orderable: false },
                { data: 'length_info', name: 'length_info', orderable: false },
                { data: 'current_usage', name: 'current_usage', orderable: false },
                { data: 'received_date', name: 'received_date', orderable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            order: [[0, 'asc']],
            pageLength: 10,
            scrollX: true
        });
    }

    // Apply tracked filters
    $('#applyTrackedFilters').on('click', function() {
        if (trackedUnitsTable) {
            trackedUnitsTable.ajax.reload();
        }
    });

    // Asset Detail Modal
    $(document).on('click', '.view-asset-detail', function() {
        const assetId = $(this).data('id');
        loadAssetDetail(assetId);
    });

    function loadAssetDetail(assetId) {
        $('#assetDetailContent').html('<div class="text-center p-4"><i class="spinner-border"></i> Loading...</div>');
        $('#assetDetailModal').modal('show');
        
        $.get(`{{ route('asset-management.inventory.asset-detail', '') }}/${assetId}`)
            .done(function(response) {
                displayAssetDetail(response);
            })
            .fail(function() {
                $('#assetDetailContent').html('<div class="alert alert-danger">Gagal memuat detail asset</div>');
            });
    }

    function displayAssetDetail(data) {
        const { asset, stock_info, cable_info, valuation } = data;
        
        let cableInfoHtml = '';
        if (cable_info) {
            cableInfoHtml = `
                <div class="col-md-6">
                    <h6>Cable Information</h6>
                    <table class="table table-sm">
                        <tr><td>Total Rolls:</td><td><strong>${cable_info.total_rolls}</strong></td></tr>
                        <tr><td>Available Rolls:</td><td><strong class="text-success">${cable_info.available_rolls}</strong></td></tr>
                        <tr><td>Total Length:</td><td><strong>${formatNumber(cable_info.total_length)}m</strong></td></tr>
                        <tr><td>Available Length:</td><td><strong class="text-success">${formatNumber(cable_info.available_length)}m</strong></td></tr>
                        <tr><td>Used Length:</td><td><strong class="text-warning">${formatNumber(cable_info.used_length)}m</strong></td></tr>
                    </table>
                </div>
            `;
        }

        const detailHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Asset Information</h6>
                    <table class="table table-sm">
                        <tr><td>Name:</td><td><strong>${asset.name}</strong></td></tr>
                        <tr><td>Code:</td><td>${asset.asset_code}</td></tr>
                        <tr><td>Category:</td><td>${asset.asset_category?.name || '-'}</td></tr>
                        <tr><td>Type:</td><td><span class="badge bg-label-info">${asset.asset_type}</span></td></tr>
                        <tr><td>Sub Type:</td><td>${asset.asset_sub_type || '-'}</td></tr>
                        <tr><td>Standard Price:</td><td><strong>Rp ${formatNumber(asset.standard_price)}</strong></td></tr>
                    </table>
                </div>
                ${cableInfoHtml}
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <h6>Stock Information</h6>
                    <table class="table table-sm">
                        <tr><td>Total Received:</td><td><strong>${formatNumber(stock_info.total_received || 0)}</strong></td></tr>
                        <tr><td>Total Receipts:</td><td>${stock_info.total_receipts || 0}</td></tr>
                        <tr><td>Average Price:</td><td>Rp ${formatNumber(stock_info.average_price || 0)}</td></tr>
                        <tr><td>Last Receipt:</td><td>${stock_info.last_receipt_date || '-'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Valuation</h6>
                    <table class="table table-sm">
                        <tr><td>Total Value:</td><td><strong class="text-primary">Rp ${formatNumber(valuation.total_value || 0)}</strong></td></tr>
                        <tr><td>Available Value:</td><td><strong class="text-success">Rp ${formatNumber(valuation.available_value || 0)}</strong></td></tr>
                    </table>
                </div>
            </div>
        `;

        $('#assetDetailContent').html(detailHtml);
    }

    // Quick filter for cable assets
    $('#viewCableAssets').on('click', function() {
        $('#filterCategory').val(''); // Reset if needed
        // Set a filter that shows only cable assets
        // This might need adjustment based on your category structure
        inventoryTable.ajax.reload();
        toastr.info('Filter applied for cable assets');
    });

    // Generate Missing QR
    $('#generateMissingQR').on('click', function() {
        Swal.fire({
            title: 'Generate Missing QR Codes?',
            text: 'Akan generate QR code untuk tracked assets yang belum punya QR code',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Generate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                generateMissingQRCodes();
            }
        });
    });

    function generateMissingQRCodes() {
        $.ajax({
            url: '{{ route("asset-management.qr.generate-missing") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                toastr.success(response.message);
                inventoryTable.ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Gagal generate QR codes');
            }
        });
    }

    // Checkbox handling for QR printing
    $('#selectAllCheckbox').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.asset-checkbox').prop('checked', isChecked);
        updateSelectedAssets();
    });

    $(document).on('change', '.asset-checkbox', function() {
        updateSelectedAssets();
    });

    function updateSelectedAssets() {
        selectedAssets.clear();
        $('.asset-checkbox:checked').each(function() {
            selectedAssets.add($(this).val());
        });
        
        $('#selectedCount').text(selectedAssets.size);
        $('#printSelectedQR').prop('disabled', selectedAssets.size === 0);
    }

    // Filters
    $('#applyFilters').on('click', function() {
        inventoryTable.ajax.reload();
        selectedAssets.clear();
        updateSelectedAssets();
    });

    $('#resetFilters').on('click', function() {
        $('#filterWarehouse, #filterCategory, #filterAssetType, #filterTrackingType, #filterStockStatus').val('');
        inventoryTable.ajax.reload();
        selectedAssets.clear();
        updateSelectedAssets();
    });

    // Utility function
    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

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