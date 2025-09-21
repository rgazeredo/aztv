import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { TenantStatusBadge } from '@/components/TenantStatusBadge';
import { TenantActionButtons } from '@/components/TenantActionButtons';
import { useTenantList } from '@/hooks/useAdminData';
import {
  Search,
  Filter,
  Plus,
  RefreshCw,
  Building2,
  Users,
  HardDrive,
  Calendar,
  ExternalLink,
} from 'lucide-react';
import { router } from '@inertiajs/react';
import { useDebounce } from '@/hooks/useDebounce';

const statusOptions = [
  { value: '', label: 'Todos os status' },
  { value: 'active', label: 'Ativo' },
  { value: 'suspended', label: 'Suspenso' },
  { value: 'cancelled', label: 'Cancelado' },
];

const planOptions = [
  { value: '', label: 'Todos os planos' },
  { value: 'basic', label: 'Básico' },
  { value: 'professional', label: 'Profissional' },
  { value: 'enterprise', label: 'Enterprise' },
];

const perPageOptions = [
  { value: 10, label: '10 por página' },
  { value: 25, label: '25 por página' },
  { value: 50, label: '50 por página' },
  { value: 100, label: '100 por página' },
];

interface TenantIndexPageProps {
  tenants?: any[];
  pagination?: any;
}

export default function TenantIndex({ tenants: initialTenants, pagination: initialPagination }: TenantIndexPageProps) {
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [planFilter, setPlanFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(25);

  const debouncedSearch = useDebounce(search, 500);

  const filters = useMemo(() => ({
    search: debouncedSearch,
    status: statusFilter,
    plan: planFilter,
    page: currentPage,
    perPage: perPage,
  }), [debouncedSearch, statusFilter, planFilter, currentPage, perPage]);

  const { tenants, pagination, loading, error, refresh } = useTenantList(filters);

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Nunca';
    return new Date(dateString).toLocaleDateString('pt-BR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const getPlanBadge = (plan: string) => {
    const variants = {
      basic: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
      professional: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
      enterprise: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    };

    const labels = {
      basic: 'Básico',
      professional: 'Profissional',
      enterprise: 'Enterprise',
    };

    return (
      <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${variants[plan as keyof typeof variants] || 'bg-gray-100 text-gray-800'}`}>
        {labels[plan as keyof typeof labels] || plan}
      </span>
    );
  };

  const handleStatusChange = (tenantId: number, newStatus: string) => {
    refresh();
  };

  const handleDelete = (tenantId: number) => {
    refresh();
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const handleCreateTenant = () => {
    router.visit('/admin/tenants/create');
  };

  const renderPagination = () => {
    if (pagination.last_page <= 1) return null;

    const pages = [];
    const maxVisiblePages = 5;
    const startPage = Math.max(1, pagination.current_page - Math.floor(maxVisiblePages / 2));
    const endPage = Math.min(pagination.last_page, startPage + maxVisiblePages - 1);

    for (let i = startPage; i <= endPage; i++) {
      pages.push(
        <Button
          key={i}
          variant={i === pagination.current_page ? 'default' : 'outline'}
          size="sm"
          onClick={() => handlePageChange(i)}
          disabled={loading}
        >
          {i}
        </Button>
      );
    }

    return (
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          Mostrando {((pagination.current_page - 1) * pagination.per_page) + 1} a{' '}
          {Math.min(pagination.current_page * pagination.per_page, pagination.total)} de{' '}
          {pagination.total} resultados
        </p>
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(pagination.current_page - 1)}
            disabled={pagination.current_page <= 1 || loading}
          >
            Anterior
          </Button>
          {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(pagination.current_page + 1)}
            disabled={pagination.current_page >= pagination.last_page || loading}
          >
            Próximo
          </Button>
        </div>
      </div>
    );
  };

  if (error) {
    return (
      <div className="container mx-auto py-8">
        <Head title="Gerenciamento de Tenants" />
        <div className="text-center">
          <p className="text-red-600 mb-4">{error}</p>
          <Button onClick={refresh}>Tentar novamente</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto py-8 space-y-6">
      <Head title="Gerenciamento de Tenants" />

      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Tenants</h1>
          <p className="text-muted-foreground">
            Gerencie todos os tenants da plataforma
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={refresh}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Atualizar
          </Button>
          <Button onClick={handleCreateTenant}>
            <Plus className="h-4 w-4 mr-2" />
            Novo Tenant
          </Button>
        </div>
      </div>

      {/* Stats Summary */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total de Tenants</CardTitle>
            <Building2 className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{pagination?.total || 0}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Tenants Ativos</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {tenants?.filter(t => t.status === 'active').length || 0}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total de Players</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {tenants?.reduce((sum, tenant) => sum + (tenant.players_count || 0), 0) || 0}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Storage Total</CardTitle>
            <HardDrive className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {formatBytes(tenants?.reduce((sum, tenant) => sum + (tenant.storage_used || 0), 0) || 0)}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center">
            <Filter className="h-5 w-5 mr-2" />
            Filtros
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Buscar por nome, slug ou email..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="pl-8"
                />
              </div>
            </div>
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="w-[180px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {statusOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={planFilter} onValueChange={setPlanFilter}>
              <SelectTrigger className="w-[180px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {planOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={perPage.toString()} onValueChange={(value) => setPerPage(Number(value))}>
              <SelectTrigger className="w-[150px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {perPageOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value.toString()}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Table */}
      <Card>
        <CardHeader>
          <CardTitle>Lista de Tenants</CardTitle>
          <CardDescription>
            Todos os tenants cadastrados na plataforma
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tenant</TableHead>
                  <TableHead>Slug/Domínio</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Plano</TableHead>
                  <TableHead>Players</TableHead>
                  <TableHead>Storage</TableHead>
                  <TableHead>Criado em</TableHead>
                  <TableHead className="text-right">Ações</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  [...Array(perPage)].map((_, i) => (
                    <TableRow key={i}>
                      {[...Array(8)].map((_, j) => (
                        <TableCell key={j}>
                          <div className="h-4 bg-muted animate-pulse rounded" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : tenants && tenants.length > 0 ? (
                  tenants.map((tenant) => (
                    <TableRow key={tenant.id}>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="font-medium">{tenant.name}</span>
                          <span className="text-sm text-muted-foreground">{tenant.email}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="font-mono text-sm">{tenant.slug}</span>
                          {tenant.domain && (
                            <div className="flex items-center text-sm text-muted-foreground">
                              <ExternalLink className="h-3 w-3 mr-1" />
                              {tenant.domain}
                            </div>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <TenantStatusBadge status={tenant.status} />
                      </TableCell>
                      <TableCell>{getPlanBadge(tenant.subscription_plan)}</TableCell>
                      <TableCell>
                        <span className="font-medium">{tenant.players_count || 0}</span>
                      </TableCell>
                      <TableCell>{formatBytes(tenant.storage_used || 0)}</TableCell>
                      <TableCell>{formatDate(tenant.created_at)}</TableCell>
                      <TableCell className="text-right">
                        <TenantActionButtons
                          tenant={tenant}
                          onStatusChange={handleStatusChange}
                          onDelete={handleDelete}
                        />
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center py-8">
                      <div className="flex flex-col items-center space-y-2">
                        <Building2 className="h-8 w-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                          Nenhum tenant encontrado
                        </p>
                        {search || statusFilter || planFilter ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setSearch('');
                              setStatusFilter('');
                              setPlanFilter('');
                            }}
                          >
                            Limpar filtros
                          </Button>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>

          {/* Pagination */}
          <div className="mt-4">
            {renderPagination()}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}