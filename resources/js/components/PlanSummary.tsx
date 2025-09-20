import { IconCheck } from '@tabler/icons-react';

interface PlanSummaryProps {
    plan: {
        id: string;
        name: string;
        description: string;
        price: number;
        currency: string;
        interval: string;
        features: string[];
    };
}

export default function PlanSummary({ plan }: PlanSummaryProps) {
    const formatPrice = (price: number, currency: string) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: currency,
        }).format(price);
    };

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div className="mb-4 border-b border-gray-200 pb-4">
                <h3 className="text-lg font-semibold text-gray-900">Plano Selecionado</h3>
            </div>

            <div className="mb-4">
                <h4 className="text-xl font-bold text-gray-900">{plan.name}</h4>
                <p className="text-sm text-gray-600">{plan.description}</p>
            </div>

            <div className="mb-6">
                <div className="text-3xl font-bold text-gray-900">{formatPrice(plan.price, plan.currency)}</div>
                <div className="text-sm text-gray-600">por {plan.interval === 'month' ? 'mês' : 'ano'}</div>
            </div>

            <div>
                <h5 className="mb-3 text-sm font-medium text-gray-900">Recursos inclusos:</h5>
                <ul className="space-y-2">
                    {plan.features.map((feature, index) => (
                        <li key={index} className="flex items-start">
                            <IconCheck className="mt-0.5 mr-2 h-4 w-4 flex-shrink-0 text-green-500" />
                            <span className="text-sm text-gray-700">{feature}</span>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="mt-6 rounded-md bg-blue-50 p-3">
                <p className="text-xs text-blue-800">
                    <strong>Garantia:</strong> Você pode cancelar sua assinatura a qualquer momento. Sem taxas de cancelamento.
                </p>
            </div>
        </div>
    );
}
