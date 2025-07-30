<!DOCTYPE html>
<html>
<head>
    <title>Laporan Hutang Aset Teknisi</title>
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
    <h1>Laporan Hutang Aset Teknisi</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Teknisi</th>
                <th>Nama Aset</th>
                <th>Identifikasi</th>
                <th>Kategori</th>
                <th>Gudang Asal</th>
                <th>Tgl Checkout</th>
                <th>Qty Awal</th>
                <th>Qty Sisa</th>
                <th>Nilai Awal</th>
                <th>Nilai Sisa</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $item)
            <tr>
                <td>{{ $item['ID'] }}</td>
                <td>{{ $item['Technician'] }}</td>
                <td>{{ $item['Asset Name'] }}</td>
                <td>{{ $item['Asset Identifier'] }}</td>
                <td>{{ $item['Category'] }}</td>
                <td>{{ $item['Warehouse Checkout'] }}</td>
                <td>{{ $item['Checkout Date'] }}</td>
                <td>{{ $item['Quantity Taken'] }}</td>
                <td>{{ $item['Current Debt Quantity'] }}</td>
                <td>Rp {{ number_format($item['Total Debt Value'], 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item['Current Debt Value'], 0, ',', '.') }}</td>
                <td>{{ $item['Status'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="text-center">Tidak ada data hutang.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>