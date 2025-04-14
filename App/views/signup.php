<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="../../public/styles/userForm.css">
</head>
<body>
    <div class="signup-container">
        <h2 class="text-center mb-4">Sign Up</h2>
        <div id="error-message" class="alert alert-danger d-none"></div>
        <form id="signupForm">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" required>
                <div class="form-text">Password must be at least 8 characters long.</div>
            </div>
            <div class="mb-3">
                <label for="confirm-password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm-password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign Up</button>
            <div class="mt-3 text-center">
                <p>Already have an account? <a href="/login">Login</a></p>
            </div>
        </form>
    </div>
    <script>
        document.getElementById('signupForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const errorMessage = document.getElementById('error-message');

            // Reset error message
            errorMessage.classList.add('d-none');

            // Validate password match
            if (password !== confirmPassword) {
                errorMessage.textContent = 'Passwords do not match!';
                errorMessage.classList.remove('d-none');
                return;
            }

            // Validate password length
            if (password.length < 8) {
                errorMessage.textContent = 'Password must be at least 8 characters long!';
                errorMessage.classList.remove('d-none');
                return;
            }

            axios.post('/api/v1/signup', {
                username: username,
                email: email,
                password: password
            })
            .then(response => {
                if (response.data.status === 'success') {
                    alert('Signup successful! You can now log in.');
                    window.location.href = '/login';
                } else {
                    errorMessage.textContent = response.data.message || 'Signup failed!';
                    errorMessage.classList.remove('d-none');
                }
            })
            .catch(error => {
                errorMessage.textContent = 'An error occurred. Please try again later.';
                errorMessage.classList.remove('d-none');
                console.error('Signup error:', error);
            });
        });
    </script>
</body>
</html>
