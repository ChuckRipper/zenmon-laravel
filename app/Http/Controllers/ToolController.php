<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MetricType;
use App\Models\AlertThreshold;
use App\Models\MonitoredDirectory;
use App\Models\HostConfiguration;

class ToolController extends Controller
{
    /**
     * Formularz eksportu konfiguracji.
     */
    public function exportForm()
    {
        return view('admin.tools.export');
    }

    /**
     * Generuje i zwraca plik JSON z obecną konfiguracją.
     */
    public function export(Request $request)
    {
        // pobieramy wszystkie elementy konfiguracji
        $payload = [
            'metric_types'          => MetricType::all()->toArray(),
            'alert_thresholds'      => AlertThreshold::all()->toArray(),
            'monitored_directories' => MonitoredDirectory::all()->toArray(),
            'host_configurations'   => HostConfiguration::all()->toArray(),
        ];

        $filename = 'zenmon_config_'.now()->format('Ymd_His').'.json';

        return response()->streamDownload(function() use($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Formularz importu konfiguracji.
     */
    public function importForm()
    {
        return view('admin.tools.import');
    }

    /**
     * Importuje konfigurację z przesłanego pliku JSON.
     */
    public function import(Request $request)
    {
        $request->validate([
            'config_file' => 'required|file|mimetypes:application/json,text/plain',
        ]);

        $json = file_get_contents($request->file('config_file')->getRealPath());
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return back()->with('error', 'Nieprawidłowy format pliku JSON.');
        }

        DB::transaction(function() use($data) {
            // nadpisujemy tylko tabele konfiguracyjne
            if (isset($data['metric_types'])) {
                MetricType::truncate();
                foreach ($data['metric_types'] as $mt) {
                    MetricType::create($mt);
                }
            }

            if (isset($data['alert_thresholds'])) {
                AlertThreshold::truncate();
                foreach ($data['alert_thresholds'] as $thr) {
                    AlertThreshold::create($thr);
                }
            }

            if (isset($data['monitored_directories'])) {
                MonitoredDirectory::truncate();
                foreach ($data['monitored_directories'] as $dir) {
                    MonitoredDirectory::create($dir);
                }
            }

            if (isset($data['host_configurations'])) {
                HostConfiguration::truncate();
                foreach ($data['host_configurations'] as $hc) {
                    HostConfiguration::create($hc);
                }
            }
        });

        return back()->with('success', 'Konfiguracja zaimportowana pomyślnie.');
    }
}
