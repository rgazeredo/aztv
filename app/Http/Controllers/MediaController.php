<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $query = MediaFile::forTenant($tenantId);

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

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($folder = $request->get('folder')) {
            $query->where('folder', $folder);
        }

        $mediaFiles = $query->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
            ->paginate($request->get('per_page', 24))
            ->withQueryString();

        $mediaFilesData = $mediaFiles->getCollection()->map(function ($mediaFile) {
            return [
                'id' => $mediaFile->id,
                'filename' => $mediaFile->filename,
                'original_name' => $mediaFile->original_name,
                'mime_type' => $mediaFile->mime_type,
                'size' => $mediaFile->size,
                'formatted_size' => $mediaFile->getFormattedSize(),
                'duration' => $mediaFile->duration,
                'display_time' => $mediaFile->display_time,
                'folder' => $mediaFile->folder,
                'tags' => $mediaFile->tags,
                'status' => $mediaFile->status,
                'url' => $mediaFile->getUrl(),
                'thumbnail_url' => $mediaFile->getThumbnailUrl(),
                'is_video' => $mediaFile->isVideo(),
                'is_image' => $mediaFile->isImage(),
                'created_at' => $mediaFile->created_at,
            ];
        });

        $folders = MediaFile::forTenant($tenantId)
            ->whereNotNull('folder')
            ->distinct()
            ->pluck('folder');

        $stats = [
            'total_files' => MediaFile::forTenant($tenantId)->count(),
            'total_size' => MediaFile::forTenant($tenantId)->sum('size'),
            'videos_count' => MediaFile::forTenant($tenantId)->videos()->count(),
            'images_count' => MediaFile::forTenant($tenantId)->images()->count(),
        ];

        return Inertia::render('Media/Index', [
            'media_files' => [
                'data' => $mediaFilesData,
                'current_page' => $mediaFiles->currentPage(),
                'last_page' => $mediaFiles->lastPage(),
                'per_page' => $mediaFiles->perPage(),
                'total' => $mediaFiles->total(),
            ],
            'filters' => $request->only(['search', 'type', 'status', 'folder', 'sort', 'direction']),
            'folders' => $folders,
            'stats' => array_merge($stats, [
                'formatted_total_size' => $this->formatBytes($stats['total_size']),
            ]),
        ]);
    }

    public function show(MediaFile $mediaFile): Response
    {
        $this->authorize('view', $mediaFile);

        $mediaFile->load(['playlists' => function ($query) {
            $query->withPivot('display_time_override');
        }]);

        return Inertia::render('Media/Show', [
            'media_file' => [
                'id' => $mediaFile->id,
                'filename' => $mediaFile->filename,
                'original_name' => $mediaFile->original_name,
                'mime_type' => $mediaFile->mime_type,
                'size' => $mediaFile->size,
                'formatted_size' => $mediaFile->getFormattedSize(),
                'duration' => $mediaFile->duration,
                'display_time' => $mediaFile->display_time,
                'folder' => $mediaFile->folder,
                'tags' => $mediaFile->tags,
                'status' => $mediaFile->status,
                'url' => $mediaFile->getUrl(),
                'thumbnail_url' => $mediaFile->getThumbnailUrl(),
                'is_video' => $mediaFile->isVideo(),
                'is_image' => $mediaFile->isImage(),
                'created_at' => $mediaFile->created_at,
            ],
            'playlists' => $mediaFile->playlists->map(function ($playlist) {
                return [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'display_time_override' => $playlist->pivot->display_time_override,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $folders = MediaFile::forTenant($tenantId)
            ->whereNotNull('folder')
            ->distinct()
            ->pluck('folder');

        return Inertia::render('Media/Create', [
            'folders' => $folders,
            'max_file_size' => $this->getMaxFileSize(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,mp4,avi,mov,wmv|max:' . $this->getMaxFileSizeInKb(),
            'folder' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'display_time' => 'nullable|integer|min:1|max:300',
        ]);

        $uploadedFiles = [];
        $errors = [];

        foreach ($request->file('files') as $file) {
            try {
                if (!MediaFile::isValidMediaFile($file)) {
                    $errors[] = "Arquivo {$file->getClientOriginalName()} não é um tipo de mídia válido.";
                    continue;
                }

                $mediaFile = MediaFile::createFromUpload(
                    $file,
                    auth()->user()->tenant_id,
                    $validated['folder'] ?? null,
                    $validated['tags'] ?? null,
                    $validated['display_time'] ?? null
                );

                $uploadedFiles[] = $mediaFile;

            } catch (\Exception $e) {
                $errors[] = "Erro ao processar {$file->getClientOriginalName()}: " . $e->getMessage();
            }
        }

        if (empty($uploadedFiles) && !empty($errors)) {
            return back()->withErrors(['files' => $errors]);
        }

        $message = count($uploadedFiles) . ' arquivo(s) enviado(s) com sucesso!';
        if (!empty($errors)) {
            $message .= ' Alguns arquivos falharam: ' . implode(', ', $errors);
        }

        return redirect()->route('media.index')
            ->with('success', $message);
    }

    public function edit(MediaFile $mediaFile): Response
    {
        $this->authorize('update', $mediaFile);

        $tenantId = auth()->user()->tenant_id;

        $folders = MediaFile::forTenant($tenantId)
            ->whereNotNull('folder')
            ->distinct()
            ->pluck('folder');

        return Inertia::render('Media/Edit', [
            'media_file' => [
                'id' => $mediaFile->id,
                'filename' => $mediaFile->filename,
                'original_name' => $mediaFile->original_name,
                'display_time' => $mediaFile->display_time,
                'folder' => $mediaFile->folder,
                'tags' => $mediaFile->tags,
                'status' => $mediaFile->status,
            ],
            'folders' => $folders,
        ]);
    }

    public function update(Request $request, MediaFile $mediaFile)
    {
        $this->authorize('update', $mediaFile);

        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'display_time' => 'nullable|integer|min:1|max:300',
            'folder' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive',
        ]);

        $mediaFile->update($validated);

        return redirect()->route('media.show', $mediaFile)
            ->with('success', 'Arquivo de mídia atualizado com sucesso!');
    }

    public function destroy(MediaFile $mediaFile)
    {
        $this->authorize('delete', $mediaFile);

        if ($mediaFile->playlists()->count() > 0) {
            return back()->with('error', 'Não é possível excluir um arquivo que está sendo usado em playlists.');
        }

        $mediaFile->delete();

        return redirect()->route('media.index')
            ->with('success', 'Arquivo de mídia excluído com sucesso!');
    }

    public function download(MediaFile $mediaFile)
    {
        $this->authorize('view', $mediaFile);

        if (!Storage::disk('public')->exists($mediaFile->path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return Storage::disk('public')->download($mediaFile->path, $mediaFile->original_name);
    }

    public function preview(MediaFile $mediaFile)
    {
        $this->authorize('view', $mediaFile);

        return response()->json([
            'url' => $mediaFile->getUrl(),
            'thumbnail_url' => $mediaFile->getThumbnailUrl(),
            'is_video' => $mediaFile->isVideo(),
            'is_image' => $mediaFile->isImage(),
            'duration' => $mediaFile->duration,
            'display_time' => $mediaFile->display_time,
        ]);
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,activate,deactivate,move_folder,add_tags',
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'exists:media_files,id',
            'folder' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $mediaFiles = MediaFile::whereIn('id', $validated['media_ids'])
            ->forTenant($tenantId);

        switch ($validated['action']) {
            case 'delete':
                $filesInPlaylists = $mediaFiles->whereHas('playlists')->count();

                if ($filesInPlaylists > 0) {
                    return back()->with('error', 'Alguns arquivos estão sendo usados em playlists e não podem ser excluídos.');
                }

                $mediaFiles->get()->each(function ($mediaFile) {
                    $mediaFile->delete();
                });

                $message = 'Arquivos excluídos com sucesso!';
                break;

            case 'activate':
                $mediaFiles->update(['status' => 'active']);
                $message = 'Arquivos ativados com sucesso!';
                break;

            case 'deactivate':
                $mediaFiles->update(['status' => 'inactive']);
                $message = 'Arquivos desativados com sucesso!';
                break;

            case 'move_folder':
                if (empty($validated['folder'])) {
                    return back()->with('error', 'Pasta de destino é obrigatória.');
                }

                $mediaFiles->update(['folder' => $validated['folder']]);
                $message = "Arquivos movidos para a pasta '{$validated['folder']}' com sucesso!";
                break;

            case 'add_tags':
                if (empty($validated['tags'])) {
                    return back()->with('error', 'Tags são obrigatórias.');
                }

                $mediaFiles->get()->each(function ($mediaFile) use ($validated) {
                    $existingTags = $mediaFile->tags ? explode(',', $mediaFile->tags) : [];
                    $newTags = explode(',', $validated['tags']);
                    $allTags = array_unique(array_merge($existingTags, $newTags));
                    $mediaFile->update(['tags' => implode(',', $allTags)]);
                });

                $message = 'Tags adicionadas com sucesso!';
                break;
        }

        return back()->with('success', $message);
    }

    public function folders(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $folders = MediaFile::forTenant($tenantId)
            ->whereNotNull('folder')
            ->selectRaw('folder, COUNT(*) as files_count, SUM(size) as total_size')
            ->groupBy('folder')
            ->orderBy('folder')
            ->get()
            ->map(function ($folder) {
                return [
                    'name' => $folder->folder,
                    'files_count' => $folder->files_count,
                    'total_size' => $folder->total_size,
                    'formatted_size' => $this->formatBytes($folder->total_size),
                ];
            });

        return response()->json(['folders' => $folders]);
    }

    public function createFolder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|regex:/^[a-zA-Z0-9\-_\s]+$/',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $exists = MediaFile::forTenant($tenantId)
            ->where('folder', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Uma pasta com este nome já existe.');
        }

        return back()->with('success', "Pasta '{$validated['name']}' criada com sucesso!");
    }

    public function deleteFolder(Request $request)
    {
        $validated = $request->validate([
            'folder' => 'required|string',
            'move_files_to' => 'nullable|string',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $filesInFolder = MediaFile::forTenant($tenantId)
            ->where('folder', $validated['folder']);

        if ($validated['move_files_to']) {
            $filesInFolder->update(['folder' => $validated['move_files_to']]);
            $message = "Pasta '{$validated['folder']}' excluída e arquivos movidos para '{$validated['move_files_to']}'!";
        } else {
            $filesInFolder->update(['folder' => null]);
            $message = "Pasta '{$validated['folder']}' excluída e arquivos movidos para a raiz!";
        }

        return back()->with('success', $message);
    }

    public function analytics(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $period = $request->get('period', '30d');

        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days);

        $analytics = [
            'uploads_by_day' => MediaFile::forTenant($tenantId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(size) as total_size')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'files_by_type' => MediaFile::forTenant($tenantId)
                ->selectRaw('CASE
                    WHEN mime_type LIKE "image%" THEN "Imagens"
                    WHEN mime_type LIKE "video%" THEN "Vídeos"
                    ELSE "Outros"
                END as type, COUNT(*) as count, SUM(size) as total_size')
                ->groupBy('type')
                ->get(),

            'storage_by_folder' => MediaFile::forTenant($tenantId)
                ->whereNotNull('folder')
                ->selectRaw('folder, COUNT(*) as files_count, SUM(size) as total_size')
                ->groupBy('folder')
                ->orderBy('total_size', 'desc')
                ->get(),

            'usage_statistics' => [
                'total_files' => MediaFile::forTenant($tenantId)->count(),
                'total_storage' => MediaFile::forTenant($tenantId)->sum('size'),
                'files_in_playlists' => MediaFile::forTenant($tenantId)->whereHas('playlists')->count(),
                'unused_files' => MediaFile::forTenant($tenantId)->whereDoesntHave('playlists')->count(),
            ],
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

    private function getMaxFileSize(): string
    {
        $maxSize = min(
            $this->parseSize(ini_get('upload_max_filesize')),
            $this->parseSize(ini_get('post_max_size'))
        );

        return $this->formatBytes($maxSize);
    }

    private function getMaxFileSizeInKb(): int
    {
        $maxSize = min(
            $this->parseSize(ini_get('upload_max_filesize')),
            $this->parseSize(ini_get('post_max_size'))
        );

        return $maxSize / 1024; // Convert to KB for validation
    }

    private function parseSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}