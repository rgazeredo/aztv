<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\MediaFile;
use App\Models\Player;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $query = Playlist::with(['mediaFiles', 'players'])
            ->withCount(['items', 'players'])
            ->forTenant($tenantId);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($hasItems = $request->get('has_items')) {
            if ($hasItems === 'with_items') {
                $query->withItems();
            } elseif ($hasItems === 'empty') {
                $query->empty();
            }
        }

        $playlists = $query->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        $playlistsData = $playlists->getCollection()->map(function ($playlist) {
            return [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'is_default' => $playlist->is_default,
                'loop_enabled' => $playlist->loop_enabled,
                'items_count' => $playlist->items_count,
                'players_count' => $playlist->players_count,
                'total_duration' => $playlist->getTotalDuration(),
                'formatted_duration' => $playlist->getFormattedDuration(),
                'created_at' => $playlist->created_at,
            ];
        });

        return Inertia::render('Playlist/Index', [
            'playlists' => [
                'data' => $playlistsData,
                'current_page' => $playlists->currentPage(),
                'last_page' => $playlists->lastPage(),
                'per_page' => $playlists->perPage(),
                'total' => $playlists->total(),
            ],
            'filters' => $request->only(['search', 'has_items', 'sort', 'direction']),
            'stats' => [
                'total_playlists' => Playlist::forTenant($tenantId)->count(),
                'with_items' => Playlist::forTenant($tenantId)->withItems()->count(),
                'empty_playlists' => Playlist::forTenant($tenantId)->empty()->count(),
                'default_playlist' => Playlist::forTenant($tenantId)->default()->first()?->name,
            ],
        ]);
    }

    public function show(Playlist $playlist): Response
    {
        $this->authorize('view', $playlist);

        $playlist->load([
            'items.mediaFile',
            'players' => function ($query) {
                $query->orderBy('pivot_priority');
            }
        ]);

        return Inertia::render('Playlist/Show', [
            'playlist' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'is_default' => $playlist->is_default,
                'loop_enabled' => $playlist->loop_enabled,
                'settings' => $playlist->settings,
                'total_duration' => $playlist->getTotalDuration(),
                'formatted_duration' => $playlist->getFormattedDuration(),
                'items_count' => $playlist->getItemsCount(),
                'created_at' => $playlist->created_at,
            ],
            'items' => $playlist->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'order' => $item->order,
                    'display_time_override' => $item->display_time_override,
                    'display_time' => $item->getDisplayTime(),
                    'media_file' => [
                        'id' => $item->mediaFile->id,
                        'filename' => $item->mediaFile->filename,
                        'original_name' => $item->mediaFile->original_name,
                        'mime_type' => $item->mediaFile->mime_type,
                        'size' => $item->mediaFile->size,
                        'formatted_size' => $item->mediaFile->getFormattedSize(),
                        'duration' => $item->mediaFile->duration,
                        'url' => $item->mediaFile->getUrl(),
                        'thumbnail_url' => $item->mediaFile->getThumbnailUrl(),
                        'is_video' => $item->mediaFile->isVideo(),
                        'is_image' => $item->mediaFile->isImage(),
                    ],
                ];
            }),
            'players' => $playlist->players->map(function ($player) {
                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'alias' => $player->alias,
                    'priority' => $player->pivot->priority,
                    'start_date' => $player->pivot->start_date,
                    'end_date' => $player->pivot->end_date,
                    'schedule_config' => $player->pivot->schedule_config,
                    'status' => $player->getStatus(),
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Playlist/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
            'loop_enabled' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $playlist = Playlist::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'is_default' => $validated['is_default'] ?? false,
            'loop_enabled' => $validated['loop_enabled'] ?? true,
            'settings' => $validated['settings'] ?? [],
        ]);

        if ($validated['is_default'] ?? false) {
            $playlist->markAsDefault();
        }

        return redirect()->route('playlists.show', $playlist)
            ->with('success', 'Playlist criada com sucesso!');
    }

    public function edit(Playlist $playlist): Response
    {
        $this->authorize('update', $playlist);

        return Inertia::render('Playlist/Edit', [
            'playlist' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'is_default' => $playlist->is_default,
                'loop_enabled' => $playlist->loop_enabled,
                'settings' => $playlist->settings,
            ],
        ]);
    }

    public function update(Request $request, Playlist $playlist)
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
            'loop_enabled' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $playlist->update($validated);

        if ($validated['is_default'] ?? false) {
            $playlist->markAsDefault();
        }

        return redirect()->route('playlists.show', $playlist)
            ->with('success', 'Playlist atualizada com sucesso!');
    }

    public function destroy(Playlist $playlist)
    {
        $this->authorize('delete', $playlist);

        if ($playlist->is_default) {
            return back()->with('error', 'Não é possível excluir a playlist padrão.');
        }

        if ($playlist->players()->count() > 0) {
            return back()->with('error', 'Não é possível excluir uma playlist que está sendo usada por players.');
        }

        $playlist->delete();

        return redirect()->route('playlists.index')
            ->with('success', 'Playlist excluída com sucesso!');
    }

    public function addMedia(Request $request, Playlist $playlist)
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'media_files' => 'required|array|min:1',
            'media_files.*' => 'exists:media_files,id',
            'display_time_override' => 'nullable|integer|min:1|max:300',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $addedCount = 0;

        foreach ($validated['media_files'] as $mediaFileId) {
            $mediaFile = MediaFile::where('id', $mediaFileId)
                ->forTenant($tenantId)
                ->first();

            if ($mediaFile) {
                $playlist->addMediaFile($mediaFile, $validated['display_time_override'] ?? null);
                $addedCount++;
            }
        }

        return back()->with('success', "{$addedCount} arquivo(s) adicionado(s) à playlist!");
    }

    public function removeMedia(Playlist $playlist, MediaFile $mediaFile)
    {
        $this->authorize('update', $playlist);

        if ($playlist->removeMediaFile($mediaFile)) {
            return back()->with('success', 'Arquivo removido da playlist!');
        }

        return back()->with('error', 'Arquivo não encontrado na playlist.');
    }

    public function reorderItems(Request $request, Playlist $playlist)
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'exists:playlist_items,id',
        ]);

        $playlist->reorderItems($validated['item_ids']);

        return back()->with('success', 'Ordem dos itens atualizada!');
    }

    public function updateItemDisplayTime(Request $request, Playlist $playlist, $itemId)
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'display_time_override' => 'nullable|integer|min:1|max:300',
        ]);

        $item = $playlist->items()->find($itemId);

        if (!$item) {
            return back()->with('error', 'Item não encontrado na playlist.');
        }

        $item->update([
            'display_time_override' => $validated['display_time_override'],
        ]);

        return back()->with('success', 'Tempo de exibição atualizado!');
    }

    public function duplicate(Request $request, Playlist $playlist)
    {
        $this->authorize('view', $playlist);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $duplicatedPlaylist = $playlist->duplicate($validated['name']);

        return redirect()->route('playlists.show', $duplicatedPlaylist)
            ->with('success', 'Playlist duplicada com sucesso!');
    }

    public function assignToPlayers(Request $request, Playlist $playlist)
    {
        $this->authorize('update', $playlist);

        $validated = $request->validate([
            'players' => 'required|array|min:1',
            'players.*.player_id' => 'exists:players,id',
            'players.*.priority' => 'integer|min:1',
            'players.*.start_date' => 'nullable|date',
            'players.*.end_date' => 'nullable|date|after:start_date',
            'players.*.schedule_config' => 'nullable|array',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $assignedCount = 0;

        foreach ($validated['players'] as $playerData) {
            $player = Player::where('id', $playerData['player_id'])
                ->forTenant($tenantId)
                ->first();

            if ($player) {
                $playlist->players()->syncWithoutDetaching([
                    $player->id => [
                        'priority' => $playerData['priority'] ?? 1,
                        'start_date' => $playerData['start_date'] ?? null,
                        'end_date' => $playerData['end_date'] ?? null,
                        'schedule_config' => $playerData['schedule_config'] ?? null,
                    ]
                ]);
                $assignedCount++;
            }
        }

        return back()->with('success', "Playlist atribuída a {$assignedCount} player(s)!");
    }

    public function unassignFromPlayer(Playlist $playlist, Player $player)
    {
        $this->authorize('update', $playlist);

        $playlist->unassignFromPlayer($player);

        return back()->with('success', 'Playlist removida do player!');
    }

    public function markAsDefault(Playlist $playlist)
    {
        $this->authorize('update', $playlist);

        $playlist->markAsDefault();

        return back()->with('success', 'Playlist marcada como padrão!');
    }

    public function availableMedia(Request $request, Playlist $playlist)
    {
        $this->authorize('view', $playlist);

        $tenantId = auth()->user()->tenant_id;

        $query = MediaFile::forTenant($tenantId)
            ->where('status', 'active')
            ->whereNotIn('id', $playlist->mediaFiles->pluck('id'));

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('original_name', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            if ($type === 'images') {
                $query->images();
            } elseif ($type === 'videos') {
                $query->videos();
            }
        }

        $mediaFiles = $query->orderBy('created_at', 'desc')
            ->paginate(12)
            ->withQueryString();

        return response()->json([
            'data' => $mediaFiles->items(),
            'pagination' => [
                'current_page' => $mediaFiles->currentPage(),
                'last_page' => $mediaFiles->lastPage(),
                'total' => $mediaFiles->total(),
            ],
        ]);
    }

    public function availablePlayers(Request $request, Playlist $playlist)
    {
        $this->authorize('view', $playlist);

        $tenantId = auth()->user()->tenant_id;

        $assignedPlayerIds = $playlist->players->pluck('id');

        $players = Player::forTenant($tenantId)
            ->whereNotIn('id', $assignedPlayerIds)
            ->orderBy('name')
            ->get();

        return response()->json(['players' => $players]);
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,duplicate',
            'playlist_ids' => 'required|array|min:1',
            'playlist_ids.*' => 'exists:playlists,id',
            'duplicate_names' => 'nullable|array',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $playlists = Playlist::whereIn('id', $validated['playlist_ids'])
            ->forTenant($tenantId);

        switch ($validated['action']) {
            case 'delete':
                $defaultPlaylists = $playlists->where('is_default', true)->count();
                $playlistsWithPlayers = $playlists->whereHas('players')->count();

                if ($defaultPlaylists > 0) {
                    return back()->with('error', 'Não é possível excluir playlists padrão.');
                }

                if ($playlistsWithPlayers > 0) {
                    return back()->with('error', 'Algumas playlists estão sendo usadas por players e não podem ser excluídas.');
                }

                $playlists->get()->each(function ($playlist) {
                    $playlist->delete();
                });

                $message = 'Playlists excluídas com sucesso!';
                break;

            case 'duplicate':
                $duplicateNames = $validated['duplicate_names'] ?? [];
                $duplicatedCount = 0;

                $playlists->get()->each(function ($playlist, $index) use ($duplicateNames, &$duplicatedCount) {
                    $newName = $duplicateNames[$index] ?? ($playlist->name . ' - Cópia');
                    $playlist->duplicate($newName);
                    $duplicatedCount++;
                });

                $message = "{$duplicatedCount} playlist(s) duplicada(s) com sucesso!";
                break;
        }

        return back()->with('success', $message);
    }

    public function analytics(Request $request, Playlist $playlist)
    {
        $this->authorize('view', $playlist);

        $period = $request->get('period', '30d');

        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days);

        $analytics = [
            'usage_by_players' => $playlist->players()
                ->withCount('logs')
                ->get()
                ->map(function ($player) {
                    return [
                        'player_name' => $player->name,
                        'logs_count' => $player->logs_count,
                        'status' => $player->getStatus(),
                    ];
                }),

            'media_distribution' => $playlist->mediaFiles()
                ->selectRaw('mime_type, COUNT(*) as count')
                ->groupBy('mime_type')
                ->get(),

            'total_duration' => $playlist->getTotalDuration(),
            'items_count' => $playlist->getItemsCount(),
            'players_count' => $playlist->players()->count(),
        ];

        return response()->json($analytics);
    }
}