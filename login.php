<?php
require_once __DIR__ . '/includes/auth.php';
start_session();

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (attempt_login($email, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = "That email or password doesn't match our records.";
}

$pageTitle = 'Log in — TourVolt';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500&family=Inter:wght@400;500&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
<script>
  if (localStorage.getItem('theme') === 'dark' ||
      (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
  }
</script>
<style>
  :root {
    --bg: #FBF8F1;
    --card: #FFFFFF;
    --border: #E4DFD1;
    --ink: #1F2117;
    --muted: #79765F;
    --accent: #9A4B2E;
    --accent-hover: #7f3d25;
    --accent-on: #F0EAD8;
  }
  html.dark {
    --bg: #14170F;
    --card: #1D2117;
    --border: #3A3F30;
    --ink: #F0EAD8;
    --muted: #8A8A73;
    --accent: #C9A15A;
    --accent-hover: #DBB877;
    --accent-on: #14170F;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    transition: background 0.2s, color 0.2s;
  }
  .wrap { max-width: 1120px; margin: 0 auto; padding: 40px 24px; }
  .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
  .brand { display: flex; align-items: center; gap: 8px; }
  .brand-badge {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--accent); display: flex; align-items: center; justify-content: center;
  }
  .brand-badge i { color: var(--accent-on); font-size: 15px; }
  .brand-name { font-family: 'Fraunces', serif; font-size: 18px; font-weight: 500; }
  .toggle-btn {
    background: transparent; border: 1px solid var(--border); border-radius: 10px;
    width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--ink);
  }
  .grid { display: grid; grid-template-columns: 1fr; gap: 28px; align-items: stretch; }
  @media (min-width: 768px) { .grid { grid-template-columns: 1fr 1fr; } }

  .hero {
    position: relative; border-radius: 18px; overflow: hidden; min-height: 540px;
    background: var(--card); border: 1px solid var(--border);
  }
  .hero img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .hero::after {
    content: ""; position: absolute; inset: 0;
    background: linear-gradient(180deg, rgba(20,23,15,0.05) 45%, rgba(20,23,15,0.90) 100%);
  }
  .hero-text { position: absolute; bottom: 0; left: 0; padding: 32px; z-index: 2; }
  .hero-eyebrow { font-size: 12px; letter-spacing: 0.12em; text-transform: uppercase; color: #C9A15A; margin-bottom: 10px; }
  .hero-title { font-family: 'Fraunces', serif; font-size: 30px; color: #F0EAD8; line-height: 1.25; margin-bottom: 10px; }
  .hero-sub { font-size: 14px; color: #A9A78F; line-height: 1.55; max-width: 360px; }

  .panel {
    background: var(--card); border: 1px solid var(--border); border-radius: 18px;
    padding: 48px; min-height: 540px; display: flex; flex-direction: column; justify-content: center;
  }
  .panel-title { font-family: 'Fraunces', serif; font-size: 20px; text-align: center; margin-bottom: 4px; }
  .panel-sub { font-size: 13px; color: var(--muted); text-align: center; margin-bottom: 24px; }

  .field { margin-bottom: 16px; }
  .field label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; }
  .field input {
    width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 10px;
    padding: 10px 12px; font-size: 14px; color: var(--ink); font-family: inherit;
  }
  .field input:focus { outline: none; border-color: var(--accent); }
  .field input::placeholder { color: var(--muted); }

  .submit {
    width: 100%; background: var(--accent); color: var(--accent-on);
    border: none; border-radius: 10px; padding: 12px; font-size: 14px; font-weight: 500;
    display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;
    font-family: inherit;
  }
  .submit:hover { background: var(--accent-hover); }

  .error {
    background: rgba(154,75,46,0.12); color: var(--accent); font-size: 13px;
    text-align: center; padding: 8px 12px; border-radius: 8px; margin-bottom: 16px;
  }
  .footnote { text-align: center; font-size: 12px; color: var(--muted); margin-top: 24px; }
</style>
</head>
<body>

<div class="wrap">

  <div class="topbar">
    <div class="brand">
      <div class="brand-badge"><i class="ti ti-compass" aria-hidden="true"></i></div>
      <span class="brand-name">TourVolt</span>
    </div>
    <button id="theme-toggle" class="toggle-btn" aria-label="Toggle dark mode">
      <i class="ti ti-moon dark-hide" aria-hidden="true"></i>
      <i class="ti ti-sun dark-show" aria-hidden="true" style="display:none;"></i>
    </button>
  </div>

  <div class="grid">

    <div class="hero">
      <img src="assets/images/tour-dashboard.png" alt="Safari landscape">
      <div class="hero-text">
        <div class="hero-eyebrow">Tourvolt Adventure</div>
        <div class="hero-title">Every trip, every shilling, in one place.</div>
        <div class="hero-sub">Replace the notebook. See what's owed, what's booked, and what each safari actually earned.</div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Welcome back</div>
      <div class="panel-sub">Log in to see today's trips and balances.</div>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="you@business.com" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="submit"><i class="ti ti-login" aria-hidden="true"></i>Log in</button>
      </form>

      <div class="footnote">A private system for Tourvolt Adventure</div>
    </div>

  </div>
</div>

<script>
  const toggle = document.getElementById('theme-toggle');
  const moon = document.querySelector('.dark-hide');
  const sun = document.querySelector('.dark-show');
  function syncIcons() {
    const isDark = document.documentElement.classList.contains('dark');
    moon.style.display = isDark ? 'none' : 'inline';
    sun.style.display = isDark ? 'inline' : 'none';
  }
  syncIcons();
  toggle.addEventListener('click', function () {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    syncIcons();
  });
</script>
</body>
</html>