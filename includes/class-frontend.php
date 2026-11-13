<?php
defined( 'ABSPATH' ) || exit;

final class Contact_Hub_Frontend {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 20 );
		add_shortcode( 'contact_hub_buttons', array( __CLASS__, 'shortcode' ) );
	}

	/* ------------------------------------------------------------ visibility */

	private static function should_render( array $s ): bool {
		if ( empty( $s['enabled'] ) || empty( $s['channels'] ) ) {
			return false;
		}
		if ( is_feed() || is_embed() ) {
			return false;
		}
		if ( empty( $s['show_desktop'] ) && empty( $s['show_mobile'] ) ) {
			return false;
		}

		$ids = array_filter( array_map( 'absint', explode( ',', (string) $s['exclude_ids'] ) ) );
		$qid = (int) get_queried_object_id();
		if ( $qid && in_array( $qid, $ids, true ) ) {
			return false;
		}
		if ( is_singular() && in_array( get_post_type(), (array) $s['exclude_types'], true ) ) {
			return false;
		}
		if ( ! empty( $s['hide_wc_checkout'] ) && function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) {
			return false;
		}

		return (bool) apply_filters( 'contact_hub_should_render', true, $s );
	}

	/* --------------------------------------------------------------- assets */

	private static function register_assets(): void {
		wp_register_style( 'contact-hub', CONTACT_HUB_URL . 'assets/css/widget.css', array(), CONTACT_HUB_VERSION );
		wp_register_script(
			'contact-hub',
			CONTACT_HUB_URL . 'assets/js/widget.js',
			array(),
			CONTACT_HUB_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	public static function enqueue(): void {
		$s = Contact_Hub_Settings::get();
		if ( ! self::should_render( $s ) ) {
			return;
		}
		self::register_assets();
		wp_enqueue_style( 'contact-hub' );
		wp_enqueue_script( 'contact-hub' );

		$config = array(
			'analytics'   => ! empty( $s['analytics'] ),
			'bubbleDelay' => (int) $s['bubble_delay'],
			'bubbleKey'   => 'ch_bubble_' . substr( md5( (string) $s['bubble_text'] ), 0, 8 ),
			'i18n'        => array(
				'copied' => __( 'Copied', 'contact-hub' ),
			),
			'hours'       => null,
		);

		if ( ! empty( $s['hours_enabled'] ) ) {
			$config['hours']     = array(
				'tz'     => wp_timezone_string(),
				'offset' => (int) round( wp_timezone()->getOffset( new DateTimeImmutable( 'now' ) ) / 60 ),
				'days'   => $s['hours'],
			);
			$config['subtitles'] = array(
				'online'  => $s['online_subtitle'],
				'offline' => $s['offline_subtitle'],
			);
		}

		wp_add_inline_script( 'contact-hub', 'window.ContactHub=' . wp_json_encode( $config ) . ';', 'before' );
	}

	/* ---------------------------------------------------------------- items */

	private static function page_context(): array {
		global $wp;
		$title = wp_strip_all_tags( wp_get_document_title() );
		$url   = is_singular() ? get_permalink() : home_url( isset( $wp->request ) ? $wp->request : '' );
		return array( (string) $title, (string) $url );
	}

	private static function items( array $s ): array {
		$types           = Contact_Hub_Channels::all();
		list( $title, $url ) = self::page_context();
		$message         = str_replace( array( '{page_title}', '{page_url}' ), array( $title, $url ), (string) $s['prefill'] );
		$items           = array();

		foreach ( (array) $s['channels'] as $c ) {
			$def = $types[ $c['type'] ] ?? null;
			if ( ! $def ) {
				continue;
			}
			$text = ! empty( $def['prefill'] ) ? $message : '';
			$href = Contact_Hub_Channels::build( $c['type'], $c['value'], $text, $title );
			if ( '' === $href ) {
				continue;
			}
			$items[] = array(
				'type'     => $c['type'],
				'href'     => $href,
				'label'    => '' !== $c['label'] ? $c['label'] : $def['label'],
				'sub'      => '' !== $c['sub'] ? $c['sub'] : $def['cta'],
				'color'    => $def['color'],
				'icon'     => $def['icon'],
				'external' => (bool) preg_match( '#^https?://#i', $href ),
				'copy'     => Contact_Hub_Channels::copy_value( $c['type'], $c['value'] ),
			);
		}
		return $items;
	}

	/** "#2f3fd0" => "47,63,208" (for rgba() with a CSS variable). */
	private static function rgb( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
	}

	private static function on_color( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return ( ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255 ) > 0.62 ? '#14181f' : '#ffffff';
	}

	private static function link_attrs( array $it ): string {
		$a = ' href="' . esc_url( $it['href'], array( 'http', 'https', 'mailto', 'tel', 'sms', 'viber' ) ) . '"';
		if ( $it['external'] ) {
			$a .= ' target="_blank" rel="noopener noreferrer"';
		}
		return $a . ' data-ch-channel="' . esc_attr( $it['type'] ) . '"';
	}

	/* --------------------------------------------------------------- render */

	public static function render(): void {
		$s = Contact_Hub_Settings::get();
		if ( ! self::should_render( $s ) ) {
			return;
		}
		$items = self::items( $s );
		if ( ! $items ) {
			return;
		}

		$classes = array( 'ch-scope' );
		if ( is_rtl() ) {
			$classes[] = 'ch-rtl';
		}
		if ( empty( $s['pulse'] ) ) {
			$classes[] = 'ch-no-pulse';
		}
		if ( empty( $s['show_desktop'] ) ) {
			$classes[] = 'ch-hide-desktop';
		}
		if ( empty( $s['show_mobile'] ) ) {
			$classes[] = 'ch-hide-mobile';
		}
		$style = sprintf(
			'--ch-accent:%s;--ch-accent-rgb:%s;--ch-on-accent:%s;--ch-x:%dpx;--ch-y:%dpx;',
			$s['accent'],
			self::rgb( $s['accent'] ),
			self::on_color( $s['accent'] ),
			(int) $s['offset_x'],
			(int) $s['offset_y']
		);
		?>
		<div id="contact-hub" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-pos="<?php echo esc_attr( $s['position'] ); ?>" data-theme="<?php echo esc_attr( $s['theme'] ); ?>" data-state="closed" style="<?php echo esc_attr( $style ); ?>">
			<?php if ( (int) $s['bubble_delay'] > 0 ) : ?>
				<div class="ch-bubble" hidden>
					<button type="button" class="ch-bubble-body"><?php echo esc_html( $s['bubble_text'] ); ?></button>
					<button type="button" class="ch-bubble-x" aria-label="<?php esc_attr_e( 'Dismiss', 'contact-hub' ); ?>"><?php echo Contact_Hub_Channels::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				</div>
			<?php endif; ?>

			<section class="ch-panel" id="ch-panel" role="dialog" aria-labelledby="ch-title" inert>
				<header class="ch-head">
					<span class="ch-dot" aria-hidden="true"></span>
					<div>
						<p class="ch-title" id="ch-title"><?php echo esc_html( $s['title'] ); ?></p>
						<p class="ch-sub"><?php echo esc_html( $s['subtitle'] ); ?></p>
					</div>
				</header>
				<ul class="ch-list">
					<?php foreach ( $items as $it ) : ?>
						<li class="ch-item-row">
							<a class="ch-item"<?php echo self::link_attrs( $it ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
								<span class="ch-tile" style="--ch-c:<?php echo esc_attr( $it['color'] ); ?>"><?php echo Contact_Hub_Channels::icon( $it['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								<span class="ch-text">
									<span class="ch-label"><?php echo esc_html( $it['label'] ); ?></span>
									<span class="ch-cta"><?php echo esc_html( $it['sub'] ); ?></span>
								</span>
								<span class="ch-chev"><?php echo Contact_Hub_Channels::icon( 'chev' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							</a>
							<?php if ( '' !== $it['copy'] ) : ?>
								<button type="button" class="ch-copy" data-copy="<?php echo esc_attr( $it['copy'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: channel name */ __( 'Copy %s', 'contact-hub' ), $it['label'] ) ); ?>"><?php echo Contact_Hub_Channels::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="ch-toast" role="status" aria-live="polite"></p>
			</section>

			<button type="button" class="ch-launch" aria-expanded="false" aria-controls="ch-panel" aria-label="<?php echo esc_attr( $s['title'] ); ?>">
				<span class="ch-ico ch-ico-open"><?php echo Contact_Hub_Channels::icon( 'launch' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<span class="ch-ico ch-ico-close"><?php echo Contact_Hub_Channels::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<span class="ch-status-dot" aria-hidden="true"></span>
			</button>
		</div>
		<?php
	}

	/* ------------------------------------------------------------ shortcode */

	public static function shortcode(): string {
		$s     = Contact_Hub_Settings::get();
		$items = self::items( $s );
		if ( ! $items ) {
			return '';
		}
		self::register_assets();
		wp_enqueue_style( 'contact-hub' );
		wp_enqueue_script( 'contact-hub' );

		ob_start();
		?>
		<ul class="ch-scope ch-inline">
			<?php foreach ( $items as $it ) : ?>
				<li>
					<a class="ch-btn"<?php echo self::link_attrs( $it ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
						<span class="ch-tile" style="--ch-c:<?php echo esc_attr( $it['color'] ); ?>"><?php echo Contact_Hub_Channels::icon( $it['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<?php echo esc_html( $it['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}
}
