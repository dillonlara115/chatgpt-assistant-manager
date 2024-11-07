document.addEventListener('DOMContentLoaded', function() {
    const chatbots = document.querySelectorAll('.chatbot-container');

    chatbots.forEach(function(chatbot) {
        const messageInput = chatbot.querySelector('#gpt-chat-message');
        const sendButton = chatbot.querySelector('#gpt-chat-send');
        const messagesContainer = chatbot.querySelector('#gpt-chat-messages');

        let currentThreadId = null;  // Initially null to reflect new thread creation
        let isNewSession = true;
        let initialPromptSent = false;  // Track if the initial prompt was sent
        let lastMessageId = null;  // NEW: Track the last message ID appended

        function appendMessage(content, type = 'user') {
            const messageElement = document.createElement('div');
            messageElement.classList.add('message');
            messageElement.classList.add(type === 'assistant' ? 'bot-message' : 'user-message');
            messageElement.innerHTML = marked.parse(content);
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function fetchWithStreaming(message, assistantId, threadId, apiKeyName) {
            console.log('Sending message:', message);

            const requestBody = {
                message: message,
                assistantId: assistantId,
                threadId: threadId || '',  // Ensure threadId is a string
                apiKeyName: apiKeyName,
                wordpressUrl: gptChatData.wordpressUrl
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
                if (data.success && Array.isArray(data.messages)) {
                    console.log('Received messages:', data.messages);
                    data.messages.forEach((msg) => {
                        appendMessage(msg, 'assistant');
                    });
                } else if (data.error) {
                    showError(data.error);
                }

                // Update thread ID after the response is received
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
        // Function to send a message (initial or user message)
        function sendMessage(message, isInitialPrompt = false) {
            console.log('sendMessage called with:', message);  // Log each time this is called

            const assistantId = chatbot.dataset.assistantId;
            const apiKeyName = chatbot.dataset.apiKeyName;

            if (!isInitialPrompt) {
                appendMessage(message, 'user');  // Only append user messages
            }

            // Call fetchWithStreaming to send the message
            fetchWithStreaming(message, assistantId, currentThreadId, apiKeyName);
            isNewSession = false;  // Mark session as started after sending a message
        }

        // Function to send the initial prompt
        function fetchInitialPrompt() {
            if (!initialPromptSent && isNewSession) {
                console.log('Fetching initial prompt');  // Log for debugging
                const initialPrompt = 'Hello, can you provide an initial prompt?';
                sendMessage(initialPrompt, true);  // Send the initial prompt without appending it
                initialPromptSent = true;  // Mark the initial prompt as sent
            }
        }

        // Call fetchInitialPrompt only if it's a new session and initial prompt hasn't been sent
        if (isNewSession && !initialPromptSent) {
            fetchInitialPrompt();
        }

        // Event listener for send button click
        sendButton.addEventListener('click', function() {
            const message = messageInput.value.trim();
            if (!message) return;  // Do nothing if the input is empty
            messageInput.value = '';  // Clear the input field
            sendMessage(message);  // Send the user's message
        });

        // Event listener for "Enter" key to submit messages
        messageInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendButton.click();  // Simulate a button click on "Enter" key press
            }
        });

        function showError(error) {
            console.error('Error:', error);
            appendMessage('Error: ' + error, 'assistant');
        }
    });
});
