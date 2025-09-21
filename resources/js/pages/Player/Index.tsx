import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Monitor, Plus, Search, Filter, Eye, Edit, FileText, Users, Wifi, WifiOff, Trash2 } from 'lucide-react';

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
}

interface Stats {
    total_players: number;
    online_players: number;
    offline_players: number;
}

interface Filters {
    search?: string;
    status?: string;
    group?: string;
    sort?: string;
    direction?: string;
}

interface Props {
    players: {
        data: Player[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: Filters;
    groups: string[];
    stats: Stats;
    performance_data?: any;
}

export default function Index({ players, filters, groups, stats, performance_data }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
    const [selectedGroup, setSelectedGroup] = useState(filters.group || 'all');
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [playerToDelete, setPlayerToDelete] = useState<Player | null>(null);

    const { delete: destroy, processing } = useForm();

    const handleDeleteClick = (player: Player) => {
        setPlayerToDelete(player);
        setShowDeleteDialog(true);
    };

    const handleDelete = () => {
        if (playerToDelete) {
            destroy(`/players/${playerToDelete.id}`);
            setShowDeleteDialog(false);
            setPlayerToDelete(null);
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Players',
            href: '/players',
        },
    ];

    const getStatusBadge = (player: Player) => {
        return (
            <Badge variant={player.is_online ? "default" : "secondary"} className="flex items-center gap-1">
                {player.is_online ? <Wifi className="h-3 w-3" /> : <WifiOff className="h-3 w-3" />}
                {player.is_online ? 'Online' : 'Offline'}
            </Badge>
        );
    };

    const formatLastSeen = (lastSeen?: string) => {
        if (!lastSeen) return 'Nunca';
        return new Date(lastSeen).toLocaleString('pt-BR');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Players" />
            <div className="flex-1 space-y-4 p-4 pt-6">

            {/* Header */}
            <div className="flex items-center justify-between space-y-2">
                <div>
                    <h2 className="text-3xl font-bold tracking-tight">Players</h2>
                    <p className="text-muted-foreground">
                        Gerencie todos os players da sua rede
                    </p>
                </div>
                <div className="flex items-center space-x-2">
                    <Button asChild>
                        <Link href="/players/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Novo Player
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Stats Cards */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Total de Players</CardTitle>
                        <Monitor className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stats.total_players}</div>
                        <p className="text-xs text-muted-foreground">dispositivos registrados</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Players Online</CardTitle>
                        <Wifi className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-green-600">{stats.online_players}</div>
                        <p className="text-xs text-muted-foreground">conectados agora</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Players Offline</CardTitle>
                        <WifiOff className="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-red-600">{stats.offline_players}</div>
                        <p className="text-xs text-muted-foreground">desconectados</p>
                    </CardContent>
                </Card>
            </div>

            {/* Filters */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Filter className="h-5 w-5" />
                        Filtros
                    </CardTitle>
                    <CardDescription>
                        Use os filtros abaixo para encontrar players específicos
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-col space-y-4 md:flex-row md:space-y-0 md:space-x-4">
                        <div className="flex-1">
                            <div className="relative">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar por nome, alias, IP, localização..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="online">Online</SelectItem>
                                <SelectItem value="offline">Offline</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={selectedGroup} onValueChange={setSelectedGroup}>
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Grupo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos os grupos</SelectItem>
                                {groups.map((group) => (
                                    <SelectItem key={group} value={group}>
                                        {group}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            {/* Players Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Lista de Players</CardTitle>
                    <CardDescription>
                        Todos os players cadastrados na sua rede
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {players.data.length > 0 ? (
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Player</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Localização</TableHead>
                                        <TableHead>IP</TableHead>
                                        <TableHead>Última Conexão</TableHead>
                                        <TableHead>Playlists</TableHead>
                                        <TableHead>Ações</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {players.data.map((player) => (
                                        <TableRow key={player.id}>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <div className="font-medium">{player.name}</div>
                                                    {player.alias && (
                                                        <div className="text-sm text-muted-foreground">
                                                            {player.alias}
                                                        </div>
                                                    )}
                                                    {player.group && (
                                                        <Badge variant="outline" className="mt-1 w-fit">
                                                            <Users className="mr-1 h-3 w-3" />
                                                            {player.group}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(player)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {player.location || '-'}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {player.ip_address || '-'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatLastSeen(player.last_seen)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {player.playlists_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center space-x-2">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/players/${player.id}`}>
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/players/${player.id}/edit`}>
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/players/${player.id}/logs`}>
                                                            <FileText className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDeleteClick(player)}
                                                        className="text-destructive hover:text-destructive"
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-8 text-center">
                            <Monitor className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold">Nenhum player encontrado</h3>
                            <p className="text-muted-foreground mb-4">
                                Comece criando seu primeiro player
                            </p>
                            <Button asChild>
                                <Link href="/players/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Criar Primeiro Player
                                </Link>
                            </Button>
                        </div>
                    )}
                </CardContent>

                {/* Pagination */}
                {players.last_page > 1 && (
                    <div className="flex items-center justify-between px-6 py-4 border-t">
                        <div className="text-sm text-muted-foreground">
                            Mostrando {((players.current_page - 1) * players.per_page) + 1} a{' '}
                            {Math.min(players.current_page * players.per_page, players.total)} de{' '}
                            {players.total} resultados
                        </div>
                        <div className="flex items-center space-x-2">
                            {players.current_page > 1 ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/players?page=${players.current_page - 1}`}>
                                        Anterior
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Anterior
                                </Button>
                            )}
                            <Button variant="default" size="sm" disabled>
                                {players.current_page}
                            </Button>
                            {players.current_page < players.last_page ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/players?page=${players.current_page + 1}`}>
                                        Próximo
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Próximo
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </Card>
            </div>

            {/* Delete Confirmation Dialog */}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar Exclusão</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir o player{' '}
                            <strong>"{playerToDelete?.name}"</strong>?
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