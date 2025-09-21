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
// import { Textarea } from '@/components/ui/textarea';
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
    IconX,
    IconRefresh
} from '@tabler/icons-react';
import { cn } from '@/lib/utils';

interface FormData {
    name: string;
    alias?: string;
    location?: string;
    group?: string;
    description?: string;
    settings: {
        volume: number;
        sync_interval: number;
        display_mode: string;
        auto_restart: boolean;
        screenshot_interval: number;
    };
}

interface Props {
    groups: string[];
    defaultSettings: {
        volume: number;
        sync_interval: number;
        display_mode: string;
        auto_restart: boolean;
        screenshot_interval: number;
    };
}

export default function PlayersCreate({ groups, defaultSettings }: Props) {
    const [loading, setLoading] = useState(false);
    const [generatedToken, setGeneratedToken] = useState<string | null>(null);
    const [copySuccess, setCopySuccess] = useState(false);

    const form = useForm<FormData>({
        defaultValues: {
            name: '',
            alias: '',
            location: '',
            group: '',
            description: '',
            settings: defaultSettings
        }
    });

    const onSubmit = async (data: FormData) => {
        setLoading(true);

        try {
            router.post('/players', data, {
                onSuccess: (page) => {
                    // If the response includes the created player with token
                    const createdPlayer = page.props.player as any;
                    if (createdPlayer?.activation_token) {
                        setGeneratedToken(createdPlayer.activation_token);
                    }
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

    const generatePreviewToken = () => {
        // Generate a preview token for demonstration
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = '';
        for (let i = 0; i < 8; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        setGeneratedToken(result);
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

    return (
        <AuthenticatedLayout>
            <Head title="Criar Novo Player" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <Button variant="outline" asChild>
                            <Link href="/players">
                                <IconArrowLeft className="h-4 w-4 mr-2" />
                                Voltar para Players
                            </Link>
                        </Button>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Main Form */}
                        <div className="lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconDevices className="h-5 w-5" />
                                        Criar Novo Player
                                    </CardTitle>
                                    <CardDescription>
                                        Configure um novo dispositivo Android para o sistema
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

                                                <FormField
                                                    control={form.control}
                                                    name="description"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Descrição</FormLabel>
                                                            <FormControl>
                                                                <Input
                                                                    placeholder="Descrição detalhada do player..."
                                                                    {...field}
                                                                />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />
                                            </div>

                                            {/* Settings */}
                                            <div className="space-y-4 border-t pt-6">
                                                <h3 className="text-lg font-medium">Configurações Iniciais</h3>

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
                                                    {loading ? 'Criando...' : 'Criar Player'}
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

                        {/* Preview and QR Code */}
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconQrcode className="h-5 w-5" />
                                        Código de Ativação
                                    </CardTitle>
                                    <CardDescription>
                                        Token gerado após criar o player
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {generatedToken ? (
                                        <div className="space-y-4">
                                            <div className="text-center">
                                                <div className="w-32 h-32 mx-auto bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                                                    <IconQrcode className="h-16 w-16 text-gray-400" />
                                                </div>
                                                <p className="text-sm text-gray-600 mb-2">QR Code será gerado</p>
                                            </div>

                                            <div className="space-y-2">
                                                <label className="text-sm font-medium">Código de Ativação:</label>
                                                <div className="flex gap-2">
                                                    <Input
                                                        value={generatedToken}
                                                        readOnly
                                                        className="font-mono text-center"
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => copyToClipboard(generatedToken)}
                                                    >
                                                        {copySuccess ? (
                                                            <IconCheck className="h-4 w-4 text-green-600" />
                                                        ) : (
                                                            <IconCopy className="h-4 w-4" />
                                                        )}
                                                    </Button>
                                                </div>
                                            </div>

                                            <div className="p-3 bg-green-50 border border-green-200 rounded-lg">
                                                <p className="text-sm text-green-700">
                                                    ✓ Player criado com sucesso! Use este código para ativar o dispositivo.
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-center space-y-4">
                                            <div className="w-32 h-32 mx-auto bg-gray-100 rounded-lg flex items-center justify-center">
                                                <IconQrcode className="h-16 w-16 text-gray-400" />
                                            </div>
                                            <p className="text-sm text-gray-600">
                                                O código de ativação será gerado após criar o player
                                            </p>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={generatePreviewToken}
                                            >
                                                <IconRefresh className="h-4 w-4 mr-2" />
                                                Preview
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Instructions */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Como Ativar</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3 text-sm">
                                        <div className="flex gap-3">
                                            <span className="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">1</span>
                                            <span>Instale o app AZ TV Player no dispositivo Android</span>
                                        </div>
                                        <div className="flex gap-3">
                                            <span className="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">2</span>
                                            <span>Abra o app e escaneie o QR Code ou digite o código</span>
                                        </div>
                                        <div className="flex gap-3">
                                            <span className="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">3</span>
                                            <span>Aguarde a sincronização automática</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}