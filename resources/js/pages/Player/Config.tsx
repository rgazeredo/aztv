import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Settings, Save, X, Monitor, Info } from 'lucide-react';

interface Player {
    id: number;
    name: string;
    alias?: string;
}

interface SettingConfig {
    type: string;
    default: any;
    description?: string;
    options?: string[];
    current_value: any;
    is_custom: boolean;
    tenant_default: any;
    source: string;
}

interface SettingCategory {
    name: string;
    settings: Record<string, SettingConfig>;
}

interface AvailableSetting {
    key: string;
    type: string;
    default_value: any;
    description?: string;
    options?: string[];
}

interface Props {
    player: Player;
    settingsWithMetadata: Record<string, SettingCategory>;
    availableSettings: AvailableSetting[];
}

export default function Config({ player, settingsWithMetadata, availableSettings }: Props) {
    // Flatten the categorized settings for form initialization
    const initialData = Object.values(settingsWithMetadata).reduce((acc, category) => {
        Object.entries(category.settings).forEach(([key, setting]) => {
            acc[key] = setting.current_value;
        });
        return acc;
    }, {} as Record<string, any>);

    const { data, setData, put, processing, errors } = useForm(initialData);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Players',
            href: '/players',
        },
        {
            title: player.name,
            href: `/players/${player.id}`,
        },
        {
            title: 'Configurações',
            href: `/players/${player.id}/config`,
        },
    ];

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/players/${player.id}/config`, {
            data: {
                settings: data
            }
        });
    };

    const getInputComponent = (settingKey: string, setting: SettingConfig) => {
        const currentValue = data[settingKey];

        switch (setting.type) {
            case 'boolean':
                return (
                    <Select
                        value={currentValue?.toString() || 'false'}
                        onValueChange={(value) => setData(settingKey, value === 'true')}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="true">Sim</SelectItem>
                            <SelectItem value="false">Não</SelectItem>
                        </SelectContent>
                    </Select>
                );
            case 'select':
                return (
                    <Select
                        value={currentValue?.toString() || ''}
                        onValueChange={(value) => setData(settingKey, value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Selecione uma opção" />
                        </SelectTrigger>
                        <SelectContent>
                            {setting.options?.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                );
            case 'number':
                return (
                    <Input
                        type="number"
                        value={currentValue || ''}
                        onChange={(e) => setData(settingKey, parseInt(e.target.value) || 0)}
                        placeholder={`Padrão: ${setting.default}`}
                    />
                );
            default:
                return (
                    <Input
                        type="text"
                        value={currentValue || ''}
                        onChange={(e) => setData(settingKey, e.target.value)}
                        placeholder={`Padrão: ${setting.default}`}
                    />
                );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Configurações - ${player.name}`} />
            <div className="flex-1 space-y-4 p-4 pt-6">

            {/* Header */}
            <div className="flex items-center justify-between space-y-2">
                <div>
                    <h2 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                        <Settings className="h-8 w-8" />
                        Configurações - {player.name}
                    </h2>
                    <p className="text-muted-foreground">
                        Configure as opções específicas do player
                    </p>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-6">
                {Object.entries(settingsWithMetadata).map(([categoryKey, category]) => (
                    <Card key={categoryKey}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Monitor className="h-5 w-5" />
                                {category.name}
                            </CardTitle>
                            <CardDescription>
                                Configurações da categoria {category.name.toLowerCase()}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-6">
                                {Object.entries(category.settings).map(([settingKey, setting]) => (
                                    <div key={settingKey} className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Label htmlFor={settingKey} className="text-sm font-medium">
                                                {settingKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                            </Label>
                                            {setting.is_custom && (
                                                <Badge variant="outline" className="text-xs">
                                                    Personalizado
                                                </Badge>
                                            )}
                                        </div>

                                        {getInputComponent(settingKey, setting)}

                                        {setting.description && (
                                            <p className="text-xs text-muted-foreground flex items-center gap-1">
                                                <Info className="h-3 w-3" />
                                                {setting.description}
                                            </p>
                                        )}

                                        {errors[settingKey] && (
                                            <p className="text-sm text-destructive">{errors[settingKey]}</p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                ))}

                <div className="flex justify-end space-x-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.history.back()}
                    >
                        <X className="mr-2 h-4 w-4" />
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                    >
                        <Save className="mr-2 h-4 w-4" />
                        {processing ? 'Salvando...' : 'Salvar Configurações'}
                    </Button>
                </div>
            </form>
            </div>
        </AppLayout>
    );
}