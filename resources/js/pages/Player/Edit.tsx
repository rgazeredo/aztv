import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Edit as EditIcon, Save, X, Monitor } from 'lucide-react';

interface Player {
    id: number;
    name: string;
    alias?: string;
    location?: string;
    group?: string;
    description?: string;
}

interface Props {
    player: Player;
    groups?: string[];
}

export default function Edit({ player, groups = [] }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: player.name || '',
        alias: player.alias || '',
        location: player.location || '',
        group: player.group || '',
        description: player.description || '',
    });

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
            title: 'Editar',
            href: `/players/${player.id}/edit`,
        },
    ];

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/players/${player.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Player: ${player.name}`} />
            <div className="flex-1 space-y-4 p-4 pt-6">

            {/* Header */}
            <div className="flex items-center justify-between space-y-2">
                <div>
                    <h2 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                        <EditIcon className="h-8 w-8" />
                        Editar Player: {player.name}
                    </h2>
                    <p className="text-muted-foreground">
                        Atualize as informações do player
                    </p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Monitor className="h-5 w-5" />
                        Informações do Player
                    </CardTitle>
                    <CardDescription>
                        Edite os dados básicos do player
                    </CardDescription>
                </CardHeader>
                <CardContent>

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Nome do player"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="alias">Alias</Label>
                                <Input
                                    id="alias"
                                    value={data.alias}
                                    onChange={(e) => setData('alias', e.target.value)}
                                    placeholder="Nome alternativo"
                                />
                                {errors.alias && (
                                    <p className="text-sm text-destructive">{errors.alias}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <Label htmlFor="location">Localização</Label>
                                <Input
                                    id="location"
                                    value={data.location}
                                    onChange={(e) => setData('location', e.target.value)}
                                    placeholder="Ex: Sala de reuniões, Recepção, etc."
                                />
                                {errors.location && (
                                    <p className="text-sm text-destructive">{errors.location}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="group">Grupo</Label>
                                <Input
                                    id="group"
                                    value={data.group}
                                    onChange={(e) => setData('group', e.target.value)}
                                    placeholder="Digite um grupo ou selecione existente"
                                    list="existing-groups"
                                />
                                <datalist id="existing-groups">
                                    {groups.map((group) => (
                                        <option key={group} value={group} />
                                    ))}
                                </datalist>
                                {errors.group && (
                                    <p className="text-sm text-destructive">{errors.group}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Descrição</Label>
                            <textarea
                                id="description"
                                rows={3}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Descrição adicional sobre o player..."
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">{errors.description}</p>
                            )}
                        </div>

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
                                {processing ? 'Salvando...' : 'Salvar Alterações'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
            </div>
        </AppLayout>
    );
}