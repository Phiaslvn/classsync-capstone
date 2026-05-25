<?php
// Include session configuration at the very beginning
require_once '../../config/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | IT Scheduling System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../public/assets/css/main.css">
  <style>
    body {
      font-family: 'Poppins', Arial, sans-serif;
      background: url('../../public/assets/img/image.png') no-repeat center center/cover;
      background-color: #f8f9fa;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 1rem;
      margin: 0;
    }
    .login-card { max-width: 400px; width:500px; margin: 100px auto 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(128,0,0,0.08); padding: 2rem; }
    .form-label { font-weight: 600; color: #800000; }
    .btn-maroon { background-color: #800000; color: #fff; border-radius: 50px; font-weight: 600; transition: background 0.3s, transform 0.2s; }
    .btn-maroon:hover, .btn-maroon:focus { background-color: #660000; color: #fff; transform: scale(1.05); }
    .footer { background-color: #800000; color: #fff; text-align: center; padding: 1rem 0; width: 100%; position: fixed; left: 0; bottom: 0; z-index: 1030; font-size: 1rem; letter-spacing: 0.5px; }
    .navbar { background-color: #800000 !important; }
    .navbar .navbar-brand { color: #fff !important; font-weight: 700; }
    .navbar .nav-link, .navbar .dropdown-item { color: #fff !important; }
    .navbar .dropdown-menu { background-color: #800000; border: none; }
    .navbar .dropdown-item:hover, .navbar .dropdown-item:focus { background-color: #660000; color: #fff; }
    @media (max-width: 575.98px) {
      .login-card { margin-top: 90px; padding: 1rem; }
      .footer { font-size: 0.9rem; padding: 0.5rem 0; }
    }
  </style>
</head>
<body>
  <!-- Fixed Top Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-maroon fixed-top shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center" href="../index.php">
        <img src="../../public/assets/img/evsu-logo.png" alt="EVSU Logo" width="32" height="32" class="me-2">
        <span>IT Scheduling System</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav align-items-lg-center">
          <li class="nav-item">
            <a href="../index.php" class="btn btn-maroon rounded-pill px-4 d-flex align-items-center gap-2">
              <span class="bi bi-arrow-left"></span> Back
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Login Form Section -->
  <div class="login-card">
    <h2 class="text-center text-maroon mb-4">Instructor Login</h2>
    
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    
    <form action="process_login.php" method="POST">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-maroon w-100 mb-2">Login</button>
      <div class="text-center">
        <a href="forgot_password.php" class="text-maroon fw-semibold text-decoration-none">Forgot password?</a>
      </div>
    </form>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p class="mb-0">&copy; 2025 IT Scheduling System. All rights reserved.</p>
    </div>
  </footer>

  <script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>