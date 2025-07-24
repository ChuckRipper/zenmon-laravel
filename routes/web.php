<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

// kontrolery
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MetricTypeController;
use App\Http\Controllers\AlertThresholdController;
use App\Http\Controllers\ToolController;

// modele
use App\Models\Host;
use App\Models\Alert;
use App\Models\MonitoredDirectory;
use App\Models\User;
// dodane modele potrzebne dla konfiguracji hosta
use App\Models\MetricType;
use App\Models\AlertThreshold;

Route::middleware('guest')->group(function () {
    Route::get('login', fn() => view('auth.login'))->name('login');
    Route::post('login', function (Request $r) {
        $data = $r->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);
        if (Auth::attempt($data, $r->boolean('remember'))) {
            $r->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }
        return back()
            ->withErrors(['login' => 'Nieprawidłowy login lub hasło.'])
            ->onlyInput('login');
    })->name('login.post');
});

Route::middleware('auth:web')->group(function () {
    // Wylogowanie
    Route::post('logout', function (Request $r) {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('login');
    })->name('logout');

    // Dashboard
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('dashboard', function () {
        $totalHosts      = Host::count();
        $activeHosts     = Host::where('is_active', true)->count();
        $hostsWithAlerts = Host::whereHas('alerts')->count();
        $hostsOnline     = Host::whereExists(fn($q) =>
            $q->selectRaw('1')
              ->from('connection_statuses as cs')
              ->whereColumn('cs.host_id','hosts.host_id')
              ->orderByDesc('cs.last_check_date')
              ->limit(1)
              ->where('cs.status','Online')
        )->count();

        $totalUsers  = User::count();
        $activeUsers = User::where('is_active', true)->count();

        $recentAlerts = Alert::with(['host','metricType'])
            ->where('status','Active')
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'totalHosts','activeHosts','hostsWithAlerts','hostsOnline',
            'totalUsers','activeUsers','recentAlerts'
        ));
    })->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Admin panel: /admin/*
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')
         ->name('admin.')
         ->group(function () {
             Route::get('/', [AdminController::class,'index'])->name('index');
             Route::resource('users', UserController::class)->except(['show']);
             Route::resource('metric-types', MetricTypeController::class);
             Route::resource('thresholds', AlertThresholdController::class)
                  ->only(['index','edit','update','destroy']);
             Route::get('tools/export',  [ToolController::class,'exportForm'])
                  ->name('tools.export.form');
             Route::post('tools/export', [ToolController::class,'export'])
                  ->name('tools.export');
             Route::get('tools/import',  [ToolController::class,'importForm'])
                  ->name('tools.import.form');
             Route::post('tools/import', [ToolController::class,'import'])
                  ->name('tools.import');
         });

    /*
    |--------------------------------------------------------------------------
    | Hosts: lista, wyszukiwanie, CRUD
    |--------------------------------------------------------------------------
    */
    Route::get('hosts', function (Request $r) {
        $search = $r->query('q');
        $q = Host::query();
        if ($search) {
            $q->where(fn($qb) =>
                $qb->where('host_name','like',"%{$search}%")
                   ->orWhere('ip_address','like',"%{$search}%")
            );
        }
        $hosts = $q->paginate(15)->withQueryString();
        return view('hosts.index', compact('hosts','search'));
    })->name('hosts.index');

    Route::get('hosts/create', fn() => view('hosts.create'))->name('hosts.create');
    Route::post('hosts', function (Request $r) {
        $data = $r->validate([
            'host_name'        => 'required|string',
            'ip_address'       => 'required|ip',
            'description'      => 'nullable|string',
            'operating_system' => 'nullable|string',
            'agent_version'    => 'nullable|string',
            'is_active'        => 'boolean',
        ]);
        Host::create($data);
        return redirect()->route('hosts.index')->with('success','Host dodany.');
    })->name('hosts.store');

    /*
    |--------------------------------------------------------------------------
    | Szczegóły hosta + AJAX dane do wykresu
    |--------------------------------------------------------------------------
    */
    Route::get('hosts/{host}', function (Host $host) {
        $host->load(['alerts.metricType','connectionStatuses']);
        $metrics = $host->metrics()
                        ->with('metricType')
                        ->where('timestamp','>=', now()->subHour())
                        ->orderBy('timestamp')
                        ->get();
        if ($metrics->isEmpty()) {
            $metrics = $host->metrics()
                            ->with('metricType')
                            ->orderBy('timestamp')
                            ->get();
        }
        $recentMetrics = $metrics->sortByDesc('timestamp')->take(10);
        $labels        = $metrics->pluck('timestamp')
                                 ->map(fn($t)=>$t->format('H:i'))
                                 ->unique()->values()->all();
        $chartData     = $metrics
            ->groupBy(fn($m)=>$m->metricType->metric_name)
            ->mapWithKeys(fn($g,$name)=>[
                $name => ['values'=>$g->pluck('value')->all()],
            ])->toArray();

        return view('hosts.show', compact('host','recentMetrics','labels','chartData'));
    })->name('hosts.show');

    // endpoint zwracający JSON { labels:[], datasets:[] }
    Route::get('hosts/{host}/metrics-data', function (Host $host) {
        $metrics = $host->metrics()
                        ->with('metricType')
                        ->where('timestamp','>=', now()->subHour())
                        ->orderBy('timestamp')
                        ->get();

        if ($metrics->isEmpty()) {
            $metrics = $host->metrics()->with('metricType')->orderBy('timestamp')->get();
        }

        $grouped = $metrics->groupBy(fn($m)=>$m->metricType->metric_name);

        $labels = $metrics
            ->pluck('timestamp')
            ->map(fn($t)=>$t->format('H:i'))
            ->unique()->values()->all();

        $datasets = $grouped->map(fn($g,$name)=>[
            'label' => $name . ' (' . $g->first()->metricType->unit . ')',
            'data'  => $g->pluck('value')->all(),
            'borderColor' => match($name) {
                'CPU Usage'    => 'rgb(255, 99, 132)',
                'Memory Usage' => 'rgb(54, 162, 235)',
                'Disk Usage'   => 'rgb(255, 205, 86)',
                default        => 'rgb(75, 192, 192)',
            },
            'fill' => false,
        ])->values()->all();

        return response()->json(compact('labels','datasets'));
    })->name('hosts.metrics-data');

    /*
    |--------------------------------------------------------------------------
    | Konfiguracja hosta
    |--------------------------------------------------------------------------
    */
    Route::get('hosts/{host}/config', function (Host $host) {
        $host->load(['hostConfiguration','monitoredDirectories']);

        $metricTypes     = MetricType::all();
        $alertThresholds = AlertThreshold::where('host_id', $host->host_id)->get();

        return view('hosts.config', compact('host','metricTypes','alertThresholds'));
    })->name('hosts.config');

    Route::post('hosts/{host}/config', function (Request $r, Host $host) {
        $data = $r->validate([
            'data_collection_interval' => 'required|integer|min:10|max:3600',
            'max_log_entries'          => 'required|integer|min:100|max:100000',
            'email_notifications'      => 'boolean',
            'slack_notifications'      => 'boolean',
        ]);
        $data['updated_by_user_id'] = auth()->id();

        $host->hostConfiguration()->updateOrCreate(
            ['host_id' => $host->host_id],
            $data
        );
        return back()->with('success','Konfiguracja zapisana.');
    })->name('hosts.config.save');

    Route::post('directories', function (Request $r) {
        $data = $r->validate([
            'host_id'        => 'required|exists:hosts,host_id',
            'directory_path' => 'required|string',
        ]);
        MonitoredDirectory::create($data);
        return back()->with('success','Katalog dodany.');
    })->name('directories.store');

    Route::delete('directories/{directory}', function (MonitoredDirectory $dir) {
        $dir->delete();
        return back()->with('success','Katalog usunięty.');
    })->name('directories.destroy');

    /*
    |--------------------------------------------------------------------------
    | Alerty
    |--------------------------------------------------------------------------
    */
    Route::get('alerts', fn() => view('alerts.index', [
        'alerts'=> Alert::with(['host','metricType'])
                         ->latest('created_at')
                         ->take(100)
                         ->get()
    ]))->name('alerts.index');

    Route::post('alerts/{alert}/acknowledge', function (Alert $alert) {
        $alert->update([
            'status'                  => 'Acknowledged',
            'acknowledged_date'       => now(),
            'acknowledged_by_user_id' => auth()->id(),
        ]);
        return back()->with('success','Alert potwierdzony.');
    })->name('alerts.acknowledge');

    Route::post('alerts/{alert}/close', function (Request $r, Alert $alert) {
        $data = $r->validate(['close_comment'=>'nullable|string']);
        $alert->update([
            'status'              => 'Closed',
            'closed_date'         => now(),
            'closed_by_user_id'   => auth()->id(),
            'close_comment'       => $data['close_comment'] ?? null,
        ]);
        return back()->with('success','Alert zamknięty.');
    })->name('alerts.close');

    /*
    |--------------------------------------------------------------------------
    | Profil i zmiana hasła
    |--------------------------------------------------------------------------
    */
    Route::get('profile', fn() => view('profile.index'))->name('profile.index');
    Route::post('profile/password', function (Request $r) {
        $d = $r->validate([
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:8',
        ]);
        if (! \Hash::check($d['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password'=>'Błędne hasło']);
        }
        auth()->user()->update(['password'=>\Hash::make($d['password'])]);
        return back()->with('success','Hasło zmienione.');
    })->name('profile.updatePassword');

    /*
    |--------------------------------------------------------------------------
    | Historyczne metryki
    |--------------------------------------------------------------------------
    */
    Route::get('hosts/{host}/metrics', function (Request $r, Host $host) {
        $to   = $r->query('to')   ? Carbon::parse($r->query('to'))   : now();
        $from = $r->query('from') ? Carbon::parse($r->query('from')) : now()->subHours(24);

        $metrics = $host->metrics()
                        ->with('metricType')
                        ->whereBetween('timestamp',[$from,$to])
                        ->orderBy('timestamp')
                        ->get();
        if ($metrics->isEmpty()) {
            $metrics = $host->metrics()
                            ->with('metricType')
                            ->orderBy('timestamp')
                            ->get();
        }

        $alerts = $host->alerts()
                       ->with('metricType')
                       ->whereBetween('created_at',[$from,$to])
                       ->get();

        $grouped   = $metrics->groupBy(fn($m)=>$m->metricType->metric_name);
        $chartData = $grouped->mapWithKeys(fn($g,$n)=>[
            $n=>[
                'series'=>      $g->map(fn($m)=>['time'=>$m->timestamp->format('H:i'),'value'=>(float)$m->value])->values()->all(),
                'alertPoints'=> $alerts->where('metricType.metric_name',$n)
                                       ->map(fn($a)=>['time'=>$a->created_at->format('H:i'),'value'=>(float)$a->current_value])
                                       ->values()->all(),
            ]
        ])->toArray();
        $units  = $grouped->mapWithKeys(fn($g,$n)=>[$n=>$g->first()->metricType->unit])->toArray();
        $labels = $metrics->pluck('timestamp')->map(fn($t)=>$t->format('H:i'))->unique()->sort()->values()->all();

        return view('hosts.metrics', compact('host','from','to','labels','chartData','units'));
    })->name('hosts.metrics');

    // Fallback
    Route::fallback(fn() => redirect()->route('dashboard'));
    
    // API Documentation redirect
    Route::get('/api-docs', function() {
        return redirect('/api/documentation');
    });
});