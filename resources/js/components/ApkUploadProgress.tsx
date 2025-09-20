import React from 'react';
import { Progress } from '@/components/ui/progress';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, XCircle, Upload, X } from 'lucide-react';

interface UploadFile {
  file: File;
  progress: number;
  status: 'uploading' | 'completed' | 'error';
  error?: string;
  id: string;
}

interface ApkUploadProgressProps {
  files: UploadFile[];
  onRemove?: (fileId: string) => void;
  onRetry?: (fileId: string) => void;
}

export function ApkUploadProgress({ files, onRemove, onRetry }: ApkUploadProgressProps) {
  if (files.length === 0) return null;

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'uploading':
        return <Upload className="h-4 w-4 text-blue-600 animate-pulse" />;
      case 'completed':
        return <CheckCircle className="h-4 w-4 text-green-600" />;
      case 'error':
        return <XCircle className="h-4 w-4 text-red-600" />;
      default:
        return <Upload className="h-4 w-4 text-gray-400" />;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'uploading':
        return 'bg-blue-600';
      case 'completed':
        return 'bg-green-600';
      case 'error':
        return 'bg-red-600';
      default:
        return 'bg-gray-400';
    }
  };

  const getStatusText = (status: string, progress: number) => {
    switch (status) {
      case 'uploading':
        return `Enviando... ${progress}%`;
      case 'completed':
        return 'Upload concluído';
      case 'error':
        return 'Erro no upload';
      default:
        return 'Aguardando...';
    }
  };

  return (
    <Card>
      <CardContent className="pt-4">
        <div className="space-y-4">
          <h4 className="text-sm font-medium text-foreground">
            Status do Upload ({files.length} arquivo{files.length !== 1 ? 's' : ''})
          </h4>

          {files.map((uploadFile) => (
            <div key={uploadFile.id} className="space-y-2">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2 flex-1 min-w-0">
                  {getStatusIcon(uploadFile.status)}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">
                      {uploadFile.file.name}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {formatBytes(uploadFile.file.size)} • {getStatusText(uploadFile.status, uploadFile.progress)}
                    </p>
                  </div>
                </div>

                <div className="flex items-center space-x-2">
                  {uploadFile.status === 'error' && onRetry && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => onRetry(uploadFile.id)}
                    >
                      Tentar Novamente
                    </Button>
                  )}
                  {onRemove && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => onRemove(uploadFile.id)}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              </div>

              {uploadFile.status === 'uploading' && (
                <Progress
                  value={uploadFile.progress}
                  className={`h-2 ${getStatusColor(uploadFile.status)}`}
                />
              )}

              {uploadFile.status === 'completed' && (
                <div className="w-full bg-green-100 dark:bg-green-900 h-2 rounded-full">
                  <div className="bg-green-600 h-2 rounded-full w-full"></div>
                </div>
              )}

              {uploadFile.status === 'error' && (
                <>
                  <div className="w-full bg-red-100 dark:bg-red-900 h-2 rounded-full">
                    <div className="bg-red-600 h-2 rounded-full w-full"></div>
                  </div>
                  {uploadFile.error && (
                    <p className="text-xs text-red-600 dark:text-red-400">
                      {uploadFile.error}
                    </p>
                  )}
                </>
              )}
            </div>
          ))}

          {/* Summary */}
          <div className="pt-2 border-t">
            <div className="flex justify-between text-xs text-muted-foreground">
              <span>
                {files.filter(f => f.status === 'completed').length} de {files.length} concluídos
              </span>
              <span>
                {files.filter(f => f.status === 'error').length > 0 &&
                  `${files.filter(f => f.status === 'error').length} com erro`
                }
              </span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}