<?php
defined( 'ABSPATH' ) || exit;

final class Contact_Hub_Settings {

	const OPTION = 'contact_hub_settings';
	const SLUG   = 'contact-hub';
	const GROUP  = 'contact_hub';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CONTACT_HUB_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/* ------------------------------------------------------------------ data */

	/** Stored defaults. Text fields stay empty so translated fallbacks follow the site language. */
	public static function defaults(): array {
		return array(
			'enabled'          => 1,
			'title'            => '',
			'subtitle'         => '',
			'online_subtitle'  => '',
			'offline_subtitle' => '',
			'bubble_text'      => '',
			'bubble_delay'     => 8,
			'prefill'          => '',
			'position'         => 'right',
			'offset_x'         => 20,
			'offset_y'         => 20,
			'accent'           => '#2f3fd0',
			'theme'            => 'light',
			'pulse'            => 1,
			'channels'         => array(),
			'hours_enabled'    => 0,
			'hours'            => self::default_hours(),
			'show_desktop'     => 1,
			'show_mobile'      => 1,
			'exclude_ids'      => '',
			'exclude_types'    => array(),
			'hide_wc_checkout' => 1,
			'analytics'        => 1,
		);
	}

	/** Translated fallbacks used when a text field is left empty. */
	public static function text_defaults(): array {
		return array(
			'title'            => __( 'Need help?', 'contact-hub' ),
			'subtitle'         => __( 'Pick a channel and we will get back to you.', 'contact-hub' ),
			'online_subtitle'  => __( "We're online and ready to reply.", 'contact-hub' ),
			'offline_subtitle' => __( "We're away right now. Leave a message and we'll reply when we're back.", 'contact-hub' ),
			'bubble_text'      => __( 'Questions? Message us here.', 'contact-hub' ),
			'prefill'          => __( "Hi! I'm on {page_title} ({page_url}) and have a question.", 'contact-hub' ),
		);
	}

	public static function default_hours(): array {
		$h = array();
		foreach ( array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' ) as $k ) {
			$h[ $k ] = array(
				'open' => in_array( $k, array( 'sat', 'sun', 'mon', 'tue', 'wed', 'thu' ), true ) ? 1 : 0,
				'from' => '09:00',
				'to'   => 'thu' === $k ? '13:00' : '17:00',
			);
		}
		return $h;
	}

	/** Weekday keys => localized names, starting at the site's start_of_week. */
	public static function days(): array {
		global $wp_locale;
		$map   = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
		$start = (int) get_option( 'start_of_week', 1 );
		$out   = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$idx          = ( $start + $i ) % 7;
			$out[ $map[ $idx ] ] = $wp_locale->get_weekday( $idx );
		}
		return $out;
	}

	/** Saved values merged over defaults, without translated text fallbacks (for the admin form). */
	public static function raw(): array {
		$saved = get_option( self::OPTION, array() );
		$s     = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		$s['hours']    = array_replace_recursive( self::default_hours(), is_array( $s['hours'] ) ? $s['hours'] : array() );
		$s['channels'] = is_array( $s['channels'] ) ? $s['channels'] : array();
		return $s;
	}

	/** Effective settings with translated fallbacks applied (for the front end). */
	public static function get(): array {
		$s = self::raw();
		foreach ( self::text_defaults() as $k => $v ) {
			if ( '' === trim( (string) $s[ $k ] ) ) {
				$s[ $k ] = $v;
			}
		}
		return $s;
	}

	/* -------------------------------------------------------------- sanitize */

	public static function sanitize( $in ): array {
		$in  = is_array( $in ) ? $in : array(); // options.php has already unslashed the input.
		$d   = self::defaults();
		$out = array();

		$out['enabled'] = empty( $in['enabled'] ) ? 0 : 1;

		foreach ( array( 'title', 'subtitle', 'online_subtitle', 'offline_subtitle', 'bubble_text' ) as $k ) {
			$out[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
		}
		$out['prefill'] = sanitize_textarea_field( $in['prefill'] ?? '' );

		$out['bubble_delay'] = min( 120, max( 0, absint( $in['bubble_delay'] ?? $d['bubble_delay'] ) ) );
		$out['position']     = ( isset( $in['position'] ) && 'left' === $in['position'] ) ? 'left' : 'right';
		$out['offset_x']     = min( 200, absint( $in['offset_x'] ?? $d['offset_x'] ) );
		$out['offset_y']     = min( 200, absint( $in['offset_y'] ?? $d['offset_y'] ) );
		$out['accent']       = sanitize_hex_color( $in['accent'] ?? '' ) ?: $d['accent'];
		$out['pulse']        = empty( $in['pulse'] ) ? 0 : 1;
		$out['theme']        = in_array( $in['theme'] ?? '', array( 'light', 'dark', 'auto' ), true ) ? $in['theme'] : 'light';

		// Channels.
		$types    = Contact_Hub_Channels::all();
		$channels = array();
		foreach ( (array) ( $in['channels'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = sanitize_key( $row['type'] ?? '' );
			if ( ! isset( $types[ $type ] ) ) {
				continue;
			}
			$value = Contact_Hub_Channels::sanitize_value( $type, $row['value'] ?? '' );
			if ( '' === $value ) {
				continue;
			}
			$channels[] = array(
				'type'  => $type,
				'value' => $value,
				'label' => sanitize_text_field( $row['label'] ?? '' ),
				'sub'   => sanitize_text_field( $row['sub'] ?? '' ),
			);
		}
		$out['channels'] = array_slice( $channels, 0, 12 );

		// Business hours.
		$out['hours_enabled'] = empty( $in['hours_enabled'] ) ? 0 : 1;
		$out['hours']         = array();
		$time                 = '/^([01]\d|2[0-3]):[0-5]\d$/';
		foreach ( self::default_hours() as $k => $def ) {
			$row  = $in['hours'][ $k ] ?? array();
			$from = isset( $row['from'] ) && preg_match( $time, $row['from'] ) ? $row['from'] : $def['from'];
			$to   = isset( $row['to'] ) && preg_match( $time, $row['to'] ) ? $row['to'] : $def['to'];
			$out['hours'][ $k ] = array(
				'open' => empty( $row['open'] ) ? 0 : 1,
				'from' => $from,
				'to'   => $to,
			);
		}

		// Display rules.
		$out['show_desktop'] = empty( $in['show_desktop'] ) ? 0 : 1;
		$out['show_mobile']  = empty( $in['show_mobile'] ) ? 0 : 1;

		$ids                = preg_split( '/[\s,]+/', Contact_Hub_Channels::latin_digits( (string) ( $in['exclude_ids'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
		$out['exclude_ids'] = implode( ',', array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		$public               = array_keys( get_post_types( array( 'public' => true ) ) );
		$out['exclude_types'] = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['exclude_types'] ?? array() ) ), $public ) );

		$out['hide_wc_checkout'] = empty( $in['hide_wc_checkout'] ) ? 0 : 1;
		$out['analytics']        = empty( $in['analytics'] ) ? 0 : 1;

		return $out;
	}

	/* ----------------------------------------------------------------- admin */

	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function menu(): void {
		add_options_page(
			__( 'Contact Hub', 'contact-hub' ),
			__( 'Contact Hub', 'contact-hub' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function action_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'contact-hub' ) . '</a>' );
		return $links;
	}

	public static function assets( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'contact-hub-admin', CONTACT_HUB_URL . 'assets/css/admin.css', array(), CONTACT_HUB_VERSION );
		wp_enqueue_script( 'contact-hub-admin', CONTACT_HUB_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), CONTACT_HUB_VERSION, true );
	}

	private static function name( string $key ): string {
		return self::OPTION . '[' . $key . ']';
	}

	private static function text_field( array $s, string $key, string $label, string $desc = '' ): void {
		$ph = self::text_defaults()[ $key ] ?? '';
		?>
		<div class="ch-field">
			<label for="ch-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" class="large-text" id="ch-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="<?php echo esc_attr( $ph ); ?>">
			<?php if ( $desc ) : ?>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function check( array $s, string $key, string $label, string $desc = '' ): void {
		?>
		<div class="ch-field ch-check">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?>>
				<?php echo esc_html( $label ); ?>
			</label>
			<?php if ( $desc ) : ?>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function channel_row( $i, array $c ): void {
		$types = Contact_Hub_Channels::all();
		$cur   = $types[ $c['type'] ] ?? $types['whatsapp'];
		$base  = self::OPTION . '[channels][' . $i . ']';
		?>
		<li class="ch-chan">
			<span class="ch-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'contact-hub' ); ?>"></span>
			<select class="ch-type" name="<?php echo esc_attr( $base ); ?>[type]" aria-label="<?php esc_attr_e( 'Channel', 'contact-hub' ); ?>">
				<?php foreach ( $types as $key => $t ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" data-hint="<?php echo esc_attr( $t['hint'] ); ?>" data-label="<?php echo esc_attr( $t['label'] ); ?>" data-cta="<?php echo esc_attr( $t['cta'] ); ?>" <?php selected( $c['type'], $key ); ?>><?php echo esc_html( $t['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" class="ch-val" dir="ltr" name="<?php echo esc_attr( $base ); ?>[value]" value="<?php echo esc_attr( $c['value'] ); ?>" placeholder="<?php echo esc_attr( $cur['hint'] ); ?>" aria-label="<?php esc_attr_e( 'Number, username or address', 'contact-hub' ); ?>">
			<input type="text" class="ch-lab" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="<?php echo esc_attr( $cur['label'] ); ?>" aria-label="<?php esc_attr_e( 'Custom label', 'contact-hub' ); ?>">
			<input type="text" class="ch-sublab" name="<?php echo esc_attr( $base ); ?>[sub]" value="<?php echo esc_attr( $c['sub'] ); ?>" placeholder="<?php echo esc_attr( $cur['cta'] ); ?>" aria-label="<?php esc_attr_e( 'Custom description', 'contact-hub' ); ?>">
			<button type="button" class="button-link ch-remove" aria-label="<?php esc_attr_e( 'Remove channel', 'contact-hub' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</li>
		<?php
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = self::raw();
		$days = self::days();
		?>
		<div class="wrap ch-admin">
			<h1><?php esc_html_e( 'Contact Hub', 'contact-hub' ); ?></h1>

			<?php if ( empty( $s['channels'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Add at least one channel below. The widget stays hidden until you do.', 'contact-hub' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<section class="ch-card">
					<h2><?php esc_html_e( 'Widget', 'contact-hub' ); ?></h2>
					<?php self::check( $s, 'enabled', __( 'Show the widget on my site', 'contact-hub' ) ); ?>
					<?php self::text_field( $s, 'title', __( 'Title', 'contact-hub' ) ); ?>
					<?php self::text_field( $s, 'subtitle', __( 'Subtitle', 'contact-hub' ), __( 'Shown under the title. Replaced by the online/offline messages when business hours are on.', 'contact-hub' ) ); ?>
				</section>

				<section class="ch-card">
					<h2><?php esc_html_e( 'Channels', 'contact-hub' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Drag to reorder. Leave label and description empty to use the defaults. Phone numbers accept Persian digits; 09121234567 is converted to the international format automatically.', 'contact-hub' ); ?></p>
					<ul id="ch-channels">
						<?php foreach ( $s['channels'] as $i => $c ) : ?>
							<?php self::channel_row( $i, $c ); ?>
						<?php endforeach; ?>
					</ul>
					<p><button type="button" class="button" id="ch-add"><?php esc_html_e( 'Add channel', 'contact-hub' ); ?></button></p>
					<script type="text/template" id="ch-row-tpl">
						<?php self::channel_row( '__INDEX__', array( 'type' => 'whatsapp', 'value' => '', 'label' => '', 'sub' => '' ) ); ?>
					</script>

					<div class="ch-field">
						<label for="ch-prefill"><?php esc_html_e( 'Prefilled message', 'contact-hub' ); ?></label>
						<textarea id="ch-prefill" class="large-text" rows="2" name="<?php echo esc_attr( self::name( 'prefill' ) ); ?>" placeholder="<?php echo esc_attr( self::text_defaults()['prefill'] ); ?>"><?php echo esc_textarea( $s['prefill'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Used by WhatsApp, SMS and Email. Placeholders: {page_title} and {page_url}.', 'contact-hub' ); ?></p>
					</div>
				</section>

				<section class="ch-card">
					<h2><?php esc_html_e( 'Appearance', 'contact-hub' ); ?></h2>
					<div class="ch-grid">
						<div class="ch-field">
							<span class="ch-lbl"><?php esc_html_e( 'Position', 'contact-hub' ); ?></span>
							<label><input type="radio" name="<?php echo esc_attr( self::name( 'position' ) ); ?>" value="right" <?php checked( $s['position'], 'right' ); ?>> <?php esc_html_e( 'Right', 'contact-hub' ); ?></label>
							<label><input type="radio" name="<?php echo esc_attr( self::name( 'position' ) ); ?>" value="left" <?php checked( $s['position'], 'left' ); ?>> <?php esc_html_e( 'Left', 'contact-hub' ); ?></label>
						</div>
						<div class="ch-field">
							<label for="ch-theme"><?php esc_html_e( 'Color mode', 'contact-hub' ); ?></label>
							<select id="ch-theme" name="<?php echo esc_attr( self::name( 'theme' ) ); ?>">
								<option value="light" <?php selected( $s['theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'contact-hub' ); ?></option>
								<option value="dark" <?php selected( $s['theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'contact-hub' ); ?></option>
								<option value="auto" <?php selected( $s['theme'], 'auto' ); ?>><?php esc_html_e( "Follow the visitor's device", 'contact-hub' ); ?></option>
							</select>
						</div>
						<div class="ch-field">
							<label for="ch-accent"><?php esc_html_e( 'Accent color', 'contact-hub' ); ?></label>
							<input type="text" class="ch-color" id="ch-accent" name="<?php echo esc_attr( self::name( 'accent' ) ); ?>" value="<?php echo esc_attr( $s['accent'] ); ?>" data-default-color="#2f3fd0">
						</div>
						<div class="ch-field">
							<label for="ch-offset_x"><?php esc_html_e( 'Distance from the side (px)', 'contact-hub' ); ?></label>
							<input type="number" min="0" max="200" id="ch-offset_x" name="<?php echo esc_attr( self::name( 'offset_x' ) ); ?>" value="<?php echo esc_attr( $s['offset_x'] ); ?>">
						</div>
						<div class="ch-field">
							<label for="ch-offset_y"><?php esc_html_e( 'Distance from the bottom (px)', 'contact-hub' ); ?></label>
							<input type="number" min="0" max="200" id="ch-offset_y" name="<?php echo esc_attr( self::name( 'offset_y' ) ); ?>" value="<?php echo esc_attr( $s['offset_y'] ); ?>">
						</div>
					</div>
					<?php self::check( $s, 'pulse', __( 'Pulsing ring around the button', 'contact-hub' ), __( 'A soft attention-grabbing animation. It stops on hover, when the panel is open, and for visitors who prefer reduced motion.', 'contact-hub' ) ); ?>
					<?php self::text_field( $s, 'bubble_text', __( 'Welcome bubble text', 'contact-hub' ) ); ?>
					<div class="ch-field">
						<label for="ch-bubble_delay"><?php esc_html_e( 'Show the welcome bubble after (seconds)', 'contact-hub' ); ?></label>
						<input type="number" min="0" max="120" id="ch-bubble_delay" name="<?php echo esc_attr( self::name( 'bubble_delay' ) ); ?>" value="<?php echo esc_attr( $s['bubble_delay'] ); ?>">
						<p class="description"><?php esc_html_e( 'Shown once per visitor. Set 0 to turn the bubble off.', 'contact-hub' ); ?></p>
					</div>
				</section>

				<section class="ch-card">
					<h2><?php esc_html_e( 'Business hours', 'contact-hub' ); ?></h2>
					<?php self::check( $s, 'hours_enabled', __( 'Show an online/offline status', 'contact-hub' ), sprintf( /* translators: %s: timezone name */ __( 'Uses your site timezone (%s). Change it in Settings → General.', 'contact-hub' ), wp_timezone_string() ) ); ?>
					<table class="ch-hours">
						<?php foreach ( $days as $key => $label ) : ?>
							<?php $h = $s['hours'][ $key ]; ?>
							<tr>
								<th scope="row"><label><input type="checkbox" name="<?php echo esc_attr( self::name( 'hours' ) . '[' . $key . '][open]' ); ?>" value="1" <?php checked( ! empty( $h['open'] ) ); ?>> <?php echo esc_html( $label ); ?></label></th>
								<td>
									<input type="time" name="<?php echo esc_attr( self::name( 'hours' ) . '[' . $key . '][from]' ); ?>" value="<?php echo esc_attr( $h['from'] ); ?>" aria-label="<?php esc_attr_e( 'Opens at', 'contact-hub' ); ?>">
									–
									<input type="time" name="<?php echo esc_attr( self::name( 'hours' ) . '[' . $key . '][to]' ); ?>" value="<?php echo esc_attr( $h['to'] ); ?>" aria-label="<?php esc_attr_e( 'Closes at', 'contact-hub' ); ?>">
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php self::text_field( $s, 'online_subtitle', __( 'Message while online', 'contact-hub' ) ); ?>
					<?php self::text_field( $s, 'offline_subtitle', __( 'Message while offline', 'contact-hub' ) ); ?>
				</section>

				<section class="ch-card">
					<h2><?php esc_html_e( 'Where to show it', 'contact-hub' ); ?></h2>
					<?php self::check( $s, 'show_desktop', __( 'Show on desktop and tablets', 'contact-hub' ) ); ?>
					<?php self::check( $s, 'show_mobile', __( 'Show on phones', 'contact-hub' ) ); ?>
					<div class="ch-field">
						<span class="ch-lbl"><?php esc_html_e( 'Hide on these content types', 'contact-hub' ); ?></span>
						<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) : ?>
							<label class="ch-inline-check"><input type="checkbox" name="<?php echo esc_attr( self::name( 'exclude_types' ) ); ?>[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $s['exclude_types'], true ) ); ?>> <?php echo esc_html( $pt->labels->name ); ?></label>
						<?php endforeach; ?>
					</div>
					<div class="ch-field">
						<label for="ch-exclude_ids"><?php esc_html_e( 'Hide on these page IDs', 'contact-hub' ); ?></label>
						<input type="text" class="regular-text" dir="ltr" id="ch-exclude_ids" name="<?php echo esc_attr( self::name( 'exclude_ids' ) ); ?>" value="<?php echo esc_attr( $s['exclude_ids'] ); ?>" placeholder="12, 48, 103">
						<p class="description"><?php esc_html_e( 'Comma-separated post or page IDs.', 'contact-hub' ); ?></p>
					</div>
					<?php self::check( $s, 'hide_wc_checkout', __( 'Hide on the WooCommerce cart and checkout', 'contact-hub' ) ); ?>
				</section>

				<section class="ch-card">
					<h2><?php esc_html_e( 'Analytics', 'contact-hub' ); ?></h2>
					<?php self::check( $s, 'analytics', __( 'Send click events to Google Tag Manager / Google Analytics', 'contact-hub' ), __( 'Pushes a contact_hub_click event to dataLayer and a contact_click event to gtag, if they exist on your site. Nothing is sent to us or anyone else.', 'contact-hub' ) ); ?>
					<p class="description"><?php esc_html_e( 'Want the buttons inside a page? Use the shortcode [contact_hub_buttons].', 'contact-hub' ); ?></p>
				</section>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
