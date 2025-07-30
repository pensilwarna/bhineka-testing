{{-- File: resources/views/asset-management/debts/index.blade.php --}}
@extends('layouts.app')

@section('title')
Manajemen Hutang Aset Teknisi
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Asset Management /</span> Hutang Aset Teknisi</h4>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Filter Hutang Aset</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="filter_technician" class="form-label">Teknisi</label>
                    <select class="form-select" id="filter_technician">
                        <option value="">Semua Teknisi</option>
                        @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filter_status" class="form-label">Status Hutang</label>
                    <select class="form-select" id="filter_status">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="partially_returned">Dikembalikan Sebagian</option>
                        <option value="fully_settled">Lunas</option>
                        <option value="written_off">Dihapus</option>
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
            <h5 class="card-title mb-0">Daftar Hutang Aset</h5>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="debtsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID Hutang</th>
                        <th>Teknisi</th>
                        <th>Nama Aset</th>
                        <th>Identifikasi Aset</th>
                        <th>Kategori</th>
                        <th>Gudang Asal</th>
                        <th>Tgl Checkout</th>
                        <th>Qty Awal</th>
                        <th>Qty Tersisa</th>
                        <th>Nilai Hutang Awal</th>
                        <th>Nilai Hutang Sisa</th>
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

<div class="modal fade" id="settleDebtModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settleDebtModalTitle">Selesaikan Hutang Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="settleDebtForm">
                @csrf
                <input type="hidden" name="technician_id" id="settle_technician_id">
                <input type="hidden" name="debt_ids[]" id="settle_debt_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="settlement_type" class="form-label">Jenis Penyelesaian <span class="text-danger">*</span></label>
                        <select class="form-select" id="settlement_type" name="settlement_type" required>
                            <option value="adhoc">Ad Hoc</option>
                            <option value="monthly">Bulanan</option>
                            <option value="weekly">Mingguan</option>
                            <option value="daily">Harian</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="total_amount_to_settle" class="form-label">Total Hutang yang Dipilih</label>
                        <input type="text" class="form-control" id="total_amount_to_settle" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="salary_deduction_amount" class="form-label">Potongan Gaji (Rp)</label>
                        <input type="number" step="0.01" class="form-control" id="salary_deduction_amount" name="salary_deduction_amount" min="0" value="0">
                    </div>
                    <div class="mb-3">
                        <label for="cash_payment_amount" class="form-label">Pembayaran Tunai (Rp)</label>
                        <input type="number" step="0.01" class="form-control" id="cash_payment_amount" name="cash_payment_amount" min="0" value="0">
                    </div>
                    <div class="mb-3">
                        <label for="settlement_notes" class="form-label">Catatan</label>
                        <textarea class="form-control" id="settlement_notes" name="notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submitSettleBtn">Simpan Penyelesaian</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        var debtsTable = $('#debtsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("asset-management.debts.get-data") }}',
                data: function(d) {
                    d.technician_id = $('#filter_technician').val();
                    d.status = $('#filter_status').val();
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'technician_name', name: 'technician.name' },
                { data: 'asset_name', name: 'asset.name' },
                { data: 'asset_identifier', name: 'trackedAsset.qr_code', orderable: false, searchable: false },
                { data: 'category_name', name: 'asset.asset_category.name' },
                { data: 'warehouse_name', name: 'warehouse.name' },
                { data: 'checkout_date', name: 'checkout_date' },
                { data: 'quantity_taken', name: 'quantity_taken' },
                { data: 'current_debt_quantity', name: 'current_debt_quantity' },
                { data: 'formatted_total_debt_value', name: 'total_debt_value' },
                { data: 'formatted_current_debt_value', name: 'current_debt_value' },
                { data: 'status_label', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Cari Hutang...',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri'
            }
        });

        $('#apply_filter').on('click', function() {
            debtsTable.ajax.reload();
        });

        $('#reset_filter').on('click', function() {
            $('#filter_technician').val('').trigger('change');
            $('#filter_status').val('').trigger('change');
            debtsTable.ajax.reload();
        });

        // Open Settle Debt Modal
        $(document).on('click', '.settle-debt', function() {
            const debtId = $(this).data('id');
            const technicianId = $(this).data('technician-id');
            const currentValue = $(this).data('current-value');

            $('#settle_technician_id').val(technicianId);
            $('#settle_debt_id').val(debtId); // For single debt settlement
            $('#total_amount_to_settle').val(currentValue).trigger('change'); // Set default value
            $('#salary_deduction_amount').val(currentValue); // Suggest full deduction by default
            $('#cash_payment_amount').val(0);
            $('#settlement_notes').val('');
            $('#settlement_type').val('adhoc'); // Default settlement type

            $('#settleDebtModalTitle').text('Selesaikan Hutang Aset (ID: ' + debtId + ')');
            $('#settleDebtModal').modal('show');
        });

        // Update total payment display
        $('#salary_deduction_amount, #cash_payment_amount').on('input', function() {
            const salary = parseFloat($('#salary_deduction_amount').val()) || 0;
            const cash = parseFloat($('#cash_payment_amount').val()) || 0;
            const total = salary + cash;
            // You can add a display for total paid vs total debt here if needed
        });

        // Submit Settle Debt Form
        $('#settleDebtForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitSettleBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

            $.ajax({
                url: '{{ route("asset-management.debts.settle") }}',
                method: 'POST',
                data: form.serialize(),
                success: function(response) {
                    toastr.success(response.message);
                    $('#settleDebtModal').modal('hide');
                    debtsTable.ajax.reload();
                    // Optionally reload technician debt summary somewhere if displayed
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON.message || 'Terjadi kesalahan saat menyimpan penyelesaian hutang.');
                    }
                    submitBtn.prop('disabled', false).html('Simpan Penyelesaian');
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush