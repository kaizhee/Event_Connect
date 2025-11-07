<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireLogin();
$user = Auth::user();

// Ensure event ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php?error=Invalid+event+ID");
    exit;
}

$eventId = (int)$_GET['id'];

$pdo = Database::pdo();

// Check if user is actually registered
$stmt = $pdo->prepare("SELECT 1 FROM event_participants WHERE user_id = ? AND event_id = ?");
$stmt->execute([$user->id, $eventId]);
if (!$stmt->fetchColumn()) {
    header("Location: dashboard.php?error=Not+registered");
    exit;
}

// Delete the registration
$stmt = $pdo->prepare("DELETE FROM event_participants WHERE user_id = ? AND event_id = ?");
$stmt->execute([$user->id, $eventId]);

// Optional: add a notification for the user
$pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
    ->execute([$user->id, "You have cancelled your registration for event ID {$eventId}."]);

// Redirect back with success flag
header("Location: dashboard.php?cancelled=1");
exit;