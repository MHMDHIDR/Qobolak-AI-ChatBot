document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('qobolak-btn')

  button.addEventListener('click', () => {
    let name = prompt("What's your name?")
    while (!name) {
      name = prompt('Please enter your name:')
    }
    alert(`Hello, ${name}!`)
  })
})
