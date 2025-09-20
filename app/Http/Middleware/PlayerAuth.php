<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Player;
use Symfony\Component\HttpFoundation\Response;

class PlayerAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $playerId = $request->header('X-Player-ID');
        $apiToken = $request->header('X-API-Token');

        if (!$playerId || !$apiToken) {
            return response()->json([
                'success' => false,
                'message' => 'Headers de autenticação obrigatórios: X-Player-ID e X-API-Token',
                'error_code' => 'MISSING_AUTH_HEADERS',
            ], 401);
        }

        $cachedToken = cache()->get("player_token_{$playerId}");

        if (!$cachedToken || $cachedToken !== $apiToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token de autenticação inválido ou expirado',
                'error_code' => 'INVALID_TOKEN',
            ], 401);
        }

        $player = Player::find($playerId);

        if (!$player) {
            return response()->json([
                'success' => false,
                'message' => 'Player não encontrado',
                'error_code' => 'PLAYER_NOT_FOUND',
            ], 404);
        }

        $player->updateLastSeen();

        $request->merge(['authenticated_player' => $player]);

        return $next($request);
    }
}