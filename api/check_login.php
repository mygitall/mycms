<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';

$token = !empty($_COOKIE['admin_token']) ? $_COOKIE['admin_token'] : null;
if (!$token) {
    echo json_encode(['code' => 0, 'data' => ['loggedIn' => false]]);
    exit;
}

$userId = verifyToken(getDB(), $token, false);
if ($userId) {
    $prefix = DB_PREFIX;
    $stmt = getDB()->prepare("SELECT username FROM {$prefix}users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    echo json_encode(['code' => 0, 'data' => ['loggedIn' => true, 'username' => $user ? $user['username'] : '']]);
} else {
    echo json_encode(['code' => 0, 'data' => ['loggedIn' => false]]);
}
