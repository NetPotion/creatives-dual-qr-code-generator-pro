<?php
/**
 * Frontend QR generator: shortcode, asset loading and the AJAX endpoint.
 *
 * @package Creatives_DQRCGP_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Creatives_DQRCGP_Frontend_Tool' ) ) :

	/**
	 * Shortcode, asset loading and AJAX handling for the public generator.
	 */
	class Creatives_DQRCGP_Frontend_Tool {

		/**
		 * Register the shortcode, assets and AJAX handlers.
		 *
		 * @return void
		 */
		public static function init() {
			add_shortcode( 'creatives_qr_frontend', array( __CLASS__, 'render_shortcode' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'wp_ajax_creatives_dqrcgp_generate', array( __CLASS__, 'handle_ajax_generate' ) );
			add_action( 'wp_ajax_nopriv_creatives_dqrcgp_generate', array( __CLASS__, 'handle_ajax_generate' ) );
		}

		/**
		 * Current centre-logo mode.
		 *
		 * 'off'     — no logo is ever added.
		 * 'site'    — every frontend QR code gets the administrator's logo.
		 * 'visitor' — visitors may upload their own; the administrator's
		 *             logo, if set, is used when they don't.
		 *
		 * @return string
		 */
		public static function logo_mode() {
			$mode = get_option( 'creatives_dqrcgp_logo_mode', 'off' );

			if ( ! in_array( $mode, array( 'off', 'site', 'visitor' ), true ) ) {
				return 'off';
			}

			if ( 'off' !== $mode && ! Creatives_DQRCGP_Logo::is_supported() ) {
				return 'off';
			}

			return $mode;
		}

		/**
		 * Configured centre-logo size key.
		 *
		 * @return string
		 */
		/**
		 * Whether the public form shows and enforces a Terms of Use block.
		 *
		 * @return bool
		 */
		public static function terms_enabled() {
			return (bool) get_option( 'creatives_dqrcgp_terms_enabled', 1 );
		}

		/**
		 * The address this request is rate limited against.
		 *
		 * CF-Connecting-IP is only honoured when the site owner has stated
		 * that the site is behind Cloudflare. The header is trivial to send
		 * by hand, so trusting it unconditionally would turn the rate limit
		 * into a formality; ignoring it on a site that really is proxied
		 * lumps every visitor onto a handful of shared addresses.
		 *
		 * @return string Client IP, or an empty string when undeterminable.
		 */
		private static function client_ip() {
			if ( get_option( 'creatives_dqrcgp_behind_cloudflare', 0 ) && isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
				$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );

				if ( filter_var( $forwarded, FILTER_VALIDATE_IP ) ) {
					return $forwarded;
				}
			}

			$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

			return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
		}

		/**
		 * The per-visitor allowance and the window it is counted over.
		 *
		 * @return array{count:int,window:int,label:string}
		 */
		private static function rate_limit_settings() {
			$windows = creatives_dqrcgp_rate_windows();
			$window  = creatives_dqrcgp_sanitize_rate_window( get_option( 'creatives_dqrcgp_rate_limit_window', DAY_IN_SECONDS ) );
			$count   = creatives_dqrcgp_sanitize_rate_count( get_option( 'creatives_dqrcgp_rate_limit_count', 5 ) );

			return array(
				'count'  => $count,
				'window' => $window,
				'label'  => $windows[ $window ],
			);
		}

		/**
		 * The configured logo size, constrained to a known budget key.
		 *
		 * @return string
		 */
		public static function logo_size() {
			$size = get_option( 'creatives_dqrcgp_logo_size', Creatives_DQRCGP_Logo::DEFAULT_SIZE );
			return isset( Creatives_DQRCGP_Logo::AREA_BUDGETS[ $size ] ) ? $size : Creatives_DQRCGP_Logo::DEFAULT_SIZE;
		}

		/**
		 * Render the frontend generator form.
		 *
		 * @return string Shortcode markup.
		 */
		public static function render_shortcode() {
			$captcha  = creatives_dqrcgp_turnstile_enabled();
			$site_key = $captcha ? (string) get_option( 'creatives_dqrcgp_turnstile_site_key', '' ) : '';

			// A half-configured CAPTCHA used to replace the whole form with
			// an error, telling every visitor that the site's administrator
			// had not finished setting it up. The form now works without the
			// CAPTCHA, and only someone who can fix it is told anything.
			if ( ! $captcha && get_option( 'creatives_dqrcgp_turnstile_enabled', 1 ) && current_user_can( 'manage_options' ) ) {
				$notice = sprintf(
					'<p style="color:#8a6d3b;background:#fcf8e3;border:1px solid #faebcc;padding:10px 14px;border-radius:4px;"><strong>%1$s</strong> %2$s</p>',
					esc_html__( 'Visible to administrators only:', 'creatives-dual-qr-code-generator-pro' ),
					esc_html__( 'the Turnstile CAPTCHA is switched on but its keys are missing, so the form below is running without it.', 'creatives-dual-qr-code-generator-pro' )
				);
			} else {
				$notice = '';
			}

			$nonce     = wp_create_nonce( 'creatives_dqrcgp_frontend_nonce' );
			$logo_mode = self::logo_mode();

			// When the terms block is switched off, or its expandable panel
			// is empty, the matching gate in handle_ajax_generate() stands
			// down too. A form that cannot satisfy its own server check
			// would just be a broken form.
			$terms_enabled = self::terms_enabled();
			$terms_intro   = $terms_enabled ? creatives_dqrcgp_terms_text( 'intro' ) : '';
			$terms_body    = $terms_enabled ? creatives_dqrcgp_terms_text( 'body' ) : '';
			ob_start(); ?>
		<div class="creatives-qr-frontend-wrapper">
			<?php echo wp_kses_post( $notice ); ?>
			<form id="creatives-qr-frontend-form" class="creatives-qr-form">
				<div class="creatives-qr-form-group">
					<label for="creatives-qr-url"><?php esc_html_e( 'Enter URL:', 'creatives-dual-qr-code-generator-pro' ); ?></label>
					<input type="url" id="creatives-qr-url" name="url" placeholder="<?php echo esc_attr__( 'https://example.com', 'creatives-dual-qr-code-generator-pro' ); ?>" required>
				</div>
				<?php if ( 'visitor' === $logo_mode ) : ?>
				<div class="creatives-qr-form-group creatives-qr-logo-group">
					<label for="creatives-qr-logo"><?php esc_html_e( 'Center logo', 'creatives-dual-qr-code-generator-pro' ); ?> <span class="creatives-qr-optional"><?php esc_html_e( '(optional)', 'creatives-dual-qr-code-generator-pro' ); ?></span></label>
					<input type="file" id="creatives-qr-logo" name="logo" accept="<?php echo esc_attr( Creatives_DQRCGP_Logo::accept_attribute() ); ?>">
					<p class="creatives-qr-logo-hint">
						<?php
						printf(
							/* translators: 1: accepted image formats, 2: maximum size in megabytes. */
							esc_html__( 'A square logo or favicon works best. %1$s, %2$s MB max. Your file is used to draw the code and is not stored on this site.', 'creatives-dual-qr-code-generator-pro' ),
							esc_html( Creatives_DQRCGP_Logo::allowed_types_label() ),
							esc_html( number_format_i18n( Creatives_DQRCGP_Logo::MAX_BYTES / 1048576 ) )
						);
						?>
					</p>
					<div class="creatives-qr-logo-preview" id="creatives-qr-logo-preview" hidden>
						<img src="" alt="" id="creatives-qr-logo-preview-img">
						<button type="button" class="creatives-qr-logo-clear" id="creatives-qr-logo-clear"><?php esc_html_e( 'Remove logo', 'creatives-dual-qr-code-generator-pro' ); ?></button>
					</div>
				</div>
				<?php endif; ?>
				<?php if ( $captcha ) : ?>
				<div class="creatives-qr-turnstile-wrapper">
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
				</div>
				<?php endif; ?>
				<?php if ( $terms_enabled ) : ?>
				<div class="creatives-qr-terms">
					<p class="creatives-qr-terms-heading"><?php esc_html_e( 'Terms of Use', 'creatives-dual-qr-code-generator-pro' ); ?></p>
					<p class="creatives-qr-terms-lead">
						<?php
						printf(
							/* translators: %s: company or site name. */
							esc_html__( 'By accessing or using the %s QR Code Generator, you agree to these Terms of Use.', 'creatives-dual-qr-code-generator-pro' ),
							esc_html( creatives_dqrcgp_terms_company() )
						);
						?>
					</p>
					<div class="creatives-qr-terms-intro"><?php echo wp_kses_post( $terms_intro ); ?></div>
					<?php if ( '' !== $terms_body ) : ?>
					<details class="creatives-qr-terms-details" id="creatives-qr-terms-details">
						<summary><?php esc_html_e( 'Read the rest of the Terms of Use', 'creatives-dual-qr-code-generator-pro' ); ?></summary>
						<div class="creatives-qr-terms-scroll"><?php echo wp_kses_post( $terms_body ); ?></div>
					</details>
					<?php endif; ?>
					<label class="creatives-qr-terms-checkbox">
						<input type="checkbox" id="creatives-qr-terms-agree" name="terms_agree" value="1" required<?php disabled( '' !== $terms_body ); ?>>
						<span><?php esc_html_e( 'I agree to the Terms of Use above.', 'creatives-dual-qr-code-generator-pro' ); ?></span>
					</label>
					<input type="hidden" name="terms_viewed" id="creatives-qr-terms-viewed" value="<?php echo '' === $terms_body ? '1' : '0'; ?>">
				</div>
				<?php endif; ?>
				<button type="submit" class="creatives-qr-submit-btn"><?php esc_html_e( 'Generate QR Code', 'creatives-dual-qr-code-generator-pro' ); ?></button>
				<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
			</form>
			<div id="creatives-qr-error" class="creatives-qr-error" style="display:none;"></div>
			<div id="creatives-qr-result" class="creatives-qr-result"></div>
			<?php echo wp_kses_post( creatives_dqrcgp_front_credit_html() ); ?>
		</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Enqueue frontend assets — only on singular content that actually
		 * contains the shortcode. Guards against get_post() returning null,
		 * which happens in some page-builder preview/AJAX contexts
		 * (Elementor, Divi) even when is_singular() reports true.
		 */
		public static function enqueue_assets() {
			if ( ! is_singular() ) {
				return;
			}

			$post = get_post();

			if ( ! ( $post instanceof WP_Post ) ) {
				return;
			}

			if ( ! has_shortcode( $post->post_content, 'creatives_qr_frontend' ) ) {
				return;
			}

			$captcha = creatives_dqrcgp_turnstile_enabled();
			$deps    = array();

			if ( $captcha ) {
				// Official Cloudflare Turnstile script. Not loaded at all when
				// the CAPTCHA is off, so a site that does not use it makes no
				// third-party request and drops no third-party cookie.
				// Vendor-hosted script: Cloudflare versions the endpoint itself, so
				// appending our plugin version would only break their caching.
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
				$deps[] = 'cf-turnstile';
			}

			wp_enqueue_style( 'creatives-dqrcgp-css', CREATIVES_DQRCGP_URL . 'assets/css/frontend-qr.css', array(), CREATIVES_DQRCGP_VERSION );

			wp_enqueue_script( 'creatives-dqrcgp-js', CREATIVES_DQRCGP_URL . 'assets/js/frontend-qr.js', $deps, CREATIVES_DQRCGP_VERSION, true );

			wp_localize_script(
				'creatives-dqrcgp-js',
				'creativesDqrcgpFrontend',
				array(
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'action'      => 'creatives_dqrcgp_generate',
					'logoMode'    => self::logo_mode(),
					'captcha'     => $captcha,
					'logoMaxSize' => Creatives_DQRCGP_Logo::MAX_BYTES,
					'i18n'        => array(
						'logoTooLarge'    => sprintf(
							/* translators: %s: maximum size in megabytes. */
							__( 'That logo is larger than %s MB. Please choose a smaller file.', 'creatives-dual-qr-code-generator-pro' ),
							number_format_i18n( Creatives_DQRCGP_Logo::MAX_BYTES / 1048576 )
						),
						'logoWrongType'   => __( 'Logo must be a PNG, JPG, or WebP image.', 'creatives-dual-qr-code-generator-pro' ),
						'logoPreview'     => __( 'Selected logo preview', 'creatives-dual-qr-code-generator-pro' ),
						'invalidUrl'      => __( 'Please enter a valid URL (e.g., https://example.com)', 'creatives-dual-qr-code-generator-pro' ),
						'termsUnread'     => __( 'Please expand and review the full Terms of Use before continuing.', 'creatives-dual-qr-code-generator-pro' ),
						'termsUnchecked'  => __( 'Please agree to the Terms of Use to generate a QR code.', 'creatives-dual-qr-code-generator-pro' ),
						'captchaMissing'  => __( 'CAPTCHA not loaded. Please refresh and try again.', 'creatives-dual-qr-code-generator-pro' ),
						'captchaUnsolved' => __( 'Please complete the CAPTCHA verification.', 'creatives-dual-qr-code-generator-pro' ),
						'genericError'    => __( 'An error occurred. Please try again.', 'creatives-dual-qr-code-generator-pro' ),
						'networkError'    => __( 'Network error. Please try again.', 'creatives-dual-qr-code-generator-pro' ),
						'generating'      => __( 'Generating...', 'creatives-dual-qr-code-generator-pro' ),
						'generate'        => __( 'Generate QR Code', 'creatives-dual-qr-code-generator-pro' ),
						'resultHeading'   => __( 'QR Code Generated', 'creatives-dual-qr-code-generator-pro' ),
						'urlLabel'        => __( 'URL: ', 'creatives-dual-qr-code-generator-pro' ),
						'qrAlt'           => __( 'Generated QR Code', 'creatives-dual-qr-code-generator-pro' ),
						'downloadPng'     => __( 'Download PNG', 'creatives-dual-qr-code-generator-pro' ),
						'downloadSvg'     => __( 'Download SVG', 'creatives-dual-qr-code-generator-pro' ),
						'again'           => __( 'Generate another QR code', 'creatives-dual-qr-code-generator-pro' ),
					),
				)
			);
		}

		/**
		 * AJAX endpoint: validate the request and return the generated code.
		 *
		 * @return void
		 */
		public static function handle_ajax_generate() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'creatives_dqrcgp_frontend_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			if ( self::terms_enabled() ) {
				if ( ! isset( $_POST['terms_agree'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['terms_agree'] ) ) ) {
					wp_send_json_error( array( 'message' => __( 'You must agree to the Terms of Use to generate a QR code.', 'creatives-dual-qr-code-generator-pro' ) ) );
					return;
				}

				// Only demanded when there is an expandable panel to open.
				$has_full_terms = '' !== creatives_dqrcgp_terms_text( 'body' );

				if ( $has_full_terms && ( ! isset( $_POST['terms_viewed'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['terms_viewed'] ) ) ) ) {
					wp_send_json_error( array( 'message' => __( 'Please expand and review the full Terms of Use before continuing.', 'creatives-dual-qr-code-generator-pro' ) ) );
					return;
				}
			}

			if ( ! isset( $_POST['url'] ) ) {
				wp_send_json_error( array( 'message' => __( 'URL is required.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			$url = sanitize_text_field( wp_unslash( $_POST['url'] ) );

			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			if ( self::is_private_url( $url ) ) {
				wp_send_json_error( array( 'message' => __( 'URLs pointing to internal networks are not allowed.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			$rate_check = self::check_rate_limit();
			if ( is_wp_error( $rate_check ) ) {
				wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
				return;
			}

			// Checked server-side against the stored settings, not against
			// anything the request claims: a client cannot opt out of the
			// CAPTCHA by omitting its token.
			if ( creatives_dqrcgp_turnstile_enabled() ) {
				if ( ! isset( $_POST['cf-turnstile-response'] ) ) {
					wp_send_json_error( array( 'message' => __( 'CAPTCHA token missing.', 'creatives-dual-qr-code-generator-pro' ) ) );
					return;
				}

				$captcha_check = self::verify_turnstile( sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) );
				if ( is_wp_error( $captcha_check ) ) {
					wp_send_json_error( array( 'message' => $captcha_check->get_error_message() ) );
					return;
				}
			}

			$logo = self::resolve_logo();
			if ( is_wp_error( $logo ) ) {
				wp_send_json_error( array( 'message' => $logo->get_error_message() ) );
				return;
			}

			$qr_data = self::generate_qr( $url, $logo );

			// The logo only ever lives in memory for the duration of this
			// request. Free it as soon as the code has been drawn.
			if ( $logo ) {
				imagedestroy( $logo );
			}

			if ( is_wp_error( $qr_data ) ) {
				wp_send_json_error( array( 'message' => $qr_data->get_error_message() ) );
				return;
			}

			self::increment_rate_limit();
			wp_send_json_success(
				array(
					'png' => $qr_data['png'],
					'svg' => $qr_data['svg'],
					'url' => esc_url( $url ),
				)
			);
		}

		/**
		 * Decide which logo, if any, this request should use.
		 *
		 * A visitor upload that fails validation is a hard error — the
		 * visitor asked for that logo, and quietly handing back a plain
		 * code would look like the feature is broken. A misconfigured
		 * site logo is not the visitor's problem, so that degrades to a
		 * plain code instead.
		 *
		 * @return resource|GdImage|WP_Error|null
		 */
		private static function resolve_logo() {
			$mode = self::logo_mode();

			if ( 'off' === $mode ) {
				return null;
			}

			// Nonce is verified at the top of handle_ajax_generate(), which is
			// the only caller; the upload itself is validated and re-encoded in
			// Creatives_DQRCGP_Logo before any byte of it is used.
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( 'visitor' === $mode && isset( $_FILES['logo'] ) && is_array( $_FILES['logo'] ) ) {
				$uploaded = $_FILES['logo']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				if ( isset( $uploaded['error'] ) && is_scalar( $uploaded['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $uploaded['error'] ) {
					return Creatives_DQRCGP_Logo::from_upload( $uploaded );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$site_logo = Creatives_DQRCGP_Logo::from_site_setting();

			if ( is_wp_error( $site_logo ) ) {
				return null;
			}

			return $site_logo;
		}

		/**
		 * Whether a URL resolves to a private or reserved address.
		 *
		 * @param string $url URL to test.
		 * @return bool
		 */
		private static function is_private_url( $url ) {
			$parsed = wp_parse_url( $url );

			if ( ! isset( $parsed['host'] ) || '' === $parsed['host'] ) {
				return true;
			}

			// Strip the brackets from an IPv6 literal host ([::1]), and the
			// trailing dot of an absolute name (192.168.1.1. resolves the
			// same as 192.168.1.1, and would otherwise skip the check).
			$host = strtolower( trim( $parsed['host'], '[]' ) );
			$host = rtrim( $host, '.' );

			if ( '' === $host ) {
				return true;
			}

			if ( 'localhost' === $host || '.localhost' === substr( $host, -10 ) || '.local' === substr( $host, -6 ) ) {
				return true;
			}

			// Only address literals are judged. Earlier releases also
			// resolved hostnames with gethostbyname(), which blocked on the
			// resolver for every request carrying a valid nonce, ahead of
			// the rate limit and the CAPTCHA, on a public endpoint. This
			// plugin encodes the URL and never fetches it, so the check is
			// a policy against printing internal addresses rather than a
			// fetch guard, and a DNS lookup buys nothing worth that cost.
			if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				return ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
			}

			// http://2130706433/, http://0177.0.0.1/ and http://0x7f000001/
			// all reach 127.0.0.1 in a browser, and none of them satisfies
			// FILTER_VALIDATE_IP. Normalise every numeric form to a packed
			// address before deciding, or the check is trivially bypassed.
			$packed = self::numeric_host_to_long( $host );

			if ( null === $packed ) {
				return false;
			}

			return self::is_reserved_ipv4( $packed );
		}

		/**
		 * Convert a numeric IPv4 host to its 32-bit value.
		 *
		 * Accepts the dotted quad plus the shorthand and non-decimal forms
		 * every browser and resolver still honours: one, two, three or four
		 * parts, each written in decimal, octal (leading zero) or hex
		 * (leading 0x), with the final part absorbing the remaining bytes.
		 *
		 * @param string $host Lowercased host, brackets already stripped.
		 * @return int|null Packed address, or null when the host is not numeric.
		 */
		private static function numeric_host_to_long( $host ) {
			$parts = explode( '.', $host );
			$count = count( $parts );

			if ( $count > 4 ) {
				return null;
			}

			$values = array();

			foreach ( $parts as $part ) {
				if ( '' === $part ) {
					return null;
				}

				if ( preg_match( '/^0x[0-9a-f]+$/', $part ) ) {
					$value = hexdec( substr( $part, 2 ) );
				} elseif ( preg_match( '/^0[0-7]+$/', $part ) ) {
					$value = octdec( substr( $part, 1 ) );
				} elseif ( preg_match( '/^[0-9]+$/', $part ) ) {
					$value = (int) $part;
				} else {
					return null; // Contains a letter: an ordinary hostname.
				}

				$values[] = $value;
			}

			// Every part but the last is one byte; the last absorbs the rest.
			$last  = array_pop( $values );
			$bytes = count( $values );
			$max   = pow( 256, 4 - $bytes );

			if ( $last < 0 || $last >= $max ) {
				return null;
			}

			$long = (int) $last;

			foreach ( array_reverse( $values ) as $index => $value ) {
				if ( $value < 0 || $value > 255 ) {
					return null;
				}

				$long += $value * pow( 256, 4 - $bytes + $index );
			}

			return $long;
		}

		/**
		 * Whether a packed IPv4 address falls in a private or reserved range.
		 *
		 * Covers PHP's own private and reserved sets plus carrier-grade NAT
		 * (100.64.0.0/10), which PHP does not treat as reserved.
		 *
		 * @param int $long Packed address.
		 * @return bool
		 */
		private static function is_reserved_ipv4( $long ) {
			$dotted = long2ip( $long );

			if ( ! $dotted ) {
				return true; // Unrepresentable: refuse rather than guess.
			}

			if ( ! filter_var( $dotted, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return true;
			}

			return ( $long & 0xFFC00000 ) === 0x64400000;
		}

		/**
		 * Whether this client is still within its generation allowance.
		 *
		 * @return true|WP_Error
		 */
		private static function check_rate_limit() {
			$ip = self::client_ip();
			if ( empty( $ip ) ) {
				return new WP_Error( 'invalid_ip', __( 'Unable to determine client IP.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$limit  = self::rate_limit_settings();
			$key    = 'creatives_dqrcgp_limit_' . md5( $ip );
			$window = self::read_window( $key, $limit['window'] );
			$count  = $window['count'];

			if ( $count >= $limit['count'] ) {
				return new WP_Error(
					'rate_limit',
					sprintf(
						/* translators: 1: number of codes allowed, 2: window label such as "day". */
						__( 'Rate limit exceeded. Maximum %1$s per %2$s.', 'creatives-dual-qr-code-generator-pro' ),
						number_format_i18n( $limit['count'] ),
						$limit['label']
					)
				);
			}

			return true;
		}

		/**
		 * Record one generation against this client's allowance.
		 *
		 * @return void
		 */
		private static function increment_rate_limit() {
			$ip = self::client_ip();
			if ( empty( $ip ) ) {
				return;
			}

			$limit  = self::rate_limit_settings();
			$key    = 'creatives_dqrcgp_limit_' . md5( $ip );
			$window = self::read_window( $key, $limit['window'] );

			// The window is deliberately not extended on each hit: it starts
			// at the visitor's first code and runs its length, so nobody is
			// locked out for longer than the window itself.
			$elapsed = time() - $window['start'];
			$ttl     = $limit['window'] - $elapsed;

			if ( $ttl < 1 ) {
				$ttl = $limit['window'];
			}

			set_transient(
				$key,
				array(
					'count' => $window['count'] + 1,
					'start' => $window['start'],
				),
				$ttl
			);
		}

		/**
		 * Read a visitor's current window, normalising whatever is stored.
		 *
		 * The window start is carried inside the transient rather than read
		 * back from a _transient_timeout_ option. That option only exists
		 * when transients live in the database; on a site with a persistent
		 * object cache there is nothing to read, and the window silently
		 * slid forward on every request instead of running from the first
		 * code. Integer values are what releases before 2.6.7 stored.
		 *
		 * @param string $key    Transient key.
		 * @param int    $length Configured window length in seconds.
		 * @return array{count:int,start:int}
		 */
		private static function read_window( $key, $length ) {
			$stored = get_transient( $key );
			$now    = time();

			if ( is_array( $stored ) && isset( $stored['count'], $stored['start'] ) ) {
				$start = (int) $stored['start'];

				// A start in the future, or older than the window, means a
				// stale or tampered row. Begin a fresh window rather than
				// trusting it.
				if ( $start > $now || $start < ( $now - $length ) ) {
					return array(
						'count' => 0,
						'start' => $now,
					);
				}

				return array(
					'count' => max( 0, (int) $stored['count'] ),
					'start' => $start,
				);
			}

			if ( is_numeric( $stored ) ) {
				return array(
					'count' => max( 0, (int) $stored ),
					'start' => $now,
				);
			}

			return array(
				'count' => 0,
				'start' => $now,
			);
		}

		/**
		 * Verify a Cloudflare Turnstile token with the siteverify endpoint.
		 *
		 * @param string $token Token supplied by the widget.
		 * @return true|WP_Error
		 */
		private static function verify_turnstile( $token ) {
			$secret = get_option( 'creatives_dqrcgp_turnstile_secret_key', '' );
			if ( empty( $secret ) ) {
				return new WP_Error( 'no_secret', __( 'Turnstile secret not configured.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$remote_ip = self::client_ip();

			// Official Cloudflare Turnstile siteverify endpoint.
			$response = wp_remote_post(
				'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				array(
					'body'      => array(
						'secret'   => $secret,
						'response' => $token,
						'remoteip' => $remote_ip,
					),
					'sslverify' => true,
					'timeout'   => 5,
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'request_failed',
					sprintf(
						/* translators: %s: error text returned by the HTTP request. */
						__( 'CAPTCHA request failed: %s', 'creatives-dual-qr-code-generator-pro' ),
						$response->get_error_message()
					)
				);
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				return new WP_Error(
					'request_failed',
					sprintf(
						/* translators: %s: HTTP status code. */
						__( 'CAPTCHA service returned an unexpected response (HTTP %s).', 'creatives-dual-qr-code-generator-pro' ),
						$response_code
					)
				);
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $body ) || ! isset( $body['success'] ) || ! $body['success'] ) {
				$error_codes = ( isset( $body['error-codes'] ) && is_array( $body['error-codes'] ) )
				? implode( ', ', $body['error-codes'] )
				: 'unknown';
				return new WP_Error(
					'validation_failed',
					sprintf(
						/* translators: %s: error codes returned by Turnstile. */
						__( 'CAPTCHA validation failed (%s).', 'creatives-dual-qr-code-generator-pro' ),
						$error_codes
					)
				);
			}

			return true;
		}

		/**
		 * Render both output formats for a URL.
		 *
		 * @param string                $url  URL to encode.
		 * @param resource|GdImage|null $logo Optional centre logo.
		 * @return array|WP_Error
		 */
		private static function generate_qr( $url, $logo = null ) {
			if ( ! class_exists( 'Creatives_DQRCGP_Generator' ) ) {
				return new WP_Error( 'no_class', __( 'QR generator class not found.', 'creatives-dual-qr-code-generator-pro' ) );
			}
			try {
				$gen = new Creatives_DQRCGP_Generator();
				$gen->set_logo( $logo, self::logo_size() );
				return array(
					'png' => $gen->generate_png_data_uri( $url ),
					'svg' => $gen->generate_svg_data_uri( $url ),
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'generation_failed', __( 'Failed to generate QR code.', 'creatives-dual-qr-code-generator-pro' ) );
			}
		}
	}

endif;
