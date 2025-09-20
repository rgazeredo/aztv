<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro de Ativação - AZ TV Player</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">AZ TV Player</h1>
                <p class="text-gray-600">Sistema de Ativação</p>
            </div>

            <!-- Error Card -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center mb-6">
                    <!-- Error Icon -->
                    <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>

                    <h2 class="text-xl font-semibold text-red-800 mb-2">Falha na Ativação</h2>
                    <p class="text-gray-600 text-sm">{{ $error }}</p>
                </div>

                <!-- Error Details -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="text-sm text-red-700">
                        @if(isset($token) && $token)
                            @if($token->is_used)
                                <p class="font-medium mb-2">Este código de ativação já foi utilizado.</p>
                                <p>Data de utilização: {{ $token->used_at->format('d/m/Y H:i') }}</p>
                            @elseif($token->isExpired())
                                <p class="font-medium mb-2">Este código de ativação expirou.</p>
                                <p>Data de expiração: {{ $token->expires_at->format('d/m/Y H:i') }}</p>
                            @endif
                        @else
                            <p class="font-medium">Código de ativação inválido ou não encontrado.</p>
                        @endif
                    </div>
                </div>

                <!-- Possible Solutions -->
                <div class="space-y-4 mb-6">
                    <h3 class="font-semibold text-gray-900">O que você pode fazer:</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-start space-x-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">1</span>
                            <span>Verifique se o código foi digitado corretamente</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">2</span>
                            <span>Solicite um novo código de ativação ao administrador</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">3</span>
                            <span>Entre em contato com o suporte técnico se o problema persistir</span>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="text-center">
                    <button
                        onclick="window.history.back()"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors mb-3"
                    >
                        ⬅️ Voltar
                    </button>
                </div>

                <!-- Footer -->
                <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                    <p class="text-xs text-gray-500">
                        Se você acredita que este é um erro, entre em contato com o suporte técnico.
                    </p>
                </div>
            </div>

            <!-- Help Section -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Precisa de ajuda?
                    <a href="mailto:suporte@aztv.com" class="text-blue-600 hover:text-blue-700">Entre em contato</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect to home after 30 seconds if user doesn't take action
        setTimeout(() => {
            if (confirm('Deseja ser redirecionado para a página inicial?')) {
                window.location.href = '/';
            }
        }, 30000);
    </script>
</body>
</html>