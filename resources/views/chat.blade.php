<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente de Datos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        #chat-messages::-webkit-scrollbar { width: 6px; }
        #chat-messages::-webkit-scrollbar-track { background: transparent; }
        #chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .message-content { white-space: pre-wrap; word-break: break-word; }
        .message-content table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 0.9em; }
        .message-content th, .message-content td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; }
        .message-content th { background-color: #f1f5f9; font-weight: 600; }
        .chart-container { max-width: 100%; margin-top: 12px; background: white; border-radius: 12px; padding: 16px; }
    </style>
</head>
<body class="bg-slate-100 h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-lg">AI</div>
            <div>
                <h1 class="font-semibold text-slate-800">Asistente de Datos</h1>
                <p class="text-xs text-slate-500">Consultas, reportes y gráficos en lenguaje natural</p>
            </div>
        </div>
        <button onclick="clearChat()" class="text-sm text-slate-500 hover:text-slate-700 transition">Limpiar chat</button>
    </header>

    <!-- Chat messages -->
    <main id="chat-messages" class="flex-1 overflow-y-auto px-4 py-6 space-y-6 max-w-4xl w-full mx-auto">
        <!-- Mensaje de bienvenida -->
        <div class="flex gap-3">
            <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">AI</div>
            <div class="bg-white rounded-2xl rounded-tl-sm px-5 py-3 shadow-sm max-w-[85%]">
                <div class="message-content text-slate-700 text-sm leading-relaxed">
                    ¡Hola! 👋 Soy tu asistente de datos.<br><br>
                    Puedes pedirme:<br>
                    • Consultas → "¿Cuántas categorías hay?"<br>
                    • Reportes → "Traeme un reporte de productos y órdenes"<br>
                    • <strong>Gráficos</strong> → "Haceme un gráfico de barras de los productos más vendidos"<br><br>
                    ¿En qué te ayudo?
                </div>
            </div>
        </div>
    </main>

    <!-- Input area -->
    <footer class="bg-white border-t border-slate-200 px-4 py-4">
        <div class="max-w-4xl mx-auto flex gap-3">
            <input 
                id="user-input" 
                type="text" 
                placeholder="Escribe tu pregunta o pedí un gráfico..." 
                class="flex-1 border border-slate-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                onkeydown="if(event.key === 'Enter') sendMessage()"
            >
            <button 
                id="send-btn"
                onclick="sendMessage()" 
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-xl text-sm font-medium transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span>Enviar</span>
            </button>
        </div>
    </footer>

    <script>
        const chatMessages = document.getElementById('chat-messages');
        const userInput = document.getElementById('user-input');
        const sendBtn = document.getElementById('send-btn');
        let chartCounter = 0;

        function addMessage(content, isUser = false) {
            const wrapper = document.createElement('div');
            wrapper.className = `flex gap-3 ${isUser ? 'flex-row-reverse' : ''}`;

            const avatar = document.createElement('div');
            avatar.className = `w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0 ${isUser ? 'bg-slate-600' : 'bg-indigo-600'}`;
            avatar.textContent = isUser ? 'Tú' : 'AI';

            const bubble = document.createElement('div');
            bubble.className = `rounded-2xl px-5 py-3 shadow-sm max-w-[85%] ${isUser ? 'bg-indigo-600 text-white rounded-tr-sm' : 'bg-white text-slate-700 rounded-tl-sm'}`;

            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content text-sm leading-relaxed';
            
            // Convertir markdown simple a HTML
            let html = content
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');

            contentDiv.innerHTML = html;
            bubble.appendChild(contentDiv);

            wrapper.appendChild(avatar);
            wrapper.appendChild(bubble);
            chatMessages.appendChild(wrapper);

            // Scroll al final
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addChartMessage(answer, chartType, title, labels, data) {
            const wrapper = document.createElement('div');
            wrapper.className = 'flex gap-3';

            const avatar = document.createElement('div');
            avatar.className = 'w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0';
            avatar.textContent = 'AI';

            const bubble = document.createElement('div');
            bubble.className = 'bg-white rounded-2xl rounded-tl-sm px-5 py-3 shadow-sm max-w-[90%] w-full';

            // Texto explicativo
            const textDiv = document.createElement('div');
            textDiv.className = 'message-content text-sm leading-relaxed mb-3';
            textDiv.innerHTML = answer.replace(/\n/g, '<br>');
            bubble.appendChild(textDiv);

            // Contenedor del gráfico
            const chartId = `chart-${++chartCounter}`;
            const chartContainer = document.createElement('div');
            chartContainer.className = 'chart-container';
            chartContainer.innerHTML = `<canvas id="${chartId}" height="220"></canvas>`;
            bubble.appendChild(chartContainer);

            wrapper.appendChild(avatar);
            wrapper.appendChild(bubble);
            chatMessages.appendChild(wrapper);

            // Crear el gráfico con Chart.js
            const ctx = document.getElementById(chartId).getContext('2d');
            new Chart(ctx, {
                type: chartType || 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: title,
                        data: data,
                        backgroundColor: [
                            'rgba(99, 102, 241, 0.7)',
                            'rgba(16, 185, 129, 0.7)',
                            'rgba(245, 158, 11, 0.7)',
                            'rgba(239, 68, 68, 0.7)',
                            'rgba(139, 92, 246, 0.7)',
                            'rgba(236, 72, 153, 0.7)',
                            'rgba(20, 184, 166, 0.7)',
                            'rgba(249, 115, 22, 0.7)'
                        ],
                        borderColor: [
                            'rgb(99, 102, 241)',
                            'rgb(16, 185, 129)',
                            'rgb(245, 158, 11)',
                            'rgb(239, 68, 68)',
                            'rgb(139, 92, 246)',
                            'rgb(236, 72, 153)',
                            'rgb(20, 184, 166)',
                            'rgb(249, 115, 22)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { 
                            display: chartType === 'pie' || chartType === 'doughnut' 
                        },
                        title: { 
                            display: true, 
                            text: title 
                        }
                    },
                    scales: (chartType === 'pie' || chartType === 'doughnut') ? {} : {
                        y: { beginAtZero: true }
                    }
                }
            });

            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addLoading() {
            const wrapper = document.createElement('div');
            wrapper.id = 'loading-message';
            wrapper.className = 'flex gap-3';
            wrapper.innerHTML = `
                <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">AI</div>
                <div class="bg-white rounded-2xl rounded-tl-sm px-5 py-3 shadow-sm">
                    <div class="flex items-center gap-2 text-slate-500 text-sm">
                        <div class="flex gap-1">
                            <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                            <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                            <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                        </div>
                        Pensando...
                    </div>
                </div>
            `;
            chatMessages.appendChild(wrapper);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function removeLoading() {
            const loading = document.getElementById('loading-message');
            if (loading) loading.remove();
        }

        async function sendMessage() {
            const message = userInput.value.trim();
            if (!message) return;

            // Deshabilitar input
            userInput.disabled = true;
            sendBtn.disabled = true;

            // Mostrar mensaje del usuario
            addMessage(message, true);
            userInput.value = '';

            // Mostrar loading
            addLoading();

            try {
                const response = await fetch('/api/reporte', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ prompt: message })
                });

                const data = await response.json();
                removeLoading();

                if (data.status === 'success') {
                    if (data.type === 'chart') {
                        addChartMessage(
                            data.answer,
                            data.chartType,
                            data.title,
                            data.labels,
                            data.data
                        );
                    } else {
                        addMessage(data.answer || 'Respuesta recibida.');
                    }
                } else {
                    // Mostrar error + SQL si viene
                    let errorMsg = data.message || 'Ocurrió un error.';
                    if (data.sql) {
                        errorMsg += '\n\nSQL generado:\n' + data.sql;
                    }
                    addMessage(errorMsg);
                }
            } catch (error) {
                removeLoading();
                addMessage('Error de conexión. Intenta de nuevo.');
                console.error(error);
            }

            // Rehabilitar input
            userInput.disabled = false;
            sendBtn.disabled = false;
            userInput.focus();
        }

        function clearChat() {
            chatMessages.innerHTML = `
                <div class="flex gap-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">AI</div>
                    <div class="bg-white rounded-2xl rounded-tl-sm px-5 py-3 shadow-sm max-w-[85%]">
                        <div class="message-content text-slate-700 text-sm leading-relaxed">
                            ¡Hola! 👋 Soy tu asistente de datos.<br><br>
                            Puedes pedirme:<br>
                            • Consultas → "¿Cuántas categorías hay?"<br>
                            • Reportes → "Traeme un reporte de productos y órdenes"<br>
                            • <strong>Gráficos</strong> → "Haceme un gráfico de barras de los productos más vendidos"<br><br>
                            ¿En qué te ayudo?
                        </div>
                    </div>
                </div>
            `;
        }

        // Focus al cargar
        userInput.focus();
    </script>
</body>
</html>