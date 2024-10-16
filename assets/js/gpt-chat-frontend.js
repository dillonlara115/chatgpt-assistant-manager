document.addEventListener('DOMContentLoaded', function() {
    const chatbots = document.querySelectorAll('.chatbot-container');
    
    chatbots.forEach(function(chatbot) {
        const messageInput = chatbot.querySelector('#gpt-chat-message');
        const sendButton = chatbot.querySelector('#gpt-chat-send');
        const messagesContainer = chatbot.querySelector('#gpt-chat-messages');

        let currentThreadId = 'test';
        let isNewSession = true;

        function appendMessage(content, type = 'user') {
            const messageElement = document.createElement('div');
            messageElement.classList.add('message');
            messageElement.classList.add(type === 'assistant' ? 'bot-message' : 'user-message');
            messageElement.innerHTML = marked.parse(content);
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            return messageElement;
        }

        function fetchWithStreaming(message, assistantId, threadId, apiKeyName) {
            let responseElement = null;
            
            const requestBody = {
                message: message,
                assistantId: assistantId,
                threadId: threadId,
                apiKeyName: apiKeyName,
                wordpressUrl: gptChatData.wordpressUrl,
                authToken: gptChatData.authToken,
                apiToken: gptChatData.apiToken
            };
        
            fetch(gptChatData.nodeJsUrl + '/api/run-assistant', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status) {
                    updateStatus(data.status);
                } else if (data.type === 'message') {
                    if (!responseElement) {
                        responseElement = appendMessage('', 'assistant');
                    }
                    appendToChat(data.content, responseElement);
                } else if (data.error) {
                    showError(data.error);
                }
                if (data.threadId) {
                    currentThreadId = data.threadId;
                    console.log('Updated thread ID:', currentThreadId);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError(error.message);
            });
        }

        function updateStatus(status) {
            console.log('Current status:', status);
        }

        function appendToChat(text, element) {
            if (element) {
                element.innerHTML += marked.parse(text);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            } else {
                console.error('No element to append to');
            }
        }

        function showError(error) {
            console.error('Error:', error);
            appendMessage('Error: ' + error, 'assistant');
        }

      
        function sendMessage(message, isInitialPrompt = false) {
          

            const assistantId = chatbot.dataset.assistantId;
            const apiKeyName = chatbot.dataset.apiKeyName;
            const requestBody = {
                message: message,
                assistantId: assistantId,
                apiKeyName: apiKeyName,
                threadId: isNewSession ? '' : (currentThreadId || '')
            };

            if (!isInitialPrompt) {
                appendMessage(message, 'user');
            }

            fetchWithStreaming(message, assistantId, currentThreadId, apiKeyName);
            isNewSession = false;
        }

        function fetchInitialPrompt() {
            sendMessage('Hello, can you provide an initial prompt?', true);
        }

        fetchInitialPrompt();

        sendButton.addEventListener('click', function() {
            const message = messageInput.value.trim();
            if (!message) return;
            messageInput.value = '';
            sendMessage(message);
        });

        messageInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendButton.click();
            }
        });
    });
});