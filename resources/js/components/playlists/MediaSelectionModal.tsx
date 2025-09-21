import { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/components/ui/dialog";
import { Card, CardContent } from "@/components/ui/card";
import {
    IconSearch,
    IconFilter,
    IconPhoto,
    IconVideo,
    IconFileMusic,
    IconFile,
    IconClock,
    IconPlus,
    IconLoader2,
} from "@tabler/icons-react";
import { toast } from "sonner";

interface MediaFile {
    id: number;
    name: string;
    filename: string;
    mime_type: string;
    file_size: number;
    duration?: number;
    thumbnail_url?: string;
    url: string;
    created_at: string;
}

interface MediaSelectionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    playlistId: number;
    existingMediaIds?: number[];
    onMediaAdded?: () => void;
}

export default function MediaSelectionModal({
    open,
    onOpenChange,
    playlistId,
    existingMediaIds = [],
    onMediaAdded
}: MediaSelectionModalProps) {
    const [mediaFiles, setMediaFiles] = useState<MediaFile[]>([]);
    const [selectedMedia, setSelectedMedia] = useState<Set<number>>(new Set());
    const [loading, setLoading] = useState(false);
    const [adding, setAdding] = useState(false);
    const [search, setSearch] = useState("");
    const [typeFilter, setTypeFilter] = useState("");
    const [currentPage, setCurrentPage] = useState(1);
    const [hasMorePages, setHasMorePages] = useState(false);
    const [totalResults, setTotalResults] = useState(0);

    const getMediaIcon = (mimeType: string) => {
        if (mimeType.startsWith('image/')) return <IconPhoto className="h-4 w-4" />;
        if (mimeType.startsWith('video/')) return <IconVideo className="h-4 w-4" />;
        if (mimeType.startsWith('audio/')) return <IconFileMusic className="h-4 w-4" />;
        return <IconFile className="h-4 w-4" />;
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    const formatDuration = (seconds?: number) => {
        if (!seconds) return 'N/A';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const fetchMediaFiles = async (page: number = 1, append: boolean = false) => {
        setLoading(true);

        try {
            const params = new URLSearchParams({
                page: page.toString(),
                per_page: '12',
                exclude_playlist: playlistId.toString(),
            });

            if (search) params.append('search', search);
            if (typeFilter) params.append('type', typeFilter);

            const response = await fetch(`/api/media-files?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch media files');
            }

            const data = await response.json();

            if (append) {
                setMediaFiles(prev => [...prev, ...data.data]);
            } else {
                setMediaFiles(data.data);
                setSelectedMedia(new Set());
            }

            setCurrentPage(data.current_page);
            setHasMorePages(data.current_page < data.last_page);
            setTotalResults(data.total);
        } catch (error) {
            console.error('Error fetching media files:', error);
            toast.error('Erro ao carregar mídias');
        } finally {
            setLoading(false);
        }
    };

    // Initial load and reload on filter changes
    useEffect(() => {
        if (open) {
            fetchMediaFiles(1, false);
        }
    }, [open, search, typeFilter, playlistId]);

    // Reset when modal opens
    useEffect(() => {
        if (open) {
            setSearch("");
            setTypeFilter("");
            setSelectedMedia(new Set());
            setCurrentPage(1);
        }
    }, [open]);

    const handleSearch = () => {
        fetchMediaFiles(1, false);
    };

    const handleLoadMore = () => {
        if (hasMorePages && !loading) {
            fetchMediaFiles(currentPage + 1, true);
        }
    };

    const handleMediaToggle = (mediaId: number) => {
        const newSelected = new Set(selectedMedia);
        if (newSelected.has(mediaId)) {
            newSelected.delete(mediaId);
        } else {
            newSelected.add(mediaId);
        }
        setSelectedMedia(newSelected);
    };

    const handleSelectAll = () => {
        const availableIds = mediaFiles
            .filter(media => !existingMediaIds.includes(media.id))
            .map(media => media.id);

        if (selectedMedia.size === availableIds.length) {
            setSelectedMedia(new Set());
        } else {
            setSelectedMedia(new Set(availableIds));
        }
    };

    const handleAddToPlaylist = async () => {
        if (selectedMedia.size === 0) return;

        setAdding(true);

        try {
            await router.post(route('playlists.add-media', playlistId), {
                media_ids: Array.from(selectedMedia)
            }, {
                preserveState: true,
                onSuccess: () => {
                    toast.success(`${selectedMedia.size} mídia(s) adicionada(s) à playlist`);
                    setSelectedMedia(new Set());
                    onMediaAdded?.();
                    onOpenChange(false);
                },
                onError: () => {
                    toast.error('Erro ao adicionar mídias à playlist');
                }
            });
        } catch (error) {
            toast.error('Erro ao adicionar mídias');
        } finally {
            setAdding(false);
        }
    };

    const availableMedia = mediaFiles.filter(media => !existingMediaIds.includes(media.id));
    const allAvailableSelected = availableMedia.length > 0 &&
        availableMedia.every(media => selectedMedia.has(media.id));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-6xl max-h-[90vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Adicionar Mídias à Playlist</DialogTitle>
                    <DialogDescription>
                        Selecione as mídias que deseja adicionar à playlist.
                        {totalResults > 0 && ` ${totalResults} mídias disponíveis.`}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-hidden flex flex-col">
                    {/* Filters */}
                    <div className="flex flex-col gap-4 mb-4 sm:flex-row sm:items-center">
                        <div className="flex-1">
                            <div className="relative">
                                <IconSearch className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Buscar mídias..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                                    className="pl-10"
                                />
                            </div>
                        </div>

                        <Select value={typeFilter} onValueChange={setTypeFilter}>
                            <SelectTrigger className="w-full sm:w-48">
                                <SelectValue placeholder="Tipo de mídia" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="image">Imagens</SelectItem>
                                <SelectItem value="video">Vídeos</SelectItem>
                                <SelectItem value="audio">Áudios</SelectItem>
                            </SelectContent>
                        </Select>

                        <div className="flex gap-2">
                            <Button onClick={handleSearch} size="sm">
                                <IconFilter className="mr-2 h-4 w-4" />
                                Filtrar
                            </Button>

                            {(search || typeFilter) && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setSearch("");
                                        setTypeFilter("");
                                    }}
                                >
                                    Limpar
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Selection Controls */}
                    {availableMedia.length > 0 && (
                        <div className="flex items-center justify-between mb-4 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    checked={allAvailableSelected}
                                    onCheckedChange={handleSelectAll}
                                />
                                <span className="text-sm font-medium">
                                    {selectedMedia.size > 0
                                        ? `${selectedMedia.size} selecionada(s)`
                                        : 'Selecionar todas'
                                    }
                                </span>
                            </div>
                            {selectedMedia.size > 0 && (
                                <Badge variant="secondary">
                                    {selectedMedia.size} {selectedMedia.size === 1 ? 'item' : 'itens'}
                                </Badge>
                            )}
                        </div>
                    )}

                    {/* Media Grid */}
                    <div className="flex-1 overflow-y-auto">
                        {loading && mediaFiles.length === 0 ? (
                            <div className="flex items-center justify-center py-12">
                                <IconLoader2 className="h-8 w-8 animate-spin text-gray-400" />
                                <span className="ml-2 text-gray-500">Carregando mídias...</span>
                            </div>
                        ) : mediaFiles.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12">
                                <IconFile className="h-12 w-12 text-gray-400" />
                                <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    Nenhuma mídia encontrada
                                </h3>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Tente ajustar os filtros ou faça upload de novas mídias.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    {mediaFiles.map((media) => {
                                        const isExisting = existingMediaIds.includes(media.id);
                                        const isSelected = selectedMedia.has(media.id);

                                        return (
                                            <Card
                                                key={media.id}
                                                className={`cursor-pointer transition-all ${
                                                    isExisting
                                                        ? 'opacity-50 cursor-not-allowed'
                                                        : isSelected
                                                        ? 'ring-2 ring-blue-500 shadow-lg'
                                                        : 'hover:shadow-md'
                                                }`}
                                                onClick={() => !isExisting && handleMediaToggle(media.id)}
                                            >
                                                <CardContent className="p-3">
                                                    <div className="relative">
                                                        {/* Thumbnail */}
                                                        <div className="aspect-video mb-3 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800">
                                                            {media.thumbnail_url ? (
                                                                <img
                                                                    src={media.thumbnail_url}
                                                                    alt={media.name}
                                                                    className="h-full w-full object-cover"
                                                                />
                                                            ) : (
                                                                <div className="flex h-full items-center justify-center">
                                                                    {getMediaIcon(media.mime_type)}
                                                                </div>
                                                            )}
                                                        </div>

                                                        {/* Checkbox */}
                                                        <div className="absolute top-2 right-2">
                                                            {isExisting ? (
                                                                <Badge variant="secondary" className="text-xs">
                                                                    Na playlist
                                                                </Badge>
                                                            ) : (
                                                                <Checkbox
                                                                    checked={isSelected}
                                                                    onChange={() => handleMediaToggle(media.id)}
                                                                    className="bg-white shadow-sm"
                                                                />
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Info */}
                                                    <div className="space-y-1">
                                                        <h4 className="text-sm font-medium truncate">
                                                            {media.name}
                                                        </h4>
                                                        <div className="flex items-center justify-between text-xs text-gray-500">
                                                            <span>{formatFileSize(media.file_size)}</span>
                                                            {media.duration && (
                                                                <span className="flex items-center">
                                                                    <IconClock className="mr-1 h-3 w-3" />
                                                                    {formatDuration(media.duration)}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <Badge variant="outline" className="text-xs">
                                                            {getMediaIcon(media.mime_type)}
                                                            <span className="ml-1">
                                                                {media.mime_type.split('/')[1].toUpperCase()}
                                                            </span>
                                                        </Badge>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                </div>

                                {/* Load More */}
                                {hasMorePages && (
                                    <div className="mt-6 text-center">
                                        <Button
                                            variant="outline"
                                            onClick={handleLoadMore}
                                            disabled={loading}
                                        >
                                            {loading ? (
                                                <>
                                                    <IconLoader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Carregando...
                                                </>
                                            ) : (
                                                'Carregar mais'
                                            )}
                                        </Button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button
                        onClick={handleAddToPlaylist}
                        disabled={selectedMedia.size === 0 || adding}
                        className="min-w-32"
                    >
                        {adding ? (
                            <>
                                <IconLoader2 className="mr-2 h-4 w-4 animate-spin" />
                                Adicionando...
                            </>
                        ) : (
                            <>
                                <IconPlus className="mr-2 h-4 w-4" />
                                Adicionar ({selectedMedia.size})
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}