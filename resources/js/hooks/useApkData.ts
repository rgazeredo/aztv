import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';

export interface ApkVersion {
  id: number;
  version: string;
  build_number: string;
  tenant_id: number;
  tenant_name: string;
  file_name: string;
  file_size: number;
  download_count: number;
  status: 'active' | 'inactive' | 'testing' | 'deprecated';
  created_at: string;
  updated_at: string;
  download_url: string;
  qr_code_url: string;
}

export interface ApkStats {
  totalDownloads: number;
  totalVersions: number;
  activeVersions: number;
  downloadsThisMonth: number;
  topVersion: {
    version: string;
    downloads: number;
  };
  topTenant: {
    name: string;
    downloads: number;
  };
}

export interface DownloadStats {
  name: string;
  downloads: number;
  date: string;
}

export interface ApkDashboardData {
  stats: ApkStats;
  downloadCharts: {
    downloadsOverTime: DownloadStats[];
    downloadsByTenant: DownloadStats[];
    downloadsByVersion: DownloadStats[];
  };
  recentDownloads: {
    id: number;
    version: string;
    tenant_name: string;
    player_name: string;
    downloaded_at: string;
    device_info: string;
  }[];
}

export function useApkList(
  filters: {
    search?: string;
    tenant?: string;
    status?: string;
    page?: number;
    perPage?: number;
  } = {}
) {
  const [apks, setApks] = useState<ApkVersion[]>([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchApks = async () => {
    try {
      setLoading(true);
      setError(null);

      const params = new URLSearchParams();
      if (filters.search) params.append('search', filters.search);
      if (filters.tenant) params.append('tenant', filters.tenant);
      if (filters.status) params.append('status', filters.status);
      if (filters.page) params.append('page', filters.page.toString());
      if (filters.perPage) params.append('per_page', filters.perPage.toString());

      router.get(`/admin/apks?${params.toString()}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['apks', 'pagination'],
        onSuccess: (page: any) => {
          setApks(page.props.apks || []);
          setPagination(page.props.pagination || pagination);
        },
        onError: (errors: any) => {
          setError('Falha ao carregar lista de APKs');
          console.error('APK list fetch error:', errors);
        },
        onFinish: () => {
          setLoading(false);
        }
      });
    } catch (err) {
      setError('Erro inesperado ao carregar APKs');
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApks();
  }, [filters.search, filters.tenant, filters.status, filters.page, filters.perPage]);

  const refresh = () => {
    fetchApks();
  };

  return {
    apks,
    pagination,
    loading,
    error,
    refresh
  };
}

export function useApkStats(period: string = '30d') {
  const [data, setData] = useState<ApkDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      setLoading(true);
      setError(null);

      router.get(`/admin/apks/statistics?period=${period}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['dashboardData'],
        onSuccess: (page: any) => {
          setData(page.props.dashboardData);
        },
        onError: (errors: any) => {
          setError('Falha ao carregar estatísticas');
          console.error('APK stats fetch error:', errors);
        },
        onFinish: () => {
          setLoading(false);
        }
      });
    } catch (err) {
      setError('Erro inesperado ao carregar estatísticas');
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [period]);

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

export function useTenantList() {
  const [tenants, setTenants] = useState<{ id: number; name: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchTenants = async () => {
      try {
        router.get('/admin/tenants/list', {}, {
          preserveState: true,
          preserveScroll: true,
          only: ['tenants'],
          onSuccess: (page: any) => {
            setTenants(page.props.tenants || []);
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

    fetchTenants();
  }, []);

  return {
    tenants,
    loading,
    error
  };
}