import { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    IconPlus,
    IconGridDots,
    IconList,
    IconDotsVertical,
    IconDownload,
    IconEdit,
    IconTrash,
    IconEye,
    IconUpload,
    IconSearch,
    IconFilter,
    IconFolder,
    IconTag,
    IconVideo,
    IconPhoto,
    IconFileMusic,
    IconFile,
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

interface Props {
    media: {
        data: MediaFile[];
        links: any[];
        meta: any;
    };
    folders: string[];
    stats: {
        total: number;
        images: number;
        videos: number;
        audio: number;
        documents: number;
        total_size: number;
        formatted_total_size: string;
    };
    filters: {
        search?: string;
        type?: string;
        folder?: string;
        tag?: string;
    };
}

export default function Index({ media, folders, stats, filters }: Props) {
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [selectedFiles, setSelectedFiles] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search || "");
    const [typeFilter, setTypeFilter] = useState(filters.type || "");
    const [folderFilter, setFolderFilter] = useState(filters.folder || "");
    const [tagFilter, setTagFilter] = useState(filters.tag || "");

    const handleFilterChange = () => {
        router.get(
            route("media.index"),
            {
                search: search || undefined,
                type: typeFilter || undefined,
                folder: folderFilter || undefined,
                tag: tagFilter || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleSelectFile = (fileId: number) => {
        setSelectedFiles(prev =>
            prev.includes(fileId)
                ? prev.filter(id => id !== fileId)
                : [...prev, fileId]
        );
    };

    const handleSelectAll = () => {
        if (selectedFiles.length === media.data.length) {
            setSelectedFiles([]);
        } else {
            setSelectedFiles(media.data.map(file => file.id));
        }
    };

    const handleBulkDelete = () => {
        if (selectedFiles.length === 0) return;

        if (confirm(`Deseja excluir ${selectedFiles.length} arquivo(s) selecionado(s)?`)) {
            router.post(
                route("media.bulk-action"),
                {
                    action: "delete",
                    file_ids: selectedFiles,
                },
                {
                    onSuccess: () => {
                        toast.success("Arquivos excluídos com sucesso");
                        setSelectedFiles([]);
                    },
                    onError: () => toast.error("Erro ao excluir arquivos"),
                }
            );
        }
    };

    const handleDelete = (file: MediaFile) => {
        if (confirm(`Deseja excluir o arquivo "${file.original_name}"?`)) {
            router.delete(route("media.destroy", file.id), {
                onSuccess: () => toast.success("Arquivo excluído com sucesso"),
                onError: () => toast.error("Erro ao excluir arquivo"),
            });
        }
    };

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

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString("pt-BR");
    };

    const formatDuration = (duration?: number) => {
        if (!duration) return null;
        const minutes = Math.floor(duration / 60);
        const seconds = duration % 60;
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Gerenciamento de Mídia" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">
                            Biblioteca de Mídia
                        </h1>
                        <p className="text-gray-600">
                            Gerencie seus arquivos de mídia
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild>
                            <Link href={route("media.create")}>
                                <IconUpload className="h-4 w-4 mr-2" />
                                Upload de Mídia
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-2">
                                <IconFile className="h-4 w-4 text-gray-600" />
                                <div>
                                    <p className="text-sm font-medium">Total</p>
                                    <p className="text-2xl font-bold">{stats.total}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-2">
                                <IconPhoto className="h-4 w-4 text-green-600" />
                                <div>
                                    <p className="text-sm font-medium">Imagens</p>
                                    <p className="text-2xl font-bold">{stats.images}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-2">
                                <IconVideo className="h-4 w-4 text-blue-600" />
                                <div>
                                    <p className="text-sm font-medium">Vídeos</p>
                                    <p className="text-2xl font-bold">{stats.videos}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-2">
                                <IconFileMusic className="h-4 w-4 text-purple-600" />
                                <div>
                                    <p className="text-sm font-medium">Áudio</p>
                                    <p className="text-2xl font-bold">{stats.audio}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="md:col-span-2">
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-2">
                                <IconFolder className="h-4 w-4 text-orange-600" />
                                <div>
                                    <p className="text-sm font-medium">Armazenamento</p>
                                    <p className="text-2xl font-bold">{stats.formatted_total_size}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col md:flex-row gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <IconSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                    <Input
                                        placeholder="Buscar arquivos..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter") {
                                                handleFilterChange();
                                            }
                                        }}
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <Select value={typeFilter} onValueChange={setTypeFilter}>
                                    <SelectTrigger className="w-36">
                                        <SelectValue placeholder="Tipo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Todos os tipos</SelectItem>
                                        <SelectItem value="image">Imagens</SelectItem>
                                        <SelectItem value="video">Vídeos</SelectItem>
                                        <SelectItem value="audio">Áudio</SelectItem>
                                        <SelectItem value="document">Documentos</SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select value={folderFilter} onValueChange={setFolderFilter}>
                                    <SelectTrigger className="w-36">
                                        <SelectValue placeholder="Pasta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Todas as pastas</SelectItem>
                                        {folders.map((folder) => (
                                            <SelectItem key={folder} value={folder}>
                                                {folder}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Button onClick={handleFilterChange}>
                                    <IconFilter className="h-4 w-4 mr-2" />
                                    Filtrar
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Toolbar */}
                <div className="flex justify-between items-center">
                    <div className="flex items-center gap-2">
                        {selectedFiles.length > 0 && (
                            <>
                                <Badge variant="secondary">
                                    {selectedFiles.length} selecionado(s)
                                </Badge>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={handleBulkDelete}
                                >
                                    <IconTrash className="h-4 w-4 mr-2" />
                                    Excluir Selecionados
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setViewMode(viewMode === 'grid' ? 'list' : 'grid')}
                        >
                            {viewMode === 'grid' ? (
                                <IconList className="h-4 w-4" />
                            ) : (
                                <IconGridDots className="h-4 w-4" />
                            )}
                        </Button>
                    </div>
                </div>

                {/* Media Grid/List */}
                {media.data.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <IconFile className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                Nenhum arquivo encontrado
                            </h3>
                            <p className="text-gray-600 mb-4">
                                Faça upload de seus primeiros arquivos de mídia
                            </p>
                            <Button asChild>
                                <Link href={route("media.create")}>
                                    <IconUpload className="h-4 w-4 mr-2" />
                                    Upload de Mídia
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : viewMode === 'grid' ? (
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                        {media.data.map((file) => (
                            <Card key={file.id} className="overflow-hidden hover:shadow-md transition-shadow">
                                <CardContent className="p-0">
                                    <div className="relative">
                                        {file.thumbnail_path ? (
                                            <img
                                                src={file.thumbnail_path}
                                                alt={file.original_name}
                                                className="w-full h-32 object-cover"
                                            />
                                        ) : (
                                            <div className="w-full h-32 bg-gray-100 flex items-center justify-center">
                                                {getFileIcon(file.type)}
                                            </div>
                                        )}

                                        <div className="absolute top-2 left-2">
                                            <Checkbox
                                                checked={selectedFiles.includes(file.id)}
                                                onCheckedChange={() => handleSelectFile(file.id)}
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
                                                        <Link href={route("media.show", file.id)}>
                                                            <IconEye className="h-4 w-4 mr-2" />
                                                            Ver detalhes
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route("media.edit", file.id)}>
                                                            <IconEdit className="h-4 w-4 mr-2" />
                                                            Editar
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route("media.download", file.id)}>
                                                            <IconDownload className="h-4 w-4 mr-2" />
                                                            Download
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleDelete(file)}
                                                        className="text-red-600"
                                                    >
                                                        <IconTrash className="h-4 w-4 mr-2" />
                                                        Excluir
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>

                                        {file.duration && (
                                            <div className="absolute bottom-2 right-2">
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs bg-black/70 text-white"
                                                >
                                                    {formatDuration(file.duration)}
                                                </Badge>
                                            </div>
                                        )}
                                    </div>

                                    <div className="p-3">
                                        <h4 className="font-medium text-sm truncate" title={file.original_name}>
                                            {file.original_name}
                                        </h4>
                                        <div className="flex items-center justify-between mt-1">
                                            <span className="text-xs text-gray-500">
                                                {file.formatted_size}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                {getFileIcon(file.type)}
                                            </div>
                                        </div>
                                        {file.tags.length > 0 && (
                                            <div className="flex flex-wrap gap-1 mt-2">
                                                {file.tags.slice(0, 2).map((tag) => (
                                                    <Badge
                                                        key={tag}
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        <IconTag className="h-2 w-2 mr-1" />
                                                        {tag}
                                                    </Badge>
                                                ))}
                                                {file.tags.length > 2 && (
                                                    <Badge variant="outline" className="text-xs">
                                                        +{file.tags.length - 2}
                                                    </Badge>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="w-12 p-4">
                                                <Checkbox
                                                    checked={selectedFiles.length === media.data.length}
                                                    onCheckedChange={handleSelectAll}
                                                />
                                            </th>
                                            <th className="text-left p-4">Nome</th>
                                            <th className="text-left p-4">Tipo</th>
                                            <th className="text-left p-4">Tamanho</th>
                                            <th className="text-left p-4">Data</th>
                                            <th className="text-left p-4">Tags</th>
                                            <th className="text-right p-4">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {media.data.map((file) => (
                                            <tr key={file.id} className="border-t hover:bg-gray-50">
                                                <td className="p-4">
                                                    <Checkbox
                                                        checked={selectedFiles.includes(file.id)}
                                                        onCheckedChange={() => handleSelectFile(file.id)}
                                                    />
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex items-center space-x-3">
                                                        {file.thumbnail_path ? (
                                                            <img
                                                                src={file.thumbnail_path}
                                                                alt={file.original_name}
                                                                className="w-10 h-10 object-cover rounded"
                                                            />
                                                        ) : (
                                                            <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center">
                                                                {getFileIcon(file.type)}
                                                            </div>
                                                        )}
                                                        <span className="font-medium">{file.original_name}</span>
                                                    </div>
                                                </td>
                                                <td className="p-4">
                                                    <div className="flex items-center space-x-1">
                                                        {getFileIcon(file.type)}
                                                        <span className="capitalize">{file.type}</span>
                                                    </div>
                                                </td>
                                                <td className="p-4">{file.formatted_size}</td>
                                                <td className="p-4">{formatDate(file.created_at)}</td>
                                                <td className="p-4">
                                                    <div className="flex flex-wrap gap-1">
                                                        {file.tags.slice(0, 2).map((tag) => (
                                                            <Badge key={tag} variant="outline" className="text-xs">
                                                                {tag}
                                                            </Badge>
                                                        ))}
                                                        {file.tags.length > 2 && (
                                                            <Badge variant="outline" className="text-xs">
                                                                +{file.tags.length - 2}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-4 text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <IconDotsVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={route("media.show", file.id)}>
                                                                    <IconEye className="h-4 w-4 mr-2" />
                                                                    Ver detalhes
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={route("media.edit", file.id)}>
                                                                    <IconEdit className="h-4 w-4 mr-2" />
                                                                    Editar
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={route("media.download", file.id)}>
                                                                    <IconDownload className="h-4 w-4 mr-2" />
                                                                    Download
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => handleDelete(file)}
                                                                className="text-red-600"
                                                            >
                                                                <IconTrash className="h-4 w-4 mr-2" />
                                                                Excluir
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Pagination */}
                {media.meta.total > media.meta.per_page && (
                    <div className="flex justify-center">
                        {media.links.map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? "default" : "outline"}
                                size="sm"
                                className="mx-1"
                                onClick={() => {
                                    if (link.url) {
                                        router.visit(link.url);
                                    }
                                }}
                                disabled={!link.url}
                            >
                                <span
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}