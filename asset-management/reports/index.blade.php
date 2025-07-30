{{-- File: resources/views/asset-management/reports/index.blade.php --}}
@extends('layouts.app')

@section('title')
Laporan Aset
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Asset Management /</span> Laporan</h4>

    <div class="row">
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title">Laporan Inventaris</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Daftar lengkap semua aset di gudang, termasuk unit terlacak dan non-terlacak, beserta status dan kuantitasnya.</p>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="{{ route('asset-management.reports.inventory') }}?format=html" class="btn btn-sm btn-outline-primary">Lihat HTML</a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.inventory') }}?format=xlsx">XLSX</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.inventory') }}?format=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.inventory') }}?format=pdf">PDF</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title">Laporan Hutang Aset Teknisi</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Detail hutang aset oleh setiap teknisi, status, dan nilainya. Bisa difilter per teknisi atau status.</p>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="{{ route('asset-management.reports.debt') }}?format=html" class="btn btn-sm btn-outline-primary">Lihat HTML</a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.debt') }}?format=xlsx">XLSX</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.debt') }}?format=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.debt') }}?format=pdf">PDF</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title">Laporan Aset Terpasang Pelanggan</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Daftar aset yang terpasang di lokasi pelanggan, dengan detail instalasi dan nilainya. Penting untuk aset kabel.</p>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="{{ route('asset-management.reports.customer-installed') }}?format=html" class="btn btn-sm btn-outline-primary">Lihat HTML</a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.customer-installed') }}?format=xlsx">XLSX</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.customer-installed') }}?format=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.customer-installed') }}?format=pdf">PDF</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title">Laporan Penggunaan Aset</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Catatan kapan dan bagaimana aset digunakan dalam setiap tiket, termasuk kuantitas dan tujuan penggunaan.</p>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="{{ route('asset-management.reports.usage') }}?format=html" class="btn btn-sm btn-outline-primary">Lihat HTML</a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.usage') }}?format=xlsx">XLSX</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.usage') }}?format=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.usage') }}?format=pdf">PDF</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title">Laporan Pemeliharaan Aset</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Ringkasan aset yang diperbaiki, diretur ke supplier, atau dihapus (write-off).</p>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="{{ route('asset-management.reports.maintenance') }}?format=html" class="btn btn-sm btn-outline-primary">Lihat HTML</a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.maintenance') }}?format=xlsx">XLSX</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.maintenance') }}?format=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="{{ route('asset-management.reports.maintenance') }}?format=pdf">PDF</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection