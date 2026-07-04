<?php
// Run this ONCE in your browser (e.g. http://localhost/setup.php) to create
// your business account and first login. Delete this file afterward.

require_once __DIR__ . '/../src/config.php';

$done = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO accounts (business_name) VALUES (?)");
        $stmt->execute([$_POST['business_name']]);
        $accountId = $pdo->lastInsertId();

        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (account_id, name, email, password_hash, role) VALUES (?,?,?,?,'owner')");
        $stmt->execute([$accountId, $_POST['name'], $_POST['email'], $hash]);

        $pdo->commit();
        $done = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Setup — TourVolt</title>
<link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="min-h-screen font-sans bg-daylight dark:bg-savanna text-dayink dark:text-ivory">
<div class="max-w-sm mx-auto mt-16 px-6">
<?php if ($done): ?>
  <div class="card text-center">
    <div class="font-serif text-xl mb-2">All set</div>
    <p class="text-sm text-daymuted dark:text-grass mb-4">Your account is ready. Delete this setup.php file now, then log in.</p>
    <a href="/login.php" class="btn-primary w-full justify-center">Go to login</a>
  </div>
<?php else: ?>
  <div class="card">
    <div class="font-serif text-xl mb-4">Set up your account</div>
    <?php if ($error): ?><div class="badge-warning badge mb-4 block"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="space-y-3">
      <input class="input-field" name="business_name" placeholder="Business name" required>
      <input class="input-field" name="name" placeholder="Your name" required>
      <input class="input-field" type="email" name="email" placeholder="Your email" required>
      <input class="input-field" type="password" name="password" placeholder="Choose a password" required minlength="8">
      <button type="submit" class="btn-primary w-full justify-center">Create account</button>
    </form>
  </div>
<?php endif; ?>
</div>
</body></html>
