import MediaCard from "./MediaCard";

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

interface MediaGridProps {
    media: MediaFile[];
    selectedFiles: number[];
    onSelectFile: (id: number) => void;
    onDeleteFile?: (media: MediaFile) => void;
    className?: string;
}

export default function MediaGrid({
    media,
    selectedFiles,
    onSelectFile,
    onDeleteFile,
    className = "",
}: MediaGridProps) {
    return (
        <div className={`grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 ${className}`}>
            {media.map((file) => (
                <MediaCard
                    key={file.id}
                    media={file}
                    isSelected={selectedFiles.includes(file.id)}
                    onSelect={onSelectFile}
                    onDelete={onDeleteFile}
                />
            ))}
        </div>
    );
}