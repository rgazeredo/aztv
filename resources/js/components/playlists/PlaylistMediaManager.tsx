import { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    DragEndEvent,
} from "@dnd-kit/core";
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import {
    useSortable,
    SortableContext as SortableProvider,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/components/ui/dialog";
import {
    IconGripVertical,
    IconTrash,
    IconClock,
    IconPhoto,
    IconVideo,
    IconFileMusic,
    IconFile,
    IconAlertTriangle,
    IconPlus,
} from "@tabler/icons-react";
import { toast } from "sonner";

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

interface PlaylistMediaManagerProps {
    playlistId: number;
    items: PlaylistItem[];
    onItemsChange?: (items: PlaylistItem[]) => void;
    onAddMedia?: () => void;
}

function SortableMediaItem({ item, onUpdateDisplayTime, onRemove }: {
    item: PlaylistItem;
    onUpdateDisplayTime: (id: number, time: number) => void;
    onRemove: (id: number) => void;
}) {
    const [displayTime, setDisplayTime] = useState(item.display_time);
    const [deleteDialog, setDeleteDialog] = useState(false);

    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: item.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const getMediaIcon = (mimeType: string) => {
        if (mimeType.startsWith('image/')) return <IconPhoto className="h-4 w-4" />;
        if (mimeType.startsWith('video/')) return <IconVideo className="h-4 w-4" />;
        if (mimeType.startsWith('audio/')) return <IconFileMusic className="h-4 w-4" />;
        return <IconFile className="h-4 w-4" />;
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    const formatDuration = (seconds?: number) => {
        if (!seconds) return 'N/A';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const handleDisplayTimeChange = (value: string) => {
        const time = parseInt(value) || 1;
        setDisplayTime(time);
    };

    const handleDisplayTimeBlur = () => {
        const validTime = Math.max(1, Math.min(300, displayTime));
        setDisplayTime(validTime);
        onUpdateDisplayTime(item.id, validTime);
    };

    const handleRemove = () => {
        onRemove(item.id);
        setDeleteDialog(false);
    };

    return (
        <>
            <Card
                ref={setNodeRef}
                style={style}
                className={`transition-all ${isDragging ? 'opacity-50 scale-105 rotate-1' : ''}`}
            >
                <CardContent className="p-4">
                    <div className="flex items-center gap-4">
                        {/* Drag Handle */}
                        <div
                            {...attributes}
                            {...listeners}
                            className="cursor-grab active:cursor-grabbing p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            <IconGripVertical className="h-5 w-5 text-gray-400" />
                        </div>

                        {/* Thumbnail */}
                        <div className="flex-shrink-0">
                            {item.media_file.thumbnail_url ? (
                                <img
                                    src={item.media_file.thumbnail_url}
                                    alt={item.media_file.name}
                                    className="h-16 w-16 rounded-lg object-cover"
                                />
                            ) : (
                                <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                                    {getMediaIcon(item.media_file.mime_type)}
                                </div>
                            )}
                        </div>

                        {/* Media Info */}
                        <div className="flex-1 min-w-0">
                            <h4 className="font-medium text-gray-900 dark:text-gray-100 truncate">
                                {item.media_file.name}
                            </h4>
                            <p className="text-sm text-gray-500 dark:text-gray-400 truncate">
                                {item.media_file.filename}
                            </p>
                            <div className="flex items-center gap-3 mt-1">
                                <Badge variant="secondary" className="text-xs">
                                    {getMediaIcon(item.media_file.mime_type)}
                                    <span className="ml-1">
                                        {item.media_file.mime_type.split('/')[1].toUpperCase()}
                                    </span>
                                </Badge>
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {formatFileSize(item.media_file.file_size)}
                                </span>
                                {item.media_file.duration && (
                                    <span className="text-xs text-gray-500 dark:text-gray-400">
                                        <IconClock className="inline h-3 w-3 mr-1" />
                                        {formatDuration(item.media_file.duration)}
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Display Time Input */}
                        <div className="flex-shrink-0 w-32">
                            <Label htmlFor={`display-time-${item.id}`} className="text-xs">
                                Tempo (s)
                            </Label>
                            <Input
                                id={`display-time-${item.id}`}
                                type="number"
                                min="1"
                                max="300"
                                value={displayTime}
                                onChange={(e) => handleDisplayTimeChange(e.target.value)}
                                onBlur={handleDisplayTimeBlur}
                                className="text-center"
                                size="sm"
                            />
                        </div>

                        {/* Remove Button */}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteDialog(true)}
                            className="text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950"
                        >
                            <IconTrash className="h-4 w-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteDialog} onOpenChange={setDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remover Mídia</DialogTitle>
                        <DialogDescription>
                            Tem certeza que deseja remover "{item.media_file.name}" desta playlist?
                            Esta ação não pode ser desfeita.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialog(false)}>
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={handleRemove}>
                            Remover
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function PlaylistMediaManager({
    playlistId,
    items: initialItems,
    onItemsChange,
    onAddMedia
}: PlaylistMediaManagerProps) {
    const [items, setItems] = useState<PlaylistItem[]>(initialItems);
    const [saving, setSaving] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    // Update local state when props change
    useEffect(() => {
        setItems(initialItems);
    }, [initialItems]);

    const handleDragEnd = async (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const oldIndex = items.findIndex((item) => item.id === active.id);
        const newIndex = items.findIndex((item) => item.id === over.id);

        const newItems = arrayMove(items, oldIndex, newIndex);

        // Update order values
        const updatedItems = newItems.map((item, index) => ({
            ...item,
            order: index + 1,
        }));

        setItems(updatedItems);
        onItemsChange?.(updatedItems);

        // Auto-save reorder
        await saveOrder(updatedItems);
    };

    const saveOrder = async (orderedItems: PlaylistItem[]) => {
        setSaving(true);

        try {
            const orderData = orderedItems.map((item, index) => ({
                id: item.id,
                order: index + 1,
            }));

            await router.patch(route('playlists.reorder', playlistId), {
                items: orderData
            }, {
                preserveState: true,
                onSuccess: () => {
                    toast.success('Ordem dos itens atualizada');
                },
                onError: () => {
                    toast.error('Erro ao atualizar ordem dos itens');
                    // Revert on error
                    setItems(initialItems);
                }
            });
        } catch (error) {
            toast.error('Erro ao salvar alterações');
            setItems(initialItems);
        } finally {
            setSaving(false);
        }
    };

    const handleUpdateDisplayTime = async (itemId: number, displayTime: number) => {
        const updatedItems = items.map(item =>
            item.id === itemId ? { ...item, display_time: displayTime } : item
        );

        setItems(updatedItems);
        onItemsChange?.(updatedItems);

        // Auto-save display time
        try {
            await router.patch(route('playlist-items.update', itemId), {
                display_time: displayTime
            }, {
                preserveState: true,
                onError: () => {
                    toast.error('Erro ao atualizar tempo de exibição');
                }
            });
        } catch (error) {
            toast.error('Erro ao salvar tempo de exibição');
        }
    };

    const handleRemoveItem = async (itemId: number) => {
        try {
            await router.delete(route('playlist-items.destroy', itemId), {
                preserveState: true,
                onSuccess: () => {
                    const newItems = items.filter(item => item.id !== itemId)
                        .map((item, index) => ({ ...item, order: index + 1 }));

                    setItems(newItems);
                    onItemsChange?.(newItems);
                    toast.success('Mídia removida da playlist');
                },
                onError: () => {
                    toast.error('Erro ao remover mídia da playlist');
                }
            });
        } catch (error) {
            toast.error('Erro ao remover mídia');
        }
    };

    const calculateTotalDuration = () => {
        return items.reduce((total, item) => total + item.display_time, 0);
    };

    const formatTotalDuration = (seconds: number) => {
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

    if (items.length === 0) {
        return (
            <div className="text-center py-12">
                <IconPlus className="mx-auto h-12 w-12 text-gray-400" />
                <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    Nenhuma mídia na playlist
                </h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Comece adicionando mídias da sua biblioteca para criar a playlist.
                </p>
                <div className="mt-6">
                    <Button onClick={onAddMedia}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        Adicionar Mídias
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Summary */}
            <div className="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                <div className="flex items-center gap-4">
                    <div>
                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {items.length} {items.length === 1 ? 'item' : 'itens'} na playlist
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Duração total: {formatTotalDuration(calculateTotalDuration())}
                        </p>
                    </div>
                    {saving && (
                        <div className="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                            Salvando...
                        </div>
                    )}
                </div>

                <Button onClick={onAddMedia}>
                    <IconPlus className="mr-2 h-4 w-4" />
                    Adicionar Mídias
                </Button>
            </div>

            {/* Help Text */}
            <div className="flex items-start gap-2 rounded-lg bg-blue-50 p-3 dark:bg-blue-950/20">
                <IconAlertTriangle className="h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5" />
                <div className="text-sm text-blue-700 dark:text-blue-300">
                    <p className="font-medium">Dicas:</p>
                    <ul className="mt-1 list-disc list-inside space-y-1">
                        <li>Arraste e solte os itens para reordenar a playlist</li>
                        <li>Configure o tempo de exibição para cada mídia (1-300 segundos)</li>
                        <li>As alterações são salvas automaticamente</li>
                    </ul>
                </div>
            </div>

            {/* Sortable List */}
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <SortableContext items={items.map(item => item.id)} strategy={verticalListSortingStrategy}>
                    <div className="space-y-3">
                        {items.map((item) => (
                            <SortableMediaItem
                                key={item.id}
                                item={item}
                                onUpdateDisplayTime={handleUpdateDisplayTime}
                                onRemove={handleRemoveItem}
                            />
                        ))}
                    </div>
                </SortableContext>
            </DndContext>
        </div>
    );
}