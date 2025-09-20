<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar Player - {{ $tenant->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">AZ TV Player</h1>
                <p class="text-gray-600">{{ $tenant->name }}</p>
            </div>

            <!-- Main Card -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Ativação do Player</h2>
                    <p class="text-gray-600 text-sm">Escaneie o QR Code ou digite o código de ativação no seu player Android</p>
                </div>

                <!-- QR Code -->
                @if($qr_code_url)
                <div class="flex justify-center mb-6">
                    <div class="bg-white p-4 rounded-lg border-2 border-gray-200">
                        <img src="{{ $qr_code_url }}" alt="QR Code de Ativação" class="w-48 h-48">
                    </div>
                </div>
                @endif

                <!-- Activation Code -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Código de Ativação</label>
                    <div class="flex items-center space-x-2">
                        <input
                            type="text"
                            value="{{ $activation_code }}"
                            readonly
                            class="flex-1 px-3 py-2 bg-white border border-gray-300 rounded-md font-mono text-lg text-center tracking-widest"
                            id="activationCode"
                        >
                        <button
                            onclick="copyToClipboard()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                            id="copyButton"
                        >
                            Copiar
                        </button>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="space-y-4 mb-6">
                    <h3 class="font-semibold text-gray-900">Como ativar:</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-start space-x-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">1</span>
                            <span>Abra o aplicativo AZ TV Player no seu dispositivo Android</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">2</span>
                            <span>Escaneie o QR Code acima ou digite o código de ativação</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium">3</span>
                            <span>Aguarde a confirmação da ativação</span>
                        </div>
                    </div>
                </div>

                <!-- Expiration Info -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start space-x-2">
                        <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-yellow-800">Código expira em:</p>
                            <p class="text-sm text-yellow-700">{{ $expires_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                    @if($qr_code_url)
                    <a
                        href="{{ route('activation.download', $token->token) }}"
                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition-colors text-center block"
                        download
                    >
                        📱 Baixar QR Code
                    </a>
                    @endif

                    <button
                        onclick="shareActivation()"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors"
                    >
                        📤 Compartilhar
                    </button>
                </div>

                <!-- Footer -->
                <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                    <p class="text-xs text-gray-500">
                        Mantenha este código seguro. Não compartilhe com pessoas não autorizadas.
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
        function copyToClipboard() {
            const input = document.getElementById('activationCode');
            const button = document.getElementById('copyButton');

            input.select();
            input.setSelectionRange(0, 99999);

            try {
                document.execCommand('copy');
                button.textContent = 'Copiado!';
                button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                button.classList.add('bg-green-600');

                setTimeout(() => {
                    button.textContent = 'Copiar';
                    button.classList.remove('bg-green-600');
                    button.classList.add('bg-blue-600', 'hover:bg-blue-700');
                }, 2000);
            } catch (err) {
                console.error('Falha ao copiar:', err);
            }
        }

        function shareActivation() {
            const code = '{{ $activation_code }}';
            const text = `Código de ativação AZ TV Player: ${code}`;

            if (navigator.share) {
                navigator.share({
                    title: 'Ativação AZ TV Player',
                    text: text,
                    url: window.location.href
                });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Link de ativação copiado para a área de transferência!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Link de ativação copiado para a área de transferência!');
            }
        }

        // Auto-refresh if token is about to expire
        const expiresAt = new Date('{{ $expires_at->toISOString() }}');
        const now = new Date();
        const timeUntilExpiry = expiresAt.getTime() - now.getTime();

        if (timeUntilExpiry > 0 && timeUntilExpiry < 300000) { // Less than 5 minutes
            setTimeout(() => {
                window.location.reload();
            }, timeUntilExpiry + 1000);
        }
    </script>
</body>
</html>