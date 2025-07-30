{{-- resources/views/asset-management/inventory/qr-labels.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>QR Asset Labels</title>
    <style>
        @page {
            margin: 10mm;
            size: A4;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 8pt;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10mm;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5mm;
        }
        
        .labels-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        
        .label {
            width: 60mm;
            height: 40mm;
            border: 1px solid #333;
            margin-bottom: 3mm;
            padding: 2mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
        }
        
        .label.small {
            width: 45mm;
            height: 30mm;
        }
        
        .label.large {
            width: 80mm;
            height: 55mm;
        }
        
        .label-header {
            text-align: center;
            font-weight: bold;
            font-size: 6pt;
            margin-bottom: 1mm;
            border-bottom: 1px solid #eee;
            padding-bottom: 1mm;
        }
        
        .label-content {
            display: flex;
            flex: 1;
        }
        
        .qr-section {
            flex: 0 0 auto;
            text-align: center;
            margin-right: 2mm;
        }
        
        .qr-code {
            max-width: 100%;
            height: auto;
        }
        
        .label.small .qr-code {
            width: 20mm;
            height: 20mm;
        }
        
        .label.medium .qr-code {
            width: 25mm;
            height: 25mm;
        }
        
        .label.large .qr-code {
            width: 35mm;
            height: 35mm;
        }
        
        .info-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            font-size: 6pt;
            line-height: 1.2;
        }
        
        .info-row {
            margin-bottom: 0.5mm;
            word-break: break-all;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 15mm;
        }
        
        .qr-text {
            font-size: 5pt;
            margin-top: 1mm;
            text-align: center;
            font-family: monospace;
        }
        
        .footer {
            margin-top: 10mm;
            text-align: center;
            font-size: 6pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 2mm;
        }
        
        @media print {
            .header, .footer {
                position: fixed;
            }
            
            .header {
                top: 0;
            }
            
            .footer {
                bottom: 0;
            }
            
            .labels-container {
                margin-top: 15mm;
                margin-bottom: 15mm;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Asset QR Code Labels</h2>
        <p>Generated: {{ $generated_at->format('d M Y H:i') }} | By: {{ $generated_by }} | Total: {{ count($labels) }} labels</p>
    </div>
    
    <div class="labels-container">
        @foreach($labels as $label)
        <div class="label {{ $label_size }}">
            <div class="label-header">
                {{ $label['asset_code'] }}
            </div>
            
            <div class="label-content">
                <div class="qr-section">
                    <img src="{{ $label['qr_image'] }}" alt="QR Code" class="qr-code">
                    <div class="qr-text">{{ $label['qr_code'] }}</div>
                </div>
                
                @if($include_text)
                <div class="info-section">
                    <div class="info-row">
                        <span class="info-label">Asset:</span>
                        <span>{{ Str::limit($label['asset_name'], 20) }}</span>
                    </div>
                    
                    @if($label['serial_number'])
                    <div class="info-row">
                        <span class="info-label">S/N:</span>
                        <span>{{ $label['serial_number'] }}</span>
                    </div>
                    @endif
                    
                    @if($label['mac_address'])
                    <div class="info-row">
                        <span class="info-label">MAC:</span>
                        <span>{{ $label['mac_address'] }}</span>
                    </div>
                    @endif
                    
                    <div class="info-row" style="margin-top: 1mm; font-size: 5pt; color: #666;">
                        Scan for details
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    
    <div class="footer">
        <p>Asset Management System | {{ config('app.name') }}</p>
        <p style="font-size: 5pt;">Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>