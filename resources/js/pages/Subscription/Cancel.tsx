import { Head, Link } from '@inertiajs/react';
import { IconArrowLeft, IconRefresh, IconX } from '@tabler/icons-react';

interface CancelProps {
    tenant?: {
        name: string;
        plan_metadata?: {
            name: string;
            description: string;
            price: number;
            currency: string;
            interval: string;
            features: string[];
        };
    } | null;
    message: string;
}

export default function Cancel({ tenant, message }: CancelProps) {
    const formatPrice = (price: number, currency: string) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: currency,
        }).format(price);
    };

    return (
        <>
            <Head title="Pagamento Cancelado" />

            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-red-50 to-orange-50 p-4">
                <div className="w-full max-w-md">
                    {/* Cancel Icon */}
                    <div className="mb-8 text-center">
                        <div className="mb-4 inline-flex h-20 w-20 items-center justify-center rounded-full bg-red-100">
                            <IconX className="h-10 w-10 text-red-600" />
                        </div>
                        <h1 className="mb-2 text-3xl font-bold text-gray-900">Pagamento Cancelado</h1>
                        <p className="text-gray-600">O processo de pagamento foi interrompido</p>
                    </div>

                    {/* Cancel Card */}
                    <div className="mb-6 rounded-lg bg-white p-6 shadow-lg">
                        <div className="mb-6 text-center">
                            <p className="text-gray-700">{message}</p>
                        </div>

                        {tenant && tenant.plan_metadata && (
                            <div className="mb-6 border-t border-gray-200 pt-4">
                                <h3 className="mb-2 text-center font-medium text-gray-900">Plano Selecionado: {tenant.plan_metadata.name}</h3>

                                <div className="mb-4 rounded-md bg-gray-50 p-4">
                                    <div className="mb-3 text-center">
                                        <div className="text-2xl font-bold text-gray-900">
                                            {formatPrice(tenant.plan_metadata.price, tenant.plan_metadata.currency)}
                                        </div>
                                        <div className="text-sm text-gray-600">por {tenant.plan_metadata.interval === 'month' ? 'mês' : 'ano'}</div>
                                    </div>

                                    <p className="mb-3 text-center text-sm text-gray-600">{tenant.plan_metadata.description}</p>

                                    <div className="text-xs text-gray-600">
                                        <p className="mb-2 font-medium">Recursos inclusos:</p>
                                        <ul className="space-y-1">
                                            {tenant.plan_metadata.features.slice(0, 3).map((feature, index) => (
                                                <li key={index}>• {feature}</li>
                                            ))}
                                            {tenant.plan_metadata.features.length > 3 && (
                                                <li>• E mais {tenant.plan_metadata.features.length - 3} recursos...</li>
                                            )}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="space-y-3">
                            {tenant && (
                                <Link
                                    href={`/subscription/retry/${tenant.name}`}
                                    method="post"
                                    className="inline-flex w-full items-center justify-center rounded-md bg-blue-600 px-4 py-3 font-medium text-white transition-colors hover:bg-blue-700"
                                >
                                    <IconRefresh className="mr-2 h-4 w-4" />
                                    Tentar Novamente
                                </Link>
                            )}

                            <Link
                                href="/"
                                className="inline-flex w-full items-center justify-center rounded-md bg-gray-100 px-4 py-3 font-medium text-gray-700 transition-colors hover:bg-gray-200"
                            >
                                <IconArrowLeft className="mr-2 h-4 w-4" />
                                Voltar ao Início
                            </Link>
                        </div>
                    </div>

                    {/* Help Info */}
                    <div className="rounded-lg bg-orange-50 p-4 text-center">
                        <p className="text-sm text-orange-800">
                            <strong>Precisa de ajuda?</strong> Entre em contato conosco pelo email. Estamos aqui para auxiliá-lo com qualquer dúvida
                            sobre nossos planos.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
