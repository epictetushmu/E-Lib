<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>Sign Up</h2>
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
            </div>
            <button type="submit" class="btn btn-primary">Sign Up</button>
        </form>
    </div>
    <script>
        document.getElementById('signupForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            axios.post('/api/signup.php', {
                username: username,
                email: email,
                password: password
            })
            .then(response => {
                if (response.data.status === 'success') {
                    alert('Signup successful! You can now log in.');
                    window.location.href = '/login.php';
                } else {
                    alert('Signup failed: ' + response.data.message);
                }
            })
            .catch(error => {
                console.error('There was an error signing up!', error);
            });
        });
    </script>
</body>
</html>
