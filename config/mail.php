<?php
/**
 * SMTP and Email Configuration for Secure Online Voting System
 */

return [
    // Primary SMTP server settings
    // For Gmail: smtp.gmail.com, port 587 (TLS) or 465 (SSL), use 16-character Google App Password
    // For Laragon Local Mail Catcher: host 127.0.0.1, port 1025, encryption 'none', no user/pass needed
    // For Brevo / Mailtrap / Sendgrid: use provided host, port, user and pass
    'smtp_host'       => 'smtp.gmail.com',
    'smtp_port'       => 587,
    'smtp_user'       => '',
    'smtp_pass'       => '',
    'smtp_encryption' => 'tls', // 'tls', 'ssl', or 'none'
    'smtp_timeout'    => 10,    // seconds

    // Sender details
    'from_email'      => 'noreply@lasustech.edu.ng',
    'from_name'       => 'LASUSTECH Secure Voting System',

    // Email delivery mode:
    // 'auto' : Tries SMTP if credentials configured; otherwise falls back to mail() or local log
    // 'smtp' : Forces SMTP sending only
    // 'mail' : Uses PHP's mail() function
    // 'log'  : Debug mode, writes email and OTP to logs/mail.log without sending
    'delivery_mode'   => 'auto',

    // Security & Expiry settings
    'otp_expiry_minutes'      => 10, // 10 minutes OTP expiration
    'otp_max_attempts'        => 5,  // Max incorrect OTP guesses before invalidating
    'resend_cooldown_seconds' => 60, // 60 seconds anti-flood cooldown before requesting a new code

    // Genuine Student Verification:
    // Set to true if you want to require students' matric numbers to be pre-registered in `eligible_students` table.
    // Set to false for open student self-registration with format and email verification.
    'enforce_student_roster'  => false,

    // Allowed email domains: leave empty [] to allow any valid domain (Gmail, Yahoo, Outlook, etc.)
    // Or set to ['student.lasustech.edu.ng', 'lasustech.edu.ng'] to restrict to official school emails.
    'allowed_email_domains'   => [],
];

