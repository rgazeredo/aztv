import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    IconDevices,
    IconArrowLeft,
    IconEdit,
    IconRefresh,
    IconWifi,
    IconWifiOff,
    IconActivity,
    IconClock,
    IconPlaylist,
    IconEye,
    IconTerminal,
    IconQrcode,
    IconCopy,
    IconCheck,
    IconCommand,
    IconReload
} from '@tabler/icons-react';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';

interface Player {
    id: number;
    name: string;
    alias?: string;
    location?: string;
    group?: string;
    status: string;
    ip_address?: string;
    last_seen?: string;
    app_version?: string;
    activation_token: string;
    device_info?: any;
    settings: any;
    is_online: boolean;
    last_seen_human: string;
    created_at: string;
    updated_at: string;
}

interface ActivityData {
    timestamp: string;
    status: number; // 1 for online, 0 for offline
}

interface PlaylistAssignment {
    id: number;
    name: string;
    items_count: number;
    assigned_at: string;
    is_default: boolean;
}

interface LogEntry {
    id: number;
    level: string;
    message: string;
    context?: any;
    created_at: string;
}

interface Props {
    player: Player;
    activityData: ActivityData[];
    playlists: PlaylistAssignment[];
    logs: {
        data: LogEntry[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

export default function PlayersShow({ player, activityData, playlists, logs }: Props) {
    const [loading, setLoading] = useState<string | null>(null);
    const [copySuccess, setCopySuccess] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    const handleCommand = async (command: string) => {
        if (!confirm(`Tem certeza que deseja executar: ${command}?`)) {
            return;
        }

        setLoading(command);

        try {
            router.post(`/players/${player.id}/send-command`, { command }, {
                onSuccess: () => {
                    setLoading(null);
                },
                onError: () => {
                    setLoading(null);
                }
            });
        } catch (error) {
            setLoading(null);
        }
    };

    const handleRestart = async () => {
        if (!confirm('Tem certeza que deseja reiniciar este player?')) {
            return;
        }

        setLoading('restart');

        try {
            router.post(`/players/${player.id}/restart`, {}, {
                onSuccess: () => {
                    setLoading(null);
                },
                onError: () => {
                    setLoading(null);
                }
            });
        } catch (error) {
            setLoading(null);
        }
    };

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopySuccess(true);
            setTimeout(() => setCopySuccess(false), 2000);
        } catch (error) {
            console.error('Failed to copy:', error);
        }
    };

    const refreshData = () => {
        setRefreshing(true);
        router.reload({
            onFinish: () => setRefreshing(false)
        });
    };

    // Auto refresh every 30 seconds
    useEffect(() => {
        const interval = setInterval(refreshData, 30000);
        return () => clearInterval(interval);
    }, []);

    const getStatusBadge = () => {
        if (player.is_online) {
            return (
                <Badge variant="default" className="bg-green-600">
                    <IconWifi className="h-3 w-3 mr-1" />
                    Online
                </Badge>
            );
        } else {
            return (
                <Badge variant="secondary" className="bg-red-100 text-red-800">
                    <IconWifiOff className="h-3 w-3 mr-1" />
                    Offline
                </Badge>
            );
        }
    };

    const getLogLevelBadge = (level: string) => {
        switch (level.toLowerCase()) {
            case 'error':
                return <Badge variant="destructive">Error</Badge>;
            case 'warning':
                return <Badge variant="secondary" className="bg-yellow-100 text-yellow-800">Warning</Badge>;
            case 'info':
                return <Badge variant="outline">Info</Badge>;
            case 'debug':
                return <Badge variant="outline" className="bg-gray-100">Debug</Badge>;
            default:
                return <Badge variant="outline">{level}</Badge>;
        }
    };

    // Process activity data for chart
    const chartData = activityData.map(item => ({
        time: format(new Date(item.timestamp), 'HH:mm'),
        status: item.status,
        timestamp: item.timestamp
    }));

    return (
        <AuthenticatedLayout>
            <Head title={`Player: ${player.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="mb-6 flex items-center justify-between">
                        <Button variant="outline" asChild>
                            <Link href="/players">
                                <IconArrowLeft className="h-4 w-4 mr-2" />
                                Voltar para Players
                            </Link>
                        </Button>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={refreshData}
                                disabled={refreshing}
                            >
                                <IconRefresh className={cn('h-4 w-4 mr-2', refreshing && 'animate-spin')} />
                                Atualizar
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/players/${player.id}/edit`}>
                                    <IconEdit className="h-4 w-4 mr-2" />
                                    Editar
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {/* Player Header */}
                    <Card className="mb-6">
                        <CardHeader>
                            <div className="flex items-start justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconDevices className="h-6 w-6" />
                                        {player.name}
                                        {player.alias && (
                                            <span className="text-lg text-gray-500">({player.alias})</span>
                                        )}
                                    </CardTitle>
                                    <CardDescription className="mt-2">
                                        {player.location && <span>{player.location} • </span>}
                                        {player.group && (
                                            <Badge variant="outline" className="mr-2">{player.group}</Badge>
                                        )}
                                        Criado em {format(new Date(player.created_at), 'dd/MM/yyyy', { locale: ptBR })}
                                    </CardDescription>
                                </div>
                                <div className="text-right">
                                    {getStatusBadge()}
                                    {player.last_seen && (
                                        <div className="text-sm text-gray-500 mt-1">
                                            Última atividade: {player.last_seen_human}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                    </Card>

                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Main Content */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Activity Chart */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconActivity className="h-5 w-5" />
                                        Atividade nas Últimas 24h
                                    </CardTitle>
                                    <CardDescription>
                                        Status de conectividade do player
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="h-64">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <LineChart data={chartData}>
                                                <CartesianGrid strokeDasharray="3 3" />
                                                <XAxis dataKey="time" />
                                                <YAxis
                                                    domain={[0, 1]}
                                                    tickFormatter={(value) => value === 1 ? 'Online' : 'Offline'}
                                                />
                                                <Tooltip
                                                    formatter={(value, name) => [
                                                        value === 1 ? 'Online' : 'Offline',
                                                        'Status'
                                                    ]}
                                                    labelFormatter={(label) => `Horário: ${label}`}
                                                />
                                                <Line
                                                    type="stepAfter"
                                                    dataKey="status"
                                                    stroke="#10b981"
                                                    strokeWidth={2}
                                                    dot={{ fill: '#10b981', strokeWidth: 2, r: 4 }}
                                                />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Playlists */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconPlaylist className="h-5 w-5" />
                                        Playlists Atribuídas
                                    </CardTitle>
                                    <CardDescription>
                                        Conteúdo configurado para este player
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {playlists.length > 0 ? (
                                        <div className="space-y-3">
                                            {playlists.map((playlist) => (
                                                <div key={playlist.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{playlist.name}</span>
                                                            {playlist.is_default && (
                                                                <Badge variant="default" className="text-xs">Padrão</Badge>
                                                            )}
                                                        </div>
                                                        <div className="text-sm text-gray-600">
                                                            {playlist.items_count} itens • Atribuída em {format(new Date(playlist.assigned_at), 'dd/MM/yyyy', { locale: ptBR })}
                                                        </div>
                                                    </div>
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/playlists/${playlist.id}`}>
                                                            <IconEye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <IconPlaylist className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                            <p>Nenhuma playlist atribuída</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Logs */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconTerminal className="h-5 w-5" />
                                        Logs de Atividade
                                    </CardTitle>
                                    <CardDescription>
                                        Histórico de eventos do player
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {logs.data.length > 0 ? (
                                        <>
                                            <div className="rounded-md border">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Nível</TableHead>
                                                            <TableHead>Mensagem</TableHead>
                                                            <TableHead>Data/Hora</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {logs.data.map((log) => (
                                                            <TableRow key={log.id}>
                                                                <TableCell>
                                                                    {getLogLevelBadge(log.level)}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <div className="font-medium">{log.message}</div>
                                                                    {log.context && (
                                                                        <div className="text-xs text-gray-500 mt-1">
                                                                            {JSON.stringify(log.context)}
                                                                        </div>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <div className="text-sm">
                                                                        {format(new Date(log.created_at), 'dd/MM/yyyy HH:mm:ss', { locale: ptBR })}
                                                                    </div>
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </div>

                                            {logs.last_page > 1 && (
                                                <div className="mt-4 text-center">
                                                    <p className="text-sm text-gray-600">
                                                        Página {logs.current_page} de {logs.last_page} ({logs.total} entradas)
                                                    </p>
                                                    <Button variant="outline" size="sm" className="mt-2" asChild>
                                                        <Link href={`/players/${player.id}/logs`}>
                                                            Ver todos os logs
                                                        </Link>
                                                    </Button>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <IconTerminal className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                            <p>Nenhum log encontrado</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Quick Actions */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconCommand className="h-5 w-5" />
                                        Ações Remotas
                                    </CardTitle>
                                    <CardDescription>
                                        Comandos para o player
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleRestart}
                                            disabled={loading === 'restart' || !player.is_online}
                                            className="w-full justify-start"
                                        >
                                            <IconReload className="h-4 w-4 mr-2" />
                                            {loading === 'restart' ? 'Reiniciando...' : 'Reiniciar Player'}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleCommand('sync')}
                                            disabled={loading === 'sync' || !player.is_online}
                                            className="w-full justify-start"
                                        >
                                            <IconRefresh className="h-4 w-4 mr-2" />
                                            {loading === 'sync' ? 'Sincronizando...' : 'Forçar Sincronização'}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleCommand('screenshot')}
                                            disabled={loading === 'screenshot' || !player.is_online}
                                            className="w-full justify-start"
                                        >
                                            <IconEye className="h-4 w-4 mr-2" />
                                            {loading === 'screenshot' ? 'Capturando...' : 'Capturar Tela'}
                                        </Button>
                                    </div>

                                    {!player.is_online && (
                                        <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <p className="text-sm text-yellow-700">
                                                ⚠️ Player offline. Comandos remotos não disponíveis.
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Token Info */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconQrcode className="h-5 w-5" />
                                        Token de Ativação
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div className="text-center">
                                            <div className="w-24 h-24 mx-auto bg-gray-100 rounded-lg flex items-center justify-center mb-3">
                                                <IconQrcode className="h-12 w-12 text-gray-400" />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-sm font-medium">Token:</label>
                                            <div className="flex gap-2">
                                                <Input
                                                    value={player.activation_token}
                                                    readOnly
                                                    className="font-mono text-xs"
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => copyToClipboard(player.activation_token)}
                                                >
                                                    {copySuccess ? (
                                                        <IconCheck className="h-4 w-4 text-green-600" />
                                                    ) : (
                                                        <IconCopy className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Device Info */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Informações do Dispositivo</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3 text-sm">
                                        {player.ip_address && (
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">IP:</span>
                                                <span className="font-mono">{player.ip_address}</span>
                                            </div>
                                        )}
                                        {player.app_version && (
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">App Version:</span>
                                                <span className="font-medium">{player.app_version}</span>
                                            </div>
                                        )}
                                        {player.device_info && Object.entries(player.device_info).map(([key, value]) => (
                                            <div key={key} className="flex justify-between">
                                                <span className="text-gray-600 capitalize">{key.replace('_', ' ')}:</span>
                                                <span className="font-medium text-right">{String(value)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Configurações</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3 text-sm">
                                        {Object.entries(player.settings).map(([key, value]) => (
                                            <div key={key} className="flex justify-between">
                                                <span className="text-gray-600 capitalize">{key.replace('_', ' ')}:</span>
                                                <span className="font-medium">{String(value)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}