import { useState, useEffect } from "react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";
import { IconClock, IconAlertCircle } from "@tabler/icons-react";

interface TimeRange {
    start: string;
    end: string;
}

interface TimeRangePickerProps {
    value?: TimeRange;
    onChange?: (value: TimeRange) => void;
    label?: string;
    error?: string;
    disabled?: boolean;
}

export default function TimeRangePicker({
    value = { start: '09:00', end: '17:00' },
    onChange,
    label = "Horário",
    error,
    disabled = false
}: TimeRangePickerProps) {
    const [timeRange, setTimeRange] = useState<TimeRange>(value);
    const [validationError, setValidationError] = useState<string>("");

    useEffect(() => {
        setTimeRange(value);
    }, [value]);

    const validateTimeRange = (start: string, end: string): string => {
        if (!start || !end) {
            return "Horários de início e fim são obrigatórios";
        }

        const startTime = new Date(`2000-01-01 ${start}`);
        const endTime = new Date(`2000-01-01 ${end}`);

        if (isNaN(startTime.getTime()) || isNaN(endTime.getTime())) {
            return "Formato de horário inválido";
        }

        if (startTime >= endTime) {
            return "Horário de início deve ser anterior ao horário de fim";
        }

        return "";
    };

    const handleTimeChange = (field: 'start' | 'end', newValue: string) => {
        const updatedRange = {
            ...timeRange,
            [field]: newValue
        };

        setTimeRange(updatedRange);

        const validation = validateTimeRange(updatedRange.start, updatedRange.end);
        setValidationError(validation);

        if (!validation) {
            onChange?.(updatedRange);
        }
    };

    const formatDuration = (start: string, end: string): string => {
        try {
            const startTime = new Date(`2000-01-01 ${start}`);
            const endTime = new Date(`2000-01-01 ${end}`);

            if (isNaN(startTime.getTime()) || isNaN(endTime.getTime())) {
                return "";
            }

            const diffMs = endTime.getTime() - startTime.getTime();
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

            if (diffHours > 0) {
                return `${diffHours}h ${diffMinutes}m`;
            } else {
                return `${diffMinutes}m`;
            }
        } catch {
            return "";
        }
    };

    const currentError = error || validationError;
    const duration = formatDuration(timeRange.start, timeRange.end);

    return (
        <div className="space-y-2">
            <Label className="text-sm font-medium flex items-center gap-2">
                <IconClock className="h-4 w-4" />
                {label}
            </Label>

            <Card className={`${currentError ? 'border-red-500' : ''} ${disabled ? 'opacity-50' : ''}`}>
                <CardContent className="p-4">
                    <div className="grid grid-cols-2 gap-4">
                        {/* Start Time */}
                        <div className="space-y-2">
                            <Label htmlFor="start-time" className="text-xs text-gray-600 dark:text-gray-400">
                                Início
                            </Label>
                            <Input
                                id="start-time"
                                type="time"
                                value={timeRange.start}
                                onChange={(e) => handleTimeChange('start', e.target.value)}
                                disabled={disabled}
                                className="text-center"
                            />
                        </div>

                        {/* End Time */}
                        <div className="space-y-2">
                            <Label htmlFor="end-time" className="text-xs text-gray-600 dark:text-gray-400">
                                Fim
                            </Label>
                            <Input
                                id="end-time"
                                type="time"
                                value={timeRange.end}
                                onChange={(e) => handleTimeChange('end', e.target.value)}
                                disabled={disabled}
                                className="text-center"
                            />
                        </div>
                    </div>

                    {/* Duration Display */}
                    {duration && !currentError && (
                        <div className="mt-3 text-center">
                            <div className="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 dark:bg-blue-950 rounded-md text-xs text-blue-700 dark:text-blue-300">
                                <IconClock className="h-3 w-3" />
                                Duração: {duration}
                            </div>
                        </div>
                    )}

                    {/* Error Display */}
                    {currentError && (
                        <div className="mt-3 flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                            <IconAlertCircle className="h-4 w-4" />
                            {currentError}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Quick Presets */}
            {!disabled && (
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => handleTimeChange('start', '09:00') || handleTimeChange('end', '17:00')}
                        className="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                    >
                        Comercial (9h-17h)
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTimeChange('start', '08:00') || handleTimeChange('end', '18:00')}
                        className="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                    >
                        Estendido (8h-18h)
                    </button>
                    <button
                        type="button"
                        onClick={() => handleTimeChange('start', '00:00') || handleTimeChange('end', '23:59')}
                        className="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                    >
                        24 Horas
                    </button>
                </div>
            )}
        </div>
    );
}