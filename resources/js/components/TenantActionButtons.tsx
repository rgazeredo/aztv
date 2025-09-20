import React, { useState } from 'react';
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
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import {
  Eye,
  Edit,
  UserX,
  UserCheck,
  Trash2,
  MoreHorizontal,
  Building,
  CreditCard,
  History,
} from 'lucide-react';
import { router } from '@inertiajs/react';

interface Tenant {
  id: number;
  name: string;
  email: string;
  slug: string;
  status: 'active' | 'suspended' | 'cancelled';
  subscription_plan: string;
}

interface TenantActionButtonsProps {
  tenant: Tenant;
  onStatusChange?: (tenantId: number, status: string) => void;
  onDelete?: (tenantId: number) => void;
  showCompact?: boolean;
}

export function TenantActionButtons({
  tenant,
  onStatusChange,
  onDelete,
  showCompact = false
}: TenantActionButtonsProps) {
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [showSuspendDialog, setShowSuspendDialog] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const { toast } = useToast();

  const handleView = () => {
    router.visit(`/admin/tenants/${tenant.id}`);
  };

  const handleEdit = () => {
    router.visit(`/admin/tenants/${tenant.id}/edit`);
  };

  const handleImpersonate = () => {
    router.post(`/admin/tenants/${tenant.id}/impersonate`, {}, {
      onSuccess: () => {
        toast({
          title: 'Impersonação iniciada',
          description: `Você está agora acessando como ${tenant.name}`,
        });
      },
      onError: () => {
        toast({
          title: 'Erro',
          description: 'Falha ao iniciar impersonação',
          variant: 'destructive',
        });
      }
    });
  };

  const handleManageSubscription = () => {
    router.visit(`/admin/tenants/${tenant.id}/subscription`);
  };

  const handleViewHistory = () => {
    router.visit(`/admin/tenants/${tenant.id}/history`);
  };

  const handleStatusChange = async (newStatus: string) => {
    setIsLoading(true);
    try {
      router.patch(`/admin/tenants/${tenant.id}`, {
        status: newStatus
      }, {
        onSuccess: () => {
          onStatusChange?.(tenant.id, newStatus);
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
          setIsLoading(false);
          setShowSuspendDialog(false);
        }
      });
    } catch (error) {
      setIsLoading(false);
      setShowSuspendDialog(false);
    }
  };

  const handleDelete = async () => {
    setIsLoading(true);
    try {
      router.delete(`/admin/tenants/${tenant.id}`, {
        onSuccess: () => {
          onDelete?.(tenant.id);
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
          setIsLoading(false);
          setShowDeleteDialog(false);
        }
      });
    } catch (error) {
      setIsLoading(false);
      setShowDeleteDialog(false);
    }
  };

  const getStatusAction = () => {
    if (tenant.status === 'active') {
      return {
        label: 'Suspender',
        icon: UserX,
        action: () => setShowSuspendDialog(true),
        variant: 'default' as const,
      };
    } else {
      return {
        label: 'Ativar',
        icon: UserCheck,
        action: () => handleStatusChange('active'),
        variant: 'default' as const,
      };
    }
  };

  const statusAction = getStatusAction();

  if (showCompact) {
    return (
      <div className="flex items-center space-x-1">
        <Button
          variant="ghost"
          size="sm"
          onClick={handleView}
          className="h-8 px-2"
        >
          <Eye className="h-4 w-4" />
        </Button>
        <Button
          variant="ghost"
          size="sm"
          onClick={handleEdit}
          className="h-8 px-2"
        >
          <Edit className="h-4 w-4" />
        </Button>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="h-8 px-2">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={statusAction.action} disabled={isLoading}>
              <statusAction.icon className="mr-2 h-4 w-4" />
              {statusAction.label}
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={() => setShowDeleteDialog(true)}
              className="text-red-600 focus:text-red-600"
              disabled={isLoading}
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Excluir
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    );
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" className="h-8 w-8 p-0">
            <span className="sr-only">Abrir menu</span>
            <MoreHorizontal className="h-4 w-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={handleView}>
            <Eye className="mr-2 h-4 w-4" />
            Visualizar
          </DropdownMenuItem>
          <DropdownMenuItem onClick={handleEdit}>
            <Edit className="mr-2 h-4 w-4" />
            Editar
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={handleImpersonate}>
            <Building className="mr-2 h-4 w-4" />
            Impersonar
          </DropdownMenuItem>
          <DropdownMenuItem onClick={handleManageSubscription}>
            <CreditCard className="mr-2 h-4 w-4" />
            Gerenciar Assinatura
          </DropdownMenuItem>
          <DropdownMenuItem onClick={handleViewHistory}>
            <History className="mr-2 h-4 w-4" />
            Ver Histórico
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={statusAction.action} disabled={isLoading}>
            <statusAction.icon className="mr-2 h-4 w-4" />
            {statusAction.label}
          </DropdownMenuItem>
          <DropdownMenuItem
            onClick={() => setShowDeleteDialog(true)}
            className="text-red-600 focus:text-red-600"
            disabled={isLoading}
          >
            <Trash2 className="mr-2 h-4 w-4" />
            Excluir
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={showSuspendDialog} onOpenChange={setShowSuspendDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Confirmar suspensão</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja suspender o tenant "{tenant.name}"?
              O tenant não conseguirá acessar a plataforma até ser reativado.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isLoading}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => handleStatusChange('suspended')}
              disabled={isLoading}
              className="bg-orange-600 hover:bg-orange-700"
            >
              {isLoading ? 'Suspendendo...' : 'Suspender'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
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
            <AlertDialogCancel disabled={isLoading}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={isLoading}
              className="bg-red-600 hover:bg-red-700"
            >
              {isLoading ? 'Excluindo...' : 'Excluir'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}