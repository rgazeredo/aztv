import { useState } from "react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    IconArrowLeft,
    IconDownload,
    IconEdit,
    IconTrash,
    IconCopy,
    IconShare,
    IconPhoto,
    IconVideo,
    IconFileMusic,
    IconFile,
    IconCalendar,
    IconClock,
    IconFolder,
    IconTag,
    IconDatabase,
    IconMonitor,
    IconPlaylist,
    IconCheck,
    IconX,
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
    description?: string;
    created_at: string;
    updated_at: string;
    formatted_size: string;
    type: 'image' | 'video' | 'audio' | 'document';
    metadata?: {
        width?: number;
        height?: number;
        resolution?: string;
        bitrate?: string;
        format?: string;
    };
}

interface PlaylistUsage {
    id: number;
    name: string;
    created_at: string;
}

interface PlayerUsage {
    id: number;
    name: string;
    last_played_at?: string;
    play_count: number;
}

interface Props {
    media: MediaFile;
    playlists: PlaylistUsage[];
    players: PlayerUsage[];
    usage_stats: {
        total_plays: number;
        unique_players: number;
        last_played_at?: string;
        avg_play_duration?: number;
    };
}

export default function Show({ media, playlists, players, usage_stats }: Props) {
    const [isEditMode, setIsEditMode] = useState(false);
    const [shareDialogOpen, setShareDialogOpen] = useState(false);

    const { data, setData, put, processing, errors, reset } = useForm({
        original_name: media.original_name,
        description: media.description || "",
        tags: media.tags.join(", "),
        folder: media.folder || "",
    });

    const handleSave = () => {
        put(route("media.update", media.id), {
            onSuccess: () => {
                toast.success("Mídia atualizada com sucesso");
                setIsEditMode(false);
            },
            onError: () => {
                toast.error("Erro ao atualizar mídia");
            },
        });
    };

    const handleCancel = () => {
        reset();
        setIsEditMode(false);
    };

    const handleDelete = () => {
        if (confirm(`Deseja excluir o arquivo "${media.original_name}"?`)) {
            router.delete(route("media.destroy", media.id), {
                onSuccess: () => {
                    toast.success("Arquivo excluído com sucesso");
                    router.visit(route("media.index"));
                },
                onError: () => toast.error("Erro ao excluir arquivo"),
            });
        }
    };

    const copyShareLink = () => {
        const shareUrl = route("media.preview", media.id);
        navigator.clipboard.writeText(shareUrl).then(() => {
            toast.success("Link copiado para a área de transferência");
            setShareDialogOpen(false);
        });
    };

    const getFileIcon = (type: string, size: number = 24) => {
        const className = `h-${size/4} w-${size/4}`;
        switch (type) {
            case 'image':
                return <IconPhoto className={`${className} text-green-600`} />;
            case 'video':
                return <IconVideo className={`${className} text-blue-600`} />;
            case 'audio':
                return <IconFileMusic className={`${className} text-purple-600`} />;
            default:
                return <IconFile className={`${className} text-gray-600`} />;
        }
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleString("pt-BR");
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

    return (
        <AuthenticatedLayout>
            <Head title={`Mídia: ${media.original_name}`} />

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
                            <h1 className="text-2xl font-semibold text-gray-900 flex items-center">
                                {getFileIcon(media.type)}
                                <span className="ml-2">{media.original_name}</span>
                            </h1>
                            <p className="text-gray-600">
                                {media.type.charAt(0).toUpperCase() + media.type.slice(1)} • {media.formatted_size}
                            </p>
                        </div>
                    </div>

                    <div className="flex space-x-2">
                        <Button variant="outline" asChild>
                            <Link href={route("media.download", media.id)}>
                                <IconDownload className="h-4 w-4 mr-2" />
                                Download
                            </Link>
                        </Button>

                        <Dialog open={shareDialogOpen} onOpenChange={setShareDialogOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <IconShare className="h-4 w-4 mr-2" />
                                    Compartilhar
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Compartilhar Mídia</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-4">
                                    <div>
                                        <Label>Link de Visualização</Label>
                                        <div className="flex mt-1">
                                            <Input
                                                value={route("media.preview", media.id)}
                                                readOnly
                                                className="rounded-r-none"
                                            />
                                            <Button
                                                onClick={copyShareLink}
                                                className="rounded-l-none"
                                            >
                                                <IconCopy className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>

                        <Button
                            variant={isEditMode ? "default" : "outline"}
                            onClick={() => setIsEditMode(!isEditMode)}
                        >
                            <IconEdit className="h-4 w-4 mr-2" />
                            {isEditMode ? "Cancelar" : "Editar"}
                        </Button>

                        <Button variant="destructive" onClick={handleDelete}>
                            <IconTrash className="h-4 w-4 mr-2" />
                            Excluir
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Media Preview */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Visualização</CardTitle>
                            </CardHeader>
                            <CardContent>
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
                            </CardContent>
                        </Card>

                        {/* Usage Statistics */}
                        <Card className="mt-6">
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <IconDatabase className="h-5 w-5 mr-2" />
                                    Estatísticas de Uso
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <p className="text-sm text-gray-600">Total de Reproduções</p>
                                        <p className="text-2xl font-bold">{usage_stats.total_plays}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Players Únicos</p>
                                        <p className="text-2xl font-bold">{usage_stats.unique_players}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Última Reprodução</p>
                                        <p className="text-sm">
                                            {usage_stats.last_played_at
                                                ? formatDate(usage_stats.last_played_at)
                                                : "Nunca"
                                            }
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Duração Média</p>
                                        <p className="text-sm">
                                            {usage_stats.avg_play_duration
                                                ? formatDuration(usage_stats.avg_play_duration)
                                                : "N/A"
                                            }
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Details Panel */}
                    <div className="space-y-6">
                        {/* File Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Informações do Arquivo</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {isEditMode ? (
                                    <div className="space-y-4">
                                        <div>
                                            <Label htmlFor="original_name">Nome</Label>
                                            <Input
                                                id="original_name"
                                                value={data.original_name}
                                                onChange={(e) => setData("original_name", e.target.value)}
                                                className="mt-1"
                                            />
                                            {errors.original_name && (
                                                <p className="text-sm text-red-600 mt-1">{errors.original_name}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="description">Descrição</Label>
                                            <Textarea
                                                id="description"
                                                value={data.description}
                                                onChange={(e) => setData("description", e.target.value)}
                                                className="mt-1"
                                                rows={3}
                                            />
                                        </div>

                                        <div>
                                            <Label htmlFor="tags">Tags</Label>
                                            <Input
                                                id="tags"
                                                value={data.tags}
                                                onChange={(e) => setData("tags", e.target.value)}
                                                placeholder="tag1, tag2, tag3"
                                                className="mt-1"
                                            />
                                            <p className="text-xs text-gray-500 mt-1">
                                                Separe múltiplas tags com vírgula
                                            </p>
                                        </div>

                                        <div className="flex space-x-2">
                                            <Button
                                                onClick={handleSave}
                                                disabled={processing}
                                                className="flex-1"
                                            >
                                                <IconCheck className="h-4 w-4 mr-2" />
                                                Salvar
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={handleCancel}
                                                className="flex-1"
                                            >
                                                <IconX className="h-4 w-4 mr-2" />
                                                Cancelar
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm text-gray-600">Nome do Arquivo</p>
                                            <p className="font-medium">{media.filename}</p>
                                        </div>

                                        <div>
                                            <p className="text-sm text-gray-600">Tipo MIME</p>
                                            <p className="font-medium">{media.mime_type}</p>
                                        </div>

                                        {media.duration && (
                                            <div>
                                                <p className="text-sm text-gray-600">Duração</p>
                                                <p className="font-medium">{formatDuration(media.duration)}</p>
                                            </div>
                                        )}

                                        {media.metadata?.resolution && (
                                            <div>
                                                <p className="text-sm text-gray-600">Resolução</p>
                                                <p className="font-medium">{media.metadata.resolution}</p>
                                            </div>
                                        )}

                                        {media.folder && (
                                            <div>
                                                <p className="text-sm text-gray-600">
                                                    <IconFolder className="h-4 w-4 inline mr-1" />
                                                    Pasta
                                                </p>
                                                <p className="font-medium">{media.folder}</p>
                                            </div>
                                        )}

                                        {media.description && (
                                            <div>
                                                <p className="text-sm text-gray-600">Descrição</p>
                                                <p className="font-medium">{media.description}</p>
                                            </div>
                                        )}

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

                                        <Separator />

                                        <div>
                                            <p className="text-sm text-gray-600">
                                                <IconCalendar className="h-4 w-4 inline mr-1" />
                                                Criado em
                                            </p>
                                            <p className="font-medium">{formatDate(media.created_at)}</p>
                                        </div>

                                        <div>
                                            <p className="text-sm text-gray-600">
                                                <IconClock className="h-4 w-4 inline mr-1" />
                                                Atualizado em
                                            </p>
                                            <p className="font-medium">{formatDate(media.updated_at)}</p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Usage in Playlists */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <IconPlaylist className="h-5 w-5 mr-2" />
                                    Usado em Playlists ({playlists.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {playlists.length === 0 ? (
                                    <p className="text-gray-500 text-sm">
                                        Esta mídia não está sendo usada em nenhuma playlist
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {playlists.map((playlist) => (
                                            <div key={playlist.id} className="flex justify-between items-center">
                                                <Link
                                                    href={route("playlists.show", playlist.id)}
                                                    className="text-blue-600 hover:underline"
                                                >
                                                    {playlist.name}
                                                </Link>
                                                <span className="text-xs text-gray-500">
                                                    {formatDate(playlist.created_at)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Player Usage */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <IconMonitor className="h-5 w-5 mr-2" />
                                    Reprodução por Players
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {players.length === 0 ? (
                                    <p className="text-gray-500 text-sm">
                                        Esta mídia ainda não foi reproduzida por nenhum player
                                    </p>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Player</TableHead>
                                                <TableHead>Reproduções</TableHead>
                                                <TableHead>Último Acesso</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {players.map((player) => (
                                                <TableRow key={player.id}>
                                                    <TableCell>
                                                        <Link
                                                            href={route("players.show", player.id)}
                                                            className="text-blue-600 hover:underline"
                                                        >
                                                            {player.name}
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell>{player.play_count}</TableCell>
                                                    <TableCell className="text-sm">
                                                        {player.last_played_at
                                                            ? formatDate(player.last_played_at)
                                                            : "Nunca"
                                                        }
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}