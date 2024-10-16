document.addEventListener('DOMContentLoaded', function() {
    const chatbots = document.querySelectorAll('.chatbot-container');
    
    chatbots.forEach(function(chatbot) {
        const messageInput = chatbot.querySelector('#gpt-chat-message');
        const sendButton = chatbot.querySelector('#gpt-chat-send');
        const messagesContainer = chatbot.querySelector('#gpt-chat-messages');

        // Variable to store the current thread ID
        let currentThreadId = null;
        let isNewSession = true;


        // Function to append a message to the chat window
        function appendMessage(content, type = 'user') {
            const messageElement = document.createElement('div');
            messageElement.classList.add('message');
            messageElement.classList.add(type === 'assistant' ? 'bot-message' : 'user-message');
            
            // Use innerHTML instead of textContent to allow HTML rendering
            messageElement.innerHTML = marked.parse(content);

            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;  // Auto-scroll to the bottom
            return messageElement;
        }

        // Function to handle streaming fetch
        function fetchWithStreaming(requestBody) {
            let responseElement = null;
        
            fetch(gptChatAjax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: requestBody
            })
            .then(response => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
        
                function readChunk() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            return;
                        }
                        
                        buffer += decoder.decode(value, { stream: true });
                        
                        let boundary;
                        while ((boundary = buffer.indexOf('\r\n')) !== -1) {
                            const chunk = buffer.slice(0, boundary);
                            buffer = buffer.slice(boundary + 2);
                            
                            if (chunk.trim()) {
                                try {
                                    const data = JSON.parse(chunk);
                                    if (data.status) {
                                        updateStatus(data.status);
                                    } else if (data.type === 'message') {
                                        if (!responseElement) {
                                            responseElement = appendMessage('', 'assistant');
                                        }
                                        appendToChat(data.content, responseElement);
                                    } else if (data.type === 'error') {
                                        showError(data.message);
                                    }
                                    if (data.thread_id) {
                                        currentThreadId = data.thread_id;
                                        console.log('Updated thread ID:', currentThreadId);
                                    }
                                } catch (e) {
                                    console.error('Error parsing chunk:', e);
                                }
                            }
                        }
                        
                        return readChunk();
                    });
                }
        
                return readChunk();
            })
            .catch(error => {
                console.error('Error:', error);
                showError(error.message);
            });
        }
        
        // Function to update status
        function updateStatus(status) {
            console.log('Current status:', status);
            // You can implement UI updates for status here if needed
        }
        
        // Function to append to chat
        function appendToChat(text, element) {
            if (element) {
                element.innerHTML += marked.parse(text);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            } else {
                console.error('No element to append to');
            }
        }
        
        // Function to show error
        function showError(error) {
            console.error('Error:', error);
            appendMessage('Error: ' + error, 'assistant');
        }

        // Function to send a message
        function sendMessage(message, isInitialPrompt = false) {
            const assistantId = chatbot.dataset.assistantId;
            const requestBody = new URLSearchParams({
                action: 'gpt_chat_send_message',
                message: message,
                assistant_id: assistantId,
                api_key_name: chatbot.dataset.apiKeyName,
                nonce: gptChatAjax.nonce,
                thread_id: isNewSession ? '' : (currentThreadId || '')
            });

            if (!isInitialPrompt) {
                appendMessage(message, 'user');
            }

            fetchWithStreaming(requestBody);
            isNewSession = false;
        }

        // Function to fetch initial prompt
        function fetchInitialPrompt() {
            sendMessage('Hello, can you provide an initial prompt?', true);
        }

        // Call the function to fetch the initial prompt
        fetchInitialPrompt();

        // Handle "Send" button click
        sendButton.addEventListener('click', function() {
            const message = messageInput.value.trim();
            if (!message) return;  // Ignore if empty

            messageInput.value = '';  // Clear input
            sendMessage(message);
        });

        // Allow sending message on Enter key press
        messageInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendButton.click();
            }
        });
    });
});