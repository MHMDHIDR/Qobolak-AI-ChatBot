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
    chatBox.classList.add(
      'fixed',
      'bottom-4',
      'right-4',
      'hidden',
      'z-50',
      'transform',
      'scale-95',
      'opacity-0',
      'transition-all',
      'duration-300',
      'ease-in-out'
    )
    document.body.appendChild(chatBox)
  }

  // Function to open chat
  const openChat = () => {
    chatBox.classList.remove('hidden')
    // Force a reflow
    void chatBox.offsetWidth
    chatBox.classList.remove('scale-95', 'opacity-0')
    chatBox.classList.add('scale-100', 'opacity-100')

    if (!chatBox.hasChildNodes()) {
      initializeChat()
    }
  }

  // Function to close chat
  const closeChat = () => {
    chatBox.classList.remove('scale-100', 'opacity-100')
    chatBox.classList.add('scale-95', 'opacity-0')

    setTimeout(() => {
      chatBox.classList.add('hidden')
    }, 300)

    if (updateInterval) {
      clearInterval(updateInterval)
      updateInterval = null
    }
  }

  // Initialize chat when button is clicked
  button.addEventListener('click', () => {
    if (chatBox.classList.contains('hidden')) {
      openChat()
    } else {
      closeChat()
    }
  })

  // Add ESC key handler
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !chatBox.classList.contains('hidden')) {
      closeChat()
    }
  })

  // Add close button handler
  document.addEventListener('click', event => {
    if (event.target.id === 'qobolak-close') {
      closeChat()
    }
  })
})

// Chat UI Template
function createChatUI() {
  const chatBox = document.getElementById('qobolak-chat')
  chatBox.innerHTML = `
    <div id="qobolak-inner" class="p-4 w-full bg-white rounded-lg shadow-lg min-w-[25rem] max-w-[25rem] transition-all duration-300 ease-in-out">
      <div class="flex justify-between items-center pb-2 mb-4 border-b">
        <h3 class="text-lg font-semibold">Chat with Qobolak</h3>
        <button id="qobolak-close" class="text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <div id="qobolak-messages" class="overflow-y-auto relative mt-4 max-h-64 min-h-20"></div>
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
          'flex-shrink-0 px-3 py-1.5 text-sm text-blue-600 whitespace-nowrap bg-blue-50 rounded-full border border-blue-100 shadow-sm transition-colors hover:shadow:md'
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

  // Only create message content if there's text
  if (text) {
    const messageContent = document.createElement('div')
    messageContent.className = 'max-w-[80%] flex flex-col'

    // Clean up text - remove extra whitespace while preserving line breaks
    let cleanText = text
      .split('\n')
      .map(line => line.trim())
      .filter(Boolean)
      .join('\n')

    // Convert URLs to clickable links
    cleanText = convertUrlsToLinks(cleanText)

    messageContent.innerHTML = `
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
      </div>`

    messageDiv.appendChild(messageContent)
  }

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

// Move these outside of any function
const meetingKeywords = [
  'appointment',
  'schedule',
  'meeting',
  'meet',
  'book',
  'consultation',
]

function shouldOfferMeeting(message) {
  return meetingKeywords.some(keyword =>
    message.toLowerCase().includes(keyword.toLowerCase())
  )
}

// Modify the existing sendMessage function to include the meeting check
async function sendMessage(message) {
  if (!message.trim()) return

  // Check for meeting keywords before proceeding
  if (shouldOfferMeeting(message)) {
    handleMeetingRequest(document.getElementById('qobolak-messages'))
    return
  }

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
    if (updateInterval) {
      clearInterval(updateInterval)
      updateInterval = null
    }
  })

  // Scroll handlers
  messagesDiv.addEventListener('scroll', checkScrollPosition)
}

// Add these functions after the existing code

function handleMeetingRequest(messagesDiv) {
  // Create message first
  const messageDiv = createMessageElement(
    'bot',
    'Would you like to schedule a meeting?',
    Date.now()
  )
  messagesDiv.appendChild(messageDiv)

  // Create options div separately
  const optionsDiv = document.createElement('div')
  optionsDiv.className = 'flex gap-2 mt-4 mb-4' // Added margin-top for spacing

  const yesBtn = document.createElement('button')
  yesBtn.className = 'px-4 py-2 text-white bg-green-500 rounded hover:bg-green-600'
  yesBtn.textContent = 'Yes'

  const noBtn = document.createElement('button')
  noBtn.className = 'px-4 py-2 text-white bg-gray-500 rounded hover:bg-gray-600'
  noBtn.textContent = 'No'

  optionsDiv.appendChild(yesBtn)
  optionsDiv.appendChild(noBtn)

  // Add options as a new message
  const optionsMessageDiv = createMessageElement('bot', '', Date.now())
  optionsMessageDiv.appendChild(optionsDiv)
  messagesDiv.appendChild(optionsMessageDiv)
  scrollToBottom()

  yesBtn.addEventListener('click', () => showDurationOptions(messagesDiv))
  noBtn.addEventListener('click', () => {
    const response = createMessageElement(
      'bot',
      'Okay, what else can I help you with?',
      Date.now()
    )
    messagesDiv.appendChild(response)
    scrollToBottom()
  })
}

function showDurationOptions(messagesDiv) {
  // Create message first
  const messageDiv = createMessageElement(
    'bot',
    'Please select a meeting duration:',
    Date.now()
  )
  messagesDiv.appendChild(messageDiv)

  // Create duration options separately
  const durationDiv = document.createElement('div')
  durationDiv.className = 'flex flex-col gap-2 mt-4 mb-4' // Added margin-top for spacing

  // Create a new message div for the options
  const optionsMessageDiv = createMessageElement('bot', '', Date.now())
  optionsMessageDiv.appendChild(durationDiv)
  messagesDiv.appendChild(optionsMessageDiv)

  // Get calendar settings from WordPress
  const formData = new FormData()
  formData.append('action', 'qobolak_get_calendar_settings')
  formData.append('security', qobolakAjax.nonce)

  fetch(qobolakAjax.url, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData,
  })
    .then(response => response.json())
    .then(data => {
      if (data.success && data.data.settings) {
        Object.entries(data.data.settings).forEach(([duration, settings]) => {
          if (settings.enabled) {
            const button = document.createElement('button')
            button.className =
              'px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600'
            button.textContent = settings.title
            button.addEventListener('click', () =>
              embedCalendar(messagesDiv, settings.cal_link, duration)
            )
            durationDiv.appendChild(button)
          }
        })
        scrollToBottom()
      }
    })
}

function expandChatBox() {
  const chatBox = document.getElementById('qobolak-chat')
  const innerBox = document.getElementById('qobolak-inner')
  const messagesDiv = document.getElementById('qobolak-messages')

  // Add transition classes if not already present
  chatBox.classList.add('transition-all', 'duration-300', 'ease-in-out')
  innerBox.classList.add('transition-all', 'duration-300', 'ease-in-out')

  // Remove small size classes
  innerBox.classList.remove('min-w-[25rem]', 'max-w-[25rem]')

  // Add expanded size classes
  innerBox.classList.add('min-w-[50rem]', 'max-w-[50rem]')

  // Increase messages container height
  messagesDiv.classList.remove('max-h-64')
  messagesDiv.classList.add('max-h-[370px]')

  // Force a reflow to ensure the transition works
  void chatBox.offsetWidth
}

function embedCalendar(messagesDiv, calLink, duration) {
  // Expand chat box first
  expandChatBox()

  const calendarDiv = document.createElement('div')
  calendarDiv.style = 'width:100%;height:600px;overflow:scroll' // Increased height
  calendarDiv.id = `cal-inline-${Date.now()}`

  const messageDiv = createMessageElement('bot', '', Date.now())
  messageDiv.appendChild(calendarDiv)
  messagesDiv.appendChild(messageDiv)
  scrollToBottom()

  // Initialize Cal.com with the proper initialization code
  ;(function (C, A, L) {
    let p = function (a, ar) {
      a.q.push(ar)
    }
    let d = C.document
    C.Cal =
      C.Cal ||
      function () {
        let cal = C.Cal
        let ar = arguments
        if (!cal.loaded) {
          cal.ns = {}
          cal.q = cal.q || []
          d.head.appendChild(d.createElement('script')).src = A
          cal.loaded = true
        }
        if (ar[0] === L) {
          const api = function () {
            p(api, arguments)
          }
          const namespace = ar[1]
          api.q = api.q || []
          if (typeof namespace === 'string') {
            cal.ns[namespace] = cal.ns[namespace] || api
            p(cal.ns[namespace], ar)
            p(cal, ['initNamespace', namespace])
          } else {
            p(cal, ar)
          }
          return
        }
        p(cal, ar)
      }
  })(window, 'https://app.cal.com/embed/embed.js', 'init')

  // Initialize with the specific duration
  Cal('init', duration, { origin: 'https://cal.com' })

  // Configure the inline calendar
  Cal.ns[duration]('inline', {
    elementOrSelector: `#${calendarDiv.id}`,
    config: {
      layout: 'month_view',
    },
    calLink: calLink,
  })

  // Add UI customization
  Cal.ns[duration]('ui', {
    cssVarsPerTheme: {
      light: {
        'cal-brand': '#6366f1', // Indigo color to match our theme
      },
      dark: {
        'cal-brand': '#818cf8',
      },
    },
    hideEventTypeDetails: false,
    layout: 'month_view',
  })
}
