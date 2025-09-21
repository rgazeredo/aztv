import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
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
import { ApkStatsCard } from '@/components/ApkStatsCard';
import { UsageChart } from '@/components/UsageChart';
import { ApkVersionBadge } from '@/components/ApkVersionBadge';
import { useApkStats, useTenantList } from '@/hooks/useApkData';
import {
  ArrowLeft,
  Download,
  Smartphone,
  TrendingUp,
  Users,
  Calendar,
  RefreshCw,
  BarChart3,
} from 'lucide-react';
import { router } from '@inertiajs/react';

const periodOptions = [
  { value: '7d', label: 'Últimos 7 dias' },
  { value: '30d', label: 'Últimos 30 dias' },
  { value: '90d', label: 'Últimos 3 meses' },
  { value: '1y', label: 'Último ano' },
];

export default function ApkStatistics() {
  const [period, setPeriod] = useState('30d');
  const [selectedTenant, setSelectedTenant] = useState('');

  const { data, loading, error, refresh } = useApkStats(period);
  const { tenants } = useTenantList();

  const handleGoBack = () => {
    router.visit('/admin/apks');
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

  const getDeviceIcon = (deviceInfo: string) => {
    // Simplified device detection
    if (deviceInfo.toLowerCase().includes('tv')) {
      return '📺';
    } else if (deviceInfo.toLowerCase().includes('tablet')) {
      return '📱';
    } else {
      return '📱';
    }
  };

  if (error) {
    return (
      <div className="container mx-auto py-8">
        <Head title="Estatísticas de APK" />
        <div className="text-center">
          <p className="text-red-600 mb-4">{error}</p>
          <Button onClick={refresh}>Tentar novamente</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto py-8 space-y-8">
      <Head title="Estatísticas de APK" />

      {/* Header */}
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-4">
          <Button
            variant="outline"
            size="sm"
            onClick={handleGoBack}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Voltar
          </Button>
          <div>
            <h1 className="text-3xl font-bold tracking-tight flex items-center">
              <BarChart3 className="h-8 w-8 mr-3" />
              Estatísticas de APK
            </h1>
            <p className="text-muted-foreground">
              Análise detalhada de downloads e uso dos APKs
            </p>
          </div>
        </div>
        <div className="flex items-center space-x-4">
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
          <Select value={period} onValueChange={setPeriod}>
            <SelectTrigger className="w-[180px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {periodOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button
            variant="outline"
            size="sm"
            onClick={refresh}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Atualizar
          </Button>
        </div>
      </div>

      {/* Main Statistics */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <ApkStatsCard
          title="Downloads Totais"
          value={data?.stats.totalDownloads || 0}
          description="no período selecionado"
          icon={Download}
          loading={loading}
          trend={{
            value: 15.2,
            label: 'vs. período anterior',
            isPositive: true,
          }}
        />
        <ApkStatsCard
          title="Versões Ativas"
          value={data?.stats.activeVersions || 0}
          description={`de ${data?.stats.totalVersions || 0} versões`}
          icon={Smartphone}
          loading={loading}
        />
        <ApkStatsCard
          title="Downloads Este Mês"
          value={data?.stats.downloadsThisMonth || 0}
          description="downloads em andamento"
          icon={TrendingUp}
          loading={loading}
          trend={{
            value: 8.7,
            label: 'vs. mês anterior',
            isPositive: true,
          }}
        />
        <ApkStatsCard
          title="Versão Mais Popular"
          value={data?.stats.topVersion?.version ? `v${data.stats.topVersion.version}` : '-'}
          description={data?.stats.topVersion?.downloads ? `${data.stats.topVersion.downloads} downloads` : 'sem dados'}
          icon={Users}
          loading={loading}
        />
      </div>

      {/* Charts */}
      <div className="grid gap-4 md:grid-cols-2">
        <UsageChart
          title="Downloads ao Longo do Tempo"
          description="Evolução dos downloads no período"
          data={data?.downloadCharts.downloadsOverTime || []}
          type="area"
          dataKey="downloads"
          loading={loading}
          formatValue={(value) => value.toString()}
        />
        <UsageChart
          title="Downloads por Tenant"
          description="Distribuição de downloads por cliente"
          data={data?.downloadCharts.downloadsByTenant || []}
          type="bar"
          dataKey="downloads"
          loading={loading}
          color="hsl(var(--chart-2))"
          formatValue={(value) => value.toString()}
        />
      </div>

      <div className="grid gap-4 md:grid-cols-1">
        <UsageChart
          title="Downloads por Versão"
          description="Popularidade de cada versão do APK"
          data={data?.downloadCharts.downloadsByVersion || []}
          type="bar"
          dataKey="downloads"
          loading={loading}
          color="hsl(var(--chart-3))"
          formatValue={(value) => value.toString()}
          height={300}
        />
      </div>

      {/* Top Statistics */}
      <div className="grid gap-4 md:grid-cols-2">
        {/* Top Tenant */}
        <Card>
          <CardHeader>
            <CardTitle>Tenant com Mais Downloads</CardTitle>
            <CardDescription>
              Cliente que mais baixou APKs no período
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="space-y-3">
                <div className="h-4 bg-muted animate-pulse rounded w-3/4" />
                <div className="h-6 bg-muted animate-pulse rounded w-1/2" />
              </div>
            ) : data?.stats.topTenant ? (
              <div className="space-y-2">
                <p className="text-2xl font-bold">{data.stats.topTenant.name}</p>
                <p className="text-sm text-muted-foreground">
                  {data.stats.topTenant.downloads} downloads
                </p>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">Sem dados disponíveis</p>
            )}
          </CardContent>
        </Card>

        {/* Period Summary */}
        <Card>
          <CardHeader>
            <CardTitle>Resumo do Período</CardTitle>
            <CardDescription>
              Estatísticas gerais dos downloads
            </CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="space-y-3">
                {[...Array(3)].map((_, i) => (
                  <div key={i} className="flex justify-between">
                    <div className="h-4 bg-muted animate-pulse rounded w-1/3" />
                    <div className="h-4 bg-muted animate-pulse rounded w-1/4" />
                  </div>
                ))}
              </div>
            ) : (
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Total de downloads:</span>
                  <span className="font-medium">{data?.stats.totalDownloads || 0}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Versões ativas:</span>
                  <span className="font-medium">{data?.stats.activeVersions || 0}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Downloads médios/dia:</span>
                  <span className="font-medium">
                    {data?.stats.totalDownloads
                      ? Math.round(data.stats.totalDownloads / (period === '7d' ? 7 : period === '30d' ? 30 : period === '90d' ? 90 : 365))
                      : 0
                    }
                  </span>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Recent Downloads */}
      <Card>
        <CardHeader>
          <CardTitle>Downloads Recentes</CardTitle>
          <CardDescription>
            Últimos downloads realizados na plataforma
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Versão</TableHead>
                  <TableHead>Tenant</TableHead>
                  <TableHead>Player/Device</TableHead>
                  <TableHead>Data do Download</TableHead>
                  <TableHead>Dispositivo</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  [...Array(10)].map((_, i) => (
                    <TableRow key={i}>
                      {[...Array(5)].map((_, j) => (
                        <TableCell key={j}>
                          <div className="h-4 bg-muted animate-pulse rounded" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : data?.recentDownloads && data.recentDownloads.length > 0 ? (
                  data.recentDownloads.map((download) => (
                    <TableRow key={download.id}>
                      <TableCell>
                        <ApkVersionBadge
                          status="active"
                          version={download.version}
                        />
                      </TableCell>
                      <TableCell>
                        <span className="font-medium">{download.tenant_name}</span>
                      </TableCell>
                      <TableCell>
                        <span className="font-medium">{download.player_name}</span>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center space-x-2">
                          <Calendar className="h-4 w-4 text-muted-foreground" />
                          <span className="text-sm">{formatDate(download.downloaded_at)}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center space-x-2">
                          <span>{getDeviceIcon(download.device_info)}</span>
                          <span className="text-sm text-muted-foreground">
                            {download.device_info}
                          </span>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8">
                      <div className="flex flex-col items-center space-y-2">
                        <Download className="h-8 w-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                          Nenhum download recente encontrado
                        </p>
                      </div>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}