<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use RuntimeException;

/**
 * EmailService – sends transactional emails using PHPMailer.
 *
 * SMTP configuration comes from environment variables:
 *   MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD,
 *   MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_ENCRYPTION (tls|ssl)
 */
final class EmailService
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private string $encryption;

    public function __construct()
    {
        $this->host        = (string) (getenv('MAIL_HOST')         ?: 'mailhog');
        $this->port        = (int)    (getenv('MAIL_PORT')         ?: 1025);
        $this->username    = (string) (getenv('MAIL_USERNAME')     ?: '');
        $this->password    = (string) (getenv('MAIL_PASSWORD')     ?: '');
        $this->fromAddress = (string) (getenv('MAIL_FROM_ADDRESS') ?: 'noreply@workeddy.com');
        $this->fromName    = (string) (getenv('MAIL_FROM_NAME')    ?: 'WorkEddy');
        $this->encryption  = strtolower((string) (getenv('MAIL_ENCRYPTION') ?: 'tls'));
    }

    /* ── Public mailer methods ──────────────────────────────────────────── */

    /** Send a welcome / registration email. */
    public function sendWelcome(string $to, string $name): void
    {
        $html = $this->render('welcome', [
            'name'    => htmlspecialchars($name),
            'appUrl'  => $this->appUrl(),
        ]);
        $this->send($to, 'Welcome to WorkEddy 🎉', $html);
    }

    /** Send an email-based OTP code (login verification). */
    public function sendOtp(string $to, string $name, string $code): void
    {
        $html = $this->render('otp', [
            'name' => htmlspecialchars($name),
            'code' => $code,
        ]);
        $this->send($to, 'Your WorkEddy verification code', $html);
    }

    /** Send 2FA-enabled confirmation. */
    public function send2faEnabled(string $to, string $name): void
    {
        $html = $this->render('2fa_enabled', [
            'name'   => htmlspecialchars($name),
            'appUrl' => $this->appUrl(),
        ]);
        $this->send($to, 'Two-Factor Authentication Enabled', $html);
    }

    /** Send 2FA-disabled warning. */
    public function send2faDisabled(string $to, string $name): void
    {
        $html = $this->render('2fa_disabled', [
            'name'   => htmlspecialchars($name),
            'appUrl' => $this->appUrl(),
        ]);
        $this->send($to, 'Two-Factor Authentication Disabled', $html);
    }

    /** Send a security alert (suspicious login, password change, etc.). */
    public function sendSecurityAlert(string $to, string $name, string $event, string $detail = ''): void
    {
        $html = $this->render('security_alert', [
            'name'   => htmlspecialchars($name),
            'event'  => htmlspecialchars($event),
            'detail' => htmlspecialchars($detail),
            'appUrl' => $this->appUrl(),
        ]);
        $this->send($to, 'Security Alert – ' . $event, $html);
    }

    /** Send a password-reset link. */
    public function sendPasswordReset(string $to, string $name, string $resetLink): void
    {
        $html = $this->render('password_reset', [
            'name'      => htmlspecialchars($name),
            'resetLink' => $resetLink,
        ]);
        $this->send($to, 'Reset Your Password', $html);
    }

    /* ── Internal ───────────────────────────────────────────────────────── */

    private function send(string $to, string $subject, string $html): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->Port       = $this->port;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;

            if ($this->username !== '') {
                $mail->SMTPAuth   = true;
                $mail->Username   = $this->username;
                $mail->Password   = $this->password;
                $mail->SMTPSecure = $this->encryption === 'ssl'
                    ? PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAuth   = false;
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

            $mail->send();
        } catch (\Throwable $e) {
            error_log('[EmailService] Failed to send to ' . $to . ': ' . $e->getMessage());
            // Don't crash the application if email fails — log only
        }
    }

    /** Render an email template with variable substitution. */
    private function render(string $template, array $vars): string
    {
        $base = $this->baseLayout();

        $body = match ($template) {
            'welcome'        => $this->tplWelcome($vars),
            'otp'            => $this->tplOtp($vars),
            '2fa_enabled'    => $this->tpl2faEnabled($vars),
            '2fa_disabled'   => $this->tpl2faDisabled($vars),
            'security_alert' => $this->tplSecurityAlert($vars),
            'password_reset' => $this->tplPasswordReset($vars),
            default          => '<p>' . implode(' ', $vars) . '</p>',
        };

        return str_replace('{{BODY}}', $body, $base);
    }

    private function appUrl(): string
    {
        return (string) (getenv('APP_URL') ?: 'http://localhost:8080');
    }

    /* ── Templates ──────────────────────────────────────────────────────── */

    private function tplWelcome(array $v): string
    {
        return <<<HTML
<h2 style="color:#1a1a2e;margin:0 0 8px">Welcome to WorkEddy!</h2>
<p style="color:#555;font-size:16px">Hi {$v['name']},</p>
<p style="color:#555">Your account has been created successfully. You can now start assessing ergonomic risks with our REBA, RULA, and NIOSH tools.</p>
<div style="text-align:center;margin:32px 0">
  <a href="{$v['appUrl']}/dashboard"
     style="background:#4361ee;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;display:inline-block">
    Go to Dashboard
  </a>
</div>
<p style="color:#999;font-size:13px">If you didn't create this account, you can safely ignore this email.</p>
HTML;
    }

    private function tplOtp(array $v): string
    {
        return <<<HTML
<h2 style="color:#1a1a2e;margin:0 0 8px">Verification Code</h2>
<p style="color:#555;font-size:16px">Hi {$v['name']},</p>
<p style="color:#555">Use the following code to verify your identity. It expires in <strong>10 minutes</strong>.</p>
<div style="text-align:center;margin:32px 0">
  <div style="display:inline-block;background:#f0f3ff;border:2px dashed #4361ee;border-radius:12px;padding:20px 48px;letter-spacing:8px;font-size:36px;font-weight:700;color:#4361ee;font-family:monospace">
    {$v['code']}
  </div>
</div>
<p style="color:#999;font-size:13px">If you didn't request this code, please change your password immediately.</p>
HTML;
    }

    private function tpl2faEnabled(array $v): string
    {
        return <<<HTML
<h2 style="color:#1a1a2e;margin:0 0 8px">2FA Enabled</h2>
<p style="color:#555;font-size:16px">Hi {$v['name']},</p>
<p style="color:#555">Two-factor authentication has been <strong>enabled</strong> on your WorkEddy account. You'll now need to enter a code from your authenticator app each time you log in.</p>
<p style="color:#555">If you didn't make this change, please <a href="{$v['appUrl']}/org/settings" style="color:#4361ee">review your account settings</a> immediately.</p>
HTML;
    }

    private function tpl2faDisabled(array $v): string
    {
        return <<<HTML
<h2 style="color:#1a1a2e;margin:0 0 8px">2FA Disabled</h2>
<p style="color:#555;font-size:16px">Hi {$v['name']},</p>
<p style="color:#555">Two-factor authentication has been <strong>disabled</strong> on your WorkEddy account. Your account is now less secure.</p>
<p style="color:#555">If you didn't make this change, please <a href="{$v['appUrl']}/org/settings" style="color:#4361ee">secure your account</a> immediately.</p>
HTML;
    }

    private function tplSecurityAlert(array $v): string
    {
        $detail = $v['detail'] ? "<p style=\"color:#555;font-size:14px;background:#fff3cd;padding:12px;border-radius:6px\">{$v['detail']}</p>" : '';
        return <<<HTML
<h2 style="color:#dc3545;margin:0 0 8px">Security Alert</h2>
<p style="color:#555;font-size:16px">Hi {$v['name']},</p>
<p style="color:#555">We detected the following security event on your account:</p>
<p style="color:#1a1a2e;font-weight:600;font-size:18px;margin:16px 0">{$v['event']}</p>
{$detail}
<p style="color:#555">If this wasn't you, please change your password and enable two-factor authentication.</p>
<div style="text-align:center;margin:24px 0">
  <a href="{$v['appUrl']}/org/settings"
     style="background:#dc3545;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-block">
    Review Account
  </a>
</div>
HTML;
    }

    private function tplPasswordReset(array $v): string
    {
        return <<<HTML
<h2 style="color:#1a1a2e;margin:0 0 8px">Reset Your Password</h2>
<p style="color:#555;font-size:16px">Hi {$v['name']},</p>
<p style="color:#555">We received a request to reset your password. Click the button below to set a new one. This link expires in <strong>30 minutes</strong>.</p>
<div style="text-align:center;margin:32px 0">
  <a href="{$v['resetLink']}"
     style="background:#4361ee;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;display:inline-block">
    Reset Password
  </a>
</div>
<p style="color:#999;font-size:13px">If you didn't request this, you can safely ignore this email.</p>
HTML;
    }

    /** Shared email wrapper / layout. */
    private function baseLayout(): string
    {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:'Inter','Segoe UI',Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 16px">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)">
      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#4361ee 0%,#3a0ca3 100%);padding:24px 32px;text-align:center">
          <span style="color:#fff;font-size:22px;font-weight:700;letter-spacing:-.5px">WorkEddy</span>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:32px">
          {{BODY}}
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f8f9fa;padding:20px 32px;text-align:center;border-top:1px solid #eee">
          <p style="margin:0;color:#aaa;font-size:12px">
            &copy; {$year} WorkEddy — Ergonomic Risk Assessment
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
}
