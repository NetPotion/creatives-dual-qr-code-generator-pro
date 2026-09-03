<?php
/**
 * Admin-side QR generator: settings-free generation for logged-in staff.
 *
 * Same encoder, same logo pipeline and same output formats as the public
 * shortcode, minus the gates that only exist because the frontend endpoint
 * is unauthenticated: no Terms of Use, no Turnstile, no rate limit and no
 * private-network URL block. Access is controlled by capability instead.
 *
 * @package Creatives_DQRCGP_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Creatives_DQRCGP_Admin_Tool' ) ) :

	/**
	 * Admin generator screen and its AJAX endpoint.
	 */
	class Creatives_DQRCGP_Admin_Tool {

		/**
		 * Menu slug of the generator screen.
		 */
		const PAGE_SLUG = 'creatives-qr-generate';

		/**
		 * Nonce action for the admin AJAX endpoint.
		 */
		const NONCE_ACTION = 'creatives_dqrcgp_admin_generate';

		/**
		 * Capability required to open the generator and call its endpoint.
		 */
		const DEFAULT_CAPABILITY = 'edit_pages';

		/**
		 * URL schemes the generator will encode.
		 */
		const ALLOWED_SCHEMES = array( 'http', 'https', 'mailto', 'tel' );

		/**
		 * Register the AJAX endpoint.
		 *
		 * The menu entries themselves are registered by the main plugin file
		 * so both admin screens stay in one place.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'wp_ajax_' . self::NONCE_ACTION, array( __CLASS__, 'handle_ajax_generate' ) );
		}

		/**
		 * Capability required to use the generator.
		 *
		 * Filterable so a site can widen it to authors or narrow it to
		 * administrators without editing the plugin.
		 *
		 * @return string
		 */
		public static function capability() {
			$cap = apply_filters( 'creatives_dqrcgp_admin_capability', self::DEFAULT_CAPABILITY );
			return is_string( $cap ) && '' !== $cap ? $cap : self::DEFAULT_CAPABILITY;
		}

		/**
		 * Enqueue the generator screen's assets.
		 *
		 * @return void
		 */
		public static function enqueue_assets() {
			wp_enqueue_media();

			wp_enqueue_style(
				'creatives-dqrcgp-admin-generator',
				CREATIVES_DQRCGP_URL . 'assets/css/admin-generator.css',
				array(),
				CREATIVES_DQRCGP_VERSION
			);

			wp_enqueue_script(
				'creatives-dqrcgp-admin-generator',
				CREATIVES_DQRCGP_URL . 'assets/js/admin-generator.js',
				array(),
				CREATIVES_DQRCGP_VERSION,
				true
			);

			wp_localize_script(
				'creatives-dqrcgp-admin-generator',
				'creativesQrAdmin',
				array(
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'action'      => self::NONCE_ACTION,
					'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
					'logoMaxSize' => Creatives_DQRCGP_Logo::MAX_BYTES,
					'mediaTitle'  => __( 'Select center logo', 'creatives-dual-qr-code-generator-pro' ),
					'mediaButton' => __( 'Use this logo', 'creatives-dual-qr-code-generator-pro' ),
					'mediaTypes'  => self::media_library_types(),
					'i18n'        => array(
						'logoTooLarge'  => sprintf(
							/* translators: %s: maximum size in megabytes. */
							__( 'That logo is larger than %s MB. Choose a smaller file.', 'creatives-dual-qr-code-generator-pro' ),
							number_format_i18n( Creatives_DQRCGP_Logo::MAX_BYTES / 1048576 )
						),
						'logoWrongType' => __( 'Logo must be a PNG, JPG, or WebP image.', 'creatives-dual-qr-code-generator-pro' ),
						'urlRequired'   => __( 'Enter a URL first.', 'creatives-dual-qr-code-generator-pro' ),
						'failed'        => __( 'Generation failed. Try again.', 'creatives-dual-qr-code-generator-pro' ),
						'networkError'  => __( 'Network error. Try again.', 'creatives-dual-qr-code-generator-pro' ),
						'resultHeading' => __( 'Your QR code', 'creatives-dual-qr-code-generator-pro' ),
						'qrAlt'         => __( 'Generated QR code', 'creatives-dual-qr-code-generator-pro' ),
						'downloadPng'   => __( 'Download PNG', 'creatives-dual-qr-code-generator-pro' ),
						'downloadSvg'   => __( 'Download SVG', 'creatives-dual-qr-code-generator-pro' ),
					),
				)
			);
		}

		/**
		 * MIME types the media picker should offer.
		 *
		 * @return array
		 */
		private static function media_library_types() {
			$types = array( 'image/png', 'image/jpeg' );

			if ( isset( Creatives_DQRCGP_Logo::allowed_types()[ IMAGETYPE_WEBP ] ) ) {
				$types[] = 'image/webp';
			}

			return $types;
		}

		/**
		 * Render the generator screen.
		 *
		 * @return void
		 */
		public static function render_page() {
			if ( ! current_user_can( self::capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to use the QR generator.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$logo_supported = Creatives_DQRCGP_Logo::is_supported();
			$default_size   = get_option( 'creatives_dqrcgp_logo_size', Creatives_DQRCGP_Logo::DEFAULT_SIZE );
			$default_size   = isset( Creatives_DQRCGP_Logo::AREA_BUDGETS[ $default_size ] ) ? $default_size : Creatives_DQRCGP_Logo::DEFAULT_SIZE;
			$site_logo_id   = (int) get_option( 'creatives_dqrcgp_logo_attachment_id', 0 );
			$site_logo_url  = $site_logo_id ? wp_get_attachment_image_url( $site_logo_id, 'medium' ) : '';
			?>
			<div class="wrap creatives-qr-admin">
				<h1><?php esc_html_e( 'QR Code Generator', 'creatives-dual-qr-code-generator-pro' ); ?></h1>
				<p class="creatives-qr-admin-lead">
					<?php esc_html_e( 'Enter a web address, add a logo if you want one, then download your code as a PNG or SVG.', 'creatives-dual-qr-code-generator-pro' ); ?>
				</p>

				<?php if ( ! $logo_supported ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'The PHP GD image library is not installed on this server, so center logos are unavailable. Plain codes still work.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
					</div>
				<?php endif; ?>

				<div class="creatives-qr-admin-layout">
					<form id="creatives-qr-admin-form" class="creatives-qr-admin-form">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="creatives-qr-admin-url"><?php esc_html_e( 'URL or address', 'creatives-dual-qr-code-generator-pro' ); ?></label>
									</th>
									<td>
										<input type="text" id="creatives-qr-admin-url" name="url" class="regular-text code" placeholder="https://example.com" required>
										<p class="description">
											<?php esc_html_e( 'http, https, mailto and tel addresses are accepted.', 'creatives-dual-qr-code-generator-pro' ); ?>
										</p>
									</td>
								</tr>

								<?php if ( $logo_supported ) : ?>
								<tr>
									<th scope="row"><?php esc_html_e( 'Center logo', 'creatives-dual-qr-code-generator-pro' ); ?></th>
									<td>
										<fieldset class="creatives-qr-admin-fieldset">
											<label class="creatives-qr-admin-radio">
												<input type="radio" name="logo_source" value="none" checked>
												<span><?php esc_html_e( 'No logo', 'creatives-dual-qr-code-generator-pro' ); ?></span>
											</label>
											<label class="creatives-qr-admin-radio">
												<input type="radio" name="logo_source" value="library">
												<span><?php esc_html_e( 'Choose from the media library', 'creatives-dual-qr-code-generator-pro' ); ?></span>
											</label>
											<label class="creatives-qr-admin-radio">
												<input type="radio" name="logo_source" value="upload">
												<span><?php esc_html_e( 'Upload a file for this code only', 'creatives-dual-qr-code-generator-pro' ); ?></span>
											</label>
										</fieldset>

										<div class="creatives-qr-admin-logo-panel" id="creatives-qr-admin-library-panel" hidden>
											<input type="hidden" name="logo_attachment_id" id="creatives-qr-admin-attachment-id" value="<?php echo esc_attr( $site_logo_id ); ?>">
											<div class="creatives-qr-admin-logo-preview" id="creatives-qr-admin-library-preview" <?php echo $site_logo_url ? '' : 'hidden'; ?>>
												<img src="<?php echo esc_url( $site_logo_url ); ?>" alt="">
											</div>
											<button type="button" class="button" id="creatives-qr-admin-library-select">
												<?php echo $site_logo_id ? esc_html__( 'Change image', 'creatives-dual-qr-code-generator-pro' ) : esc_html__( 'Select image', 'creatives-dual-qr-code-generator-pro' ); ?>
											</button>
											<?php if ( $site_logo_id ) : ?>
												<p class="description"><?php esc_html_e( 'Starts on the site logo set in Settings. Pick a different image for a one-off code.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
											<?php endif; ?>
										</div>

										<div class="creatives-qr-admin-logo-panel" id="creatives-qr-admin-upload-panel" hidden>
											<input type="file" name="logo" id="creatives-qr-admin-logo-file" accept="<?php echo esc_attr( Creatives_DQRCGP_Logo::accept_attribute() ); ?>">
											<div class="creatives-qr-admin-logo-preview" id="creatives-qr-admin-upload-preview" hidden>
												<img src="" alt="">
											</div>
											<p class="description">
												<?php
												printf(
													/* translators: %s: comma-separated list of accepted image formats. */
													esc_html__( '%s, 2 MB max. The file is used to draw this code and is not saved to the media library.', 'creatives-dual-qr-code-generator-pro' ),
													esc_html( Creatives_DQRCGP_Logo::allowed_types_label() )
												);
												?>
											</p>
										</div>
									</td>
								</tr>

								<tr class="creatives-qr-admin-size-row" hidden>
									<th scope="row"><?php esc_html_e( 'Logo size', 'creatives-dual-qr-code-generator-pro' ); ?></th>
									<td>
										<fieldset class="creatives-qr-admin-fieldset">
											<?php
											$sizes = array(
												'small'  => __( 'Small — most conservative; use for very long URLs.', 'creatives-dual-qr-code-generator-pro' ),
												'medium' => __( 'Medium — recommended. Comfortable margin on every code tested.', 'creatives-dual-qr-code-generator-pro' ),
												'large'  => __( 'Large — top of the measured-safe range. Test-scan before printing.', 'creatives-dual-qr-code-generator-pro' ),
											);
											foreach ( $sizes as $value => $desc ) :
												?>
												<label class="creatives-qr-admin-radio">
													<input type="radio" name="logo_size" value="<?php echo esc_attr( $value ); ?>" <?php checked( $default_size, $value ); ?>>
													<span><?php echo esc_html( $desc ); ?></span>
												</label>
											<?php endforeach; ?>
										</fieldset>
									</td>
								</tr>
								<?php endif; ?>
							</tbody>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary" id="creatives-qr-admin-submit">
								<?php esc_html_e( 'Generate QR Code', 'creatives-dual-qr-code-generator-pro' ); ?>
							</button>
							<span class="spinner" id="creatives-qr-admin-spinner"></span>
						</p>
					</form>

					<div class="creatives-qr-admin-output">
						<div class="notice notice-error inline creatives-qr-admin-error" id="creatives-qr-admin-error" hidden><p></p></div>
						<div id="creatives-qr-admin-result" class="creatives-qr-admin-result"></div>
					</div>
				</div>
				<?php echo wp_kses_post( creatives_dqrcgp_credit_line() ); ?>
			</div>
			<?php
		}

		/**
		 * AJAX endpoint: generate a code for a logged-in, capable user.
		 *
		 * @return void
		 */
		public static function handle_ajax_generate() {
			if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed. Reload the page and try again.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			if ( ! current_user_can( self::capability() ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to generate QR codes.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			if ( ! isset( $_POST['url'] ) ) {
				wp_send_json_error( array( 'message' => __( 'A URL is required.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			$url = trim( sanitize_text_field( wp_unslash( $_POST['url'] ) ) );

			if ( '' === $url ) {
				wp_send_json_error( array( 'message' => __( 'A URL is required.', 'creatives-dual-qr-code-generator-pro' ) ) );
				return;
			}

			$scheme = strtolower( (string) strstr( $url, ':', true ) );

			/**
			 * Filters the URL schemes the admin generator will encode.
			 *
			 * @param array $schemes Lowercase scheme names.
			 */
			$schemes = apply_filters( 'creatives_dqrcgp_admin_allowed_schemes', self::ALLOWED_SCHEMES );
			$schemes = is_array( $schemes ) ? $schemes : self::ALLOWED_SCHEMES;

			if ( '' === $scheme || ! in_array( $scheme, $schemes, true ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: comma-separated list of accepted URL schemes. */
							__( 'Include a scheme this tool accepts: %s.', 'creatives-dual-qr-code-generator-pro' ),
							implode( ', ', array_map( 'strval', $schemes ) )
						),
					)
				);
				return;
			}

			$valid = self::is_valid_address( $url, $scheme );

			if ( is_wp_error( $valid ) ) {
				wp_send_json_error( array( 'message' => $valid->get_error_message() ) );
				return;
			}

			// Deliberately no private-network check here. An administrator
			// generating a code for 192.168.1.1 or a .local host is a normal
			// thing to do; that block exists for the public endpoint only.
			$logo = self::resolve_logo();

			if ( is_wp_error( $logo ) ) {
				wp_send_json_error( array( 'message' => $logo->get_error_message() ) );
				return;
			}

			$size = isset( $_POST['logo_size'] ) ? sanitize_key( wp_unslash( $_POST['logo_size'] ) ) : Creatives_DQRCGP_Logo::DEFAULT_SIZE;
			$size = isset( Creatives_DQRCGP_Logo::AREA_BUDGETS[ $size ] ) ? $size : Creatives_DQRCGP_Logo::DEFAULT_SIZE;

			$qr_data = self::generate_qr( $url, $logo, $size );

			if ( $logo ) {
				imagedestroy( $logo );
			}

			if ( is_wp_error( $qr_data ) ) {
				wp_send_json_error( array( 'message' => $qr_data->get_error_message() ) );
				return;
			}

			wp_send_json_success(
				array(
					'png'      => $qr_data['png'],
					'svg'      => $qr_data['svg'],
					'url'      => $url,
					'filename' => self::filename_for( $url ),
				)
			);
		}

		/**
		 * Validate an address against the rules for its own scheme.
		 *
		 * FILTER_VALIDATE_URL alone is the wrong test here: it rejects
		 * perfectly good tel: numbers for having no host, and it accepts
		 * plenty of things that are not addresses at all.
		 *
		 * @param string $url    Address as typed.
		 * @param string $scheme Lowercase scheme already pulled off the front.
		 * @return true|WP_Error
		 */
		private static function is_valid_address( $url, $scheme ) {
			$body = substr( $url, strlen( $scheme ) + 1 );

			if ( 'mailto' === $scheme ) {
				$address = strtok( ltrim( $body, '/' ), '?' );

				if ( ! $address || ! is_email( $address ) ) {
					return new WP_Error( 'bad_email', __( 'Enter a valid email address, for example mailto:hello@example.com.', 'creatives-dual-qr-code-generator-pro' ) );
				}

				return true;
			}

			if ( 'tel' === $scheme ) {
				$number = trim( $body );

				if ( ! preg_match( '/^\+?[0-9][0-9\-.() ]{2,30}$/', $number ) ) {
					return new WP_Error( 'bad_tel', __( 'Enter a valid phone number, for example tel:+13205551234.', 'creatives-dual-qr-code-generator-pro' ) );
				}

				return true;
			}

			$parsed = wp_parse_url( $url );

			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) || empty( $parsed['host'] ) ) {
				return new WP_Error( 'bad_url', __( 'That does not look like a valid address.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			return true;
		}

		/**
		 * Decide which logo, if any, this request should use.
		 *
		 * Unlike the frontend, nothing here falls back to the site logo: the
		 * user picked a source on the form, so a failure is reported rather
		 * than quietly substituted.
		 *
		 * @return resource|GdImage|WP_Error|null
		 */
		private static function resolve_logo() {
			// Nonce and capability are both verified at the top of
			// handle_ajax_generate(), the only caller.
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$source = isset( $_POST['logo_source'] ) ? sanitize_key( wp_unslash( $_POST['logo_source'] ) ) : 'none';

			if ( ! in_array( $source, array( 'none', 'library', 'upload' ), true ) || 'none' === $source ) {
				return null;
			}

			if ( ! Creatives_DQRCGP_Logo::is_supported() ) {
				return new WP_Error( 'logo_unsupported', __( 'This server cannot add a logo to QR codes (the GD image library is unavailable).', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( 'library' === $source ) {
				$attachment_id = isset( $_POST['logo_attachment_id'] ) ? absint( wp_unslash( $_POST['logo_attachment_id'] ) ) : 0;
				return self::logo_from_attachment( $attachment_id );
			}

			if ( ! isset( $_FILES['logo'] ) || ! is_array( $_FILES['logo'] ) ) {
				return new WP_Error( 'logo_missing', __( 'Choose a logo file, or switch the logo option to "No logo".', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$uploaded = $_FILES['logo']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( isset( $uploaded['error'] ) && is_scalar( $uploaded['error'] ) && UPLOAD_ERR_NO_FILE === (int) $uploaded['error'] ) {
				return new WP_Error( 'logo_missing', __( 'Choose a logo file, or switch the logo option to "No logo".', 'creatives-dual-qr-code-generator-pro' ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			return Creatives_DQRCGP_Logo::from_upload( $uploaded );
		}

		/**
		 * Load a logo from a media library attachment.
		 *
		 * Full-size originals often exceed the 2 MB working limit. Rather
		 * than refusing a perfectly usable image, the largest generated
		 * size that fits is used instead — the logo is drawn at a few
		 * hundred pixels anyway.
		 *
		 * @param int $attachment_id Attachment post ID.
		 * @return resource|GdImage|WP_Error
		 */
		private static function logo_from_attachment( $attachment_id ) {
			$attachment_id = absint( $attachment_id );

			if ( $attachment_id <= 0 ) {
				return new WP_Error( 'logo_missing', __( 'Select an image from the media library, or switch the logo option to "No logo".', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error( 'logo_not_image', __( 'That media item is not an image.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$path = get_attached_file( $attachment_id );

			if ( ! $path || ! is_readable( $path ) ) {
				return new WP_Error( 'logo_unreadable', __( 'The selected image file is missing from the server.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( filesize( $path ) > Creatives_DQRCGP_Logo::MAX_BYTES ) {
				$smaller = self::smaller_attachment_file( $attachment_id, $path );

				if ( '' !== $smaller ) {
					$path = $smaller;
				}
			}

			return Creatives_DQRCGP_Logo::from_path( $path );
		}

		/**
		 * Largest generated size of an attachment that fits the working limit.
		 *
		 * @param int    $attachment_id Attachment post ID.
		 * @param string $full_path     Absolute path to the original file.
		 * @return string Absolute path, or an empty string when none fits.
		 */
		private static function smaller_attachment_file( $attachment_id, $full_path ) {
			$meta = wp_get_attachment_metadata( $attachment_id );

			if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
				return '';
			}

			$dir   = dirname( $full_path );
			$best  = '';
			$width = 0;

			foreach ( $meta['sizes'] as $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) || ! is_string( $size['file'] ) ) {
					continue;
				}

				$candidate = $dir . '/' . wp_basename( $size['file'] );

				if ( ! is_readable( $candidate ) || filesize( $candidate ) > Creatives_DQRCGP_Logo::MAX_BYTES ) {
					continue;
				}

				$candidate_width = isset( $size['width'] ) ? (int) $size['width'] : 0;

				if ( $candidate_width > $width ) {
					$width = $candidate_width;
					$best  = $candidate;
				}
			}

			return $best;
		}

		/**
		 * Build a download filename stem from the encoded address.
		 *
		 * @param string $url Encoded URL.
		 * @return string
		 */
		private static function filename_for( $url ) {
			$parsed = wp_parse_url( $url );
			$parts  = array();

			if ( ! empty( $parsed['host'] ) ) {
				$parts[] = $parsed['host'];
			}

			if ( ! empty( $parsed['path'] ) ) {
				$parts[] = $parsed['path'];
			}

			$stem = sanitize_title( implode( '-', $parts ) );

			if ( '' === $stem ) {
				return 'qr-code';
			}

			return 'qr-' . substr( $stem, 0, 60 );
		}

		/**
		 * Render both output formats for an address.
		 *
		 * @param string                $url  Address to encode.
		 * @param resource|GdImage|null $logo Optional centre logo.
		 * @param string                $size Logo size key.
		 * @return array|WP_Error
		 */
		private static function generate_qr( $url, $logo, $size ) {
			if ( ! class_exists( 'Creatives_DQRCGP_Generator' ) ) {
				return new WP_Error( 'no_class', __( 'QR generator class not found.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			try {
				$gen = new Creatives_DQRCGP_Generator();
				$gen->set_logo( $logo, $size );

				return array(
					'png' => $gen->generate_png_data_uri( $url ),
					'svg' => $gen->generate_svg_data_uri( $url ),
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'generation_failed', __( 'That address could not be encoded as a QR code (it may be too long).', 'creatives-dual-qr-code-generator-pro' ) );
			}
		}
	}

endif;
