import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    IconPlaylistAdd,
    IconClock,
    IconPhoto,
    IconVideo,
    IconFileMusic,
    IconFile,
    IconListDetails,
} from "@tabler/icons-react";

interface MediaFile {
    id: number;
    name: string;
    mime_type: string;
    duration?: number;
}

interface PlaylistItem {
    id: number;
    display_time: number;
    media_file: MediaFile;
}

interface Playlist {
    id: number;
    name: string;
    description?: string;
    status: 'active' | 'inactive';
    loop_enabled: boolean;
    items: PlaylistItem[];
}

interface PlaylistPreviewProps {
    playlist: Playlist | null;
    compact?: boolean;
}

export default function PlaylistPreview({ playlist, compact = false }: PlaylistPreviewProps) {
    if (!playlist) {
        return (
            <Card className="h-full">
                <CardContent className="flex h-full items-center justify-center p-8">
                    <div className="text-center text-gray-500 dark:text-gray-400">
                        <IconPlaylistAdd className="mx-auto h-12 w-12 mb-3" />
                        <p className="text-sm">Selecione uma playlist para ver os detalhes</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const getMediaIcon = (mimeType: string) => {
        if (mimeType.startsWith('image/')) return <IconPhoto className="h-4 w-4" />;
        if (mimeType.startsWith('video/')) return <IconVideo className="h-4 w-4" />;
        if (mimeType.startsWith('audio/')) return <IconFileMusic className="h-4 w-4" />;
        return <IconFile className="h-4 w-4" />;
    };

    const calculateTotalDuration = () => {
        return playlist.items.reduce((total, item) => total + item.display_time, 0);
    };

    const formatDuration = (seconds: number) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`;
        } else if (minutes > 0) {
            return `${minutes}m ${secs}s`;
        } else {
            return `${secs}s`;
        }
    };

    const getMediaTypeStats = () => {
        const stats = { images: 0, videos: 0, audios: 0, others: 0 };
        playlist.items.forEach(item => {
            const mimeType = item.media_file.mime_type;
            if (mimeType.startsWith('image/')) stats.images++;
            else if (mimeType.startsWith('video/')) stats.videos++;
            else if (mimeType.startsWith('audio/')) stats.audios++;
            else stats.others++;
        });
        return stats;
    };

    const mediaStats = getMediaTypeStats();
    const totalDuration = calculateTotalDuration();

    if (compact) {
        return (
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                        <IconPlaylistAdd className="h-5 w-5 text-blue-500 mt-0.5" />
                        <div className="flex-1 min-w-0">
                            <h4 className="font-medium truncate">{playlist.name}</h4>
                            <div className="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>{playlist.items.length} itens</span>
                                <span>{formatDuration(totalDuration)}</span>
                                <Badge
                                    variant={playlist.status === 'active' ? 'default' : 'secondary'}
                                    className={`text-xs ${playlist.status === 'active' ? 'bg-green-100 text-green-800' : ''}`}
                                >
                                    {playlist.status === 'active' ? 'Ativa' : 'Inativa'}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconPlaylistAdd className="h-5 w-5" />
                    Preview da Playlist
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Playlist Info */}
                <div>
                    <h3 className="font-semibold text-lg">{playlist.name}</h3>
                    {playlist.description && (
                        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {playlist.description}
                        </p>
                    )}

                    <div className="flex items-center gap-3 mt-2">
                        <Badge
                            variant={playlist.status === 'active' ? 'default' : 'secondary'}
                            className={playlist.status === 'active' ? 'bg-green-100 text-green-800' : ''}
                        >
                            {playlist.status === 'active' ? 'Ativa' : 'Inativa'}
                        </Badge>

                        {playlist.loop_enabled && (
                            <Badge variant="outline">
                                Loop Habilitado
                            </Badge>
                        )}
                    </div>
                </div>

                <Separator />

                {/* Statistics */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <IconListDetails className="h-6 w-6 mx-auto mb-1 text-blue-500" />
                        <p className="text-sm font-medium">{playlist.items.length}</p>
                        <p className="text-xs text-gray-500">Itens</p>
                    </div>

                    <div className="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <IconClock className="h-6 w-6 mx-auto mb-1 text-green-500" />
                        <p className="text-sm font-medium">{formatDuration(totalDuration)}</p>
                        <p className="text-xs text-gray-500">Duração</p>
                    </div>
                </div>

                {/* Media Type Breakdown */}
                {playlist.items.length > 0 && (
                    <>
                        <Separator />

                        <div>
                            <h4 className="text-sm font-medium mb-3">Tipos de Mídia</h4>
                            <div className="grid grid-cols-2 gap-2 text-xs">
                                {mediaStats.images > 0 && (
                                    <div className="flex items-center gap-2">
                                        <IconPhoto className="h-3 w-3 text-purple-500" />
                                        <span>{mediaStats.images} Imagens</span>
                                    </div>
                                )}
                                {mediaStats.videos > 0 && (
                                    <div className="flex items-center gap-2">
                                        <IconVideo className="h-3 w-3 text-red-500" />
                                        <span>{mediaStats.videos} Vídeos</span>
                                    </div>
                                )}
                                {mediaStats.audios > 0 && (
                                    <div className="flex items-center gap-2">
                                        <IconFileMusic className="h-3 w-3 text-blue-500" />
                                        <span>{mediaStats.audios} Áudios</span>
                                    </div>
                                )}
                                {mediaStats.others > 0 && (
                                    <div className="flex items-center gap-2">
                                        <IconFile className="h-3 w-3 text-gray-500" />
                                        <span>{mediaStats.others} Outros</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </>
                )}

                {/* Items Preview (Limited) */}
                {playlist.items.length > 0 && (
                    <>
                        <Separator />

                        <div>
                            <h4 className="text-sm font-medium mb-3">
                                Primeiros Itens {playlist.items.length > 3 && `(${Math.min(3, playlist.items.length)} de ${playlist.items.length})`}
                            </h4>
                            <div className="space-y-2">
                                {playlist.items.slice(0, 3).map((item, index) => (
                                    <div key={item.id} className="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-900 rounded">
                                        <span className="text-xs text-gray-500 w-4">{index + 1}</span>
                                        {getMediaIcon(item.media_file.mime_type)}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm truncate">{item.media_file.name}</p>
                                            <p className="text-xs text-gray-500">
                                                {item.display_time}s de exibição
                                            </p>
                                        </div>
                                    </div>
                                ))}

                                {playlist.items.length > 3 && (
                                    <div className="text-center py-2">
                                        <p className="text-xs text-gray-500">
                                            E mais {playlist.items.length - 3} itens...
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </>
                )}

                {playlist.items.length === 0 && (
                    <>
                        <Separator />
                        <div className="text-center py-6 text-gray-500 dark:text-gray-400">
                            <IconListDetails className="h-8 w-8 mx-auto mb-2" />
                            <p className="text-sm">Esta playlist não possui itens</p>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}