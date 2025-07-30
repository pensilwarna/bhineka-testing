<!DOCTYPE html>
<html>
<head>
    <title>Laporan Penggunaan Aset</title>
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
    <h1>Laporan Penggunaan Aset</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kode Tiket</th>
                <th>Nama Aset</th>
                <th>Identifikasi</th>
                <th>User (Teknisi)</th>
                <th>Kuantitas Digunakan</th>
                <th>Tujuan Penggunaan</th>
                <th>Waktu Digunakan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $item)
            <tr>
                <td>{{ $item['ID'] }}</td>
                <td>{{ $item['Ticket Code'] }}</td>
                <td>{{ $item['Asset Name'] }}</td>
                <td>{{ $item['Asset Identifier'] }}</td>
                <td>{{ $item['User (Technician)'] }}</td>
                <td>{{ $item['Quantity Used'] }}</td>
                <td>{{ $item['Usage Purpose'] }}</td>
                <td>{{ $item['Used At'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center">Tidak ada data penggunaan aset.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>