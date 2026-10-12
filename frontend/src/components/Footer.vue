<script setup lang="ts">
const year = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="meander meander--on-dark"></div>
    <div class="container footer-grid">
      <div>
        <h4>Quick Links</h4>
        <ul>
          <li><RouterLink to="/">Home</RouterLink></li>
          <li><RouterLink to="/browse">Browse</RouterLink></li>
          <li><RouterLink to="/docs">Docs</RouterLink></li>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul class="plain">
          <li>Ρωμανού 3, Χαλέπα, 73133 Χανιά</li>
          <li><a href="mailto:info@epictetuslibrary.org">info@epictetuslibrary.org</a></li>
          <li><a href="https://ee.hmu.gr" target="_blank" rel="noopener">ee.hmu.gr</a></li>
        </ul>
      </div>
      <div>
        <h4>Follow Us</h4>
        <div class="social-links">
          <a href="https://www.linkedin.com/company/epictetus-hmu" target="_blank" rel="noopener" aria-label="LinkedIn">LinkedIn</a>
          <a href="https://www.instagram.com/ee.epictetus_/" target="_blank" rel="noopener" aria-label="Instagram">Instagram</a>
          <a href="https://github.com/epictetushmu" target="_blank" rel="noopener" aria-label="GitHub">GitHub</a>
        </div>
      </div>
    </div>
    <div class="container footer-bottom">
      <small>&copy; {{ year }} Epictetus Library. All rights reserved.</small>
      <small class="text-muted">
        A project of
        <a href="https://ee.hmu.gr" target="_blank" rel="noopener">Department of Electrical &amp; Computer Engineering, HMU</a>
      </small>
    </div>
  </footer>
</template>

<style scoped src="@/css/components/Footer.css"></style>
