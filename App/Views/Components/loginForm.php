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
        <div class="login-container p-4 border rounded shadow-sm bg-light">
            <h2 class="text-center mb-4">Login</h2>
            <div id="error-message" class="alert alert-danger d-none"></div>

            <form id="loginForm" method="POST" action="<?= htmlspecialchars($formAction) ?>">
                <?php if (function_exists('csrf_field')): ?>
                    <?= csrf_field() ?>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>

                <div class="mt-3 text-center">
                    <p>Don't have an account? <a href="/signup">Sign up</a></p>
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

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
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
