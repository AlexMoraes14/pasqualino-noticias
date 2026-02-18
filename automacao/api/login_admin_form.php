<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../HTML/index.html', true, 302);
    exit;
}

$email = cnp_normalize_email($_POST['email'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: ../HTML/index.html?erro=missing', true, 303);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@pasqualino\.com\.br$/', $email)) {
    header('Location: ../HTML/index.html?erro=email', true, 303);
    exit;
}

if (!cnp_is_panel_password_configured()) {
    header('Location: ../HTML/index.html?erro=config', true, 303);
    exit;
}

$role = cnp_role_for_email($email);
if (!cnp_has_panel_access_role($role)) {
    header('Location: ../HTML/index.html?erro=email_auth', true, 303);
    exit;
}

if (!cnp_verify_panel_password($password)) {
    header('Location: ../HTML/index.html?erro=senha', true, 303);
    exit;
}

if (!cnp_admin_login($email, $password)) {
    header('Location: ../HTML/index.html?erro=cred', true, 303);
    exit;
}

header('Location: ../HTML/adm.html', true, 303);
exit;
