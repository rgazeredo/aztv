import React from 'react';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, Clock, AlertCircle, XCircle } from 'lucide-react';

interface ApkVersionBadgeProps {
  status: 'active' | 'inactive' | 'testing' | 'deprecated';
  showIcon?: boolean;
  version?: string;
}

export function ApkVersionBadge({ status, showIcon = false, version }: ApkVersionBadgeProps) {
  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'active':
        return {
          label: 'Ativa',
          variant: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' as const,
          icon: CheckCircle,
        };
      case 'testing':
        return {
          label: 'Em Teste',
          variant: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' as const,
          icon: Clock,
        };
      case 'inactive':
        return {
          label: 'Inativa',
          variant: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300' as const,
          icon: AlertCircle,
        };
      case 'deprecated':
        return {
          label: 'Descontinuada',
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
    <div className="flex items-center space-x-2">
      {version && (
        <Badge variant="outline" className="font-mono text-xs">
          v{version}
        </Badge>
      )}
      <Badge className={config.variant}>
        {showIcon && <Icon className="w-3 h-3 mr-1" />}
        {config.label}
      </Badge>
    </div>
  );
}