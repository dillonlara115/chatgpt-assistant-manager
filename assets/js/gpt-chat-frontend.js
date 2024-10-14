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
            console.log('Fetching initial prompt');
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
            })
            .then(response => {
                console.log('Response Status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Parsed Response:', data);  // Log the parsed response
                
                // Hide loading indicator
                loadingIndicator.style.display = 'none';
        
                // Check for success
                if (data.success) {
                    // Access and log the thread ID
                    currentThreadId = data.data.thread_id;
                    console.log('Initial Thread ID:', currentThreadId);
        
                    let responseData = data.data.response;
        
                    // Check if the response contains Markdown
                    if (responseData && (responseData.includes('**') || responseData.includes('_') || responseData.includes('`'))) {
                        // Convert Markdown to HTML
                        responseData = marked.parse(responseData);
                    }
        
                    // Append the assistant's response to the chat
                    appendMessage(responseData, 'assistant');
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
            loadingIndicator.style.display = 'block';
        
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
                    thread_id: currentThreadId || ''
                })
            }).then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error, status = ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                loadingIndicator.style.display = 'none';
                
                if (data.success) {
                    currentThreadId = data.data.thread_id;
                    
                    let responseData = data.data.response;
                    if (responseData && (responseData.includes('**') || responseData.includes('_') || responseData.includes('`'))) {
                        responseData = marked.parse(responseData);
                    }
                    
                    appendMessage(responseData, 'assistant');
                } else {
                    console.error('Server Error:', data);
                    appendMessage('Error: ' + (data.error || 'Unknown error occurred.'), 'assistant');
                }
            })
            .catch((error) => {
                loadingIndicator.style.display = 'none';
                console.error('Fetch Error:', error);
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