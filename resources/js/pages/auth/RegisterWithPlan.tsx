import { zodResolver } from '@hookform/resolvers/zod';
import { Head, useForm } from '@inertiajs/react';
import { IconArrowLeft, IconEye, IconEyeOff, IconLoader2 } from '@tabler/icons-react';
import { useState } from 'react';
import { useForm as useHookForm } from 'react-hook-form';
import * as z from 'zod';
import PlanSummary from '../../components/PlanSummary';
import { Button } from '../../components/ui/button';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '../../components/ui/form';
import { Input } from '../../components/ui/input';

interface Plan {
    id: string;
    name: string;
    description: string;
    price: number;
    currency: string;
    interval: string;
    features: string[];
}

interface RegisterWithPlanProps {
    plan: Plan;
}

const registerSchema = z
    .object({
        // Dados pessoais
        name: z.string().min(1, 'Nome é obrigatório').min(2, 'Nome deve ter pelo menos 2 caracteres'),
        email: z.string().email('E-mail inválido'),
        password: z.string().min(8, 'Senha deve ter pelo menos 8 caracteres'),
        password_confirmation: z.string(),

        // Dados da empresa (opcionais)
        company_name: z.string().optional(),
        document: z.string().optional(),
        phone: z.string().optional(),

        // Endereço (opcional)
        address: z
            .object({
                cep: z.string().optional(),
                street: z.string().optional(),
                number: z.string().optional(),
                complement: z.string().optional(),
                neighborhood: z.string().optional(),
                city: z.string().optional(),
                state: z.string().optional(),
            })
            .optional(),
    })
    .refine((data) => data.password === data.password_confirmation, {
        message: 'Senhas não coincidem',
        path: ['password_confirmation'],
    });

type RegisterFormValues = z.infer<typeof registerSchema>;

export default function RegisterWithPlan({ plan }: RegisterWithPlanProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { post } = useForm();

    const form = useHookForm<RegisterFormValues>({
        resolver: zodResolver(registerSchema),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            company_name: '',
            document: '',
            phone: '',
            address: {
                cep: '',
                street: '',
                number: '',
                complement: '',
                neighborhood: '',
                city: '',
                state: '',
            },
        },
    });

    const onSubmit = async (data: RegisterFormValues) => {
        setIsSubmitting(true);

        try {
            const response = await fetch('/register-with-plan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    ...data,
                    plan_id: plan.id,
                }),
            });

            const result = await response.json();

            if (response.ok && result.checkout_url) {
                // Redirecionar para o Stripe Checkout
                window.location.href = result.checkout_url;
            } else {
                // Tratar erros de validação
                if (result.errors) {
                    Object.keys(result.errors).forEach((field) => {
                        form.setError(field as any, {
                            type: 'server',
                            message: result.errors[field][0],
                        });
                    });
                } else {
                    throw new Error(result.error || 'Erro interno do servidor');
                }
            }
        } catch (error: any) {
            console.error('Erro ao enviar formulário:', error);
            alert('Erro ao processar cadastro. Tente novamente.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const maskCEP = (value: string) => {
        return value
            .replace(/\D/g, '')
            .replace(/(\d{5})(\d)/, '$1-$2')
            .substr(0, 9);
    };

    const maskPhone = (value: string) => {
        return value
            .replace(/\D/g, '')
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4,5})(\d{4})/, '$1-$2')
            .substr(0, 15);
    };

    const maskDocument = (value: string) => {
        const numbers = value.replace(/\D/g, '');
        if (numbers.length <= 11) {
            // CPF
            return numbers
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})/, '$1-$2');
        } else {
            // CNPJ
            return numbers
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2')
                .substr(0, 18);
        }
    };

    return (
        <>
            <Head title="Criar Conta - AZTV" />

            <div className="min-h-screen bg-gray-50">
                <div className="container mx-auto px-4 py-8">
                    <div className="mb-8">
                        <Button variant="ghost" onClick={() => window.history.back()} className="mb-4">
                            <IconArrowLeft className="mr-2 h-4 w-4" />
                            Voltar
                        </Button>

                        <h1 className="text-3xl font-bold text-gray-900">Criar sua conta</h1>
                        <p className="text-gray-600">Preencha os dados abaixo para finalizar seu cadastro</p>
                    </div>

                    <div className="grid gap-8 lg:grid-cols-3">
                        {/* Formulário */}
                        <div className="lg:col-span-2">
                            <div className="rounded-lg bg-white p-6 shadow-sm">
                                <Form {...form}>
                                    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-8">
                                        {/* Dados Pessoais */}
                                        <div>
                                            <h2 className="mb-4 text-lg font-semibold text-gray-900">Dados Pessoais</h2>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <FormField
                                                    control={form.control}
                                                    name="name"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Nome completo</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Seu nome completo" {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="email"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>E-mail</FormLabel>
                                                            <FormControl>
                                                                <Input type="email" placeholder="seu@email.com" {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="password"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Senha</FormLabel>
                                                            <FormControl>
                                                                <div className="relative">
                                                                    <Input
                                                                        type={showPassword ? 'text' : 'password'}
                                                                        placeholder="Sua senha"
                                                                        {...field}
                                                                    />
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="absolute top-0 right-0 h-full px-3 py-2 hover:bg-transparent"
                                                                        onClick={() => setShowPassword(!showPassword)}
                                                                    >
                                                                        {showPassword ? (
                                                                            <IconEyeOff className="h-4 w-4" />
                                                                        ) : (
                                                                            <IconEye className="h-4 w-4" />
                                                                        )}
                                                                    </Button>
                                                                </div>
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="password_confirmation"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Confirmar senha</FormLabel>
                                                            <FormControl>
                                                                <div className="relative">
                                                                    <Input
                                                                        type={showConfirmPassword ? 'text' : 'password'}
                                                                        placeholder="Confirme sua senha"
                                                                        {...field}
                                                                    />
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="absolute top-0 right-0 h-full px-3 py-2 hover:bg-transparent"
                                                                        onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                                                    >
                                                                        {showConfirmPassword ? (
                                                                            <IconEyeOff className="h-4 w-4" />
                                                                        ) : (
                                                                            <IconEye className="h-4 w-4" />
                                                                        )}
                                                                    </Button>
                                                                </div>
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />
                                            </div>
                                        </div>

                                        {/* Dados da Empresa */}
                                        <div>
                                            <h2 className="mb-4 text-lg font-semibold text-gray-900">Dados da Empresa</h2>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <FormField
                                                    control={form.control}
                                                    name="company_name"
                                                    render={({ field }) => (
                                                        <FormItem className="md:col-span-2">
                                                            <FormLabel>Nome da empresa (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Nome da sua empresa" {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="document"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>CNPJ/CPF (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input
                                                                    placeholder="00.000.000/0000-00"
                                                                    {...field}
                                                                    onChange={(e) => {
                                                                        const masked = maskDocument(e.target.value);
                                                                        field.onChange(masked);
                                                                    }}
                                                                />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="phone"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Telefone (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input
                                                                    placeholder="(11) 99999-9999"
                                                                    {...field}
                                                                    onChange={(e) => {
                                                                        const masked = maskPhone(e.target.value);
                                                                        field.onChange(masked);
                                                                    }}
                                                                />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />
                                            </div>
                                        </div>

                                        {/* Endereço */}
                                        <div>
                                            <h2 className="mb-4 text-lg font-semibold text-gray-900">Endereço</h2>
                                            <div className="grid gap-4 md:grid-cols-3">
                                                <FormField
                                                    control={form.control}
                                                    name="address.cep"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>CEP (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input
                                                                    placeholder="00000-000"
                                                                    {...field}
                                                                    onChange={(e) => {
                                                                        const masked = maskCEP(e.target.value);
                                                                        field.onChange(masked);
                                                                    }}
                                                                />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="address.street"
                                                    render={({ field }) => (
                                                        <FormItem className="md:col-span-2">
                                                            <FormLabel>Rua (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Nome da rua" {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="address.number"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Número (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="123" {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="address.complement"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Complemento</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Apto, sala..." {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="address.neighborhood"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Bairro (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Nome do bairro" {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="address.city"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Cidade (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="Nome da cidade" {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />

                                                <FormField
                                                    control={form.control}
                                                    name="address.state"
                                                    render={({ field }) => (
                                                        <FormItem>
                                                            <FormLabel>Estado (opcional)</FormLabel>
                                                            <FormControl>
                                                                <Input placeholder="SP" maxLength={2} {...field} />
                                                            </FormControl>
                                                            <FormMessage />
                                                        </FormItem>
                                                    )}
                                                />
                                            </div>
                                        </div>

                                        <div className="flex justify-end">
                                            <Button
                                                type="submit"
                                                disabled={isSubmitting}
                                                className="w-full bg-gray-900 hover:bg-gray-800 md:w-auto"
                                                size="lg"
                                            >
                                                {isSubmitting && <IconLoader2 className="mr-2 h-4 w-4 animate-spin" />}
                                                Finalizar Cadastro e Pagar
                                            </Button>
                                        </div>
                                    </form>
                                </Form>
                            </div>
                        </div>

                        {/* Sidebar com resumo do plano */}
                        <div className="lg:col-span-1">
                            <div className="sticky top-8">
                                <PlanSummary plan={plan} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
