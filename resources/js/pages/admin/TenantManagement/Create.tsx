import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { TenantForm, TenantFormData } from '@/components/TenantForm';
import { useToast } from '@/hooks/use-toast';
import { router } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';

interface TenantCreatePageProps {
  errors?: Record<string, string>;
}

export default function TenantCreate({ errors }: TenantCreatePageProps) {
  const [isLoading, setIsLoading] = useState(false);
  const { toast } = useToast();

  const handleSubmit = (data: TenantFormData) => {
    setIsLoading(true);

    router.post('/admin/tenants', data, {
      onSuccess: () => {
        toast({
          title: 'Tenant criado com sucesso',
          description: `O tenant "${data.name}" foi criado e está ativo.`,
        });
      },
      onError: (errors) => {
        toast({
          title: 'Erro ao criar tenant',
          description: 'Verifique os dados informados e tente novamente.',
          variant: 'destructive',
        });
        console.error('Tenant creation errors:', errors);
      },
      onFinish: () => {
        setIsLoading(false);
      }
    });
  };

  const handleGoBack = () => {
    router.visit('/admin/tenants');
  };

  return (
    <div className="container mx-auto py-8 max-w-4xl">
      <Head title="Criar Tenant" />

      {/* Header */}
      <div className="flex items-center space-x-4 mb-8">
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
            Criar Novo Tenant
          </h1>
          <p className="text-muted-foreground">
            Adicione um novo cliente à plataforma AZ TV
          </p>
        </div>
      </div>

      {/* Info Card */}
      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="text-lg">Informações Importantes</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <h4 className="font-medium text-foreground mb-2">Planos Disponíveis:</h4>
              <ul className="space-y-1 text-muted-foreground">
                <li>• <strong>Básico:</strong> 1GB storage, 10 players</li>
                <li>• <strong>Profissional:</strong> 5GB storage, 50 players</li>
                <li>• <strong>Enterprise:</strong> 20GB storage, players ilimitados</li>
              </ul>
            </div>
            <div>
              <h4 className="font-medium text-foreground mb-2">Configurações Automáticas:</h4>
              <ul className="space-y-1 text-muted-foreground">
                <li>• Tenant será criado com status ativo</li>
                <li>• Slug será gerado automaticamente do nome</li>
                <li>• Configurações padrão aplicadas</li>
                <li>• Email de boas-vindas será enviado</li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Form */}
      <Card>
        <CardHeader>
          <CardTitle>Dados do Tenant</CardTitle>
          <CardDescription>
            Preencha as informações básicas para criar o novo tenant
          </CardDescription>
        </CardHeader>
        <CardContent>
          <TenantForm
            onSubmit={handleSubmit}
            isLoading={isLoading}
            submitLabel="Criar Tenant"
            mode="create"
          />
        </CardContent>
      </Card>

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