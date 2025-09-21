import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import ClientLayout from "@/Layouts/ClientLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Separator } from "@/components/ui/separator";
import { IconArrowLeft, IconSave, IconRefresh, IconTrash } from "@tabler/icons-react";
import { toast } from "sonner";
import ActivationQRCode from "@/components/players/ActivationQRCode";

interface Player {
    id: number;
    name: string;
    description?: string;
    location?: string;
    activation_code: string;
    is_online: boolean;
    settings: {
        volume: number;
        sync_interval: number;
        display_mode: string;
        auto_restart: boolean;
        debug_mode: boolean;
    };
}

interface EditProps {
    player: Player;
}

export default function Edit({ player }: EditProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: player.name,
        description: player.description || "",
        location: player.location || "",
        settings: player.settings,
    });

    const [showAdvanced, setShowAdvanced] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        put(route("players.update", player.id), {
            onSuccess: () => {
                toast.success("Player atualizado com sucesso!");
            },
            onError: () => {
                toast.error("Erro ao atualizar player. Verifique os dados.");
            }
        });
    };

    const regenerateCode = () => {
        router.post(route("players.regenerate-token", player.id), {}, {
            onSuccess: () => {
                toast.success("Código de ativação regenerado!");
            },
            onError: () => {
                toast.error("Erro ao regenerar código.");
            }
        });
    };

    return (
        <ClientLayout>
            <Head title={`Editar ${player.name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <a href={route("players.show", player.id)}>
                            <IconArrowLeft className="h-4 w-4 mr-2" />
                            Voltar
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Editar Player</h1>
                        <p className="text-gray-600">
                            Atualize as informações do player {player.name}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Form */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Informações do Player</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Nome *</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData("name", e.target.value)}
                                        placeholder="Ex: Player Recepção"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-red-600">{errors.name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="description">Descrição</Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData("description", e.target.value)}
                                        placeholder="Descrição opcional do player"
                                        rows={3}
                                    />
                                    {errors.description && (
                                        <p className="text-sm text-red-600">{errors.description}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="location">Localização</Label>
                                    <Input
                                        id="location"
                                        value={data.location}
                                        onChange={(e) => setData("location", e.target.value)}
                                        placeholder="Ex: Sala de Espera, Andar 2"
                                    />
                                    {errors.location && (
                                        <p className="text-sm text-red-600">{errors.location}</p>
                                    )}
                                </div>

                                <Separator />

                                {/* Advanced Settings */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <Label className="text-base font-medium">Configurações</Label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setShowAdvanced(!showAdvanced)}
                                        >
                                            {showAdvanced ? "Ocultar" : "Mostrar"}
                                        </Button>
                                    </div>

                                    {showAdvanced && (
                                        <div className="space-y-4 pt-4 border-t">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="volume">Volume Padrão (%)</Label>
                                                    <Input
                                                        id="volume"
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        value={data.settings.volume}
                                                        onChange={(e) => setData("settings", {
                                                            ...data.settings,
                                                            volume: parseInt(e.target.value) || 0
                                                        })}
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="sync_interval">Intervalo de Sincronização (min)</Label>
                                                    <Input
                                                        id="sync_interval"
                                                        type="number"
                                                        min="1"
                                                        max="1440"
                                                        value={data.settings.sync_interval}
                                                        onChange={(e) => setData("settings", {
                                                            ...data.settings,
                                                            sync_interval: parseInt(e.target.value) || 30
                                                        })}
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex items-center space-x-4">
                                                <label className="flex items-center space-x-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.settings.auto_restart}
                                                        onChange={(e) => setData("settings", {
                                                            ...data.settings,
                                                            auto_restart: e.target.checked
                                                        })}
                                                        className="rounded"
                                                    />
                                                    <span className="text-sm">Reinício Automático</span>
                                                </label>

                                                <label className="flex items-center space-x-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.settings.debug_mode}
                                                        onChange={(e) => setData("settings", {
                                                            ...data.settings,
                                                            debug_mode: e.target.checked
                                                        })}
                                                        className="rounded"
                                                    />
                                                    <span className="text-sm">Modo Debug</span>
                                                </label>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <Separator />

                                <div className="flex justify-end gap-2">
                                    <Button type="button" variant="outline" asChild>
                                        <a href={route("players.show", player.id)}>
                                            Cancelar
                                        </a>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <IconSave className="h-4 w-4 mr-2" />
                                        {processing ? "Salvando..." : "Salvar Alterações"}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* QR Code and Actions */}
                    <div className="space-y-6">
                        <ActivationQRCode
                            activationCode={player.activation_code}
                            playerName={player.name}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Ações do Player</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Button
                                    variant="outline"
                                    onClick={regenerateCode}
                                    className="w-full"
                                >
                                    <IconRefresh className="h-4 w-4 mr-2" />
                                    Regenerar Código de Ativação
                                </Button>

                                {player.is_online && (
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            router.post(route("players.restart", player.id));
                                        }}
                                        className="w-full"
                                    >
                                        <IconRefresh className="h-4 w-4 mr-2" />
                                        Reiniciar Player
                                    </Button>
                                )}

                                <Button
                                    variant="destructive"
                                    onClick={() => {
                                        if (confirm(`Deseja excluir o player "${player.name}"?`)) {
                                            router.delete(route("players.destroy", player.id));
                                        }
                                    }}
                                    className="w-full"
                                >
                                    <IconTrash className="h-4 w-4 mr-2" />
                                    Excluir Player
                                </Button>

                                <div className="text-sm text-gray-600 mt-4">
                                    <p><strong>Atenção:</strong></p>
                                    <ul className="list-disc list-inside space-y-1 ml-2">
                                        <li>Regenerar o código requer nova ativação</li>
                                        <li>Reiniciar afeta apenas players online</li>
                                        <li>Excluir remove permanentemente o player</li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}