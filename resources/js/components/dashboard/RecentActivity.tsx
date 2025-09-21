import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    IconActivity,
    IconDevices,
    IconPhoto,
    IconPlaylist,
    IconUpload,
    IconWifi,
    IconWifiOff,
    IconClock,
    IconEye,
    IconChevronDown,
    IconChevronUp
} from '@tabler/icons-react';
import { cn } from '@/lib/utils';

interface RecentData {
    recent_media: Array<{
        id: number;
        name: string;
        filename: string;
        mime_type: string;
        size: string;
        thumbnail_url?: string;
        created_at: string;
        created_at_human: string;
    }>;
    recent_playlists: Array<{
        id: number;
        name: string;
        description?: string;
        items_count: number;
        updated_at: string;
        updated_at_human: string;
    }>;
    recent_player_activity: Array<{
        id: number;
        name: string;
        alias?: string;
        status: string;
        is_online: boolean;
        last_seen: string;
        last_seen_human: string;
        ip_address?: string;
    }>;
}

interface RecentActivityProps {
    data: RecentData;
}

interface ActivityItem {
    id: string;
    type: 'media' | 'playlist' | 'player_online' | 'player_offline';
    title: string;
    description: string;
    timestamp: string;
    timestamp_human: string;
    metadata?: any;
}

const RecentActivity: React.FC<RecentActivityProps> = ({ data }) => {
    const [showAll, setShowAll] = useState(false);
    const [filter, setFilter] = useState<'all' | 'media' | 'playlist' | 'player'>('all');

    const generateActivityItems = (): ActivityItem[] => {
        const items: ActivityItem[] = [];

        // Add media uploads
        data.recent_media?.forEach((media) => {
            items.push({
                id: `media-${media.id}`,
                type: 'media',
                title: `Nova mídia adicionada`,
                description: media.name,
                timestamp: media.created_at,
                timestamp_human: media.created_at_human,
                metadata: {
                    size: media.size,
                    mime_type: media.mime_type,
                    thumbnail_url: media.thumbnail_url
                }
            });
        });

        // Add playlist updates
        data.recent_playlists?.forEach((playlist) => {
            items.push({
                id: `playlist-${playlist.id}`,
                type: 'playlist',
                title: `Playlist atualizada`,
                description: `${playlist.name} (${playlist.items_count} itens)`,
                timestamp: playlist.updated_at,
                timestamp_human: playlist.updated_at_human,
                metadata: {
                    items_count: playlist.items_count,
                    description: playlist.description
                }
            });
        });

        // Add player activity
        data.recent_player_activity?.forEach((player) => {
            items.push({
                id: `player-${player.id}`,
                type: player.is_online ? 'player_online' : 'player_offline',
                title: player.is_online ? 'Player conectado' : 'Player desconectado',
                description: `${player.name}${player.alias ? ` (${player.alias})` : ''}`,
                timestamp: player.last_seen,
                timestamp_human: player.last_seen_human,
                metadata: {
                    ip_address: player.ip_address,
                    status: player.status
                }
            });
        });

        // Sort by timestamp (most recent first)
        return items.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());
    };

    const allActivities = generateActivityItems();
    const filteredActivities = filter === 'all'
        ? allActivities
        : allActivities.filter(item => {
            if (filter === 'player') return item.type.startsWith('player_');
            return item.type === filter;
        });

    const displayedActivities = showAll ? filteredActivities : filteredActivities.slice(0, 5);

    const getActivityIcon = (type: ActivityItem['type']) => {
        switch (type) {
            case 'media':
                return IconPhoto;
            case 'playlist':
                return IconPlaylist;
            case 'player_online':
                return IconWifi;
            case 'player_offline':
                return IconWifiOff;
            default:
                return IconActivity;
        }
    };

    const getActivityColors = (type: ActivityItem['type']) => {
        switch (type) {
            case 'media':
                return {
                    bg: 'bg-blue-100',
                    icon: 'text-blue-600',
                    border: 'border-blue-200'
                };
            case 'playlist':
                return {
                    bg: 'bg-purple-100',
                    icon: 'text-purple-600',
                    border: 'border-purple-200'
                };
            case 'player_online':
                return {
                    bg: 'bg-green-100',
                    icon: 'text-green-600',
                    border: 'border-green-200'
                };
            case 'player_offline':
                return {
                    bg: 'bg-red-100',
                    icon: 'text-red-600',
                    border: 'border-red-200'
                };
            default:
                return {
                    bg: 'bg-gray-100',
                    icon: 'text-gray-600',
                    border: 'border-gray-200'
                };
        }
    };

    const getTypeBadge = (type: ActivityItem['type']) => {
        switch (type) {
            case 'media':
                return <Badge variant="secondary" className="text-xs">Mídia</Badge>;
            case 'playlist':
                return <Badge variant="secondary" className="text-xs">Playlist</Badge>;
            case 'player_online':
                return <Badge variant="default" className="text-xs bg-green-600">Online</Badge>;
            case 'player_offline':
                return <Badge variant="destructive" className="text-xs">Offline</Badge>;
            default:
                return null;
        }
    };

    if (allActivities.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconActivity className="h-5 w-5" />
                        Atividade Recente
                    </CardTitle>
                    <CardDescription>
                        Timeline das últimas ações e eventos
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-center py-8">
                        <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <IconClock className="h-8 w-8 text-gray-400" />
                        </div>
                        <p className="text-lg font-medium text-gray-600">Nenhuma atividade recente</p>
                        <p className="text-sm text-gray-500">As ações do sistema aparecerão aqui</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconActivity className="h-5 w-5" />
                    Atividade Recente
                    <Badge variant="outline" className="ml-auto">
                        {filteredActivities.length}
                    </Badge>
                </CardTitle>
                <CardDescription>
                    Timeline das últimas ações e eventos do sistema
                </CardDescription>
            </CardHeader>
            <CardContent>
                {/* Filter Buttons */}
                <div className="flex gap-2 mb-4 overflow-x-auto">
                    <Button
                        variant={filter === 'all' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('all')}
                    >
                        Todos ({allActivities.length})
                    </Button>
                    <Button
                        variant={filter === 'media' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('media')}
                    >
                        <IconPhoto className="h-4 w-4 mr-1" />
                        Mídia ({allActivities.filter(a => a.type === 'media').length})
                    </Button>
                    <Button
                        variant={filter === 'playlist' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('playlist')}
                    >
                        <IconPlaylist className="h-4 w-4 mr-1" />
                        Playlists ({allActivities.filter(a => a.type === 'playlist').length})
                    </Button>
                    <Button
                        variant={filter === 'player' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('player')}
                    >
                        <IconDevices className="h-4 w-4 mr-1" />
                        Players ({allActivities.filter(a => a.type.startsWith('player_')).length})
                    </Button>
                </div>

                {/* Activity Timeline */}
                <div className="space-y-3">
                    {displayedActivities.map((activity, index) => {
                        const Icon = getActivityIcon(activity.type);
                        const colors = getActivityColors(activity.type);

                        return (
                            <div key={activity.id} className="relative">
                                {index < displayedActivities.length - 1 && (
                                    <div className="absolute left-6 top-12 w-0.5 h-6 bg-gray-200" />
                                )}
                                <div className="flex items-start gap-3">
                                    <div className={cn(
                                        'flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center',
                                        colors.bg
                                    )}>
                                        <Icon className={cn('h-5 w-5', colors.icon)} />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <p className="font-medium text-gray-900">
                                                {activity.title}
                                            </p>
                                            {getTypeBadge(activity.type)}
                                        </div>
                                        <p className="text-sm text-gray-600 mb-1">
                                            {activity.description}
                                        </p>
                                        <div className="flex items-center gap-4 text-xs text-gray-500">
                                            <span className="flex items-center gap-1">
                                                <IconClock className="h-3 w-3" />
                                                {activity.timestamp_human}
                                            </span>
                                            {activity.metadata?.size && (
                                                <span>{activity.metadata.size}</span>
                                            )}
                                            {activity.metadata?.ip_address && (
                                                <span>IP: {activity.metadata.ip_address}</span>
                                            )}
                                        </div>
                                    </div>
                                    {activity.metadata?.thumbnail_url && (
                                        <img
                                            src={activity.metadata.thumbnail_url}
                                            alt="Thumbnail"
                                            className="w-12 h-12 object-cover rounded"
                                        />
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Show More/Less Button */}
                {filteredActivities.length > 5 && (
                    <div className="mt-4 text-center">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowAll(!showAll)}
                        >
                            {showAll ? (
                                <>
                                    <IconChevronUp className="h-4 w-4 mr-1" />
                                    Mostrar menos
                                </>
                            ) : (
                                <>
                                    <IconChevronDown className="h-4 w-4 mr-1" />
                                    Mostrar mais ({filteredActivities.length - 5} itens)
                                </>
                            )}
                        </Button>
                    </div>
                )}

                {filteredActivities.length === 0 && filter !== 'all' && (
                    <div className="text-center py-8">
                        <p className="text-gray-500">Nenhuma atividade encontrada para este filtro</p>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setFilter('all')}
                            className="mt-2"
                        >
                            Ver todas as atividades
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

export default RecentActivity;