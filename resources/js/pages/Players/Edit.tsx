import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    IconDevices,
    IconArrowLeft,
    IconQrcode,
    IconCopy,
    IconCheck,
    IconRefresh,
    IconWifi,
    IconWifiOff,
    IconSave,
    IconTrash
} from '@tabler/icons-react';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';

interface Player {
    id: number;
    name: string;
    alias?: string;
    location?: string;
    group?: string;
    status: string;
    ip_address?: string;
    last_seen?: string;
    app_version?: string;
    activation_token: string;
    device_info?: any;
    settings: {
        volume: number;
        sync_interval: number;
        display_mode: string;
        auto_restart: boolean;
        screenshot_interval: number;
    };
    is_online: boolean;
    last_seen_human: string;
    created_at: string;
    updated_at: string;
}

interface FormData {
    name: string;
    alias?: string;
    location?: string;
    group?: string;
    settings: {
        volume: number;
        sync_interval: number;
        display_mode: string;
        auto_restart: boolean;
        screenshot_interval: number;
    };
}

interface Props {
    player: Player;
    groups: string[];
}

export default function PlayersEdit({ player, groups }: Props) {
    const [loading, setLoading] = useState(false);
    const [tokenLoading, setTokenLoading] = useState(false);
    const [copySuccess, setCopySuccess] = useState(false);

    const form = useForm<FormData>({
        defaultValues: {
            name: player.name,
            alias: player.alias || '',
            location: player.location || '',
            group: player.group || '',
            settings: player.settings
        }
    });

    const onSubmit = async (data: FormData) => {
        setLoading(true);

        try {
            router.put(`/players/${player.id}`, data, {
                onSuccess: () => {
                    setLoading(false);
                },
                onError: (errors) => {
                    setLoading(false);
                    // Handle validation errors
                    Object.keys(errors).forEach((key) => {
                        if (key.includes('.')) {
                            const [parent, child] = key.split('.');
                            form.setError(`${parent}.${child}` as any, {
                                message: errors[key]
                            });
                        } else {
                            form.setError(key as any, {
                                message: errors[key]
                            });
                        }
                    });
                }
            });
        } catch (error) {
            setLoading(false);
        }
    };

    const handleRegenerateToken = async () => {
        if (!confirm('Tem certeza que deseja regenerar o token? O player precisará ser reativado.')) {
            return;
        }

        setTokenLoading(true);

        try {
            router.post(`/players/${player.id}/regenerate-token`, {}, {
                onSuccess: () => {
                    setTokenLoading(false);
                },
                onError: () => {
                    setTokenLoading(false);
                }
            });
        } catch (error) {
            setTokenLoading(false);
        }
    };

    const handleDeletePlayer = async () => {
        if (!confirm('Tem certeza que deseja excluir este player? Esta ação não pode ser desfeita.')) {
            return;
        }

        router.delete(`/players/${player.id}`, {
            onSuccess: () => {
                router.visit('/players');
            }
        });
    };

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopySuccess(true);
            setTimeout(() => setCopySuccess(false), 2000);
        } catch (error) {
            console.error('Failed to copy:', error);
        }
    };

    const displayModeOptions = [
        { value: 'fullscreen', label: 'Tela Cheia' },
        { value: 'windowed', label: 'Janela' },
        { value: 'kiosk', label: 'Modo Quiosque' },
    ];

    const getStatusBadge = () => {
        if (player.is_online) {
            return (
                <Badge variant="default" className="bg-green-600">
                    <IconWifi className="h-3 w-3 mr-1" />
                    Online
                </Badge>
            );
        } else {
            return (
                <Badge variant="secondary" className="bg-red-100 text-red-800">
                    <IconWifiOff className="h-3 w-3 mr-1" />
                    Offline
                </Badge>
            );
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Editar Player: ${player.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="mb-6 flex items-center justify-between">
                        <Button variant="outline" asChild>
                            <Link href="/players">
                                <IconArrowLeft className="h-4 w-4 mr-2" />
                                Voltar para Players
                            </Link>
                        </Button>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={handleRegenerateToken}
                                disabled={tokenLoading}
                            >
                                <IconRefresh className="h-4 w-4 mr-2" />
                                {tokenLoading ? 'Regenerando...' : 'Regenerar Token'}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleDeletePlayer}
                            >
                                <IconTrash className="h-4 w-4 mr-2" />
                                Excluir Player
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Main Form */}
                        <div className="lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconDevices className="h-5 w-5" />
                                        Editar Player: {player.name}
                                    </CardTitle>
                                    <CardDescription>
                                        Atualize as configurações do dispositivo Android
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form {...form}>
                                        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                                            {/* Basic Information */}
                                            <div className="space-y-4">
                                                <h3 className="text-lg font-medium">Informações Básicas</h3>

                                                <FormField
                                                    control={form.control}
                                                    name="name"
                                                    rules={{ required: 'Nome é obrigatório' }}
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Nome do Player *</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Ex: TV Sala Principal" {...field} />
                                                            </FormControl>
                                                            <FormDescription>
                                                                Nome identificador do dispositivo
                                                            </FormDescription>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="alias"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Apelido</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Ex: TV01" {...field} />
                                                            </FormControl>
                                                            <FormDescription>
                                                                Nome curto para identificação rápida
                                                            </FormDescription>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <FormField
                                                        control={form.control}
                                                        name="location"
                                                        render={({ field }) => (
                                                            <FormItem>
                                                                <FormLabel>Localização</FormLabel>
                                                                <FormControl>
                                                                    <Input placeholder="Ex: Sala de Espera" {...field} />
                                                                </FormControl>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />

                                                    <FormField
                                                        control={form.control}
                                                        name="group"
                                                        render={({ field }) => (
                                                            <FormItem>
                                                                <FormLabel>Grupo</FormLabel>
                                                                <Select onValueChange={field.onChange} defaultValue={field.value}>
                                                                    <FormControl>
                                                                        <SelectTrigger>
                                                                            <SelectValue placeholder="Selecionar grupo" />
                                                                        </SelectTrigger>
                                                                    </FormControl>
                                                                    <SelectContent>
                                                                        {groups.map((group) => (
                                                                            <SelectItem key={group} value={group}>
                                                                                {group}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />
                                                </div>
                                            </div>

                                            {/* Settings */}
                                            <div className="space-y-4 border-t pt-6">
                                                <h3 className="text-lg font-medium">Configurações</h3>

                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <FormField
                                                        control={form.control}
                                                        name="settings.volume"
                                                        render={({ field }) => (
                                                            <FormItem>
                                                                <FormLabel>Volume Padrão (%)</FormLabel>
                                                                <FormControl>
                                                                    <Input
                                                                        type="number"
                                                                        min="0"
                                                                        max="100"
                                                                        {...field}
                                                                        onChange={e => field.onChange(parseInt(e.target.value))}
                                                                    />
                                                                </FormControl>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />

                                                    <FormField
                                                        control={form.control}
                                                        name="settings.sync_interval"
                                                        render={({ field }) => (
                                                            <FormItem>
                                                                <FormLabel>Intervalo de Sinc. (min)</FormLabel>
                                                                <FormControl>
                                                                    <Input
                                                                        type="number"
                                                                        min="1"
                                                                        max="60"
                                                                        {...field}
                                                                        onChange={e => field.onChange(parseInt(e.target.value))}
                                                                    />
                                                                </FormControl>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />

                                                    <FormField
                                                        control={form.control}
                                                        name="settings.display_mode"
                                                        render={({ field }) => (
                                                            <FormItem>
                                                                <FormLabel>Modo de Exibição</FormLabel>
                                                                <Select onValueChange={field.onChange} defaultValue={field.value}>
                                                                    <FormControl>
                                                                        <SelectTrigger>
                                                                            <SelectValue />
                                                                        </SelectTrigger>
                                                                    </FormControl>
                                                                    <SelectContent>
                                                                        {displayModeOptions.map((option) => (
                                                                            <SelectItem key={option.value} value={option.value}>
                                                                                {option.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />

                                                    <FormField
                                                        control={form.control}
                                                        name="settings.screenshot_interval"
                                                        render={({ field }) => (
                                                            <FormItem>
                                                                <FormLabel>Screenshot (min)</FormLabel>
                                                                <FormControl>
                                                                    <Input
                                                                        type="number"
                                                                        min="0"
                                                                        max="60"
                                                                        {...field}
                                                                        onChange={e => field.onChange(parseInt(e.target.value))}
                                                                    />
                                                                </FormControl>
                                                                <FormDescription>
                                                                    0 = desabilitado
                                                                </FormDescription>
                                                                <FormMessage />
                                                            </FormItem>
                                                        )}
                                                    />
                                                </div>

                                                <FormField
                                                    control={form.control}
                                                    name="settings.auto_restart"
                                                    render={({ field }) => (
                                                        <FormItem className="flex flex-row items-center justify-between rounded-lg border p-4">
                                                            <div className="space-y-0.5">
                                                                <FormLabel className="text-base">
                                                                    Reinício Automático
                                                                </FormLabel>
                                                                <FormDescription>
                                                                    Reiniciar automaticamente em caso de falha
                                                                </FormDescription>
                                                            </div>
                                                            <FormControl>
                                                                <input
                                                                    type="checkbox"
                                                                    checked={field.value}
                                                                    onChange={field.onChange}
                                                                    className="rounded"
                                                                />
                                                            </FormControl>
                                                        </FormItem>
                                                    )}
                                                />
                                            </div>

                                            {/* Submit Buttons */}
                                            <div className="flex gap-4 pt-6">
                                                <Button type="submit" disabled={loading} className="flex-1">
                                                    <IconSave className="h-4 w-4 mr-2" />
                                                    {loading ? 'Salvando...' : 'Salvar Alterações'}
                                                </Button>
                                                <Button type="button" variant="outline" asChild>
                                                    <Link href="/players">Cancelar</Link>
                                                </Button>
                                            </div>
                                        </form>
                                    </Form>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Player Info and Token */}
                        <div className="space-y-6">
                            {/* Player Status */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconDevices className="h-5 w-5" />
                                        Status do Player
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-gray-600">Status:</span>
                                            {getStatusBadge()}
                                        </div>

                                        {player.last_seen && (
                                            <div>
                                                <span className="text-sm text-gray-600">Última atividade:</span>
                                                <div className="text-sm font-medium">{player.last_seen_human}</div>
                                                <div className="text-xs text-gray-500">
                                                    {format(new Date(player.last_seen), 'dd/MM/yyyy HH:mm', { locale: ptBR })}
                                                </div>
                                            </div>
                                        )}

                                        {player.ip_address && (
                                            <div>
                                                <span className="text-sm text-gray-600">IP Address:</span>
                                                <div className="text-sm font-medium font-mono">{player.ip_address}</div>
                                            </div>
                                        )}

                                        {player.app_version && (
                                            <div>
                                                <span className="text-sm text-gray-600">Versão do App:</span>
                                                <div className="text-sm font-medium">{player.app_version}</div>
                                            </div>
                                        )}

                                        <div>
                                            <span className="text-sm text-gray-600">Criado em:</span>
                                            <div className="text-sm font-medium">
                                                {format(new Date(player.created_at), 'dd/MM/yyyy HH:mm', { locale: ptBR })}
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Token Management */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconQrcode className="h-5 w-5" />
                                        Token de Ativação
                                    </CardTitle>
                                    <CardDescription>
                                        Código atual para ativação do player
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="text-center">
                                            <div className="w-32 h-32 mx-auto bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                                                <IconQrcode className="h-16 w-16 text-gray-400" />
                                            </div>
                                            <p className="text-sm text-gray-600 mb-2">QR Code do token atual</p>
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-sm font-medium">Token de Ativação:</label>
                                            <div className="flex gap-2">
                                                <Input
                                                    value={player.activation_token}
                                                    readOnly
                                                    className="font-mono text-center text-xs"
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => copyToClipboard(player.activation_token)}
                                                >
                                                    {copySuccess ? (
                                                        <IconCheck className="h-4 w-4 text-green-600" />
                                                    ) : (
                                                        <IconCopy className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleRegenerateToken}
                                            disabled={tokenLoading}
                                            className="w-full"
                                        >
                                            <IconRefresh className="h-4 w-4 mr-2" />
                                            {tokenLoading ? 'Regenerando...' : 'Regenerar Token'}
                                        </Button>

                                        <div className="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <p className="text-sm text-yellow-700">
                                                ⚠️ Regenerar o token desconectará o player e exigirá nova ativação.
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Device Info */}
                            {player.device_info && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Informações do Dispositivo</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2 text-sm">
                                            {Object.entries(player.device_info).map(([key, value]) => (
                                                <div key={key} className="flex justify-between">
                                                    <span className="text-gray-600 capitalize">{key.replace('_', ' ')}:</span>
                                                    <span className="font-medium">{String(value)}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}