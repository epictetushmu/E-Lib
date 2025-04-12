<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="./styles/login.css">
</head>
<body>
    <div class="login-container">
        <h2 class="text-center">Login</h2>
        <div id="error-message" class="alert alert-danger d-none"></div>
        <form id="loginForm">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
            <div class="mt-3 text-center">
                <p>Don't have an account? <a href="/E-Lib/signup">Sign up</a></p>
            </div>
        </form>
        <div class="container mt-5 text-center">
            <h5>Or login with</h5>
            <p>Click the button below to login using CAS authentication.</p>
            <a href="https://auth.hmu.gr/cas/login?service=https://your-callback-url" class="btn btn-secondary">
                Login with CAS
            </a>
        </div>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorMessage = document.getElementById('error-message');

            // Reset error message
            errorMessage.classList.add('d-none');

            axios.post('/E-Lib/api/login', {
                email: email,
                password: password
            })
            .then(response => {
                if (response.data.status === 'success') {
                    window.location.href = '/E-Lib/';
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
</body>
</html>