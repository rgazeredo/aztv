import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';

export interface AdminStats {
  totalTenants: number;
  activeTenants: number;
  playersOnline: number;
  totalPlayers: number;
  storageUsed: number;
  storageLimit: number;
  apkDownloads: number;
  mediaFiles: number;
  recentActivityCount: number;
}

export interface UsageData {
  name: string;
  value: number;
  date: string;
}

export interface TenantData {
  id: number;
  name: string;
  email: string;
  status: 'active' | 'suspended' | 'cancelled';
  subscription_plan: string;
  created_at: string;
  last_login: string | null;
  players_count: number;
  storage_used: number;
}

export interface AdminDashboardData {
  stats: AdminStats;
  usageCharts: {
    tenantsOverTime: UsageData[];
    playersOverTime: UsageData[];
    storageOverTime: UsageData[];
    downloadsOverTime: UsageData[];
  };
  recentTenants: TenantData[];
}

export function useAdminData(period: string = '7d') {
  const [data, setData] = useState<AdminDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      setLoading(true);
      setError(null);

      // Use Inertia to fetch data
      router.get(`/admin/dashboard/data?period=${period}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['dashboardData'],
        onSuccess: (page: any) => {
          setData(page.props.dashboardData);
        },
        onError: (errors: any) => {
          setError('Falha ao carregar dados do dashboard');
          console.error('Admin data fetch error:', errors);
        },
        onFinish: () => {
          setLoading(false);
        }
      });
    } catch (err) {
      setError('Erro inesperado ao carregar dados');
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [period]);

  // Auto-refresh every 30 seconds
  useEffect(() => {
    const interval = setInterval(() => {
      if (!loading) {
        fetchData();
      }
    }, 30000);

    return () => clearInterval(interval);
  }, [loading, period]);

  const refresh = () => {
    fetchData();
  };

  return {
    data,
    loading,
    error,
    refresh
  };
}

export function useTenantList(
  filters: {
    search?: string;
    status?: string;
    page?: number;
    perPage?: number;
  } = {}
) {
  const [tenants, setTenants] = useState<TenantData[]>([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchTenants = async () => {
    try {
      setLoading(true);
      setError(null);

      const params = new URLSearchParams();
      if (filters.search) params.append('search', filters.search);
      if (filters.status) params.append('status', filters.status);
      if (filters.page) params.append('page', filters.page.toString());
      if (filters.perPage) params.append('per_page', filters.perPage.toString());

      router.get(`/admin/tenants?${params.toString()}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['tenants', 'pagination'],
        onSuccess: (page: any) => {
          setTenants(page.props.tenants || []);
          setPagination(page.props.pagination || pagination);
        },
        onError: (errors: any) => {
          setError('Falha ao carregar lista de tenants');
          console.error('Tenant list fetch error:', errors);
        },
        onFinish: () => {
          setLoading(false);
        }
      });
    } catch (err) {
      setError('Erro inesperado ao carregar tenants');
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTenants();
  }, [filters.search, filters.status, filters.page, filters.perPage]);

  const refresh = () => {
    fetchTenants();
  };

  return {
    tenants,
    pagination,
    loading,
    error,
    refresh
  };
}