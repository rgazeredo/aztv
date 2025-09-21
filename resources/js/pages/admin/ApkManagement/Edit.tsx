import React from 'react';
import { Head, useForm } from '@inertiajs/react';

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
}

export default function Edit({ apkVersion }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        version: apkVersion.version || '',
        changelog: apkVersion.changelog || '',
        is_active: apkVersion.is_active || false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.apk.update', apkVersion.id));
    };

    return (
        <div className="container mx-auto py-8 space-y-6">
            <Head title={`Editar APK v${apkVersion.version}`} />

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h1 className="text-2xl font-bold">
                                    Editar APK v{apkVersion.version}
                                </h1>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label htmlFor="version" className="block text-sm font-medium text-gray-700">
                                            Versão
                                        </label>
                                        <input
                                            type="text"
                                            id="version"
                                            value={data.version}
                                            onChange={(e) => setData('version', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="1.0.0"
                                        />
                                        {errors.version && (
                                            <div className="text-red-600 text-sm mt-1">{errors.version}</div>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="is_active" className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="is_active"
                                                checked={data.is_active}
                                                onChange={(e) => setData('is_active', e.target.checked)}
                                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">
                                                Versão Ativa
                                            </span>
                                        </label>
                                        {errors.is_active && (
                                            <div className="text-red-600 text-sm mt-1">{errors.is_active}</div>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label htmlFor="changelog" className="block text-sm font-medium text-gray-700">
                                        Changelog
                                    </label>
                                    <textarea
                                        id="changelog"
                                        rows={6}
                                        value={data.changelog}
                                        onChange={(e) => setData('changelog', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Descreva as mudanças nesta versão..."
                                    />
                                    {errors.changelog && (
                                        <div className="text-red-600 text-sm mt-1">{errors.changelog}</div>
                                    )}
                                </div>

                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">Informações do Arquivo</h3>
                                    <dl className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Build Number</dt>
                                            <dd className="text-sm text-gray-900">{apkVersion.build_number}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Tamanho</dt>
                                            <dd className="text-sm text-gray-900">{apkVersion.formatted_size}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Downloads</dt>
                                            <dd className="text-sm text-gray-900">{apkVersion.download_count}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div className="flex justify-end space-x-3">
                                    <button
                                        type="button"
                                        onClick={() => window.history.back()}
                                        className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                                    >
                                        {processing ? 'Salvando...' : 'Salvar Alterações'}
                                    </button>
                                </div>
                            </form>
                </div>
            </div>
        </div>
    );
}