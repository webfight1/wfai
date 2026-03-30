<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CRM AI Assistent</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .message-enter {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .typing-indicator span {
            animation: blink 1.4s infinite;
        }
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes blink {
            0%, 60%, 100% { opacity: 0.3; }
            30% { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col h-[85vh]">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6">
                <h1 class="text-2xl font-bold">CRM AI Assistent</h1>
                <p class="text-blue-100 text-sm mt-1">Küsi mind klientide, ülesannete ja projektide kohta</p>
            </div>

            <!-- Messages Container -->
            <div id="messages" class="flex-1 overflow-y-auto p-6 space-y-4">
                <div class="flex justify-start">
                    <div class="bg-gray-100 rounded-2xl rounded-tl-none px-4 py-3 max-w-[80%]">
                        <p class="text-gray-800">Tere! Olen CRM assistent. Saan aidata sul leida infot klientide, ülesannete ja projektide kohta. Küsi julgelt!</p>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="border-t border-gray-200 p-4 bg-gray-50">
                <form id="chatForm" class="flex gap-3">
                    <input 
                        type="text" 
                        id="messageInput" 
                        placeholder="Kirjuta oma küsimus siia..."
                        class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        autocomplete="off"
                    >
                    <button 
                        type="submit" 
                        id="sendButton"
                        class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Saada
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const messagesContainer = document.getElementById('messages');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');

        function convertMarkdownLinks(text) {
            // Convert [text](url) to <a href="url">text</a>
            return text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="underline hover:text-blue-600 font-medium">$1</a>');
        }

        function addMessage(content, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'} message-enter`;
            
            const bubble = document.createElement('div');
            bubble.className = `${isUser ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl rounded-tr-none' : 'bg-gray-100 text-gray-800 rounded-2xl rounded-tl-none'} px-4 py-3 max-w-[80%] shadow-md`;
            
            const text = document.createElement('p');
            text.style.whiteSpace = 'pre-wrap';
            
            // Convert markdown links to HTML if not user message
            if (!isUser) {
                text.innerHTML = convertMarkdownLinks(content);
            } else {
                text.textContent = content;
            }
            
            bubble.appendChild(text);
            messageDiv.appendChild(bubble);
            messagesContainer.appendChild(messageDiv);
            
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function addTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.id = 'typing-indicator';
            typingDiv.className = 'flex justify-start';
            
            typingDiv.innerHTML = `
                <div class="bg-gray-100 rounded-2xl rounded-tl-none px-4 py-3 shadow-md">
                    <div class="typing-indicator flex gap-1">
                        <span class="w-2 h-2 bg-gray-400 rounded-full inline-block"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full inline-block"></span>
                        <span class="w-2 h-2 bg-gray-400 rounded-full inline-block"></span>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(typingDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function removeTypingIndicator() {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;
            
            addMessage(message, true);
            messageInput.value = '';
            messageInput.disabled = true;
            sendButton.disabled = true;
            
            addTypingIndicator();
            
            try {
                const response = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ message })
                });
                
                const data = await response.json();
                
                removeTypingIndicator();
                
                if (data.reply) {
                    addMessage(data.reply, false);
                } else {
                    addMessage('Vabandust, tekkis viga. Palun proovi uuesti.', false);
                }
            } catch (error) {
                removeTypingIndicator();
                addMessage('Vabandust, tekkis ühenduse viga. Palun kontrolli oma internetiühendust.', false);
                console.error('Error:', error);
            } finally {
                messageInput.disabled = false;
                sendButton.disabled = false;
                messageInput.focus();
            }
        });

        messageInput.focus();
    </script>
</body>
</html>
