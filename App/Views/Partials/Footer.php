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
                    <li><i class="fas fa-map-marker-alt me-2"></i>Ρωμανού 3, Χαλέπα, 73133 Χανιά</li>
                    <li><i class="fas fa-envelope me-2"></i><a href="mailto:info@epictetuslibrary.org" class="text-decoration-none link-light">info@epictetuslibrary.org</a></li>
                    <li class="mt-2"><i class="fas fa-university me-2"></i><a href="https://ee.hmu.gr" target="_blank" class="text-decoration-none link-light">ee.hmu.gr</a></li>
                </ul>
            </div>

            <!-- Social Media -->
            <div class="col-md-4 mb-4">
                <h5 class="text-warning">Follow Us</h5>
                <div class="d-flex gap-3">
                    <a href="https://www.linkedin.com/company/epictetus-hmu" class="text-light" aria-label="LinkedIn"><i class="fab fa-linkedin fa-2x"></i></a>
                    <a href="https://www.instagram.com/ee.epictetus_/" class="text-light" aria-label="Instagram"><i class="fab fa-instagram fa-2x"></i></a>
                    <a href="https://github.com/epictetushmu" class="text-light" aria-label="Github"><i class="fab fa-github fa-2x"></i></a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <small class="d-block mb-0">&copy; <?= date('Y') ?> Epictetus Library. All rights reserved.</small>
            <small class="d-block text-muted mt-1">A project of <a href="https://ee.hmu.gr" target="_blank" class="text-decoration-none link-light">Department of Electrical & Computer Engineering, HMU</a></small>
        </div>
    </div>
</footer>

<?php include_once($_SERVER['DOCUMENT_ROOT'] . '/App/Views/Components/SupportModal.php'); ?>
