/**
 * Sends a POST request with JSON credentials to the given API path.
 * Used by both login and register so the fetch logic is written once.
 */
async function sendCredentials(url, username, password) {
    var response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: username, password: password })
    });

    var data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || 'Request failed');
    }

    return data;
}

/**
 * Shows a text message in the status paragraph.
 * Used after every submit to tell the user what happened.
 */
function showMessage(text) {
    document.getElementById('message').textContent = text;
}

/**
 * Handles the login form submission.
 * On success, redirects to the puzzle page.
 * On failure, shows the server error message.
 */
function handleLogin(event) {
    event.preventDefault();

    var username = document.getElementById('login-username').value.trim();
    var password = document.getElementById('login-password').value;

    sendCredentials('/api/login.php', username, password)
        .then(function () {
            showMessage('Logging in...');
            window.location.href = 'index.html';
        })
        .catch(function (error) {
            showMessage(error.message);
        });
}

/**
 * Handles the register form submission.
 * On success, shows a confirmation and switches to the login form.
 * On failure, shows the server error message.
 */
function handleRegister(event) {
    event.preventDefault();

    var username = document.getElementById('reg-username').value.trim();
    var password = document.getElementById('reg-password').value;

    sendCredentials('/api/register.php', username, password)
        .then(function () {
            showMessage('Account created. You can now log in.');
            toggleForms();
        })
        .catch(function (error) {
            showMessage(error.message);
        });
}

/**
 * Switches between the login and register forms.
 * Only one form is visible at a time. The link text also changes
 * so the user always knows which form they are looking at.
 */
function toggleForms() {
    var loginForm = document.getElementById('login-form');
    var registerForm = document.getElementById('register-form');
    var toggleLink = document.getElementById('toggle-link');

    if (loginForm.hidden) {
        loginForm.hidden = false;
        registerForm.hidden = true;
        toggleLink.textContent = 'Register here';
    } else {
        loginForm.hidden = true;
        registerForm.hidden = false;
        toggleLink.textContent = 'Back to login';
    }

    showMessage('');
}

/**
 * Attaches all event listeners when the page loads.
 * Each form gets a submit handler. The toggle link switches forms.
 */
function listenForAuthEvents() {
    document.getElementById('login-form').addEventListener('submit', handleLogin);
    document.getElementById('register-form').addEventListener('submit', handleRegister);

    document.getElementById('toggle-link').addEventListener('click', function (event) {
        event.preventDefault();
        toggleForms();
    });
}

listenForAuthEvents();
