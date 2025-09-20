import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PaginatedCollection } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';

interface PlayerActivationToken {
    id: number;
    token: string;
    activation_code: string;
    qr_code_url?: string;
    activation_url: string;
    short_url: string;
    expires_at: string;
    is_used: boolean;
    used_at?: string;
    status: 'active' | 'used' | 'expired';
    expires_in: string;
    player?: {
        id: number;
        name: string;
    };
}

interface Statistics {
    total: number;
    active: number;
    used: number;
    expired: number;
    usage_rate: number;
}

interface Props {
    tokens: PaginatedCollection<PlayerActivationToken>;
    statistics: Statistics;
    filters: {
        status?: string;
        search?: string;
    };
}

export default function ActivationIndex({ tokens, statistics, filters }: Props) {
    const [loading, setLoading] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState(filters.search || '');

    const handleCreateToken = async (expiresHours: number = 24) => {
        setLoading('create');

        try {
            router.post('/activation', { expires_hours: expiresHours }, {
                onSuccess: () => {
                    setLoading(null);
                },
                onError: () => {
                    setLoading(null);
                }
            });
        } catch (error) {
            setLoading(null);
        }
    };

    const handleRevokeToken = async (token: string) => {
        if (!confirm('Tem certeza que deseja revogar este token?')) {
            return;
        }

        setLoading(`revoke-${token}`);

        try {
            router.delete(`/activation/${token}/revoke`, {
                onSuccess: () => {
                    setLoading(null);
                },
                onError: () => {
                    setLoading(null);
                }
            });
        } catch (error) {
            setLoading(null);
        }
    };

    const handleRegenerateQR = async (token: string) => {
        setLoading(`qr-${token}`);

        try {
            router.post(`/activation/${token}/regenerate-qr`, {}, {
                onSuccess: () => {
                    setLoading(null);
                },
                onError: () => {
                    setLoading(null);
                }
            });
        } catch (error) {
            setLoading(null);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/activation', {
            ...filters,
            search: searchQuery || undefined
        }, {
            preserveState: true
        });
    };

    const handleFilterStatus = (status: string | undefined) => {
        router.get('/activation', {
            ...filters,
            status: status || undefined
        }, {
            preserveState: true
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active':
                return 'bg-green-100 text-green-800';
            case 'used':
                return 'bg-blue-100 text-blue-800';
            case 'expired':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusText = (status: string) => {
        switch (status) {
            case 'active':
                return 'Ativo';
            case 'used':
                return 'Usado';
            case 'expired':
                return 'Expirado';
            default:
                return status;
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Tokens de Ativação
                </h2>
            }
        >
            <Head title="Tokens de Ativação" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Statistics */}
                    <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="bg-white p-6 shadow-sm rounded-lg">
                            <h3 className="text-sm font-medium text-gray-500">Total</h3>
                            <p className="text-2xl font-bold text-gray-900">{statistics.total}</p>
                        </div>
                        <div className="bg-white p-6 shadow-sm rounded-lg">
                            <h3 className="text-sm font-medium text-gray-500">Ativos</h3>
                            <p className="text-2xl font-bold text-green-600">{statistics.active}</p>
                        </div>
                        <div className="bg-white p-6 shadow-sm rounded-lg">
                            <h3 className="text-sm font-medium text-gray-500">Usados</h3>
                            <p className="text-2xl font-bold text-blue-600">{statistics.used}</p>
                        </div>
                        <div className="bg-white p-6 shadow-sm rounded-lg">
                            <h3 className="text-sm font-medium text-gray-500">Expirados</h3>
                            <p className="text-2xl font-bold text-red-600">{statistics.expired}</p>
                        </div>
                        <div className="bg-white p-6 shadow-sm rounded-lg">
                            <h3 className="text-sm font-medium text-gray-500">Taxa de Uso</h3>
                            <p className="text-2xl font-bold text-gray-900">{statistics.usage_rate}%</p>
                        </div>
                    </div>

                    <div className="bg-white shadow-sm rounded-lg">
                        {/* Header */}
                        <div className="px-6 py-4 border-b border-gray-200">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex-1">
                                    <form onSubmit={handleSearch} className="flex gap-4">
                                        <input
                                            type="text"
                                            placeholder="Buscar por token ou código..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <button
                                            type="submit"
                                            className="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                                        >
                                            Buscar
                                        </button>
                                    </form>
                                </div>
                                <div className="mt-4 sm:mt-0 sm:ml-4 flex gap-2">
                                    <button
                                        onClick={() => handleCreateToken(24)}
                                        disabled={loading === 'create'}
                                        className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {loading === 'create' ? 'Criando...' : 'Novo Token (24h)'}
                                    </button>
                                    <button
                                        onClick={() => handleCreateToken(168)}
                                        disabled={loading === 'create'}
                                        className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {loading === 'create' ? 'Criando...' : 'Novo Token (7 dias)'}
                                    </button>
                                </div>
                            </div>

                            {/* Filters */}
                            <div className="mt-4 flex gap-2">
                                <button
                                    onClick={() => handleFilterStatus(undefined)}
                                    className={`px-3 py-1 rounded-full text-sm ${!filters.status ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'}`}
                                >
                                    Todos
                                </button>
                                <button
                                    onClick={() => handleFilterStatus('active')}
                                    className={`px-3 py-1 rounded-full text-sm ${filters.status === 'active' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700'}`}
                                >
                                    Ativos
                                </button>
                                <button
                                    onClick={() => handleFilterStatus('used')}
                                    className={`px-3 py-1 rounded-full text-sm ${filters.status === 'used' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'}`}
                                >
                                    Usados
                                </button>
                                <button
                                    onClick={() => handleFilterStatus('expired')}
                                    className={`px-3 py-1 rounded-full text-sm ${filters.status === 'expired' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700'}`}
                                >
                                    Expirados
                                </button>
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Código
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Expira em
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Player
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            QR Code
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {tokens.data.map((token) => (
                                        <tr key={token.id}>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {token.activation_code}
                                                    </div>
                                                    <div className="text-sm text-gray-500 font-mono">
                                                        {token.token.substring(0, 8)}...
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex px-2 text-xs font-semibold rounded-full ${getStatusColor(token.status)}`}>
                                                    {getStatusText(token.status)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div>
                                                    {token.expires_in}
                                                </div>
                                                <div className="text-xs">
                                                    {format(new Date(token.expires_at), 'dd/MM/yyyy HH:mm', { locale: ptBR })}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {token.player ? token.player.name : 'Não ativado'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {token.qr_code_url && (
                                                    <div className="flex items-center gap-2">
                                                        <img
                                                            src={token.qr_code_url}
                                                            alt="QR Code"
                                                            className="w-8 h-8"
                                                        />
                                                        <button
                                                            onClick={() => handleRegenerateQR(token.token)}
                                                            disabled={loading === `qr-${token.token}` || token.status !== 'active'}
                                                            className="text-xs text-blue-600 hover:text-blue-900 disabled:opacity-50"
                                                        >
                                                            {loading === `qr-${token.token}` ? 'Regenerando...' : 'Regenerar'}
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={token.activation_url}
                                                        target="_blank"
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        Ver
                                                    </Link>
                                                    {token.qr_code_url && (
                                                        <Link
                                                            href={`/activation/${token.token}/download`}
                                                            className="text-green-600 hover:text-green-900"
                                                        >
                                                            Download
                                                        </Link>
                                                    )}
                                                    {token.status === 'active' && (
                                                        <button
                                                            onClick={() => handleRevokeToken(token.token)}
                                                            disabled={loading === `revoke-${token.token}`}
                                                            className="text-red-600 hover:text-red-900 disabled:opacity-50"
                                                        >
                                                            {loading === `revoke-${token.token}` ? 'Revogando...' : 'Revogar'}
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {tokens.data.length === 0 && (
                            <div className="px-6 py-8 text-center">
                                <p className="text-gray-500">Nenhum token encontrado.</p>
                            </div>
                        )}

                        {tokens.links && (
                            <div className="px-6 py-4 border-t border-gray-200">
                                <div className="flex items-center justify-between">
                                    <div className="text-sm text-gray-700">
                                        Mostrando {tokens.from} a {tokens.to} de {tokens.total} resultados
                                    </div>
                                    <div className="flex gap-2">
                                        {tokens.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={`px-3 py-1 text-sm rounded ${
                                                    link.active
                                                        ? 'bg-indigo-600 text-white'
                                                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                                } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}