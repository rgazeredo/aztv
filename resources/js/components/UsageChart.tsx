import React from 'react';
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface DataPoint {
  name: string;
  value: number;
  date?: string;
  [key: string]: any;
}

interface UsageChartProps {
  title: string;
  description?: string;
  data: DataPoint[];
  type?: 'line' | 'area' | 'bar';
  dataKey?: string;
  loading?: boolean;
  height?: number;
  color?: string;
  formatValue?: (value: number) => string;
}

export function UsageChart({
  title,
  description,
  data,
  type = 'area',
  dataKey = 'value',
  loading = false,
  height = 300,
  color = 'hsl(var(--primary))',
  formatValue = (value) => value.toString()
}: UsageChartProps) {
  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="h-5 w-32 bg-muted animate-pulse rounded" />
          </CardTitle>
          {description && (
            <CardDescription>
              <div className="h-4 w-48 bg-muted animate-pulse rounded" />
            </CardDescription>
          )}
        </CardHeader>
        <CardContent>
          <div
            className="w-full bg-muted animate-pulse rounded"
            style={{ height: `${height}px` }}
          />
        </CardContent>
      </Card>
    );
  }

  const formatTooltipValue = (value: number, name: string) => [
    formatValue(value),
    name
  ];

  const formatXAxisLabel = (tickItem: string) => {
    // Try to format as date if it looks like a date
    if (tickItem.includes('-') && tickItem.length >= 10) {
      try {
        const date = new Date(tickItem);
        return date.toLocaleDateString('pt-BR', {
          month: 'short',
          day: 'numeric'
        });
      } catch {
        return tickItem;
      }
    }
    return tickItem;
  };

  const renderChart = () => {
    const commonProps = {
      data,
      height,
      margin: { top: 5, right: 30, left: 20, bottom: 5 }
    };

    switch (type) {
      case 'line':
        return (
          <LineChart {...commonProps}>
            <XAxis
              dataKey="name"
              tickFormatter={formatXAxisLabel}
              fontSize={12}
              tickLine={false}
              axisLine={false}
            />
            <YAxis
              fontSize={12}
              tickLine={false}
              axisLine={false}
              tickFormatter={formatValue}
            />
            <Tooltip
              formatter={formatTooltipValue}
              labelStyle={{ color: 'hsl(var(--foreground))' }}
              contentStyle={{
                backgroundColor: 'hsl(var(--background))',
                border: '1px solid hsl(var(--border))',
                borderRadius: '6px'
              }}
            />
            <Line
              type="monotone"
              dataKey={dataKey}
              stroke={color}
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4, stroke: color }}
            />
          </LineChart>
        );

      case 'bar':
        return (
          <BarChart {...commonProps}>
            <XAxis
              dataKey="name"
              tickFormatter={formatXAxisLabel}
              fontSize={12}
              tickLine={false}
              axisLine={false}
            />
            <YAxis
              fontSize={12}
              tickLine={false}
              axisLine={false}
              tickFormatter={formatValue}
            />
            <Tooltip
              formatter={formatTooltipValue}
              labelStyle={{ color: 'hsl(var(--foreground))' }}
              contentStyle={{
                backgroundColor: 'hsl(var(--background))',
                border: '1px solid hsl(var(--border))',
                borderRadius: '6px'
              }}
            />
            <Bar
              dataKey={dataKey}
              fill={color}
              radius={[4, 4, 0, 0]}
            />
          </BarChart>
        );

      case 'area':
      default:
        return (
          <AreaChart {...commonProps}>
            <XAxis
              dataKey="name"
              tickFormatter={formatXAxisLabel}
              fontSize={12}
              tickLine={false}
              axisLine={false}
            />
            <YAxis
              fontSize={12}
              tickLine={false}
              axisLine={false}
              tickFormatter={formatValue}
            />
            <Tooltip
              formatter={formatTooltipValue}
              labelStyle={{ color: 'hsl(var(--foreground))' }}
              contentStyle={{
                backgroundColor: 'hsl(var(--background))',
                border: '1px solid hsl(var(--border))',
                borderRadius: '6px'
              }}
            />
            <Area
              type="monotone"
              dataKey={dataKey}
              stroke={color}
              fill={color}
              fillOpacity={0.2}
              strokeWidth={2}
            />
          </AreaChart>
        );
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        {description && (
          <CardDescription>{description}</CardDescription>
        )}
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={height}>
          {renderChart()}
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}