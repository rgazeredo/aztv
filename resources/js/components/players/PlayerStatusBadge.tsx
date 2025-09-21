import { Badge } from "@/components/ui/badge";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { IconCircle } from "@tabler/icons-react";

interface PlayerStatusBadgeProps {
    isOnline: boolean;
    lastSeen?: string;
    className?: string;
}

export default function PlayerStatusBadge({
    isOnline,
    lastSeen,
    className = ""
}: PlayerStatusBadgeProps) {
    const formatLastSeen = (dateString?: string) => {
        if (!dateString) return "Nunca visto";

        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffMins < 1) return "Agora mesmo";
        if (diffMins < 60) return `${diffMins}min atrás`;
        if (diffHours < 24) return `${diffHours}h atrás`;
        if (diffDays < 7) return `${diffDays}d atrás`;

        return date.toLocaleDateString("pt-BR");
    };

    const badge = (
        <Badge
            variant={isOnline ? "default" : "secondary"}
            className={`
                ${isOnline
                    ? "bg-green-100 text-green-800 border-green-200 hover:bg-green-200"
                    : "bg-red-100 text-red-800 border-red-200 hover:bg-red-200"
                }
                ${className}
            `}
        >
            <IconCircle
                className={`h-2 w-2 mr-1 ${isOnline ? "fill-green-600" : "fill-red-600"}`}
            />
            {isOnline ? "Online" : "Offline"}
        </Badge>
    );

    if (lastSeen) {
        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        {badge}
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Última atividade: {formatLastSeen(lastSeen)}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    return badge;
}