import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    IconDevices,
    IconWifi,
    IconWifiOff,
    IconUsers,
} from "@tabler/icons-react";

interface PlayerStatsProps {
    total: number;
    online: number;
    offline: number;
    groups: number;
}

export default function PlayerStats({ total, online, offline, groups }: PlayerStatsProps) {
    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Total de Players
                    </CardTitle>
                    <IconDevices className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{total}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Players Online
                    </CardTitle>
                    <IconWifi className="h-4 w-4 text-green-600" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-green-600">{online}</div>
                    <p className="text-xs text-muted-foreground">
                        {total > 0 ? Math.round((online / total) * 100) : 0}% do total
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Players Offline
                    </CardTitle>
                    <IconWifiOff className="h-4 w-4 text-red-600" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-red-600">{offline}</div>
                    <p className="text-xs text-muted-foreground">
                        {total > 0 ? Math.round((offline / total) * 100) : 0}% do total
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Grupos Ativos
                    </CardTitle>
                    <IconUsers className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{groups}</div>
                </CardContent>
            </Card>
        </div>
    );
}