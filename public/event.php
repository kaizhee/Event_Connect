<?php
include __DIR__ . '/../includes/page_nav.php';
require_once __DIR__ . '/../config/config.php';
// Load PHPMailer classes
require __DIR__ . '/../src/PHPMailer/PHPMailer.php';
require __DIR__ . '/../src/PHPMailer/SMTP.php';
require __DIR__ . '/../src/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

Auth::requireLogin();
$user = Auth::user();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('events.php');

$pdo = Database::pdo();
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'approved' LIMIT 1");
$stmt->execute([$id]);
$ev = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ev) redirect('events.php');

// Already joined?
$joinedStmt = $pdo->prepare("SELECT 1 FROM event_participants WHERE user_id = ? AND event_id = ?");
$joinedStmt->execute([$user->id, $id]);
$already = (bool)$joinedStmt->fetchColumn();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already) {
    try {
        $ins = $pdo->prepare("INSERT IGNORE INTO event_participants (user_id, event_id, joined_at) VALUES (?, ?, NOW())");
        $ins->execute([$user->id, $id]);
        $already = true;
        $success = 'You have registered for this event.';

        // Student notification
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$user->id, "You registered for '{$ev['name']}' on {$ev['event_date']} at {$ev['venue']}."]);

        // Club admin (event creator) notification
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$ev['created_by'], "{$user->name} registered for your event '{$ev['name']}'."]);

        // Email confirmation
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tangkaizhe8330@gmail.com';
            $mail->Password   = 'rowutuweausoqqsp'; // App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('tangkaizhe8330@gmail.com', 'EventConnect');
            $mail->addAddress($user->email, $user->name);

            $mail->isHTML(true);
            $mail->Subject = "You're Registered for {$ev['name']} 🎉";

            $mail->Body = "
              <div style='font-family: Arial, sans-serif; background-color:#f9f9f9; padding:20px;'>
                <div style='max-width:600px; margin:auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1);'>
                  
                  <div style='background:#0d6efd; color:#fff; padding:20px; text-align:center;'>
                    <h2 style='margin:0;'>EventConnect Registration</h2>
                  </div>
                  
                  <div style='padding:20px; color:#333;'>
                    <h3 style='color:#0d6efd; margin-top:0;'>Registration Confirmed ✅</h3>
                    <p>Hi <strong>{$user->name}</strong>,</p>
                    <p>You’ve successfully registered for:</p>
                    
                    <table style='width:100%; border-collapse:collapse; margin:15px 0;'>
                      <tr>
                        <td style='padding:8px; border-bottom:1px solid #eee;'><strong>Event:</strong></td>
                        <td style='padding:8px; border-bottom:1px solid #eee;'>{$ev['name']}</td>
                      </tr>
                      <tr>
                        <td style='padding:8px; border-bottom:1px solid #eee;'><strong>Date:</strong></td>
                        <td style='padding:8px; border-bottom:1px solid #eee;'>".date('F d, Y', strtotime($ev['event_date']))."</td>
                      </tr>
                      <tr>
                        <td style='padding:8px; border-bottom:1px solid #eee;'><strong>Time:</strong></td>
                        <td style='padding:8px; border-bottom:1px solid #eee;'>".substr($ev['start_at'],0,5)." – ".substr($ev['end_at'],0,5)."</td>
                      </tr>
                      <tr>
                        <td style='padding:8px;'><strong>Venue:</strong></td>
                        <td style='padding:8px;'>{$ev['venue']}</td>
                      </tr>
                    </table>
                    
                    <p style='margin:20px 0;'>We’ll keep you updated if there are any changes. In the meantime, you can view event details below:</p>
                    
                    <div style='text-align:center;'>
                      <a href='https://yourdomain.com/event.php?id={$ev['id']}'
                         style='display:inline-block; background:#0d6efd; color:#fff; padding:12px 20px; border-radius:4px; text-decoration:none; font-weight:bold;'>
                         View Event Details
                      </a>
                    </div>
                  </div>
                  
                  <div style='background:#f1f1f1; padding:15px; text-align:center; font-size:12px; color:#666;'>
                    <p style='margin:0;'>Powered by EventConnect • Bringing students together</p>
                  </div>
                </div>
              </div>
            ";
            $mail->send();
        } catch (Exception $e) { /* ignore */ }

    } catch (Throwable $e) {
        $error = 'Failed to register. Please try again.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= e($ev['name']) ?> | EventConnect</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../public/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="card">
    <div class="card-body">
      <h1 class="h4"><?= e($ev['name']) ?></h1>
      <p><strong>Organizer:</strong> <?= e($ev['organizer']) ?></p>
      <p><strong>Date:</strong> <?= e($ev['event_date']) ?></p>
      <p><strong>Time:</strong> <?= e(substr($ev['start_at'],0,5)) ?> - <?= e(substr($ev['end_at'],0,5)) ?></p>
      <p><strong>Venue:</strong> <?= e($ev['venue']) ?></p>
      <p><?= nl2br(e($ev['description'])) ?></p>
      <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <?php if (!$already): ?>
        <form method="post"><button class="btn btn-primary">Register</button></form>
      <?php else: ?>
        <span class="badge bg-success">You have registered</span>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>