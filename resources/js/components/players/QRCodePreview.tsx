import { useEffect, useRef } from "react";
import QRCode from "qrcode";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { IconDownload } from "@tabler/icons-react";

interface QRCodePreviewProps {
    value: string;
    title?: string;
    description?: string;
}

export default function QRCodePreview({ value, title = "QR Code", description }: QRCodePreviewProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        if (canvasRef.current && value) {
            QRCode.toCanvas(canvasRef.current, value, {
                width: 200,
                margin: 2,
                color: {
                    dark: "#000000",
                    light: "#FFFFFF",
                },
            });
        }
    }, [value]);

    const downloadQRCode = () => {
        if (canvasRef.current) {
            const link = document.createElement("a");
            link.download = "qrcode.png";
            link.href = canvasRef.current.toDataURL();
            link.click();
        }
    };

    if (!value) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">{title}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-center h-48 bg-gray-100 rounded-lg">
                        <p className="text-gray-500 text-sm">
                            QR Code será gerado após salvar
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">{title}</CardTitle>
                {description && (
                    <p className="text-xs text-muted-foreground">{description}</p>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex justify-center">
                    <canvas ref={canvasRef} className="border rounded-lg" />
                </div>

                <div className="space-y-2">
                    <p className="text-xs text-muted-foreground break-all">
                        {value}
                    </p>

                    <Button
                        variant="outline"
                        size="sm"
                        onClick={downloadQRCode}
                        className="w-full"
                    >
                        <IconDownload className="h-4 w-4 mr-2" />
                        Baixar QR Code
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}