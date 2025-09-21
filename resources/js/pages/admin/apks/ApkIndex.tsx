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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { ApkVersionBadge } from '@/components/ApkVersionBadge';
import { QrCodeModal } from '@/components/QrCodeModal';
import { useApkList, useTenantList } from '@/hooks/useApkData';
import { useToast } from '@/hooks/use-toast';
import {
  Search,
  Filter,
  Plus,
  RefreshCw,
  Smartphone,
  Download,
  BarChart3,
  QrCode,
  Edit,
  Trash2,
  MoreHorizontal,
  ExternalLink,
} from 'lucide-react';
import { router } from '@inertiajs/react';
import { useDebounce } from '@/hooks/useDebounce';

const statusOptions = [
  { value: '', label: 'Todos os status' },
  { value: 'active', label: 'Ativa' },
  { value: 'testing', label: 'Em Teste' },
  { value: 'inactive', label: 'Inativa' },
  { value: 'deprecated', label: 'Descontinuada' },
];

const perPageOptions = [
  { value: 10, label: '10 por página' },
  { value: 25, label: '25 por página' },
  { value: 50, label: '50 por página' },
  { value: 100, label: '100 por página' },
];

export default function ApkIndex() {
  const [search, setSearch] = useState('');
  const [selectedTenant, setSelectedTenant] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [selectedApk, setSelectedApk] = useState<any>(null);
  const [showQrModal, setShowQrModal] = useState(false);
  const [deleteApkId, setDeleteApkId] = useState<number | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const debouncedSearch = useDebounce(search, 500);
  const { toast } = useToast();

  const filters = useMemo(() => ({
    search: debouncedSearch,
    tenant: selectedTenant,
    status: statusFilter,
    page: currentPage,
    perPage: perPage,
  }), [debouncedSearch, selectedTenant, statusFilter, currentPage, perPage]);

  const { apks, pagination, loading, error, refresh } = useApkList(filters);
  const { tenants } = useTenantList();

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('pt-BR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const handleShowQr = (apk: any) => {
    setSelectedApk(apk);
    setShowQrModal(true);
  };

  const handleDownload = (apk: any) => {
    window.open(apk.download_url, '_blank');
  };

  const handleEdit = (apk: any) => {
    router.visit(`/admin/apks/${apk.id}/edit`);
  };

  const handleDelete = async () => {
    if (!deleteApkId) return;

    setIsDeleting(true);
    try {
      router.delete(`/admin/apks/${deleteApkId}`, {
        onSuccess: () => {
          toast({
            title: 'APK excluído',
            description: 'O APK foi excluído com sucesso.',
          });
          refresh();
        },
        onError: () => {
          toast({
            title: 'Erro',
            description: 'Falha ao excluir APK.',
            variant: 'destructive',
          });
        },
        onFinish: () => {
          setIsDeleting(false);
          setDeleteApkId(null);
        }
      });
    } catch (error) {
      setIsDeleting(false);
      setDeleteApkId(null);
    }
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const handleUpload = () => {
    router.visit('/admin/apks/upload');
  };

  const handleViewStats = () => {
    router.visit('/admin/apks/statistics');
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
        <Head title="Gerenciamento de APKs" />
        <div className="text-center">
          <p className="text-red-600 mb-4">{error}</p>
          <Button onClick={refresh}>Tentar novamente</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto py-8 space-y-6">
      <Head title="Gerenciamento de APKs" />

      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">APKs</h1>
          <p className="text-muted-foreground">
            Gerencie versões de APK da plataforma AZ TV
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={handleViewStats}
          >
            <BarChart3 className="h-4 w-4 mr-2" />
            Estatísticas
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={refresh}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Atualizar
          </Button>
          <Button onClick={handleUpload}>
            <Plus className="h-4 w-4 mr-2" />
            Upload APK
          </Button>
        </div>
      </div>

      {/* Stats Summary */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total de APKs</CardTitle>
            <Smartphone className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{pagination?.total || 0}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Versões Ativas</CardTitle>
            <Smartphone className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {apks?.filter(a => a.status === 'active').length || 0}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Downloads Totais</CardTitle>
            <Download className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {apks?.reduce((sum, apk) => sum + (apk.download_count || 0), 0) || 0}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Tamanho Total</CardTitle>
            <Smartphone className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {formatBytes(apks?.reduce((sum, apk) => sum + (apk.file_size || 0), 0) || 0)}
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
                  placeholder="Buscar por versão ou nome do arquivo..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="pl-8"
                />
              </div>
            </div>
            <Select value={selectedTenant} onValueChange={setSelectedTenant}>
              <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="Todos os tenants" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">Todos os tenants</SelectItem>
                {tenants.map((tenant) => (
                  <SelectItem key={tenant.id} value={tenant.id.toString()}>
                    {tenant.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
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
          <CardTitle>Lista de APKs</CardTitle>
          <CardDescription>
            Todas as versões de APK disponíveis na plataforma
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Versão</TableHead>
                  <TableHead>Tenant</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Tamanho</TableHead>
                  <TableHead>Downloads</TableHead>
                  <TableHead>Criado em</TableHead>
                  <TableHead className="text-right">Ações</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  [...Array(perPage)].map((_, i) => (
                    <TableRow key={i}>
                      {[...Array(7)].map((_, j) => (
                        <TableCell key={j}>
                          <div className="h-4 bg-muted animate-pulse rounded" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : apks && apks.length > 0 ? (
                  apks.map((apk) => (
                    <TableRow key={apk.id}>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="font-mono font-medium">v{apk.version}</span>
                          <span className="text-sm text-muted-foreground">{apk.file_name}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <span className="font-medium">{apk.tenant_name}</span>
                      </TableCell>
                      <TableCell>
                        <ApkVersionBadge status={apk.status} />
                      </TableCell>
                      <TableCell>{formatBytes(apk.file_size)}</TableCell>
                      <TableCell>
                        <span className="font-medium">{apk.download_count}</span>
                      </TableCell>
                      <TableCell>{formatDate(apk.created_at)}</TableCell>
                      <TableCell className="text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                              <span className="sr-only">Abrir menu</span>
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => handleShowQr(apk)}>
                              <QrCode className="mr-2 h-4 w-4" />
                              Ver QR Code
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => handleDownload(apk)}>
                              <Download className="mr-2 h-4 w-4" />
                              Baixar APK
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={() => handleEdit(apk)}>
                              <Edit className="mr-2 h-4 w-4" />
                              Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => setDeleteApkId(apk.id)}
                              className="text-red-600 focus:text-red-600"
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Excluir
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-8">
                      <div className="flex flex-col items-center space-y-2">
                        <Smartphone className="h-8 w-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                          Nenhum APK encontrado
                        </p>
                        {search || selectedTenant || statusFilter ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setSearch('');
                              setSelectedTenant('');
                              setStatusFilter('');
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

      {/* QR Code Modal */}
      {selectedApk && (
        <QrCodeModal
          isOpen={showQrModal}
          onClose={() => setShowQrModal(false)}
          apkVersion={selectedApk}
        />
      )}

      {/* Delete Confirmation */}
      <AlertDialog open={!!deleteApkId} onOpenChange={() => setDeleteApkId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Confirmar exclusão</AlertDialogTitle>
            <AlertDialogDescription>
              Esta ação não pode ser desfeita. Isso excluirá permanentemente
              o APK e todos os dados associados.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeleting}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={isDeleting}
              className="bg-red-600 hover:bg-red-700"
            >
              {isDeleting ? 'Excluindo...' : 'Excluir'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}