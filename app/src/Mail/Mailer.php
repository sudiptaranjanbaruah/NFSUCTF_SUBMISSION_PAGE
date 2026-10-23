<?php
namespace App\Mail;

use App\Core\DB;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends mail through Hostinger SMTP. Every message is recorded in mail_queue:
 * urgent ones (OTP codes) are sent immediately, bulk ones are drained by cron
 * within the hourly cap.
 */
final class Mailer
{
    /** Render an email template into [html, text]. */
    public static function render(string $template, array $data): array
    {
        $data['event_name'] = setting('event_name');
        $data['org_name'] = setting('org_name');
        $data['app_url'] = rtrim((string) env('APP_URL', ''), '/');
        $body = render('emails/' . $template, $data, null);
        $html = render('emails/layout', $data + ['body' => $body], null);
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>|</h\d>|</tr>#i', "\n", $body)), ENT_QUOTES));
        $text = preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text));
        return [$html, $text];
    }

    /** Send now (used for OTP codes). Returns true on success. */
    public static function sendNow(string $to, string $subject, string $template, array $data = []): bool
    {
        [$html, $text] = self::render($template, $data);
        $id = DB::insert('mail_queue', ['to_email' => $to, 'subject' => $subject, 'html' => $html, 'text_body' => $text, 'attempts' => 1]);
        try {
            self::deliver($to, $subject, $html, $text);
            DB::query('UPDATE mail_queue SET sent_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
            return true;
        } catch (\Throwable $e) {
            DB::query('UPDATE mail_queue SET last_error = ? WHERE id = ?', [mb_substr($e->getMessage(), 0, 250), $id]);
            error_log('Mail send failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Queue for the cron worker (bulk invites, announcements). */
    public static function queue(string $to, string $subject, string $template, array $data = []): void
    {
        [$html, $text] = self::render($template, $data);
        DB::insert('mail_queue', ['to_email' => $to, 'subject' => $subject, 'html' => $html, 'text_body' => $text]);
    }

    /** Send pending queued mail, respecting MAIL_HOURLY_CAP. Returns number sent. */
    public static function drain(int $max = 50): int
    {
        $cap = (int) env('MAIL_HOURLY_CAP', 90);
        $sentLastHour = (int) DB::val('SELECT COUNT(*) FROM mail_queue WHERE sent_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR');
        $budget = min($max, max(0, $cap - $sentLastHour));
        if ($budget === 0) {
            return 0;
        }
        $rows = DB::all('SELECT * FROM mail_queue WHERE sent_at IS NULL AND attempts < 3 ORDER BY id LIMIT ' . $budget);
        $sent = 0;
        foreach ($rows as $m) {
            try {
                self::deliver($m['to_email'], $m['subject'], $m['html'], $m['text_body']);
                DB::query('UPDATE mail_queue SET sent_at = UTC_TIMESTAMP(), attempts = attempts + 1 WHERE id = ?', [$m['id']]);
                $sent++;
            } catch (\Throwable $e) {
                DB::query('UPDATE mail_queue SET attempts = attempts + 1, last_error = ? WHERE id = ?', [mb_substr($e->getMessage(), 0, 250), $m['id']]);
            }
        }
        return $sent;
    }

    private static function deliver(string $to, string $subject, string $html, ?string $text): void
    {
        if (env('MAIL_DRIVER', 'smtp') === 'log') {
            file_put_contents(
                APP_ROOT . '/storage/logs/mail.log',
                sprintf("=== %s | To: %s | %s\n%s\n\n", gmdate('c'), $to, $subject, $text),
                FILE_APPEND
            );
            return;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) env('MAIL_HOST', 'smtp.hostinger.com');
        $mail->Port = (int) env('MAIL_PORT', 465);
        $mail->SMTPAuth = true;
        $mail->Username = (string) env('MAIL_USERNAME');
        $mail->Password = (string) env('MAIL_PASSWORD');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string) env('MAIL_FROM', env('MAIL_USERNAME')), (string) env('MAIL_FROM_NAME', setting('event_name')));
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text ?? '';
        try {
            $mail->send();
        } catch (MailException $e) {
            throw new \RuntimeException($mail->ErrorInfo ?: $e->getMessage());
        }
    }
}
