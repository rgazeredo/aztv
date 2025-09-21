import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { FileText, Search, Filter, RefreshCw, ArrowLeft, Monitor } from 'lucide-react';

interface Player {
    id: number;
    name: string;
    alias?: string;
}

interface Log {
    id: number;
    type: string;
    level: string;
    message: string;
    context?: any;
    created_at: string;
}

interface Props {
    player: Player;
    logs: {
        data: Log[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters?: {
        level?: string;
        type?: string;
        search?: string;
    };
}

export default function Logs({ player, logs, filters = {} }: Props) {
    const [selectedLevel, setSelectedLevel] = useState(filters.level || 'all');
    const [selectedType, setSelectedType] = useState(filters.type || 'all');
    const [search, setSearch] = useState(filters.search || '');

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Players',
            href: '/players',
        },
        {
            title: player.name,
            href: `/players/${player.id}`,
        },
        {
            title: 'Logs',
            href: `/players/${player.id}/logs`,
        },
    ];

    const getLevelBadge = (level: string | undefined) => {
        const safeLevel = level || 'info';

        const getVariant = (level: string) => {
            switch (level) {
                case 'error':
                case 'critical':
                    return 'destructive';
                case 'warning':
                    return 'secondary';
                case 'info':
                    return 'default';
                case 'debug':
                    return 'outline';
                default:
                    return 'secondary';
            }
        };

        return (
            <Badge variant={getVariant(safeLevel) as any}>
                {safeLevel.toUpperCase()}
            </Badge>
        );
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('pt-BR');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Logs - ${player.name}`} />
            <div className="flex-1 space-y-4 p-4 pt-6">

            {/* Header */}
            <div className="flex items-center justify-between space-y-2">
                <div>
                    <h2 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                        <FileText className="h-8 w-8" />
                        Logs - {player.name}
                    </h2>
                    {player.alias && (
                        <p className="text-muted-foreground">{player.alias}</p>
                    )}
                </div>
                <div className="flex items-center space-x-2">
                    <Button variant="outline" asChild>
                        <Link href={`/players/${player.id}`}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Voltar ao Player
                        </Link>
                    </Button>
                    <Button
                        onClick={() => window.location.reload()}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Atualizar
                    </Button>
                </div>
            </div>

            {/* Filters */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Filter className="h-5 w-5" />
                        Filtros
                    </CardTitle>
                    <CardDescription>
                        Use os filtros abaixo para encontrar logs específicos
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-col space-y-4 md:flex-row md:space-y-0 md:space-x-4">
                        <div className="flex-1">
                            <div className="relative">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar por mensagem..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <Select value={selectedLevel} onValueChange={setSelectedLevel}>
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Nível" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos os níveis</SelectItem>
                                <SelectItem value="critical">Critical</SelectItem>
                                <SelectItem value="error">Error</SelectItem>
                                <SelectItem value="warning">Warning</SelectItem>
                                <SelectItem value="info">Info</SelectItem>
                                <SelectItem value="debug">Debug</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={selectedType} onValueChange={setSelectedType}>
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos os tipos</SelectItem>
                                <SelectItem value="connection">Conexão</SelectItem>
                                <SelectItem value="playback">Reprodução</SelectItem>
                                <SelectItem value="system">Sistema</SelectItem>
                                <SelectItem value="app">Aplicação</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            {/* Logs Table */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Monitor className="h-5 w-5" />
                        Logs do Player
                    </CardTitle>
                    <CardDescription>
                        {logs.total} logs encontrados
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {logs.data.length > 0 ? (
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Data/Hora</TableHead>
                                        <TableHead>Nível</TableHead>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Mensagem</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {logs.data.map((log) => (
                                        <TableRow key={log.id}>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(log.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                {getLevelBadge(log.level)}
                                            </TableCell>
                                            <TableCell>
                                                <span className="capitalize">{log.type}</span>
                                            </TableCell>
                                            <TableCell>
                                                <div className="max-w-md">
                                                    <p className="truncate">{log.message}</p>
                                                    {log.context && Object.keys(log.context).length > 0 && (
                                                        <details className="mt-2">
                                                            <summary className="cursor-pointer text-primary text-xs">
                                                                Ver contexto
                                                            </summary>
                                                            <pre className="mt-2 bg-muted p-2 rounded text-xs overflow-x-auto">
                                                                {JSON.stringify(log.context, null, 2)}
                                                            </pre>
                                                        </details>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-8 text-center">
                            <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold">Nenhum log encontrado</h3>
                            <p className="text-muted-foreground">
                                Não há logs para este player com os filtros aplicados
                            </p>
                        </div>
                    )}
                </CardContent>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between px-6 py-4 border-t">
                        <div className="text-sm text-muted-foreground">
                            Mostrando {((logs.current_page - 1) * logs.per_page) + 1} a{' '}
                            {Math.min(logs.current_page * logs.per_page, logs.total)} de{' '}
                            {logs.total} resultados
                        </div>
                        <div className="flex items-center space-x-2">
                            {logs.current_page > 1 ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/players/${player.id}/logs?page=${logs.current_page - 1}`}>
                                        Anterior
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Anterior
                                </Button>
                            )}
                            <Button variant="default" size="sm" disabled>
                                {logs.current_page}
                            </Button>
                            {logs.current_page < logs.last_page ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/players/${player.id}/logs?page=${logs.current_page + 1}`}>
                                        Próximo
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Próximo
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </Card>
            </div>
        </AppLayout>
    );
}