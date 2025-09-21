import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { router } from "@inertiajs/react";
import {
    IconDotsVertical,
    IconEdit,
    IconEye,
    IconPower,
    IconRefresh,
    IconTrash,
} from "@tabler/icons-react";

interface Player {
    id: number;
    name: string;
    is_online: boolean;
    last_seen: string | null;
}

interface PlayerActionsProps {
    player: Player;
    onRestart?: (playerId: number) => void;
    onShutdown?: (playerId: number) => void;
    onDelete?: (playerId: number) => void;
}

export default function PlayerActions({
    player,
    onRestart,
    onShutdown,
    onDelete,
}: PlayerActionsProps) {
    const handleView = () => {
        router.visit(`/players/${player.id}`);
    };

    const handleEdit = () => {
        router.visit(`/players/${player.id}/edit`);
    };

    const handleRestart = () => {
        if (onRestart) {
            onRestart(player.id);
        }
    };

    const handleShutdown = () => {
        if (onShutdown) {
            onShutdown(player.id);
        }
    };

    const handleDelete = () => {
        if (onDelete && confirm(`Tem certeza que deseja excluir o player "${player.name}"?`)) {
            onDelete(player.id);
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-8 w-8 p-0">
                    <span className="sr-only">Abrir menu</span>
                    <IconDotsVertical className="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={handleView}>
                    <IconEye className="mr-2 h-4 w-4" />
                    Ver detalhes
                </DropdownMenuItem>
                <DropdownMenuItem onClick={handleEdit}>
                    <IconEdit className="mr-2 h-4 w-4" />
                    Editar
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                {player.is_online && (
                    <>
                        <DropdownMenuItem onClick={handleRestart}>
                            <IconRefresh className="mr-2 h-4 w-4" />
                            Reiniciar
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={handleShutdown}>
                            <IconPower className="mr-2 h-4 w-4" />
                            Desligar
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                    </>
                )}
                <DropdownMenuItem onClick={handleDelete} className="text-red-600">
                    <IconTrash className="mr-2 h-4 w-4" />
                    Excluir
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}