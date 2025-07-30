<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pemeliharaan Aset</title>
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
    <h1>Laporan Pemeliharaan Aset</h1>
    <table>
        <thead>
            <tr>
                <th>Tipe</th>
                <th>ID</th>
                <th>Nama Aset</th>
                <th>Identifikasi</th>
                <th>Tanggal</th>
                <th>Detail</th>
                <th>Biaya</th>
                <th>Status</th>
                <th>Pihak Terkait</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $item)
            <tr>
                <td>{{ $item['Type'] }}</td>
                <td>{{ $item['ID'] }}</td>
                <td>{{ $item['Asset Name'] }}</td>
                <td>{{ $item['Asset Identifier'] }}</td>
                <td>{{ $item['Date'] }}</td>
                <td>{{ $item['Details'] }}</td>
                <td>{{ $item['Cost'] }}</td>
                <td>{{ $item['Status'] }}</td>
                <td>{{ $item['User'] }}</td>
                <td>{{ $item['Reason/Notes'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center">Tidak ada data pemeliharaan aset.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>