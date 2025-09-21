import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    IconAlertTriangle,
    IconAlertCircle,
    IconInfoCircle,
    IconDevicesOff,
    IconDatabase,
    IconWifi,
    IconX,
    IconEye,
    IconBell
} from '@tabler/icons-react';
import { cn } from '@/lib/utils';

interface DashboardMetrics {
    players: {
        total: number;
        online: number;
        offline: number;
        online_percentage: number;
    };
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

interface RecentData {
    recent_player_activity: Array<{
        id: number;
        name: string;
        alias?: string;
        status: string;
        is_online: boolean;
        last_seen: string;
        last_seen_human: string;
        ip_address?: string;
    }>;
}

interface ImportantAlertsProps {
    metrics: DashboardMetrics;
    recentData?: RecentData;
}

interface Alert {
    id: string;
    type: 'warning' | 'error' | 'info';
    title: string;
    message: string;
    timestamp: string;
    category: 'storage' | 'players' | 'connectivity' | 'system';
    priority: 'high' | 'medium' | 'low';
    dismissible: boolean;
}

const ImportantAlerts: React.FC<ImportantAlertsProps> = ({ metrics, recentData }) => {
    const [dismissedAlerts, setDismissedAlerts] = useState<Set<string>>(new Set());

    const generateAlerts = (): Alert[] => {
        const alerts: Alert[] = [];

        // Storage alerts
        if (metrics.storage.percentage >= 90) {
            alerts.push({
                id: 'storage-critical',
                type: 'error',
                title: 'Armazenamento Crítico',
                message: `Apenas ${metrics.storage.available_formatted} disponíveis de ${metrics.storage.limit_formatted}. Faça limpeza urgentemente.`,
                timestamp: new Date().toISOString(),
                category: 'storage',
                priority: 'high',
                dismissible: false
            });
        } else if (metrics.storage.percentage >= 80) {
            alerts.push({
                id: 'storage-warning',
                type: 'warning',
                title: 'Armazenamento Limitado',
                message: `${metrics.storage.percentage.toFixed(1)}% do armazenamento está sendo usado. Considere fazer limpeza.`,
                timestamp: new Date().toISOString(),
                category: 'storage',
                priority: 'medium',
                dismissible: true
            });
        }

        // Player connectivity alerts
        const offlinePlayersCount = metrics.players.offline;
        if (offlinePlayersCount > 0 && metrics.players.total > 0) {
            if (metrics.players.online_percentage < 50) {
                alerts.push({
                    id: 'players-critical',
                    type: 'error',
                    title: 'Maioria dos Players Offline',
                    message: `${offlinePlayersCount} de ${metrics.players.total} players estão offline. Verifique a conectividade.`,
                    timestamp: new Date().toISOString(),
                    category: 'players',
                    priority: 'high',
                    dismissible: true
                });
            } else if (offlinePlayersCount >= 3) {
                alerts.push({
                    id: 'players-warning',
                    type: 'warning',
                    title: 'Players Offline',
                    message: `${offlinePlayersCount} players estão offline. Verifique a conectividade individual.`,
                    timestamp: new Date().toISOString(),
                    category: 'players',
                    priority: 'medium',
                    dismissible: true
                });
            }
        }

        // Long-term offline players
        const longOfflinePlayers = recentData?.recent_player_activity?.filter(
            player => !player.is_online && player.last_seen_human.includes('dia')
        );

        if (longOfflinePlayers && longOfflinePlayers.length > 0) {
            alerts.push({
                id: 'players-long-offline',
                type: 'warning',
                title: 'Players Offline por Dias',
                message: `${longOfflinePlayers.length} player(s) estão offline há mais de 24 horas. Verifique se precisam de manutenção.`,
                timestamp: new Date().toISOString(),
                category: 'connectivity',
                priority: 'medium',
                dismissible: true
            });
        }

        // No players registered
        if (metrics.players.total === 0) {
            alerts.push({
                id: 'no-players',
                type: 'info',
                title: 'Nenhum Player Registrado',
                message: 'Adicione players ao sistema para começar a transmitir conteúdo.',
                timestamp: new Date().toISOString(),
                category: 'system',
                priority: 'low',
                dismissible: true
            });
        }

        // System health info
        if (metrics.players.online_percentage === 100 && metrics.players.total > 0) {
            alerts.push({
                id: 'all-online',
                type: 'info',
                title: 'Todos os Players Online',
                message: `Excelente! Todos os ${metrics.players.total} players estão conectados e funcionando.`,
                timestamp: new Date().toISOString(),
                category: 'system',
                priority: 'low',
                dismissible: true
            });
        }

        return alerts.filter(alert => !dismissedAlerts.has(alert.id));
    };

    const alerts = generateAlerts();

    const dismissAlert = (alertId: string) => {
        setDismissedAlerts(prev => new Set([...prev, alertId]));
    };

    const getAlertIcon = (type: Alert['type'], category: Alert['category']) => {
        if (category === 'storage') return IconDatabase;
        if (category === 'players') return IconDevicesOff;
        if (category === 'connectivity') return IconWifi;

        switch (type) {
            case 'error':
                return IconAlertCircle;
            case 'warning':
                return IconAlertTriangle;
            case 'info':
                return IconInfoCircle;
            default:
                return IconBell;
        }
    };

    const getAlertColors = (type: Alert['type']) => {
        switch (type) {
            case 'error':
                return {
                    bg: 'bg-red-50',
                    border: 'border-red-200',
                    text: 'text-red-800',
                    icon: 'text-red-500'
                };
            case 'warning':
                return {
                    bg: 'bg-yellow-50',
                    border: 'border-yellow-200',
                    text: 'text-yellow-800',
                    icon: 'text-yellow-500'
                };
            case 'info':
                return {
                    bg: 'bg-blue-50',
                    border: 'border-blue-200',
                    text: 'text-blue-800',
                    icon: 'text-blue-500'
                };
            default:
                return {
                    bg: 'bg-gray-50',
                    border: 'border-gray-200',
                    text: 'text-gray-800',
                    icon: 'text-gray-500'
                };
        }
    };

    const getPriorityBadge = (priority: Alert['priority']) => {
        switch (priority) {
            case 'high':
                return <Badge variant="destructive" className="text-xs">Alta</Badge>;
            case 'medium':
                return <Badge variant="secondary" className="text-xs">Média</Badge>;
            case 'low':
                return <Badge variant="outline" className="text-xs">Baixa</Badge>;
        }
    };

    if (alerts.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconBell className="h-5 w-5 text-green-500" />
                        Avisos Importantes
                    </CardTitle>
                    <CardDescription>
                        Monitoramento de alertas e notificações do sistema
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="text-center py-8">
                        <div className="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                            <IconEye className="h-8 w-8 text-green-600" />
                        </div>
                        <p className="text-lg font-medium text-green-600">Tudo funcionando bem!</p>
                        <p className="text-sm text-gray-500">Não há alertas importantes no momento</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconBell className="h-5 w-5" />
                    Avisos Importantes
                    {alerts.length > 0 && (
                        <Badge variant="destructive" className="ml-2">
                            {alerts.length}
                        </Badge>
                    )}
                </CardTitle>
                <CardDescription>
                    Alertas e notificações que requerem atenção
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {alerts.map((alert) => {
                        const Icon = getAlertIcon(alert.type, alert.category);
                        const colors = getAlertColors(alert.type);

                        return (
                            <div
                                key={alert.id}
                                className={cn(
                                    'p-4 rounded-lg border',
                                    colors.bg,
                                    colors.border
                                )}
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start gap-3 flex-1">
                                        <Icon className={cn('h-5 w-5 mt-0.5', colors.icon)} />
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-1">
                                                <h4 className={cn('font-medium', colors.text)}>
                                                    {alert.title}
                                                </h4>
                                                {getPriorityBadge(alert.priority)}
                                            </div>
                                            <p className={cn('text-sm', colors.text)}>
                                                {alert.message}
                                            </p>
                                            <p className="text-xs text-gray-500 mt-2">
                                                {new Date(alert.timestamp).toLocaleString('pt-BR')}
                                            </p>
                                        </div>
                                    </div>
                                    {alert.dismissible && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => dismissAlert(alert.id)}
                                            className="h-8 w-8 p-0 hover:bg-transparent"
                                        >
                                            <IconX className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {alerts.length > 3 && (
                    <div className="mt-4 text-center">
                        <Button variant="outline" size="sm">
                            Ver todos os alertas ({alerts.length})
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

export default ImportantAlerts;