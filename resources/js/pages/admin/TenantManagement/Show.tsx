import React from 'react';
import { Head } from '@inertiajs/react';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    domain?: string;
    phone?: string;
    document?: string;
    address?: any;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    plan?: {
        id: number;
        name: string;
        price: number;
    };
    subscription?: {
        status: string;
        ends_at?: string;
    };
    users_count: number;
    players_count: number;
    media_files_count: number;
    storage_usage: number;
    formatted_storage: string;
}

interface Props {
    tenant: Tenant;
    analytics: {
        players_activity: any[];
        media_usage: any[];
        recent_activity: any[];
    };
}

export default function Show({ tenant, analytics }: Props) {
    return (
        <div className="container mx-auto py-8 space-y-6">
            <Head title={`Tenant: ${tenant.name}`} />

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h1 className="text-2xl font-bold">
                                    Tenant: {tenant.name}
                                </h1>
                                <div className="flex space-x-2">
                                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                                        tenant.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    }`}>
                                        {tenant.is_active ? 'Ativo' : 'Inativo'}
                                    </span>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                                <div className="bg-blue-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-blue-600">Usuários</h3>
                                    <p className="text-2xl font-bold text-blue-900">{tenant.users_count}</p>
                                </div>
                                <div className="bg-green-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-green-600">Players</h3>
                                    <p className="text-2xl font-bold text-green-900">{tenant.players_count}</p>
                                </div>
                                <div className="bg-yellow-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-yellow-600">Arquivos de Mídia</h3>
                                    <p className="text-2xl font-bold text-yellow-900">{tenant.media_files_count}</p>
                                </div>
                                <div className="bg-purple-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-purple-600">Armazenamento</h3>
                                    <p className="text-2xl font-bold text-purple-900">{tenant.formatted_storage}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div className="bg-gray-50 p-6 rounded-lg">
                                    <h2 className="text-lg font-semibold mb-4">Informações do Tenant</h2>
                                    <dl className="space-y-2">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Nome</dt>
                                            <dd className="text-sm text-gray-900">{tenant.name}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Slug</dt>
                                            <dd className="text-sm text-gray-900">{tenant.slug}</dd>
                                        </div>
                                        {tenant.domain && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Domínio</dt>
                                                <dd className="text-sm text-gray-900">{tenant.domain}</dd>
                                            </div>
                                        )}
                                        {tenant.phone && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Telefone</dt>
                                                <dd className="text-sm text-gray-900">{tenant.phone}</dd>
                                            </div>
                                        )}
                                        {tenant.document && (
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Documento</dt>
                                                <dd className="text-sm text-gray-900">{tenant.document}</dd>
                                            </div>
                                        )}
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Criado em</dt>
                                            <dd className="text-sm text-gray-900">
                                                {new Date(tenant.created_at).toLocaleDateString('pt-BR')}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div className="bg-gray-50 p-6 rounded-lg">
                                    <h2 className="text-lg font-semibold mb-4">Plano e Assinatura</h2>
                                    <dl className="space-y-2">
                                        {tenant.plan && (
                                            <>
                                                <div>
                                                    <dt className="text-sm font-medium text-gray-500">Plano</dt>
                                                    <dd className="text-sm text-gray-900">{tenant.plan.name}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-sm font-medium text-gray-500">Preço</dt>
                                                    <dd className="text-sm text-gray-900">
                                                        R$ {tenant.plan.price.toFixed(2)}/mês
                                                    </dd>
                                                </div>
                                            </>
                                        )}
                                        {tenant.subscription && (
                                            <>
                                                <div>
                                                    <dt className="text-sm font-medium text-gray-500">Status da Assinatura</dt>
                                                    <dd className="text-sm text-gray-900">{tenant.subscription.status}</dd>
                                                </div>
                                                {tenant.subscription.ends_at && (
                                                    <div>
                                                        <dt className="text-sm font-medium text-gray-500">Expira em</dt>
                                                        <dd className="text-sm text-gray-900">
                                                            {new Date(tenant.subscription.ends_at).toLocaleDateString('pt-BR')}
                                                        </dd>
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </dl>
                                </div>
                            </div>

                            {analytics && (
                                <div className="mt-8">
                                    <h2 className="text-lg font-semibold mb-4">Analytics</h2>
                                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        {analytics.players_activity?.length > 0 && (
                                            <div className="bg-gray-50 p-6 rounded-lg">
                                                <h3 className="text-md font-medium mb-3">Atividade dos Players</h3>
                                                <div className="space-y-2">
                                                    {analytics.players_activity.slice(0, 5).map((activity: any, index: number) => (
                                                        <div key={index} className="text-sm">
                                                            <span className="font-medium">{activity.player_name}</span>
                                                            <span className="text-gray-500 ml-2">{activity.status}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {analytics.recent_activity?.length > 0 && (
                                            <div className="bg-gray-50 p-6 rounded-lg">
                                                <h3 className="text-md font-medium mb-3">Atividade Recente</h3>
                                                <div className="space-y-2">
                                                    {analytics.recent_activity.slice(0, 5).map((activity: any, index: number) => (
                                                        <div key={index} className="text-sm">
                                                            <span className="font-medium">{activity.description}</span>
                                                            <span className="text-gray-500 ml-2">
                                                                {new Date(activity.created_at).toLocaleDateString('pt-BR')}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                </div>
            </div>
        </div>
    );
}