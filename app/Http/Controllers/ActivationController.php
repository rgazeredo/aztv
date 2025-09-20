<?php

namespace App\Http\Controllers;

use App\Models\PlayerActivationToken;
use App\Models\Tenant;
use App\Services\TokenGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;

class ActivationController extends Controller
{
    protected TokenGenerationService $tokenService;

    public function __construct(TokenGenerationService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function index(Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        $query = PlayerActivationToken::forTenant($tenant->id)
            ->with(['tenant', 'player'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active();
                    break;
                case 'used':
                    $query->used();
                    break;
                case 'expired':
                    $query->expired();
                    break;
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('token', 'LIKE', "%{$search}%")
                  ->orWhere('activation_code', 'LIKE', "%{$search}%");
            });
        }

        $tokens = $query->paginate(15)->withQueryString();

        $statistics = $this->tokenService->getTokenStatistics($tenant);

        return Inertia::render('Activation/Index', [
            'tokens' => $tokens,
            'statistics' => $statistics,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        $validator = Validator::make($request->all(), [
            'expires_hours' => 'integer|min:1|max:168', // Max 1 week
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $expiresHours = $request->input('expires_hours', 24);
            $playerData = [
                'expires_at' => now()->addHours($expiresHours),
            ];

            $token = $this->tokenService->generateActivationToken($tenant, $playerData);

            Log::info('Activation token created via admin panel', [
                'token_id' => $token->id,
                'tenant_id' => $tenant->id,
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'token' => [
                    'id' => $token->id,
                    'token' => $token->token,
                    'activation_code' => $token->activation_code,
                    'activation_url' => $token->activation_url,
                    'qr_code_url' => $this->tokenService->getQRCodeUrl($token),
                    'expires_at' => $token->expires_at,
                    'short_url' => $token->short_url,
                ],
                'message' => 'Token de ativação criado com sucesso',
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to create activation token', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Falha ao criar token de ativação',
            ], 500);
        }
    }

    public function show(string $token)
    {
        $validationResult = $this->tokenService->validateActivationData($token);

        if (!$validationResult['valid']) {
            return view('activation.error', [
                'error' => $validationResult['error'],
                'token' => $validationResult['token'],
            ]);
        }

        $activationToken = $validationResult['token'];
        $qrCodeUrl = $this->tokenService->getQRCodeUrl($activationToken);

        return view('activation.show', [
            'token' => $activationToken,
            'tenant' => $activationToken->tenant,
            'qr_code_url' => $qrCodeUrl,
            'activation_code' => $activationToken->activation_code,
            'expires_at' => $activationToken->expires_at,
        ]);
    }

    public function download(string $token)
    {
        $activationToken = PlayerActivationToken::where('token', $token)->first();

        if (!$activationToken || !$activationToken->isValid()) {
            abort(404, 'Token não encontrado ou inválido');
        }

        if (!$activationToken->qr_code_path) {
            abort(404, 'QR Code não encontrado');
        }

        $disk = config('filesystems.default', 'public');

        if (!Storage::disk($disk)->exists($activationToken->qr_code_path)) {
            abort(404, 'Arquivo do QR Code não encontrado');
        }

        $fileName = "qr_code_activation_{$activationToken->activation_code}.png";

        return Storage::disk($disk)->download($activationToken->qr_code_path, $fileName);
    }

    public function revoke(string $token, Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        $activationToken = PlayerActivationToken::where('token', $token)
            ->forTenant($tenant->id)
            ->first();

        if (!$activationToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token não encontrado',
            ], 404);
        }

        if ($activationToken->is_used) {
            return response()->json([
                'success' => false,
                'message' => 'Token já foi utilizado e não pode ser revogado',
            ], 422);
        }

        try {
            $success = $this->tokenService->revokeToken($activationToken);

            if ($success) {
                Log::info('Activation token revoked via admin panel', [
                    'token_id' => $activationToken->id,
                    'tenant_id' => $tenant->id,
                    'revoked_by' => $request->user()->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Token revogado com sucesso',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Falha ao revogar token',
            ], 500);

        } catch (Exception $e) {
            Log::error('Failed to revoke activation token', [
                'token_id' => $activationToken->id,
                'error' => $e->getMessage(),
                'revoked_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao revogar token',
            ], 500);
        }
    }

    public function regenerateQR(string $token, Request $request)
    {
        $tenant = $this->getCurrentTenant($request);

        $activationToken = PlayerActivationToken::where('token', $token)
            ->forTenant($tenant->id)
            ->first();

        if (!$activationToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token não encontrado',
            ], 404);
        }

        if (!$activationToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido ou expirado',
            ], 422);
        }

        try {
            $success = $this->tokenService->regenerateQRCode($activationToken);

            if ($success) {
                $qrCodeUrl = $this->tokenService->getQRCodeUrl($activationToken->fresh());

                return response()->json([
                    'success' => true,
                    'qr_code_url' => $qrCodeUrl,
                    'message' => 'QR Code regenerado com sucesso',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Falha ao regenerar QR Code',
            ], 500);

        } catch (Exception $e) {
            Log::error('Failed to regenerate QR code', [
                'token_id' => $activationToken->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao regenerar QR Code',
            ], 500);
        }
    }

    private function getCurrentTenant(Request $request): Tenant
    {
        return $request->user()->tenant ?? Tenant::first();
    }
}
