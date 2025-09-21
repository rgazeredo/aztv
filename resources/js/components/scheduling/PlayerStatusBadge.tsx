import { Badge } from "@/components/ui/badge";
import { IconWifi, IconWifiOff, IconClock } from "@tabler/icons-react";

interface PlayerStatusBadgeProps {
    status: 'online' | 'offline' | 'inactive';
    size?: 'sm' | 'md' | 'lg';
    showIcon?: boolean;
}

export default function PlayerStatusBadge({
    status,
    size = 'md',
    showIcon = true
}: PlayerStatusBadgeProps) {
    const getStatusConfig = () => {
        switch (status) {
            case 'online':
                return {
                    variant: 'default' as const,
                    className: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                    icon: <IconWifi className="h-3 w-3" />,
                    label: 'Online'
                };
            case 'offline':
                return {
                    variant: 'destructive' as const,
                    className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                    icon: <IconWifiOff className="h-3 w-3" />,
                    label: 'Offline'
                };
            case 'inactive':
                return {
                    variant: 'secondary' as const,
                    className: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
                    icon: <IconClock className="h-3 w-3" />,
                    label: 'Inativo'
                };
            default:
                return {
                    variant: 'secondary' as const,
                    className: '',
                    icon: <IconClock className="h-3 w-3" />,
                    label: 'Desconhecido'
                };
        }
    };

    const getSizeClass = () => {
        switch (size) {
            case 'sm':
                return 'text-xs px-1.5 py-0.5';
            case 'lg':
                return 'text-sm px-3 py-1';
            default:
                return 'text-xs px-2 py-1';
        }
    };

    const config = getStatusConfig();

    return (
        <Badge
            variant={config.variant}
            className={`${config.className} ${getSizeClass()} inline-flex items-center gap-1`}
        >
            {showIcon && config.icon}
            {config.label}
        </Badge>
    );
}