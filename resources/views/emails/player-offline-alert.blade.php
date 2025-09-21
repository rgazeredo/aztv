<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Player Offline Alert</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .alert-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
            color: white;
        }
        .content {
            padding: 30px;
        }
        .player-info {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #ef4444;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #374151;
        }
        .info-value {
            color: #6b7280;
        }
        .alert-message {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .alert-message .icon {
            font-size: 20px;
            margin-right: 8px;
        }
        .actions {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 0 10px;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #2563eb;
        }
        .btn-secondary {
            background-color: #6b7280;
        }
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .status-critical {
            background-color: #dc2626;
        }
        .status-error {
            background-color: #ef4444;
        }
        .status-warning {
            background-color: #f59e0b;
        }
        @media (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            .content {
                padding: 20px;
            }
            .info-row {
                flex-direction: column;
            }
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Player Offline</h1>
            <div class="alert-badge status-{{ $alertSeverity['level'] }}">
                {{ $alertSeverity['icon'] }} {{ $alertSeverity['label'] }}
            </div>
        </div>

        <div class="content">
            <div class="alert-message">
                <span class="icon">{{ $alertSeverity['icon'] }}</span>
                <strong>{{ $player->name }}</strong> está offline há <strong>{{ $offlineDuration }}</strong>.
                <br>
                <small>Último contato: {{ $lastSeenFormatted }}</small>
            </div>

            <div class="player-info">
                <h3 style="margin-top: 0; color: #374151;">Informações do Player</h3>

                <div class="info-row">
                    <span class="info-label">Nome:</span>
                    <span class="info-value">{{ $player->name }}</span>
                </div>

                @if($player->location)
                <div class="info-row">
                    <span class="info-label">Localização:</span>
                    <span class="info-value">{{ $player->location }}</span>
                </div>
                @endif

                @if($player->ip_address)
                <div class="info-row">
                    <span class="info-label">Endereço IP:</span>
                    <span class="info-value">{{ $player->ip_address }}</span>
                </div>
                @endif

                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: #dc2626; font-weight: 600;">
                        🔴 Offline
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Tempo offline:</span>
                    <span class="info-value" style="color: #dc2626; font-weight: 600;">
                        {{ $offlineDuration }}
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Último contato:</span>
                    <span class="info-value">{{ $lastSeenFormatted }}</span>
                </div>

                @if($player->app_version)
                <div class="info-row">
                    <span class="info-label">Versão do App:</span>
                    <span class="info-value">{{ $player->app_version }}</span>
                </div>
                @endif
            </div>

            <div class="actions">
                <a href="{{ $playerUrl }}" class="btn">
                    👁️ Ver Detalhes do Player
                </a>
                <a href="{{ $dashboardUrl }}" class="btn btn-secondary">
                    📊 Ir para Dashboard
                </a>
            </div>

            <div style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 16px; margin: 20px 0;">
                <h4 style="margin-top: 0; color: #0369a1;">💡 Próximos passos recomendados:</h4>
                <ul style="margin: 0; color: #0369a1;">
                    <li>Verifique se o dispositivo está ligado e conectado à internet</li>
                    <li>Confirme se o aplicativo AZ TV Player está em execução</li>
                    <li>Teste a conectividade de rede no local do player</li>
                    <li>Considere reinicializar o dispositivo se o problema persistir</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p>
                <strong>{{ $tenant->name }}</strong><br>
                Sistema AZ TV - Monitoramento de Players<br>
                Alerta gerado automaticamente em {{ now()->format('d/m/Y \à\s H:i') }}
            </p>
            <p style="font-size: 12px; color: #9ca3af;">
                Para parar de receber estes alertas, entre em contato com o administrador do sistema.
            </p>
        </div>
    </div>
</body>
</html>