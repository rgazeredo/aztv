import { useCallback } from "react";
import { useDropzone } from "react-dropzone";
import { IconUpload, IconAlertTriangle } from "@tabler/icons-react";
import { toast } from "sonner";

interface UploadDropzoneProps {
    onFilesSelected: (files: File[]) => void;
    acceptedTypes?: Record<string, string[]>;
    maxFileSize?: number;
    maxFiles?: number;
    className?: string;
    disabled?: boolean;
}

export default function UploadDropzone({
    onFilesSelected,
    acceptedTypes = {
        'image/*': ['.jpg', '.jpeg', '.png', '.gif', '.webp'],
        'video/*': ['.mp4', '.avi', '.mov', '.wmv', '.flv'],
        'audio/*': ['.mp3', '.wav', '.aac', '.ogg'],
    },
    maxFileSize = 100 * 1024 * 1024, // 100MB
    maxFiles = 10,
    className = "",
    disabled = false,
}: UploadDropzoneProps) {
    const onDrop = useCallback((acceptedFiles: File[], rejectedFiles: any[]) => {
        if (rejectedFiles.length > 0) {
            rejectedFiles.forEach(({ file, errors }) => {
                errors.forEach((error: any) => {
                    if (error.code === 'file-too-large') {
                        toast.error(`${file.name}: Arquivo muito grande`);
                    } else if (error.code === 'file-invalid-type') {
                        toast.error(`${file.name}: Tipo de arquivo não suportado`);
                    } else if (error.code === 'too-many-files') {
                        toast.error(`Máximo de ${maxFiles} arquivos permitido`);
                    } else {
                        toast.error(`${file.name}: ${error.message}`);
                    }
                });
            });
        }

        if (acceptedFiles.length > 0) {
            onFilesSelected(acceptedFiles);
        }
    }, [onFilesSelected, maxFiles]);

    const { getRootProps, getInputProps, isDragActive, isDragReject } = useDropzone({
        onDrop,
        accept: acceptedTypes,
        maxSize: maxFileSize,
        maxFiles: maxFiles,
        multiple: true,
        disabled,
    });

    return (
        <div
            {...getRootProps()}
            className={`
                border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors
                ${isDragActive && !isDragReject
                    ? 'border-blue-500 bg-blue-50'
                    : isDragReject
                    ? 'border-red-500 bg-red-50'
                    : 'border-gray-300 hover:border-gray-400'
                }
                ${disabled ? 'cursor-not-allowed opacity-50' : ''}
                ${className}
            `}
        >
            <input {...getInputProps()} />

            {isDragReject ? (
                <>
                    <IconAlertTriangle className="h-12 w-12 mx-auto mb-4 text-red-400" />
                    <p className="text-red-600">
                        Alguns arquivos não são aceitos
                    </p>
                </>
            ) : (
                <>
                    <IconUpload className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                    {isDragActive ? (
                        <p className="text-blue-600">
                            Solte os arquivos aqui...
                        </p>
                    ) : (
                        <div>
                            <p className="text-gray-600 mb-2">
                                {disabled
                                    ? "Upload desabilitado"
                                    : "Arraste e solte arquivos aqui, ou clique para selecionar"
                                }
                            </p>
                            {!disabled && (
                                <p className="text-sm text-gray-500">
                                    Máximo de {maxFiles} arquivos • Tamanho máximo: {Math.round(maxFileSize / (1024 * 1024))}MB
                                </p>
                            )}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}