<?php
/**
 * Login Form Component
 * 
 * @param string $formAction Optional - login endpoint URL (default: '/api/v1/login')
 * @param string $casUrl Optional - CAS login URL
 */

$formAction = $formAction ?? '/api/v1/login';
$casUrl = $casUrl ?? 'https://auth.hmu.gr/cas/login?service=https://your-callback-url';
?>

<div class="container mt-5">
    <div class="col-md-6 offset-md-3">
        <div class="popup-container p-4 border rounded shadow-sm bg-light position-relative"">
            <!-- Close Button -->
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" 
                    onclick="closePopup('loginPopup')" aria-label="Close"></button>
            
            <h2 class="text-center mb-4">Login</h2>
            <div id="error-message" class="alert alert-danger d-none"></div>

            <form id="loginForm" method="POST" action="<?= htmlspecialchars($formAction) ?>">
                <?php if (function_exists('csrf_field')): ?>
                    <?= csrf_field() ?>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="login-email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="login-email" name="email" required>
                </div>

                <div class="mb-3">
                    <label for="login-password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="login-password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>

                <div class="mt-3 text-center">
                    <p>Don't have an account? <a href="#" onclick="closePopup('loginPopup'); openPopup('signupPopup');">Sign up</a></p>
                </div>
            </form>

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

<script>
document.getElementById('loginForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value.trim();
    const errorMessage = document.getElementById('error-message');

    errorMessage.classList.add('d-none');

    axios.post('<?= htmlspecialchars($formAction) ?>', {
        email: email,
        password: password
    })
    .then(response => {
        if (response.data.status === 'success') {
            window.location.href = '/';
        } else {
            errorMessage.textContent = response.data.message || 'Login failed. Please check your credentials.';
            errorMessage.classList.remove('d-none');
        }
    })
    .catch(error => {
        errorMessage.textContent = 'An error occurred while trying to log in. Please try again later.';
        errorMessage.classList.remove('d-none');
        console.error('Login error:', error);
    });
});
</script>
