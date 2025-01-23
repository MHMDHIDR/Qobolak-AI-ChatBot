document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('qobolak-btn')
  const chatBox = document.createElement('div')
  const MAX_MESSAGE_LENGTH = 500
  let messageLengthRemaining = MAX_MESSAGE_LENGTH
  let chatHistory = loadMessagesFromLocalStorage()
  let previousQuestion = null

  // Create chatbox HTML
  chatBox.id = 'qobolak-chatbox'
  chatBox.style.display = 'none'
  chatBox.classList =
    'fixed bottom-4 right-4 w-96 bg-white shadow-lg rounded-lg p-4 transition-all transform scale-50 opacity-0'
  chatBox.innerHTML = `
      <div class="flex justify-between items-center">
          <h2 class="text-lg font-bold select-none">Qobolak Chat</h2>
          <button id="qobolak-close" class="px-2 py-1 text-red-500">&times;</button>
      </div>
      <div id="qobolak-messages" class="overflow-y-auto mt-4 max-h-64"></div>
      <div class="flex flex-col mt-4">
          <span id="qobolak-message-length" class="inline-flex mr-2 text-sm text-gray-500 select-none">${messageLengthRemaining}/${MAX_MESSAGE_LENGTH}</span>
          <div class="gap-y-1">
            <textarea id="qobolak-input" class="flex-1 p-2 mr-1 rounded-l-lg border resize-none"
              placeholder="Type your message..." rows="1" max="${MAX_MESSAGE_LENGTH}" dir="auto"></textarea>
            <button id="qobolak-send" class="px-8 py-2 max-h-48 text-white bg-blue-500 rounded-md hover:bg-blue-600">
              <strong>Send</strong>
            </button>
          </div>
      </div>
  `
  document.body.appendChild(chatBox)

  const inputField = document.getElementById('qobolak-input')
  const sendButton = document.getElementById('qobolak-send')
  const messagesDiv = document.getElementById('qobolak-messages')

  // Clear messagesDiv and load chat history
  function renderChatHistory() {
    messagesDiv.innerHTML = '' // Clear previous messages
    chatHistory.forEach(message => {
      const messageDiv = document.createElement('div')
      messageDiv.classList =
        message.sender === 'user'
          ? 'text-right mb-2 text-blue-500'
          : 'text-left mb-2 text-gray-500'
      messageDiv.textContent = message.text
      messagesDiv.appendChild(messageDiv)
    })
    scrollToBottom()
  }

  renderChatHistory() // Load chat history on page load

  function toggleChatBox(show) {
    chatBox.style.display = show ? 'block' : 'none'
    if (show) {
      setTimeout(() => {
        chatBox.style.transform = 'scale(1)'
        chatBox.style.opacity = '1'
      }, 10)
    }
  }

  async function sendMessage(message) {
    if (!message.trim()) return

    appendMessage('user', message)

    const loadingDiv = document.createElement('div')
    loadingDiv.classList = 'text-left mb-2 text-gray-500'
    loadingDiv.textContent = 'Typing...'
    messagesDiv.appendChild(loadingDiv)
    scrollToBottom()

    const formData = new FormData()
    formData.append('action', 'qobolak_chat')
    formData.append('message', message)
    formData.append('security', qobolakAjax.nonce)
    formData.append('chatHistory', JSON.stringify(chatHistory))

    // If we have a previous question, this is a training answer
    if (previousQuestion) {
      formData.append('previous_question', previousQuestion)
      formData.append('training_answer', message)
      formData.append('is_training', 'true')
    }

    try {
      const response = await fetch(qobolakAjax.url, { method: 'POST', body: formData })
      const data = await response.json()
      loadingDiv.remove()

      if (data.success) {
        appendMessage('bot', data.data.response)

        if (data.data.is_training) {
          previousQuestion = data.data.previous_question // Store for next training response
        } else {
          previousQuestion = null // Clear previous question if not in training mode
        }
      } else {
        appendMessage(
          'bot',
          data.data.message || 'Sorry, I encountered an error. Please try again.'
        )
      }
    } catch (error) {
      loadingDiv.remove()
      appendMessage('bot', 'Sorry, there was an error processing your request.')
      console.error('Error:', error)
    }

    scrollToBottom()
  }

  function appendMessage(sender, text) {
    const messageDiv = document.createElement('div')
    messageDiv.classList =
      sender === 'user' ? 'text-right mb-2 text-blue-500' : 'text-left mb-2 text-gray-500'

    // Convert newlines to <br> tags and preserve whitespace
    const formattedText = text.replace(/\n/g, '<br>')
    messageDiv.style.whiteSpace = 'pre-wrap'
    messageDiv.style.wordBreak = 'break-word'
    messageDiv.innerHTML = formattedText

    messagesDiv.appendChild(messageDiv)
    scrollToBottom()

    // Update chat history
    chatHistory.push({ sender, text })
    if (chatHistory.length > 50) {
      chatHistory.shift()
    }
    saveMessagesToLocalStorage(chatHistory)
  }

  function scrollToBottom() {
    messagesDiv.scrollTop = messagesDiv.scrollHeight
  }

  function saveMessagesToLocalStorage(messages) {
    try {
      localStorage.setItem('qobolak-chat-history', JSON.stringify(messages))
    } catch (error) {
      console.error('Failed to save messages:', error)
    }
  }

  function loadMessagesFromLocalStorage() {
    try {
      const messages = localStorage.getItem('qobolak-chat-history')
      return messages ? JSON.parse(messages) : []
    } catch (error) {
      console.error('Failed to load messages:', error)
      return []
    }
  }

  // Dynamic textarea height adjustment
  inputField.addEventListener('input', () => {
    const messageLength = inputField.value.length
    messageLengthRemaining = MAX_MESSAGE_LENGTH - messageLength

    // Prevent additional characters if max length is exceeded
    if (messageLength > MAX_MESSAGE_LENGTH) {
      inputField.value = inputField.value.slice(0, MAX_MESSAGE_LENGTH)
      messageLengthRemaining = 0
    }

    // Adjust the height of the textarea
    inputField.style.height = 'auto'
    inputField.style.height = `${inputField.scrollHeight}px`

    // Update remaining characters display
    document.getElementById(
      'qobolak-message-length'
    ).textContent = `${messageLengthRemaining}/${MAX_MESSAGE_LENGTH}`
  })

  // Event Listeners
  button.addEventListener('click', () => toggleChatBox(true))
  document
    .getElementById('qobolak-close')
    .addEventListener('click', () => toggleChatBox(false))
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') toggleChatBox(false)
  })

  sendButton.addEventListener('click', () => {
    const message = inputField.value.trim()
    if (message) {
      sendMessage(message)
      inputField.value = ''
      inputField.style.height = 'auto'
      messageLengthRemaining = MAX_MESSAGE_LENGTH
      document.getElementById(
        'qobolak-message-length'
      ).textContent = `${messageLengthRemaining}/${MAX_MESSAGE_LENGTH}`
    }
  })

  inputField.addEventListener('keypress', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      sendButton.click()
    }
  })
})
