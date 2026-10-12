<script setup lang="ts"></script>

<template>
  <div class="container docs-page">
    <h1>E-Lib Documentation</h1>

    <section>
      <h2>About the Application</h2>
      <p>
        E-Lib is a digital library application designed to store, manage, and serve electronic books (PDFs) to
        authorized users. The application provides a comprehensive set of features for both users and
        administrators to interact with the digital library content.
      </p>
      <p class="text-center">
        <a href="https://github.com/epictetushmu/E-Lib" class="btn btn-outline" target="_blank" rel="noopener">View on GitHub</a>
      </p>
    </section>

    <section>
      <h2>Main Features</h2>
      <div class="feature-grid">
        <div class="card feature-card">
          <h3>User Features</h3>
          <ul>
            <li>Search books by title, author, or category</li>
            <li>Preview book contents online</li>
            <li>Download books (when enabled)</li>
            <li>Save books to a personal reading list</li>
            <li>Rate and review books</li>
          </ul>
        </div>
        <div class="card feature-card">
          <h3>Admin Features</h3>
          <ul>
            <li>Add and manage books, including uploading several PDFs at once</li>
            <li>Edit book metadata</li>
            <li>Remove books from the library</li>
            <li>Control book download permissions</li>
            <li>View system logs</li>
          </ul>
        </div>
      </div>
    </section>

    <section>
      <h2>Technical Implementation</h2>

      <details open>
        <summary>Architecture</summary>
        <p>E-Lib follows an MVC (Model-View-Controller) architecture pattern, with a Vue 3 single-page frontend:</p>
        <ul>
          <li><strong>Models:</strong> Handle data manipulation and storage (Books, Users)</li>
          <li><strong>Controllers:</strong> Process incoming API requests and responses</li>
          <li><strong>Services:</strong> Contain business logic between controllers and models</li>
          <li><strong>Middleware:</strong> JWT authentication and request logging</li>
          <li><strong>Frontend:</strong> A Vue 3 + Vite SPA calling the JSON API, built to <code>public/dist/</code></li>
        </ul>
      </details>

      <details>
        <summary>Database</summary>
        <p>E-Lib uses MongoDB as its sole datastore:</p>
        <ul>
          <li>Connected via the MongoDB PHP extension, with TLS and MongoDB Atlas support</li>
          <li><strong>Books:</strong> title, author, description, categories, file paths</li>
          <li><strong>Users:</strong> accounts, saved/downloaded books, admin flag</li>
          <li><strong>Reviews:</strong> embedded within book documents</li>
        </ul>
      </details>

      <details>
        <summary>Authentication &amp; Security</summary>
        <p>E-Lib implements multiple authentication mechanisms:</p>
        <ul>
          <li><strong>JWT Authentication:</strong> Bearer tokens for all authenticated API calls, HMAC-SHA256 signed, 1-hour expiry</li>
          <li><strong>CAS Authentication:</strong> Single sign-on for institutional users, bridged into a JWT on successful ticket validation</li>
          <li><strong>Password Security:</strong> bcrypt via PHP's <code>password_hash()</code></li>
          <li><strong>Security Headers:</strong> CSP, X-Frame-Options, X-XSS-Protection, HSTS</li>
        </ul>
      </details>

      <details>
        <summary>File Management</summary>
        <p>Book PDFs and thumbnails are handled with:</p>
        <ul>
          <li>Extension-allowlist validation on upload (not MIME-only)</li>
          <li>Secure, randomized filenames to avoid path traversal and collisions</li>
          <li>Automatic thumbnail generation from the first page via ImageMagick/poppler-utils</li>
          <li>Per-book download permission control and download tracking</li>
        </ul>
      </details>

      <details>
        <summary>API Endpoints</summary>
        <table class="api-table">
          <thead>
            <tr>
              <th>Endpoint</th>
              <th>Method</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>/api/v1/books</td><td>GET</td><td>Get all books (admin)</td></tr>
            <tr><td>/api/v1/books</td><td>POST</td><td>Add a new book (admin)</td></tr>
            <tr><td>/api/v1/books/featured</td><td>GET</td><td>Get featured books</td></tr>
            <tr><td>/api/v1/books/list</td><td>GET</td><td>Get public books</td></tr>
            <tr><td>/api/v1/reviews/:id</td><td>GET</td><td>Get reviews for a book</td></tr>
            <tr><td>/api/v1/reviews</td><td>POST</td><td>Submit a book review</td></tr>
            <tr><td>/api/v1/save-book</td><td>POST</td><td>Save a book to reading list</td></tr>
            <tr><td>/api/v1/saved-books</td><td>GET</td><td>Get saved books</td></tr>
            <tr><td>/api/v1/login</td><td>POST</td><td>User login</td></tr>
            <tr><td>/api/v1/signup</td><td>POST</td><td>User registration</td></tr>
          </tbody>
        </table>
      </details>
    </section>

    <section>
      <h2>System Requirements</h2>
      <div class="feature-grid">
        <div class="card feature-card">
          <h3>Server</h3>
          <ul>
            <li>PHP 8.2 or higher</li>
            <li>MongoDB (Atlas or self-hosted)</li>
            <li>Apache or the PHP built-in server</li>
            <li>PHP extensions: mongodb, gd, imagick, fileinfo</li>
            <li>Node.js/npm to build the frontend</li>
          </ul>
        </div>
        <div class="card feature-card">
          <h3>Client</h3>
          <ul>
            <li>A modern browser (Chrome, Firefox, Safari, Edge)</li>
            <li>JavaScript enabled</li>
            <li>Cookies enabled (CAS bridge)</li>
          </ul>
        </div>
      </div>
    </section>

    <section>
      <h2>User Guides</h2>
      <div class="feature-grid">
        <div class="card feature-card">
          <h3>For Standard Users</h3>
          <ol>
            <li>Create an account via Sign Up</li>
            <li>Log in with email and password, or CAS</li>
            <li>Browse or search for books</li>
            <li>Use "Online Preview" to read in-browser</li>
            <li>Download (when available) or save to your reading list</li>
            <li>Rate and review books you've read</li>
          </ol>
        </div>
        <div class="card feature-card">
          <h3>For Administrators</h3>
          <ol>
            <li>Log in with an admin account</li>
            <li>Manage books from the Admin Dashboard</li>
            <li>Use Upload PDF for bulk PDF ingestion</li>
            <li>Toggle status/featured, edit metadata, or delete</li>
            <li>Monitor the system via Admin &gt; Logs</li>
          </ol>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped src="@/css/views/Docs.css"></style>
