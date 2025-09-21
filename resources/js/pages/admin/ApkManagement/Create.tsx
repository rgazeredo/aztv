import React, { useState, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import { useDropzone } from 'react-dropzone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ApkUploadProgress } from '@/components/ApkUploadProgress';
import { useTenantList } from '@/hooks/useApkData';
import { useToast } from '@/hooks/use-toast';
import {
  ArrowLeft,
  Upload,
  FileText,
  Smartphone,
  AlertCircle,
  CheckCircle,
  X,
} from 'lucide-react';
import { router } from '@inertiajs/react';

interface UploadFile {
  file: File;
  progress: number;
  status: 'uploading' | 'completed' | 'error';
  error?: string;
  id: string;
}

export default function ApkUpload() {
  const [selectedTenant, setSelectedTenant] = useState('');
  const [version, setVersion] = useState('');
  const [buildNumber, setBuildNumber] = useState('');
  const [description, setDescription] = useState('');
  const [uploadFiles, setUploadFiles] = useState<UploadFile[]>([]);
  const [isUploading, setIsUploading] = useState(false);

  const { tenants, loading: tenantsLoading } = useTenantList();
  const { toast } = useToast();

  const onDrop = useCallback((acceptedFiles: File[]) => {
    const newFiles = acceptedFiles.map(file => ({
      file,
      progress: 0,
      status: 'uploading' as const,
      id: Math.random().toString(36).substr(2, 9)
    }));

    setUploadFiles(prev => [...prev, ...newFiles]);

    // Start upload for each file
    newFiles.forEach(uploadFile => {
      uploadAPK(uploadFile);
    });
  }, [selectedTenant, version, buildNumber, description]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'application/vnd.android.package-archive': ['.apk']
    },
    multiple: false,
    disabled: isUploading || !selectedTenant || !version
  });

  const uploadAPK = async (uploadFile: UploadFile) => {
    if (!selectedTenant || !version) {
      updateFileStatus(uploadFile.id, 'error', 'Tenant e versão são obrigatórios');
      return;
    }

    setIsUploading(true);

    const formData = new FormData();
    formData.append('apk_file', uploadFile.file);
    formData.append('tenant_id', selectedTenant);
    formData.append('version', version);
    formData.append('build_number', buildNumber);
    formData.append('description', description);

    try {
      const xhr = new XMLHttpRequest();

      xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) {
          const progress = Math.round((event.loaded / event.total) * 100);
          updateFileProgress(uploadFile.id, progress);
        }
      });

      xhr.addEventListener('load', () => {
        if (xhr.status === 200 || xhr.status === 201) {
          updateFileStatus(uploadFile.id, 'completed');
          toast({
            title: 'Upload concluído',
            description: `O APK ${uploadFile.file.name} foi enviado com sucesso.`,
          });
        } else {
          const response = JSON.parse(xhr.responseText);
          updateFileStatus(uploadFile.id, 'error', response.message || 'Erro no upload');
          toast({
            title: 'Erro no upload',
            description: response.message || 'Falha ao enviar o APK.',
            variant: 'destructive',
          });
        }
        setIsUploading(false);
      });

      xhr.addEventListener('error', () => {
        updateFileStatus(uploadFile.id, 'error', 'Erro de conexão');
        toast({
          title: 'Erro no upload',
          description: 'Falha na conexão durante o upload.',
          variant: 'destructive',
        });
        setIsUploading(false);
      });

      xhr.open('POST', '/admin/apks');
      xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
      xhr.send(formData);

    } catch (error) {
      updateFileStatus(uploadFile.id, 'error', 'Erro inesperado');
      setIsUploading(false);
    }
  };

  const updateFileProgress = (fileId: string, progress: number) => {
    setUploadFiles(prev => prev.map(file =>
      file.id === fileId ? { ...file, progress } : file
    ));
  };

  const updateFileStatus = (fileId: string, status: 'uploading' | 'completed' | 'error', error?: string) => {
    setUploadFiles(prev => prev.map(file =>
      file.id === fileId ? { ...file, status, error } : file
    ));
  };

  const removeFile = (fileId: string) => {
    setUploadFiles(prev => prev.filter(file => file.id !== fileId));
  };

  const retryUpload = (fileId: string) => {
    const file = uploadFiles.find(f => f.id === fileId);
    if (file) {
      updateFileStatus(fileId, 'uploading');
      updateFileProgress(fileId, 0);
      uploadAPK(file);
    }
  };

  const handleGoBack = () => {
    router.visit('/admin/apks');
  };

  const isFormValid = selectedTenant && version && version.trim().length > 0;
  const hasCompletedUploads = uploadFiles.some(file => file.status === 'completed');

  return (
    <div className="container mx-auto py-8 max-w-4xl">
      <Head title="Upload de APK" />

      {/* Header */}
      <div className="flex items-center space-x-4 mb-8">
        <Button
          variant="outline"
          size="sm"
          onClick={handleGoBack}
          disabled={isUploading}
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Voltar
        </Button>
        <div>
          <h1 className="text-3xl font-bold tracking-tight flex items-center">
            <Smartphone className="h-8 w-8 mr-3" />
            Upload de APK
          </h1>
          <p className="text-muted-foreground">
            Envie uma nova versão do aplicativo AZ TV
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Form */}
        <div className="lg:col-span-2 space-y-6">
          {/* Upload Info */}
          <Card>
            <CardHeader>
              <CardTitle>Informações do APK</CardTitle>
              <CardDescription>
                Preencha os dados da nova versão
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="tenant">Tenant *</Label>
                  <Select
                    value={selectedTenant}
                    onValueChange={setSelectedTenant}
                    disabled={isUploading}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione um tenant" />
                    </SelectTrigger>
                    <SelectContent>
                      {tenants.map((tenant) => (
                        <SelectItem key={tenant.id} value={tenant.id.toString()}>
                          {tenant.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="version">Versão *</Label>
                  <Input
                    id="version"
                    placeholder="1.0.0"
                    value={version}
                    onChange={(e) => setVersion(e.target.value)}
                    disabled={isUploading}
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="buildNumber">Build Number</Label>
                <Input
                  id="buildNumber"
                  placeholder="123"
                  value={buildNumber}
                  onChange={(e) => setBuildNumber(e.target.value)}
                  disabled={isUploading}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">Descrição</Label>
                <Textarea
                  id="description"
                  placeholder="Descrição das alterações desta versão..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  disabled={isUploading}
                  className="resize-none"
                  rows={3}
                />
              </div>
            </CardContent>
          </Card>

          {/* Upload Area */}
          <Card>
            <CardHeader>
              <CardTitle>Arquivo APK</CardTitle>
              <CardDescription>
                Arraste e solte o arquivo APK ou clique para selecionar
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div
                {...getRootProps()}
                className={`
                  border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors
                  ${isDragActive
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-950'
                    : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600'
                  }
                  ${(!isFormValid || isUploading) ? 'opacity-50 cursor-not-allowed' : ''}
                `}
              >
                <input {...getInputProps()} />
                <div className="space-y-4">
                  <Upload className="mx-auto h-12 w-12 text-gray-400" />
                  <div>
                    <p className="text-lg font-medium">
                      {isDragActive
                        ? 'Solte o arquivo aqui'
                        : 'Arraste um arquivo APK aqui'
                      }
                    </p>
                    <p className="text-sm text-muted-foreground">
                      ou clique para selecionar um arquivo
                    </p>
                  </div>
                  {!isFormValid && (
                    <div className="flex items-center justify-center space-x-2 text-amber-600">
                      <AlertCircle className="h-4 w-4" />
                      <span className="text-sm">Preencha o tenant e versão primeiro</span>
                    </div>
                  )}
                </div>
              </div>

              {/* File Requirements */}
              <div className="mt-4 text-xs text-muted-foreground">
                <p><strong>Requisitos:</strong></p>
                <ul className="list-disc list-inside space-y-1">
                  <li>Apenas arquivos .apk são aceitos</li>
                  <li>Tamanho máximo: 100MB</li>
                  <li>O arquivo deve estar assinado digitalmente</li>
                </ul>
              </div>
            </CardContent>
          </Card>

          {/* Upload Progress */}
          {uploadFiles.length > 0 && (
            <ApkUploadProgress
              files={uploadFiles}
              onRemove={removeFile}
              onRetry={retryUpload}
            />
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Instructions */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Instruções</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-3 text-sm">
                <div className="flex items-start space-x-2">
                  <span className="flex-shrink-0 w-5 h-5 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">1</span>
                  <p>Selecione o tenant de destino</p>
                </div>
                <div className="flex items-start space-x-2">
                  <span className="flex-shrink-0 w-5 h-5 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">2</span>
                  <p>Defina a versão (formato: x.y.z)</p>
                </div>
                <div className="flex items-start space-x-2">
                  <span className="flex-shrink-0 w-5 h-5 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">3</span>
                  <p>Faça upload do arquivo APK</p>
                </div>
                <div className="flex items-start space-x-2">
                  <span className="flex-shrink-0 w-5 h-5 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">4</span>
                  <p>QR Code será gerado automaticamente</p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Status */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Status</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                <div className="flex items-center space-x-2">
                  {isFormValid ? (
                    <CheckCircle className="h-4 w-4 text-green-600" />
                  ) : (
                    <AlertCircle className="h-4 w-4 text-amber-600" />
                  )}
                  <span className="text-sm">
                    {isFormValid ? 'Formulário válido' : 'Preencha os campos obrigatórios'}
                  </span>
                </div>

                <div className="flex items-center space-x-2">
                  {hasCompletedUploads ? (
                    <CheckCircle className="h-4 w-4 text-green-600" />
                  ) : uploadFiles.length > 0 ? (
                    <Upload className="h-4 w-4 text-blue-600" />
                  ) : (
                    <FileText className="h-4 w-4 text-gray-400" />
                  )}
                  <span className="text-sm">
                    {hasCompletedUploads
                      ? 'Upload concluído'
                      : uploadFiles.length > 0
                      ? 'Upload em andamento'
                      : 'Aguardando arquivo'
                    }
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Success Actions */}
          {hasCompletedUploads && (
            <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
              <CardHeader>
                <CardTitle className="text-lg text-green-800 dark:text-green-200">
                  Upload Concluído!
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-green-700 dark:text-green-300">
                  O APK foi enviado e está sendo processado. O QR Code estará disponível em breve.
                </p>
                <div className="space-y-2">
                  <Button
                    onClick={handleGoBack}
                    className="w-full"
                    size="sm"
                  >
                    Ver Lista de APKs
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => {
                      setSelectedTenant('');
                      setVersion('');
                      setBuildNumber('');
                      setDescription('');
                      setUploadFiles([]);
                    }}
                    className="w-full"
                    size="sm"
                  >
                    Fazer Novo Upload
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}