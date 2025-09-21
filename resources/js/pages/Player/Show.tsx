import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Monitor,
    Wifi,
    WifiOff,
    Edit,
    FileText,
    Settings,
    RotateCcw,
    Key,
    MapPin,
    Users,
    Clock,
    Calendar,
    Smartphone,
    Trash2
} from 'lucide-react';

interface Player {
    id: number;
    name: string;
    alias?: string;
    location?: string;
    group?: string;
    status: string;
    is_online: boolean;
    last_seen?: string;
    ip_address?: string;
    app_version?: string;
    device_info?: any;
    playlists_count: number;
    logs_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    player: Player;
    analytics?: any;
    recent_logs?: any[];
    playlists?: any[];
}

export default function Show({ player, analytics, recent_logs, playlists }: Props) {
    const { delete: destroy, processing } = useForm();
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

    const handleDelete = () => {
        destroy(`/players/${player.id}`);
        setShowDeleteDialog(false);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Players',
            href: '/players',
        },
        {
            title: player.name,
            href: `/players/${player.id}`,
        },
    ];

    const getStatusBadge = () => {
        return (
            <Badge variant={player.is_online ? "default" : "secondary"} className="flex items-center gap-1">
                {player.is_online ? <Wifi className="h-3 w-3" /> : <WifiOff className="h-3 w-3" />}
                {player.is_online ? 'Online' : 'Offline'}
            </Badge>
        );
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('pt-BR');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Player: ${player.name}`} />
            <div className="flex-1 space-y-4 p-4 pt-6">

            {/* Header */}
            <div className="flex items-center justify-between space-y-2">
                <div>
                    <h2 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                        <Monitor className="h-8 w-8" />
                        {player.name}
                    </h2>
                    {player.alias && (
                        <p className="text-muted-foreground">{player.alias}</p>
                    )}
                </div>
                <div className="flex items-center space-x-2">
                    {getStatusBadge()}
                    <Button asChild>
                        <Link href={`/players/${player.id}/edit`}>
                            <Edit className="mr-2 h-4 w-4" />
                            Editar
                        </Link>
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => setShowDeleteDialog(true)}
                        disabled={processing}
                    >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Excluir
                    </Button>
                </div>
            </div>

            {/* Player Info */}
            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Smartphone className="h-5 w-5" />
                            Informações do Player
                        </CardTitle>
                        <CardDescription>
                            Detalhes básicos e configurações do player
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-4">
                            <div className="flex items-center gap-2">
                                <Monitor className="h-4 w-4 text-muted-foreground" />
                                <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Nome:</dt>
                                <dd className="text-sm font-medium">{player.name}</dd>
                            </div>
                            {player.alias && (
                                <div className="flex items-center gap-2">
                                    <Monitor className="h-4 w-4 text-muted-foreground" />
                                    <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Alias:</dt>
                                    <dd className="text-sm">{player.alias}</dd>
                                </div>
                            )}
                            {player.location && (
                                <div className="flex items-center gap-2">
                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                    <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Localização:</dt>
                                    <dd className="text-sm">{player.location}</dd>
                                </div>
                            )}
                            {player.group && (
                                <div className="flex items-center gap-2">
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Grupo:</dt>
                                    <dd className="text-sm">
                                        <Badge variant="outline">{player.group}</Badge>
                                    </dd>
                                </div>
                            )}
                            <div className="flex items-center gap-2">
                                <Wifi className="h-4 w-4 text-muted-foreground" />
                                <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Status:</dt>
                                <dd className="text-sm">{getStatusBadge()}</dd>
                            </div>
                            {player.ip_address && (
                                <div className="flex items-center gap-2">
                                    <Monitor className="h-4 w-4 text-muted-foreground" />
                                    <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">IP:</dt>
                                    <dd className="text-sm font-mono">{player.ip_address}</dd>
                                </div>
                            )}
                            {player.app_version && (
                                <div className="flex items-center gap-2">
                                    <Smartphone className="h-4 w-4 text-muted-foreground" />
                                    <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Versão:</dt>
                                    <dd className="text-sm">{player.app_version}</dd>
                                </div>
                            )}
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4 text-muted-foreground" />
                                <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Última Conexão:</dt>
                                <dd className="text-sm">
                                    {player.last_seen ? formatDate(player.last_seen) : 'Nunca'}
                                </dd>
                            </div>
                            <div className="flex items-center gap-2">
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                <dt className="text-sm font-medium text-muted-foreground min-w-[100px]">Criado em:</dt>
                                <dd className="text-sm">{formatDate(player.created_at)}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Estatísticas</CardTitle>
                        <CardDescription>
                            Resumo de atividades e recursos
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4">
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center p-6">
                                    <FileText className="h-8 w-8 text-blue-600 mb-2" />
                                    <div className="text-2xl font-bold text-blue-600">{player.playlists_count}</div>
                                    <p className="text-xs text-muted-foreground text-center">Playlists</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center p-6">
                                    <FileText className="h-8 w-8 text-green-600 mb-2" />
                                    <div className="text-2xl font-bold text-green-600">{player.logs_count}</div>
                                    <p className="text-xs text-muted-foreground text-center">Logs</p>
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Device Info */}
            {player.device_info && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Smartphone className="h-5 w-5" />
                            Informações do Dispositivo
                        </CardTitle>
                        <CardDescription>
                            Detalhes técnicos do hardware e sistema
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <pre className="bg-muted p-4 rounded-lg text-sm overflow-x-auto">
                            {JSON.stringify(player.device_info, null, 2)}
                        </pre>
                    </CardContent>
                </Card>
            )}

            {/* Recent Logs */}
            {recent_logs && recent_logs.length > 0 && (
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    Logs Recentes
                                </CardTitle>
                                <CardDescription>
                                    Últimas atividades registradas
                                </CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={`/players/${player.id}/logs`}>
                                    Ver todos os logs
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {recent_logs.slice(0, 5).map((log: any, index: number) => (
                                <div key={index} className="border-l-4 border-primary pl-4 py-2 bg-muted/30 rounded-r">
                                    <p className="text-sm font-medium">{log.message}</p>
                                    <p className="text-xs text-muted-foreground">{formatDate(log.created_at)}</p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Playlists */}
            {playlists && playlists.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Playlists Atribuídas
                        </CardTitle>
                        <CardDescription>
                            Conteúdo programado para este player
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {playlists.map((playlist: any) => (
                                <Card key={playlist.id}>
                                    <CardContent className="p-4">
                                        <h3 className="font-medium mb-1">{playlist.name}</h3>
                                        <p className="text-sm text-muted-foreground mb-3">{playlist.description}</p>
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/playlists/${playlist.id}`}>
                                                Ver playlist
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Actions */}
            <Card>
                <CardHeader>
                    <CardTitle>Ações</CardTitle>
                    <CardDescription>
                        Operações disponíveis para este player
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild>
                            <Link href={`/players/${player.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Editar Player
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/players/${player.id}/logs`}>
                                <FileText className="mr-2 h-4 w-4" />
                                Ver Logs
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/players/${player.id}/config`}>
                                <Settings className="mr-2 h-4 w-4" />
                                Configurações
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {/* TODO: Implement restart functionality */}}
                        >
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Reiniciar Player
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {/* TODO: Implement regenerate token functionality */}}
                        >
                            <Key className="mr-2 h-4 w-4" />
                            Regenerar Token
                        </Button>
                    </div>
                </CardContent>
            </Card>
            </div>

            {/* Delete Confirmation Dialog */}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar Exclusão</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir o player <strong>"{player.name}"</strong>?
                            <br />
                            Esta ação não pode ser desfeita e todos os dados relacionados serão perdidos.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowDeleteDialog(false)}
                            disabled={processing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={processing}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            {processing ? 'Excluindo...' : 'Excluir Player'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}