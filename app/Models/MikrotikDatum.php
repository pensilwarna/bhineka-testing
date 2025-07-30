<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikDatum extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = [
        'mikrotik_id',
        'data_type',
        'collected_at',
        'interface_name',
        'interface_type',
        'interface_status',
        'interface_rx_bytes',
        'interface_tx_bytes',
        'interface_rx_packets',
        'interface_tx_packets',
        'cpu_load',
        'memory_usage',
        'free_memory',
        'uptime',
        'firmware_version',
        'active_connections',
        'ip_address',
        'mac_address',
        'signal_strength',
        'ssid',
        'firewall_rule_chain',
        'firewall_rule_action',
        'firewall_rule_bytes',
        'dhcp_lease_ip',
        'dhcp_lease_mac',
        'dhcp_lease_hostname',
        'source_ip',
        'comment',
        'profile',
    ];

    protected $casts = [
        'mikrotik_id' => 'integer',
        'data_type' => 'string',
        'collected_at' => 'datetime',
        'interface_rx_bytes' => 'integer',
        'interface_tx_bytes' => 'integer',
        'interface_rx_packets' => 'integer',
        'interface_tx_packets' => 'integer',
        'cpu_load' => 'float',
        'memory_usage' => 'float',
        'free_memory' => 'integer',
        'active_connections' => 'integer',
    ];

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function getFormattedCollectedAtAttribute()
    {
        return $this->collected_at ? $this->collected_at->format('d M Y H:i:s') : null;
    }
    
    protected $appends = ['formatted_collected_at'];


}