import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { Copy, Download, ExternalLink, QrCode, Smartphone } from 'lucide-react';

interface QrCodeModalProps {
  isOpen: boolean;
  onClose: () => void;
  apkVersion: {
    id: number;
    version: string;
    tenant_name: string;
    download_url: string;
    qr_code_url: string;
    file_size: number;
  };
}

export function QrCodeModal({ isOpen, onClose, apkVersion }: QrCodeModalProps) {
  const [isLoading, setIsLoading] = useState(false);
  const { toast } = useToast();

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(apkVersion.download_url);
      toast({
        title: 'Link copiado',
        description: 'O link de download foi copiado para a área de transferência.',
      });
    } catch (error) {
      toast({
        title: 'Erro',
        description: 'Falha ao copiar o link.',
        variant: 'destructive',
      });
    }
  };

  const handleDownloadQr = async () => {
    try {
      setIsLoading(true);
      const response = await fetch(apkVersion.qr_code_url);
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `qr-code-${apkVersion.tenant_name}-v${apkVersion.version}.png`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);

      toast({
        title: 'Download iniciado',
        description: 'O QR Code foi baixado com sucesso.',
      });
    } catch (error) {
      toast({
        title: 'Erro',
        description: 'Falha ao baixar o QR Code.',
        variant: 'destructive',
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleOpenLink = () => {
    window.open(apkVersion.download_url, '_blank');
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center">
            <QrCode className="h-5 w-5 mr-2" />
            QR Code para Download
          </DialogTitle>
          <DialogDescription>
            Escaneie o QR Code ou use o link para baixar o APK
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          {/* APK Info */}
          <Card>
            <CardContent className="pt-4">
              <div className="space-y-2">
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-muted-foreground">Tenant:</span>
                  <span className="text-sm font-medium">{apkVersion.tenant_name}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-muted-foreground">Versão:</span>
                  <span className="text-sm font-medium">v{apkVersion.version}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm font-medium text-muted-foreground">Tamanho:</span>
                  <span className="text-sm font-medium">{formatBytes(apkVersion.file_size)}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* QR Code */}
          <div className="flex justify-center">
            <div className="bg-white p-4 rounded-lg border">
              <img
                src={apkVersion.qr_code_url}
                alt={`QR Code para ${apkVersion.tenant_name} v${apkVersion.version}`}
                className="w-48 h-48 object-contain"
                onError={(e) => {
                  e.currentTarget.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTkyIiBoZWlnaHQ9IjE5MiIgdmlld0JveD0iMCAwIDE5MiAxOTIiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxOTIiIGhlaWdodD0iMTkyIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik05NiA2NEw5NiAxMjhNNjQgOTZMMTI4IDk2IiBzdHJva2U9IiM5Q0EzQUYiIHN0cm9rZS13aWR0aD0iNCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIi8+Cjx0ZXh0IHg9Ijk2IiB5PSIxNTYiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxMiIgZmlsbD0iIzlDQTNBRiIgdGV4dC1hbmNob3I9Im1pZGRsZSI+UVIgQ29kZSBOw6NvIERpc3BvbsOtdmVsPC90ZXh0Pgo8L3N2Zz4K';
                }}
              />
            </div>
          </div>

          {/* Instructions */}
          <div className="bg-blue-50 dark:bg-blue-950 p-4 rounded-lg">
            <div className="flex items-start space-x-3">
              <Smartphone className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" />
              <div className="text-sm text-blue-800 dark:text-blue-200">
                <p className="font-medium mb-1">Como usar:</p>
                <ol className="list-decimal list-inside space-y-1 text-xs">
                  <li>Abra a câmera do seu dispositivo Android</li>
                  <li>Aponte para o QR Code acima</li>
                  <li>Toque na notificação para baixar o APK</li>
                  <li>Permita a instalação de fontes desconhecidas se solicitado</li>
                </ol>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="grid grid-cols-2 gap-3">
            <Button
              variant="outline"
              onClick={handleCopyLink}
              className="w-full"
            >
              <Copy className="h-4 w-4 mr-2" />
              Copiar Link
            </Button>
            <Button
              variant="outline"
              onClick={handleOpenLink}
              className="w-full"
            >
              <ExternalLink className="h-4 w-4 mr-2" />
              Abrir Link
            </Button>
          </div>

          <Button
            onClick={handleDownloadQr}
            disabled={isLoading}
            className="w-full"
          >
            <Download className="h-4 w-4 mr-2" />
            {isLoading ? 'Baixando...' : 'Baixar QR Code'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}