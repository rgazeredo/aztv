import { useState } from "react";
import { Link, router } from "@inertiajs/react";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    IconDotsVertical,
    IconEye,
    IconEdit,
    IconDownload,
    IconTrash,
    IconPhoto,
    IconVideo,
    IconFileMusic,
    IconFile,
    IconTag,
} from "@tabler/icons-react";
import { toast } from "sonner";

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

interface MediaCardProps {
    media: MediaFile;
    isSelected: boolean;
    onSelect: (id: number) => void;
    onDelete?: (media: MediaFile) => void;
}

export default function MediaCard({ media, isSelected, onSelect, onDelete }: MediaCardProps) {
    const getFileIcon = (type: string) => {
        switch (type) {
            case 'image':
                return <IconPhoto className="h-5 w-5 text-green-600" />;
            case 'video':
                return <IconVideo className="h-5 w-5 text-blue-600" />;
            case 'audio':
                return <IconFileMusic className="h-5 w-5 text-purple-600" />;
            default:
                return <IconFile className="h-5 w-5 text-gray-600" />;
        }
    };

    const formatDuration = (duration?: number) => {
        if (!duration) return null;
        const minutes = Math.floor(duration / 60);
        const seconds = duration % 60;
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    };

    const handleDelete = () => {
        if (onDelete) {
            onDelete(media);
        } else {
            if (confirm(`Deseja excluir o arquivo "${media.original_name}"?`)) {
                router.delete(route("media.destroy", media.id), {
                    onSuccess: () => toast.success("Arquivo excluído com sucesso"),
                    onError: () => toast.error("Erro ao excluir arquivo"),
                });
            }
        }
    };

    return (
        <Card className="overflow-hidden hover:shadow-md transition-shadow">
            <CardContent className="p-0">
                <div className="relative">
                    {media.thumbnail_path ? (
                        <img
                            src={media.thumbnail_path}
                            alt={media.original_name}
                            className="w-full h-32 object-cover"
                        />
                    ) : (
                        <div className="w-full h-32 bg-gray-100 flex items-center justify-center">
                            {getFileIcon(media.type)}
                        </div>
                    )}

                    <div className="absolute top-2 left-2">
                        <Checkbox
                            checked={isSelected}
                            onCheckedChange={() => onSelect(media.id)}
                            className="bg-white shadow-sm"
                        />
                    </div>

                    <div className="absolute top-2 right-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-8 w-8 p-0 bg-white shadow-sm"
                                >
                                    <IconDotsVertical className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link href={route("media.show", media.id)}>
                                        <IconEye className="h-4 w-4 mr-2" />
                                        Ver detalhes
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={route("media.edit", media.id)}>
                                        <IconEdit className="h-4 w-4 mr-2" />
                                        Editar
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={route("media.download", media.id)}>
                                        <IconDownload className="h-4 w-4 mr-2" />
                                        Download
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={handleDelete}
                                    className="text-red-600"
                                >
                                    <IconTrash className="h-4 w-4 mr-2" />
                                    Excluir
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    {media.duration && (
                        <div className="absolute bottom-2 right-2">
                            <Badge
                                variant="secondary"
                                className="text-xs bg-black/70 text-white"
                            >
                                {formatDuration(media.duration)}
                            </Badge>
                        </div>
                    )}
                </div>

                <div className="p-3">
                    <h4 className="font-medium text-sm truncate" title={media.original_name}>
                        {media.original_name}
                    </h4>
                    <div className="flex items-center justify-between mt-1">
                        <span className="text-xs text-gray-500">
                            {media.formatted_size}
                        </span>
                        <div className="flex items-center gap-1">
                            {getFileIcon(media.type)}
                        </div>
                    </div>
                    {media.tags.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-2">
                            {media.tags.slice(0, 2).map((tag) => (
                                <Badge
                                    key={tag}
                                    variant="outline"
                                    className="text-xs"
                                >
                                    <IconTag className="h-2 w-2 mr-1" />
                                    {tag}
                                </Badge>
                            ))}
                            {media.tags.length > 2 && (
                                <Badge variant="outline" className="text-xs">
                                    +{media.tags.length - 2}
                                </Badge>
                            )}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}