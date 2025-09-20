<?php

namespace App\Http\Controllers;

use App\Models\ApkVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ApkManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isAdmin()) {
                abort(403, 'Access denied. Admin privileges required.');
            }
            return $next($request);
        });
    }

    public function index(Request $request): InertiaResponse
    {
        $query = ApkVersion::query();

        if ($search = $request->get('search')) {
            $query->where('version', 'like', "%{$search}%")
                  ->orWhere('changelog', 'like', "%{$search}%");
        }

        if ($status = $request->get('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $apkVersions = $query->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        $apkVersionsData = $apkVersions->getCollection()->map(function ($apkVersion) {
            return [
                'id' => $apkVersion->id,
                'version' => $apkVersion->version,
                'filename' => $apkVersion->filename,
                'is_active' => $apkVersion->is_active,
                'download_count' => $apkVersion->download_count,
                'file_size' => $apkVersion->getFormattedFileSize(),
                'created_at' => $apkVersion->created_at,
                'changelog' => $apkVersion->changelog,
                'download_url' => $apkVersion->getDownloadUrl(),
                'qr_code_url' => $apkVersion->generateQrCode(),
            ];
        });

        return Inertia::render('Admin/ApkManagement/Index', [
            'apk_versions' => [
                'data' => $apkVersionsData,
                'current_page' => $apkVersions->currentPage(),
                'last_page' => $apkVersions->lastPage(),
                'per_page' => $apkVersions->perPage(),
                'total' => $apkVersions->total(),
            ],
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
            'stats' => [
                'total_versions' => ApkVersion::count(),
                'active_version' => ApkVersion::getActiveVersion()?->version,
                'total_downloads' => ApkVersion::sum('download_count'),
                'latest_version' => ApkVersion::latest()->first()?->version,
            ],
        ]);
    }

    public function show(ApkVersion $apkVersion): InertiaResponse
    {
        return Inertia::render('Admin/ApkManagement/Show', [
            'apk_version' => [
                'id' => $apkVersion->id,
                'version' => $apkVersion->version,
                'filename' => $apkVersion->filename,
                'is_active' => $apkVersion->is_active,
                'download_count' => $apkVersion->download_count,
                'file_size' => $apkVersion->getFormattedFileSize(),
                'created_at' => $apkVersion->created_at,
                'changelog' => $apkVersion->changelog,
                'download_url' => $apkVersion->getDownloadUrl(),
                'qr_code_url' => $apkVersion->generateQrCode(),
                'short_url' => $apkVersion->generateShortUrl(),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/ApkManagement/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'version' => 'required|string|unique:apk_versions,version',
            'apk_file' => 'required|file|mimes:apk|max:102400', // 100MB max
            'changelog' => 'nullable|string|max:1000',
            'activate_immediately' => 'boolean',
        ]);

        $apkFile = $request->file('apk_file');

        if (!ApkVersion::isValidApk($apkFile)) {
            return back()->withErrors(['apk_file' => 'O arquivo deve ser um APK válido.']);
        }

        if (ApkVersion::isVersionExists($validated['version'])) {
            return back()->withErrors(['version' => 'Esta versão já existe.']);
        }

        $apkVersion = ApkVersion::createFromUpload(
            $apkFile,
            $validated['version'],
            $validated['changelog'] ?? null
        );

        if ($validated['activate_immediately'] ?? false) {
            $apkVersion->activate();
        }

        return redirect()->route('admin.apk.show', $apkVersion)
            ->with('success', 'APK enviado com sucesso!');
    }

    public function edit(ApkVersion $apkVersion): InertiaResponse
    {
        return Inertia::render('Admin/ApkManagement/Edit', [
            'apk_version' => [
                'id' => $apkVersion->id,
                'version' => $apkVersion->version,
                'is_active' => $apkVersion->is_active,
                'changelog' => $apkVersion->changelog,
            ],
        ]);
    }

    public function update(Request $request, ApkVersion $apkVersion)
    {
        $validated = $request->validate([
            'version' => 'required|string|unique:apk_versions,version,' . $apkVersion->id,
            'changelog' => 'nullable|string|max:1000',
        ]);

        $apkVersion->update($validated);

        return redirect()->route('admin.apk.show', $apkVersion)
            ->with('success', 'APK atualizado com sucesso!');
    }

    public function destroy(ApkVersion $apkVersion)
    {
        if ($apkVersion->is_active) {
            return back()->with('error', 'Não é possível excluir a versão ativa do APK.');
        }

        $apkVersion->delete();

        return redirect()->route('admin.apk.index')
            ->with('success', 'APK excluído com sucesso!');
    }

    public function activate(ApkVersion $apkVersion)
    {
        $apkVersion->activate();

        return back()->with('success', "Versão {$apkVersion->version} ativada com sucesso!");
    }

    public function deactivate(ApkVersion $apkVersion)
    {
        $apkVersion->deactivate();

        return back()->with('success', "Versão {$apkVersion->version} desativada com sucesso!");
    }

    public function download(ApkVersion $apkVersion)
    {
        if (!Storage::disk('public')->exists($apkVersion->path)) {
            abort(404, 'Arquivo APK não encontrado.');
        }

        $apkVersion->incrementDownloadCount();

        return Storage::disk('public')->download($apkVersion->path, $apkVersion->filename);
    }

    public function downloadLatest()
    {
        $latestVersion = ApkVersion::getLatestVersion();

        if (!$latestVersion) {
            abort(404, 'Nenhuma versão de APK disponível.');
        }

        return $this->download($latestVersion);
    }

    public function downloadActive()
    {
        $activeVersion = ApkVersion::getActiveVersion();

        if (!$activeVersion) {
            abort(404, 'Nenhuma versão ativa de APK disponível.');
        }

        return $this->download($activeVersion);
    }

    public function qrCode(ApkVersion $apkVersion)
    {
        $qrCodeUrl = $apkVersion->generateQrCode();

        return response()->json([
            'qr_code_url' => $qrCodeUrl,
            'download_url' => $apkVersion->getDownloadUrl(),
        ]);
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete',
            'apk_ids' => 'required|array|min:1',
            'apk_ids.*' => 'exists:apk_versions,id',
        ]);

        $apkVersions = ApkVersion::whereIn('id', $validated['apk_ids']);

        switch ($validated['action']) {
            case 'delete':
                $activeVersions = $apkVersions->where('is_active', true)->count();

                if ($activeVersions > 0) {
                    return back()->with('error', 'Não é possível excluir versões ativas do APK.');
                }

                $apkVersions->get()->each(function ($apkVersion) {
                    $apkVersion->delete();
                });

                $message = 'APKs excluídos com sucesso!';
                break;
        }

        return back()->with('success', $message);
    }

    public function checkUpdate(Request $request)
    {
        $currentVersion = $request->get('current_version');
        $activeVersion = ApkVersion::getActiveVersion();

        if (!$activeVersion) {
            return response()->json([
                'update_available' => false,
                'message' => 'Nenhuma versão disponível',
            ]);
        }

        $updateAvailable = version_compare($activeVersion->version, $currentVersion, '>');

        return response()->json([
            'update_available' => $updateAvailable,
            'latest_version' => $activeVersion->version,
            'download_url' => $updateAvailable ? $activeVersion->getDownloadUrl() : null,
            'changelog' => $updateAvailable ? $activeVersion->changelog : null,
            'file_size' => $updateAvailable ? $activeVersion->getFormattedFileSize() : null,
        ]);
    }

    public function analytics(Request $request)
    {
        $period = $request->get('period', '30d');

        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days);

        $analytics = [
            'downloads_by_version' => ApkVersion::selectRaw('version, download_count')
                ->orderBy('download_count', 'desc')
                ->get(),

            'daily_downloads' => ApkVersion::selectRaw('DATE(updated_at) as date, SUM(download_count) as downloads')
                ->where('updated_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'version_adoption' => ApkVersion::selectRaw('version, download_count, is_active')
                ->orderBy('created_at', 'desc')
                ->get(),
        ];

        return response()->json($analytics);
    }

    public function forceUpdate(Request $request)
    {
        $validated = $request->validate([
            'version' => 'required|exists:apk_versions,version',
            'force_update' => 'required|boolean',
        ]);

        $apkVersion = ApkVersion::where('version', $validated['version'])->first();

        $settings = $apkVersion->settings ?? [];
        $settings['force_update'] = $validated['force_update'];

        $apkVersion->update(['settings' => $settings]);

        $action = $validated['force_update'] ? 'ativada' : 'desativada';

        return back()->with('success', "Atualização forçada {$action} para a versão {$apkVersion->version}!");
    }
}