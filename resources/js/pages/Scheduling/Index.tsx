import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
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
    IconPlus,
    IconDotsVertical,
    IconEdit,
    IconTrash,
    IconCopy,
    IconPlay,
    IconPause,
    IconSearch,
    IconFilter,
    IconCalendar,
    IconClock,
    IconUsers,
    IconAlertTriangle,
    IconCheck,
} from "@tabler/icons-react";
import { toast } from "sonner";
import PlayerStatusBadge from "@/components/scheduling/PlayerStatusBadge";

interface Player {
    id: number;
    name: string;
    status: 'online' | 'offline' | 'inactive';
}

interface Playlist {
    id: number;
    name: string;
    items_count: number;
}

interface Schedule {
    id: number;
    playlist: Playlist;
    players: Player[];
    priority: number;
    start_date?: string;
    end_date?: string;
    schedule_enabled: boolean;
    schedule_config?: {
        start_time: string;
        end_time: string;
        days_of_week: string[];
        recurrence: string;
    };
    status: 'active' | 'paused' | 'expired';
    created_at: string;
    updated_at: string;
}

interface SchedulingIndexProps {
    schedules: {
        data: Schedule[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
        period?: string;
        sort?: string;
        direction?: string;
    };
    stats: {
        total_schedules: number;
        active_schedules: number;
        paused_schedules: number;
        expired_schedules: number;
    };
}

const DAYS_MAP: { [key: string]: string } = {
    '0': 'Dom',
    '1': 'Seg',
    '2': 'Ter',
    '3': 'Qua',
    '4': 'Qui',
    '5': 'Sex',
    '6': 'Sáb',
};

export default function SchedulingIndex({ schedules, filters, stats }: SchedulingIndexProps) {
    const [search, setSearch] = useState(filters.search || "");
    const [status, setStatus] = useState(filters.status || "");
    const [period, setPeriod] = useState(filters.period || "");
    const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; schedule: Schedule | null }>({
        open: false,
        schedule: null,
    });

    const handleSearch = () => {
        router.get(route('scheduling.index'), {
            search: search || undefined,
            status: status || undefined,
            period: period || undefined,
            sort: filters.sort,
            direction: filters.direction,
        });
    };

    const handleSort = (column: string) => {
        const direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(route('scheduling.index'), {
            search: search || undefined,
            status: status || undefined,
            period: period || undefined,
            sort: column,
            direction,
        });
    };

    const handleStatusToggle = (schedule: Schedule) => {
        const newStatus = schedule.status === 'active' ? 'paused' : 'active';

        router.patch(route('player-playlists.update-status', schedule.id), {
            status: newStatus
        }, {
            onSuccess: () => {
                toast.success(`Agendamento ${newStatus === 'active' ? 'ativado' : 'pausado'} com sucesso`);
            },
            onError: () => {
                toast.error('Erro ao alterar status do agendamento');
            }
        });
    };

    const handleDuplicate = (schedule: Schedule) => {
        router.post(route('player-playlists.duplicate', schedule.id), {}, {
            onSuccess: () => {
                toast.success('Agendamento duplicado com sucesso!');
            },
            onError: () => {
                toast.error('Erro ao duplicar agendamento');
            }
        });
    };

    const handleDelete = () => {
        if (!deleteDialog.schedule) return;

        router.delete(route('player-playlists.destroy', deleteDialog.schedule.id), {
            onSuccess: () => {
                toast.success('Agendamento excluído com sucesso!');
                setDeleteDialog({ open: false, schedule: null });
            },
            onError: () => {
                toast.error('Erro ao excluir agendamento');
            }
        });
    };

    const clearFilters = () => {
        setSearch("");
        setStatus("");
        setPeriod("");
        router.get(route('scheduling.index'));
    };

    const formatDateRange = (startDate?: string, endDate?: string): string => {
        if (!startDate) return 'Indefinido';

        const start = new Date(startDate).toLocaleDateString('pt-BR');
        if (!endDate) return `${start} - Indefinido`;

        const end = new Date(endDate).toLocaleDateString('pt-BR');
        return `${start} - ${end}`;
    };

    const formatScheduleTime = (schedule: Schedule): string => {
        if (!schedule.schedule_enabled || !schedule.schedule_config) {
            return 'Sempre ativo';
        }

        const { start_time, end_time, days_of_week } = schedule.schedule_config;
        const days = days_of_week.map(day => DAYS_MAP[day]).join(', ');
        return `${start_time} - ${end_time} (${days})`;
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <Badge className="bg-green-100 text-green-800">Ativo</Badge>;
            case 'paused':
                return <Badge variant="secondary">Pausado</Badge>;
            case 'expired':
                return <Badge variant="destructive">Expirado</Badge>;
            default:
                return <Badge variant="outline">Desconhecido</Badge>;
        }
    };

    const hasConflicts = (schedule: Schedule): boolean => {
        // This would be determined by backend logic
        // For now, we'll simulate some conflicts
        return schedule.priority > 3 && schedule.players.length > 5;
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Agendamentos de Playlists
                    </h2>
                    <Link href={route('scheduling.assign')}>
                        <Button>
                            <IconPlus className="mr-2 h-4 w-4" />
                            Novo Agendamento
                        </Button>
                    </Link>
                </div>
            }
        >
            <Head title="Agendamentos" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Stats Cards */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center">
                                    <IconCalendar className="h-8 w-8 text-blue-500" />
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Total
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {stats.total_schedules}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center">
                                    <IconCheck className="h-8 w-8 text-green-500" />
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Ativos
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {stats.active_schedules}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center">
                                    <IconPause className="h-8 w-8 text-yellow-500" />
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Pausados
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {stats.paused_schedules}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center">
                                    <IconClock className="h-8 w-8 text-red-500" />
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            Expirados
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {stats.expired_schedules}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filters */}
                    <div className="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="flex-1">
                                <div className="relative">
                                    <IconSearch className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <Input
                                        placeholder="Buscar agendamentos..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                                        className="pl-10"
                                    />
                                </div>
                            </div>

                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger className="w-full sm:w-48">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Ativos</SelectItem>
                                    <SelectItem value="paused">Pausados</SelectItem>
                                    <SelectItem value="expired">Expirados</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value={period} onValueChange={setPeriod}>
                                <SelectTrigger className="w-full sm:w-48">
                                    <SelectValue placeholder="Período" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="today">Hoje</SelectItem>
                                    <SelectItem value="week">Próximos 7 dias</SelectItem>
                                    <SelectItem value="month">Próximos 30 dias</SelectItem>
                                </SelectContent>
                            </Select>

                            <div className="flex gap-2">
                                <Button onClick={handleSearch}>
                                    <IconFilter className="mr-2 h-4 w-4" />
                                    Filtrar
                                </Button>

                                {(search || status || period) && (
                                    <Button variant="outline" onClick={clearFilters}>
                                        Limpar
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Schedules Table */}
                    <div className="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead
                                        className="cursor-pointer"
                                        onClick={() => handleSort('playlist_name')}
                                    >
                                        Playlist
                                        {filters.sort === 'playlist_name' && (
                                            <span className="ml-1">
                                                {filters.direction === 'asc' ? '↑' : '↓'}
                                            </span>
                                        )}
                                    </TableHead>
                                    <TableHead>Players</TableHead>
                                    <TableHead>Período</TableHead>
                                    <TableHead>Horário</TableHead>
                                    <TableHead>Prioridade</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead
                                        className="cursor-pointer"
                                        onClick={() => handleSort('created_at')}
                                    >
                                        Criado em
                                        {filters.sort === 'created_at' && (
                                            <span className="ml-1">
                                                {filters.direction === 'asc' ? '↑' : '↓'}
                                            </span>
                                        )}
                                    </TableHead>
                                    <TableHead className="text-right">Ações</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {schedules.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-center py-8">
                                            <div className="flex flex-col items-center gap-2">
                                                <IconCalendar className="h-12 w-12 text-gray-400" />
                                                <p className="text-gray-500 dark:text-gray-400">
                                                    Nenhum agendamento encontrado
                                                </p>
                                                <Link href={route('scheduling.assign')}>
                                                    <Button size="sm">
                                                        <IconPlus className="mr-2 h-4 w-4" />
                                                        Criar primeiro agendamento
                                                    </Button>
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    schedules.data.map((schedule) => (
                                        <TableRow key={schedule.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div>
                                                        <p className="font-medium">{schedule.playlist.name}</p>
                                                        <p className="text-sm text-gray-500">
                                                            {schedule.playlist.items_count} itens
                                                        </p>
                                                    </div>
                                                    {hasConflicts(schedule) && (
                                                        <IconAlertTriangle className="h-4 w-4 text-yellow-500" title="Possível conflito" />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <IconUsers className="h-4 w-4" />
                                                    <span>{schedule.players.length}</span>
                                                    <div className="flex gap-1">
                                                        {schedule.players.slice(0, 3).map((player) => (
                                                            <PlayerStatusBadge
                                                                key={player.id}
                                                                status={player.status}
                                                                size="sm"
                                                                showIcon={false}
                                                            />
                                                        ))}
                                                        {schedule.players.length > 3 && (
                                                            <Badge variant="outline" className="text-xs">
                                                                +{schedule.players.length - 3}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-sm">
                                                    {formatDateRange(schedule.start_date, schedule.end_date)}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-sm">{formatScheduleTime(schedule)}</p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    Prioridade {schedule.priority}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(schedule.status)}
                                            </TableCell>
                                            <TableCell>
                                                {new Date(schedule.created_at).toLocaleDateString('pt-BR')}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            <IconDotsVertical className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('scheduling.edit', schedule.id)}>
                                                                <IconEdit className="mr-2 h-4 w-4" />
                                                                Editar
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() => handleDuplicate(schedule)}
                                                        >
                                                            <IconCopy className="mr-2 h-4 w-4" />
                                                            Duplicar
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() => handleStatusToggle(schedule)}
                                                        >
                                                            {schedule.status === 'active' ? (
                                                                <>
                                                                    <IconPause className="mr-2 h-4 w-4" />
                                                                    Pausar
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <IconPlay className="mr-2 h-4 w-4" />
                                                                    Ativar
                                                                </>
                                                            )}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            className="text-red-600"
                                                            onClick={() => setDeleteDialog({ open: true, schedule })}
                                                        >
                                                            <IconTrash className="mr-2 h-4 w-4" />
                                                            Excluir
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        {schedules.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-6 py-3">
                                <div className="text-sm text-gray-700 dark:text-gray-300">
                                    Mostrando {((schedules.current_page - 1) * schedules.per_page) + 1} a{' '}
                                    {Math.min(schedules.current_page * schedules.per_page, schedules.total)} de{' '}
                                    {schedules.total} resultados
                                </div>
                                <div className="flex gap-2">
                                    {schedules.current_page > 1 && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(route('scheduling.index'), {
                                                ...filters,
                                                page: schedules.current_page - 1
                                            })}
                                        >
                                            Anterior
                                        </Button>
                                    )}
                                    {schedules.current_page < schedules.last_page && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(route('scheduling.index'), {
                                                ...filters,
                                                page: schedules.current_page + 1
                                            })}
                                        >
                                            Próxima
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog({ open, schedule: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar Exclusão</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir este agendamento? Esta ação não pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteDialog({ open: false, schedule: null })}
                        >
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Excluir
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}