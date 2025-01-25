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
    <div class="p-4 w-full bg-white rounded-lg shadow-lg min-w-[25rem] max-w-[25rem]">
      <div class="flex justify-between items-center">
        <div class="flex flex-col">
          <h2 class="text-lg font-semibold">Qobolak AI Assistant</h2>
          <small class="text-gray-500">How can I help you?</small>
        </div>
        <button id="qobolak-close" class="px-2 py-1 text-red-500">&times;</button>
      </div>
      <div id="qobolak-messages" class="overflow-y-auto relative mt-4 max-h-64"></div>
      <button id="scroll-to-bottom" title="Scroll to bottom" class="hidden fixed bottom-40 right-[20%] z-[9999] p-1.5 text-white bg-blue-500 rounded-full shadow-lg opacity-0 hover:bg-blue-600 transition-all duration-300 ease-in-out transform scale-75">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </button>
      <div class="flex flex-col mt-4">
        <div class="relative">
          <div class="absolute top-0 bottom-0 left-0 z-10 w-12 bg-gradient-to-r from-white via-white to-transparent pointer-events-none"></div>
          <div id="suggested-questions" class="flex overflow-x-auto gap-2 px-10 pb-1 scroll-smooth hide-scrollbar"></div>
          <div class="absolute top-0 right-0 bottom-0 z-10 w-12 bg-gradient-to-l from-white via-white to-transparent pointer-events-none"></div>
        </div>
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

  // Add styles for hiding scrollbar but keeping functionality
  const style = document.createElement('style')
  style.textContent = `
    .hide-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    .hide-scrollbar::-webkit-scrollbar {
      display: none;
    }
  `
  document.head.appendChild(style)

  // Load and display suggested questions
  loadSuggestedQuestions()
}

// Load suggested questions from WordPress options
async function loadSuggestedQuestions() {
  try {
    const formData = new FormData()
    formData.append('action', 'qobolak_get_suggested_questions')
    formData.append('security', qobolakAjax.nonce)

    const response = await fetch(qobolakAjax.url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    })

    if (!response.ok) {
      throw new Error('Network response was not ok')
    }

    const data = await response.json()

    if (data.success && Array.isArray(data.data.questions)) {
      const container = document.getElementById('suggested-questions')
      container.innerHTML = '' // Clear existing questions

      data.data.questions.forEach(question => {
        const button = document.createElement('button')
        button.className =
          'flex-shrink-0 px-3 py-1.5 text-sm text-blue-600 whitespace-nowrap bg-blue-50 rounded-full border border-blue-100 transition-colors hover:bg-blue-100'
        button.textContent = question
        button.addEventListener('click', () => {
          const input = document.getElementById('qobolak-input')
          input.value = question
          sendMessage(question)
          input.value = ''

          // Scroll button into view
          button.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center',
          })
        })
        container.appendChild(button)
      })

      // Add scroll buttons if content overflows
      if (container.scrollWidth > container.clientWidth) {
        const scrollLeft = document.createElement('button')
        scrollLeft.className =
          'absolute left-2 top-1/2 z-20 p-1.5 bg-white rounded-full shadow-md transition-colors -translate-y-1/2 hover:bg-gray-50'
        scrollLeft.innerHTML =
          '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>'
        scrollLeft.addEventListener('click', () => {
          container.scrollBy({ left: -150, behavior: 'smooth' })
        })
        container.parentElement.appendChild(scrollLeft)

        const scrollRight = document.createElement('button')
        scrollRight.className =
          'absolute right-2 top-1/2 z-20 p-1.5 bg-white rounded-full shadow-md transition-colors -translate-y-1/2 hover:bg-gray-50'
        scrollRight.innerHTML =
          '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>'
        scrollRight.addEventListener('click', () => {
          container.scrollBy({ left: 150, behavior: 'smooth' })
        })
        container.parentElement.appendChild(scrollRight)

        // Show/hide scroll buttons based on scroll position
        const updateScrollButtons = () => {
          const isAtStart = container.scrollLeft === 0
          const isAtEnd =
            container.scrollLeft + container.clientWidth >= container.scrollWidth - 1

          scrollLeft.style.opacity = isAtStart ? '0' : '1'
          scrollRight.style.opacity = isAtEnd ? '0' : '1'
          scrollLeft.style.pointerEvents = isAtStart ? 'none' : 'auto'
          scrollRight.style.pointerEvents = isAtEnd ? 'none' : 'auto'
        }

        container.addEventListener('scroll', updateScrollButtons)
        updateScrollButtons()
      }
    } else {
      console.error('Invalid response format:', data)
    }
  } catch (error) {
    console.error('Failed to load suggested questions:', error)
  }
}

// Function to convert URLs to clickable links
function convertUrlsToLinks(text) {
  return text.replace(
    /(https?:\/\/[^\s<]+|www\.[^\s<]+\.[^\s<]+|[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}\/?[^\s<]*)/gi,
    url => {
      const fullUrl = url.startsWith('http')
        ? url
        : url.startsWith('www.')
        ? 'http://' + url
        : 'http://' + url
      return `<a href="${fullUrl}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline break-words hover:text-blue-800">${url}</a>`
    }
  )
}

function createMessageElement(sender, text, timestamp) {
  const isUser = sender === 'user'
  const isArabic = /[\u0600-\u06FF]/.test(text)
  const direction = isArabic ? 'rtl' : 'ltr'

  const messageDiv = document.createElement('div')
  messageDiv.classList = `mb-4 flex ${isUser ? 'justify-end' : 'justify-start'}`

  // Clean up text - remove extra whitespace while preserving line breaks
  let cleanText = text
    .split('\n')
    .map(line => line.trim())
    .filter(Boolean)
    .join('\n')

  // Convert URLs to clickable links
  cleanText = convertUrlsToLinks(cleanText)

  messageDiv.innerHTML = `
    <div class="max-w-[80%] flex flex-col">
      <div class="${
        isUser ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-800'
      } px-4 py-2 rounded-t-2xl ${
    isUser ? 'rounded-bl-2xl' : 'rounded-br-2xl'
  } shadow-sm" style="direction:${direction};unicode-bidi:embed;white-space:pre-wrap;word-break:break-word">${cleanText}</div>
      <div class="${isUser ? 'text-right' : 'text-left'} text-xs text-gray-500 mt-1 px-1">
        <span>${isUser ? 'You' : 'QobolakAgent'}</span> •
        <span class="message-time" data-timestamp="${timestamp}" title="${
    new Date(timestamp).toISOString().split('T')[1].split('.')[0]
  }">
          ${formatTimeAgo(timestamp)}
        </span>
      </div>
    </div>`

  return messageDiv
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
        isTraining: data.data.is_training,
        fromTraining: data.data.from_training,
      })
      saveMessagesToLocalStorage(chatHistory)

      // Handle training mode
      if (data.data.is_training) {
        // If this is a training response, show a special input box
        const trainingDiv = document.createElement('div')
        trainingDiv.classList = 'flex flex-col gap-2 mt-4'
        trainingDiv.innerHTML = `
          <textarea
            class="px-3 py-2 w-full text-gray-700 rounded-lg border resize-none focus:outline-none"
            placeholder="Type your answer to teach me..."
            rows="2"
            dir="auto"
          ></textarea>
          <div class="flex gap-2">
            <button class="flex-1 px-4 py-2 text-white bg-green-500 rounded-lg hover:bg-green-600">
              Submit Answer
            </button>
            <button class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
              Skip
            </button>
          </div>
        `

        const submitBtn = trainingDiv.querySelector('button:first-child')
        const skipBtn = trainingDiv.querySelector('button:last-child')
        const textarea = trainingDiv.querySelector('textarea')

        submitBtn.addEventListener('click', async () => {
          const answer = textarea.value.trim()
          if (answer) {
            trainingDiv.remove()
            // Create a new FormData object specifically for training submission
            const trainingData = new FormData()
            trainingData.append('action', 'qobolak_chat')
            trainingData.append('security', qobolakAjax.nonce)
            trainingData.append('is_training', 'true')
            trainingData.append('previous_question', data.data.previous_question)
            trainingData.append('training_answer', answer)
            trainingData.append('message', '') // Add empty message to indicate this is an answer submission

            try {
              const response = await fetch(qobolakAjax.url, {
                method: 'POST',
                body: trainingData,
              })
              const result = await response.json()

              if (result.success) {
                const confirmTimestamp = Date.now()
                const confirmMessageDiv = createMessageElement(
                  'bot',
                  result.data.response,
                  confirmTimestamp
                )
                messagesDiv.appendChild(confirmMessageDiv)
                scrollToBottom()
                previousQuestion = null // Reset the training state
              }
            } catch (error) {
              console.error('Error submitting training answer:', error)
              const errorMessage = createMessageElement(
                'bot',
                'Sorry, there was an error saving your answer. Please try again.',
                Date.now()
              )
              messagesDiv.appendChild(errorMessage)
              scrollToBottom()
            }
          }
        })

        skipBtn.addEventListener('click', () => {
          trainingDiv.remove()
          previousQuestion = null
          const skipMessage = createMessageElement(
            'bot',
            'No problem! Feel free to ask me another question.',
            Date.now()
          )
          messagesDiv.appendChild(skipMessage)
          scrollToBottom()
        })

        messagesDiv.appendChild(trainingDiv)
        scrollToBottom()
        textarea.focus()
      } else if (data.data.training_complete) {
        // Training answer was successfully saved
        previousQuestion = null
      } else {
        previousQuestion = null
      }
    } else {
      const errorDiv = createMessageElement(
        'bot',
        data.data.message || 'Sorry, I encountered an error. Please try again.',
        Date.now()
      )
      messagesDiv.appendChild(errorDiv)
      scrollToBottom()
      previousQuestion = null
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
    previousQuestion = null
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
