import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import PlayerStatusBadge from "./PlayerStatusBadge";
import PlayerActions from "./PlayerActions";

interface Player {
    id: number;
    name: string;
    mac_address: string;
    ip_address: string | null;
    group: string | null;
    is_online: boolean;
    last_seen: string | null;
    created_at: string;
}

interface PlayerTableProps {
    players: Player[];
    selectedPlayers: number[];
    onSelectPlayer: (playerId: number) => void;
    onSelectAllPlayers: (selected: boolean) => void;
    onRestart?: (playerId: number) => void;
    onShutdown?: (playerId: number) => void;
    onDelete?: (playerId: number) => void;
}

export default function PlayerTable({
    players,
    selectedPlayers,
    onSelectPlayer,
    onSelectAllPlayers,
    onRestart,
    onShutdown,
    onDelete,
}: PlayerTableProps) {
    const formatDate = (date: string) => {
        return new Date(date).toLocaleString("pt-BR");
    };

    const allSelected = players.length > 0 && selectedPlayers.length === players.length;
    const someSelected = selectedPlayers.length > 0;

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-12">
                            <Checkbox
                                checked={allSelected}
                                onCheckedChange={onSelectAllPlayers}
                                aria-label="Selecionar todos"
                            />
                        </TableHead>
                        <TableHead>Nome</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>MAC Address</TableHead>
                        <TableHead>IP Address</TableHead>
                        <TableHead>Grupo</TableHead>
                        <TableHead>Último acesso</TableHead>
                        <TableHead className="text-right">Ações</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {players.length === 0 ? (
                        <TableRow>
                            <TableCell colSpan={8} className="h-24 text-center">
                                Nenhum player encontrado.
                            </TableCell>
                        </TableRow>
                    ) : (
                        players.map((player) => (
                            <TableRow key={player.id}>
                                <TableCell>
                                    <Checkbox
                                        checked={selectedPlayers.includes(player.id)}
                                        onCheckedChange={() => onSelectPlayer(player.id)}
                                        aria-label={`Selecionar ${player.name}`}
                                    />
                                </TableCell>
                                <TableCell className="font-medium">
                                    {player.name}
                                </TableCell>
                                <TableCell>
                                    <PlayerStatusBadge player={player} />
                                </TableCell>
                                <TableCell className="font-mono text-sm">
                                    {player.mac_address}
                                </TableCell>
                                <TableCell className="font-mono text-sm">
                                    {player.ip_address || "-"}
                                </TableCell>
                                <TableCell>
                                    {player.group || "-"}
                                </TableCell>
                                <TableCell>
                                    {player.last_seen ? formatDate(player.last_seen) : "Nunca"}
                                </TableCell>
                                <TableCell className="text-right">
                                    <PlayerActions
                                        player={player}
                                        onRestart={onRestart}
                                        onShutdown={onShutdown}
                                        onDelete={onDelete}
                                    />
                                </TableCell>
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
        </div>
    );
}