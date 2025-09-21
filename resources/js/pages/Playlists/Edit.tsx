import { useState } from "react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
    IconArrowLeft,
    IconEdit,
    IconSave,
    IconPlaylistAdd,
    IconClock,
    IconPhoto,
    IconMusic,
    IconVideo,
} from "@tabler/icons-react";
import { toast } from "sonner";
import PlaylistMediaManager from "@/components/playlists/PlaylistMediaManager";
import MediaSelectionModal from "@/components/playlists/MediaSelectionModal";

interface MediaFile {
    id: number;
    name: string;
    filename: string;
    mime_type: string;
    file_size: number;
    duration?: number;
    thumbnail_url?: string;
    url: string;
}

interface PlaylistItem {
    id: number;
    order: number;
    display_time: number;
    media_file: MediaFile;
}

interface Playlist {
    id: number;
    name: string;
    description?: string;
    status: 'active' | 'inactive';
    loop_enabled: boolean;
    items: PlaylistItem[];
    created_at: string;
    updated_at: string;
}

interface EditPlaylistForm {
    name: string;
    description: string;
    status: 'active' | 'inactive';
    loop_enabled: boolean;
}

interface PlaylistsEditProps {
    playlist: Playlist;
}

export default function PlaylistsEdit({ playlist }: PlaylistsEditProps) {
    const [items, setItems] = useState<PlaylistItem[]>(playlist.items);
    const [mediaModalOpen, setMediaModalOpen] = useState(false);

    const { data, setData, patch, processing, errors } = useForm<EditPlaylistForm>({
        name: playlist.name,
        description: playlist.description || '',
        status: playlist.status,
        loop_enabled: playlist.loop_enabled,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        patch(route('playlists.update', playlist.id), {
            onSuccess: () => {
                toast.success('Playlist atualizada com sucesso!');
            },
            onError: () => {
                toast.error('Erro ao atualizar playlist. Verifique os dados e tente novamente.');
            }
        });
    };

    const calculateTotalDuration = () => {
        return items.reduce((total, item) => total + item.display_time, 0);
    };

    const formatDuration = (seconds: number) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`;
        } else if (minutes > 0) {
            return `${minutes}m ${secs}s`;
        } else {
            return `${secs}s`;
        }
    };

    const getMediaTypeStats = () => {
        const stats = {
            images: 0,
            videos: 0,
            audios: 0,
            others: 0,
        };

        items.forEach(item => {
            const mimeType = item.media_file.mime_type;
            if (mimeType.startsWith('image/')) stats.images++;
            else if (mimeType.startsWith('video/')) stats.videos++;
            else if (mimeType.startsWith('audio/')) stats.audios++;
            else stats.others++;
        });

        return stats;
    };

    const handleItemsChange = (newItems: PlaylistItem[]) => {
        setItems(newItems);
    };

    const handleMediaAdded = () => {
        // Reload the page to get updated playlist data
        router.reload({ only: ['playlist'] });
    };

    const existingMediaIds = items.map(item => item.media_file.id);
    const mediaStats = getMediaTypeStats();

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link href={route('playlists.index')}>
                        <Button variant="ghost" size="sm">
                            <IconArrowLeft className="mr-2 h-4 w-4" />
                            Voltar
                        </Button>
                    </Link>
                    <div className="flex-1">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Editar Playlist
                        </h2>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {playlist.name}
                        </p>
                    </div>
                    <Badge
                        variant={playlist.status === 'active' ? 'default' : 'secondary'}
                        className={playlist.status === 'active' ? 'bg-green-100 text-green-800' : ''}
                    >
                        {playlist.status === 'active' ? 'Ativa' : 'Inativa'}
                    </Badge>
                </div>
            }
        >
            <Head title={`Editar Playlist - ${playlist.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Tabs defaultValue="media" className="space-y-6">
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="media" className="flex items-center gap-2">
                                <IconPlaylistAdd className="h-4 w-4" />
                                Gerenciar Mídias
                            </TabsTrigger>
                            <TabsTrigger value="settings" className="flex items-center gap-2">
                                <IconEdit className="h-4 w-4" />
                                Configurações
                            </TabsTrigger>
                        </TabsList>

                        {/* Media Management Tab */}
                        <TabsContent value="media" className="space-y-6">
                            {/* Playlist Statistics */}
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <Card>
                                    <CardContent className="p-6">
                                        <div className="flex items-center">
                                            <IconPlaylistAdd className="h-8 w-8 text-blue-500" />
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                    Total de Itens
                                                </p>
                                                <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                                    {items.length}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="p-6">
                                        <div className="flex items-center">
                                            <IconClock className="h-8 w-8 text-green-500" />
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                    Duração Total
                                                </p>
                                                <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                                    {formatDuration(calculateTotalDuration())}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="p-6">
                                        <div className="flex items-center">
                                            <IconPhoto className="h-8 w-8 text-purple-500" />
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                    Imagens
                                                </p>
                                                <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                                    {mediaStats.images}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="p-6">
                                        <div className="flex items-center">
                                            <IconVideo className="h-8 w-8 text-red-500" />
                                            <div className="ml-4">
                                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                    Vídeos
                                                </p>
                                                <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                                    {mediaStats.videos}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Media Manager */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Mídias da Playlist</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PlaylistMediaManager
                                        playlistId={playlist.id}
                                        items={items}
                                        onItemsChange={handleItemsChange}
                                        onAddMedia={() => setMediaModalOpen(true)}
                                    />
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {/* Settings Tab */}
                        <TabsContent value="settings" className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <IconEdit className="h-5 w-5" />
                                        Configurações da Playlist
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleSubmit} className="space-y-6">
                                        {/* Nome */}
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                Nome da Playlist *
                                            </Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder="Digite o nome da playlist"
                                                className={errors.name ? 'border-red-500' : ''}
                                                maxLength={255}
                                                required
                                            />
                                            {errors.name && (
                                                <p className="text-sm text-red-600 dark:text-red-400">
                                                    {errors.name}
                                                </p>
                                            )}
                                        </div>

                                        {/* Descrição */}
                                        <div className="space-y-2">
                                            <Label htmlFor="description">
                                                Descrição
                                            </Label>
                                            <Textarea
                                                id="description"
                                                value={data.description}
                                                onChange={(e) => setData('description', e.target.value)}
                                                placeholder="Descreva o propósito ou conteúdo desta playlist (opcional)"
                                                className={errors.description ? 'border-red-500' : ''}
                                                rows={4}
                                                maxLength={500}
                                            />
                                            {errors.description && (
                                                <p className="text-sm text-red-600 dark:text-red-400">
                                                    {errors.description}
                                                </p>
                                            )}
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {data.description.length}/500 caracteres
                                            </p>
                                        </div>

                                        {/* Status */}
                                        <div className="space-y-2">
                                            <Label htmlFor="status">
                                                Status
                                            </Label>
                                            <Select
                                                value={data.status}
                                                onValueChange={(value: 'active' | 'inactive') => setData('status', value)}
                                            >
                                                <SelectTrigger className={errors.status ? 'border-red-500' : ''}>
                                                    <SelectValue placeholder="Selecione o status" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="active">
                                                        Ativa - A playlist estará disponível para reprodução
                                                    </SelectItem>
                                                    <SelectItem value="inactive">
                                                        Inativa - A playlist ficará salva mas não será reproduzida
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {errors.status && (
                                                <p className="text-sm text-red-600 dark:text-red-400">
                                                    {errors.status}
                                                </p>
                                            )}
                                        </div>

                                        {/* Loop */}
                                        <div className="space-y-2">
                                            <Label htmlFor="loop_enabled">
                                                Reprodução em Loop
                                            </Label>
                                            <Select
                                                value={data.loop_enabled ? 'true' : 'false'}
                                                onValueChange={(value) => setData('loop_enabled', value === 'true')}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="true">
                                                        Habilitado - A playlist será reproduzida continuamente
                                                    </SelectItem>
                                                    <SelectItem value="false">
                                                        Desabilitado - A playlist será reproduzida apenas uma vez
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <Separator />

                                        {/* Informações da Playlist */}
                                        <div className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-900">
                                            <h4 className="mb-3 font-medium text-gray-900 dark:text-gray-100">
                                                Resumo da Playlist
                                            </h4>
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <p><strong>Criada em:</strong> {new Date(playlist.created_at).toLocaleDateString('pt-BR')}</p>
                                                    <p><strong>Última atualização:</strong> {new Date(playlist.updated_at).toLocaleDateString('pt-BR')}</p>
                                                </div>
                                                <div>
                                                    <p><strong>Total de itens:</strong> {items.length}</p>
                                                    <p><strong>Duração total:</strong> {formatDuration(calculateTotalDuration())}</p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Buttons */}
                                        <div className="flex items-center justify-between border-t pt-6">
                                            <Link href={route('playlists.index')}>
                                                <Button type="button" variant="outline">
                                                    Cancelar
                                                </Button>
                                            </Link>

                                            <Button
                                                type="submit"
                                                disabled={processing || !data.name.trim()}
                                                className="min-w-32"
                                            >
                                                {processing ? (
                                                    <>
                                                        <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                        Salvando...
                                                    </>
                                                ) : (
                                                    <>
                                                        <IconSave className="mr-2 h-4 w-4" />
                                                        Salvar Alterações
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>
            </div>

            {/* Media Selection Modal */}
            <MediaSelectionModal
                open={mediaModalOpen}
                onOpenChange={setMediaModalOpen}
                playlistId={playlist.id}
                existingMediaIds={existingMediaIds}
                onMediaAdded={handleMediaAdded}
            />
        </AuthenticatedLayout>
    );
}