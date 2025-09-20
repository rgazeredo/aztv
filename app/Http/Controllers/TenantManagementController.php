<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantManagementController extends Controller
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

    public function index(Request $request): Response
    {
        $query = Tenant::with(['users', 'players', 'mediaFiles'])
            ->withCount(['users', 'players', 'mediaFiles']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->inactive();
            }
        }

        if ($subscription = $request->get('subscription')) {
            $query->whereHas('subscription', function ($q) use ($subscription) {
                $q->where('name', $subscription);
            });
        }

        $tenants = $query->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        $tenantsData = $tenants->getCollection()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'is_active' => $tenant->is_active,
                'created_at' => $tenant->created_at,
                'subscription_status' => $tenant->getSubscriptionStatus(),
                'users_count' => $tenant->users_count,
                'players_count' => $tenant->players_count,
                'media_files_count' => $tenant->media_files_count,
                'storage_used' => $tenant->mediaFiles->sum('size'),
                'formatted_storage' => $this->formatBytes($tenant->mediaFiles->sum('size')),
            ];
        });

        return Inertia::render('Admin/TenantManagement/Index', [
            'tenants' => [
                'data' => $tenantsData,
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ],
            'filters' => $request->only(['search', 'status', 'subscription', 'sort', 'direction']),
            'subscription_plans' => $this->getSubscriptionPlans(),
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load([
            'users' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'players' => function ($query) {
                $query->orderBy('last_seen', 'desc');
            },
            'mediaFiles' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'playlists' => function ($query) {
                $query->withCount('items')->orderBy('created_at', 'desc');
            }
        ]);

        $stats = [
            'total_users' => $tenant->users->count(),
            'total_players' => $tenant->players->count(),
            'online_players' => $tenant->players->where('last_seen', '>=', now()->subMinutes(5))->count(),
            'total_media_files' => $tenant->mediaFiles->count(),
            'total_playlists' => $tenant->playlists->count(),
            'storage_used' => $tenant->mediaFiles->sum('size'),
            'formatted_storage' => $this->formatBytes($tenant->mediaFiles->sum('size')),
        ];

        return Inertia::render('Admin/TenantManagement/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'is_active' => $tenant->is_active,
                'created_at' => $tenant->created_at,
                'subscription_status' => $tenant->getSubscriptionStatus(),
                'settings' => $tenant->settings,
            ],
            'stats' => $stats,
            'users' => $tenant->users,
            'players' => $tenant->players->map(function ($player) {
                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'alias' => $player->alias,
                    'status' => $player->getStatus(),
                    'last_seen' => $player->last_seen,
                    'ip_address' => $player->ip_address,
                    'app_version' => $player->app_version,
                ];
            }),
            'media_files' => $tenant->mediaFiles,
            'playlists' => $tenant->playlists,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TenantManagement/Create', [
            'subscription_plans' => $this->getSubscriptionPlans(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:20',
            'subscription_plan' => 'nullable|string',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'settings' => 'nullable|array',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'is_active' => true,
            'settings' => $validated['settings'] ?? [],
        ]);

        $adminUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => 'admin',
            'is_active' => true,
        ]);

        if (!empty($validated['subscription_plan'])) {
            $tenant->newSubscription('default', $validated['subscription_plan'])->create();
        }

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', 'Cliente criado com sucesso!');
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('Admin/TenantManagement/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'is_active' => $tenant->is_active,
                'settings' => $tenant->settings,
                'subscription_status' => $tenant->getSubscriptionStatus(),
            ],
            'subscription_plans' => $this->getSubscriptionPlans(),
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        if ($validated['name'] !== $tenant->name) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (Tenant::where('slug', $slug)->where('id', '!=', $tenant->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $validated['slug'] = $slug;
        }

        $tenant->update($validated);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Tenant $tenant)
    {
        if ($tenant->users()->count() > 0 || $tenant->players()->count() > 0) {
            return back()->with('error', 'Não é possível excluir um cliente que possui usuários ou players ativos.');
        }

        $tenant->delete();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Cliente excluído com sucesso!');
    }

    public function toggleStatus(Tenant $tenant)
    {
        $tenant->update(['is_active' => !$tenant->is_active]);

        $status = $tenant->is_active ? 'ativado' : 'desativado';

        return back()->with('success', "Cliente {$status} com sucesso!");
    }

    public function impersonate(Tenant $tenant)
    {
        $adminUser = $tenant->users()->where('role', 'admin')->first();

        if (!$adminUser) {
            return back()->with('error', 'Este cliente não possui um usuário administrador.');
        }

        session(['impersonating_tenant_id' => $tenant->id]);
        session(['original_admin_id' => auth()->id()]);

        auth()->login($adminUser);

        return redirect()->route('dashboard')
            ->with('success', "Agora você está gerenciando a conta de {$tenant->name}");
    }

    public function stopImpersonating()
    {
        $originalAdminId = session('original_admin_id');

        if (!$originalAdminId) {
            return back()->with('error', 'Sessão de impersonação inválida.');
        }

        $originalAdmin = User::find($originalAdminId);

        if (!$originalAdmin || !$originalAdmin->isAdmin()) {
            return back()->with('error', 'Usuário administrador original não encontrado.');
        }

        session()->forget(['impersonating_tenant_id', 'original_admin_id']);
        auth()->login($originalAdmin);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Você retornou à sua conta de administrador.');
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'tenant_ids' => 'required|array|min:1',
            'tenant_ids.*' => 'exists:tenants,id',
        ]);

        $tenants = Tenant::whereIn('id', $validated['tenant_ids']);

        switch ($validated['action']) {
            case 'activate':
                $tenants->update(['is_active' => true]);
                $message = 'Clientes ativados com sucesso!';
                break;

            case 'deactivate':
                $tenants->update(['is_active' => false]);
                $message = 'Clientes desativados com sucesso!';
                break;

            case 'delete':
                $tenantsWithData = $tenants->whereHas('users')
                    ->orWhereHas('players')
                    ->count();

                if ($tenantsWithData > 0) {
                    return back()->with('error', 'Alguns clientes possuem dados e não podem ser excluídos.');
                }

                $tenants->delete();
                $message = 'Clientes excluídos com sucesso!';
                break;
        }

        return back()->with('success', $message);
    }

    public function analytics(Request $request, Tenant $tenant)
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
            'player_activity' => $tenant->players()
                ->where('last_seen', '>=', $startDate)
                ->selectRaw('DATE(last_seen) as date, COUNT(DISTINCT id) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'media_uploads' => $tenant->mediaFiles()
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(size) as total_size')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'storage_growth' => $tenant->mediaFiles()
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, SUM(size) as daily_size')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];

        return response()->json($analytics);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function getSubscriptionPlans(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'price' => 29.90,
                'features' => ['Até 5 players', '10GB storage', 'Suporte básico'],
            ],
            'professional' => [
                'name' => 'Professional',
                'price' => 59.90,
                'features' => ['Até 20 players', '50GB storage', 'Suporte prioritário'],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price' => 199.90,
                'features' => ['Players ilimitados', '500GB storage', 'Suporte dedicado'],
            ],
        ];
    }
}