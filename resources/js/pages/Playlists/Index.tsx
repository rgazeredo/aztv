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
} from "@/components/ui/dropdown-menu";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/components/ui/dialog";
import {
    IconPlus,
    IconDotsVertical,
    IconEdit,
    IconTrash,
    IconCopy,
    IconSearch,
    IconFilter,
    IconList,
    IconClock,
    IconPlaylistAdd,
} from "@tabler/icons-react";
import { toast } from "sonner";

interface Playlist {
    id: number;
    name: string;
    description?: string;
    media_count: number;
    total_duration: number;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
}

interface PlaylistsIndexProps {
    playlists: {
        data: Playlist[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
        sort?: string;
        direction?: string;
    };
    stats: {
        total_playlists: number;
        active_playlists: number;
        inactive_playlists: number;
    };
}

export default function PlaylistsIndex({ playlists, filters, stats }: PlaylistsIndexProps) {
    const [search, setSearch] = useState(filters.search || "");
    const [status, setStatus] = useState(filters.status || "");
    const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; playlist: Playlist | null }>({
        open: false,
        playlist: null,
    });

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

    const handleSearch = () => {
        router.get(route('playlists.index'), {
            search: search || undefined,
            status: status || undefined,
            sort: filters.sort,
            direction: filters.direction,
        });
    };

    const handleSort = (column: string) => {
        const direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(route('playlists.index'), {
            search: search || undefined,
            status: status || undefined,
            sort: column,
            direction,
        });
    };

    const handleDuplicate = (playlist: Playlist) => {
        router.post(route('playlists.duplicate', playlist.id), {}, {
            onSuccess: () => {
                toast.success(`Playlist "${playlist.name}" duplicada com sucesso!`);
            },
            onError: () => {
                toast.error('Erro ao duplicar playlist');
            }
        });
    };

    const handleDelete = () => {
        if (!deleteDialog.playlist) return;

        router.delete(route('playlists.destroy', deleteDialog.playlist.id), {
            onSuccess: () => {
                toast.success(`Playlist "${deleteDialog.playlist?.name}" excluída com sucesso!`);
                setDeleteDialog({ open: false, playlist: null });
            },
            onError: () => {
                toast.error('Erro ao excluir playlist');
            }
        });
    };

    const handleStatusToggle = (playlist: Playlist) => {
        const newStatus = playlist.status === 'active' ? 'inactive' : 'active';

        router.patch(route('playlists.update', playlist.id), {
            status: newStatus
        }, {
            onSuccess: () => {
                toast.success(`Status da playlist alterado para ${newStatus === 'active' ? 'ativa' : 'inativa'}`);
            },
            onError: () => {
                toast.error('Erro ao alterar status da playlist');
            }
        });
    };

    const clearFilters = () => {
        setSearch("");
        setStatus("");
        router.get(route('playlists.index'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Playlists
                    </h2>
                    <Link href={route('playlists.create')}>
                        <Button>
                            <IconPlus className="mr-2 h-4 w-4" />
                            Nova Playlist
                        </Button>
                    </Link>
                </div>
            }
        >
            <Head title="Playlists" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Stats Cards */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                            <div className="flex items-center">
                                <IconList className="h-8 w-8 text-blue-500" />
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total de Playlists
                                    </p>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {stats.total_playlists}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                            <div className="flex items-center">
                                <IconPlaylistAdd className="h-8 w-8 text-green-500" />
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Playlists Ativas
                                    </p>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {stats.active_playlists}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                            <div className="flex items-center">
                                <IconClock className="h-8 w-8 text-orange-500" />
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Playlists Inativas
                                    </p>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {stats.inactive_playlists}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="flex-1">
                                <div className="relative">
                                    <IconSearch className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <Input
                                        placeholder="Buscar playlists..."
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
                                    <SelectItem value="active">Ativas</SelectItem>
                                    <SelectItem value="inactive">Inativas</SelectItem>
                                </SelectContent>
                            </Select>

                            <div className="flex gap-2">
                                <Button onClick={handleSearch}>
                                    <IconFilter className="mr-2 h-4 w-4" />
                                    Filtrar
                                </Button>

                                {(search || status) && (
                                    <Button variant="outline" onClick={clearFilters}>
                                        Limpar
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Playlists Table */}
                    <div className="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead
                                        className="cursor-pointer"
                                        onClick={() => handleSort('name')}
                                    >
                                        Nome
                                        {filters.sort === 'name' && (
                                            <span className="ml-1">
                                                {filters.direction === 'asc' ? '↑' : '↓'}
                                            </span>
                                        )}
                                    </TableHead>
                                    <TableHead>Descrição</TableHead>
                                    <TableHead
                                        className="cursor-pointer"
                                        onClick={() => handleSort('media_count')}
                                    >
                                        Qtd. Mídias
                                        {filters.sort === 'media_count' && (
                                            <span className="ml-1">
                                                {filters.direction === 'asc' ? '↑' : '↓'}
                                            </span>
                                        )}
                                    </TableHead>
                                    <TableHead>Duração Total</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead
                                        className="cursor-pointer"
                                        onClick={() => handleSort('created_at')}
                                    >
                                        Criada em
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
                                {playlists.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center py-8">
                                            <div className="flex flex-col items-center gap-2">
                                                <IconList className="h-12 w-12 text-gray-400" />
                                                <p className="text-gray-500 dark:text-gray-400">
                                                    Nenhuma playlist encontrada
                                                </p>
                                                <Link href={route('playlists.create')}>
                                                    <Button size="sm">
                                                        <IconPlus className="mr-2 h-4 w-4" />
                                                        Criar primeira playlist
                                                    </Button>
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    playlists.data.map((playlist) => (
                                        <TableRow key={playlist.id}>
                                            <TableCell className="font-medium">
                                                {playlist.name}
                                            </TableCell>
                                            <TableCell>
                                                <p className="max-w-xs truncate text-gray-600 dark:text-gray-400">
                                                    {playlist.description || '-'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {playlist.media_count} itens
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {formatDuration(playlist.total_duration)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={playlist.status === 'active' ? 'default' : 'secondary'}
                                                    className={playlist.status === 'active' ? 'bg-green-100 text-green-800' : ''}
                                                >
                                                    {playlist.status === 'active' ? 'Ativa' : 'Inativa'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(playlist.created_at).toLocaleDateString('pt-BR')}
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
                                                            <Link href={route('playlists.edit', playlist.id)}>
                                                                <IconEdit className="mr-2 h-4 w-4" />
                                                                Editar
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() => handleDuplicate(playlist)}
                                                        >
                                                            <IconCopy className="mr-2 h-4 w-4" />
                                                            Duplicar
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() => handleStatusToggle(playlist)}
                                                        >
                                                            <IconClock className="mr-2 h-4 w-4" />
                                                            {playlist.status === 'active' ? 'Desativar' : 'Ativar'}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            className="text-red-600"
                                                            onClick={() => setDeleteDialog({ open: true, playlist })}
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
                        {playlists.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-6 py-3">
                                <div className="text-sm text-gray-700 dark:text-gray-300">
                                    Mostrando {((playlists.current_page - 1) * playlists.per_page) + 1} a{' '}
                                    {Math.min(playlists.current_page * playlists.per_page, playlists.total)} de{' '}
                                    {playlists.total} resultados
                                </div>
                                <div className="flex gap-2">
                                    {playlists.current_page > 1 && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(route('playlists.index'), {
                                                ...filters,
                                                page: playlists.current_page - 1
                                            })}
                                        >
                                            Anterior
                                        </Button>
                                    )}
                                    {playlists.current_page < playlists.last_page && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(route('playlists.index'), {
                                                ...filters,
                                                page: playlists.current_page + 1
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
            <Dialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog({ open, playlist: null })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar Exclusão</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir a playlist "{deleteDialog.playlist?.name}"?
                            Esta ação não pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteDialog({ open: false, playlist: null })}
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