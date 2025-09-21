import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { IconDatabase, IconAlertTriangle, IconAlertCircle } from '@tabler/icons-react';
import { cn } from '@/lib/utils';

interface StorageData {
    storage: {
        used: number;
        used_formatted: string;
        limit: number;
        limit_formatted: string;
        percentage: number;
        available: number;
        available_formatted: string;
    };
}

interface StorageUsageBarProps {
    data: StorageData;
}

const StorageUsageBar: React.FC<StorageUsageBarProps> = ({ data }) => {
    const { storage } = data;
    const percentage = Math.min(storage.percentage, 100);

    // Determine color and alert level based on usage
    const getUsageColor = (percent: number) => {
        if (percent >= 90) return 'bg-red-500';
        if (percent >= 80) return 'bg-yellow-500';
        if (percent >= 60) return 'bg-blue-500';
        return 'bg-green-500';
    };

    const getAlertLevel = (percent: number) => {
        if (percent >= 90) return 'critical';
        if (percent >= 80) return 'warning';
        return 'normal';
    };

    const alertLevel = getAlertLevel(percentage);
    const progressColor = getUsageColor(percentage);

    const AlertIcon = () => {
        switch (alertLevel) {
            case 'critical':
                return <IconAlertCircle className="h-5 w-5 text-red-500" />;
            case 'warning':
                return <IconAlertTriangle className="h-5 w-5 text-yellow-500" />;
            default:
                return <IconDatabase className="h-5 w-5 text-blue-500" />;
        }
    };

    const getAlertMessage = () => {
        switch (alertLevel) {
            case 'critical':
                return 'Armazenamento quase esgotado! Considere fazer limpeza ou upgrade do plano.';
            case 'warning':
                return 'Armazenamento próximo do limite. Monitore o uso de espaço.';
            default:
                return null;
        }
    };

    if (storage.limit === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconDatabase className="h-5 w-5" />
                        Uso de Armazenamento
                    </CardTitle>
                    <CardDescription>
                        Monitoramento do espaço de armazenamento
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-center py-8">
                        <IconDatabase className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                        <p className="text-lg font-medium text-gray-600">Limite não definido</p>
                        <p className="text-sm text-gray-500">
                            Usado: {storage.used_formatted}
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={cn(
            alertLevel === 'critical' && 'border-red-200 bg-red-50/50',
            alertLevel === 'warning' && 'border-yellow-200 bg-yellow-50/50'
        )}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <AlertIcon />
                    Uso de Armazenamento
                </CardTitle>
                <CardDescription>
                    {storage.used_formatted} de {storage.limit_formatted} utilizados ({percentage}%)
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Alert Message */}
                {getAlertMessage() && (
                    <div className={cn(
                        'p-3 rounded-lg border-l-4 text-sm',
                        alertLevel === 'critical' && 'bg-red-50 border-red-500 text-red-700',
                        alertLevel === 'warning' && 'bg-yellow-50 border-yellow-500 text-yellow-700'
                    )}>
                        <div className="flex items-center gap-2">
                            {alertLevel === 'critical' ? (
                                <IconAlertCircle className="h-4 w-4" />
                            ) : (
                                <IconAlertTriangle className="h-4 w-4" />
                            )}
                            {getAlertMessage()}
                        </div>
                    </div>
                )}

                {/* Progress Bar */}
                <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-600">Usado</span>
                        <span className="font-medium">{percentage.toFixed(1)}%</span>
                    </div>

                    <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div
                            className={cn(
                                'h-full transition-all duration-700 ease-out rounded-full',
                                progressColor
                            )}
                            style={{ width: `${percentage}%` }}
                        />
                    </div>

                    <div className="flex justify-between text-xs text-gray-500">
                        <span>0</span>
                        <span>{storage.limit_formatted}</span>
                    </div>
                </div>

                {/* Storage Statistics */}
                <div className="grid grid-cols-3 gap-4 pt-4 border-t">
                    <div className="text-center">
                        <div className="text-lg font-bold text-blue-600">
                            {storage.used_formatted}
                        </div>
                        <div className="text-xs text-gray-600">Usado</div>
                    </div>
                    <div className="text-center">
                        <div className="text-lg font-bold text-green-600">
                            {storage.available_formatted}
                        </div>
                        <div className="text-xs text-gray-600">Disponível</div>
                    </div>
                    <div className="text-center">
                        <div className="text-lg font-bold text-gray-600">
                            {storage.limit_formatted}
                        </div>
                        <div className="text-xs text-gray-600">Limite</div>
                    </div>
                </div>

                {/* Usage Breakdown */}
                <div className="bg-gray-50 rounded-lg p-3">
                    <div className="text-sm font-medium text-gray-700 mb-2">
                        Detalhes do Uso
                    </div>
                    <div className="space-y-1 text-xs">
                        <div className="flex justify-between">
                            <span className="text-gray-600">Espaço total do plano:</span>
                            <span className="font-medium">{storage.limit_formatted}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Espaço utilizado:</span>
                            <span className="font-medium">{storage.used_formatted}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Espaço livre:</span>
                            <span className="font-medium text-green-600">{storage.available_formatted}</span>
                        </div>
                        <div className="flex justify-between border-t pt-1">
                            <span className="text-gray-600">Percentual usado:</span>
                            <span className={cn(
                                'font-medium',
                                percentage >= 90 ? 'text-red-600' :
                                percentage >= 80 ? 'text-yellow-600' :
                                'text-green-600'
                            )}>
                                {percentage.toFixed(1)}%
                            </span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

export default StorageUsageBar;