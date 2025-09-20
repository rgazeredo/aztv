import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { TenantForm, TenantFormData } from '@/components/TenantForm';
import { TenantStatusBadge } from '@/components/TenantStatusBadge';
import { useToast } from '@/hooks/use-toast';
import { router } from '@inertiajs/react';
import {
  ArrowLeft,
  Building2,
  Calendar,
  Users,
  HardDrive,
  Activity,
  UserX,
  UserCheck,
  Trash2,
  Eye,
} from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  email: string;
  subscription_plan: string;
  status: 'active' | 'suspended' | 'cancelled';
  description?: string;
  created_at: string;
  updated_at: string;
  last_login?: string;
  players_count: number;
  storage_used: number;
  media_files_count: number;
}

interface TenantEditPageProps {
  tenant: Tenant;
  errors?: Record<string, string>;
}

export default function TenantEdit({ tenant, errors }: TenantEditPageProps) {
  const [isLoading, setIsLoading] = useState(false);
  const [isStatusChanging, setIsStatusChanging] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const { toast } = useToast();

  const handleSubmit = (data: TenantFormData) => {
    setIsLoading(true);

    router.patch(`/admin/tenants/${tenant.id}`, data, {
      onSuccess: () => {
        toast({
          title: 'Tenant atualizado com sucesso',
          description: `As informações do tenant "${data.name}" foram atualizadas.`,
        });
      },
      onError: (errors) => {
        toast({
          title: 'Erro ao atualizar tenant',
          description: 'Verifique os dados informados e tente novamente.',
          variant: 'destructive',
        });
        console.error('Tenant update errors:', errors);
      },
      onFinish: () => {
        setIsLoading(false);
      }
    });
  };

  const handleStatusChange = (newStatus: string) => {
    setIsStatusChanging(true);

    router.patch(`/admin/tenants/${tenant.id}`, { status: newStatus }, {
      onSuccess: () => {
        toast({
          title: 'Status atualizado',
          description: `Tenant ${newStatus === 'active' ? 'ativado' : 'suspenso'} com sucesso.`,
        });
      },
      onError: () => {
        toast({
          title: 'Erro',
          description: 'Falha ao atualizar status do tenant.',
          variant: 'destructive',
        });
      },
      onFinish: () => {
        setIsStatusChanging(false);
      }
    });
  };

  const handleDelete = () => {
    setIsDeleting(true);

    router.delete(`/admin/tenants/${tenant.id}`, {
      onSuccess: () => {
        toast({
          title: 'Tenant excluído',
          description: 'Tenant foi excluído permanentemente.',
        });
      },
      onError: () => {
        toast({
          title: 'Erro',
          description: 'Falha ao excluir tenant.',
          variant: 'destructive',
        });
      },
      onFinish: () => {
        setIsDeleting(false);
      }
    });
  };

  const handleGoBack = () => {
    router.visit('/admin/tenants');
  };

  const handleViewTenant = () => {
    router.visit(`/admin/tenants/${tenant.id}`);
  };

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('pt-BR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getPlanLabel = (plan: string) => {
    const labels = {
      basic: 'Básico',
      professional: 'Profissional',
      enterprise: 'Enterprise',
    };
    return labels[plan as keyof typeof labels] || plan;
  };

  return (
    <div className="container mx-auto py-8 max-w-6xl">
      <Head title={`Editar Tenant: ${tenant.name}`} />

      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div className="flex items-center space-x-4">
          <Button
            variant="outline"
            size="sm"
            onClick={handleGoBack}
            disabled={isLoading}
          >
            <ArrowLeft className="h-4 w-4 mr-2" />
            Voltar
          </Button>
          <div>
            <h1 className="text-3xl font-bold tracking-tight flex items-center">
              <Building2 className="h-8 w-8 mr-3" />
              Editar Tenant
            </h1>
            <p className="text-muted-foreground">
              Gerenciar informações de "{tenant.name}"
            </p>
          </div>
        </div>
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            onClick={handleViewTenant}
            disabled={isLoading}
          >
            <Eye className="h-4 w-4 mr-2" />
            Visualizar
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Tenant Information & Form */}
        <div className="lg:col-span-2 space-y-6">
          {/* Current Info */}
          <Card>
            <CardHeader>
              <CardTitle>Informações Atuais</CardTitle>
              <CardDescription>
                Status e dados básicos do tenant
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Status</p>
                  <TenantStatusBadge status={tenant.status} showIcon />
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Plano</p>
                  <Badge variant="outline">{getPlanLabel(tenant.subscription_plan)}</Badge>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Criado em</p>
                  <p className="text-sm">{formatDate(tenant.created_at)}</p>
                </div>
              </div>

              <Separator />

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Slug</p>
                  <p className="font-mono text-sm">{tenant.slug}</p>
                </div>
                {tenant.domain && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Domínio</p>
                    <p className="text-sm">{tenant.domain}</p>
                  </div>
                )}
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Última atualização</p>
                  <p className="text-sm">{formatDate(tenant.updated_at)}</p>
                </div>
                {tenant.last_login && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Último login</p>
                    <p className="text-sm">{formatDate(tenant.last_login)}</p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Edit Form */}
          <Card>
            <CardHeader>
              <CardTitle>Editar Informações</CardTitle>
              <CardDescription>
                Atualize os dados básicos do tenant
              </CardDescription>
            </CardHeader>
            <CardContent>
              <TenantForm
                initialData={{
                  name: tenant.name,
                  slug: tenant.slug,
                  domain: tenant.domain || '',
                  email: tenant.email,
                  subscription_plan: tenant.subscription_plan as any,
                  description: tenant.description || '',
                }}
                onSubmit={handleSubmit}
                isLoading={isLoading}
                submitLabel="Atualizar Tenant"
                mode="edit"
              />
            </CardContent>
          </Card>
        </div>

        {/* Sidebar - Stats & Actions */}
        <div className="space-y-6">
          {/* Stats */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Estatísticas</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Users className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm">Players</span>
                </div>
                <span className="font-medium">{tenant.players_count}</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <HardDrive className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm">Storage</span>
                </div>
                <span className="font-medium">{formatBytes(tenant.storage_used)}</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Activity className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm">Mídia</span>
                </div>
                <span className="font-medium">{tenant.media_files_count} arquivos</span>
              </div>
            </CardContent>
          </Card>

          {/* Actions */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Ações Administrativas</CardTitle>
              <CardDescription>
                Gerenciar status e configurações
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {tenant.status === 'active' ? (
                <AlertDialog>
                  <AlertDialogTrigger asChild>
                    <Button
                      variant="outline"
                      className="w-full"
                      disabled={isStatusChanging}
                    >
                      <UserX className="h-4 w-4 mr-2" />
                      Suspender Tenant
                    </Button>
                  </AlertDialogTrigger>
                  <AlertDialogContent>
                    <AlertDialogHeader>
                      <AlertDialogTitle>Confirmar suspensão</AlertDialogTitle>
                      <AlertDialogDescription>
                        Tem certeza que deseja suspender o tenant "{tenant.name}"?
                        O tenant não conseguirá acessar a plataforma até ser reativado.
                      </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                      <AlertDialogCancel>Cancelar</AlertDialogCancel>
                      <AlertDialogAction
                        onClick={() => handleStatusChange('suspended')}
                        className="bg-orange-600 hover:bg-orange-700"
                      >
                        Suspender
                      </AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>
              ) : (
                <Button
                  variant="outline"
                  className="w-full"
                  onClick={() => handleStatusChange('active')}
                  disabled={isStatusChanging}
                >
                  <UserCheck className="h-4 w-4 mr-2" />
                  Ativar Tenant
                </Button>
              )}

              <Separator />

              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <Button
                    variant="destructive"
                    className="w-full"
                    disabled={isDeleting}
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Excluir Tenant
                  </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>Confirmar exclusão</AlertDialogTitle>
                    <AlertDialogDescription>
                      Esta ação não pode ser desfeita. Isso excluirá permanentemente
                      o tenant "{tenant.name}" e todos os dados associados incluindo
                      players, mídia e configurações.
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction
                      onClick={handleDelete}
                      className="bg-red-600 hover:bg-red-700"
                    >
                      Excluir Permanentemente
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Server Errors Display */}
      {errors && Object.keys(errors).length > 0 && (
        <Card className="mt-6 border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950">
          <CardHeader>
            <CardTitle className="text-red-800 dark:text-red-200">
              Erros de Validação
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-1">
              {Object.entries(errors).map(([field, message]) => (
                <li key={field} className="text-sm text-red-700 dark:text-red-300">
                  <strong className="capitalize">{field}:</strong> {message}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}
    </div>
  );
}