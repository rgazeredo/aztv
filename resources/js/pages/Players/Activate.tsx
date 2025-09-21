import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import ClientLayout from "@/Layouts/ClientLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    IconArrowLeft,
    IconScan,
    IconKey,
    IconCheck,
    IconClock,
    IconDeviceGamepad2
} from "@tabler/icons-react";
import { toast } from "sonner";

interface PendingPlayer {
    id: number;
    name: string;
    activation_code: string;
    created_at: string;
}

interface ActivateProps {
    pendingPlayers: PendingPlayer[];
}

export default function Activate({ pendingPlayers }: ActivateProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        activation_code: "",
    });

    const [scannerActive, setScannerActive] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.activation_code.trim()) {
            toast.error("Por favor, digite ou escaneie um código de ativação");
            return;
        }

        post(route("players.activate"), {
            onSuccess: () => {
                toast.success("Player ativado com sucesso!");
                reset();
            },
            onError: () => {
                toast.error("Código de ativação inválido ou expirado");
            }
        });
    };

    const handleQuickActivate = (code: string) => {
        setData("activation_code", code);

        post(route("players.activate"), {
            data: { activation_code: code },
            onSuccess: () => {
                toast.success("Player ativado com sucesso!");
                reset();
            },
            onError: () => {
                toast.error("Erro ao ativar player");
            }
        });
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString("pt-BR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    };

    // Simular scanner QR Code
    const startScanner = () => {
        setScannerActive(true);
        toast.info("Scanner ativo - aponte para um QR Code válido");

        // Simular scan após 3 segundos (em produção seria a câmera real)
        setTimeout(() => {
            setScannerActive(false);
            setData("activation_code", "DEMO-SCAN-CODE");
            toast.success("QR Code escaneado!");
        }, 3000);
    };

    return (
        <ClientLayout>
            <Head title="Ativar Player" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <a href={route("players.index")}>
                            <IconArrowLeft className="h-4 w-4 mr-2" />
                            Voltar
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">Ativar Player</h1>
                        <p className="text-gray-600">
                            Ative um dispositivo player usando o código de ativação
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Activation Form */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <IconKey className="h-5 w-5" />
                                Código de Ativação
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="activation_code">Código</Label>
                                    <Input
                                        id="activation_code"
                                        value={data.activation_code}
                                        onChange={(e) => setData("activation_code", e.target.value.toUpperCase())}
                                        placeholder="Digite o código de ativação"
                                        className="text-center font-mono text-lg"
                                        maxLength={20}
                                    />
                                    {errors.activation_code && (
                                        <p className="text-sm text-red-600">{errors.activation_code}</p>
                                    )}
                                </div>

                                <Button type="submit" disabled={processing} className="w-full">
                                    <IconCheck className="h-4 w-4 mr-2" />
                                    {processing ? "Ativando..." : "Ativar Player"}
                                </Button>
                            </form>

                            <Separator />

                            {/* QR Scanner */}
                            <div className="space-y-4">
                                <h3 className="font-medium">Escanear QR Code</h3>

                                <div className="text-center">
                                    {scannerActive ? (
                                        <div className="border-2 border-dashed border-blue-500 rounded-lg p-8 bg-blue-50">
                                            <div className="animate-pulse">
                                                <IconScan className="h-16 w-16 mx-auto mb-4 text-blue-600" />
                                                <p className="text-blue-600 font-medium">Escaneando...</p>
                                                <p className="text-sm text-blue-500 mt-2">
                                                    Aponte a câmera para o QR Code
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="border-2 border-dashed border-gray-300 rounded-lg p-8">
                                            <IconScan className="h-16 w-16 mx-auto mb-4 text-gray-400" />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={startScanner}
                                                disabled={processing}
                                            >
                                                <IconScan className="h-4 w-4 mr-2" />
                                                Iniciar Scanner
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="text-sm text-gray-600 space-y-2">
                                <p><strong>Como ativar:</strong></p>
                                <ol className="list-decimal list-inside space-y-1 ml-2">
                                    <li>Obtenha o código de ativação do painel administrativo</li>
                                    <li>Digite o código no campo acima OU escaneie o QR Code</li>
                                    <li>Clique em "Ativar Player"</li>
                                    <li>O dispositivo será configurado automaticamente</li>
                                </ol>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Pending Players */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <IconClock className="h-5 w-5" />
                                Players Pendentes
                                <Badge variant="secondary">{pendingPlayers.length}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {pendingPlayers.length === 0 ? (
                                <div className="text-center py-8">
                                    <IconDeviceGamepad2 className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                    <p className="text-gray-500">
                                        Nenhum player aguardando ativação
                                    </p>
                                    <Button variant="outline" className="mt-4" asChild>
                                        <a href={route("players.create")}>
                                            Criar Novo Player
                                        </a>
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {pendingPlayers.map((player) => (
                                        <div
                                            key={player.id}
                                            className="p-4 border rounded-lg space-y-3"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <h4 className="font-medium">{player.name}</h4>
                                                    <p className="text-sm text-gray-600">
                                                        Criado em {formatDate(player.created_at)}
                                                    </p>
                                                </div>
                                                <Badge variant="outline" className="text-xs">
                                                    Pendente
                                                </Badge>
                                            </div>

                                            <div className="space-y-2">
                                                <Label className="text-xs">Código de Ativação:</Label>
                                                <div className="flex gap-2">
                                                    <code className="flex-1 text-xs bg-gray-100 p-2 rounded font-mono">
                                                        {player.activation_code}
                                                    </code>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleQuickActivate(player.activation_code)}
                                                        disabled={processing}
                                                    >
                                                        <IconCheck className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}

                                    <div className="pt-4 border-t">
                                        <Button variant="outline" className="w-full" asChild>
                                            <a href={route("players.create")}>
                                                Criar Novo Player
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </ClientLayout>
    );
}