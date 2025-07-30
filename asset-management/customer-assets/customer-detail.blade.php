{{-- File: resources/views/asset-management/customer-assets/customer-detail.blade.php --}}
@extends('layouts.app')

@section('title')
Aset Terpasang Pelanggan: {{ $customer->name }}
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Aset Terpasang Pelanggan /</span> {{ $customer->name }}</h4>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Informasi Pelanggan</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <strong>Nama Pelanggan:</strong> {{ $customer->name }}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Email:</strong> {{ $customer->email ?? '-' }}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Telepon:</strong> {{ $customer->phone ?? '-' }}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Alamat:</strong> {{ $customer->address ?? '-' }}
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Daftar Aset Terpasang</h5>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="customerSpecificAssetsTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Lokasi Layanan</th>
                        <th>Nama Aset</th>
                        <th>Identifikasi Aset</th>
                        <th>Qty</th>
                        <th>Panjang Terpasang</th>
                        <th>Panjang Saat Ini (Audit)</th>
                        <th>Nilai Total</th>
                        <th>Tgl Instalasi</th>
                        <th>Status</th>
                        <th>Teknisi</th>
                        <th>Actions</th>
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
        var customerSpecificAssetsTable = $('#customerSpecificAssetsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("asset-management.customer-assets.get-customer-data", $customer->id) }}',
            columns: [
                { data: 'id', name: 'id' },
                { data: 'service_location_name', name: 'serviceLocation.address' }, // Assuming you modify API to return name
                { data: 'asset_name', name: 'asset.name' },
                { data: 'identifier', name: 'trackedAsset.qr_code', orderable: false, searchable: false },
                { data: 'quantity_installed', name: 'quantity_installed' },
                { data: 'installed_length', name: 'installed_length' },
                { data: 'current_length', name: 'current_length' },
                { data: 'total_asset_value', name: 'total_asset_value' },
                { data: 'installation_date', name: 'installation_date' },
                { data: 'status', name: 'status' },
                { data: 'technician_name', name: 'technician.name' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false } // You'll need to define actions here
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Cari Aset...',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri'
            }
        });

        // Add any specific actions here if needed for this table (e.g., view detail modal for each row)

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush