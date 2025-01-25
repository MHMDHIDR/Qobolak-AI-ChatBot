// Constants
const MAX_MESSAGE_LENGTH = 500

// Initialize chat interface when button is clicked
document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('qobolak-btn')
  if (!button) return

  // Create chat box container if it doesn't exist
  let chatBox = document.getElementById('qobolak-chat')
  if (!chatBox) {
    chatBox = document.createElement('div')
    chatBox.id = 'qobolak-chat'
    chatBox.classList.add('fixed', 'bottom-4', 'right-4', 'hidden', 'z-50')
    document.body.appendChild(chatBox)
  }

  // Initialize chat when button is clicked
  button.addEventListener('click', () => {
    if (chatBox.classList.contains('hidden')) {
      chatBox.classList.remove('hidden')
      if (!chatBox.hasChildNodes()) {
        initializeChat()
      }
    } else {
      chatBox.classList.add('hidden')
      // Clear interval if chat is hidden
      if (updateInterval) {
        clearInterval(updateInterval)
        updateInterval = null
      }
    }
  })
})

// Chat UI Template
function createChatUI() {
  document.getElementById('qobolak-chat').innerHTML = `
    <div class="p-4 w-full max-w-md bg-white rounded-lg shadow-lg">
      <div class="flex justify-between items-center">
        <h2 class="text-lg font-semibold">Qobolak AI Assistant</h2>
        <button id="qobolak-close" class="px-2 py-1 text-red-500">&times;</button>
      </div>
      <div id="qobolak-messages" class="overflow-y-auto relative mt-4 max-h-64"></div>
      <button id="scroll-to-bottom" title="Scroll to bottom" class="hidden fixed bottom-40 right-8 z-[9999] p-1.5 text-white bg-blue-500 rounded-full shadow-lg opacity-0 hover:bg-blue-600 transition-all duration-300 ease-in-out transform scale-75">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </button>
      <div class="flex flex-col mt-4">
        <span id="qobolak-message-length" class="inline-flex mr-2 text-sm text-gray-500 select-none">${MAX_MESSAGE_LENGTH}/${MAX_MESSAGE_LENGTH}</span>
        <div class="gap-y-1">
          <textarea
            id="qobolak-input"
            rows="2"
            class="px-3 py-2 w-full text-gray-700 rounded-lg border resize-none focus:outline-none"
            placeholder="Type your message here..."
            dir="auto"
          ></textarea>
          <button id="qobolak-send" class="px-4 py-2 mt-2 w-full text-white bg-blue-500 rounded-lg hover:bg-blue-600">
            Send
          </button>
        </div>
      </div>
    </div>
  `
}

// State
let chatHistory = []
let previousQuestion = null
let updateInterval = null

// Time Formatting Functions
function formatTimeAgo(timestamp) {
  const now = Date.now()
  const seconds = Math.floor((now - timestamp) / 1000)

  if (seconds < 60) return `${seconds} ${seconds === 1 ? 'second' : 'seconds'} ago`

  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes} ${minutes === 1 ? 'min' : 'mins'} ago`

  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`

  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} ${days === 1 ? 'day' : 'days'} ago`

  const weeks = Math.floor(days / 7)
  if (weeks < 4) return `${weeks} ${weeks === 1 ? 'week' : 'weeks'} ago`

  const months = Math.floor(days / 30)
  return `${months} ${months === 1 ? 'month' : 'months'} ago`
}

function startTimestampUpdates() {
  if (updateInterval) clearInterval(updateInterval)
  updateInterval = setInterval(() => {
    document.querySelectorAll('.message-time').forEach(element => {
      const timestamp = parseInt(element.dataset.timestamp)
      const timeAgo = formatTimeAgo(timestamp)
      if (element.textContent !== timeAgo) {
        element.textContent = timeAgo
      }
    })
  }, 1000)
}

// Scroll Functions
function checkScrollPosition() {
  const scrollButton = document.getElementById('scroll-to-bottom')
  const messagesDiv = document.getElementById('qobolak-messages')
  const scrollThreshold = 50

  const scrolledFromBottom =
    messagesDiv.scrollHeight - messagesDiv.scrollTop - messagesDiv.clientHeight

  if (scrolledFromBottom > scrollThreshold) {
    scrollButton.classList.remove('hidden')
    requestAnimationFrame(() => {
      scrollButton.style.opacity = '1'
    })
  } else {
    scrollButton.style.opacity = '0'
    setTimeout(() => {
      if (scrolledFromBottom <= scrollThreshold) {
        scrollButton.classList.add('hidden')
      }
    }, 300)
  }
}

function scrollToBottom() {
  const messagesDiv = document.getElementById('qobolak-messages')
  messagesDiv.scrollTo({
    top: messagesDiv.scrollHeight,
    behavior: 'smooth',
  })
  checkScrollPosition()
}

// Message Functions
function createMessageElement(sender, text, timestamp) {
  const isUser = sender === 'user'
  const isArabic = /[\u0600-\u06FF]/.test(text)
  const direction = isArabic ? 'rtl' : 'ltr'

  const messageDiv = document.createElement('div')
  messageDiv.classList = `mb-4 flex ${isUser ? 'justify-end' : 'justify-start'}`

  // Clean up text - remove extra whitespace while preserving line breaks
  const cleanText = text
    .split('\n')
    .map(line => line.trim())
    .filter(Boolean)
    .join('\n')

  messageDiv.innerHTML = `
    <div class="max-w-[80%] flex flex-col">
      <div class="${
        isUser ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-800'
      } px-4 py-2 rounded-t-2xl ${
    isUser ? 'rounded-bl-2xl' : 'rounded-br-2xl'
  } shadow-sm" style="direction:${direction};unicode-bidi:embed;white-space:pre-wrap;word-break:break-word">${cleanText}</div>
      <div class="${isUser ? 'text-right' : 'text-left'} text-xs text-gray-500 mt-1 px-1">
        <span>${isUser ? 'You' : 'QobolakAgent'}</span> •
        <span class="message-time" data-timestamp="${timestamp}">${formatTimeAgo(
    timestamp
  )}</span>
      </div>
    </div>`

  return messageDiv
}

async function sendMessage(message) {
  if (!message.trim()) return

  const messagesDiv = document.getElementById('qobolak-messages')

  // Display user message
  const userTimestamp = Date.now()
  const userMessageDiv = createMessageElement('user', message, userTimestamp)
  messagesDiv.appendChild(userMessageDiv)
  scrollToBottom()

  // Save user message
  chatHistory.push({
    sender: 'user',
    text: message,
    timestamp: userTimestamp,
  })
  saveMessagesToLocalStorage(chatHistory)

  // Show loading indicator
  const loadingDiv = document.createElement('div')
  loadingDiv.classList = 'text-left mb-2 text-gray-500'
  loadingDiv.textContent = 'Typing...'
  messagesDiv.appendChild(loadingDiv)
  scrollToBottom()

  try {
    const formData = new FormData()
    formData.append('action', 'qobolak_chat')
    formData.append('message', message)
    formData.append('security', qobolakAjax.nonce)
    formData.append('chatHistory', JSON.stringify(chatHistory))

    if (previousQuestion) {
      formData.append('previous_question', previousQuestion)
      formData.append('training_answer', message)
      formData.append('is_training', 'true')
    }

    const response = await fetch(qobolakAjax.url, { method: 'POST', body: formData })
    const data = await response.json()
    loadingDiv.remove()

    if (data.success) {
      const botTimestamp = Date.now()
      const botMessageDiv = createMessageElement('bot', data.data.response, botTimestamp)
      messagesDiv.appendChild(botMessageDiv)
      scrollToBottom()

      // Save bot message
      chatHistory.push({
        sender: 'bot',
        text: data.data.response,
        timestamp: botTimestamp,
      })
      saveMessagesToLocalStorage(chatHistory)

      previousQuestion = data.data.is_training ? data.data.previous_question : null
    } else {
      const errorDiv = createMessageElement(
        'bot',
        'Sorry, I encountered an error. Please try again.',
        Date.now()
      )
      messagesDiv.appendChild(errorDiv)
      scrollToBottom()
    }
  } catch (error) {
    console.error('Error:', error)
    const errorDiv = createMessageElement(
      'bot',
      'Sorry, I encountered an error. Please try again.',
      Date.now()
    )
    messagesDiv.appendChild(errorDiv)
    scrollToBottom()
  }
}

// Storage Functions
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
    return messages
      ? JSON.parse(messages).map(msg => ({
          ...msg,
          timestamp: msg.timestamp || Date.now(),
        }))
      : []
  } catch (error) {
    console.error('Failed to load messages:', error)
    return []
  }
}

// Initialize Chat
function initializeChat() {
  // Create UI
  createChatUI()

  // Get DOM elements
  const input = document.getElementById('qobolak-input')
  const sendButton = document.getElementById('qobolak-send')
  const messagesDiv = document.getElementById('qobolak-messages')
  const closeButton = document.getElementById('qobolak-close')
  const scrollToBottomButton = document.getElementById('scroll-to-bottom')

  // Load chat history
  chatHistory = loadMessagesFromLocalStorage()

  // Render existing messages
  messagesDiv.innerHTML = ''
  chatHistory.forEach(message => {
    const messageDiv = createMessageElement(
      message.sender,
      message.text,
      message.timestamp
    )
    messagesDiv.appendChild(messageDiv)
  })

  // Start timestamp updates
  startTimestampUpdates()

  // Initial scroll
  scrollToBottom()

  // Message input handlers
  input.addEventListener('input', () => {
    const messageLengthRemaining = MAX_MESSAGE_LENGTH - input.value.length
    document.getElementById(
      'qobolak-message-length'
    ).textContent = `${messageLengthRemaining}/${MAX_MESSAGE_LENGTH}`
  })

  input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      if (input.value.length > 0 && input.value.length <= MAX_MESSAGE_LENGTH) {
        sendMessage(input.value)
        input.value = ''
        document.getElementById(
          'qobolak-message-length'
        ).textContent = `${MAX_MESSAGE_LENGTH}/${MAX_MESSAGE_LENGTH}`
      }
    }
  })

  // Button handlers
  sendButton.addEventListener('click', () => {
    if (input.value.length > 0 && input.value.length <= MAX_MESSAGE_LENGTH) {
      sendMessage(input.value)
      input.value = ''
      document.getElementById(
        'qobolak-message-length'
      ).textContent = `${MAX_MESSAGE_LENGTH}/${MAX_MESSAGE_LENGTH}`
    }
  })

  scrollToBottomButton.addEventListener('click', scrollToBottom)

  closeButton.addEventListener('click', () => {
    document.getElementById('qobolak-chat').style.display = 'none'
    if (updateInterval) {
      clearInterval(updateInterval)
      updateInterval = null
    }
  })

  // Scroll handlers
  messagesDiv.addEventListener('scroll', checkScrollPosition)
}
