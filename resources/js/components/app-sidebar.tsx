import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem, type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutGrid,
    Monitor,
    FileVideo,
    ListMusic,
    Calendar,
    Puzzle,
    QrCode,
    Activity,
    FileText,
    Bell,
    Settings,
    Users
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;

    // Função para verificar se o usuário é admin
    const isAdmin = auth?.user?.role === 'admin';

    // Grupos de menu para clientes organizados por categoria
    const clientNavGroups: NavGroup[] = [
        {
            title: 'Painel',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ]
        },
        {
            title: 'Gerenciamento',
            items: [
                {
                    title: 'Players',
                    href: '/players',
                    icon: Monitor,
                },
                {
                    title: 'Mídia',
                    href: '/media',
                    icon: FileVideo,
                },
                {
                    title: 'Playlists',
                    href: '/playlists',
                    icon: ListMusic,
                },
            ]
        },
        {
            title: 'Programação',
            items: [
                {
                    title: 'Agendamentos',
                    href: '/playlist-schedules',
                    icon: Calendar,
                },
                {
                    title: 'Módulos de Conteúdo',
                    href: '/content-modules',
                    icon: Puzzle,
                },
            ]
        },
        {
            title: 'Ferramentas',
            items: [
                {
                    title: 'QR Codes',
                    href: '/qr-code',
                    icon: QrCode,
                },
                {
                    title: 'Tokens de Ativação',
                    href: '/activation',
                    icon: Users,
                },
            ]
        },
        {
            title: 'Monitoramento',
            items: [
                {
                    title: 'Logs de Atividade',
                    href: '/activity-logs',
                    icon: Activity,
                },
                {
                    title: 'Logs dos Players',
                    href: '/player-logs',
                    icon: FileText,
                },
                {
                    title: 'Alertas',
                    href: '/alerts',
                    icon: Bell,
                },
            ]
        },
        {
            title: 'Sistema',
            items: [
                {
                    title: 'Configurações',
                    href: '/settings',
                    icon: Settings,
                },
            ]
        }
    ];

    // Menu items para admin (apenas dashboard por enquanto)
    const adminNavItems: NavItem[] = [
        {
            title: t('dashboard.title'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {isAdmin ? (
                    <NavMain items={adminNavItems} />
                ) : (
                    <NavMain groups={clientNavGroups} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
