import React from 'react';
import { Head, Link } from '@inertiajs/react';

interface ApkVersion {
    id: number;
    version: string;
    build_number: number;
    file_path: string;
    file_size: number;
    formatted_size: string;
    changelog?: string;
    is_active: boolean;
    download_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    apkVersion: ApkVersion;
    download_url: string;
    qr_code_url?: string;
}

export default function Show({ apkVersion, download_url, qr_code_url }: Props) {
    return (
        <div className="container mx-auto py-8 space-y-6">
            <Head title={`APK v${apkVersion.version}`} />

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h1 className="text-2xl font-bold">
                                    APK Versão {apkVersion.version}
                                </h1>
                                <div className="flex space-x-2">
                                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                                        apkVersion.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-800'
                                    }`}>
                                        {apkVersion.is_active ? 'Ativa' : 'Inativa'}
                                    </span>
                                    <Link
                                        href={route('admin.apk.edit', apkVersion.id)}
                                        className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                                    >
                                        Editar
                                    </Link>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                                <div className="bg-blue-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-blue-600">Versão</h3>
                                    <p className="text-2xl font-bold text-blue-900">{apkVersion.version}</p>
                                </div>
                                <div className="bg-green-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-green-600">Build</h3>
                                    <p className="text-2xl font-bold text-green-900">{apkVersion.build_number}</p>
                                </div>
                                <div className="bg-yellow-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-yellow-600">Tamanho</h3>
                                    <p className="text-2xl font-bold text-yellow-900">{apkVersion.formatted_size}</p>
                                </div>
                                <div className="bg-purple-50 p-4 rounded-lg">
                                    <h3 className="text-sm font-medium text-purple-600">Downloads</h3>
                                    <p className="text-2xl font-bold text-purple-900">{apkVersion.download_count}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div className="bg-gray-50 p-6 rounded-lg">
                                    <h2 className="text-lg font-semibold mb-4">Informações do APK</h2>
                                    <dl className="space-y-2">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Versão</dt>
                                            <dd className="text-sm text-gray-900">{apkVersion.version}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Número do Build</dt>
                                            <dd className="text-sm text-gray-900">{apkVersion.build_number}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Tamanho do Arquivo</dt>
                                            <dd className="text-sm text-gray-900">{apkVersion.formatted_size}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Total de Downloads</dt>
                                            <dd className="text-sm text-gray-900">{apkVersion.download_count}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Data de Upload</dt>
                                            <dd className="text-sm text-gray-900">
                                                {new Date(apkVersion.created_at).toLocaleDateString('pt-BR')}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Última Atualização</dt>
                                            <dd className="text-sm text-gray-900">
                                                {new Date(apkVersion.updated_at).toLocaleDateString('pt-BR')}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div className="bg-gray-50 p-6 rounded-lg">
                                    <h2 className="text-lg font-semibold mb-4">Ações</h2>
                                    <div className="space-y-3">
                                        <a
                                            href={download_url}
                                            className="block w-full bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-center"
                                            download
                                        >
                                            Download APK
                                        </a>

                                        {qr_code_url && (
                                            <Link
                                                href={qr_code_url}
                                                className="block w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center"
                                            >
                                                Ver QR Code
                                            </Link>
                                        )}

                                        <Link
                                            href={route('admin.apk.edit', apkVersion.id)}
                                            className="block w-full bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded text-center"
                                        >
                                            Editar
                                        </Link>

                                        {!apkVersion.is_active && (
                                            <Link
                                                href={route('admin.apk.activate', apkVersion.id)}
                                                method="post"
                                                className="block w-full bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-center"
                                            >
                                                Ativar Versão
                                            </Link>
                                        )}

                                        {apkVersion.is_active && (
                                            <Link
                                                href={route('admin.apk.deactivate', apkVersion.id)}
                                                method="post"
                                                className="block w-full bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-center"
                                            >
                                                Desativar Versão
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {apkVersion.changelog && (
                                <div className="mt-6">
                                    <h2 className="text-lg font-semibold mb-4">Changelog</h2>
                                    <div className="bg-gray-50 p-6 rounded-lg">
                                        <pre className="whitespace-pre-wrap text-sm text-gray-700">
                                            {apkVersion.changelog}
                                        </pre>
                                    </div>
                                </div>
                            )}
                </div>
            </div>
        </div>
    );
}