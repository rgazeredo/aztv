import { useEffect, useState } from 'react';

export interface PricingPlan {
    id: string;
    product_id: string;
    name: string;
    description: string;
    price: number;
    currency: string;
    interval: string;
    interval_count: number;
    type: 'basic' | 'professional' | 'enterprise';
    features: string[];
    popular: boolean;
    stripe_price_id: string;
    metadata: Record<string, any>;
}

export function usePricing() {
    const [plans, setPlans] = useState<PricingPlan[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const fetchPlans = async () => {
            try {
                setLoading(true);
                setError(null);

                const response = await fetch('/api/pricing/plans');

                if (!response.ok) {
                    throw new Error('Erro ao buscar planos');
                }

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                setPlans(data);
            } catch (err) {
                setError(err instanceof Error ? err.message : 'Erro desconhecido');
                console.error('Erro ao buscar planos:', err);

                // Fallback para dados estáticos em caso de erro
                setPlans(getStaticPlans());
            } finally {
                setLoading(false);
            }
        };

        fetchPlans();
    }, []);

    return { plans, loading, error };
}

// Dados estáticos como fallback
function getStaticPlans(): PricingPlan[] {
    return [
        {
            id: 'static-basic',
            product_id: 'static-basic',
            name: 'Básico',
            description: 'Ideal para pequenos negócios',
            price: 29.9,
            currency: 'BRL',
            interval: 'month',
            interval_count: 1,
            type: 'basic',
            features: ['1.000 mensagens/mês', 'Dashboard básico', 'Suporte por email'],
            popular: false,
            stripe_price_id: '',
            metadata: {},
        },
        {
            id: 'static-professional',
            product_id: 'static-professional',
            name: 'Profissional',
            description: 'Para empresas em crescimento',
            price: 59.9,
            currency: 'BRL',
            interval: 'month',
            interval_count: 1,
            type: 'professional',
            features: ['5.000 mensagens/mês', 'Dashboard avançado', 'API acesso completo', 'Suporte prioritário'],
            popular: true,
            stripe_price_id: '',
            metadata: {},
        },
        {
            id: 'static-enterprise',
            product_id: 'static-enterprise',
            name: 'Empresarial',
            description: 'Para grandes volumes',
            price: 149.9,
            currency: 'BRL',
            interval: 'month',
            interval_count: 1,
            type: 'enterprise',
            features: [
                'Conexões ilimitadas',
                '25.000 mensagens/mês',
                'Dashboard empresarial',
                'API completa + Webhooks',
                'Cursos exclusivos',
                'Suporte 24/7',
            ],
            popular: false,
            stripe_price_id: '',
            metadata: {},
        },
    ];
}
