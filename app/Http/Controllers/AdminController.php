<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Host;
use App\Models\Alert;
use App\Models\Metric;
use App\Models\MetricType;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        // 1) Kluczowe statystyki
        $totalUsers   = User::count();
        $activeUsers  = User::where('is_active', true)->count();
        $totalHosts   = Host::count();
        $activeAlerts = Alert::where('status', 'Active')->count();
        $totalMetrics = Metric::count();

        // 2) Paginowane kolekcje do tabel
        $users       = User::paginate(10);
        $hosts       = Host::paginate(50);
        $metricTypes = MetricType::paginate(60);
        $recentAlerts = Alert::with(['host','metricType'])
                             ->latest('created_at')
                             ->take(10)
                             ->get();

        return view('admin.index', compact(
            'totalUsers','activeUsers','totalHosts','activeAlerts','totalMetrics',
            'users','hosts','metricTypes','recentAlerts'
        ));
    }
}
