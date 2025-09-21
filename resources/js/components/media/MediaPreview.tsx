import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
    IconDownload,
    IconX,
    IconPhoto,
    IconVideo,
    IconFileMusic,
    IconFile,
    IconTag,
} from "@tabler/icons-react";

interface MediaFile {
    id: number;
    filename: string;
    original_name: string;
    mime_type: string;
    size: number;
    path: string;
    thumbnail_path?: string;
    duration?: number;
    folder?: string;
    tags: string[];
    created_at: string;
    formatted_size: string;
    type: 'image' | 'video' | 'audio' | 'document';
}

interface MediaPreviewProps {
    media: MediaFile | null;
    isOpen: boolean;
    onClose: () => void;
}

export default function MediaPreview({ media, isOpen, onClose }: MediaPreviewProps) {
    if (!media) return null;

    const getFileIcon = (type: string) => {
        switch (type) {
            case 'image':
                return <IconPhoto className="h-8 w-8 text-green-600" />;
            case 'video':
                return <IconVideo className="h-8 w-8 text-blue-600" />;
            case 'audio':
                return <IconFileMusic className="h-8 w-8 text-purple-600" />;
            default:
                return <IconFile className="h-8 w-8 text-gray-600" />;
        }
    };

    const formatDuration = (duration?: number) => {
        if (!duration) return null;
        const hours = Math.floor(duration / 3600);
        const minutes = Math.floor((duration % 3600) / 60);
        const seconds = duration % 60;

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString("pt-BR");
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center justify-between">
                        <DialogTitle className="flex items-center">
                            {getFileIcon(media.type)}
                            <span className="ml-2">{media.original_name}</span>
                        </DialogTitle>
                        <div className="flex items-center space-x-2">
                            <Button variant="outline" size="sm" asChild>
                                <a href={route("media.download", media.id)} download>
                                    <IconDownload className="h-4 w-4 mr-2" />
                                    Download
                                </a>
                            </Button>
                            <Button variant="ghost" size="sm" onClick={onClose}>
                                <IconX className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Media Content */}
                    <div className="bg-gray-900 rounded-lg overflow-hidden">
                        {media.type === 'image' ? (
                            <img
                                src={media.path}
                                alt={media.original_name}
                                className="w-full h-auto max-h-96 object-contain mx-auto"
                            />
                        ) : media.type === 'video' ? (
                            <video
                                controls
                                className="w-full h-auto max-h-96"
                                poster={media.thumbnail_path}
                            >
                                <source src={media.path} type={media.mime_type} />
                                Seu navegador não suporta o elemento de vídeo.
                            </video>
                        ) : media.type === 'audio' ? (
                            <div className="p-8 text-center">
                                <IconFileMusic className="h-24 w-24 mx-auto mb-4 text-purple-400" />
                                <audio controls className="w-full">
                                    <source src={media.path} type={media.mime_type} />
                                    Seu navegador não suporta o elemento de áudio.
                                </audio>
                            </div>
                        ) : (
                            <div className="p-8 text-center text-white">
                                <IconFile className="h-24 w-24 mx-auto mb-4 text-gray-400" />
                                <p>Preview não disponível para este tipo de arquivo</p>
                            </div>
                        )}
                    </div>

                    {/* Media Details */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p className="text-gray-600">Tipo</p>
                            <p className="font-medium capitalize">{media.type}</p>
                        </div>
                        <div>
                            <p className="text-gray-600">Tamanho</p>
                            <p className="font-medium">{media.formatted_size}</p>
                        </div>
                        {media.duration && (
                            <div>
                                <p className="text-gray-600">Duração</p>
                                <p className="font-medium">{formatDuration(media.duration)}</p>
                            </div>
                        )}
                        <div>
                            <p className="text-gray-600">Criado em</p>
                            <p className="font-medium">{formatDate(media.created_at)}</p>
                        </div>
                    </div>

                    {/* Tags */}
                    {media.tags.length > 0 && (
                        <div>
                            <p className="text-sm text-gray-600 mb-2">
                                <IconTag className="h-4 w-4 inline mr-1" />
                                Tags
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {media.tags.map((tag) => (
                                    <Badge key={tag} variant="outline">
                                        {tag}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Additional Info */}
                    <div className="bg-gray-50 p-3 rounded-lg text-sm">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <span className="text-gray-600">Nome do arquivo:</span>
                                <span className="ml-1 font-mono">{media.filename}</span>
                            </div>
                            <div>
                                <span className="text-gray-600">Tipo MIME:</span>
                                <span className="ml-1 font-mono">{media.mime_type}</span>
                            </div>
                            {media.folder && (
                                <div>
                                    <span className="text-gray-600">Pasta:</span>
                                    <span className="ml-1">{media.folder}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}