<?php

namespace App\Http\Controllers;

use App\Services\UdaraService;
use Illuminate\Http\Request;

class UdaraController extends Controller
{
    public function __construct(private UdaraService $udara)
    {
    }

    // GET /api/udara?lat=0.5071&lon=101.4478
    public function udara(Request $request)
    {
        $lat = (float) $request->query('lat', 0.5071);   // default Pekanbaru
        $lon = (float) $request->query('lon', 101.4478);

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return response()->json(['error' => 'Koordinat tidak valid'], 422);
        }

        $data = $this->udara->ambil($lat, $lon);

        if (!$data) {
            return response()->json(['error' => 'Data udara belum tersedia'], 503);
        }

        return response()->json($data);
    }
}
