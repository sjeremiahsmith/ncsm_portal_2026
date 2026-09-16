<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "Testing database connection...<br>";
try {
    $db = getDb();
    echo "✓ Database connected<br>";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
    exit;
}

echo "Testing contact_messages table...<br>";
try {
    $messages = $db->fetchAll("SELECT * FROM contact_messages ORDER BY created_at DESC");
    echo "✓ Table works. Found " . count($messages) . " messages<br>";
} catch (Exception $e) {
    echo "✗ Query error: " . $e->getMessage() . "<br>";
    exit;
}

try {
    $unreadCount = $db->fetchOne("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0")['c'];
    echo "✓ Unread count: " . $unreadCount . "<br>";
} catch (Exception $e) {
    echo "✗ Unread query error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<br>All tests passed! The messages page should work.";
