import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type User } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    IconBuilding,
    IconUsers,
    IconTrendingUp,
    IconCalendar,
    IconBook,
    IconUser,
    IconHeadphones,
    IconSettings,
    IconUsersGroup,
    IconChartBar,
    IconCash,
    IconShield,
    IconDatabase,
    IconDevices,
    IconPlaylist,
    IconPhoto,
    IconClock,
    IconRefresh
} from '@tabler/icons-react';
import { useTranslation } from 'react-i18next';
import { useState, useEffect } from 'react';


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
    content: {
        total_media: number;
        total_playlists: number;
        media_this_week: number;
        playlists_this_week: number;
    };
    activity: {
        new_media_this_week: number;
        new_playlists_this_week: number;
        active_players_today: number;
    };
    apk_downloads: {
        active_apk_version: string;
        active_apk_size: string;
        last_apk_update: string;
    };
}

interface RecentData {
    recent_media: Array<{
        id: number;
        name: string;
        filename: string;
        mime_type: string;
        size: string;
        thumbnail_url?: string;
        created_at: string;
        created_at_human: string;
    }>;
    recent_playlists: Array<{
        id: number;
        name: string;
        description?: string;
        items_count: number;
        updated_at: string;
        updated_at_human: string;
    }>;
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

interface DashboardProps {
    auth: {
        user: User & {
            tenant?: {
                id: number;
                name: string;
                slug: string;
                settings: Record<string, any>;
            };
        };
    };
    metrics?: DashboardMetrics;
    recentData?: RecentData;
    tenant?: {
        id: number;
        name: string;
        slug: string;
    };
}

function ClientDashboard({
    tenant,
    metrics,
    recentData
}: {
    tenant?: DashboardProps['auth']['user']['tenant'];
    metrics?: DashboardMetrics;
    recentData?: RecentData;
}) {
    const { t } = useTranslation();
    const [currentMetrics, setCurrentMetrics] = useState<DashboardMetrics | undefined>(metrics);
    const [refreshing, setRefreshing] = useState(false);

    const refreshMetrics = async () => {
        setRefreshing(true);
        try {
            const response = await fetch('/dashboard/metrics');
            const data = await response.json();
            if (data.success) {
                setCurrentMetrics(data.metrics);
            }
        } catch (error) {
            console.error('Failed to refresh metrics:', error);
        } finally {
            setRefreshing(false);
        }
    };

    // Auto refresh metrics every 5 minutes
    useEffect(() => {
        const interval = setInterval(refreshMetrics, 5 * 60 * 1000);
        return () => clearInterval(interval);
    }, []);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            {tenant && (
                <Card className="mb-4">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <IconBuilding className="h-5 w-5" />
                            {tenant.name}
                        </CardTitle>
                        <CardDescription className="flex items-center justify-between">
                            <span>Painel de Controle AZ TV</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={refreshMetrics}
                                disabled={refreshing}
                                className="h-6"
                            >
                                <IconRefresh className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            </Button>
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}

            {/* Metrics Cards */}
            <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <IconDevices className="h-5 w-5" />
                            Players
                        </CardTitle>
                        <CardDescription>
                            Dispositivos conectados
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-2xl font-bold text-blue-600 mb-1">
                                    {currentMetrics?.players.online || 0}
                                </div>
                                <p className="text-sm text-gray-600">
                                    de {currentMetrics?.players.total || 0} total
                                </p>
                            </div>
                            <div className="text-right">
                                <div className="text-sm font-medium text-green-600">
                                    {currentMetrics?.players.online_percentage || 0}% online
                                </div>
                                <div className="text-xs text-gray-500">
                                    {currentMetrics?.players.offline || 0} offline
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <IconDatabase className="h-5 w-5" />
                            Storage
                        </CardTitle>
                        <CardDescription>
                            Uso de armazenamento
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span>Usado:</span>
                                <span className="font-medium">{currentMetrics?.storage.used_formatted || '0 B'}</span>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    className="bg-blue-600 h-2 rounded-full"
                                    style={{ width: `${Math.min(currentMetrics?.storage.percentage || 0, 100)}%` }}
                                ></div>
                            </div>
                            <div className="flex justify-between text-xs text-gray-500">
                                <span>{currentMetrics?.storage.percentage || 0}% usado</span>
                                <span>Limite: {currentMetrics?.storage.limit_formatted || 'N/A'}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <IconPhoto className="h-5 w-5" />
                            Conteúdo
                        </CardTitle>
                        <CardDescription>
                            Mídia e playlists
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-600">Mídias:</span>
                                <span className="font-medium">{currentMetrics?.content.total_media || 0}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-600">Playlists:</span>
                                <span className="font-medium">{currentMetrics?.content.total_playlists || 0}</span>
                            </div>
                            <div className="text-xs text-green-600 mt-1">
                                +{currentMetrics?.activity.new_media_this_week || 0} mídia(s) esta semana
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <IconTrendingUp className="h-5 w-5" />
                            Atividade
                        </CardTitle>
                        <CardDescription>
                            Esta semana
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <div className="text-2xl font-bold text-green-600 mb-1">
                                {currentMetrics?.activity.active_players_today || 0}
                            </div>
                            <p className="text-sm text-gray-600">
                                players ativos hoje
                            </p>
                            <div className="text-xs text-gray-500">
                                +{currentMetrics?.activity.new_playlists_this_week || 0} playlist(s) criada(s)
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* Recent Media */}
                <Card>
                    <CardHeader>
                        <CardTitle>Mídias Recentes</CardTitle>
                        <CardDescription>
                            Últimos arquivos adicionados
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {recentData?.recent_media?.map((media) => (
                                <div key={media.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    {media.thumbnail_url ? (
                                        <img
                                            src={media.thumbnail_url}
                                            alt={media.name}
                                            className="w-12 h-12 object-cover rounded"
                                        />
                                    ) : (
                                        <div className="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                                            <IconPhoto className="h-6 w-6 text-gray-400" />
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <h4 className="font-medium truncate">{media.name}</h4>
                                        <p className="text-sm text-gray-600">{media.size} • {media.created_at_human}</p>
                                    </div>
                                </div>
                            ))}
                            {!recentData?.recent_media?.length && (
                                <p className="text-gray-500 text-center py-4">Nenhuma mídia encontrada</p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Recent Activity */}
                <Card>
                    <CardHeader>
                        <CardTitle>Atividade dos Players</CardTitle>
                        <CardDescription>
                            Status recente dos dispositivos
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {recentData?.recent_player_activity?.map((player) => (
                                <div key={player.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <div className={`w-3 h-3 rounded-full ${player.is_online ? 'bg-green-500' : 'bg-red-500'}`}></div>
                                        <div>
                                            <h4 className="font-medium">{player.name}</h4>
                                            <p className="text-sm text-gray-600">{player.last_seen_human}</p>
                                        </div>
                                    </div>
                                    <Badge variant={player.is_online ? 'default' : 'secondary'}>
                                        {player.is_online ? 'Online' : 'Offline'}
                                    </Badge>
                                </div>
                            ))}
                            {!recentData?.recent_player_activity?.length && (
                                <p className="text-gray-500 text-center py-4">Nenhum player encontrado</p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Quick Actions */}
            <Card>
                <CardHeader>
                    <CardTitle>Ações Rápidas</CardTitle>
                    <CardDescription>
                        Acesso rápido às principais funcionalidades
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Button
                            className="h-20 flex-col gap-2"
                            onClick={() => router.visit('/players')}
                        >
                            <IconDevices className="h-5 w-5" />
                            <span className="font-semibold">Players</span>
                        </Button>
                        <Button
                            variant="outline"
                            className="h-20 flex-col gap-2"
                            onClick={() => router.visit('/media')}
                        >
                            <IconPhoto className="h-5 w-5" />
                            <span className="font-semibold">Mídia</span>
                        </Button>
                        <Button
                            variant="outline"
                            className="h-20 flex-col gap-2"
                            onClick={() => router.visit('/playlists')}
                        >
                            <IconPlaylist className="h-5 w-5" />
                            <span className="font-semibold">Playlists</span>
                        </Button>
                        <Button
                            variant="outline"
                            className="h-20 flex-col gap-2"
                            onClick={() => router.visit('/activation')}
                        >
                            <IconSettings className="h-5 w-5" />
                            <span className="font-semibold">Ativação</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function AdminDashboard({ allTenants }: { allTenants?: Array<{ id: number; name: string; slug: string; users_count: number; is_active: boolean }> }) {
    const { t } = useTranslation();
    return (
        <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <Card className="mb-4">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconShield className="h-5 w-5" />
                        {t('dashboard.globalAdministration')}
                    </CardTitle>
                    <CardDescription>
                        {t('dashboard.tenantAdminPanel')}
                    </CardDescription>
                </CardHeader>
            </Card>
            <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg">{t('dashboard.totalTenants')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.activeOrganizations')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-blue-600 mb-1">
                            {allTenants?.filter(t => t.is_active).length || 0}
                        </div>
                        <p className="text-sm text-gray-600">
                            {t('dashboard.activeOrganizations')}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg">{t('dashboard.totalUsers')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.usersAllTenants')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-green-600 mb-1">
                            {allTenants?.reduce((acc, tenant) => acc + tenant.users_count, 0) || 0}
                        </div>
                        <p className="text-sm text-green-600">
                            {t('dashboard.allTenants')}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg">{t('dashboard.largestTenant')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.organizationWithMostUsers')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-lg font-bold text-purple-600 mb-1">
                            {allTenants?.reduce((max, tenant) =>
                                tenant.users_count > (max?.users_count || 0) ? tenant : max,
                                allTenants[0]
                            )?.name || 'N/A'}
                        </div>
                        <p className="text-sm text-gray-600">
                            {allTenants?.reduce((max, tenant) =>
                                tenant.users_count > (max?.users_count || 0) ? tenant : max,
                                allTenants[0]
                            )?.users_count || 0} {t('dashboard.users')}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg">{t('dashboard.system')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.generalStatus')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-lg font-bold text-green-600 mb-1">
                            {t('dashboard.operational')}
                        </div>
                        <p className="text-sm text-green-600">
                            {t('dashboard.allSystems')}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="grid lg:grid-cols-3 gap-6">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>{t('dashboard.activeTenants')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.platformRegisteredOrganizations')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {allTenants?.map((tenant) => (
                                <div key={tenant.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <h4 className="font-medium">{tenant.name}</h4>
                                        <p className="text-sm text-gray-600">{tenant.users_count} {t('dashboard.user')}(s)</p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Badge variant={tenant.is_active ? 'default' : 'secondary'}>
                                            {tenant.is_active ? t('dashboard.active') : t('dashboard.inactive')}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                            {!allTenants?.length && (
                                <p className="text-gray-500 text-center py-4">{t('dashboard.noTenantsFound')}</p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.adminActions')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.platformManagement')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <Button className="w-full justify-start gap-2">
                                <IconUsersGroup className="h-4 w-4" />
                                {t('dashboard.manageTenants')}
                            </Button>
                            <Button variant="outline" className="w-full justify-start gap-2">
                                <IconBuilding className="h-4 w-4" />
                                {t('dashboard.createNewTenant')}
                            </Button>
                            <Button variant="outline" className="w-full justify-start gap-2">
                                <IconChartBar className="h-4 w-4" />
                                {t('dashboard.globalReports')}
                            </Button>
                            <Button variant="outline" className="w-full justify-start gap-2">
                                <IconSettings className="h-4 w-4" />
                                {t('dashboard.systemSettings')}
                            </Button>
                            <Button variant="outline" className="w-full justify-start gap-2">
                                <IconCalendar className="h-4 w-4" />
                                {t('dashboard.activityLogs')}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.recentActivity')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.lastSystemActions')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <div className="border-l-4 border-blue-500 pl-4 py-2">
                                <p className="text-sm font-medium">{t('dashboard.newUserRegistered')}</p>
                                <p className="text-xs text-gray-600">há 2 minutos</p>
                            </div>
                            <div className="border-l-4 border-green-500 pl-4 py-2">
                                <p className="text-sm font-medium">{t('dashboard.projectCreatedBy', { user: 'João Silva' })}</p>
                                <p className="text-xs text-gray-600">há 15 minutos</p>
                            </div>
                            <div className="border-l-4 border-orange-500 pl-4 py-2">
                                <p className="text-sm font-medium">{t('dashboard.paymentProcessed')}</p>
                                <p className="text-xs text-gray-600">há 1 hora</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.systemAlerts')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.importantNotifications')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                <p className="text-sm font-medium text-yellow-800">
                                    {t('dashboard.backupScheduled')}
                                </p>
                            </div>
                            <div className="bg-green-50 border border-green-200 rounded-lg p-3">
                                <p className="text-sm font-medium text-green-800">
                                    {t('dashboard.systemRunningNormally')}
                                </p>
                            </div>
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <p className="text-sm font-medium text-blue-800">
                                    {t('dashboard.updateAvailable')}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

export default function Dashboard({
    auth,
    tenants,
    metrics,
    recentData,
    tenant
}: DashboardProps & {
    tenants?: Array<{ id: number; name: string; slug: string; users_count: number; is_active: boolean }>
}) {
    const isAdmin = auth.user.role === 'admin';
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('dashboard.title'),
            href: dashboard().url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('dashboard.title')} ${isAdmin ? `- ${t('dashboard.administration')}` : auth.user.tenant ? `- ${auth.user.tenant.name}` : `- ${t('dashboard.clientAdmin')}`}`} />
            {isAdmin ? (
                <AdminDashboard allTenants={tenants} />
            ) : (
                <ClientDashboard
                    tenant={auth.user.tenant || tenant}
                    metrics={metrics}
                    recentData={recentData}
                />
            )}
        </AppLayout>
    );
}
