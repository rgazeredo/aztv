import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type User } from '@/types';
import { Head } from '@inertiajs/react';
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
    IconShield
} from '@tabler/icons-react';
import { useTranslation } from 'react-i18next';


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
}

function ClientDashboard({ tenant }: { tenant?: DashboardProps['auth']['user']['tenant'] }) {
    const { t } = useTranslation();
    return (
        <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            {tenant && (
                <Card className="mb-4">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <IconBuilding className="h-5 w-5" />
                            {tenant.name}
                        </CardTitle>
                        <CardDescription>
                            {t('dashboard.organizationPanel')}
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">{t('dashboard.myProjects')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.trackActiveProjects')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-blue-600 mb-2">
                            5
                        </div>
                        <p className="text-sm text-gray-600">
                            {t('dashboard.activeProjects')}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">{t('dashboard.progress')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.yourOverallProgress')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-green-600 mb-2">
                            75%
                        </div>
                        <p className="text-sm text-gray-600">
                            {t('dashboard.completed')}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">{t('dashboard.nextTask')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.yourNextActivity')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-lg font-semibold text-gray-900 mb-1">
                            {t('dashboard.projectManagement')}
                        </div>
                        <p className="text-sm text-gray-600">
                            {t('dashboard.today')}, 14:00
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.recentActivities')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.yourLatestActivities')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <h4 className="font-medium">{t('dashboard.taskManagement')}</h4>
                                    <p className="text-sm text-gray-600">Task 15 - {t('dashboard.projectPlanning')}</p>
                                </div>
                                <Badge variant="secondary">{t('dashboard.completedBadge')}</Badge>
                            </div>
                            <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <h4 className="font-medium">{t('dashboard.analytics')}</h4>
                                    <p className="text-sm text-gray-600">Report 8 - {t('dashboard.reports')}</p>
                                </div>
                                <Badge variant="secondary">{t('dashboard.completedBadge')}</Badge>
                            </div>
                            <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <div>
                                    <h4 className="font-medium">{t('dashboard.projectManagement')}</h4>
                                    <p className="text-sm text-gray-600">Flow 12 - {t('dashboard.workflows')}</p>
                                </div>
                                <Badge>{t('dashboard.inProgressBadge')}</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('dashboard.quickActions')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.quickAccessToMainFeatures')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4">
                            <Button className="h-20 flex-col gap-2">
                                <IconBook className="h-5 w-5" />
                                <div className="text-center">
                                    <span className="text-sm">{t('dashboard.view')}</span>
                                    <br />
                                    <span className="font-semibold">{t('dashboard.projects')}</span>
                                </div>
                            </Button>
                            <Button variant="outline" className="h-20 flex-col gap-2">
                                <IconUser className="h-5 w-5" />
                                <div className="text-center">
                                    <span className="text-sm">{t('dashboard.my')}</span>
                                    <br />
                                    <span className="font-semibold">{t('dashboard.profile')}</span>
                                </div>
                            </Button>
                            <Button variant="outline" className="h-20 flex-col gap-2">
                                <IconHeadphones className="h-5 w-5" />
                                <div className="text-center">
                                    <span className="text-sm">{t('dashboard.support')}</span>
                                    <br />
                                    <span className="font-semibold">{t('dashboard.contact')}</span>
                                </div>
                            </Button>
                            <Button variant="outline" className="h-20 flex-col gap-2">
                                <IconSettings className="h-5 w-5" />
                                <span className="font-semibold">{t('dashboard.settings')}</span>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
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

export default function Dashboard({ auth, tenants }: DashboardProps & { tenants?: Array<{ id: number; name: string; slug: string; users_count: number; is_active: boolean }> }) {
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
            {isAdmin ? <AdminDashboard allTenants={tenants} /> : <ClientDashboard tenant={auth.user.tenant} />}
        </AppLayout>
    );
}
