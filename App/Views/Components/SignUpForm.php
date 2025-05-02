<div class="container mt-5">
    <div class="login-container">
        <h2 class="text-center mb-3">Create an Account</h2>
        <div id="signup-error-message" class="alert alert-danger d-none"></div>
        
        <form id="signupForm" action="/api/v1/signup" method="post">
            <div class="form-group mb-2">
                <label for="name" class="form-label">Username</label>
                <input type="text" class="form-control" id="name" name="name" required autocomplete="name">
            </div>
            <div class="form-group mb-2">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
            </div>
            <div class="form-group mb-2">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" minlength="8" required autocomplete="new-password">
                <small class="form-text text-muted">Min 8 chars with number and special char</small>
            </div>
            <div class="form-group mb-2">
                <label for="confirm-password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm-password" name="confirm_password" required autocomplete="new-password">
            </div>
            <div class="form-group mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                    <label for="terms" class="form-check-label small">I agree to the <a href="/terms" target="_blank">Terms of Service</a></label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign Up</button>
            <div class="mt-2 text-center">
                <small>Already have an account? <a href="#" onclick="closePopup('signupPopup'); openPopup('loginPopup');">Login</a></small>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const signupForm = document.getElementById('signupForm');
        const errorMessage = document.getElementById('signup-error-message');
        
        if (!signupForm || !errorMessage) {
            console.error("Signup form or error message element not found!");
            return;
        }
        
        // Add event listener to the form for submission
        signupForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent traditional form submission
            handleSignup();
        });
        
        function handleSignup() {
            console.log("Processing signup");
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const termsChecked = document.getElementById('terms').checked;
            
            // Reset error message
            errorMessage.classList.add('d-none');
            
            // Basic form validation
            if (!name || !email || !password || !confirmPassword) {
                errorMessage.textContent = 'All fields are required!';
                errorMessage.classList.remove('d-none');
                return;
            }
            
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
            
            // Check for at least one number
            if (!/\d/.test(password)) {
                errorMessage.textContent = 'Password must contain at least one number!';
                errorMessage.classList.remove('d-none');
                return;
            }
            
            // Check for at least one special character
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                errorMessage.textContent = 'Password must contain at least one special character!';
                errorMessage.classList.remove('d-none');
                return;
            }
            
            // Terms agreement check
            if (!termsChecked) {
                errorMessage.textContent = 'You must agree to the Terms of Service!';
                errorMessage.classList.remove('d-none');
                return;
            }
            
            axios.post('/api/v1/signup', {
                username: name,
                email: email,
                password: password
            })
            .then(response => {
                console.log("Signup response received:", response);
                
                if (response.data.status === 'success') {
                    console.log("Signup successful");
                    
                    // Show success message
                    errorMessage.textContent = 'Account created successfully! Redirecting to login...';
                    errorMessage.classList.remove('d-none');
                    errorMessage.classList.remove('alert-danger');
                    errorMessage.classList.add('alert-success');
                    
                    // Redirect to login page after a short delay
                    setTimeout(() => {
                        window.location.href = '/?showLogin=1&redirect=';
                    }, 1500);
                } else {
                    // This handles the error case
                    console.error("Signup failed:", response);
                    errorMessage.textContent = response.message || 'Signup failed. Please check your information.';
                    errorMessage.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error("Signup error:", error);
                errorMessage.textContent = 'An error occurred while trying to create your account. Please try again later.';
                errorMessage.classList.remove('d-none');
            });
        }
    });
</script>