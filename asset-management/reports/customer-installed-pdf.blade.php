<!DOCTYPE html>
<html>
<head>
    <title>Laporan Aset Terpasang Pelanggan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 20px; }
        .footer { text-align: right; font-size: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Laporan Aset Terpasang Pelanggan</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pelanggan</th>
                <th>Lokasi Layanan</th>
                <th>Nama Aset</th>
                <th>Identifikasi</th>
                <th>Qty</th>
                <th>Panjang Awal</th>
                <th>Panjang Audit</th>
                <th>Nilai Unit</th>
                <th>Total Nilai</th>
                <th>Tgl Instalasi</th>
                <th>Status</th>
                <th>Teknisi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $item)
            <tr>
                <td>{{ $item['ID'] }}</td>
                <td>{{ $item['Customer'] }}</td>
                <td>{{ $item['Service Location'] }}</td>
                <td>{{ $item['Asset Name'] }}</td>
                <td>{{ $item['Asset Identifier'] }}</td>
                <td>{{ $item['Quantity Installed'] }}</td>
                <td>{{ $item['Installed Length'] }}</td>
                <td>{{ $item['Current Length (Audit)'] }}</td>
                <td>Rp {{ number_format($item['Unit Value'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item['Total Asset Value'], 0, ',', '.') }}</td>
                <td>{{ $item['Installation Date'] }}</td>
                <td>{{ $item['Status'] }}</td>
                <td>{{ $item['Technician'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="13" class="text-center">Tidak ada data aset terpasang.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>