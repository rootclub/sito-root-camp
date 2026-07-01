<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Vendorato in inc/PHPMailer/ (Exception, PHPMailer, SMTP). v6.10.0.
// Caricato on-demand dentro mailer_send() per non pesare sulle pagine che non spediscono.

/**
 * Invio email via SMTP.
 *
 * @param string  $to        destinatario
 * @param string  $subject   oggetto
 * @param string  $bodyHtml  corpo HTML
 * @param string  $bodyText  corpo plain-text (alt body)
 * @param ?string $toName    nome destinatario (opzionale)
 * @return bool   true se accettato dal server SMTP, false in caso di errore (loggato)
 */
function mailer_send(
    string $to,
    string $subject,
    string $bodyHtml,
    string $bodyText,
    ?string $toName = null
): bool {
    if (SMTP_HOST === '' || SMTP_FROM === '') {
        error_log('mailer_send: SMTP_HOST o SMTP_FROM non configurati, mail non inviata.');
        return false;
    }

    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = 15;

        if (SMTP_USER !== '' || SMTP_PASS !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
        }

        switch (strtolower(SMTP_SECURE)) {
            case 'ssl':
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                break;
            case 'tls':
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                break;
            case 'none':
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
                break;
            default:
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Debug solo in development.
        if (APP_DEBUG) {
            $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_OFF; // alza a DEBUG_SERVER se serve
        }

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, (string)$toName);
        $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;

        return $mail->send();

    } catch (\Throwable $e) {
        error_log('mailer_send failed: ' . ($mail->ErrorInfo ?: $e->getMessage()));
        return false;
    }
}

/**
 * Mail di conferma iscrizione, template default.
 *
 * @param array $iscrizione  payload con name, email, sleep_kind, total_eur,
 *                            edit_token, meals (array di label).
 * @param array $edition     riga della tabella editions
 */
function mailer_send_iscrizione_confirm(array $iscrizione, array $edition): bool
{
    $name    = (string)$iscrizione['name'];
    $email   = (string)$iscrizione['email'];
    $sleep   = (string)$iscrizione['sleep_kind'];
    $total   = (int)$iscrizione['total_eur'];
    $token   = (string)($iscrizione['edit_token'] ?? '');
    $meals   = $iscrizione['meals'] ?? [];
    if (!is_array($meals)) $meals = [];
    $tshirt  = trim((string)($iscrizione['tshirt_size'] ?? ''));
    $edName  = (string)$edition['name'];
    $when    = (string)$edition['date_label'];
    $where   = trim(((string)$edition['loc_name']) . ' · ' . ((string)$edition['loc_city']));
    $contact = (string)($edition['contact_email'] ?? SMTP_FROM);

    $base = APP_URL !== '' ? APP_URL : '';
    $editUrl = $base !== '' && $token !== ''
        ? $base . '/modifica.php?t=' . urlencode($token)
        : '';

    $subject = "Iscrizione ricevuta · $edName";

    $mealsTxt = $meals
        ? implode("\n", array_map(fn($m) => "    - $m", $meals))
        : '    (nessuno selezionato)';

    $tshirtTxt = $tshirt !== '' ? "  - Maglietta (taglia): $tshirt\n" : '';

    $editTxt = $editUrl !== ''
        ? "\nPer modificare le tue scelte (pasti, dormita, contatti) usa questo link:\n  $editUrl\n"
        : '';

    $text = <<<TXT
Ciao $name,

abbiamo ricevuto la tua iscrizione a $edName.

Quando: $when
Dove:   $where

Riepilogo:
  - Pernotto: $sleep
  - Pasti prenotati:
$mealsTxt
$tshirtTxt  - Totale previsto: $total €
$editTxt
Quando arrivi al camp passa al check-in: ti consegniamo il braccialetto.

Per qualunque cosa scrivi a $contact.

A presto, nel prato!
— /RooT-Camp
TXT;

    $eName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $eEd    = htmlspecialchars($edName, ENT_QUOTES, 'UTF-8');
    $eWhen  = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');
    $eWhere = htmlspecialchars($where, ENT_QUOTES, 'UTF-8');
    $eSleep = htmlspecialchars($sleep, ENT_QUOTES, 'UTF-8');
    $eTotal = (string)$total;
    $eMail  = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');
    $eEdit  = htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8');
    $eTshirt = htmlspecialchars($tshirt, ENT_QUOTES, 'UTF-8');

    $tshirtHtmlRow = $tshirt !== ''
        ? '<tr><td style="color:#6a8578;font-size:12px;text-transform:uppercase;letter-spacing:.1em;padding-bottom:4px;">Maglietta (taglia)</td></tr>'
          . '<tr><td style="padding-bottom:14px;">' . $eTshirt . '</td></tr>'
        : '';

    $eMealsHtml = $meals
        ? '<ul style="margin:0;padding-left:18px;">' . implode('', array_map(
            fn($m) => '<li>' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</li>',
            $meals
          )) . '</ul>'
        : '<em style="color:#6a8578;">nessuno selezionato</em>';

    $editHtmlBlock = $editUrl !== ''
        ? '<p style="font-size:14px;line-height:1.5;color:#3a5a46;margin:22px 0 0;">'
          . 'Hai cambiato idea su qualche pasto, sulla dormita o vuoi correggere un dato? '
          . '<a href="' . $eEdit . '" style="color:#ff6b3d;">Modifica le tue scelte</a> in qualunque momento.'
          . '</p>'
        : '';

    $html = <<<HTML
<!doctype html>
<html lang="it"><head><meta charset="utf-8"><title>$eEd</title></head>
<body style="margin:0;padding:0;background:#fffef5;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#0f2a1a;">
<div style="max-width:560px;margin:0 auto;padding:32px 24px;">
  <p style="font-family:'Courier New',monospace;font-size:13px;letter-spacing:.04em;color:#3a5a46;margin:0 0 18px;">/RooT-Camp</p>
  <h1 style="font-size:28px;line-height:1.1;margin:0 0 14px;color:#0f2a1a;">Iscrizione ricevuta</h1>
  <p style="font-size:16px;line-height:1.5;margin:0 0 18px;">Ciao $eName, abbiamo registrato la tua iscrizione a <strong>$eEd</strong>.</p>

  <table cellpadding="0" cellspacing="0" style="width:100%;background:#ffffff;border:2px solid #0f2a1a;border-radius:14px;padding:18px;font-size:15px;">
    <tr><td style="color:#6a8578;font-size:12px;text-transform:uppercase;letter-spacing:.1em;padding-bottom:4px;">Quando</td></tr>
    <tr><td style="padding-bottom:14px;"><strong>$eWhen</strong></td></tr>
    <tr><td style="color:#6a8578;font-size:12px;text-transform:uppercase;letter-spacing:.1em;padding-bottom:4px;">Dove</td></tr>
    <tr><td style="padding-bottom:14px;">$eWhere</td></tr>
    <tr><td style="color:#6a8578;font-size:12px;text-transform:uppercase;letter-spacing:.1em;padding-bottom:4px;">Pernotto</td></tr>
    <tr><td style="padding-bottom:14px;">$eSleep</td></tr>
    <tr><td style="color:#6a8578;font-size:12px;text-transform:uppercase;letter-spacing:.1em;padding-bottom:4px;">Pasti prenotati</td></tr>
    <tr><td style="padding-bottom:14px;">$eMealsHtml</td></tr>
    $tshirtHtmlRow
    <tr><td style="color:#6a8578;font-size:12px;text-transform:uppercase;letter-spacing:.1em;padding-bottom:4px;">Totale previsto</td></tr>
    <tr><td><strong style="font-size:20px;">$eTotal &euro;</strong></td></tr>
  </table>

  $editHtmlBlock

  <p style="font-size:14px;line-height:1.5;color:#3a5a46;margin:14px 0 0;">
    Per qualunque cosa scrivi a <a href="mailto:$eMail" style="color:#ff6b3d;">$eMail</a>.
  </p>
  <p style="font-family:'Brush Script MT',cursive;font-size:24px;color:#ffd36b;margin:26px 0 0;">a presto, nel prato!</p>
</div>
</body></html>
HTML;

    return mailer_send($email, $subject, $html, $text, $name);
}
