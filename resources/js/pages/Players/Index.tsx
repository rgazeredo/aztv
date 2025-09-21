import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PaginatedCollection } from '@/types';
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
    IconPlus,
    IconEdit,
    IconTrash,
    IconEye,
    IconSearch,
    IconFilter,
    IconRefresh,
    IconWifi,
    IconWifiOff,
    IconSettings,
    IconQrcode,
    IconCopy
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
    settings?: any;
    is_online: boolean;
    last_seen_human: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    players: PaginatedCollection<Player>;
    filters: {
        search?: string;
        status?: string;
        group?: string;
    };
    statistics: {
        total: number;
        online: number;
        offline: number;
        groups: Array<{ name: string; count: number }>;
    };
}

export default function PlayersIndex({ players, filters, statistics }: Props) {
    const [loading, setLoading] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedPlayers, setSelectedPlayers] = useState<Set<number>>(new Set());

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/players', {
            ...filters,
            search: searchQuery || undefined
        }, {
            preserveState: true
        });
    };

    const handleFilterStatus = (status: string | undefined) => {
        router.get('/players', {
            ...filters,
            status: status || undefined
        }, {
            preserveState: true
        });
    };

    const handleFilterGroup = (group: string | undefined) => {
        router.get('/players', {
            ...filters,
            group: group || undefined
        }, {
            preserveState: true
        });
    };

    const handleDeletePlayer = async (playerId: number) => {
        if (!confirm('Tem certeza que deseja excluir este player?')) {
            return;
        }

        setLoading(`delete-${playerId}`);

        try {
            router.delete(`/players/${playerId}`, {
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

    const handleRestartPlayer = async (playerId: number) => {
        if (!confirm('Tem certeza que deseja reiniciar este player?')) {
            return;
        }

        setLoading(`restart-${playerId}`);

        try {
            router.post(`/players/${playerId}/restart`, {}, {
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

    const handleRegenerateToken = async (playerId: number) => {
        if (!confirm('Tem certeza que deseja regenerar o token? O player precisará ser reativado.')) {
            return;
        }

        setLoading(`token-${playerId}`);

        try {
            router.post(`/players/${playerId}/regenerate-token`, {}, {
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
            // Could add a toast notification here
        } catch (error) {
            console.error('Failed to copy:', error);
        }
    };

    const getStatusBadge = (player: Player) => {
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

    const handleBulkAction = (action: string) => {
        if (selectedPlayers.size === 0) {
            alert('Selecione pelo menos um player');
            return;
        }

        const playerIds = Array.from(selectedPlayers);

        if (!confirm(`Tem certeza que deseja ${action} ${playerIds.length} player(s)?`)) {
            return;
        }

        setLoading(`bulk-${action}`);

        router.post('/players/bulk-action', {
            player_ids: playerIds,
            action: action
        }, {
            onSuccess: () => {
                setSelectedPlayers(new Set());
                setLoading(null);
            },
            onError: () => {
                setLoading(null);
            }
        });
    };

    const togglePlayerSelection = (playerId: number) => {
        const newSelection = new Set(selectedPlayers);
        if (newSelection.has(playerId)) {
            newSelection.delete(playerId);
        } else {
            newSelection.add(playerId);
        }
        setSelectedPlayers(newSelection);
    };

    const toggleAllSelection = () => {
        if (selectedPlayers.size === players.data.length) {
            setSelectedPlayers(new Set());
        } else {
            setSelectedPlayers(new Set(players.data.map(p => p.id)));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Gerenciamento de Players" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Statistics */}
                    <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-gray-500">Total de Players</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.total}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-gray-500">Online</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">{statistics.online}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-gray-500">Offline</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">{statistics.offline}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-gray-500">Taxa Online</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-600">
                                    {statistics.total > 0 ? Math.round((statistics.online / statistics.total) * 100) : 0}%
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconDevices className="h-5 w-5" />
                                        Players Android
                                    </CardTitle>
                                    <CardDescription>
                                        Gerencie os dispositivos conectados ao sistema
                                    </CardDescription>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        onClick={() => router.reload()}
                                        variant="outline"
                                        size="sm"
                                    >
                                        <IconRefresh className="h-4 w-4" />
                                    </Button>
                                    <Button asChild>
                                        <Link href="/players/create">
                                            <IconPlus className="h-4 w-4 mr-2" />
                                            Novo Player
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {/* Filters */}
                            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <form onSubmit={handleSearch} className="flex gap-2 flex-1">
                                    <div className="relative flex-1 max-w-sm">
                                        <IconSearch className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                        <input
                                            type="text"
                                            placeholder="Buscar players..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <Button type="submit" variant="outline">
                                        Buscar
                                    </Button>
                                </form>

                                <div className="flex gap-2">
                                    <select
                                        value={filters.status || ''}
                                        onChange={(e) => handleFilterStatus(e.target.value || undefined)}
                                        className="rounded-md border-gray-300 text-sm"
                                    >
                                        <option value="">Todos os status</option>
                                        <option value="online">Online</option>
                                        <option value="offline">Offline</option>
                                    </select>

                                    <select
                                        value={filters.group || ''}
                                        onChange={(e) => handleFilterGroup(e.target.value || undefined)}
                                        className="rounded-md border-gray-300 text-sm"
                                    >
                                        <option value="">Todos os grupos</option>
                                        {statistics.groups.map((group) => (
                                            <option key={group.name} value={group.name}>
                                                {group.name} ({group.count})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Bulk Actions */}
                            {selectedPlayers.size > 0 && (
                                <div className="mb-4 flex items-center gap-2 p-4 bg-blue-50 rounded-lg">
                                    <span className="text-sm text-blue-700">
                                        {selectedPlayers.size} player(s) selecionado(s)
                                    </span>
                                    <div className="flex gap-2 ml-auto">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('restart')}
                                            disabled={loading?.startsWith('bulk-')}
                                        >
                                            Reiniciar
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('delete')}
                                            disabled={loading?.startsWith('bulk-')}
                                        >
                                            Excluir
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {/* Table */}
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedPlayers.size === players.data.length && players.data.length > 0}
                                                    onChange={toggleAllSelection}
                                                    className="rounded"
                                                />
                                            </TableHead>
                                            <TableHead>Player</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Última Atividade</TableHead>
                                            <TableHead>Token</TableHead>
                                            <TableHead>Localização</TableHead>
                                            <TableHead className="text-right">Ações</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {players.data.map((player) => (
                                            <TableRow key={player.id}>
                                                <TableCell>
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedPlayers.has(player.id)}
                                                        onChange={() => togglePlayerSelection(player.id)}
                                                        className="rounded"
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{player.name}</div>
                                                        {player.alias && (
                                                            <div className="text-sm text-gray-500">{player.alias}</div>
                                                        )}
                                                        {player.ip_address && (
                                                            <div className="text-xs text-gray-400">IP: {player.ip_address}</div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(player)}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {player.last_seen_human}
                                                    </div>
                                                    {player.last_seen && (
                                                        <div className="text-xs text-gray-500">
                                                            {format(new Date(player.last_seen), 'dd/MM/yyyy HH:mm', { locale: ptBR })}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <code className="text-xs bg-gray-100 px-2 py-1 rounded">
                                                            {player.activation_token.substring(0, 8)}...
                                                        </code>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => copyToClipboard(player.activation_token)}
                                                            className="h-6 w-6 p-0"
                                                        >
                                                            <IconCopy className="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {player.location || 'Não definida'}
                                                    </div>
                                                    {player.group && (
                                                        <Badge variant="outline" className="text-xs">
                                                            {player.group}
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            asChild
                                                        >
                                                            <Link href={`/players/${player.id}`}>
                                                                <IconEye className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            asChild
                                                        >
                                                            <Link href={`/players/${player.id}/edit`}>
                                                                <IconEdit className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => handleRegenerateToken(player.id)}
                                                            disabled={loading === `token-${player.id}`}
                                                        >
                                                            <IconQrcode className="h-4 w-4" />
                                                        </Button>
                                                        {player.is_online && (
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => handleRestartPlayer(player.id)}
                                                                disabled={loading === `restart-${player.id}`}
                                                            >
                                                                <IconRefresh className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => handleDeletePlayer(player.id)}
                                                            disabled={loading === `delete-${player.id}`}
                                                            className="text-red-600 hover:text-red-900"
                                                        >
                                                            <IconTrash className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Empty State */}
                            {players.data.length === 0 && (
                                <div className="text-center py-12">
                                    <IconDevices className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                                        Nenhum player encontrado
                                    </h3>
                                    <p className="text-gray-500 mb-4">
                                        {filters.search || filters.status || filters.group
                                            ? 'Tente ajustar os filtros de busca'
                                            : 'Comece criando seu primeiro player Android'
                                        }
                                    </p>
                                    <Button asChild>
                                        <Link href="/players/create">
                                            <IconPlus className="h-4 w-4 mr-2" />
                                            Criar Primeiro Player
                                        </Link>
                                    </Button>
                                </div>
                            )}

                            {/* Pagination */}
                            {players.data.length > 0 && players.links && (
                                <div className="mt-6 flex items-center justify-between">
                                    <div className="text-sm text-gray-700">
                                        Mostrando {players.from} a {players.to} de {players.total} resultados
                                    </div>
                                    <div className="flex gap-2">
                                        {players.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={cn(
                                                    'px-3 py-1 text-sm rounded',
                                                    link.active
                                                        ? 'bg-indigo-600 text-white'
                                                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300',
                                                    !link.url && 'opacity-50 cursor-not-allowed'
                                                )}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}