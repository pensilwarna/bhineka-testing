{{-- resources/views/asset-management/receipts/qr-labels.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Asset QR Code Labels</title>
    <style>
        @page {
            margin: 8mm;
            size: A4;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            font-size: 7pt;
            line-height: 1.2;
        }
        
        .header {
            text-align: center;
            margin-bottom: 8mm;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4mm;
        }
        
        .labels-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 2mm;
        }
        
        /* Label Sizes */
        .label {
            border: 1px solid #333;
            margin-bottom: 2mm;
            padding: 1.5mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            background: white;
        }
        
        .label.small {
            width: 45mm;
            height: 30mm;
            font-size: 5pt;
        }
        
        .label.medium {
            width: 60mm;
            height: 40mm;
            font-size: 6pt;
        }
        
        .label.large {
            width: 80mm;
            height: 55mm;
            font-size: 7pt;
        }
        
        /* Label Header */
        .label-header {
            text-align: center;
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 1mm;
            border-bottom: 1px solid #eee;
            padding-bottom: 1mm;
            color: #333;
        }
        
        .label-header .asset-code {
            color: #0066cc;
        }
        
        /* Label Content Layout */
        .label-content {
            display: flex;
            flex: 1;
            gap: 1mm;
        }
        
        .qr-section {
            flex: 0 0 auto;
            text-align: center;
        }
        
        .qr-code {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
        }
        
        .label.small .qr-code {
            width: 18mm;
            height: 18mm;
        }
        
        .label.medium .qr-code {
            width: 24mm;
            height: 24mm;
        }
        
        .label.large .qr-code {
            width: 32mm;
            height: 32mm;
        }
        
        .qr-text {
            font-size: 0.8em;
            margin-top: 0.5mm;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }
        
        /* Info Section */
        .info-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding-left: 1mm;
        }
        
        .info-main {
            flex: 1;
        }
        
        .info-row {
            margin-bottom: 0.5mm;
            display: flex;
            align-items: flex-start;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 12mm;
            flex-shrink: 0;
            color: #666;
        }
        
        .info-value {
            flex: 1;
            word-break: break-word;
        }
        
        /* Asset Type Specific Styling */
        .asset-type-badge {
            display: inline-block;
            padding: 0.5mm 1mm;
            font-size: 0.8em;
            font-weight: bold;
            border-radius: 1mm;
            margin-bottom: 1mm;
        }
        
        .asset-type-cable {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #1976d2;
        }
        
        .asset-type-device {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #7b1fa2;
        }
        
        .asset-type-consumable {
            background: #e8f5e8;
            color: #388e3c;
            border: 1px solid #388e3c;
        }
        
        /* Cable Specific Info */
        .cable-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 1mm;
            padding: 1mm;
            margin-top: 1mm;
        }
        
        .cable-length {
            font-weight: bold;
            color: #1976d2;
            font-size: 1.1em;
        }
        
        .length-progress {
            width: 100%;
            height: 2mm;
            background: #e0e0e0;
            border-radius: 1mm;
            margin: 0.5mm 0;
            overflow: hidden;
        }
        
        .length-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #ff9800, #f44336);
            transition: width 0.3s ease;
        }
        
        /* Device Info */
        .device-info {
            background: #fff3e0;
            border: 1px solid #ff9800;
            border-radius: 1mm;
            padding: 1mm;
            margin-top: 1mm;
        }
        
        /* Footer Info */
        .label-footer {
            border-top: 1px solid #eee;
            padding-top: 0.5mm;
            margin-top: 1mm;
            font-size: 0.8em;
            color: #666;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .warehouse-info {
            font-weight: bold;
            color: #333;
        }
        
        /* Status Indicators */
        .status-indicator {
            width: 3mm;
            height: 3mm;
            border-radius: 50%;
            display: inline-block;
            margin-right: 1mm;
        }
        
        .status-available { background: #4caf50; }
        .status-in-use { background: #ff9800; }
        .status-damaged { background: #f44336; }
        
        /* Print Optimizations */
        @media print {
            .header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: white;
                z-index: 1000;
            }
            
            .labels-container {
                margin-top: 20mm;
            }
            
            .label {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        /* Responsive Grid */
        @media screen and (max-width: 210mm) {
            .labels-container {
                justify-content: center;
            }
        }
        
        /* Group Headers */
        .group-header {
            width: 100%;
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 2mm;
            margin: 2mm 0;
            font-weight: bold;
            text-align: center;
            page-break-after: avoid;
        }
        
        /* Footer */
        .page-footer {
            margin-top: 8mm;
            text-align: center;
            font-size: 5pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 2mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Asset QR Code Labels</h2>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2mm;">
            <div>
                @if(isset($receipt))
                <strong>Receipt:</strong> {{ $receipt->receipt_number }}<br>
                <strong>Supplier:</strong> {{ $receipt->supplier->name }}
                @else
                <strong>Inventory Labels</strong>
                @endif
            </div>
            <div style="text-align: center;">
                <strong>Generated:</strong> {{ $generated_at->format('d M Y H:i') }}<br>
                <strong>By:</strong> {{ $generated_by }}
            </div>
            <div style="text-align: right;">
                <strong>Total Labels:</strong> {{ is_array($labels) ? count($labels) : (isset($labels[0]['labels']) ? array_sum(array_column($labels, 'labels')) : count($labels)) }}<br>
                <strong>Label Size:</strong> {{ ucfirst($label_size) }}
            </div>
        </div>
    </div>
    
    <div class="labels-container">
        @if($group_by_asset && isset($labels[0]['asset_group']))
            {{-- Grouped by asset type --}}
            @foreach($labels as $group)
                <div class="group-header">
                    {{ $group['asset_group'] }}
                    @if(isset($group['asset_sub_type']))
                        <span class="asset-type-badge asset-type-{{ str_contains($group['asset_sub_type'], 'cable') ? 'cable' : (str_contains($group['asset_sub_type'], 'router') || str_contains($group['asset_sub_type'], 'switch') ? 'device' : 'consumable') }}">
                            {{ $group['asset_sub_type'] }}
                        </span>
                    @endif
                </div>
                
                @foreach($group['labels'] as $label)
                    @include('asset-management.receipts.partials.single-qr-label', ['label' => $label])
                @endforeach
            @endforeach
        @else
            {{-- Regular mixed labels --}}
            @foreach($labels as $label)
                @include('asset-management.receipts.partials.single-qr-label', ['label' => $label])
            @endforeach
        @endif
    </div>
    
    <div class="page-footer">
        <p>{{ config('app.name') }} - Asset Management System</p>
        <p style="font-size: 4pt;">Generated on {{ now()->format('Y-m-d H:i:s') }} | Page printed at {{ now()->toISOString() }}</p>
    </div>
</body>
</html>

{{-- Single Label Partial Template --}}
@push('partial-templates')
<!-- resources/views/asset-management/receipts/partials/single-qr-label.blade.php -->
<div class="label {{ $label_size }}">
    <div class="label-header">
        <span class="asset-code">{{ $label['asset_code'] }}</span>
        @if(isset($label['asset_sub_type']))
            <div class="asset-type-badge asset-type-{{ str_contains($label['asset_sub_type'], 'cable') ? 'cable' : (str_contains($label['asset_sub_type'], 'router') || str_contains($label['asset_sub_type'], 'switch') ? 'device' : 'consumable') }}">
                {{ $label['asset_sub_type'] }}
            </div>
        @endif
    </div>
    
    <div class="label-content">
        <div class="qr-section">
            <img src="{{ $label['qr_image'] }}" alt="QR Code" class="qr-code">
            <div class="qr-text">{{ $label['qr_code'] }}</div>
        </div>
        
        @if($include_text)
        <div class="info-section">
            <div class="info-main">
                <div class="info-row">
                    <span class="info-label">Asset:</span>
                    <span class="info-value">{{ Str::limit($label['asset_name'], $label_size == 'small' ? 15 : ($label_size == 'medium' ? 20 : 30)) }}</span>
                </div>
                
                @if($label['serial_number'])
                <div class="info-row">
                    <span class="info-label">S/N:</span>
                    <span class="info-value">{{ $label['serial_number'] }}</span>
                </div>
                @endif
                
                @if($label['mac_address'])
                <div class="info-row">
                    <span class="info-label">MAC:</span>
                    <span class="info-value">{{ $label['mac_address'] }}</span>
                </div>
                @endif
                
                {{-- Cable-specific information --}}
                @if(isset($label['initial_length']) && $label['initial_length'])
                <div class="cable-info">
                    <div class="cable-length">
                        {{ number_format($label['current_length'] ?? $label['initial_length'], 1) }}m
                        @if(isset($label['current_length']) && $label['current_length'] != $label['initial_length'])
                            / {{ number_format($label['initial_length'], 1) }}m
                        @endif
                    </div>
                    
                    @if(isset($label['current_length']) && $label['current_length'] != $label['initial_length'])
                        @php
                            $percentage = ($label['current_length'] / $label['initial_length']) * 100;
                        @endphp
                        <div class="length-progress">
                            <div class="length-progress-bar" style="width: {{ $percentage }}%"></div>
                        </div>
                        <div style="font-size: 0.8em; color: #666;">
                            {{ number_format($percentage, 1) }}% remaining
                        </div>
                    @endif
                    
                    @if(isset($label['unit_of_measure']))
                        <div style="font-size: 0.8em; color: #666;">
                            Unit: {{ $label['unit_of_measure'] }}
                        </div>
                    @endif
                </div>
                @endif
                
                {{-- Device-specific information --}}
                @if($label['serial_number'] || $label['mac_address'])
                <div class="device-info">
                    <div style="font-size: 0.8em; color: #666;">
                        Network Device
                    </div>
                </div>
                @endif
            </div>
            
            <div class="label-footer">
                @if(isset($label['warehouse']))
                <div class="receipt-info">
                    <span class="warehouse-info">{{ $label['warehouse'] }}</span>
                    @if(isset($label['received_date']))
                        <span>{{ $label['received_date'] }}</span>
                    @endif
                </div>
                @endif
                
                <div style="margin-top: 0.5mm; font-size: 0.7em;">
                    <span class="status-indicator status-available"></span>
                    Scan for details
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endpush