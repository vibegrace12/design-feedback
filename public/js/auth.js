// Authentication and form handling

class AuthManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupTabSwitching();
        this.setupFormHandlers();
        this.checkAuthStatus();
    }

    setupTabSwitching() {
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');

        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tabName = link.dataset.tab;

                // Deactivate all tabs
                tabLinks.forEach(l => l.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                // Activate selected tab
                link.classList.add('active');
                document.getElementById(tabName).classList.add('active');
            });
        });
    }

    setupFormHandlers() {
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');

        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }
    }

    handleLogin(e) {
        e.preventDefault();
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const errorDiv = document.getElementById('login-error');

        fetch('/api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem('user', JSON.stringify(data.user));
                    localStorage.setItem('token', data.token);
                    window.location.href = '/index.html';
                } else {
                    errorDiv.textContent = data.error || 'Login failed';
                }
            })
            .catch(err => {
                errorDiv.textContent = 'An error occurred. Please try again.';
                console.error(err);
            });
    }

    handleRegister(e) {
        e.preventDefault();
        const username = document.getElementById('register-username').value;
        const email = document.getElementById('register-email').value;
        const password = document.getElementById('register-password').value;
        const confirm = document.getElementById('register-confirm').value;
        const fullName = document.getElementById('register-full-name').value;
        const errorDiv = document.getElementById('register-error');

        if (password !== confirm) {
            errorDiv.textContent = 'Passwords do not match';
            return;
        }

        fetch('/api/auth.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username,
                email,
                password,
                full_name: fullName
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    errorDiv.style.color = 'green';
                    errorDiv.textContent = 'Registration successful! Redirecting to login...';
                    setTimeout(() => {
                        document.querySelector('[data-tab="login"]').click();
                    }, 2000);
                } else {
                    errorDiv.textContent = data.error || 'Registration failed';
                }
            })
            .catch(err => {
                errorDiv.textContent = 'An error occurred. Please try again.';
                console.error(err);
            });
    }

    checkAuthStatus() {
        fetch('/api/auth.php?action=verify')
            .then(res => res.json())
            .then(data => {
                if (data.authenticated) {
                    window.location.href = '/index.html';
                }
            })
            .catch(err => console.error(err));
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new AuthManager();
});