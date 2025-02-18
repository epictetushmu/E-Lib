<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .login-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center">Login</h2>
        <form id="loginForm">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="container mt-5 text-center">
        <h5>Or-with-Cas</h5>
        <p>Click the button below to login using CAS authentication.</p>
        <a href="https://auth.hmu.gr/cas/login?service=https:%2F%2Feclass.hmu.gr%2Fmodules%2Fauth%2Faltsearch.php%3Fauth%3D7%26is_submit%3Dtrue" class="btn btn-primary">
            Login with CAS
        </a>
    </div>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            axios.post('/E-Lib/api/login.php', {
                username: username,
                password: password
            })
            .then(response => {
                if (response.data.status === 'success') {
                    alert('Login successful!');
                    window.location.href = '/E-Lib/';
                } else {
                    alert('Login failed: ' + response.data.message);
                }
            })
            .catch(error => {
                console.error('There was an error logging in!', error);
            });
        });
    </script>
</body>
</html>