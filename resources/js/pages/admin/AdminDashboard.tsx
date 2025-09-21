import React from 'react';
import { Head } from '@inertiajs/react';

interface Stats {
    total_tenants: number;
    active_tenants: number;
    total_users: number;
    total_players: number;
    online_players: number;
    total_media_files: number;
    total_storage_used: number;
    apk_downloads: number;
}

interface RecentTenant {
    id: number;
    name: string;
    slug: string;
    users_count: number;
    is_active: boolean;
    created_at: string;
    subscription_status: string;
}

interface PlayerActivity {
    id: number;
    name: string;
    tenant_name: string;
    status: string;
    last_seen: string;
    ip_address: string;
}

interface StorageByTenant {
    tenant_name: string;
    storage_used: number;
    formatted_storage: string;
}

interface Props {
    stats: Stats;
    recent_tenants: RecentTenant[];
    players_activity: PlayerActivity[];
    storage_by_tenant: StorageByTenant[];
    formatted_stats: {
        total_storage_formatted: string;
    };
}

export default function AdminDashboard({
    stats,
    recent_tenants,
    players_activity,
    storage_by_tenant,
    formatted_stats,
}: Props) {

    return (
        <div className="container mx-auto py-8 space-y-6">
            <Head title="Admin Dashboard" />

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900">
                    <h1 className="text-2xl font-bold mb-6">Dashboard Administrativo</h1>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div className="bg-blue-50 p-6 rounded-lg">
                            <h3 className="text-sm font-medium text-blue-600">Total de Tenants</h3>
                            <p className="text-3xl font-bold text-blue-900">{stats.total_tenants}</p>
                            <p className="text-sm text-blue-600">{stats.active_tenants} ativos</p>
                        </div>
                        <div className="bg-green-50 p-6 rounded-lg">
                            <h3 className="text-sm font-medium text-green-600">Total de Usuários</h3>
                            <p className="text-3xl font-bold text-green-900">{stats.total_users}</p>
                        </div>
                        <div className="bg-yellow-50 p-6 rounded-lg">
                            <h3 className="text-sm font-medium text-yellow-600">Players</h3>
                            <p className="text-3xl font-bold text-yellow-900">{stats.total_players}</p>
                            <p className="text-sm text-yellow-600">{stats.online_players} online</p>
                        </div>
                        <div className="bg-purple-50 p-6 rounded-lg">
                            <h3 className="text-sm font-medium text-purple-600">Armazenamento</h3>
                            <p className="text-3xl font-bold text-purple-900">{formatted_stats.total_storage_formatted}</p>
                            <p className="text-sm text-purple-600">{stats.total_media_files} arquivos</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <div className="bg-gray-50 p-6 rounded-lg">
                            <h2 className="text-lg font-semibold mb-4">Tenants Recentes</h2>
                            <div className="space-y-3">
                                {recent_tenants.map((tenant) => (
                                    <div key={tenant.id} className="flex justify-between items-center">
                                        <div>
                                            <p className="font-medium">{tenant.name}</p>
                                            <p className="text-sm text-gray-500">{tenant.users_count} usuários</p>
                                        </div>
                                        <div className="text-right">
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                tenant.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {tenant.is_active ? 'Ativo' : 'Inativo'}
                                            </span>
                                            <p className="text-xs text-gray-500 mt-1">{tenant.subscription_status}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="bg-gray-50 p-6 rounded-lg">
                            <h2 className="text-lg font-semibold mb-4">Atividade dos Players</h2>
                            <div className="space-y-3">
                                {players_activity.map((player) => (
                                    <div key={player.id} className="flex justify-between items-center">
                                        <div>
                                            <p className="font-medium">{player.name}</p>
                                            <p className="text-sm text-gray-500">{player.tenant_name}</p>
                                        </div>
                                        <div className="text-right">
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                player.status === 'online'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}>
                                                {player.status}
                                            </span>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {new Date(player.last_seen).toLocaleString('pt-BR')}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="bg-gray-50 p-6 rounded-lg">
                        <h2 className="text-lg font-semibold mb-4">Uso de Armazenamento por Tenant</h2>
                        <div className="space-y-3">
                            {storage_by_tenant.map((tenant, index) => (
                                <div key={index} className="flex justify-between items-center">
                                    <p className="font-medium">{tenant.tenant_name}</p>
                                    <p className="text-sm text-gray-600">{tenant.formatted_storage}</p>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-indigo-50 p-6 rounded-lg">
                            <h3 className="text-lg font-semibold text-indigo-900 mb-2">Downloads de APK</h3>
                            <p className="text-3xl font-bold text-indigo-900">{stats.apk_downloads}</p>
                            <p className="text-sm text-indigo-600">Total de downloads</p>
                        </div>
                        <div className="bg-orange-50 p-6 rounded-lg">
                            <h3 className="text-lg font-semibold text-orange-900 mb-2">Status do Sistema</h3>
                            <div className="flex items-center">
                                <div className="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span className="text-sm text-orange-900">Sistema Operacional</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}