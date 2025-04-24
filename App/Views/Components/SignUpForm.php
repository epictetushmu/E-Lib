
<div class="container mt-5">
    <div class="col-md-6 offset-md-3">
        <div class="popup-container p-4 border rounded shadow-sm bg-light position-relative">
            <!-- Close Button -->
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" 
                        onclick="closePopup('signupPopup')" aria-label="Close"></button>
            <h2 class="text-center mb-4">Sign Up</h2>
            <div id="error-message" class="alert alert-danger d-none"></div>
            <form id="signupForm">
                <div class="mb-3">
                    <label for="signup-username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="signup-username" required>
                </div>
                <div class="mb-3">
                    <label for="signup-email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="signup-email" required>
                </div>
                <div class="mb-3">
                    <label for="signup-password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="signup-password" required>
                    <div class="form-text">Password must be at least 8 characters long.</div>
                </div>
                <div class="mb-3">
                    <label for="signup-confirm-password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="signup-confirm-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                <div class="mt-3 text-center">
                    <p>Already have an account? <a href="#" onclick="closePopup('signupPopup'); openPopup('loginPopup');">Login</a></p>
                </div>
            </form>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.getElementById('signupForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const username = document.getElementById('signup-username').value;
            const email = document.getElementById('signup-email').value;
            const password = document.getElementById('signup-password').value;
            const confirmPassword = document.getElementById('signup-confirm-password').value;
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