import React from 'react';
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { IconDevices } from '@tabler/icons-react';

interface PlayerStatusData {
    players: {
        total: number;
        online: number;
        offline: number;
        online_percentage: number;
    };
}

interface PlayerStatusChartProps {
    data: PlayerStatusData;
}

const PlayerStatusChart: React.FC<PlayerStatusChartProps> = ({ data }) => {
    const chartData = [
        {
            name: 'Online',
            value: data.players.online,
            color: '#10b981', // green-500
            percentage: data.players.online_percentage
        },
        {
            name: 'Offline',
            value: data.players.offline,
            color: '#ef4444', // red-500
            percentage: 100 - data.players.online_percentage
        }
    ];

    const CustomTooltip = ({ active, payload }: any) => {
        if (active && payload && payload.length) {
            const data = payload[0].payload;
            return (
                <div className="bg-white p-3 border rounded-lg shadow-lg">
                    <p className="font-medium" style={{ color: data.color }}>
                        {data.name}: {data.value} players
                    </p>
                    <p className="text-sm text-gray-600">
                        {data.percentage.toFixed(1)}% do total
                    </p>
                </div>
            );
        }
        return null;
    };

    const CustomLabel = ({ cx, cy, midAngle, innerRadius, outerRadius, percent }: any) => {
        if (percent < 0.05) return null; // Don't show label if less than 5%

        const RADIAN = Math.PI / 180;
        const radius = innerRadius + (outerRadius - innerRadius) * 0.5;
        const x = cx + radius * Math.cos(-midAngle * RADIAN);
        const y = cy + radius * Math.sin(-midAngle * RADIAN);

        return (
            <text
                x={x}
                y={y}
                fill="white"
                textAnchor={x > cx ? 'start' : 'end'}
                dominantBaseline="central"
                className="text-sm font-medium"
            >
                {`${(percent * 100).toFixed(0)}%`}
            </text>
        );
    };

    const renderCustomLegend = (props: any) => {
        return (
            <div className="flex justify-center space-x-6 mt-4">
                {props.payload.map((entry: any, index: number) => (
                    <div key={index} className="flex items-center space-x-2">
                        <div
                            className="w-3 h-3 rounded-full"
                            style={{ backgroundColor: entry.color }}
                        />
                        <span className="text-sm text-gray-600">
                            {entry.value}: {chartData[index].value}
                        </span>
                    </div>
                ))}
            </div>
        );
    };

    if (data.players.total === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconDevices className="h-5 w-5" />
                        Status dos Players
                    </CardTitle>
                    <CardDescription>
                        Distribuição de players online vs offline
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-center h-64 text-gray-500">
                        <div className="text-center">
                            <IconDevices className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                            <p className="text-lg font-medium">Nenhum player registrado</p>
                            <p className="text-sm">Adicione players para visualizar estatísticas</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconDevices className="h-5 w-5" />
                    Status dos Players
                </CardTitle>
                <CardDescription>
                    Distribuição de players online vs offline (total: {data.players.total})
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="h-64">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={chartData}
                                cx="50%"
                                cy="50%"
                                labelLine={false}
                                label={CustomLabel}
                                outerRadius={80}
                                fill="#8884d8"
                                dataKey="value"
                                animationBegin={0}
                                animationDuration={800}
                            >
                                {chartData.map((entry, index) => (
                                    <Cell key={`cell-${index}`} fill={entry.color} />
                                ))}
                            </Pie>
                            <Tooltip content={<CustomTooltip />} />
                            <Legend content={renderCustomLegend} />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                {/* Summary Statistics */}
                <div className="grid grid-cols-3 gap-4 mt-4 pt-4 border-t">
                    <div className="text-center">
                        <div className="text-2xl font-bold text-gray-900">
                            {data.players.total}
                        </div>
                        <div className="text-sm text-gray-600">Total</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-green-600">
                            {data.players.online}
                        </div>
                        <div className="text-sm text-gray-600">Online</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-red-600">
                            {data.players.offline}
                        </div>
                        <div className="text-sm text-gray-600">Offline</div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

export default PlayerStatusChart;