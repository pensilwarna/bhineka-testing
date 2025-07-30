<!DOCTYPE html>
<html>
<head>
    <title>Laporan Inventaris Aset</title>
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
    <h1>Laporan Inventaris Aset</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Aset</th>
                <th>Kode</th>
                <th>Kategori</th>
                <th>Gudang</th>
                <th>Status</th>
                <th>Total Qty</th>
                <th>Avail Qty</th>
                <th>Unit</th>
                <th>Harga</th>
                <th>Requires QR</th>
                <th>QR/SN/MAC</th>
                <th>Init L.</th>
                <th>Curr L.</th>
                <th>Fisik Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $item)
            <tr>
                <td>{{ $item['ID'] }}</td>
                <td>{{ $item['Asset Name'] }}</td>
                <td>{{ $item['Asset Code'] }}</td>
                <td>{{ $item['Category'] }}</td>
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
                <td colspan="15" class="text-center">Tidak ada data inventaris.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>