<?php
/**
 * PVR Media Reviews — Email sender via Resend API
 *
 * Sends license key emails after purchase.
 * Requires RESEND_API_KEY and RESEND_FROM_EMAIL in .env.
 */

declare(strict_types=1);

function sendLicenseEmail(string $to, string $domain, string $licenseKey, string $updatesUntil): bool
{
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("PVR email: invalid address '{$to}'");
        return false;
    }

    $apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
    $fromEmail = defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : 'noreply@pv-reviews.site';
    $fromName = 'PVR Media Reviews';

    if ($apiKey === '') {
        error_log("PVR email: RESEND_API_KEY not configured. Skipping email to {$to}");
        return false;
    }

    $subject = "Your PVR Pro License Key — {$domain}";

    $htmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif; max-width:560px; margin:0 auto; padding:40px 20px; color:#1e293b;">
  <div style="text-align:center; margin-bottom:32px;">
    <div style="width:56px; height:56px; background:linear-gradient(135deg,#6366f1,#4f46e5); border-radius:14px; display:inline-flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:800;">P</div>
  </div>
  <h1 style="font-size:22px; font-weight:700; margin-bottom:8px; text-align:center;">Your purchase is confirmed</h1>
  <p style="font-size:15px; color:#64748b; text-align:center; margin-bottom:32px;">Here is your PVR Media Reviews Pro license key.</p>

  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:24px; margin-bottom:24px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding:8px 0; font-size:14px; color:#64748b; font-weight:500;">License Key</td>
        <td style="padding:8px 0; font-size:14px; font-weight:600; text-align:right; font-family:monospace; color:#1e293b; letter-spacing:0.5px;">' . htmlspecialchars($licenseKey) . '</td>
      </tr>
      <tr>
        <td style="padding:8px 0; font-size:14px; color:#64748b; font-weight:500; border-top:1px solid #e2e8f0;">Domain</td>
        <td style="padding:8px 0; font-size:14px; font-weight:600; text-align:right; border-top:1px solid #e2e8f0; color:#1e293b;">' . htmlspecialchars($domain) . '</td>
      </tr>
      <tr>
        <td style="padding:8px 0; font-size:14px; color:#64748b; font-weight:500; border-top:1px solid #e2e8f0;">License</td>
        <td style="padding:8px 0; font-size:14px; font-weight:600; text-align:right; border-top:1px solid #e2e8f0; color:#10b981;">Lifetime</td>
      </tr>
      <tr>
        <td style="padding:8px 0; font-size:14px; color:#64748b; font-weight:500; border-top:1px solid #e2e8f0;">Updates &amp; Support Until</td>
        <td style="padding:8px 0; font-size:14px; font-weight:600; text-align:right; border-top:1px solid #e2e8f0; color:#1e293b;">' . htmlspecialchars($updatesUntil) . '</td>
      </tr>
    </table>
  </div>

  <h2 style="font-size:16px; font-weight:600; margin-bottom:12px;">How to activate</h2>
  <ol style="font-size:14px; color:#475569; line-height:1.8; padding-left:20px; margin-bottom:24px;">
    <li>Install the PVR Media Reviews plugin on your WordPress site.</li>
    <li>Go to <strong>Settings &rarr; PVR License</strong> and enter the key above.</li>
    <li>Click <strong>Activate</strong> &mdash; Pro features unlock instantly.</li>
  </ol>

  <p style="font-size:13px; color:#94a3b8; text-align:center; margin-top:32px;">
    Need help? <a href="https://pv-reviews.site/#faq" style="color:#6366f1;">Check our FAQ</a> or reply to this email.
  </p>
</body>
</html>';

    $textBody = "Your PVR Pro License Key\n\n";
    $textBody .= "License Key: {$licenseKey}\n";
    $textBody .= "Domain:      {$domain}\n";
    $textBody .= "License:     Lifetime\n";
    $textBody .= "Updates & Support Until: {$updatesUntil}\n\n";
    $textBody .= "How to activate:\n";
    $textBody .= "1. Install PVR Media Reviews on your WordPress site.\n";
    $textBody .= "2. Go to Settings -> PVR License and enter the key.\n";
    $textBody .= "3. Click Activate.\n\n";
    $textBody .= "Need help? https://pv-reviews.site/#faq\n";

    $payload = json_encode([
        'from'    => $fromName . ' <' . $fromEmail . '>',
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $htmlBody,
        'text'    => $textBody,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("PVR email: curl error sending to {$to}: {$curlError}");
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        $emailId = $data['id'] ?? 'unknown';
        error_log("PVR email: sent to {$to} — id={$emailId}");
        return true;
    }

    error_log("PVR email: Resend API error {$httpCode} sending to {$to}: {$response}");
    return false;
}
