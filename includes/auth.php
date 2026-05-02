<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// --- CSRF Token Helpers ---
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

// --- Auth Functions ---
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireEmployee() {
    requireLogin();
    if ($_SESSION['role'] !== 'employee') {
        $_SESSION['flash_error'] = 'Access denied. Employee privileges required.';
        header('Location: dashboard.php');
        exit;
    }
}

function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentCustomerId() {
    return $_SESSION['customer_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? 'Guest';
}

function getCurrentRole() {
    return $_SESSION['role'] ?? 'guest';
}

// --- Login ---
function loginUser($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM JKP_USERS WHERE USERNAME = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['PASSWORD_HASH'])) {
        $_SESSION['user_id']     = $user['USER_ID'];
        $_SESSION['username']    = $user['USERNAME'];
        $_SESSION['role']        = $user['ROLE'];
        $_SESSION['customer_id'] = $user['CUSTOMER_ID'];
        $_SESSION['full_name']   = $user['FULL_NAME'];

        // Log login
        $log = $db->prepare("INSERT INTO JKP_LOGIN_HISTORY (USER_ID, LOGIN_TIME, IP_ADDRESS) VALUES (:uid, NOW(), :ip)");
        $log->execute([':uid' => $user['USER_ID'], ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

        return true;
    }
    return false;
}

// --- Register ---
function registerUser($username, $password, $fullName, $role = 'customer', $customerId = null) {
    $db = getDB();

    // Check if username exists
    $check = $db->prepare("SELECT USER_ID FROM JKP_USERS WHERE USERNAME = :username");
    $check->execute([':username' => $username]);
    if ($check->fetch()) {
        return "Username already exists.";
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO JKP_USERS (USERNAME, PASSWORD_HASH, FULL_NAME, ROLE, CUSTOMER_ID) VALUES (:u, :p, :fn, :r, :cid)");
    $stmt->execute([
        ':u'   => $username,
        ':p'   => $hash,
        ':fn'  => $fullName,
        ':r'   => $role,
        ':cid' => $customerId,
    ]);
    return true;
}

// --- Logout ---
function logoutUser() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// --- Flash Messages ---
function setFlash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function getFlash($type) {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

// --- Sanitize Output (XSS Prevention) ---
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
