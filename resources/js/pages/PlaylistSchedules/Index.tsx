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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    IconPlus,
    IconDotsVertical,
    IconEdit,
    IconEye,
    IconTrash,
    IconCopy,
    IconPower,
    IconPowerOff,
    IconCalendar,
    IconClock,
} from "@tabler/icons-react";
import { toast } from "sonner";

interface Playlist {
    id: number;
    name: string;
}

interface PlaylistSchedule {
    id: number;
    name: string;
    playlist: Playlist;
    start_date: string | null;
    end_date: string | null;
    start_time: string | null;
    end_time: string | null;
    days_of_week: number[] | null;
    is_active: boolean;
    priority: number;
    formatted_time_range: string;
    formatted_date_range: string;
    formatted_days_of_week: string;
}

interface Props {
    schedules: {
        data: PlaylistSchedule[];
        links: any[];
        meta: any;
    };
    playlists: Playlist[];
    filters: {
        playlist_id?: string;
        status?: string;
        search?: string;
    };
}

export default function Index({ schedules, playlists, filters }: Props) {
    const [search, setSearch] = useState(filters.search || "");
    const [playlistId, setPlaylistId] = useState(filters.playlist_id || "");
    const [status, setStatus] = useState(filters.status || "");

    const handleFilterChange = () => {
        router.get(
            route("playlist-schedules.index"),
            { search, playlist_id: playlistId, status },
            { preserveState: true, replace: true }
        );
    };

    const handleToggle = async (schedule: PlaylistSchedule) => {
        try {
            const response = await fetch(
                route("playlist-schedules.toggle", schedule.id),
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                    },
                }
            );

            if (response.ok) {
                const data = await response.json();
                toast.success(data.message);
                router.reload({ only: ["schedules"] });
            }
        } catch (error) {
            toast.error("Erro ao alterar status do agendamento");
        }
    };

    const handleDelete = (schedule: PlaylistSchedule) => {
        if (
            confirm(
                `Tem certeza que deseja excluir o agendamento "${schedule.name}"?`
            )
        ) {
            router.delete(route("playlist-schedules.destroy", schedule.id), {
                onSuccess: () => toast.success("Agendamento excluído com sucesso"),
                onError: () => toast.error("Erro ao excluir agendamento"),
            });
        }
    };

    const handleDuplicate = (schedule: PlaylistSchedule) => {
        router.post(route("playlist-schedules.duplicate", schedule.id), {}, {
            onSuccess: () => toast.success("Agendamento duplicado com sucesso"),
            onError: () => toast.error("Erro ao duplicar agendamento"),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Agendamentos de Playlists" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">
                            Agendamentos de Playlists
                        </h1>
                        <p className="text-gray-600">
                            Configure horários para ativação automática de playlists
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route("playlist-schedules.create")}>
                            <IconPlus className="h-4 w-4 mr-2" />
                            Novo Agendamento
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-lg shadow space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <Input
                            placeholder="Buscar agendamentos..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                    handleFilterChange();
                                }
                            }}
                        />

                        <Select value={playlistId} onValueChange={setPlaylistId}>
                            <SelectTrigger>
                                <SelectValue placeholder="Todas as playlists" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Todas as playlists</SelectItem>
                                {playlists.map((playlist) => (
                                    <SelectItem key={playlist.id} value={playlist.id.toString()}>
                                        {playlist.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger>
                                <SelectValue placeholder="Todos os status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Todos os status</SelectItem>
                                <SelectItem value="active">Ativos</SelectItem>
                                <SelectItem value="inactive">Inativos</SelectItem>
                            </SelectContent>
                        </Select>

                        <Button onClick={handleFilterChange} className="w-full">
                            Filtrar
                        </Button>
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome</TableHead>
                                <TableHead>Playlist</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Prioridade</TableHead>
                                <TableHead>
                                    <IconCalendar className="h-4 w-4 inline mr-1" />
                                    Período
                                </TableHead>
                                <TableHead>
                                    <IconClock className="h-4 w-4 inline mr-1" />
                                    Horário
                                </TableHead>
                                <TableHead>Dias da Semana</TableHead>
                                <TableHead className="text-right">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {schedules.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-center py-8">
                                        <div className="text-gray-500">
                                            <IconCalendar className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                            <p>Nenhum agendamento encontrado</p>
                                            <Button asChild className="mt-4">
                                                <Link href={route("playlist-schedules.create")}>
                                                    Criar primeiro agendamento
                                                </Link>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                schedules.data.map((schedule) => (
                                    <TableRow key={schedule.id}>
                                        <TableCell className="font-medium">
                                            {schedule.name}
                                        </TableCell>
                                        <TableCell>{schedule.playlist.name}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    schedule.is_active ? "default" : "secondary"
                                                }
                                                className={
                                                    schedule.is_active
                                                        ? "bg-green-600"
                                                        : "bg-gray-400"
                                                }
                                            >
                                                {schedule.is_active ? "Ativo" : "Inativo"}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {schedule.priority}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {schedule.formatted_date_range}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {schedule.formatted_time_range}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {schedule.formatted_days_of_week}
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
                                                        <Link
                                                            href={route(
                                                                "playlist-schedules.show",
                                                                schedule.id
                                                            )}
                                                        >
                                                            <IconEye className="h-4 w-4 mr-2" />
                                                            Ver detalhes
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={route(
                                                                "playlist-schedules.edit",
                                                                schedule.id
                                                            )}
                                                        >
                                                            <IconEdit className="h-4 w-4 mr-2" />
                                                            Editar
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleToggle(schedule)}
                                                    >
                                                        {schedule.is_active ? (
                                                            <>
                                                                <IconPowerOff className="h-4 w-4 mr-2" />
                                                                Desativar
                                                            </>
                                                        ) : (
                                                            <>
                                                                <IconPower className="h-4 w-4 mr-2" />
                                                                Ativar
                                                            </>
                                                        )}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleDuplicate(schedule)}
                                                    >
                                                        <IconCopy className="h-4 w-4 mr-2" />
                                                        Duplicar
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleDelete(schedule)}
                                                        className="text-red-600"
                                                    >
                                                        <IconTrash className="h-4 w-4 mr-2" />
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
                </div>

                {/* Pagination */}
                {schedules.meta.total > schedules.meta.per_page && (
                    <div className="flex justify-center">
                        {schedules.links.map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? "default" : "outline"}
                                size="sm"
                                className="mx-1"
                                onClick={() => {
                                    if (link.url) {
                                        router.visit(link.url);
                                    }
                                }}
                                disabled={!link.url}
                            >
                                <span
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}