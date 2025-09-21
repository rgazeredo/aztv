import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
    IconAlertTriangle,
    IconChevronDown,
    IconChevronUp,
    IconCalendar,
    IconClock,
    IconUsers,
} from "@tabler/icons-react";
import { useState } from "react";

interface ConflictingSchedule {
    id: number;
    playlist_name: string;
    player_names: string[];
    start_time: string;
    end_time: string;
    days_of_week: string[];
    priority: number;
}

interface ConflictWarningProps {
    conflicts: ConflictingSchedule[];
    onResolve?: (conflictId: number) => void;
    onIgnore?: () => void;
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

export default function ConflictWarning({ conflicts, onResolve, onIgnore }: ConflictWarningProps) {
    const [isOpen, setIsOpen] = useState(false);

    if (conflicts.length === 0) {
        return null;
    }

    const formatDays = (days: string[]) => {
        return days.map(day => DAYS_MAP[day]).join(', ');
    };

    const getSeverityLevel = (conflicts: ConflictingSchedule[]) => {
        const highPriorityConflicts = conflicts.filter(c => c.priority <= 2);

        if (highPriorityConflicts.length > 0) {
            return 'high';
        } else if (conflicts.length > 2) {
            return 'medium';
        } else {
            return 'low';
        }
    };

    const severity = getSeverityLevel(conflicts);

    const getSeverityConfig = () => {
        switch (severity) {
            case 'high':
                return {
                    className: 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950',
                    iconColor: 'text-red-600 dark:text-red-400',
                    textColor: 'text-red-800 dark:text-red-200',
                    title: 'Conflitos Críticos Detectados',
                    description: 'Existem conflitos com agendamentos de alta prioridade que podem afetar a reprodução.'
                };
            case 'medium':
                return {
                    className: 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950',
                    iconColor: 'text-yellow-600 dark:text-yellow-400',
                    textColor: 'text-yellow-800 dark:text-yellow-200',
                    title: 'Múltiplos Conflitos Detectados',
                    description: 'Vários agendamentos podem entrar em conflito com a configuração atual.'
                };
            default:
                return {
                    className: 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950',
                    iconColor: 'text-orange-600 dark:text-orange-400',
                    textColor: 'text-orange-800 dark:text-orange-200',
                    title: 'Conflitos Menores Detectados',
                    description: 'Alguns agendamentos podem sobrepor o horário configurado.'
                };
        }
    };

    const config = getSeverityConfig();

    return (
        <Alert className={config.className}>
            <div className="flex items-start gap-3">
                <IconAlertTriangle className={`h-5 w-5 ${config.iconColor} mt-0.5`} />
                <div className="flex-1">
                    <div className="flex items-center justify-between">
                        <div>
                            <h4 className={`font-medium ${config.textColor}`}>
                                {config.title}
                            </h4>
                            <AlertDescription className="mt-1">
                                {config.description}
                            </AlertDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className="text-xs">
                                {conflicts.length} conflito(s)
                            </Badge>
                            <Collapsible open={isOpen} onOpenChange={setIsOpen}>
                                <CollapsibleTrigger asChild>
                                    <Button variant="ghost" size="sm">
                                        {isOpen ? (
                                            <IconChevronUp className="h-4 w-4" />
                                        ) : (
                                            <IconChevronDown className="h-4 w-4" />
                                        )}
                                    </Button>
                                </CollapsibleTrigger>
                            </Collapsible>
                        </div>
                    </div>

                    <Collapsible open={isOpen} onOpenChange={setIsOpen}>
                        <CollapsibleContent className="mt-4">
                            <div className="space-y-3">
                                {conflicts.map((conflict) => (
                                    <div
                                        key={conflict.id}
                                        className="border rounded-lg p-3 bg-white dark:bg-gray-900"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <h5 className="font-medium">{conflict.playlist_name}</h5>
                                                    <Badge
                                                        variant={conflict.priority <= 2 ? 'destructive' : 'secondary'}
                                                        className="text-xs"
                                                    >
                                                        Prioridade {conflict.priority}
                                                    </Badge>
                                                </div>

                                                <div className="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                    <div className="flex items-center gap-2">
                                                        <IconClock className="h-3 w-3" />
                                                        <span>
                                                            {conflict.start_time} - {conflict.end_time}
                                                        </span>
                                                    </div>

                                                    <div className="flex items-center gap-2">
                                                        <IconCalendar className="h-3 w-3" />
                                                        <span>{formatDays(conflict.days_of_week)}</span>
                                                    </div>

                                                    <div className="flex items-center gap-2">
                                                        <IconUsers className="h-3 w-3" />
                                                        <span>
                                                            {conflict.player_names.length} player(s): {' '}
                                                            {conflict.player_names.slice(0, 3).join(', ')}
                                                            {conflict.player_names.length > 3 && (
                                                                <span className="text-gray-500">
                                                                    {' '}e mais {conflict.player_names.length - 3}
                                                                </span>
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {onResolve && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => onResolve(conflict.id)}
                                                    className="ml-3"
                                                >
                                                    Resolver
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                {/* Action Buttons */}
                                <div className="flex items-center justify-between pt-2 border-t">
                                    <div className="text-xs text-gray-600 dark:text-gray-400">
                                        <p className="font-medium">Recomendações:</p>
                                        <ul className="mt-1 list-disc list-inside space-y-0.5">
                                            <li>Ajuste os horários para evitar sobreposição</li>
                                            <li>Considere alterar a prioridade dos agendamentos</li>
                                            <li>Redistribua os players entre diferentes playlists</li>
                                        </ul>
                                    </div>

                                    <div className="flex gap-2">
                                        {onIgnore && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={onIgnore}
                                            >
                                                Ignorar Conflitos
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            onClick={() => setIsOpen(false)}
                                        >
                                            Entendi
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </div>
        </Alert>
    );
}