import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    IconQrcode,
    IconDownload,
    IconRefresh,
    IconCopy,
    IconCheck,
    IconEye,
} from "@tabler/icons-react";
import { toast } from "sonner";

interface Player {
    id: number;
    name: string;
    activation_token?: string;
}

interface QRCodeDisplayProps {
    player: Player;
    className?: string;
}

interface QRCodeData {
    url: string;
    path: string;
    activation_url: string;
    has_qr_code: boolean;
}

export default function QRCodeDisplay({ player, className }: QRCodeDisplayProps) {
    const [qrData, setQrData] = useState<QRCodeData | null>(null);
    const [loading, setLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const [options, setOptions] = useState({
        size: 300,
        margin: 2,
        primaryColor: "#000000",
        backgroundColor: "#FFFFFF",
    });

    const fetchQRCode = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/qr-code/player/${player.id}/generate`, {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (response.ok) {
                const data = await response.json();
                setQrData(data);
            } else {
                toast.error("Erro ao gerar QR Code");
            }
        } catch (error) {
            toast.error("Erro ao conectar com o servidor");
        } finally {
            setLoading(false);
        }
    };

    const regenerateQRCode = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                size: options.size.toString(),
                margin: options.margin.toString(),
                primaryColor: options.primaryColor,
                backgroundColor: options.backgroundColor,
            });

            const response = await fetch(
                `/qr-code/player/${player.id}/regenerate?${params}`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                    },
                }
            );

            if (response.ok) {
                const data = await response.json();
                setQrData(data);
                toast.success("QR Code regenerado com sucesso");
            } else {
                toast.error("Erro ao regenerar QR Code");
            }
        } catch (error) {
            toast.error("Erro ao conectar com o servidor");
        } finally {
            setLoading(false);
        }
    };

    const downloadQRCode = async () => {
        try {
            const params = new URLSearchParams({
                size: options.size.toString(),
                margin: options.margin.toString(),
                primaryColor: options.primaryColor,
                backgroundColor: options.backgroundColor,
            });

            const response = await fetch(
                `/qr-code/player/${player.id}/download?${params}`
            );

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = `qrcode_player_${player.name}_${player.id}.png`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                toast.success("QR Code baixado com sucesso");
            } else {
                toast.error("Erro ao baixar QR Code");
            }
        } catch (error) {
            toast.error("Erro ao baixar arquivo");
        }
    };

    const copyActivationUrl = async () => {
        if (qrData?.activation_url) {
            try {
                await navigator.clipboard.writeText(qrData.activation_url);
                setCopied(true);
                toast.success("URL copiada para a área de transferência");
                setTimeout(() => setCopied(false), 2000);
            } catch (error) {
                toast.error("Erro ao copiar URL");
            }
        }
    };

    const checkQRCodeStatus = async () => {
        try {
            const response = await fetch(`/qr-code/player/${player.id}/show`);
            if (response.ok) {
                const data = await response.json();
                if (data.has_qr_code && data.qr_code_url) {
                    setQrData({
                        url: data.qr_code_url,
                        path: "",
                        activation_url: data.activation_url,
                        has_qr_code: data.has_qr_code,
                    });
                }
            }
        } catch (error) {
            console.error("Error checking QR code status:", error);
        }
    };

    useEffect(() => {
        if (isOpen && !qrData) {
            checkQRCodeStatus();
        }
    }, [isOpen]);

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className={className}>
                    <IconQrcode className="h-4 w-4 mr-2" />
                    QR Code
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>QR Code de Ativação - {player.name}</DialogTitle>
                </DialogHeader>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* QR Code Display */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">QR Code</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex justify-center">
                                    {qrData?.url ? (
                                        <img
                                            src={qrData.url}
                                            alt="QR Code de Ativação"
                                            className="border rounded-lg"
                                            style={{
                                                width: options.size,
                                                height: options.size,
                                            }}
                                        />
                                    ) : (
                                        <div
                                            className="flex items-center justify-center bg-gray-100 rounded-lg border-2 border-dashed"
                                            style={{
                                                width: options.size,
                                                height: options.size,
                                            }}
                                        >
                                            <div className="text-center">
                                                <IconQrcode className="h-12 w-12 mx-auto text-gray-400 mb-2" />
                                                <p className="text-gray-500 text-sm">
                                                    QR Code não gerado
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-2">
                                    {!qrData ? (
                                        <Button
                                            onClick={fetchQRCode}
                                            disabled={loading}
                                            className="flex-1"
                                        >
                                            <IconQrcode className="h-4 w-4 mr-2" />
                                            {loading ? "Gerando..." : "Gerar QR Code"}
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                variant="outline"
                                                onClick={regenerateQRCode}
                                                disabled={loading}
                                                className="flex-1"
                                            >
                                                <IconRefresh className="h-4 w-4 mr-2" />
                                                {loading ? "Regenerando..." : "Regenerar"}
                                            </Button>
                                            <Button onClick={downloadQRCode} className="flex-1">
                                                <IconDownload className="h-4 w-4 mr-2" />
                                                Baixar
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Activation Info */}
                        {qrData && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm">
                                        Informações de Ativação
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <Label className="text-xs text-muted-foreground">
                                            Token de Ativação
                                        </Label>
                                        <div className="flex items-center gap-2 mt-1">
                                            <Badge variant="secondary" className="font-mono text-xs">
                                                {player.activation_token}
                                            </Badge>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div>
                                        <Label className="text-xs text-muted-foreground">
                                            URL de Ativação
                                        </Label>
                                        <div className="flex items-center gap-2 mt-1">
                                            <Input
                                                value={qrData.activation_url}
                                                readOnly
                                                className="text-xs"
                                            />
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={copyActivationUrl}
                                            >
                                                {copied ? (
                                                    <IconCheck className="h-4 w-4" />
                                                ) : (
                                                    <IconCopy className="h-4 w-4" />
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Customization Options */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Personalização</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label htmlFor="size" className="text-xs">
                                            Tamanho
                                        </Label>
                                        <Input
                                            id="size"
                                            type="number"
                                            min="100"
                                            max="600"
                                            value={options.size}
                                            onChange={(e) =>
                                                setOptions((prev) => ({
                                                    ...prev,
                                                    size: parseInt(e.target.value) || 300,
                                                }))
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="margin" className="text-xs">
                                            Margem
                                        </Label>
                                        <Input
                                            id="margin"
                                            type="number"
                                            min="0"
                                            max="10"
                                            value={options.margin}
                                            onChange={(e) =>
                                                setOptions((prev) => ({
                                                    ...prev,
                                                    margin: parseInt(e.target.value) || 2,
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label htmlFor="primaryColor" className="text-xs">
                                            Cor Principal
                                        </Label>
                                        <Input
                                            id="primaryColor"
                                            type="color"
                                            value={options.primaryColor}
                                            onChange={(e) =>
                                                setOptions((prev) => ({
                                                    ...prev,
                                                    primaryColor: e.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="backgroundColor" className="text-xs">
                                            Cor de Fundo
                                        </Label>
                                        <Input
                                            id="backgroundColor"
                                            type="color"
                                            value={options.backgroundColor}
                                            onChange={(e) =>
                                                setOptions((prev) => ({
                                                    ...prev,
                                                    backgroundColor: e.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="text-xs text-muted-foreground">
                                    <p>
                                        <strong>Dica:</strong> Altere as configurações e clique em
                                        "Regenerar" para aplicar as mudanças ao QR Code.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Como Usar</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-xs text-muted-foreground">
                                <div className="flex items-start gap-2">
                                    <span className="bg-primary text-primary-foreground rounded-full w-4 h-4 flex items-center justify-center text-xs font-bold mt-0.5">
                                        1
                                    </span>
                                    <p>
                                        Escaneie o QR Code com o app Android do player ou acesse a
                                        URL manualmente
                                    </p>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="bg-primary text-primary-foreground rounded-full w-4 h-4 flex items-center justify-center text-xs font-bold mt-0.5">
                                        2
                                    </span>
                                    <p>
                                        O player será automaticamente associado ao seu tenant
                                    </p>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="bg-primary text-primary-foreground rounded-full w-4 h-4 flex items-center justify-center text-xs font-bold mt-0.5">
                                        3
                                    </span>
                                    <p>
                                        Após a ativação, o player começará a receber conteúdo
                                        automaticamente
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}