{{-- File: resources/views/asset-management/master/assets/index.blade.php --}}
@extends('layouts.app')

@section('title')
Master Aset (Jenis)
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Master Data Aset /</span> Jenis Aset</h4>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Daftar Jenis Aset (Master)</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#masterAssetModal">
                <i class="ti ti-plus me-1"></i>Tambah Jenis Aset
            </button>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="masterAssetsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Kode Aset</th>
                        <th>Nama Aset</th>
                        <th>Kategori</th>
                        <th>Sub Tipe</th>
                        <th>Tipe</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Harga Standar</th>
                        <th>Tracking</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit -->
<div class="modal fade" id="masterAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="masterAssetModalTitle">Tambah Jenis Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="masterAssetForm">
                @csrf
                <input type="hidden" name="_method" id="masterAssetMethod" value="POST">
                <input type="hidden" name="id" id="masterAssetId">
                <div class="modal-body">
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="asset_code" class="form-label">Kode Aset <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="asset_code" name="asset_code" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Nama Aset <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>

                    <!-- Category and Type -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="asset_category_id" class="form-label">Kategori Aset <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="asset_category_id" name="asset_category_id" required>
                                <option value="">Pilih Kategori</option>
                                @foreach($assetCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->unit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="asset_sub_type" class="form-label">Sub Tipe Aset</label>
                            <select class="form-select select2" id="asset_sub_type" name="asset_sub_type">
                                <option value="">Pilih Sub Tipe</option>
                                <optgroup label="Network Equipment">
                                    <option value="router">Router</option>
                                    <option value="switch">Switch</option>
                                    <option value="ont">ONT/Modem</option>
                                    <option value="olt">OLT</option>
                                    <option value="access_point">Access Point</option>
                                    <option value="media_converter">Media Converter</option>
                                </optgroup>
                                <optgroup label="Kabel & Passive">
                                    <option value="cable_fiber">Kabel Fiber Optik</option>
                                    <option value="cable_copper">Kabel Tembaga/UTP</option>
                                    <option value="cable_power">Kabel Power</option>
                                    <option value="odp">ODP/Joint Box</option>
                                    <option value="splitter">Splitter</option>
                                    <option value="patch_panel">Patch Panel</option>
                                </optgroup>
                                <optgroup label="Consumables">
                                    <option value="patch_core">Patch Core</option>
                                    <option value="connector">Connector</option>
                                    <option value="adapter">Adapter</option>
                                    <option value="splice_sleeve">Splice Sleeve</option>
                                </optgroup>
                                <optgroup label="Tools & Equipment">
                                    <option value="fusion_splicer">Fusion Splicer</option>
                                    <option value="otdr">OTDR</option>
                                    <option value="power_meter">Power Meter</option>
                                    <option value="cleaver">Cleaver</option>
                                    <option value="tools">Tools/Equipment</option>
                                </optgroup>
                                <optgroup label="Other">
                                    <option value="consumable">Other/Consumable</option>
                                </optgroup>
                            </select>
                            <small class="text-muted">Pilih sub tipe untuk auto-set tracking requirements</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="asset_type" class="form-label">Tipe Aset <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="asset_type" name="asset_type" required>
                                <option value="consumable">Consumable (Habis Pakai)</option>
                                <option value="fixed">Fixed (Aset Tetap)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Brand, Model, and Specifications -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="standard_price" class="form-label">Harga Standar (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="standard_price" name="standard_price" required min="0">
                        </div>
                    </div>

                    <!-- Cable Specific -->
                    <div class="row" id="cable-specific-fields" style="display:none;">
                        <div class="col-md-6 mb-3">
                            <label for="standard_length_per_roll" class="form-label">Panjang Standar per Roll (meter)</label>
                            <input type="number" step="0.001" class="form-control" id="standard_length_per_roll" name="standard_length_per_roll" min="0">
                            <small class="text-muted">Contoh: 1000 untuk kabel fiber 1000m per roll</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="warehouse_id" class="form-label">Gudang Default</label>
                            <select class="form-select select2" id="warehouse_id" name="warehouse_id">
                                <option value="">Pilih Gudang Default</option>
                                @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Non-cable fields -->
                    <div class="row" id="non-cable-fields">
                        <div class="col-md-6 mb-3">
                            <label for="warehouse_id_non_cable" class="form-label">Gudang Default</label>
                            <select class="form-select select2" id="warehouse_id_non_cable" name="warehouse_id">
                                <option value="">Pilih Gudang Default</option>
                                @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Tracking Requirements -->
                    <div class="card border mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Konfigurasi Tracking</h6>
                            <small class="text-muted">Akan otomatis terisi berdasarkan Sub Tipe yang dipilih</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="requires_qr_tracking" name="requires_qr_tracking" value="1">
                                        <label class="form-check-label" for="requires_qr_tracking">
                                            QR Tracking
                                        </label>
                                    </div>
                                    <small class="text-muted">Tracking per unit dengan QR Code</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="requires_serial_number" name="requires_serial_number" value="1">
                                        <label class="form-check-label" for="requires_serial_number">
                                            Serial Number
                                        </label>
                                    </div>
                                    <small class="text-muted">Wajib input Serial Number</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="requires_mac_address" name="requires_mac_address" value="1">
                                        <label class="form-check-label" for="requires_mac_address">
                                            MAC Address
                                        </label>
                                    </div>
                                    <small class="text-muted">Untuk network device</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="manual_tracking_override" name="manual_tracking_override" value="1">
                                        <label class="form-check-label" for="manual_tracking_override">
                                            Manual Override
                                        </label>
                                    </div>
                                    <small class="text-muted">Override setting otomatis</small>
                                </div>
                            </div>
                            <div class="alert alert-info" id="tracking-instructions" style="display:none;">
                                <i class="ti ti-info-circle me-2"></i>
                                <span id="tracking-instructions-text"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Description and Instructions -->
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tracking_instructions" class="form-label">Instruksi Tracking</label>
                            <textarea class="form-control" id="tracking_instructions" name="tracking_instructions" rows="3"></textarea>
                            <small class="text-muted">Panduan untuk staff dalam melakukan tracking asset ini</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary" id="submitMasterAssetBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Show Details -->
<div class="modal fade" id="showMasterAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Jenis Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Kode Aset:</label>
                        <p class="form-control-plaintext" id="show_asset_code">-</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nama Aset:</label>
                        <p class="form-control-plaintext" id="show_name">-</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Kategori:</label>
                        <p class="form-control-plaintext" id="show_category">-</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Sub Tipe:</label>
                        <p class="form-control-plaintext" id="show_sub_type">-</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Tipe:</label>
                        <p class="form-control-plaintext" id="show_asset_type">-</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Brand:</label>
                        <p class="form-control-plaintext" id="show_brand">-</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Model:</label>
                        <p class="form-control-plaintext" id="show_model">-</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Harga Standar:</label>
                        <p class="form-control-plaintext" id="show_standard_price">-</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Panjang Standar per Roll:</label>
                        <p class="form-control-plaintext" id="show_standard_length">-</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Gudang Default:</label>
                        <p class="form-control-plaintext" id="show_warehouse">-</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Tracking Requirements:</label>
                        <div id="show_tracking_requirements">-</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Deskripsi:</label>
                        <p class="form-control-plaintext" id="show_description">-</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Instruksi Tracking:</label>
                        <p class="form-control-plaintext" id="show_tracking_instructions">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        var masterAssetsTable = $('#masterAssetsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("asset-management.master.assets.get-data") }}',
            columns: [
                { data: 'id', name: 'id' },
                { data: 'asset_code', name: 'asset_code' },
                { data: 'name', name: 'name' },
                { data: 'category_name', name: 'asset_category.name' },
                { data: 'asset_sub_type_display', name: 'asset_sub_type' },
                { data: 'asset_type', name: 'asset_type' },
                { data: 'brand', name: 'brand' },
                { data: 'model', name: 'model' },
                { data: 'formatted_price', name: 'standard_price' },
                { data: 'tracking_type', name: 'tracking_type', orderable: false, searchable: false },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            lengthMenu: [7, 10, 25, 50, 70, 100],
            displayLength: 10,
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Search Aset...',
                info: 'Displaying _START_ to _END_ of _TOTAL_ entries'
            },
            buttons: [
                {
                    extend: 'collection',
                    className: 'btn btn-label-secondary btn-sm dropdown-toggle waves-effect waves-light mx-2',
                    text: '<i class="ti ti-download me-1 ti-xs"></i>Export',
                    buttons: [
                        { extend: 'print', text: '<i class="ti ti-printer me-2"></i>Print', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } },
                        { extend: 'csv', text: '<i class="ti ti-file me-2"></i>Csv', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } },
                        { extend: 'excel', text: '<i class="ti ti-file-export me-2"></i>Excel', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } },
                        { extend: 'pdf', text: '<i class="ti ti-file-text me-2"></i>Pdf', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } },
                        { extend: 'copy', text: '<i class="ti ti-copy me-2"></i>Copy', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] } }
                    ]
                },
            ],
        });

        // Auto-configure tracking requirements based on sub type
        $('#asset_sub_type').on('change', function() {
            const subType = $(this).val();
            const manualOverride = $('#manual_tracking_override').is(':checked');
            
            if (subType && !manualOverride) {
                $.get('{{ route("asset-management.master.assets.get-sub-type-config") }}', {
                    sub_type: subType
                })
                .done(function(data) {
                    $('#requires_qr_tracking').prop('checked', data.requires_qr_tracking);
                    $('#requires_serial_number').prop('checked', data.requires_serial_number);
                    $('#requires_mac_address').prop('checked', data.requires_mac_address);
                    
                    // Show instructions
                    $('#tracking-instructions-text').text(data.instructions);
                    $('#tracking-instructions').show();
                    
                    // Set default tracking instructions
                    if (!$('#tracking_instructions').val()) {
                        $('#tracking_instructions').val(data.instructions);
                    }
                })
                .fail(function() {
                    toastr.warning('Gagal memuat konfigurasi sub tipe asset.');
                });
            }
            
            // Show/hide cable specific fields
            if (['cable_fiber', 'cable_copper', 'cable_power'].includes(subType)) {
                $('#cable-specific-fields').show();
                $('#non-cable-fields').hide();
            } else {
                $('#cable-specific-fields').hide();
                $('#non-cable-fields').show();
            }
        });

        // Toggle manual override
        $('#manual_tracking_override').on('change', function() {
            if ($(this).is(':checked')) {
                $('#tracking-instructions').hide();
            } else {
                $('#asset_sub_type').trigger('change');
            }
        });

        // Add Master Asset / Reset Form
        $('#masterAssetModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modalTitle = $(this).find('#masterAssetModalTitle');
            var form = $(this).find('#masterAssetForm');
            
            // Reset form
            form.trigger('reset');
            form.find('#masterAssetMethod').val('POST');
            form.find('#masterAssetId').val('');
            
            // Reset checkboxes and visibility
            form.find('#requires_qr_tracking, #requires_serial_number, #requires_mac_address, #manual_tracking_override').prop('checked', false);
            $('#tracking-instructions').hide();
            $('#cable-specific-fields').hide();
            $('#non-cable-fields').show();

            if (button.hasClass('edit-master-asset')) {
                modalTitle.text('Edit Jenis Aset');
                form.find('#masterAssetMethod').val('PUT');
                var assetId = button.data('id');
                
                $.get('{{ url("asset-management/master/assets") }}/' + assetId)
                    .done(function(data) {
                        form.find('#masterAssetId').val(data.id);
                        form.find('#asset_code').val(data.asset_code);
                        form.find('#name').val(data.name);
                        form.find('#asset_category_id').val(data.asset_category_id);
                        form.find('#asset_sub_type').val(data.asset_sub_type || '');
                        form.find('#asset_type').val(data.asset_type);
                        form.find('#brand').val(data.brand || '');
                        form.find('#model').val(data.model || '');
                        form.find('#standard_price').val(data.standard_price);
                        form.find('#standard_length_per_roll').val(data.standard_length_per_roll || '');
                        form.find('#description').val(data.description || '');
                        form.find('#tracking_instructions').val(data.tracking_instructions || '');
                        form.find('#warehouse_id, #warehouse_id_non_cable').val(data.warehouse_id || '');
                        
                        // Set checkboxes
                        form.find('#requires_qr_tracking').prop('checked', data.requires_qr_tracking);
                        form.find('#requires_serial_number').prop('checked', data.requires_serial_number);
                        form.find('#requires_mac_address').prop('checked', data.requires_mac_address);
                        
                        // Trigger sub type change to show appropriate fields
                        $('#asset_sub_type').trigger('change');
                    })
                    .fail(function(xhr) {
                        toastr.error('Gagal memuat data jenis aset.');
                    });
            } else {
                modalTitle.text('Tambah Jenis Aset');
            }
        });

        // Show Master Asset Details
        $(document).on('click', '.show-master-asset', function() {
            var assetId = $(this).data('id');
            
            $.get('{{ url("asset-management/master/assets") }}/' + assetId)
                .done(function(data) {
                    $('#show_asset_code').text(data.asset_code || '-');
                    $('#show_name').text(data.name || '-');
                    $('#show_category').text(data.asset_category ? data.asset_category.name + ' (' + data.asset_category.unit + ')' : '-');
                    $('#show_sub_type').text(data.asset_sub_type ? data.asset_sub_type.replace('_', ' ').toUpperCase() : '-');
                    $('#show_asset_type').text(data.asset_type === 'consumable' ? 'Consumable (Habis Pakai)' : 'Fixed (Aset Tetap)');
                    $('#show_brand').text(data.brand || '-');
                    $('#show_model').text(data.model || '-');
                    $('#show_standard_price').text(data.standard_price ? 'Rp ' + new Intl.NumberFormat('id-ID').format(data.standard_price) : '-');
                    $('#show_standard_length').text(data.standard_length_per_roll ? data.standard_length_per_roll + ' meter' : '-');
                    $('#show_warehouse').text(data.warehouse ? data.warehouse.name : '-');
                    $('#show_description').text(data.description || '-');
                    $('#show_tracking_instructions').text(data.tracking_instructions || '-');
                    
                    // Show tracking requirements
                    var trackingBadges = [];
                    if (data.requires_qr_tracking) trackingBadges.push('<span class="badge bg-label-primary">QR Tracking</span>');
                    if (data.requires_serial_number) trackingBadges.push('<span class="badge bg-label-info">Serial Number</span>');
                    if (data.requires_mac_address) trackingBadges.push('<span class="badge bg-label-warning">MAC Address</span>');
                    
                    $('#show_tracking_requirements').html(trackingBadges.length > 0 ? trackingBadges.join(' ') : '<span class="badge bg-label-secondary">Simple Tracking</span>');
                    
                    $('#showMasterAssetModal').modal('show');
                })
                .fail(function(xhr) {
                    toastr.error('Gagal memuat data jenis aset.');
                });
        });

        // Submit Form
        $('#masterAssetForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var assetId = form.find('#masterAssetId').val();
            var url = assetId ? '{{ url("asset-management/master/assets") }}/' + assetId : '{{ route("asset-management.master.assets.store") }}';
            var method = form.find('#masterAssetMethod').val();

            $.ajax({
                url: url,
                method: method,
                data: form.serialize(),
                success: function(response) {
                    toastr.success(response.message);
                    $('#masterAssetModal').modal('hide');
                    masterAssetsTable.ajax.reload();
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON.message || 'Terjadi kesalahan.');
                    }
                }
            });
        });

        // Delete Master Asset
        $(document).on('click', '.delete-master-asset', function() {
            var assetId = $(this).data('id');
            Swal.fire({
                title: 'Anda yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan! Ini akan mempengaruhi data terkait seperti unit fisik, penggunaan, dan hutang.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    $.ajax({
                        url: '{{ url("asset-management/master/assets") }}/' + assetId,
                        method: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(response) {
                            toastr.success(response.message);
                            masterAssetsTable.ajax.reload();
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON.message || 'Gagal menghapus jenis aset.');
                        }
                    });
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush