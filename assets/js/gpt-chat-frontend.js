document.addEventListener('DOMContentLoaded', function() {
    const chatbots = document.querySelectorAll('.chatbot-container');
    
    chatbots.forEach(function(chatbot) {
        const messageInput = chatbot.querySelector('#gpt-chat-message');
        const sendButton = chatbot.querySelector('#gpt-chat-send');
        const messagesContainer = chatbot.querySelector('#gpt-chat-messages');

        // Create a loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'loading-indicator';
        loadingIndicator.textContent = 'Loading...';
        loadingIndicator.style.display = 'none';  // Initially hidden
        messagesContainer.appendChild(loadingIndicator);

        // Variable to store the current thread ID
        let currentThreadId = null;

        // Function to append a message to the chat window
        function appendMessage(content, type = 'user') {
            const messageElement = document.createElement('div');
            messageElement.classList.add('message');
            messageElement.classList.add(type === 'assistant' ? 'bot-message' : 'user-message');
            messageElement.textContent = content;
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;  // Auto-scroll to the bottom
        }

        // Function to make an initial API call to get the first prompt
        function fetchInitialPrompt() {
            const assistantId = chatbot.dataset.assistantId;
        
            // Show loading indicator
            loadingIndicator.style.display = 'block';
        
            const requestBody = new URLSearchParams({
                action: 'gpt_chat_send_message',
                message: 'Hello, can you provide an initial prompt?',  // Default message for initial prompt
                assistant_id: assistantId,
                api_key_name: chatbot.dataset.apiKeyName,
                nonce: gptChatAjax.nonce
            });
        
            console.log('Request Body:', requestBody.toString());  // Log the request body
        
            fetch(gptChatAjax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: requestBody
            }).then(response => {
                console.log('Response Status:', response.status);  // Log the response status
                return response.json();
            })
            .then(data => {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';
        
                console.log('Server Response:', data);  // Log the entire server response
        
                if (data.success) {
                    if (data.data && data.data.thread_details) {
                        // Access the thread ID from the correct path
                        currentThreadId = data.data.thread_details.threadId;
                        console.log('Initial Thread ID:', currentThreadId);  // Log the thread ID
        
                        let responseData = data.data.response;
        
                        // Check if the response contains Markdown
                        if (responseData.includes('**') || responseData.includes('_') || responseData.includes('`')) {
                            // Convert Markdown to HTML
                            responseData = marked.parse(responseData);
                        }
        
                        // Append server's response to the chat
                        const botMessage = document.createElement('div');
                        botMessage.className = 'message bot-message';
                        botMessage.innerHTML = responseData;
                        messagesContainer.appendChild(botMessage);
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;  // Auto-scroll to the bottom
                    } else {
                        console.error('Thread details are missing in the response:', data);
                        appendMessage('Error: Thread details are missing.', 'assistant');
                    }
                } else {
                    console.error('Server Error:', data);
                    appendMessage('Error: ' + (data.error || 'Unknown error occurred.'), 'assistant');
                }
            })
            .catch((error) => {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';
        
                console.error('Fetch Error:', error);
                appendMessage('Error: ' + error.message, 'assistant');
            });
        }

        // Call the function to fetch the initial prompt
        fetchInitialPrompt();

        // Function to send a user message
        function sendMessage(message) {
            // Show loading indicator
            loadingIndicator.style.display = 'block';
        
            // Retrieve the assistant ID from the data attribute
            const assistantId = chatbot.dataset.assistantId;
            fetch(gptChatAjax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'gpt_chat_send_message',
                    message: message,
                    assistant_id: assistantId,
                    api_key_name: chatbot.dataset.apiKeyName,
                    nonce: gptChatAjax.nonce,
                    thread_id: currentThreadId  // Use the existing thread ID
                })
            }).then(response => response.json())
            .then(data => {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';
        
                if (data.success) {
                    let responseData = data.data.response;
        
                    // Check if the response contains Markdown
                    if (responseData.includes('**') || responseData.includes('_') || responseData.includes('`')) {
                        // Convert Markdown to HTML
                        responseData = marked.parse(responseData);
                    }
        
                    // Append server's response to the chat
                    const botMessage = document.createElement('div');
                    botMessage.className = 'message bot-message';
                    botMessage.innerHTML = responseData;
                    messagesContainer.appendChild(botMessage);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;  // Auto-scroll to the bottom
                } else {
                    appendMessage('Error: ' + (data.error || 'Unknown error occurred.'), 'assistant');
                }
            })
            .catch((error) => {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';
        
                appendMessage('Error: ' + error.message, 'assistant');
            });
        }

        // Handle "Send" button click
        sendButton.addEventListener('click', function() {
            const message = messageInput.value.trim();
            if (!message) return;  // Ignore if empty

            // Append the user's message to the chat window
            appendMessage(message, 'user');
            messageInput.value = '';  // Clear input

            // Send the user's message
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