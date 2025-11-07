<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();
$pdo  = Database::pdo();

// Get user roles
$roleStmt = $pdo->prepare("
    SELECT r.slug 
    FROM roles r
    JOIN user_roles ur ON ur.role_id = r.id
    WHERE ur.user_id = ?
");
$roleStmt->execute([$user->id]);
$roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$user->id]);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate into buckets
$studentNotices = [];
$adminNotices   = [];
foreach ($all as $n) {
    if (str_starts_with($n['message'], 'You registered for') ||
        str_starts_with($n['message'], 'You submitted feedback for') ||
        str_contains($n['message'], 'has been cancelled')) {
        $studentNotices[] = $n;
    }
    if (str_contains($n['message'], 'registered for your event') ||
        str_contains($n['message'], 'submitted feedback for your event') ||
        str_starts_with($n['message'], 'Your event') ||
        str_starts_with($n['message'], 'You created event') ||
        str_contains($n['message'], 'has been deleted') ||
        str_contains($n['message'], 'has been cancelled')) {
        $adminNotices[] = $n;
    }
}

// Mark all as read
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user->id]);

function detectType($msg) {
    if (str_contains($msg, 'registered for your event')) return ['Registration','info'];
    if (str_contains($msg, 'submitted feedback for your event')) return ['Feedback','info'];
    if (str_starts_with($msg, 'Your event') && str_contains($msg, 'approved')) return ['Approval','success'];
    if (str_starts_with($msg, 'Your event') && str_contains($msg, 'rejected')) return ['Approval','danger'];
    if (str_contains($msg, 'has been deleted') || str_contains($msg, 'has been cancelled')) return ['Deletion','warning'];
    if (str_starts_with($msg, 'You created event')) return ['Event','primary'];
    if (str_starts_with($msg, 'You registered for')) return ['Registration','primary'];
    if (str_starts_with($msg, 'You submitted feedback for')) return ['Feedback','primary'];
    return ['Notice','secondary'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications | EventConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- important for mobile -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../public/assets/css/style.css">
</head>
<body>
<div class="container-fluid py-4"> <!-- fluid container for full width -->
  <h1 class="h4 mb-3"><i class="bi bi-bell me-2"></i>Notifications</h1>

  <div class="row">
    <div class="col-12 col-md-6">
      <?php if (in_array('student', $roles)): ?>
        <h2 class="h5 mt-4">Student Notifications</h2>
        <?php if (!$studentNotices): ?>
          <div class="alert alert-info">No student notifications yet.</div>
        <?php endif; ?>
        <?php foreach ($studentNotices as $n): 
            [$label,$class] = detectType($n['message']);
        ?>
          <div class="alert <?= $n['is_read'] ? 'alert-secondary' : 'alert-'.$class ?> mb-2">
            <span class="badge bg-<?= $class ?> me-2"><?= e($label) ?></span>
            <?= e($n['message']) ?>
            <small class="text-muted d-block"><?= e($n['created_at']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="col-12 col-md-6">
      <?php if (in_array('club_admin', $roles)): ?>
        <h2 class="h5 mt-4">Club Admin Notifications</h2>
        <?php if (!$adminNotices): ?>
          <div class="alert alert-info">No club admin notifications yet.</div>
        <?php endif; ?>
        <?php foreach ($adminNotices as $n): 
            [$label,$class] = detectType($n['message']);
        ?>
          <div class="alert <?= $n['is_read'] ? 'alert-secondary' : 'alert-'.$class ?> mb-2">
            <span class="badge bg-<?= $class ?> me-2"><?= e($label) ?></span>
            <?= e($n['message']) ?>
            <small class="text-muted d-block"><?= e($n['created_at']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>