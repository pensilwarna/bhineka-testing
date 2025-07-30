{{-- File: resources/views/asset-management/reports/inventory.blade.php --}}
@extends('layouts.app')

@section('title')
Laporan Inventaris Aset
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Laporan Aset /</span> Inventaris</h4>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Laporan Inventaris Aset</h5>
            <div class="d-flex gap-2">
                <a href="{{ route('asset-management.reports.inventory') }}?format=xlsx" class="btn btn-success btn-sm"><i class="ti ti-file-spreadsheet me-1"></i> Export XLSX</a>
                <a href="{{ route('asset-management.reports.inventory') }}?format=pdf" class="btn btn-danger btn-sm"><i class="ti ti-file-text me-1"></i> Export PDF</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Aset</th>
                            <th>Kode Aset</th>
                            <th>Kategori</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Gudang</th>
                            <th>Status</th>
                            <th>Total Qty</th>
                            <th>Avail Qty</th>
                            <th>Unit</th>
                            <th>Harga Standar</th>
                            <th>Requires QR</th>
                            <th>QR Code/SN/MAC</th>
                            <th>Initial Length</th>
                            <th>Current Length</th>
                            <th>Physical Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $item)
                        <tr>
                            <td>{{ $item['ID'] }}</td>
                            <td>{{ $item['Asset Name'] }}</td>
                            <td>{{ $item['Asset Code'] }}</td>
                            <td>{{ $item['Category'] }}</td>
                            <td>{{ $item['Brand'] }}</td>
                            <td>{{ $item['Model'] }}</td>
                            <td>{{ $item['Warehouse'] }}</td>
                            <td>{{ $item['Status'] }}</td>
                            <td>{{ $item['Total Quantity'] }}</td>
                            <td>{{ $item['Available Quantity'] }}</td>
                            <td>{{ $item['Unit'] }}</td>
                            <td>Rp {{ number_format($item['Standard Price'], 0, ',', '.') }}</td>
                            <td>{{ $item['Requires QR'] }}</td>
                            <td>{{ $item['QR Code/SN/MAC'] }}</td>
                            <td>{{ $item['Initial Length'] }}</td>
                            <td>{{ $item['Current Length'] }}</td>
                            <td>{{ $item['Physical Status'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="17" class="text-center">Tidak ada data inventaris.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection