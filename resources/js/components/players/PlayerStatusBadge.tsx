import { Badge } from "@/components/ui/badge";
import { IconWifi, IconWifiOff } from "@tabler/icons-react";

interface Player {
    id: number;
    name: string;
    is_online: boolean;
    last_seen: string | null;
}

interface PlayerStatusBadgeProps {
    player: Player;
}

export default function PlayerStatusBadge({ player }: PlayerStatusBadgeProps) {
    if (player.is_online) {
        return (
            <Badge variant="default" className="bg-green-600">
                <IconWifi className="h-3 w-3 mr-1" />
                Online
            </Badge>
        );
    } else {
        return (
            <Badge variant="secondary" className="bg-red-100 text-red-800">
                <IconWifiOff className="h-3 w-3 mr-1" />
                Offline
            </Badge>
        );
    }
}