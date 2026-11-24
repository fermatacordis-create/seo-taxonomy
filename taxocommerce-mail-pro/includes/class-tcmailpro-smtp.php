<?php
/**
 * Isolated SMTP transport using PHPMailer bundled with WordPress.
 * Does not replace wp_mail() or change WooCommerce's mail transport.
 *
 * @package TaxoCommerceMailPro
 */
defined( 'ABSPATH' ) || exit;
final class TCMAILPRO_SMTP {
    public static function send( $account, $to, $subject, $message, $reply_to = '' ) {
        if ( ! current_user_can( 'manage_options' ) || ! is_email( $to ) ||
            ! is_string( $message ) || '' === trim( $message ) ||
            strlen( $message ) > 50000 || strlen( $subject ) > 250 ) {
            return false;
        }
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer( true );
        try {
            $mail->isSMTP();
            $mail->Host = $account['smtp_host'];
            $mail->Port = (int) $account['smtp_port'];
            $mail->SMTPAuth = true;
            $mail->Username = $account['username'];
            $mail->Password = $account['password'];
            $mail->SMTPSecure = 465 === $mail->Port
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = false;
            $mail->Timeout = 12;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;
            $mail->setFrom( $account['email'], $account['name'] );
            $mail->addAddress( $to );
            if ( $reply_to && is_email( $reply_to ) ) {
                $mail->addReplyTo( $reply_to );
            }
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = $message;
            $mail->isHTML( false );
            // Return the MIME payload only on SMTP acceptance; never persist it locally.
            return $mail->send() ? $mail->getSentMIMEMessage() : false;
        } catch ( \PHPMailer\PHPMailer\Exception $exception ) {
            // Never expose SMTP authentication, headers, customer data or exception details.
            return false;
        } finally {
            $mail->Password = '';
            $mail->clearAllRecipients();
        }
    }
}
