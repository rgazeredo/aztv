import { useState, useEffect } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Separator } from "@/components/ui/separator";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
    IconSearch,
    IconFilter,
    IconUsers,
    IconPlaylistAdd,
    IconCalendar,
    IconClock,
    IconInfoCircle,
    IconAlertTriangle,
    IconCheck,
} from "@tabler/icons-react";
import { toast } from "sonner";
import PlayerStatusBadge from "@/components/scheduling/PlayerStatusBadge";
import TimeRangePicker from "@/components/scheduling/TimeRangePicker";
import PlaylistPreview from "@/components/scheduling/PlaylistPreview";

interface Player {
    id: number;
    name: string;
    alias?: string;
    location?: string;
    status: 'online' | 'offline' | 'inactive';
    last_seen?: string;
}

interface Playlist {
    id: number;
    name: string;
    description?: string;
    status: 'active' | 'inactive';
    loop_enabled: boolean;
    items: any[];
}

interface AssignmentFormData {
    player_ids: number[];
    playlist_id: string;
    priority: number;
    start_date?: string;
    end_date?: string;
    schedule_enabled: boolean;
    schedule_config: {
        start_time: string;
        end_time: string;
        days_of_week: string[];
        recurrence: 'daily' | 'weekly' | 'monthly';
    };
}

interface PlayerPlaylistAssignmentProps {
    players: Player[];
    playlists: Playlist[];
}

const DAYS_OF_WEEK = [
    { value: '1', label: 'Segunda-feira', short: 'Seg' },
    { value: '2', label: 'Terça-feira', short: 'Ter' },
    { value: '3', label: 'Quarta-feira', short: 'Qua' },
    { value: '4', label: 'Quinta-feira', short: 'Qui' },
    { value: '5', label: 'Sexta-feira', short: 'Sex' },
    { value: '6', label: 'Sábado', short: 'Sáb' },
    { value: '0', label: 'Domingo', short: 'Dom' },
];

export default function PlayerPlaylistAssignment({ players, playlists }: PlayerPlaylistAssignmentProps) {
    const [searchTerm, setSearchTerm] = useState("");
    const [statusFilter, setStatusFilter] = useState<string>("all");
    const [selectedPlaylist, setSelectedPlaylist] = useState<Playlist | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm<AssignmentFormData>({
        player_ids: [],
        playlist_id: '',
        priority: 1,
        start_date: '',
        end_date: '',
        schedule_enabled: false,
        schedule_config: {
            start_time: '09:00',
            end_time: '17:00',
            days_of_week: ['1', '2', '3', '4', '5'], // Monday to Friday
            recurrence: 'weekly',
        },
    });

    // Filter players based on search and status
    const filteredPlayers = players.filter(player => {
        const matchesSearch = !searchTerm ||
            player.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (player.alias && player.alias.toLowerCase().includes(searchTerm.toLowerCase())) ||
            (player.location && player.location.toLowerCase().includes(searchTerm.toLowerCase()));

        const matchesStatus = statusFilter === 'all' || player.status === statusFilter;

        return matchesSearch && matchesStatus;
    });

    // Update selected playlist when playlist_id changes
    useEffect(() => {
        const playlist = playlists.find(p => p.id.toString() === data.playlist_id);
        setSelectedPlaylist(playlist || null);
    }, [data.playlist_id, playlists]);

    const handlePlayerToggle = (playerId: number) => {
        const newPlayerIds = data.player_ids.includes(playerId)
            ? data.player_ids.filter(id => id !== playerId)
            : [...data.player_ids, playerId];

        setData('player_ids', newPlayerIds);
    };

    const handleSelectAllPlayers = () => {
        const allPlayerIds = filteredPlayers.map(p => p.id);
        const allSelected = allPlayerIds.every(id => data.player_ids.includes(id));

        if (allSelected) {
            setData('player_ids', []);
        } else {
            setData('player_ids', [...new Set([...data.player_ids, ...allPlayerIds])]);
        }
    };

    const handleDayToggle = (day: string) => {
        const newDays = data.schedule_config.days_of_week.includes(day)
            ? data.schedule_config.days_of_week.filter(d => d !== day)
            : [...data.schedule_config.days_of_week, day];

        setData('schedule_config', {
            ...data.schedule_config,
            days_of_week: newDays,
        });
    };

    const handleTimeRangeChange = (timeRange: { start: string; end: string }) => {
        setData('schedule_config', {
            ...data.schedule_config,
            start_time: timeRange.start,
            end_time: timeRange.end,
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (data.player_ids.length === 0) {
            toast.error('Selecione pelo menos um player');
            return;
        }

        if (!data.playlist_id) {
            toast.error('Selecione uma playlist');
            return;
        }

        post(route('player-playlists.store'), {
            onSuccess: () => {
                toast.success('Playlist atribuída aos players com sucesso!');
                reset();
                setSelectedPlaylist(null);
            },
            onError: () => {
                toast.error('Erro ao atribuir playlist. Verifique os dados e tente novamente.');
            }
        });
    };

    const selectedPlayersCount = data.player_ids.length;
    const onlinePlayersCount = filteredPlayers.filter(p => p.status === 'online').length;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Atribuir Playlist aos Players
                    </h2>
                    <Badge variant="outline">
                        {selectedPlayersCount} players selecionados
                    </Badge>
                </div>
            }
        >
            <Head title="Atribuir Playlist aos Players" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Left Column - Player Selection */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconUsers className="h-5 w-5" />
                                        Selecionar Players
                                        <Badge variant="secondary">{onlinePlayersCount} online</Badge>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Search and Filters */}
                                    <div className="space-y-3">
                                        <div className="relative">
                                            <IconSearch className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                            <Input
                                                placeholder="Buscar players..."
                                                value={searchTerm}
                                                onChange={(e) => setSearchTerm(e.target.value)}
                                                className="pl-10"
                                            />
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                                <SelectTrigger className="w-40">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">Todos</SelectItem>
                                                    <SelectItem value="online">Online</SelectItem>
                                                    <SelectItem value="offline">Offline</SelectItem>
                                                    <SelectItem value="inactive">Inativos</SelectItem>
                                                </SelectContent>
                                            </Select>

                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleSelectAllPlayers}
                                            >
                                                {filteredPlayers.every(p => data.player_ids.includes(p.id))
                                                    ? 'Desmarcar Todos'
                                                    : 'Selecionar Todos'
                                                }
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Players List */}
                                    <div className="max-h-96 overflow-y-auto space-y-2">
                                        {filteredPlayers.length === 0 ? (
                                            <div className="text-center py-8 text-gray-500 dark:text-gray-400">
                                                <IconUsers className="h-8 w-8 mx-auto mb-2" />
                                                <p className="text-sm">Nenhum player encontrado</p>
                                            </div>
                                        ) : (
                                            filteredPlayers.map((player) => (
                                                <div
                                                    key={player.id}
                                                    className={`p-3 border rounded-lg cursor-pointer transition-colors ${
                                                        data.player_ids.includes(player.id)
                                                            ? 'bg-blue-50 border-blue-200 dark:bg-blue-950 dark:border-blue-800'
                                                            : 'hover:bg-gray-50 dark:hover:bg-gray-900'
                                                    }`}
                                                    onClick={() => handlePlayerToggle(player.id)}
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <Checkbox
                                                            checked={data.player_ids.includes(player.id)}
                                                            onChange={() => handlePlayerToggle(player.id)}
                                                        />
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-2">
                                                                <h4 className="font-medium">{player.name}</h4>
                                                                <PlayerStatusBadge
                                                                    status={player.status}
                                                                    size="sm"
                                                                />
                                                            </div>
                                                            {(player.alias || player.location) && (
                                                                <p className="text-sm text-gray-500 mt-1">
                                                                    {[player.alias, player.location].filter(Boolean).join(' • ')}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Right Column - Playlist and Schedule Configuration */}
                            <div className="space-y-6">
                                {/* Playlist Selection */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <IconPlaylistAdd className="h-5 w-5" />
                                            Configuração da Playlist
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="playlist">Playlist *</Label>
                                            <Select
                                                value={data.playlist_id}
                                                onValueChange={(value) => setData('playlist_id', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Selecione uma playlist" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {playlists.filter(p => p.status === 'active').map((playlist) => (
                                                        <SelectItem key={playlist.id} value={playlist.id.toString()}>
                                                            {playlist.name} ({playlist.items.length} itens)
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.playlist_id && (
                                                <p className="text-sm text-red-600">{errors.playlist_id}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="priority">Prioridade</Label>
                                            <Select
                                                value={data.priority.toString()}
                                                onValueChange={(value) => setData('priority', parseInt(value))}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="1">1 - Mais Alta</SelectItem>
                                                    <SelectItem value="2">2 - Alta</SelectItem>
                                                    <SelectItem value="3">3 - Média</SelectItem>
                                                    <SelectItem value="4">4 - Baixa</SelectItem>
                                                    <SelectItem value="5">5 - Mais Baixa</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Schedule Configuration */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <IconCalendar className="h-5 w-5" />
                                            Agendamento Avançado
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {/* Enable Scheduling Toggle */}
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="schedule-enabled">Ativar Agendamento</Label>
                                                <p className="text-sm text-gray-500">
                                                    Configure horários específicos para reprodução
                                                </p>
                                            </div>
                                            <Switch
                                                id="schedule-enabled"
                                                checked={data.schedule_enabled}
                                                onCheckedChange={(checked) => setData('schedule_enabled', checked)}
                                            />
                                        </div>

                                        {data.schedule_enabled && (
                                            <>
                                                <Separator />

                                                {/* Date Range */}
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="start_date">Data de Início</Label>
                                                        <Input
                                                            id="start_date"
                                                            type="date"
                                                            value={data.start_date}
                                                            onChange={(e) => setData('start_date', e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="end_date">Data de Fim (Opcional)</Label>
                                                        <Input
                                                            id="end_date"
                                                            type="date"
                                                            value={data.end_date}
                                                            onChange={(e) => setData('end_date', e.target.value)}
                                                        />
                                                    </div>
                                                </div>

                                                {/* Time Range */}
                                                <TimeRangePicker
                                                    value={{
                                                        start: data.schedule_config.start_time,
                                                        end: data.schedule_config.end_time,
                                                    }}
                                                    onChange={handleTimeRangeChange}
                                                    label="Horário de Funcionamento"
                                                />

                                                {/* Days of Week */}
                                                <div className="space-y-3">
                                                    <Label>Dias da Semana</Label>
                                                    <div className="grid grid-cols-4 gap-2">
                                                        {DAYS_OF_WEEK.map((day) => (
                                                            <div
                                                                key={day.value}
                                                                className={`p-2 border rounded-lg cursor-pointer text-center text-sm transition-colors ${
                                                                    data.schedule_config.days_of_week.includes(day.value)
                                                                        ? 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-950 dark:border-blue-800 dark:text-blue-300'
                                                                        : 'hover:bg-gray-50 dark:hover:bg-gray-900'
                                                                }`}
                                                                onClick={() => handleDayToggle(day.value)}
                                                            >
                                                                <div className="font-medium">{day.short}</div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Recurrence */}
                                                <div className="space-y-3">
                                                    <Label>Recorrência</Label>
                                                    <RadioGroup
                                                        value={data.schedule_config.recurrence}
                                                        onValueChange={(value: 'daily' | 'weekly' | 'monthly') =>
                                                            setData('schedule_config', {
                                                                ...data.schedule_config,
                                                                recurrence: value,
                                                            })
                                                        }
                                                    >
                                                        <div className="flex items-center space-x-2">
                                                            <RadioGroupItem value="daily" id="daily" />
                                                            <Label htmlFor="daily">Diariamente</Label>
                                                        </div>
                                                        <div className="flex items-center space-x-2">
                                                            <RadioGroupItem value="weekly" id="weekly" />
                                                            <Label htmlFor="weekly">Semanalmente</Label>
                                                        </div>
                                                        <div className="flex items-center space-x-2">
                                                            <RadioGroupItem value="monthly" id="monthly" />
                                                            <Label htmlFor="monthly">Mensalmente</Label>
                                                        </div>
                                                    </RadioGroup>
                                                </div>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        {/* Playlist Preview */}
                        {selectedPlaylist && (
                            <PlaylistPreview playlist={selectedPlaylist} compact />
                        )}

                        {/* Summary and Actions */}
                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="font-medium">Resumo da Atribuição</h3>
                                        <div className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            <p>
                                                <strong>{selectedPlayersCount}</strong> players selecionados
                                                {selectedPlaylist && (
                                                    <span> • Playlist: <strong>{selectedPlaylist.name}</strong></span>
                                                )}
                                                {data.schedule_enabled && (
                                                    <span> • Agendamento: <strong>Ativado</strong></span>
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                reset();
                                                setSelectedPlaylist(null);
                                            }}
                                        >
                                            Limpar
                                        </Button>

                                        <Button
                                            type="submit"
                                            disabled={processing || selectedPlayersCount === 0 || !data.playlist_id}
                                        >
                                            {processing ? (
                                                <>
                                                    <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                    Atribuindo...
                                                </>
                                            ) : (
                                                <>
                                                    <IconCheck className="mr-2 h-4 w-4" />
                                                    Atribuir Playlist
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}