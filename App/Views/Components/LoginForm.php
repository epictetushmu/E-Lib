<?php
/**
 * Login Form Component
 * 
 * @param string $formAction Optional - login endpoint URL (default: '/api/v1/login')
 * @param string $casUrl Optional - CAS login URL
 */

$formAction = $formAction ?? '/api/v1/login';
$casUrl = $casUrl ?? 'https://auth.hmu.gr/cas/login?service=https://your-callback-url';
$redirect = $_GET['redirect'] ?? '/'; // Default to home page if no redirect is provided
?>

<div class="container mt-5">
    <div class="col-md-6 offset-md-3">
        <div class="popup-container p-4 border rounded shadow-sm bg-light position-relative">
            <!-- Close Button -->
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" 
                    onclick="closePopup('loginPopup')" aria-label="Close"></button>
            
            <h2 class="text-center mb-4">Login</h2>
            <div id="error-message" class="alert alert-danger d-none"></div>

            <form id="loginForm">
                <?php if (function_exists('csrf_field')): ?>
                    <?= csrf_field() ?>
                <?php endif; 
             
                    // Capture redirect from URL parameters or use current path as fallback
                    $redirect = $_GET['redirect'] ?? $_SERVER['REQUEST_URI'];
                    if ($redirect === '/login' || $redirect === '/signup') {
                        $redirect = '/'; // Prevent redirect loops
                    }
                ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="mb-3">
                    <label for="login-email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="login-email" name="email" required>
                </div>

                <div class="mb-3">
                    <label for="login-password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="login-password" name="password" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember-me" name="remember">
                    <label for="remember-me" class="form-check-label">Remember me</label>
                </div>
                <button type="button" id="login-submit" class="btn btn-primary w-100">Login</button>
            </form>

            <div class="mt-3 text-center">
                <p>Don't have an account? <a href="#" onclick="closePopup('loginPopup'); openPopup('signupPopup');">Sign up</a></p>
            </div>

            <div class="mt-4 text-center">
                <h5>Or login with</h5>
                <p class="mb-2">Use CAS authentication below:</p>
                <a href="<?= htmlspecialchars($casUrl) ?>" class="btn btn-secondary">
                    Login with CAS
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    console.log("DOM loaded - Setting up event listeners");
    
    // Get reference to login form and submit button
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('login-submit');
    
    if (!loginButton) {
        console.error("Login button not found!");
        return;
    }
    
    // Add click event listener to the button
    loginButton.addEventListener('click', function(e) {
        console.log("Login button clicked!");
        handleLogin();
    });
    
    // Also prevent form submission as a backup measure
    loginForm.addEventListener('submit', function(e) {
        console.log("Form submit prevented");
        e.preventDefault();
        return false;
    });

    // Separated function for login handling
    function handleLogin() {
        console.log("handleLogin function called");
        
        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value.trim();
        const redirect = document.querySelector('input[name="redirect"]').value;
        const errorMessage = document.getElementById('error-message');
        const rememberMe = document.getElementById('remember-me').checked;

        console.log("Collected form data, preparing to send");
        errorMessage.classList.add('d-none');

        axios.post('/api/v1/login', {
            email: email,
            password: password
        })
        .then(response => {
            console.log("Login response received:", response);
            response = response.data;
            if (response.status === 'success') {
                console.log("Login successful");
                if (rememberMe) {
                    localStorage.setItem('authToken', response.data.token);
                } else {
                    sessionStorage.setItem('authToken', response.data.token);
                }

                console.log("Redirecting to:", redirect);
                const redirectUrl = redirect ? new URL(redirect, window.location.origin) : new URL('/', window.location.origin);
                window.location.href = redirectUrl.href;
            } else {
                console.error("Login failed:", response);
                errorMessage.textContent = response.data.message || 'Login failed. Please check your credentials.';
                errorMessage.classList.remove('d-none');
            }
        })
        .catch(error => {
            console.error("Login error:", error);
            errorMessage.textContent = 'An error occurred while trying to log in. Please try again later.';
            errorMessage.classList.remove('d-none');
        });
    }
});

</script>
