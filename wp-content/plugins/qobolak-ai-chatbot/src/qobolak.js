document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('qobolak-btn')
  const chatBox = document.createElement('div')
  const MAX_MESSAGE_LENGTH = 150
  let messageLengthRemaining = MAX_MESSAGE_LENGTH

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
              placeholder="Type your message..." rows="1" max="${MAX_MESSAGE_LENGTH}"></textarea>
            <button id="qobolak-send" class="px-8 py-2 text-white bg-blue-500 rounded-md hover:bg-blue-600">
              <strong>Send</strong>
            </button>
          </div>
      </div>
  `
  document.body.appendChild(chatBox)

  const inputField = document.getElementById('qobolak-input')
  const sendButton = document.getElementById('qobolak-send')
  const messagesDiv = document.getElementById('qobolak-messages')

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

    // Display user message
    appendMessage('user', message)

    // Show loading indicator
    const loadingDiv = document.createElement('div')
    loadingDiv.classList = 'text-left mb-2 text-gray-500'
    loadingDiv.textContent = 'Typing...'
    messagesDiv.appendChild(loadingDiv)
    scrollToBottom()

    // Prepare form data
    const formData = new FormData()
    formData.append('action', 'qobolak_chat')
    formData.append('message', message)
    formData.append('security', qobolakAjax.nonce)

    try {
      const response = await fetch(qobolakAjax.url, {
        method: 'POST',
        body: formData,
      })

      const data = await response.json()

      // Remove loading indicator
      loadingDiv.remove()

      if (data.success) {
        appendMessage('bot', data.data.response)
      } else {
        appendMessage('bot', 'Sorry, I encountered an error. Please try again.')
      }
    } catch (error) {
      loadingDiv.remove()
      appendMessage('bot', 'Sorry, there was an error processing your request.')
      console.error('Error:', error)
    }
  }

  function appendMessage(sender, text) {
    const messageDiv = document.createElement('div')
    messageDiv.classList =
      sender === 'user' ? 'text-right mb-2 text-blue-500' : 'text-left mb-2 text-gray-500'
    messageDiv.textContent = text
    messagesDiv.appendChild(messageDiv)
    scrollToBottom()
  }

  function scrollToBottom() {
    messagesDiv.scrollTop = messagesDiv.scrollHeight
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
