import { useEffect, useRef } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { IconCopy, IconDownload, IconQrcode } from "@tabler/icons-react";
import { toast } from "sonner";
import QRCode from "qrcode";

interface ActivationQRCodeProps {
    activationCode: string;
    playerName?: string;
    className?: string;
}

export default function ActivationQRCode({
    activationCode,
    playerName,
    className = ""
}: ActivationQRCodeProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        if (canvasRef.current && activationCode) {
            QRCode.toCanvas(canvasRef.current, activationCode, {
                width: 200,
                margin: 2,
                color: {
                    dark: "#000000",
                    light: "#FFFFFF"
                }
            }).catch(err => {
                console.error("Erro ao gerar QR Code:", err);
                toast.error("Erro ao gerar QR Code");
            });
        }
    }, [activationCode]);

    const copyToClipboard = async () => {
        try {
            await navigator.clipboard.writeText(activationCode);
            toast.success("Código copiado para a área de transferência");
        } catch (err) {
            console.error("Erro ao copiar:", err);
            toast.error("Erro ao copiar código");
        }
    };

    const downloadQRCode = () => {
        if (canvasRef.current) {
            const link = document.createElement("a");
            link.download = `qr-code-${playerName || 'player'}-${activationCode}.png`;
            link.href = canvasRef.current.toDataURL();
            link.click();
        }
    };

    return (
        <Card className={className}>
            <CardHeader className="text-center">
                <CardTitle className="flex items-center justify-center gap-2">
                    <IconQrcode className="h-5 w-5" />
                    QR Code de Ativação
                </CardTitle>
                {playerName && (
                    <p className="text-sm text-gray-600">{playerName}</p>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex justify-center">
                    <canvas
                        ref={canvasRef}
                        className="border rounded-lg shadow-sm"
                    />
                </div>

                <div className="text-center space-y-2">
                    <p className="text-sm font-medium text-gray-700">
                        Código de Ativação:
                    </p>
                    <code className="block bg-gray-100 p-2 rounded text-sm font-mono break-all">
                        {activationCode}
                    </code>
                </div>

                <div className="flex gap-2 justify-center">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={copyToClipboard}
                        className="flex items-center gap-2"
                    >
                        <IconCopy className="h-4 w-4" />
                        Copiar Código
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={downloadQRCode}
                        className="flex items-center gap-2"
                    >
                        <IconDownload className="h-4 w-4" />
                        Download QR
                    </Button>
                </div>

                <div className="text-xs text-gray-500 text-center">
                    <p>Escaneie este QR Code com o aplicativo do player</p>
                    <p>para ativar automaticamente o dispositivo</p>
                </div>
            </CardContent>
        </Card>
    );
}