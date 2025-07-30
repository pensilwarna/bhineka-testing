{{-- File: resources/views/asset-management/debts/settlements-index.blade.php --}}
@extends('layouts.app')

@section('title')
Riwayat Penyelesaian Hutang
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Hutang Aset Teknisi /</span> Riwayat Penyelesaian</h4>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Filter Riwayat Penyelesaian</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="filter_technician" class="form-label">Teknisi</label>
                    <select class="form-select" id="filter_technician">
                        <option value="">Semua Teknisi</option>
                        @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="button" id="apply_filter" class="btn btn-primary me-2">Terapkan Filter</button>
                    <button type="button" id="reset_filter" class="btn btn-label-secondary">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Daftar Riwayat Penyelesaian Hutang</h5>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="settlementsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Teknisi</th>
                        <th>Tipe</th>
                        <th>Total Hutang</th>
                        <th>Potongan Gaji</th>
                        <th>Pembayaran Tunai</th>
                        <th>Tgl Penyelesaian</th>
                        <th>Diproses Oleh</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        var settlementsTable = $('#settlementsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("asset-management.debts.settlements.get-data") }}',
                data: function(d) {
                    d.technician_id = $('#filter_technician').val();
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'technician_name', name: 'technician.name' },
                { data: 'settlement_type', name: 'settlement_type' },
                { data: 'formatted_total_debt_amount', name: 'total_debt_amount' },
                { data: 'formatted_salary_deduction', name: 'salary_deduction' },
                { data: 'formatted_cash_payment', name: 'cash_payment' },
                { data: 'settlement_date', name: 'settlement_date' },
                { data: 'processed_by_name', name: 'processedBy.name' },
                { data: 'status_label', name: 'status' },
                { data: 'notes', name: 'notes' },
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Cari Penyelesaian...',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri'
            }
        });

        $('#apply_filter').on('click', function() {
            settlementsTable.ajax.reload();
        });

        $('#reset_filter').on('click', function() {
            $('#filter_technician').val('').trigger('change');
            settlementsTable.ajax.reload();
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush