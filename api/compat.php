<?php
/**
 * PHP 5.6 ~ 8.x 兼容层
 */

// ---- random_bytes() polyfill for PHP < 7.0 ----
if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if ($strong && $bytes !== false) {
                return $bytes;
            }
        }
        // Low-quality fallback — should never be reached on PHP 5.6
        // because openssl is typically available
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}

// ---- hash_equals() polyfill for PHP < 5.6 ----
if (!function_exists('hash_equals')) {
    function hash_equals($a, $b) {
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $result |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $result === 0;
    }
}

// ---- ctype_space polyfill (rare but possible on very old builds) ----
if (!function_exists('ctype_space')) {
    function ctype_space($text) {
        return preg_match('/^\s+$/', $text) > 0;
    }
}

// ---- 兼容的密码哈希 ----
function compat_password_hash($password) {
    if (defined('PASSWORD_ARGON2ID')) {
        // PHP 7.3+ with argon2 — 不带 threads 参数，确保 sodium 实现也能工作
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
        ]);
    }
    // bcrypt fallback (PHP 5.5+)
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// ---- 兼容的 session_start ----
function compat_session_start() {
    if (PHP_VERSION_ID >= 70300) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'use_strict_mode' => true,
            'use_only_cookies' => true,
        ]);
    } else {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }
        session_start();
    }
}
