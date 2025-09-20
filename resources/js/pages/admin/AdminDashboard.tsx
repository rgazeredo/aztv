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
import { StatCard } from '@/components/StatCard';
import { UsageChart } from '@/components/UsageChart';
import { useAdminData } from '@/hooks/useAdminData';
import {
  Users,
  Monitor,
  HardDrive,
  Download,
  Building2,
  Activity,
  Files,
  RefreshCw,
  TrendingUp,
} from 'lucide-react';

const periodOptions = [
  { value: '7d', label: 'Últimos 7 dias' },
  { value: '30d', label: 'Últimos 30 dias' },
  { value: '90d', label: 'Últimos 3 meses' },
];

export default function AdminDashboard() {
  const [period, setPeriod] = useState('7d');
  const { data, loading, error, refresh } = useAdminData(period);

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatPercentage = (used: number, total: number) => {
    if (total === 0) return 0;
    return Math.round((used / total) * 100);
  };

  if (error) {
    return (
      <div className="container mx-auto py-8">
        <Head title="Dashboard Administrativo" />
        <div className="text-center">
          <p className="text-red-600 mb-4">{error}</p>
          <Button onClick={refresh}>Tentar novamente</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto py-8 space-y-8">
      <Head title="Dashboard Administrativo" />

      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Dashboard Administrativo</h1>
          <p className="text-muted-foreground">
            Visão geral da plataforma AZ TV
          </p>
        </div>
        <div className="flex items-center space-x-4">
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

      {/* Statistics Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Total de Tenants"
          value={data?.stats.totalTenants || 0}
          description={`${data?.stats.activeTenants || 0} ativos`}
          icon={Building2}
          loading={loading}
          trend={{
            value: 12.5,
            label: 'vs. período anterior',
            isPositive: true,
          }}
        />
        <StatCard
          title="Players Online"
          value={data?.stats.playersOnline || 0}
          description={`de ${data?.stats.totalPlayers || 0} players`}
          icon={Monitor}
          loading={loading}
          trend={{
            value: 8.2,
            label: 'vs. período anterior',
            isPositive: true,
          }}
        />
        <StatCard
          title="Armazenamento"
          value={data ? formatBytes(data.stats.storageUsed) : '0 B'}
          description={data ? `${formatPercentage(data.stats.storageUsed, data.stats.storageLimit)}% utilizado` : '0% utilizado'}
          icon={HardDrive}
          loading={loading}
          trend={{
            value: 5.7,
            label: 'vs. período anterior',
            isPositive: false,
          }}
        />
        <StatCard
          title="Downloads APK"
          value={data?.stats.apkDownloads || 0}
          description="no período"
          icon={Download}
          loading={loading}
          trend={{
            value: 23.1,
            label: 'vs. período anterior',
            isPositive: true,
          }}
        />
      </div>

      {/* Secondary Stats */}
      <div className="grid gap-4 md:grid-cols-3">
        <StatCard
          title="Arquivos de Mídia"
          value={data?.stats.mediaFiles || 0}
          description="total na plataforma"
          icon={Files}
          loading={loading}
        />
        <StatCard
          title="Atividade Recente"
          value={data?.stats.recentActivityCount || 0}
          description="eventos nas últimas 24h"
          icon={Activity}
          loading={loading}
        />
        <StatCard
          title="Taxa de Crescimento"
          value="12.5%"
          description="novos tenants"
          icon={TrendingUp}
          loading={loading}
        />
      </div>

      {/* Charts */}
      <div className="grid gap-4 md:grid-cols-2">
        <UsageChart
          title="Tenants ao Longo do Tempo"
          description="Crescimento de tenants na plataforma"
          data={data?.usageCharts.tenantsOverTime || []}
          type="area"
          loading={loading}
          formatValue={(value) => value.toString()}
        />
        <UsageChart
          title="Players Online"
          description="Número de players conectados"
          data={data?.usageCharts.playersOverTime || []}
          type="line"
          loading={loading}
          color="hsl(var(--chart-2))"
          formatValue={(value) => value.toString()}
        />
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <UsageChart
          title="Uso de Armazenamento"
          description="Crescimento do uso de storage"
          data={data?.usageCharts.storageOverTime || []}
          type="area"
          loading={loading}
          color="hsl(var(--chart-3))"
          formatValue={formatBytes}
        />
        <UsageChart
          title="Downloads de APK"
          description="Downloads realizados por período"
          data={data?.usageCharts.downloadsOverTime || []}
          type="bar"
          loading={loading}
          color="hsl(var(--chart-4))"
          formatValue={(value) => value.toString()}
        />
      </div>

      {/* Recent Tenants */}
      <Card>
        <CardHeader>
          <CardTitle>Tenants Recentes</CardTitle>
          <CardDescription>
            Últimos tenants cadastrados na plataforma
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="space-y-3">
              {[...Array(5)].map((_, i) => (
                <div key={i} className="flex items-center space-x-4">
                  <div className="h-10 w-10 bg-muted animate-pulse rounded-full" />
                  <div className="space-y-2 flex-1">
                    <div className="h-4 w-32 bg-muted animate-pulse rounded" />
                    <div className="h-3 w-48 bg-muted animate-pulse rounded" />
                  </div>
                  <div className="h-4 w-16 bg-muted animate-pulse rounded" />
                </div>
              ))}
            </div>
          ) : (
            <div className="space-y-4">
              {data?.recentTenants.map((tenant) => (
                <div key={tenant.id} className="flex items-center space-x-4">
                  <div className="h-10 w-10 bg-primary/10 rounded-full flex items-center justify-center">
                    <Building2 className="h-5 w-5 text-primary" />
                  </div>
                  <div className="flex-1 space-y-1">
                    <p className="text-sm font-medium leading-none">
                      {tenant.name}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {tenant.email} • {tenant.subscription_plan}
                    </p>
                  </div>
                  <div className="text-xs text-muted-foreground">
                    {new Date(tenant.created_at).toLocaleDateString('pt-BR')}
                  </div>
                </div>
              ))}
              {(!data?.recentTenants || data.recentTenants.length === 0) && (
                <p className="text-sm text-muted-foreground text-center py-4">
                  Nenhum tenant recente encontrado
                </p>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}