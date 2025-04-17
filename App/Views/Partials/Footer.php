<footer class="py-4 mt-auto bg-dark text-light">
    <div class="container">
        <div class="row">
            <!-- Quick Links -->
            <div class="col-md-4 mb-4">
                <h5 class="text-warning">Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="/" class="text-decoration-none link-light">Home</a></li>
                    <li><a href="/view-books" class="text-decoration-none link-light">Books</a></li>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <li><a href="/login" class="text-decoration-none link-light">Login</a></li>
                    <?php else: ?>
                        <li><a href="/profile" class="text-decoration-none link-light">My Profile</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-md-4 mb-4">
                <h5 class="text-warning">Contact</h5>
                <ul class="list-unstyled">
                    <li><i class="fas fa-map-marker-alt me-2"></i>123 Knowledge Street</li>
                    <li><i class="fas fa-envelope me-2"></i><a href="mailto:info@epictetuslibrary.org" class="text-decoration-none link-light">info@epictetuslibrary.org</a></li>
                    <li><i class="fas fa-phone me-2"></i><a href="tel:+15551234567" class="text-decoration-none link-light">(555) 123-4567</a></li>
                </ul>
            </div>

            <!-- Social Media -->
            <div class="col-md-4 mb-4">
                <h5 class="text-warning">Follow Us</h5>
                <div class="d-flex gap-3">
                    <a href="#" class="text-light" aria-label="Facebook"><i class="fab fa-facebook fa-2x"></i></a>
                    <a href="#" class="text-light" aria-label="Twitter"><i class="fab fa-twitter fa-2x"></i></a>
                    <a href="#" class="text-light" aria-label="Instagram"><i class="fab fa-instagram fa-2x"></i></a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <small class="d-block mb-0">&copy; <?= date('Y') ?> Epictetus Library. All rights reserved.</small>
        </div>
    </div>
</footer>
