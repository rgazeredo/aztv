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
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
    IconArrowLeft,
    IconPlaylistAdd,
    IconInfoCircle,
} from "@tabler/icons-react";
import { toast } from "sonner";

interface CreatePlaylistForm {
    name: string;
    description: string;
    status: 'active' | 'inactive';
}

export default function PlaylistsCreate() {
    const { data, setData, post, processing, errors } = useForm<CreatePlaylistForm>({
        name: '',
        description: '',
        status: 'active',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(route('playlists.store'), {
            onSuccess: (page: any) => {
                toast.success('Playlist criada com sucesso!');
                // Redirect to edit page to add media items
                const playlistId = page.props.playlist?.id;
                if (playlistId) {
                    router.visit(route('playlists.edit', playlistId));
                }
            },
            onError: () => {
                toast.error('Erro ao criar playlist. Verifique os dados e tente novamente.');
            }
        });
    };

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
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Nova Playlist
                    </h2>
                </div>
            }
        >
            <Head title="Nova Playlist" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <IconPlaylistAdd className="h-5 w-5" />
                                Criar Nova Playlist
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Alert className="mb-6">
                                <IconInfoCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Após criar a playlist, você será redirecionado para a página de edição
                                    onde poderá adicionar mídias e configurar a ordem de reprodução.
                                </AlertDescription>
                            </Alert>

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
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Máximo de 255 caracteres
                                    </p>
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
                                        Máximo de 500 caracteres ({data.description.length}/500)
                                    </p>
                                </div>

                                {/* Status */}
                                <div className="space-y-2">
                                    <Label htmlFor="status">
                                        Status Inicial
                                    </Label>
                                    <Select
                                        value={data.status}
                                        onValueChange={(value: 'active' | 'inactive') => setData('status', value)}
                                    >
                                        <SelectTrigger className={errors.status ? 'border-red-500' : ''}>
                                            <SelectValue placeholder="Selecione o status inicial" />
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

                                {/* Preview */}
                                {data.name && (
                                    <div className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-900">
                                        <h4 className="mb-2 font-medium text-gray-900 dark:text-gray-100">
                                            Preview da Playlist
                                        </h4>
                                        <div className="space-y-1 text-sm">
                                            <p><strong>Nome:</strong> {data.name}</p>
                                            {data.description && (
                                                <p><strong>Descrição:</strong> {data.description}</p>
                                            )}
                                            <p>
                                                <strong>Status:</strong>{' '}
                                                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                    data.status === 'active'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
                                                }`}>
                                                    {data.status === 'active' ? 'Ativa' : 'Inativa'}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                )}

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
                                                Criando...
                                            </>
                                        ) : (
                                            <>
                                                <IconPlaylistAdd className="mr-2 h-4 w-4" />
                                                Criar Playlist
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Help Section */}
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Próximos Passos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                                <div className="flex items-start gap-2">
                                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                                        1
                                    </span>
                                    <p>
                                        Após criar a playlist, você será redirecionado para a página de edição
                                    </p>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                                        2
                                    </span>
                                    <p>
                                        Na página de edição, você poderá adicionar mídias da sua biblioteca
                                    </p>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                                        3
                                    </span>
                                    <p>
                                        Configure a ordem de reprodução arrastando e soltando os itens
                                    </p>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                                        4
                                    </span>
                                    <p>
                                        Defina o tempo de exibição para cada mídia individualmente
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}