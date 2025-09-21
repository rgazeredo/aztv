import { useState, useCallback } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { useDropzone } from "react-dropzone";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout";
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import {
    IconUpload,
    IconX,
    IconPhoto,
    IconVideo,
    IconFileMusic,
    IconFile,
    IconCheck,
    IconAlertTriangle,
    IconArrowLeft,
    IconTag,
    IconFolder,
} from "@tabler/icons-react";
import { toast } from "sonner";

interface UploadFile {
    file: File;
    id: string;
    preview?: string;
    progress: number;
    status: 'pending' | 'uploading' | 'completed' | 'error';
    error?: string;
}

interface Props {
    folders: string[];
    upload_limits: {
        max_file_size: number;
        max_total_size: number;
        allowed_types: string[];
        current_usage: number;
        remaining_space: number;
    };
}

export default function Create({ folders, upload_limits }: Props) {
    const [uploadFiles, setUploadFiles] = useState<UploadFile[]>([]);
    const [isUploading, setIsUploading] = useState(false);
    const [folder, setFolder] = useState("");
    const [tags, setTags] = useState("");
    const [description, setDescription] = useState("");

    const isValidFileType = (file: File) => {
        return upload_limits.allowed_types.some(type =>
            file.type.startsWith(type) || file.name.toLowerCase().endsWith(type.replace('*', ''))
        );
    };

    const isValidFileSize = (file: File) => {
        return file.size <= upload_limits.max_file_size;
    };

    const canUploadFiles = (files: File[]) => {
        const totalSize = files.reduce((sum, file) => sum + file.size, 0);
        const currentTotalSize = uploadFiles.reduce((sum, item) => sum + item.file.size, 0);
        return (currentTotalSize + totalSize) <= upload_limits.remaining_space;
    };

    const onDrop = useCallback((acceptedFiles: File[]) => {
        const validFiles: UploadFile[] = [];
        const errors: string[] = [];

        acceptedFiles.forEach(file => {
            if (!isValidFileType(file)) {
                errors.push(`${file.name}: Tipo de arquivo não permitido`);
                return;
            }

            if (!isValidFileSize(file)) {
                errors.push(`${file.name}: Arquivo muito grande (máx: ${formatBytes(upload_limits.max_file_size)})`);
                return;
            }

            const uploadFile: UploadFile = {
                file,
                id: Math.random().toString(36).substr(2, 9),
                progress: 0,
                status: 'pending',
            };

            // Create preview for images
            if (file.type.startsWith('image/')) {
                uploadFile.preview = URL.createObjectURL(file);
            }

            validFiles.push(uploadFile);
        });

        if (validFiles.length > 0) {
            if (!canUploadFiles(validFiles.map(item => item.file))) {
                toast.error("Não há espaço suficiente para fazer upload destes arquivos");
                return;
            }

            setUploadFiles(prev => [...prev, ...validFiles]);
        }

        if (errors.length > 0) {
            errors.forEach(error => toast.error(error));
        }
    }, [uploadFiles, upload_limits]);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: {
            'image/*': ['.jpg', '.jpeg', '.png', '.gif', '.webp'],
            'video/*': ['.mp4', '.avi', '.mov', '.wmv', '.flv'],
            'audio/*': ['.mp3', '.wav', '.aac', '.ogg'],
        },
        multiple: true,
    });

    const removeFile = (id: string) => {
        setUploadFiles(prev => {
            const updatedFiles = prev.filter(item => item.id !== id);
            // Revoke preview URLs to prevent memory leaks
            const fileToRemove = prev.find(item => item.id === id);
            if (fileToRemove?.preview) {
                URL.revokeObjectURL(fileToRemove.preview);
            }
            return updatedFiles;
        });
    };

    const uploadFile = async (uploadFile: UploadFile) => {
        const formData = new FormData();
        formData.append('file', uploadFile.file);
        formData.append('folder', folder);
        formData.append('tags', tags);
        formData.append('description', description);

        try {
            setUploadFiles(prev => prev.map(item =>
                item.id === uploadFile.id
                    ? { ...item, status: 'uploading', progress: 0 }
                    : item
            ));

            const response = await fetch(route('media.store'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                setUploadFiles(prev => prev.map(item =>
                    item.id === uploadFile.id
                        ? { ...item, status: 'completed', progress: 100 }
                        : item
                ));
            } else {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Erro no upload');
            }
        } catch (error) {
            setUploadFiles(prev => prev.map(item =>
                item.id === uploadFile.id
                    ? { ...item, status: 'error', error: error instanceof Error ? error.message : 'Erro desconhecido' }
                    : item
            ));
        }
    };

    const uploadAllFiles = async () => {
        setIsUploading(true);

        const pendingFiles = uploadFiles.filter(item => item.status === 'pending');

        try {
            // Upload files sequentially to avoid overwhelming the server
            for (const uploadFile of pendingFiles) {
                await uploadFile(uploadFile);
            }

            toast.success(`${pendingFiles.length} arquivo(s) enviado(s) com sucesso`);

            // Redirect to media index after a short delay
            setTimeout(() => {
                router.visit(route('media.index'));
            }, 1500);

        } catch (error) {
            toast.error('Erro durante o upload dos arquivos');
        } finally {
            setIsUploading(false);
        }
    };

    const getFileIcon = (file: File) => {
        if (file.type.startsWith('image/')) {
            return <IconPhoto className="h-8 w-8 text-green-600" />;
        } else if (file.type.startsWith('video/')) {
            return <IconVideo className="h-8 w-8 text-blue-600" />;
        } else if (file.type.startsWith('audio/')) {
            return <IconFileMusic className="h-8 w-8 text-purple-600" />;
        } else {
            return <IconFile className="h-8 w-8 text-gray-600" />;
        }
    };

    const formatBytes = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getTotalSize = () => {
        return uploadFiles.reduce((sum, item) => sum + item.file.size, 0);
    };

    const getCompletedCount = () => {
        return uploadFiles.filter(item => item.status === 'completed').length;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Upload de Mídia" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Button variant="outline" asChild>
                            <Link href={route('media.index')}>
                                <IconArrowLeft className="h-4 w-4 mr-2" />
                                Voltar
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">
                                Upload de Mídia
                            </h1>
                            <p className="text-gray-600">
                                Faça upload de imagens, vídeos e áudios
                            </p>
                        </div>
                    </div>
                </div>

                {/* Upload Limits Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Limites de Upload</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <span className="text-gray-600">Tamanho máximo por arquivo:</span>
                                <div className="font-medium">{formatBytes(upload_limits.max_file_size)}</div>
                            </div>
                            <div>
                                <span className="text-gray-600">Espaço disponível:</span>
                                <div className="font-medium">{formatBytes(upload_limits.remaining_space)}</div>
                            </div>
                            <div>
                                <span className="text-gray-600">Tipos permitidos:</span>
                                <div className="flex flex-wrap gap-1 mt-1">
                                    {upload_limits.allowed_types.map(type => (
                                        <Badge key={type} variant="outline" className="text-xs">
                                            {type}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Upload Area */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Selecionar Arquivos</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div
                                    {...getRootProps()}
                                    className={`border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors ${
                                        isDragActive
                                            ? 'border-blue-500 bg-blue-50'
                                            : 'border-gray-300 hover:border-gray-400'
                                    }`}
                                >
                                    <input {...getInputProps()} />
                                    <IconUpload className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                    {isDragActive ? (
                                        <p className="text-blue-600">
                                            Solte os arquivos aqui...
                                        </p>
                                    ) : (
                                        <div>
                                            <p className="text-gray-600 mb-2">
                                                Arraste e solte arquivos aqui, ou clique para selecionar
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                Suporte a múltiplos arquivos
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* File List */}
                                {uploadFiles.length > 0 && (
                                    <div className="mt-6 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <h3 className="font-medium">
                                                Arquivos Selecionados ({uploadFiles.length})
                                            </h3>
                                            <div className="text-sm text-gray-600">
                                                Total: {formatBytes(getTotalSize())}
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            {uploadFiles.map((uploadFile) => (
                                                <div
                                                    key={uploadFile.id}
                                                    className="flex items-center space-x-3 p-3 border rounded-lg"
                                                >
                                                    <div className="flex-shrink-0">
                                                        {uploadFile.preview ? (
                                                            <img
                                                                src={uploadFile.preview}
                                                                alt={uploadFile.file.name}
                                                                className="w-12 h-12 object-cover rounded"
                                                            />
                                                        ) : (
                                                            <div className="w-12 h-12 bg-gray-100 rounded flex items-center justify-center">
                                                                {getFileIcon(uploadFile.file)}
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center justify-between">
                                                            <p className="font-medium truncate">
                                                                {uploadFile.file.name}
                                                            </p>
                                                            <div className="flex items-center space-x-2">
                                                                {uploadFile.status === 'completed' && (
                                                                    <IconCheck className="h-4 w-4 text-green-600" />
                                                                )}
                                                                {uploadFile.status === 'error' && (
                                                                    <IconAlertTriangle className="h-4 w-4 text-red-600" />
                                                                )}
                                                                {uploadFile.status === 'pending' && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => removeFile(uploadFile.id)}
                                                                        disabled={isUploading}
                                                                    >
                                                                        <IconX className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center justify-between mt-1">
                                                            <p className="text-sm text-gray-500">
                                                                {formatBytes(uploadFile.file.size)}
                                                            </p>
                                                            <Badge
                                                                variant={
                                                                    uploadFile.status === 'completed'
                                                                        ? 'default'
                                                                        : uploadFile.status === 'error'
                                                                        ? 'destructive'
                                                                        : 'secondary'
                                                                }
                                                                className="text-xs"
                                                            >
                                                                {uploadFile.status === 'pending' && 'Aguardando'}
                                                                {uploadFile.status === 'uploading' && 'Enviando...'}
                                                                {uploadFile.status === 'completed' && 'Concluído'}
                                                                {uploadFile.status === 'error' && 'Erro'}
                                                            </Badge>
                                                        </div>
                                                        {uploadFile.status === 'uploading' && (
                                                            <Progress value={uploadFile.progress} className="mt-2" />
                                                        )}
                                                        {uploadFile.error && (
                                                            <p className="text-xs text-red-600 mt-1">
                                                                {uploadFile.error}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Upload Settings */}
                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle>Configurações</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label htmlFor="folder">
                                        <IconFolder className="h-4 w-4 inline mr-1" />
                                        Pasta
                                    </Label>
                                    <Select value={folder} onValueChange={setFolder}>
                                        <SelectTrigger id="folder" className="mt-1">
                                            <SelectValue placeholder="Selecionar pasta" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Raiz</SelectItem>
                                            {folders.map((folderName) => (
                                                <SelectItem key={folderName} value={folderName}>
                                                    {folderName}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label htmlFor="tags">
                                        <IconTag className="h-4 w-4 inline mr-1" />
                                        Tags
                                    </Label>
                                    <Input
                                        id="tags"
                                        value={tags}
                                        onChange={(e) => setTags(e.target.value)}
                                        placeholder="tag1, tag2, tag3"
                                        className="mt-1"
                                    />
                                    <p className="text-xs text-gray-500 mt-1">
                                        Separe múltiplas tags com vírgula
                                    </p>
                                </div>

                                <div>
                                    <Label htmlFor="description">Descrição</Label>
                                    <Textarea
                                        id="description"
                                        value={description}
                                        onChange={(e) => setDescription(e.target.value)}
                                        placeholder="Descrição opcional dos arquivos"
                                        className="mt-1"
                                        rows={3}
                                    />
                                </div>

                                <div className="pt-4 border-t">
                                    <Button
                                        onClick={uploadAllFiles}
                                        disabled={uploadFiles.length === 0 || isUploading}
                                        className="w-full"
                                    >
                                        <IconUpload className="h-4 w-4 mr-2" />
                                        {isUploading
                                            ? `Enviando... (${getCompletedCount()}/${uploadFiles.length})`
                                            : `Fazer Upload (${uploadFiles.length})`
                                        }
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}