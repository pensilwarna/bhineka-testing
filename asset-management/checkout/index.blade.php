{{-- File: resources/views/asset-management/checkout/index.blade.php --}}
@extends('layouts.app')

@section('title')
Checkout & Pengembalian Aset
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Asset Management /</span> Checkout & Pengembalian</h4>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Form Checkout Aset Baru</h5>
        </div>
        <div class="card-body">
            <form id="checkoutForm">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="technician_id" class="form-label">Teknisi <span class="text-danger">*</span></label>
                        <select class="form-select" id="technician_id" name="technician_id" required>
                            <option value="">Pilih Teknisi</option>
                            @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="warehouse_id" class="form-label">Gudang Asal <span class="text-danger">*</span></label>
                        <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                            <option value="">Pilih Gudang</option>
                            @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="checkout_notes" class="form-label">Catatan Checkout</label>
                    <textarea class="form-control" id="checkout_notes" name="notes" rows="2"></textarea>
                </div>

                <hr class="my-4">

                <h5>Daftar Aset yang Dibawa Teknisi</h5>
                <div id="checkout-items-container">
                    </div>
                <button type="button" class="btn btn-success mt-3" id="add-checkout-item-btn">
                    <i class="ti ti-plus me-1"></i>Tambah Aset
                </button>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary" id="submitCheckoutBtn">Proses Checkout</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Form Pengembalian Aset Teknisi</h5>
        </div>
        <div class="card-body">
            <form id="returnForm">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="return_technician_id" class="form-label">Teknisi <span class="text-danger">*</span></label>
                        <select class="form-select" id="return_technician_id" name="technician_id" required>
                            <option value="">Pilih Teknisi</option>
                            @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="returned_to_warehouse_id" class="form-label">Gudang Tujuan Pengembalian <span class="text-danger">*</span></label>
                        <select class="form-select" id="returned_to_warehouse_id" name="returned_to_warehouse_id" required>
                            <option value="">Pilih Gudang</option>
                            @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <h6 class="mt-4">Aset Aktif yang Dihutang oleh Teknisi Ini:</h6>
                <div id="active-debts-container" class="mb-4">
                    <p class="text-muted">Pilih teknisi untuk melihat daftar hutang aktifnya.</p>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-success" id="submitReturnBtn" disabled>Proses Pengembalian</button>
                </div>
            </form>
        </div>
    </div>


    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Riwayat Checkout Aset</h5>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="checkoutsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Teknisi</th>
                        <th>Gudang Asal</th>
                        <th>Tanggal Checkout</th>
                        <th>Total Item</th>
                        <th>Total Nilai</th>
                        <th>Melebihi Limit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="scanAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Scan QR / Input SN/MAC</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentCheckoutItemIndex">
                <div class="mb-3">
                    <label for="qr_sn_mac_input" class="form-label">Scan QR Code atau Input Serial/MAC</label>
                    <input type="text" class="form-control" id="qr_sn_mac_input" placeholder="Scan atau ketik kode aset">
                </div>
                <div id="scanned-asset-details" class="alert alert-info" style="display: none;">
                    <strong>Aset Ditemukan:</strong> <span id="scanned-asset-name"></span><br>
                    <strong>Status:</strong> <span id="scanned-asset-status"></span><br>
                    <strong>Gudang:</strong> <span id="scanned-asset-warehouse"></span>
                </div>
                <div class="alert alert-danger" id="scan-error-message" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="addScannedAssetBtn" disabled>Tambahkan Aset</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    const allAssetsMaster = @json($assets); // Master asset list from controller
    const allWarehouses = @json($warehouses);

    $(document).ready(function() {
        let checkoutItemIndex = 0;
        let selectedTrackedAsset = null; // To hold data of asset scanned via modal

        // --- Checkout Form Logic ---
        function addCheckoutItemRow(item = null) {
            const currentIndex = checkoutItemIndex++;
            const row = `
                <div class="card mb-2 p-3 checkout-item-row" data-index="${currentIndex}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>Aset Checkout #${currentIndex + 1}</h6>
                        <button type="button" class="btn btn-danger btn-sm remove-checkout-item-btn"><i class="ti ti-trash"></i></button>
                    </div>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="checkout_asset_id_${currentIndex}" class="form-label">Jenis Aset <span class="text-danger">*</span></label>
                            <select class="form-select checkout-asset-select" id="checkout_asset_id_${currentIndex}" name="items[${currentIndex}][asset_id]" data-index="${currentIndex}" required>
                                <option value="">Pilih Jenis Aset</option>
                                @foreach($assets as $asset)
                                <option value="{{ $asset->id }}" 
                                    data-requires-qr="{{ $asset->requires_qr_tracking ? 'true' : 'false' }}" 
                                    data-unit="{{ $asset->asset_category->unit ?? 'unit' }}">{{ $asset->name }} ({{ $asset->asset_category->unit ?? 'unit' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="checkout_quantity_${currentIndex}" class="form-label">Kuantitas <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" class="form-control" id="checkout_quantity_${currentIndex}" name="items[${currentIndex}][quantity]" required min="0.001">
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <input type="hidden" name="items[${currentIndex}][is_tracked]" id="is_tracked_${currentIndex}" value="false">
                            <input type="hidden" name="items[${currentIndex}][tracked_asset_id]" id="tracked_asset_id_${currentIndex}">
                            <button type="button" class="btn btn-info btn-sm scan-checkout-asset-btn" data-index="${currentIndex}" style="display:none;">
                                <i class="ti ti-qrcode me-1"></i>Scan / Pilih Unit
                            </button>
                            <span class="ms-2 scanned-asset-info" style="display:none; color: green;">Unit: <strong id="scanned_identifier_${currentIndex}"></strong></span>
                        </div>
                    </div>
                </div>
            `;
            $('#checkout-items-container').append(row);
        }

        addCheckoutItemRow(); // Add first row on load

        $('#add-checkout-item-btn').on('click', function() {
            addCheckoutItemRow();
        });

        $(document).on('click', '.remove-checkout-item-btn', function() {
            $(this).closest('.checkout-item-row').remove();
        });

        // Toggle scan button based on asset type
        $(document).on('change', '.checkout-asset-select', function() {
            const index = $(this).data('index');
            const requiresQr = $(this).find('option:selected').data('requires-qr');
            const scanBtn = $(this).closest('.checkout-item-row').find('.scan-checkout-asset-btn');
            const isTrackedInput = $(`#is_tracked_${index}`);
            const trackedAssetIdInput = $(`#tracked_asset_id_${index}`);
            const quantityInput = $(`#checkout_quantity_${index}`);
            const scannedInfoSpan = $(`#scanned_identifier_${index}`).parent();

            if (requiresQr === true || requiresQr === 'true') {
                scanBtn.show();
                isTrackedInput.val('true');
                quantityInput.val(1).prop('readonly', true); // Quantity fixed to 1 for tracked assets
                scannedInfoSpan.hide(); // Hide info until scanned
                trackedAssetIdInput.val(''); // Clear previous selection
            } else {
                scanBtn.hide();
                isTrackedInput.val('false');
                quantityInput.prop('readonly', false); // Enable quantity for non-tracked
                scannedInfoSpan.hide(); // Hide info
                trackedAssetIdInput.val(''); // Clear previous selection
            }
        });

        // Open scan modal
        $(document).on('click', '.scan-checkout-asset-btn', function() {
            const index = $(this).data('index');
            const selectedAssetId = $(`#checkout_asset_id_${index}`).val();

            if (!selectedAssetId) {
                toastr.warning('Pilih jenis aset terlebih dahulu.');
                return;
            }

            $('#currentCheckoutItemIndex').val(index);
            $('#qr_sn_mac_input').val('').focus();
            $('#scanned-asset-details').hide();
            $('#scan-error-message').hide();
            $('#addScannedAssetBtn').prop('disabled', true);
            $('#scanAssetModal').modal('show');
        });

        // Handle scan input in modal
        $('#qr_sn_mac_input').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                const identifier = $(this).val();
                if (identifier) {
                    $.post('{{ route("asset-management.qr.get-asset-by-qr") }}', {
                        qr_code: identifier,
                        _token: '{{ csrf_token() }}'
                    })
                    .done(function(response) {
                        if (response.success && response.asset) {
                            const scannedAsset = response.asset;
                            const currentItemIndex = $('#currentCheckoutItemIndex').val();
                            const selectedAssetId = $(`#checkout_asset_id_${currentItemIndex}`).val();
                            const selectedMasterAsset = allAssetsMaster.find(a => a.id == selectedAssetId);

                            // Validate if the scanned asset matches the selected master asset type
                            if (scannedAsset.name !== selectedMasterAsset.name) {
                                $('#scan-error-message').text('Aset yang discan tidak cocok dengan jenis aset yang dipilih (Jenis yang dipilih: ' + selectedMasterAsset.name + ').').show();
                                $('#scanned-asset-details').hide();
                                $('#addScannedAssetBtn').prop('disabled', true);
                                selectedTrackedAsset = null;
                                return;
                            }
                            
                            // Validate status and warehouse
                            if (scannedAsset.current_status !== 'available' || scannedAsset.warehouse !== allWarehouses.find(w => w.id == $(`#warehouse_id`).val()).name) {
                                $('#scan-error-message').text('Aset ini tidak tersedia di gudang yang dipilih atau statusnya tidak "available". Status: ' + scannedAsset.current_status + ', Gudang: ' + scannedAsset.warehouse).show();
                                $('#scanned-asset-details').hide();
                                $('#addScannedAssetBtn').prop('disabled', true);
                                selectedTrackedAsset = null;
                                return;
                            }


                            $('#scanned-asset-name').text(scannedAsset.name + (scannedAsset.serial_number ? ' (SN: ' + scannedAsset.serial_number + ')' : ''));
                            $('#scanned-asset-status').text(scannedAsset.current_status);
                            $('#scanned-asset-warehouse').text(scannedAsset.warehouse);
                            $('#scanned-asset-details').show();
                            $('#scan-error-message').hide();
                            $('#addScannedAssetBtn').prop('disabled', false);
                            selectedTrackedAsset = scannedAsset; // Store the scanned asset for adding
                        } else {
                            $('#scan-error-message').text(response.message || 'Aset tidak ditemukan.').show();
                            $('#scanned-asset-details').hide();
                            $('#addScannedAssetBtn').prop('disabled', true);
                            selectedTrackedAsset = null;
                        }
                    })
                    .fail(function(xhr) {
                        $('#scan-error-message').text(xhr.responseJSON?.message || 'Terjadi kesalahan saat mencari aset.').show();
                        $('#scanned-asset-details').hide();
                        $('#addScannedAssetBtn').prop('disabled', true);
                        selectedTrackedAsset = null;
                    });
                }
            }
        });

        // Add scanned asset to form
        $('#addScannedAssetBtn').on('click', function() {
            if (selectedTrackedAsset) {
                const index = $('#currentCheckoutItemIndex').val();
                $(`#tracked_asset_id_${index}`).val(selectedTrackedAsset.id);
                $(`#scanned_identifier_${index}`).text(selectedTrackedAsset.qr_code || selectedTrackedAsset.serial_number || selectedTrackedAsset.mac_address || selectedTrackedAsset.id);
                $(`#scanned_identifier_${index}`).parent().show();
                $('#scanAssetModal').modal('hide');
            } else {
                toastr.error('Tidak ada aset yang discan atau aset tidak valid.');
            }
        });

        // Submit Checkout Form
        $('#checkoutForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitCheckoutBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

            const formData = new FormData(form[0]);
            const items = [];
            let isValid = true;

            $('.checkout-item-row').each(function(index, rowElement) {
                const itemData = {};
                const itemIndex = $(rowElement).data('index');
                
                itemData.asset_id = $(rowElement).find('.checkout-asset-select').val();
                itemData.quantity = parseFloat($(rowElement).find(`[name="items[${itemIndex}][quantity]"]`).val());
                itemData.is_tracked = $(rowElement).find(`[name="items[${itemIndex}][is_tracked]"]`).val() === 'true';
                itemData.tracked_asset_id = $(rowElement).find(`[name="items[${itemIndex}][tracked_asset_id]"]`).val();

                if (itemData.is_tracked && !itemData.tracked_asset_id) {
                    toastr.error(`Item #${itemIndex + 1}: Aset terlacak belum discan/dipilih.`);
                    isValid = false;
                    return false; // Stop loop
                }
                items.push(itemData);
            });

            if (!isValid) {
                submitBtn.prop('disabled', false).html('Proses Checkout');
                return;
            }

            formData.delete('items');
            items.forEach((item, idx) => {
                for (const key in item) {
                    if (item.hasOwnProperty(key)) {
                        formData.append(`items[${idx}][${key}]`, item[key]);
                    }
                }
            });

            $.ajax({
                url: '{{ route("asset-management.checkout.process") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    toastr.success(response.message);
                    if (response.exceed_limit_warning) {
                        toastr.warning('Perhatian: Limit hutang teknisi terlampaui. Perlu approval NOC.');
                    }
                    form.trigger('reset');
                    $('#checkout-items-container').empty();
                    checkoutItemIndex = 0;
                    addCheckoutItemRow(); // Add first row back
                    checkoutsTable.ajax.reload(); // Reload history table
                    loadTechnicianDebts($('#return_technician_id').val()); // Refresh return form too
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON.message || 'Terjadi kesalahan saat checkout aset.');
                    }
                    submitBtn.prop('disabled', false).html('Proses Checkout');
                }
            });
        });

        // --- Return Form Logic ---
        $('#return_technician_id').on('change', function() {
            const technicianId = $(this).val();
            if (technicianId) {
                loadTechnicianDebts(technicianId);
            } else {
                $('#active-debts-container').html('<p class="text-muted">Pilih teknisi untuk melihat daftar hutang aktifnya.</p>');
                $('#submitReturnBtn').prop('disabled', true);
            }
        });

        function loadTechnicianDebts(technicianId) {
            $.get(`{{ url('asset-management/checkout/get-technician-active-debts') }}/${technicianId}`)
                .done(function(data) {
                    let html = '';
                    if (data.length > 0) {
                        html += `<table class="table table-sm"><thead><tr><th><input type="checkbox" id="select-all-debts" class="form-check-input"></th><th>Aset</th><th>Identifikasi</th><th>Kuantitas Dihutang</th><th>Kuantitas Tersisa</th><th>Nilai Hutang</th><th>Status Aset Fisik</th><th>Aksi</th></tr></thead><tbody>`;
                        data.forEach(function(debt, index) {
                            const isCable = debt.unit_of_measure.toLowerCase().includes('meter');
                            const qtyDisplay = isCable ? `${debt.current_debt_quantity} ${debt.unit_of_measure}` : debt.current_debt_quantity;
                            const isTracked = debt.is_tracked;

                            html += `
                                <tr>
                                    <td><input type="checkbox" class="form-check-input debt-checkbox" data-debt-id="${debt.id}" data-is-tracked="${isTracked}" data-asset-id="${debt.asset_name}" data-tracked-asset-id="${debt.tracked_asset_id}" data-current-debt-quantity="${debt.current_debt_quantity}"></td>
                                    <td>${debt.asset_name}</td>
                                    <td>${debt.identifier || '-'}</td>
                                    <td>${debt.quantity_taken} ${debt.unit_of_measure}</td>
                                    <td>${qtyDisplay}</td>
                                    <td>${debt.current_debt_value}</td>
                                    <td>${isTracked ? '<span class="badge bg-label-' + getStatusBadgeClass(debt.tracked_asset_current_status) + '">' + capitalizeFirstLetter(debt.tracked_asset_current_status.replace(/_/g, ' ')) + '</span>' : 'N/A'}</td>
                                    <td>
                                        ${isTracked ? `
                                            <select class="form-select form-select-sm status-on-return" data-debt-id="${debt.id}" name="returned_items[${index}][tracked_asset_status_on_return]">
                                                <option value="available">Available</option>
                                                <option value="damaged">Damaged</option>
                                                <option value="scrap">Scrap</option>
                                                <option value="lost">Lost</option>
                                            </select>
                                        ` : `
                                            <input type="number" step="0.001" class="form-control form-control-sm quantity-to-return" data-debt-id="${debt.id}" name="returned_items[${index}][quantity_returned]" value="${debt.current_debt_quantity}" min="0.001" max="${debt.current_debt_quantity}">
                                        `}
                                        <input type="hidden" name="returned_items[${index}][debt_id]" value="${debt.id}">
                                        <input type="hidden" name="returned_items[${index}][is_tracked]" value="${isTracked}">
                                    </td>
                                </tr>
                            `;
                        });
                        html += `</tbody></table>`;
                        $('#submitReturnBtn').prop('disabled', false);
                    } else {
                        html = '<p class="text-muted">Tidak ada aset aktif yang dihutangkan oleh teknisi ini.</p>';
                        $('#submitReturnBtn').prop('disabled', true);
                    }
                    $('#active-debts-container').html(html);
                })
                .fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Gagal memuat hutang teknisi.');
                    $('#active-debts-container').html('<p class="text-danger">Gagal memuat data hutang.</p>');
                    $('#submitReturnBtn').prop('disabled', true);
                });
        }

        // Select All Debts for Return
        $(document).on('change', '#select-all-debts', function() {
            $('.debt-checkbox').prop('checked', this.checked);
        });

        // Submit Return Form
        $('#returnForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitReturnBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

            const selectedDebts = $('.debt-checkbox:checked');
            if (selectedDebts.length === 0) {
                toastr.warning('Pilih minimal satu aset untuk dikembalikan.');
                submitBtn.prop('disabled', false).html('Proses Pengembalian');
                return;
            }

            const returnedItemsData = [];
            selectedDebts.each(function() {
                const debtId = $(this).data('debt-id');
                const isTracked = $(this).data('is-tracked');
                const itemData = {
                    debt_id: debtId,
                    is_tracked: isTracked,
                };

                if (isTracked) {
                    itemData.quantity_returned = 1; // Always 1 for tracked assets
                    itemData.tracked_asset_status_on_return = $(`select.status-on-return[data-debt-id="${debtId}"]`).val();
                } else {
                    itemData.quantity_returned = parseFloat($(`input.quantity-to-return[data-debt-id="${debtId}"]`).val());
                }
                returnedItemsData.push(itemData);
            });

            const formData = {
                _token: '{{ csrf_token() }}',
                technician_id: $('#return_technician_id').val(),
                returned_to_warehouse_id: $('#returned_to_warehouse_id').val(),
                returned_items: returnedItemsData
            };

            $.ajax({
                url: '{{ route("asset-management.checkout.return") }}',
                method: 'POST',
                data: formData,
                success: function(response) {
                    toastr.success(response.message);
                    loadTechnicianDebts($('#return_technician_id').val()); // Reload active debts
                    checkoutsTable.ajax.reload(); // Reload historical checkouts
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON.message || 'Terjadi kesalahan saat memproses pengembalian.');
                    }
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Proses Pengembalian');
                }
            });
        });

        // --- DataTables for Historical Checkouts ---
        var checkoutsTable = $('#checkoutsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("asset-management.checkout.get-data") }}',
            columns: [
                { data: 'id', name: 'id' },
                { data: 'technician_name', name: 'technician.name' },
                { data: 'warehouse_name', name: 'warehouse.name' },
                { data: 'checkout_date', name: 'checkout_date' },
                { data: 'total_items', name: 'total_items' },
                { data: 'formatted_total_value', name: 'total_value' },
                { data: 'exceed_limit_status', name: 'exceed_limit' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Cari Checkout...',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri'
            }
        });

        function getStatusBadgeClass(status) {
            switch (status) {
                case 'available': return 'success';
                case 'loaned':
                case 'in_transit': return 'info';
                case 'installed': return 'primary';
                case 'damaged':
                case 'in_repair':
                case 'awaiting_return_to_supplier': return 'warning';
                case 'written_off':
                case 'scrap':
                case 'lost': return 'danger';
                default: return 'secondary';
            }
        }

        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush