import React from 'react';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, XCircle, AlertCircle } from 'lucide-react';

interface TenantStatusBadgeProps {
  status: 'active' | 'suspended' | 'cancelled';
  showIcon?: boolean;
}

export function TenantStatusBadge({ status, showIcon = false }: TenantStatusBadgeProps) {
  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'active':
        return {
          label: 'Ativo',
          variant: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' as const,
          icon: CheckCircle,
        };
      case 'suspended':
        return {
          label: 'Suspenso',
          variant: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300' as const,
          icon: AlertCircle,
        };
      case 'cancelled':
        return {
          label: 'Cancelado',
          variant: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' as const,
          icon: XCircle,
        };
      default:
        return {
          label: status,
          variant: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300' as const,
          icon: AlertCircle,
        };
    }
  };

  const config = getStatusConfig(status);
  const Icon = config.icon;

  return (
    <Badge className={config.variant}>
      {showIcon && <Icon className="w-3 h-3 mr-1" />}
      {config.label}
    </Badge>
  );
}