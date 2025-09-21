import { Head } from "@inertiajs/react";
import ClientLayout from "@/Layouts/ClientLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { IconArrowLeft, IconEdit, IconRefresh, IconTrash, IconClock, IconPlaylist } from "@tabler/icons-react";
import PlayerStatusBadge from "@/components/players/PlayerStatusBadge";
import ActivationQRCode from "@/components/players/ActivationQRCode";

interface Player {
    id: number;
    name: string;
    description?: string;
    location?: string;
    activation_code: string;
    is_online: boolean;
    last_seen?: string;
    created_at: string;
    playlists_count: number;
    settings: {
        volume: number;
        sync_interval: number;
        display_mode: string;
        auto_restart: boolean;
        debug_mode: boolean;
    };
}

interface Activity {
    id: number;
    type: string;
    description: string;
    created_at: string;
}

interface ShowProps {
    player: Player;
    activities: Activity[];
    playlists: Array<{
        id: number;
        name: string;
        media_count: number;
    }>;
}

export default function Show({ player, activities, playlists }: ShowProps) {
    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString("pt-BR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    };

    const getActivityIcon = (type: string) => {
        switch (type) {
            case 'sync':
                return <IconRefresh className="h-4 w-4 text-blue-600" />;
            case 'playlist':
                return <IconPlaylist className="h-4 w-4 text-green-600" />;
            default:
                return <IconClock className="h-4 w-4 text-gray-600" />;
        }
    };

    return (
        <ClientLayout>
            <Head title={player.name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <a href={route("players.index")}>
                                <IconArrowLeft className="h-4 w-4 mr-2" />
                                Voltar
                            </a>
                        </Button>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-3xl font-bold">{player.name}</h1>
                                <PlayerStatusBadge
                                    isOnline={player.is_online}
                                    lastSeen={player.last_seen}
                                />
                            </div>
                            {player.description && (
                                <p className="text-gray-600">{player.description}</p>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={route("players.edit", player.id)}>
                                <IconEdit className="h-4 w-4 mr-2" />
                                Editar
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Player Info */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Basic Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Informações do Player</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-gray-600">Localização</p>
                                        <p className="font-medium">{player.location || "Não definida"}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Criado em</p>
                                        <p className="font-medium">{formatDate(player.created_at)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Último acesso</p>
                                        <p className="font-medium">
                                            {player.last_seen ? formatDate(player.last_seen) : "Nunca"}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Playlists atribuídas</p>
                                        <p className="font-medium">{player.playlists_count}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Settings */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Configurações Atuais</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-gray-600">Volume</p>
                                        <p className="font-medium">{player.settings.volume}%</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Sincronização</p>
                                        <p className="font-medium">{player.settings.sync_interval} min</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Modo de Exibição</p>
                                        <Badge variant="outline">{player.settings.display_mode}</Badge>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Configurações</p>
                                        <div className="flex gap-2">
                                            {player.settings.auto_restart && (
                                                <Badge variant="outline" className="text-xs">Auto-restart</Badge>
                                            )}
                                            {player.settings.debug_mode && (
                                                <Badge variant="outline" className="text-xs">Debug</Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Playlists */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Playlists Atribuídas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {playlists.length === 0 ? (
                                    <p className="text-gray-500 text-center py-4">
                                        Nenhuma playlist atribuída a este player
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {playlists.map((playlist) => (
                                            <div
                                                key={playlist.id}
                                                className="flex items-center justify-between p-3 border rounded-lg"
                                            >
                                                <div>
                                                    <p className="font-medium">{playlist.name}</p>
                                                    <p className="text-sm text-gray-600">
                                                        {playlist.media_count} mídia(s)
                                                    </p>
                                                </div>
                                                <Button variant="outline" size="sm" asChild>
                                                    <a href={route("playlists.show", playlist.id)}>
                                                        Ver
                                                    </a>
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Activity Log */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Logs de Atividade</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {activities.length === 0 ? (
                                    <p className="text-gray-500 text-center py-4">
                                        Nenhuma atividade registrada
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {activities.slice(0, 10).map((activity) => (
                                            <div key={activity.id} className="flex items-start gap-3">
                                                {getActivityIcon(activity.type)}
                                                <div className="flex-1">
                                                    <p className="text-sm">{activity.description}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {formatDate(activity.created_at)}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* QR Code */}
                        <ActivationQRCode
                            activationCode={player.activation_code}
                            playerName={player.name}
                        />

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Ações Rápidas</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button variant="outline" className="w-full" asChild>
                                    <a href={route("players.edit", player.id)}>
                                        <IconEdit className="h-4 w-4 mr-2" />
                                        Editar Player
                                    </a>
                                </Button>

                                {player.is_online && (
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => {
                                            // Implementar restart via API
                                        }}
                                    >
                                        <IconRefresh className="h-4 w-4 mr-2" />
                                        Reiniciar Player
                                    </Button>
                                )}

                                <Button
                                    variant="destructive"
                                    className="w-full"
                                    onClick={() => {
                                        if (confirm(`Deseja excluir o player "${player.name}"?`)) {
                                            // Implementar delete via API
                                        }
                                    }}
                                >
                                    <IconTrash className="h-4 w-4 mr-2" />
                                    Excluir Player
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}