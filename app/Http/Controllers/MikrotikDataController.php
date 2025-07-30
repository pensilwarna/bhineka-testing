<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\MikrotikDatum;
use RouterOS\Client;
use RouterOS\Query;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;


class MikrotikDataController extends Controller
{
    public function index()
    {
        $mikrotiks = Mikrotik::select('id', 'name')->where('is_enabled', true)->get();
        
        // Ambil data awal jika tabel kosong
        if (MikrotikDatum::count() == 0) {
            $this->refresh(new \Illuminate\Http\Request(['mikrotik_id' => $mikrotiks->first()->id ?? null]));
        }

        return view('mikrotik-data.index', compact('mikrotiks'));
    }

    public function fetch(Request $request)
    {
        $query = MikrotikDatum::with('mikrotik');

        if ($request->mikrotik_id) {
            $query->where('mikrotik_id', $request->mikrotik_id);

            $dataExists = MikrotikDatum::where('mikrotik_id', $request->mikrotik_id)->exists();
            if (!$dataExists) {
                $this->refresh($request);
            }
        }

        if ($request->type) {
            $query->where('data_type', $request->type);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('mikrotik_name', function ($data) {
                return $data->mikrotik ? $data->mikrotik->name : '-';
            })
            ->addColumn('actions', function ($data) {
                // Replace collected_at with formatted_collected_at in the data
                $dataArray = $data->toArray();
                $dataArray['collected_at'] = $data->formatted_collected_at;
                return '
                    <button class="btn btn-sm btn-icon view-details" data-data=\'' . json_encode($dataArray) . '\' title="Lihat Detail"><i class="ti ti-eye"></i></button>
                    <button class="btn btn-sm btn-icon refresh-record" data-id="' . $data->id . '" data-mikrotik-id="' . $data->mikrotik_id . '" data-type="' . $data->data_type . '" title="Refresh"><i class="ti ti-refresh"></i></button>
                ';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function refresh(Request $request)
    {
        $mikrotik = Mikrotik::where('is_enabled', true)->find($request->mikrotik_id);

        if (!$mikrotik) {
            return response()->json(['error' => 'Tidak ada konfigurasi Mikrotik yang aktif.'], 400);
        }

        try {
            $client = new Client([
                'host' => $mikrotik->ip_address,
                'user' => $mikrotik->username,
                'pass' => $mikrotik->password ?? '',
                'port' => (int) $mikrotik->port,
            ]);

            // Ambil data dari Mikrotik
            $pppProfiles = $client->query(new Query('/ppp/profile/print'))->read();
            $pppoeServers = $client->query(new Query('/interface/pppoe-server/server/print'))->read();
            $pppoeUsers = $client->query(new Query('/ppp/secret/print'))->read();

            // Simpan atau perbarui data ke tabel mikrotik_data
            $this->storeData($mikrotik->id, 'ppp_profiles', $pppProfiles);
            $this->storeData($mikrotik->id, 'pppoe_servers', $pppoeServers);
            $this->storeData($mikrotik->id, 'pppoe_users', $pppoeUsers);

            return response()->json(['success' => 'Data berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal terhubung ke Mikrotik: ' . $e->getMessage()], 500);
        }
    }

    private function storeData($mikrotikId, $dataType, $items)
    {
        // Hapus data lama untuk mikrotik_id dan data_type tertentu
        MikrotikDatum::where('mikrotik_id', $mikrotikId)->where('data_type', $dataType)->delete();

        foreach ($items as $item) {
            $data = [
                'mikrotik_id' => $mikrotikId,
                'data_type' => $dataType,
                'collected_at' => now(),
            ];

            // Petakan field sesuai kebutuhan
            if ($dataType === 'ppp_profiles') {
                $data['interface_name'] = $item['name'] ?? null;
                $data['ip_address'] = $item['local-address'] ?? null;
                $data['source_ip'] = $item['remote-address'] ?? null;
                $data['comment'] = $item['comment'] ?? null;
            } elseif ($dataType === 'pppoe_servers') {
                $data['interface_name'] = $item['interface'] ?? null;
                $data['interface_type'] = $item['service-name'] ?? null;
                $data['interface_status'] = $item['disabled'] === 'true' ? 'disabled' : 'enabled';
            } elseif ($dataType === 'pppoe_users') {
                $data['interface_name'] = $item['name'] ?? null;
                $data['interface_type'] = $item['service'] ?? null;
                $data['profile'] = $item['profile'] ?? null;
                $data['ip_address'] = $item['local-address'] ?? null;
                $data['source_ip'] = $item['remote-address'] ?? null;
            }

            MikrotikDatum::create($data);
        }
    }
}