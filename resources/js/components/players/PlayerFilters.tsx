import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { IconSearch, IconX } from "@tabler/icons-react";

interface PlayerFiltersProps {
    search: string;
    status: string;
    group: string;
    groups: string[];
    onSearchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onGroupChange: (value: string) => void;
    onClearFilters: () => void;
}

export default function PlayerFilters({
    search,
    status,
    group,
    groups,
    onSearchChange,
    onStatusChange,
    onGroupChange,
    onClearFilters,
}: PlayerFiltersProps) {
    const hasActiveFilters = search || status !== "all" || group !== "all";

    return (
        <div className="flex flex-col sm:flex-row gap-4 p-4 bg-gray-50 rounded-lg">
            <div className="flex-1">
                <div className="relative">
                    <IconSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                    <Input
                        placeholder="Buscar players..."
                        value={search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="pl-9"
                    />
                </div>
            </div>

            <div className="flex gap-2">
                <Select value={status} onValueChange={onStatusChange}>
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="online">Online</SelectItem>
                        <SelectItem value="offline">Offline</SelectItem>
                    </SelectContent>
                </Select>

                <Select value={group} onValueChange={onGroupChange}>
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="Grupo" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        {groups.map((groupName) => (
                            <SelectItem key={groupName} value={groupName}>
                                {groupName}
                            </SelectItem>
                        ))}
                        <SelectItem value="no_group">Sem grupo</SelectItem>
                    </SelectContent>
                </Select>

                {hasActiveFilters && (
                    <Button
                        variant="outline"
                        onClick={onClearFilters}
                        className="px-3"
                    >
                        <IconX className="h-4 w-4" />
                    </Button>
                )}
            </div>
        </div>
    );
}