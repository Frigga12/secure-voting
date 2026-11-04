<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * Remove all session data, including temporary voting selections.
 */
$_SESSION = [];

/*
 * Expire the session cookie in the browser.
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires"  => time() - 42000,
            "path"     => $params["path"],
            "domain"   => $params["domain"],
            "secure"   => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Lax"
        ]
    );
}

/*
 * Destroy the server-side session.
 */
session_destroy();

/*
 * Prevent the browser from treating protected pages as cached pages.
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: login.php");
exit;