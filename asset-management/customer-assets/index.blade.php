{{-- File: resources/views/asset-management/customer-assets/index.blade.php --}}
@extends('layouts.app')

@section('title')
Aset Terpasang Pelanggan
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Asset Management /</span> Aset Terpasang Pelanggan</h4>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Filter Aset Terpasang</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="filter_customer" class="form-label">Pelanggan</label>
                    <select class="form-select" id="filter_customer">
                        <option value="">Semua Pelanggan</option>
                        {{-- Customers will be loaded via AJAX if many, or passed from controller --}}
                        {{-- For now, assume you load them if needed. E.g., from Customer Model --}}
                        {{-- @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach --}}
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filter_status" class="form-label">Status Aset</label>
                    <select class="form-select" id="filter_status">
                        <option value="">Semua Status</option>
                        <option value="installed">Terpasang</option>
                        <option value="removed">Ditarik</option>
                        <option value="replaced">Diganti</option>
                        <option value="damaged">Rusak</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" id="apply_filter" class="btn btn-primary me-2">Terapkan Filter</button>
                    <button type="button" id="reset_filter" class="btn btn-label-secondary">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Daftar Aset Terpasang</h5>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="customerAssetsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Pelanggan</th>
                        <th>Lokasi Layanan</th>
                        <th>Nama Aset</th>
                        <th>Identifikasi Aset</th>
                        <th>Qty</th>
                        <th>Nilai Total</th>
                        <th>Tgl Instalasi</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="installAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Instalasi Aset Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="installAssetForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="install_customer_id" class="form-label">Pelanggan <span class="text-danger">*</span></label>
                            <select class="form-select" id="install_customer_id" name="customer_id" required>
                                <option value="">Pilih Pelanggan</option>
                                {{-- Load dynamically if too many, or pass from controller --}}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="install_service_location_id" class="form-label">Lokasi Layanan <span class="text-danger">*</span></label>
                            <select class="form-select" id="install_service_location_id" name="service_location_id" required>
                                <option value="">Pilih Lokasi Layanan</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="install_ticket_id" class="form-label">Tiket Terkait <span class="text-danger">*</span></label>
                            <select class="form-select" id="install_ticket_id" name="ticket_id" required>
                                <option value="">Pilih Tiket</option>
                                {{-- Load dynamically --}}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="install_asset_id" class="form-label">Jenis Aset <span class="text-danger">*</span></label>
                            <select class="form-select" id="install_asset_id" name="asset_id" required>
                                <option value="">Pilih Jenis Aset</option>
                                {{-- @foreach($assets as $asset)
                                <option value="{{ $asset->id }}" data-requires-qr="{{ $asset->requires_qr_tracking ? 'true' : 'false' }}" data-unit="{{ $asset->asset_category->unit ?? 'unit' }}">{{ $asset->name }} ({{ $asset->asset_category->unit ?? 'unit' }})</option>
                                @endforeach --}}
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="install_tracked_asset_id" class="form-label">Unit Aset Terlacak (Opsional)</label>
                            <select class="form-select" id="install_tracked_asset_id" name="tracked_asset_id">
                                <option value="">Pilih Unit Terlacak (Jika berlaku)</option>
                                {{-- Options will be loaded dynamically based on technician's debts --}}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="install_quantity" class="form-label">Kuantitas Terpasang <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" class="form-control" id="install_quantity" name="quantity_installed" required min="0.001">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="install_debt_id" class="form-label">Hutang Teknisi Terkait <span class="text-danger">*</span></label>
                            <select class="form-select" id="install_debt_id" name="debt_id" required>
                                <option value="">Pilih Hutang Aset</option>
                                {{-- Options will be loaded dynamically based on technician's debts and selected asset --}}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="install_date" class="form-label">Tanggal Instalasi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="install_date" name="installation_date" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="install_length" class="form-label">Panjang Terpasang (Meter/KM) <span class="text-danger install-length-required">*</span></label>
                        <input type="number" step="0.001" class="form-control" id="install_length" name="installed_length" min="0">
                        <small class="text-muted install-length-hint" style="display:none;">Hanya isi jika aset adalah kabel.</small>
                    </div>
                    <div class="mb-3">
                        <label for="installation_photos" class="form-label">Foto Instalasi (Opsional)</label>
                        <input type="file" class="form-control" id="installation_photos" name="installation_photos[]" multiple accept="image/*">
                        <small class="text-muted">Maks 2MB per foto, format JPG/PNG.</small>
                    </div>
                    <div class="mb-3">
                        <label for="install_notes" class="form-label">Catatan Instalasi</label>
                        <textarea class="form-control" id="install_notes" name="installation_notes" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="install_latitude" class="form-label">Latitude GPS</label>
                            <input type="text" class="form-control" id="install_latitude" name="gps_latitude">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="install_longitude" class="form-label">Longitude GPS</label>
                            <input type="text" class="form-control" id="install_longitude" name="gps_longitude">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submitInstallBtn">Simpan Instalasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="removeAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Penarikan Aset Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="removeAssetForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="customer_installed_asset_id" id="remove_cia_id">
                <div class="modal-body">
                    <p>Anda akan menarik aset: <strong id="remove-asset-name"></strong> (ID: <span id="remove-asset-identifier"></span>)</p>
                    <div class="mb-3">
                        <label for="remove_date" class="form-label">Tanggal Penarikan <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="remove_date" name="removed_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3" id="return_to_warehouse_section">
                        <label for="returned_to_warehouse_id" class="form-label">Kembalikan ke Gudang (Opsional)</label>
                        <select class="form-select" id="returned_to_warehouse_id" name="returned_to_warehouse_id">
                            <option value="">Tidak Dikembalikan / Langsung Scrap</option>
                            @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Pilih gudang jika aset fisik akan dikembalikan.</small>
                    </div>
                     <div class="mb-3" id="status_on_return_section" style="display:none;">
                        <label for="status_on_return" class="form-label">Status Aset Setelah Kembali</label>
                        <select class="form-select" id="status_on_return" name="status_on_return">
                            <option value="available">Available</option>
                            <option value="damaged">Damaged</option>
                            <option value="scrap">Scrap</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="removal_photos" class="form-label">Foto Penarikan (Opsional)</label>
                        <input type="file" class="form-control" id="removal_photos" name="removal_photos[]" multiple accept="image/*">
                        <small class="text-muted">Maks 2MB per foto, format JPG/PNG.</small>
                    </div>
                    <div class="mb-3">
                        <label for="remove_notes" class="form-label">Catatan Penarikan</label>
                        <textarea class="form-control" id="remove_notes" name="removal_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" id="submitRemoveBtn">Konfirmasi Penarikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="replaceAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ganti Aset Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="replaceAssetForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="old_customer_installed_asset_id" id="replace_old_cia_id">
                <div class="modal-body">
                    <h6>Aset Lama yang Akan Diganti:</h6>
                    <p>Nama: <strong id="old-replace-asset-name"></strong> (ID: <span id="old-replace-asset-identifier"></span>)</p>
                    <div class="mb-3" id="old_asset_return_to_warehouse_section">
                        <label for="old_asset_returned_to_warehouse_id" class="form-label">Kembalikan Aset Lama ke Gudang (Opsional)</label>
                        <select class="form-select" id="old_asset_returned_to_warehouse_id" name="old_asset_returned_to_warehouse_id">
                            <option value="">Tidak Dikembalikan / Langsung Scrap</option>
                            @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Pilih gudang jika aset fisik lama akan dikembalikan.</small>
                    </div>
                    <div class="mb-3" id="status_old_asset_on_return_section" style="display:none;">
                        <label for="status_old_asset_on_return" class="form-label">Status Aset Lama Setelah Kembali</label>
                        <select class="form-select" id="status_old_asset_on_return" name="status_old_asset_on_return">
                            <option value="available">Available</option>
                            <option value="damaged">Damaged</option>
                            <option value="scrap">Scrap</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>

                    <hr class="my-4">

                    <h6>Detail Aset Baru Pengganti:</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_asset_id" class="form-label">Jenis Aset Baru <span class="text-danger">*</span></label>
                            <select class="form-select" id="new_asset_id" name="new_asset_id" required>
                                <option value="">Pilih Jenis Aset</option>
                                {{-- Load assets --}}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_tracked_asset_id" class="form-label">Unit Aset Terlacak Baru (Opsional)</label>
                            <select class="form-select" id="new_tracked_asset_id" name="new_tracked_asset_id">
                                <option value="">Pilih Unit Terlacak (Jika berlaku)</option>
                                {{-- Load based on technician's debts and new asset type --}}
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_quantity_installed" class="form-label">Kuantitas Terpasang Baru <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" class="form-control" id="new_quantity_installed" name="new_quantity_installed" required min="0.001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_debt_id" class="form-label">Hutang Teknisi Terkait Baru <span class="text-danger">*</span></label>
                            <select class="form-select" id="new_debt_id" name="new_debt_id" required>
                                <option value="">Pilih Hutang Aset</option>
                                {{-- Load based on technician's debts and new asset --}}
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="new_installed_length" class="form-label">Panjang Terpasang Baru (Meter/KM) <span class="text-danger new-install-length-required">*</span></label>
                        <input type="number" step="0.001" class="form-control" id="new_installed_length" name="new_installed_length" min="0">
                        <small class="text-muted new-install-length-hint" style="display:none;">Hanya isi jika aset adalah kabel.</small>
                    </div>
                    <div class="mb-3">
                        <label for="replacement_photos" class="form-label">Foto Penggantian (Opsional)</label>
                        <input type="file" class="form-control" id="replacement_photos" name="replacement_photos[]" multiple accept="image/*">
                        <small class="text-muted">Maks 2MB per foto, format JPG/PNG.</small>
                    </div>
                    <div class="mb-3">
                        <label for="replacement_notes" class="form-label">Catatan Penggantian</label>
                        <textarea class="form-control" id="replacement_notes" name="replacement_notes" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="replacement_date" class="form-label">Tanggal Penggantian <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="replacement_date" name="replacement_date" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submitReplaceBtn">Konfirmasi Penggantian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="auditCableLengthModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Panjang Kabel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="auditCableLengthForm">
                @csrf
                <input type="hidden" name="customer_installed_asset_id" id="audit_cia_id">
                <div class="modal-body">
                    <p>Audit panjang kabel untuk: <strong id="audit-asset-name"></strong> (Identifikasi: <span id="audit-asset-identifier"></span>)</p>
                    <p>Panjang Terpasang Awal: <strong id="audit-initial-length"></strong></p>
                    <p>Panjang Saat Ini (Sistem): <strong id="audit-current-length-system"></strong></p>
                    <div class="mb-3">
                        <label for="new_current_length" class="form-label">Panjang Baru Hasil Audit <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" class="form-control" id="new_current_length" name="new_current_length" required min="0">
                        <small class="text-muted">Masukkan panjang kabel aktual setelah audit.</small>
                    </div>
                    <div class="mb-3">
                        <label for="change_reason" class="form-label">Alasan Perubahan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="change_reason" name="change_reason" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="audit_notes" class="form-label">Catatan Audit (Opsional)</label>
                        <textarea class="form-control" id="audit_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submitAuditBtn">Simpan Audit</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('after-scripts')
<script>
    const allCustomers = []; // Load from backend if many, or fetch dynamically
    const allServiceLocations = []; // Load dynamically based on customer selection
    const allTickets = []; // Load dynamically based on customer/service location
    const allAssets = @json(App\Models\Asset::with('asset_category')->get());
    const allWarehouses = @json(App\Models\Warehouse::all());

    $(document).ready(function() {
        // --- DataTables for Customer Assets ---
        var customerAssetsTable = $('#customerAssetsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("asset-management.customer-assets.get-data") }}',
                data: function(d) {
                    d.customer_id = $('#filter_customer').val();
                    d.status = $('#filter_status').val();
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'customer_name', name: 'customer.name' },
                { data: 'service_location_address', name: 'serviceLocation.address' },
                { data: 'asset_name', name: 'asset.name' },
                { data: 'asset_identifier', name: 'trackedAsset.qr_code', orderable: false, searchable: false },
                { data: 'quantity_installed', name: 'quantity_installed' },
                { data: 'formatted_value', name: 'total_asset_value' },
                { data: 'installation_date', name: 'installation_date' },
                { data: 'status_label', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Cari Aset Pelanggan...',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri'
            }
        });

        $('#apply_filter').on('click', function() {
            customerAssetsTable.ajax.reload();
        });

        $('#reset_filter').on('click', function() {
            $('#filter_customer').val('').trigger('change');
            $('#filter_status').val('').trigger('change');
            customerAssetsTable.ajax.reload();
        });

        // --- Helper functions for dynamic dropdowns (simplified for example) ---
        function loadCustomers(selectElementId, selectedValue = null) {
            $.get('/api/customers').done(function(data) { // Assuming an API endpoint for customers
                const select = $(`#${selectElementId}`);
                select.empty().append('<option value="">Pilih Pelanggan</option>');
                data.forEach(cust => select.append(`<option value="${cust.id}">${cust.name}</option>`));
                if (selectedValue) select.val(selectedValue).trigger('change');
            });
        }

        function loadServiceLocations(customerId, selectElementId, selectedValue = null) {
            if (!customerId) {
                $(`#${selectElementId}`).empty().append('<option value="">Pilih Lokasi Layanan</option>');
                return;
            }
            $.get(`/api/customers/${customerId}/service-locations`).done(function(data) { // Assuming an API endpoint
                const select = $(`#${selectElementId}`);
                select.empty().append('<option value="">Pilih Lokasi Layanan</option>');
                data.forEach(loc => select.append(`<option value="${loc.id}">${loc.address}</option>`));
                if (selectedValue) select.val(selectedValue).trigger('change');
            });
        }

        function loadTickets(serviceLocationId, selectElementId, selectedValue = null) {
            if (!serviceLocationId) {
                $(`#${selectElementId}`).empty().append('<option value="">Pilih Tiket</option>');
                return;
            }
            $.get(`/api/service-locations/${serviceLocationId}/tickets`).done(function(data) { // Assuming an API endpoint
                const select = $(`#${selectElementId}`);
                select.empty().append('<option value="">Pilih Tiket</option>');
                data.forEach(ticket => select.append(`<option value="${ticket.id}">${ticket.kode} - ${ticket.title}</option>`));
                if (selectedValue) select.val(selectedValue).trigger('change');
            });
        }

        function loadTechnicianDebts(technicianId, assetId, debtSelectElementId, trackedAssetSelectElementId, selectedDebtId = null, selectedTrackedAssetId = null) {
            const debtSelect = $(`#${debtSelectElementId}`);
            const trackedAssetSelect = $(`#${trackedAssetSelectElementId}`);
            debtSelect.empty().append('<option value="">Pilih Hutang Aset</option>');
            trackedAssetSelect.empty().append('<option value="">Pilih Unit Terlacak (Jika berlaku)</option>');

            if (!technicianId || !assetId) return;

            $.get(`{{ url('asset-management/checkout/get-technician-active-debts') }}/${technicianId}`).done(function(data) {
                data.forEach(debt => {
                    // Populate debt dropdown
                    if (debt.asset_name === allAssets.find(a => a.id == assetId).name) { // Match by asset name (could be asset_id for better precision)
                        debtSelect.append(`<option value="${debt.id}" data-is-tracked="${debt.is_tracked}" data-tracked-asset-id="${debt.tracked_asset_id}">${debt.asset_name} (Qty: ${debt.current_debt_quantity} ${debt.unit_of_measure}) - ${debt.current_debt_value}</option>`);
                    }
                    // Populate tracked asset dropdown if it's a tracked asset
                    if (debt.is_tracked && debt.tracked_asset_id) {
                        trackedAssetSelect.append(`<option value="${debt.tracked_asset_id}">${debt.asset_name} (${debt.identifier}) - Status: ${debt.tracked_asset_current_status}</option>`);
                    }
                });
                if (selectedDebtId) debtSelect.val(selectedDebtId);
                if (selectedTrackedAssetId) trackedAssetSelect.val(selectedTrackedAssetId);
            });
        }

        // --- Install Asset Modal Logic ---
        $('#installAssetModal').on('show.bs.modal', function() {
            loadCustomers('install_customer_id');
            // Assuming all assets are passed from controller (as `allAssets`)
            const assetSelect = $('#install_asset_id');
            assetSelect.empty().append('<option value="">Pilih Jenis Aset</option>');
            allAssets.forEach(asset => {
                assetSelect.append(`<option value="${asset.id}" data-requires-qr="${asset.requires_qr_tracking}" data-unit="${asset.asset_category.unit ?? 'unit'}">${asset.name} (${asset.asset_category.unit ?? 'unit'})</option>`);
            });

            $('#install_length').hide().val('');
            $('.install-length-required').hide();
            $('.install-length-hint').hide();
        });

        $('#install_customer_id').on('change', function() {
            loadServiceLocations($(this).val(), 'install_service_location_id');
        });

        $('#install_service_location_id').on('change', function() {
            loadTickets($(this).val(), 'install_ticket_id');
        });

        $('#install_asset_id').on('change', function() {
            const selectedAsset = allAssets.find(a => a.id == $(this).val());
            const requiresQr = $(this).find('option:selected').data('requires-qr');
            const isCable = selectedAsset && selectedAsset.asset_category.unit.toLowerCase().includes('meter');

            // Toggle tracked asset ID and quantity based on `requires_qr_tracking`
            if (requiresQr) {
                $('#install_tracked_asset_id').closest('.mb-3').show();
                $('#install_quantity').val(1).prop('readonly', true); // Fixed quantity to 1
            } else {
                $('#install_tracked_asset_id').closest('.mb-3').hide();
                $('#install_tracked_asset_id').val(''); // Clear selection
                $('#install_quantity').prop('readonly', false); // Allow quantity input
            }

            // Toggle length input for cables
            if (isCable) {
                $('#install_length').show().prop('required', true);
                $('.install-length-required').show();
                $('.install-length-hint').show();
            } else {
                $('#install_length').hide().prop('required', false).val('');
                $('.install-length-required').hide();
                $('.install-length-hint').hide();
            }

            // Load technician debts for selected asset (assuming current user is technician or will select one)
            // For now, assume technician is current user
            loadTechnicianDebts({{ Auth::id() }}, $(this).val(), 'install_debt_id', 'install_tracked_asset_id');
        });

        $('#installAssetForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitInstallBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

            const formData = new FormData(form[0]);

            // Append photos manually if any, to avoid issues with serialize()
            const photos = $('#installation_photos')[0].files;
            if (photos.length > 0) {
                for (let i = 0; i < photos.length; i++) {
                    formData.append('installation_photos[]', photos[i]);
                }
            }

            $.ajax({
                url: '{{ route("asset-management.customer-assets.install") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    toastr.success(response.message);
                    $('#installAssetModal').modal('hide');
                    customerAssetsTable.ajax.reload();
                    form.trigger('reset'); // Reset the form
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON?.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Terjadi kesalahan saat menginstal aset.');
                    }
                    submitBtn.prop('disabled', false).html('Simpan Instalasi');
                }
            });
        });

        // --- Remove Asset Modal Logic ---
        $(document).on('click', '.remove-asset', function() {
            const ciaId = $(this).data('id');
            const rowData = customerAssetsTable.row($(this).closest('tr')).data(); // Get current row data
            $('#remove_cia_id').val(ciaId);
            $('#remove-asset-name').text(rowData.asset_name);
            $('#remove-asset-identifier').text(rowData.asset_identifier);

            const isTracked = rowData.asset_identifier !== '-'; // Simple check if it's a tracked asset
            const isCable = rowData.is_cable;

            if (isTracked && !isCable) { // Show warehouse return options only for non-cable tracked assets
                $('#return_to_warehouse_section').show();
                $('#status_on_return_section').show();
            } else {
                $('#return_to_warehouse_section').hide();
                $('#status_on_return_section').hide();
            }
            $('#removeAssetModal').modal('show');
        });

        $('#removeAssetForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitRemoveBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

            const formData = new FormData(form[0]);
            const photos = $('#removal_photos')[0].files;
            if (photos.length > 0) {
                for (let i = 0; i < photos.length; i++) {
                    formData.append('removal_photos[]', photos[i]);
                }
            }

            $.ajax({
                url: '{{ route("asset-management.customer-assets.remove") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    toastr.success(response.message);
                    $('#removeAssetModal').modal('hide');
                    customerAssetsTable.ajax.reload();
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON?.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Terjadi kesalahan saat menarik aset.');
                    }
                    submitBtn.prop('disabled', false).html('Konfirmasi Penarikan');
                }
            });
        });

        // --- Replace Asset Modal Logic ---
        $(document).on('click', '.replace-asset', function() {
            const ciaId = $(this).data('id');
            const rowData = customerAssetsTable.row($(this).closest('tr')).data();
            $('#replace_old_cia_id').val(ciaId);
            $('#old-replace-asset-name').text(rowData.asset_name);
            $('#old-replace-asset-identifier').text(rowData.asset_identifier);

            const isOldTracked = rowData.asset_identifier !== '-';
            const isOldCable = rowData.is_cable;

            if (isOldTracked && !isOldCable) {
                $('#old_asset_return_to_warehouse_section').show();
                $('#status_old_asset_on_return_section').show();
            } else {
                $('#old_asset_return_to_warehouse_section').hide();
                $('#status_old_asset_on_return_section').hide();
            }

            // Populate new asset dropdowns
            const newAssetSelect = $('#new_asset_id');
            newAssetSelect.empty().append('<option value="">Pilih Jenis Aset</option>');
            allAssets.forEach(asset => {
                newAssetSelect.append(`<option value="${asset.id}" data-requires-qr="${asset.requires_qr_tracking}" data-unit="${asset.asset_category.unit ?? 'unit'}">${asset.name} (${asset.asset_category.unit ?? 'unit'})</option>`);
            });
            $('#new_installed_length').hide().val('');
            $('.new-install-length-required').hide();
            $('.new-install-length-hint').hide();

            $('#replaceAssetModal').modal('show');
        });

        $('#new_asset_id').on('change', function() {
            const selectedAsset = allAssets.find(a => a.id == $(this).val());
            const requiresQr = $(this).find('option:selected').data('requires-qr');
            const isCable = selectedAsset && selectedAsset.asset_category.unit.toLowerCase().includes('meter');

            // Toggle new tracked asset ID and quantity
            if (requiresQr) {
                $('#new_tracked_asset_id').closest('.mb-3').show();
                $('#new_quantity_installed').val(1).prop('readonly', true);
            } else {
                $('#new_tracked_asset_id').closest('.mb-3').hide();
                $('#new_tracked_asset_id').val('');
                $('#new_quantity_installed').prop('readonly', false);
            }

            // Toggle new length input for cables
            if (isCable) {
                $('#new_installed_length').show().prop('required', true);
                $('.new-install-length-required').show();
                $('.new-install-length-hint').show();
            } else {
                $('#new_installed_length').hide().prop('required', false).val('');
                $('.new-install-length-required').hide();
                $('.new-install-length-hint').hide();
            }

            // Load technician debts for new asset
            // Assuming technician for replacement is current user
            loadTechnicianDebts({{ Auth::id() }}, $(this).val(), 'new_debt_id', 'new_tracked_asset_id');
        });

        $('#replaceAssetForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitReplaceBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

            const formData = new FormData(form[0]);
            const photos = $('#replacement_photos')[0].files;
            if (photos.length > 0) {
                for (let i = 0; i < photos.length; i++) {
                    formData.append('replacement_photos[]', photos[i]);
                }
            }

            $.ajax({
                url: '{{ route("asset-management.customer-assets.replace") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    toastr.success(response.message);
                    $('#replaceAssetModal').modal('hide');
                    customerAssetsTable.ajax.reload();
                    form.trigger('reset');
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON?.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Terjadi kesalahan saat mengganti aset.');
                    }
                    submitBtn.prop('disabled', false).html('Konfirmasi Penggantian');
                }
            });
        });

        // --- Audit Cable Length Modal Logic ---
        $(document).on('click', '.audit-cable-length', function() {
            const ciaId = $(this).data('id');
            $('#audit_cia_id').val(ciaId);

            // Fetch current details for the modal
            $.get(`{{ url('asset-management/customer-assets/get-customer-data') }}/${ciaId}`)
                .done(function(data) {
                    const assetData = data[0]; // Assuming it returns an array with one item
                    $('#audit-asset-name').text(assetData.asset_name);
                    $('#audit-asset-identifier').text(assetData.identifier);
                    $('#audit-initial-length').text(assetData.installed_length);
                    $('#audit-current-length-system').text(assetData.current_length);
                    $('#new_current_length').val(parseFloat(assetData.current_length.split(' ')[0])); // Pre-fill with current system value
                    $('#auditCableLengthModal').modal('show');
                })
                .fail(function() {
                    toastr.error('Gagal memuat data aset untuk audit.');
                });
        });

        $('#auditCableLengthForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitAuditBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

            $.ajax({
                url: '{{ route("asset-management.customer-assets.audit-adjust") }}',
                method: 'POST',
                data: form.serialize(),
                success: function(response) {
                    toastr.success(response.message);
                    $('#auditCableLengthModal').modal('hide');
                    customerAssetsTable.ajax.reload();
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON?.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Terjadi kesalahan saat menyimpan audit.');
                    }
                    submitBtn.prop('disabled', false).html('Simpan Audit');
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush$