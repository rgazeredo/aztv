import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Button } from '@/components/ui/button';
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

const tenantFormSchema = z.object({
  name: z.string().min(2, 'Nome deve ter pelo menos 2 caracteres'),
  slug: z.string()
    .min(2, 'Slug deve ter pelo menos 2 caracteres')
    .max(50, 'Slug deve ter no máximo 50 caracteres')
    .regex(/^[a-z0-9-]+$/, 'Slug deve conter apenas letras minúsculas, números e hífens'),
  domain: z.string()
    .optional()
    .refine((val) => !val || /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9](?:\.[a-zA-Z]{2,})+$/.test(val), {
      message: 'Domínio deve ser válido (ex: exemplo.com)'
    }),
  email: z.string().email('Email deve ser válido'),
  subscription_plan: z.enum(['basic', 'professional', 'enterprise'], {
    required_error: 'Selecione um plano de assinatura'
  }),
  description: z.string().optional(),
});

export type TenantFormData = z.infer<typeof tenantFormSchema>;

interface TenantFormProps {
  initialData?: Partial<TenantFormData>;
  onSubmit: (data: TenantFormData) => void;
  isLoading?: boolean;
  submitLabel?: string;
  mode?: 'create' | 'edit';
}

const subscriptionPlans = [
  { value: 'basic', label: 'Básico', description: '1GB storage, 10 players' },
  { value: 'professional', label: 'Profissional', description: '5GB storage, 50 players' },
  { value: 'enterprise', label: 'Enterprise', description: '20GB storage, ilimitado players' },
];

export function TenantForm({
  initialData,
  onSubmit,
  isLoading = false,
  submitLabel = 'Salvar',
  mode = 'create'
}: TenantFormProps) {
  const form = useForm<TenantFormData>({
    resolver: zodResolver(tenantFormSchema),
    defaultValues: {
      name: initialData?.name || '',
      slug: initialData?.slug || '',
      domain: initialData?.domain || '',
      email: initialData?.email || '',
      subscription_plan: initialData?.subscription_plan || 'basic',
      description: initialData?.description || '',
    },
  });

  // Auto-generate slug from name when creating
  React.useEffect(() => {
    if (mode === 'create') {
      const subscription = form.watch('name');
      if (subscription) {
        const slug = subscription
          .toLowerCase()
          .replace(/[^a-z0-9\s-]/g, '')
          .replace(/\s+/g, '-')
          .replace(/-+/g, '-')
          .trim();

        if (slug !== form.getValues('slug')) {
          form.setValue('slug', slug);
        }
      }
    }
  }, [form.watch('name'), mode, form]);

  const handleSubmit = (data: TenantFormData) => {
    onSubmit(data);
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
        {/* Basic Information */}
        <Card>
          <CardHeader>
            <CardTitle>Informações Básicas</CardTitle>
            <CardDescription>
              Informações gerais sobre o tenant
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nome *</FormLabel>
                    <FormControl>
                      <Input placeholder="Nome do tenant" {...field} />
                    </FormControl>
                    <FormDescription>
                      Nome público do tenant
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Email *</FormLabel>
                    <FormControl>
                      <Input type="email" placeholder="email@exemplo.com" {...field} />
                    </FormControl>
                    <FormDescription>
                      Email principal do tenant
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormField
                control={form.control}
                name="slug"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Slug *</FormLabel>
                    <FormControl>
                      <Input placeholder="tenant-slug" {...field} />
                    </FormControl>
                    <FormDescription>
                      Identificador único (apenas letras, números e hífens)
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="domain"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Domínio</FormLabel>
                    <FormControl>
                      <Input placeholder="exemplo.com" {...field} />
                    </FormControl>
                    <FormDescription>
                      Domínio personalizado (opcional)
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Descrição</FormLabel>
                  <FormControl>
                    <Textarea
                      placeholder="Descrição opcional do tenant..."
                      className="resize-none"
                      {...field}
                    />
                  </FormControl>
                  <FormDescription>
                    Descrição opcional sobre o tenant
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        {/* Subscription Plan */}
        <Card>
          <CardHeader>
            <CardTitle>Plano de Assinatura</CardTitle>
            <CardDescription>
              Selecione o plano de assinatura para este tenant
            </CardDescription>
          </CardHeader>
          <CardContent>
            <FormField
              control={form.control}
              name="subscription_plan"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Plano *</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione um plano" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {subscriptionPlans.map((plan) => (
                        <SelectItem key={plan.value} value={plan.value}>
                          <div className="flex flex-col">
                            <span className="font-medium">{plan.label}</span>
                            <span className="text-xs text-muted-foreground">
                              {plan.description}
                            </span>
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    Define os limites de storage e players
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </CardContent>
        </Card>

        <Separator />

        {/* Actions */}
        <div className="flex justify-end space-x-4">
          <Button
            type="button"
            variant="outline"
            onClick={() => window.history.back()}
            disabled={isLoading}
          >
            Cancelar
          </Button>
          <Button type="submit" disabled={isLoading}>
            {isLoading ? 'Salvando...' : submitLabel}
          </Button>
        </div>
      </form>
    </Form>
  );
}