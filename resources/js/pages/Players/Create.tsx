import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { IconArrowLeft, IconSave, IconRefresh } from "@tabler/icons-react";
import { toast } from "sonner";
import ActivationQRCode from "@/components/players/ActivationQRCode";

interface CreateProps {
    groups?: string[];
    activationCode?: string;
}

export default function Create({ groups = [], activationCode }: CreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        description: "",
        location: "",
        group: "",
        activation_code: activationCode || "",
        settings: {
            volume: 80,
            sync_interval: 30,
            display_mode: "fullscreen",
            auto_restart: true,
            debug_mode: false,
        }
    });

    const [showAdvanced, setShowAdvanced] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(route("players.store"), {
            onSuccess: () => {
                toast.success("Player criado com sucesso!");
            },
            onError: () => {
                toast.error("Erro ao criar player. Verifique os dados.");
            }
        });
    };

    const generateNewCode = () => {
        router.reload({
            data: { generate_code: true },
            onSuccess: () => {
                toast.success("Novo código gerado!");
            }
        });
    };

    return (
        <ClientLayout>
            <Head title="Novo Player" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <a href={route("players.index")}>
                            <IconArrowLeft className="h-4 w-4 mr-2" />
                            Voltar
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Novo Player</h1>
                        <p className="text-gray-600">
                            Cadastre um novo dispositivo player na sua rede
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

                                {groups.length > 0 && (
                                    <div className="space-y-2">
                                        <Label htmlFor="group">Grupo</Label>
                                        <Select value={data.group} onValueChange={(value) => setData("group", value)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Selecione um grupo" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">Nenhum grupo</SelectItem>
                                                {groups.map((group) => (
                                                    <SelectItem key={group} value={group}>
                                                        {group}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.group && (
                                            <p className="text-sm text-red-600">{errors.group}</p>
                                        )}
                                    </div>
                                )}

                                <Separator />

                                {/* Advanced Settings */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <Label className="text-base font-medium">Configurações Avançadas</Label>
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

                                            <div className="space-y-2">
                                                <Label htmlFor="display_mode">Modo de Exibição</Label>
                                                <Select
                                                    value={data.settings.display_mode}
                                                    onValueChange={(value) => setData("settings", {
                                                        ...data.settings,
                                                        display_mode: value
                                                    })}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="fullscreen">Tela Cheia</SelectItem>
                                                        <SelectItem value="windowed">Janela</SelectItem>
                                                        <SelectItem value="kiosk">Modo Quiosque</SelectItem>
                                                    </SelectContent>
                                                </Select>
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
                                        <a href={route("players.index")}>
                                            Cancelar
                                        </a>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <IconSave className="h-4 w-4 mr-2" />
                                        {processing ? "Salvando..." : "Criar Player"}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* QR Code Preview */}
                    <div className="space-y-6">
                        {data.activation_code && (
                            <ActivationQRCode
                                activationCode={data.activation_code}
                                playerName={data.name || "Novo Player"}
                            />
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Código de Ativação</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="activation_code">Código</Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="activation_code"
                                            value={data.activation_code}
                                            onChange={(e) => setData("activation_code", e.target.value)}
                                            placeholder="Código será gerado automaticamente"
                                            readOnly
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={generateNewCode}
                                        >
                                            <IconRefresh className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    {errors.activation_code && (
                                        <p className="text-sm text-red-600">{errors.activation_code}</p>
                                    )}
                                </div>

                                <div className="text-sm text-gray-600 space-y-2">
                                    <p><strong>Como usar:</strong></p>
                                    <ol className="list-decimal list-inside space-y-1 ml-2">
                                        <li>Instale o app AZ TV Player no dispositivo Android</li>
                                        <li>Abra o app e toque em "Ativar Player"</li>
                                        <li>Escaneie o QR Code ou digite o código manualmente</li>
                                        <li>O player será ativado automaticamente</li>
                                    </ol>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </ClientLayout>
    );
}