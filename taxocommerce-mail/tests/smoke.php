<?php
/**
 * Offline smoke: account encryption, validation and non-executing MIME parsing.
 *
 * Run: php taxocommerce-mail/tests/smoke.php
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['tcmail_options'] = array();
function wp_salt( $scheme ) { return 'test-only-deterministic-salt-' . $scheme; }
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function get_option( $name, $default = false ) { return isset( $GLOBALS['tcmail_options'][$name] ) ? $GLOBALS['tcmail_options'][$name] : $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['tcmail_options'][$name] = $value; return true; }
function wp_generate_uuid4() { return '90000000-0000-4000-8000-000000000001'; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_email( $value ) { return strtolower( filter_var( $value, FILTER_SANITIZE_EMAIL ) ); }
function is_email( $value ) { return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function sanitize_text_field( $value ) { return preg_replace( '/[\x00-\x1f\x7f]/', '', strip_tags( (string) $value ) ); }
require_once dirname( __DIR__ ) . '/includes/class-tcmail-accounts.php';
require_once dirname( __DIR__ ) . '/includes/class-tcmail-imap.php';
function assert_mail( $value, $message ) {
    if ( ! $value ) {
        fwrite( STDERR, "FAIL: $message\n" );
        exit( 1 );
    }
}
$input = array(
    'name' => 'Store support', 'email' => 'support@shop.example',
    'username' => 'support@shop.example', 'imap_host' => 'mail.shop.example',
    'smtp_host' => 'mail.shop.example', 'smtp_port' => 465,
);
$minimal = TCMAIL_Accounts::basic_defaults( array(
    'username' => 'support@shop.example',
    'imap_host' => 'mail.shop.example',
) );
assert_mail( 'support@shop.example' === $minimal['email'], 'Quick setup must use the complete address.' );
assert_mail( 'support' === $minimal['name'], 'Quick setup must derive a default display name.' );
assert_mail( 'mail.shop.example' === $minimal['smtp_host'] && 465 === $minimal['smtp_port'], 'Quick setup must retain optional SMTP functionality.' );
assert_mail( TCMAIL_Accounts::validate( $minimal ) !== false, 'Three-field quick setup must validate.' );
assert_mail( TCMAIL_Accounts::ready(), 'OpenSSL AES-256-GCM is required.' );
assert_mail( TCMAIL_Accounts::save( $input, 'local-test-secret' ), 'Cannot save first mailbox.' );
$saved = $GLOBALS['tcmail_options'][TCMAIL_Accounts::OPTION];
assert_mail( 1 === count( $saved ) && false === strpos( serialize( $saved ), 'local-test-secret' ), 'Password leaked into stored account.' );
$id = $saved[0]['id'];
assert_mail( 'local-test-secret' === TCMAIL_Accounts::credentials( $id )['password'], 'Encrypted password cannot be recovered.' );
assert_mail( TCMAIL_Accounts::save( $input, '', $id ), 'Editing the mailbox did not retain password.' );
assert_mail( 'local-test-secret' === TCMAIL_Accounts::credentials( $id )['password'], 'Password retention changed the secret.' );
$invalid = $input;
$invalid['imap_host'] = 'localhost';
assert_mail( false === TCMAIL_Accounts::save( $invalid, 'x' ), 'Unsafe localhost hostname accepted.' );
$invalid = $input;
$invalid['smtp_port'] = 25;
assert_mail( false === TCMAIL_Accounts::save( $invalid, 'x' ), 'Unencrypted SMTP port accepted.' );
$parsed = TCMAIL_IMAP::parse_message("From: Customer <client@example.test>\r\nSubject: =?UTF-8?B?UHJ1ZWJh?=\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . base64_encode( "Hola, necesito ayuda\n" ) );
assert_mail( false !== strpos( $parsed['subject'], 'Prueba' ) && false !== strpos( $parsed['body'], 'Hola, necesito ayuda' ), 'MIME/plain-text parsing failed.' );
$parts = 'TEST_SEPARATOR';
$multipart = "From: customer@example.test\r\nContent-Type: multipart/alternative; boundary=\"{$parts}\"\r\n\r\n"
    . "--{$parts}\r\nContent-Type: text/plain\r\n\r\nPreferred plain part\r\n"
    . "--{$parts}\r\nContent-Type: text/html\r\n\r\n<img src='https://tracker.example/x'>HTML\r\n--{$parts}--\r\n";
$parsed = TCMAIL_IMAP::parse_message( $multipart );
assert_mail( 'Preferred plain part' === $parsed['body'], 'Multipart alternative should use text/plain.' );
assert_mail( TCMAIL_Accounts::remove( $id ) && 0 === count( TCMAIL_Accounts::public_list() ), 'Disconnecting mailbox failed.' );
echo "PASS: encrypted account storage, mailbox validation and basic MIME reading.\n";
