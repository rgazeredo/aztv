import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    IconCalendar,
    IconClock,
    IconRepeat,
    IconUsers,
    IconCheck,
    IconAlertTriangle,
} from "@tabler/icons-react";

interface ScheduleData {
    start_date?: string;
    end_date?: string;
    schedule_enabled: boolean;
    schedule_config?: {
        start_time: string;
        end_time: string;
        days_of_week: string[];
        recurrence: 'daily' | 'weekly' | 'monthly';
    };
    player_count: number;
    playlist_name?: string;
}

interface SchedulePreviewProps {
    schedule: ScheduleData;
    showTitle?: boolean;
}

const DAYS_MAP: { [key: string]: string } = {
    '0': 'Domingo',
    '1': 'Segunda',
    '2': 'Terça',
    '3': 'Quarta',
    '4': 'Quinta',
    '5': 'Sexta',
    '6': 'Sábado',
};

const DAYS_SHORT: { [key: string]: string } = {
    '0': 'Dom',
    '1': 'Seg',
    '2': 'Ter',
    '3': 'Qua',
    '4': 'Qui',
    '5': 'Sex',
    '6': 'Sáb',
};

export default function SchedulePreview({ schedule, showTitle = true }: SchedulePreviewProps) {
    const formatDate = (dateString?: string) => {
        if (!dateString) return null;
        return new Date(dateString).toLocaleDateString('pt-BR');
    };

    const formatDateRange = () => {
        const start = formatDate(schedule.start_date);
        const end = formatDate(schedule.end_date);

        if (!start) return 'Data não definida';
        if (!end) return `A partir de ${start}`;
        return `${start} até ${end}`;
    };

    const formatDaysOfWeek = (days: string[]) => {
        if (days.length === 0) return 'Nenhum dia selecionado';
        if (days.length === 7) return 'Todos os dias';

        const weekdays = ['1', '2', '3', '4', '5'];
        const weekends = ['0', '6'];

        if (weekdays.every(day => days.includes(day)) && !weekends.some(day => days.includes(day))) {
            return 'Dias úteis (Seg-Sex)';
        }

        if (weekends.every(day => days.includes(day)) && !weekdays.some(day => days.includes(day))) {
            return 'Fins de semana (Sáb-Dom)';
        }

        return days.map(day => DAYS_SHORT[day]).join(', ');
    };

    const getRecurrenceLabel = (recurrence: string) => {
        switch (recurrence) {
            case 'daily': return 'Diariamente';
            case 'weekly': return 'Semanalmente';
            case 'monthly': return 'Mensalmente';
            default: return 'Personalizado';
        }
    };

    const calculateDuration = () => {
        if (!schedule.schedule_config) return null;

        try {
            const start = new Date(`2000-01-01 ${schedule.schedule_config.start_time}`);
            const end = new Date(`2000-01-01 ${schedule.schedule_config.end_time}`);

            const diffMs = end.getTime() - start.getTime();
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

            if (diffHours > 0) {
                return `${diffHours}h ${diffMinutes}m por dia`;
            } else {
                return `${diffMinutes}m por dia`;
            }
        } catch {
            return null;
        }
    };

    const hasWarnings = () => {
        const warnings = [];

        if (!schedule.schedule_enabled) {
            warnings.push('Sempre ativo (sem agendamento)');
        }

        if (schedule.schedule_config && schedule.schedule_config.days_of_week.length === 0) {
            warnings.push('Nenhum dia da semana selecionado');
        }

        if (schedule.player_count === 0) {
            warnings.push('Nenhum player selecionado');
        }

        return warnings;
    };

    const warnings = hasWarnings();
    const duration = calculateDuration();

    return (
        <Card>
            {showTitle && (
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconCalendar className="h-5 w-5" />
                        Preview do Agendamento
                    </CardTitle>
                </CardHeader>
            )}
            <CardContent className="space-y-4">
                {/* Warnings */}
                {warnings.length > 0 && (
                    <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 dark:bg-yellow-950 dark:border-yellow-800">
                        <div className="flex items-start gap-2">
                            <IconAlertTriangle className="h-4 w-4 text-yellow-600 dark:text-yellow-400 mt-0.5" />
                            <div>
                                <h4 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    Atenção
                                </h4>
                                <ul className="mt-1 text-sm text-yellow-700 dark:text-yellow-300 list-disc list-inside">
                                    {warnings.map((warning, index) => (
                                        <li key={index}>{warning}</li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                )}

                {/* Basic Info */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <IconUsers className="h-6 w-6 mx-auto mb-1 text-blue-500" />
                        <p className="text-sm font-medium">{schedule.player_count}</p>
                        <p className="text-xs text-gray-500">Players</p>
                    </div>

                    <div className="text-center p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        <IconCheck className="h-6 w-6 mx-auto mb-1 text-green-500" />
                        <p className="text-sm font-medium">
                            {schedule.schedule_enabled ? 'Agendado' : 'Sempre Ativo'}
                        </p>
                        <p className="text-xs text-gray-500">Status</p>
                    </div>
                </div>

                {/* Playlist Info */}
                {schedule.playlist_name && (
                    <>
                        <Separator />
                        <div>
                            <h4 className="text-sm font-medium mb-2">Playlist</h4>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {schedule.playlist_name}
                            </p>
                        </div>
                    </>
                )}

                {/* Date Range */}
                <Separator />
                <div>
                    <h4 className="text-sm font-medium mb-2 flex items-center gap-2">
                        <IconCalendar className="h-4 w-4" />
                        Período
                    </h4>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        {formatDateRange()}
                    </p>
                </div>

                {/* Schedule Details */}
                {schedule.schedule_enabled && schedule.schedule_config && (
                    <>
                        <Separator />
                        <div className="space-y-3">
                            <h4 className="text-sm font-medium flex items-center gap-2">
                                <IconClock className="h-4 w-4" />
                                Configuração de Horário
                            </h4>

                            {/* Time Range */}
                            <div className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div>
                                    <p className="text-sm font-medium">
                                        {schedule.schedule_config.start_time} - {schedule.schedule_config.end_time}
                                    </p>
                                    {duration && (
                                        <p className="text-xs text-gray-500 mt-1">{duration}</p>
                                    )}
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    Horário
                                </Badge>
                            </div>

                            {/* Days of Week */}
                            <div className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div>
                                    <p className="text-sm font-medium">
                                        {formatDaysOfWeek(schedule.schedule_config.days_of_week)}
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        {schedule.schedule_config.days_of_week.length} dia(s) selecionado(s)
                                    </p>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    Dias
                                </Badge>
                            </div>

                            {/* Recurrence */}
                            <div className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div>
                                    <p className="text-sm font-medium flex items-center gap-2">
                                        <IconRepeat className="h-4 w-4" />
                                        {getRecurrenceLabel(schedule.schedule_config.recurrence)}
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        Padrão de repetição
                                    </p>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    Recorrência
                                </Badge>
                            </div>
                        </div>
                    </>
                )}

                {/* Summary */}
                <Separator />
                <div className="rounded-lg bg-blue-50 border border-blue-200 p-3 dark:bg-blue-950 dark:border-blue-800">
                    <h4 className="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">
                        Resumo
                    </h4>
                    <div className="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <p>
                            • <strong>{schedule.player_count}</strong> player(s) selecionado(s)
                        </p>
                        {schedule.playlist_name && (
                            <p>
                                • Playlist: <strong>{schedule.playlist_name}</strong>
                            </p>
                        )}
                        <p>
                            • Período: <strong>{formatDateRange()}</strong>
                        </p>
                        {schedule.schedule_enabled && schedule.schedule_config ? (
                            <>
                                <p>
                                    • Horário: <strong>
                                        {schedule.schedule_config.start_time} - {schedule.schedule_config.end_time}
                                    </strong>
                                </p>
                                <p>
                                    • Dias: <strong>
                                        {formatDaysOfWeek(schedule.schedule_config.days_of_week)}
                                    </strong>
                                </p>
                                <p>
                                    • Recorrência: <strong>
                                        {getRecurrenceLabel(schedule.schedule_config.recurrence)}
                                    </strong>
                                </p>
                            </>
                        ) : (
                            <p>
                                • Status: <strong>Sempre ativo</strong>
                            </p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}