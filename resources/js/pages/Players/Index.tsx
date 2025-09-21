import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import ClientLayout from "@/Layouts/ClientLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    IconPlus,
    IconSearch,
    IconDotsVertical,
    IconEye,
    IconEdit,
    IconTrash,
    IconSettings,
    IconRefresh,
    IconDownload,
    IconDeviceGamepad2,
} from "@tabler/icons-react";
import { toast } from "sonner";
import PlayerStatusBadge from "@/components/players/PlayerStatusBadge";

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
}

interface PlayersIndexProps {
    players: Player[];
    stats: {
        total: number;
        online: number;
        offline: number;
        pending_activation: number;
    };
}

export default function Index({ players, stats }: PlayersIndexProps) {
    const [search, setSearch] = useState("");
    const [statusFilter, setStatusFilter] = useState("all");
    const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; player?: Player }>({
        open: false
    });

    const filteredPlayers = players.filter(player => {
        const matchesSearch = player.name.toLowerCase().includes(search.toLowerCase()) ||
            player.location?.toLowerCase().includes(search.toLowerCase());

        const matchesStatus = statusFilter === "all" ||
            (statusFilter === "online" && player.is_online) ||
            (statusFilter === "offline" && !player.is_online);

        return matchesSearch && matchesStatus;
    });

    const handleDelete = (player: Player) => {
        setDeleteDialog({ open: true, player });
    };

    const confirmDelete = () => {
        if (deleteDialog.player) {
            router.delete(route("players.destroy", deleteDialog.player.id), {
                onSuccess: () => {
                    toast.success("Player excluído com sucesso");
                    setDeleteDialog({ open: false });
                },
                onError: () => toast.error("Erro ao excluir player"),
            });
        }
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString("pt-BR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    };

    return (
        <ClientLayout>
            <Head title="Players" />

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold">Players</h1>
                        <p className="text-gray-600">
                            Gerencie os dispositivos players da sua rede
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route("players.create")}>
                            <IconPlus className="h-4 w-4 mr-2" />
                            Novo Player
                        </Link>
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total de Players
                            </CardTitle>
                            <IconDeviceGamepad2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Online
                            </CardTitle>
                            <div className="h-2 w-2 bg-green-500 rounded-full"></div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.online}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Offline
                            </CardTitle>
                            <div className="h-2 w-2 bg-red-500 rounded-full"></div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{stats.offline}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pendente Ativação
                            </CardTitle>
                            <div className="h-2 w-2 bg-yellow-500 rounded-full"></div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">{stats.pending_activation}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col md:flex-row gap-4">
                            <div className="relative flex-1">
                                <IconSearch className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Buscar por nome ou localização..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger className="w-full md:w-48">
                                    <SelectValue placeholder="Filtrar por status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="online">Online</SelectItem>
                                    <SelectItem value="offline">Offline</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button variant="outline" onClick={() => window.location.reload()}>
                                <IconRefresh className="h-4 w-4 mr-2" />
                                Atualizar
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Players Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Players ({filteredPlayers.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nome</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Localização</TableHead>
                                        <TableHead>Código</TableHead>
                                        <TableHead>Playlists</TableHead>
                                        <TableHead>Último Acesso</TableHead>
                                        <TableHead className="text-right">Ações</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredPlayers.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-center h-32">
                                                {search || statusFilter !== "all"
                                                    ? "Nenhum player encontrado com os filtros aplicados"
                                                    : "Nenhum player cadastrado ainda"
                                                }
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        filteredPlayers.map((player) => (
                                            <TableRow key={player.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{player.name}</div>
                                                        {player.description && (
                                                            <div className="text-sm text-gray-500">
                                                                {player.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <PlayerStatusBadge
                                                        isOnline={player.is_online}
                                                        lastSeen={player.last_seen}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    {player.location || "-"}
                                                </TableCell>
                                                <TableCell>
                                                    <code className="text-xs bg-gray-100 p-1 rounded">
                                                        {player.activation_code}
                                                    </code>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {player.playlists_count}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-sm text-gray-500">
                                                    {player.last_seen
                                                        ? formatDate(player.last_seen)
                                                        : "Nunca"
                                                    }
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
                                                                <Link href={route("players.show", player.id)}>
                                                                    <IconEye className="h-4 w-4 mr-2" />
                                                                    Ver Detalhes
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={route("players.edit", player.id)}>
                                                                    <IconEdit className="h-4 w-4 mr-2" />
                                                                    Editar
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem>
                                                                <IconSettings className="h-4 w-4 mr-2" />
                                                                Configurações
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem>
                                                                <IconDownload className="h-4 w-4 mr-2" />
                                                                Download QR
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => handleDelete(player)}
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
                    </CardContent>
                </Card>
            </div>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog({ open })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar Exclusão</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja excluir o player "{deleteDialog.player?.name}"?
                            Esta ação não pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteDialog({ open: false })}
                        >
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Excluir
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ClientLayout>
    );
}