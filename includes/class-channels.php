<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registry of supported contact channels, plus helpers to sanitize values
 * and build deep links. Must be called on/after `init` (uses translations).
 */
final class Contact_Hub_Channels {

	/**
	 * kind: phone | username | email | url
	 */
	public static function all(): array {
		return array(
			'whatsapp'  => array(
				'label'   => 'WhatsApp',
				'kind'    => 'phone',
				'hint'    => '+989121234567',
				'color'   => '#1da851',
				'icon'    => 'chat',
				'cta'     => __( 'Chat on WhatsApp', 'contact-hub' ),
				'prefill' => true,
			),
			'telegram'  => array(
				'label' => 'Telegram',
				'kind'  => 'username',
				'hint'  => 'username',
				'color' => '#229ed9',
				'icon'  => 'send',
				'cta'   => __( 'Message on Telegram', 'contact-hub' ),
			),
			'eitaa'     => array(
				'label' => __( 'Eitaa', 'contact-hub' ),
				'kind'  => 'username',
				'hint'  => 'username',
				'color' => '#f26b21',
				'icon'  => 'chat',
				'cta'   => __( 'Message on Eitaa', 'contact-hub' ),
			),
			'bale'      => array(
				'label' => __( 'Bale', 'contact-hub' ),
				'kind'  => 'username',
				'hint'  => 'username',
				'color' => '#17a05d',
				'icon'  => 'chat',
				'cta'   => __( 'Message on Bale', 'contact-hub' ),
			),
			'rubika'    => array(
				'label' => __( 'Rubika', 'contact-hub' ),
				'kind'  => 'username',
				'hint'  => 'username',
				'color' => '#c2185b',
				'icon'  => 'chat',
				'cta'   => __( 'Message on Rubika', 'contact-hub' ),
			),
			'instagram' => array(
				'label' => 'Instagram',
				'kind'  => 'username',
				'hint'  => 'username',
				'color' => '#e1306c',
				'icon'  => 'camera',
				'cta'   => __( 'Send a direct message', 'contact-hub' ),
			),
			'messenger' => array(
				'label' => 'Messenger',
				'kind'  => 'username',
				'hint'  => 'page-name',
				'color' => '#0084ff',
				'icon'  => 'chat',
				'cta'   => __( 'Chat on Messenger', 'contact-hub' ),
			),
			'viber'     => array(
				'label' => 'Viber',
				'kind'  => 'phone',
				'hint'  => '+989121234567',
				'color' => '#7360f2',
				'icon'  => 'chat',
				'cta'   => __( 'Chat on Viber', 'contact-hub' ),
			),
			'sms'       => array(
				'label'   => __( 'SMS', 'contact-hub' ),
				'kind'    => 'phone',
				'hint'    => '+989121234567',
				'color'   => '#2563eb',
				'icon'    => 'sms',
				'cta'     => __( 'Send a text message', 'contact-hub' ),
				'prefill' => true,
				'copy'    => true,
			),
			'call'      => array(
				'label' => __( 'Phone call', 'contact-hub' ),
				'kind'  => 'phone',
				'hint'  => '+982112345678',
				'color' => '#0f766e',
				'icon'  => 'call',
				'cta'   => __( 'Call us now', 'contact-hub' ),
				'copy'  => true,
			),
			'email'     => array(
				'label'   => __( 'Email', 'contact-hub' ),
				'kind'    => 'email',
				'hint'    => 'hello@example.com',
				'color'   => '#64748b',
				'icon'    => 'mail',
				'cta'     => __( 'Write us an email', 'contact-hub' ),
				'prefill' => true,
				'copy'    => true,
			),
			'link'      => array(
				'label' => __( 'Link', 'contact-hub' ),
				'kind'  => 'url',
				'hint'  => 'https://example.com/contact',
				'color' => '#334155',
				'icon'  => 'link',
				'cta'   => __( 'Open the contact page', 'contact-hub' ),
			),
		);
	}

	/** Convert Persian/Arabic-Indic digits to Latin digits. */
	public static function latin_digits( string $s ): string {
		return strtr(
			$s,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
	}

	/** International digits-only phone number (no plus). Iranian 09xxxxxxxxx becomes 989xxxxxxxxx. */
	public static function phone_digits( string $value ): string {
		$d = preg_replace( '/\D+/', '', self::latin_digits( $value ) );
		if ( 0 === strpos( $d, '00' ) ) {
			$d = substr( $d, 2 );
		} elseif ( preg_match( '/^09\d{9}$/', $d ) ) {
			$d = '98' . substr( $d, 1 );
		}
		return (string) apply_filters( 'contact_hub_phone_digits', $d, $value );
	}

	/** Sanitize a stored value according to the channel kind. Returns '' when invalid. */
	public static function sanitize_value( string $type, $raw ): string {
		$all = self::all();
		if ( ! isset( $all[ $type ] ) ) {
			return '';
		}
		$raw = trim( (string) $raw );

		switch ( $all[ $type ]['kind'] ) {
			case 'phone':
				$raw = self::latin_digits( $raw );
				$v   = preg_replace( '/[^\d+]/', '', $raw );
				$v   = ( '' !== $v && '+' === $v[0] ? '+' : '' ) . str_replace( '+', '', $v );
				return strlen( preg_replace( '/\D/', '', $v ) ) >= 5 ? $v : '';

			case 'username':
				if ( preg_match( '~^(?:https?://)?[a-z0-9.-]+\.[a-z]{2,}/(.+)$~i', $raw, $m ) ) {
					$raw = $m[1];
				}
				$raw = ltrim( $raw, '@' );
				$raw = (string) strtok( $raw, '?#/' );
				return preg_replace( '/[^A-Za-z0-9_.\-]/', '', $raw );

			case 'email':
				return (string) sanitize_email( $raw );

			case 'url':
				return (string) esc_url_raw( $raw, array( 'http', 'https' ) );
		}
		return '';
	}

	/**
	 * Build the deep link. $text is an optional prefilled message, $subject is used for email.
	 */
	public static function build( string $type, string $value, string $text = '', string $subject = '' ): string {
		$all = self::all();
		if ( ! isset( $all[ $type ] ) || '' === $value ) {
			return '';
		}
		$enc = 'rawurlencode';

		switch ( $type ) {
			case 'whatsapp':
				$n = self::phone_digits( $value );
				return $n ? 'https://wa.me/' . $n . ( '' !== $text ? '?text=' . $enc( $text ) : '' ) : '';
			case 'telegram':
				return 'https://t.me/' . $enc( $value );
			case 'eitaa':
				return 'https://eitaa.com/' . $enc( $value );
			case 'bale':
				return 'https://ble.ir/' . $enc( $value );
			case 'rubika':
				return 'https://rubika.ir/' . $enc( $value );
			case 'instagram':
				return 'https://ig.me/m/' . $enc( $value );
			case 'messenger':
				return 'https://m.me/' . $enc( $value );
			case 'viber':
				$n = self::phone_digits( $value );
				return $n ? 'viber://chat?number=%2B' . $n : '';
			case 'sms':
				$n = self::phone_digits( $value );
				return $n ? 'sms:+' . $n . ( '' !== $text ? '?body=' . $enc( $text ) : '' ) : '';
			case 'call':
				$n = self::phone_digits( $value );
				return $n ? 'tel:+' . $n : '';
			case 'email':
				$q = array();
				if ( '' !== $subject ) {
					$q[] = 'subject=' . $enc( $subject );
				}
				if ( '' !== $text ) {
					$q[] = 'body=' . $enc( $text );
				}
				return 'mailto:' . $value . ( $q ? '?' . implode( '&', $q ) : '' );
			case 'link':
				return $value;
		}
		return '';
	}

	/** Text shown/copied by the copy button. */
	public static function copy_value( string $type, string $value ): string {
		$all = self::all();
		if ( empty( $all[ $type ]['copy'] ) ) {
			return '';
		}
		if ( 'email' === $type ) {
			return $value;
		}
		$n = self::phone_digits( $value );
		return $n ? '+' . $n : '';
	}

	/** Inline SVG icon (static, trusted markup). Feather-style, MIT-compatible geometry. */
	public static function icon( string $name ): string {
		$p = array(
			'launch' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/><path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01"/>',
			'chat'   => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
			'send'   => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>',
			'call'   => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
			'mail'   => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="m22 6-10 7L2 6"/>',
			'sms'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
			'camera' => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
			'link'   => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
			'copy'   => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
			'chev'   => '<path d="m9 18 6-6-6-6"/>',
			'x'      => '<path d="M18 6 6 18M6 6l12 12"/>',
		);
		return '<svg class="ch-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}
}
