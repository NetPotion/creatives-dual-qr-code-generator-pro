<?php
/**
 * Plugin Name: Creatives Dual QR Code Generator Pro
 * Plugin URI: https://midstatedesign.com/
 * Description: QR code generator for WordPress, front end and back end. Admin generator + frontend shortcode. Download codes as PNG or SVG, with an optional center logo. Includes Cloudflare Turnstile CAPTCHA on the public form.
 * Version: 3.0.1
 * Author: MidState Design
 * Author URI: https://midstatedesign.com/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: creatives-dual-qr-code-generator-pro
 * Domain Path: /languages
 * Requires at least: 5.2
 * Requires PHP: 7.4
 *
 * @package Creatives_Dual_QR_Code_Generator_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CREATIVES_DQRCGP_FILE' ) ) {
	define( 'CREATIVES_DQRCGP_FILE', __FILE__ );
	define( 'CREATIVES_DQRCGP_DIR', plugin_dir_path( __FILE__ ) );
	define( 'CREATIVES_DQRCGP_URL', plugin_dir_url( __FILE__ ) );
	define( 'CREATIVES_DQRCGP_VERSION', '3.0.1' );
}

require_once CREATIVES_DQRCGP_DIR . 'includes/class-creatives-dqrcgp-logo.php';
require_once CREATIVES_DQRCGP_DIR . 'includes/class-creatives-dqrcgp-generator.php';
require_once CREATIVES_DQRCGP_DIR . 'includes/class-creatives-dqrcgp-frontend-tool.php';
require_once CREATIVES_DQRCGP_DIR . 'includes/class-creatives-dqrcgp-admin-tool.php';

if ( ! function_exists( 'creatives_dqrcgp_init' ) ) :
	/**
	 * Boot the frontend tool and the admin generator.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_init() {
		// Sites that install this by hand keep their translations in the
		// plugin's own languages folder; wordpress.org hosted plugins get
		// theirs loaded automatically, and this call is harmless there.
		load_plugin_textdomain(
			'creatives-dual-qr-code-generator-pro',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		Creatives_DQRCGP_Frontend_Tool::init();
		Creatives_DQRCGP_Admin_Tool::init();
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_credit_line' ) ) :
	/**
	 * Credit line shown at the foot of every screen this plugin renders.
	 *
	 * @return string Escaped HTML.
	 */
	function creatives_dqrcgp_credit_line() {
		return sprintf(
			'<p class="creatives-qr-credit">%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a> %4$s <span class="creatives-qr-credit-heart" style="color:#d63638;">&hearts;</span></p>',
			esc_html__( 'Crafted and offered by', 'creatives-dual-qr-code-generator-pro' ),
			esc_url( 'https://midstatedesign.com/' ),
			esc_html__( 'MidState Design', 'creatives-dual-qr-code-generator-pro' ),
			esc_html__( 'with abiding love & respect for creatives, entrepreneurs, and small businesses alike.', 'creatives-dual-qr-code-generator-pro' )
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_register_settings' ) ) :
	/**
	 * Register settings, sections and fields for the options page.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_register_settings() {
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_turnstile_enabled',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_checkbox',
				'default'           => 1,
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_turnstile_site_key',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_turnstile_secret_key',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_secret_key',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'creatives_dqrcgp_turnstile_section',
			__( 'Cloudflare Turnstile Configuration', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_turnstile_section',
			'creatives_dqrcgp_settings'
		);

		add_settings_field(
			'creatives_dqrcgp_turnstile_enabled',
			__( 'CAPTCHA', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_turnstile_enabled_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_turnstile_section'
		);

		add_settings_field(
			'creatives_dqrcgp_turnstile_site_key',
			__( 'Turnstile Site Key', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_site_key_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_turnstile_section'
		);

		// Registered after the secret itself so that options.php processes it
		// second: the clear runs against a value that has already been saved.
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_turnstile_secret_clear',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_handle_secret_clear',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		add_settings_field(
			'creatives_dqrcgp_turnstile_secret_key',
			__( 'Turnstile Secret Key', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_secret_key_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_turnstile_section'
		);

		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_logo_mode',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_logo_mode',
				'default'           => 'off',
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_logo_size',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_logo_size',
				'default'           => Creatives_DQRCGP_Logo::DEFAULT_SIZE,
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_logo_attachment_id',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_logo_attachment',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'creatives_dqrcgp_logo_section',
			__( 'Center Logo', 'creatives-dual-qr-code-generator-pro' ),
			function () {
				printf( '<p>%s</p>', esc_html__( 'Place a small square logo in the middle of generated QR codes. Codes with a logo are encoded at the highest error-correction level (H, 30% recoverable) so the covered area does not affect scanning.', 'creatives-dual-qr-code-generator-pro' ) );

				if ( ! Creatives_DQRCGP_Logo::is_supported() ) {
					printf(
						'<div class="notice notice-warning inline"><p><strong>%1$s</strong> %2$s</p></div>',
						esc_html__( 'Unavailable on this server.', 'creatives-dual-qr-code-generator-pro' ),
						esc_html__( 'The PHP GD image library is not installed, so logos cannot be composited. Ask your host to enable the gd extension.', 'creatives-dual-qr-code-generator-pro' )
					);
				}
			},
			'creatives_dqrcgp_settings'
		);

		add_settings_field(
			'creatives_dqrcgp_logo_mode',
			__( 'Logo Mode', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_logo_mode_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_logo_section'
		);

		add_settings_field(
			'creatives_dqrcgp_logo_attachment_id',
			__( 'Site Logo', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_logo_image_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_logo_section'
		);

		add_settings_field(
			'creatives_dqrcgp_logo_size',
			__( 'Logo Size', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_logo_size_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_logo_section'
		);

		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_enabled',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_checkbox',
				'default'           => 1,
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_company',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_intro',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_rich_text',
				'default'           => creatives_dqrcgp_default_terms_intro(),
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_body',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_rich_text',
				'default'           => creatives_dqrcgp_default_terms_body(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'creatives_dqrcgp_terms_section',
			__( 'Terms of Use', 'creatives-dual-qr-code-generator-pro' ),
			function () {
				printf( '<p>%s</p>', esc_html__( 'The public form can show a Terms of Use block that visitors must expand and accept before a code is generated. The fields below start out holding the wording that ships with the plugin; edit it, or clear a field to drop that part.', 'creatives-dual-qr-code-generator-pro' ) );
				printf(
					'<p>%1$s <code>{company}</code>, <code>{limit}</code> %2$s <code>{window}</code>. %3$s</p>',
					esc_html__( 'Three placeholders are replaced when the block is displayed:', 'creatives-dual-qr-code-generator-pro' ),
					esc_html__( 'and', 'creatives-dual-qr-code-generator-pro' ),
					esc_html__( 'Using them keeps the terms in step with the settings above and below.', 'creatives-dual-qr-code-generator-pro' )
				);
			},
			'creatives_dqrcgp_settings'
		);

		add_settings_field(
			'creatives_dqrcgp_terms_enabled',
			__( 'Show Terms of Use', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_terms_enabled_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_section'
		);

		add_settings_field(
			'creatives_dqrcgp_terms_company',
			__( 'Company Name', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_terms_company_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_section'
		);

		add_settings_field(
			'creatives_dqrcgp_terms_intro',
			__( 'Opening Text', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_terms_intro_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_section'
		);

		add_settings_field(
			'creatives_dqrcgp_terms_body',
			__( 'Full Terms', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_terms_body_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_terms_section'
		);

		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_show_credit',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_checkbox',
				'default'           => 1,
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'creatives_dqrcgp_display_section',
			__( 'Public Form Display', 'creatives-dual-qr-code-generator-pro' ),
			'__return_false',
			'creatives_dqrcgp_settings'
		);

		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_credit_text',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_rich_text',
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		add_settings_field(
			'creatives_dqrcgp_show_credit',
			__( 'Footer Note', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_show_credit_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_display_section'
		);

		add_settings_field(
			'creatives_dqrcgp_credit_text',
			__( 'Your Own Text', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_credit_text_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_display_section'
		);

		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_rate_limit_count',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_rate_count',
				'default'           => 5,
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_rate_limit_window',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_rate_window',
				'default'           => DAY_IN_SECONDS,
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_behind_cloudflare',
			array(
				'sanitize_callback' => 'creatives_dqrcgp_sanitize_checkbox',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'creatives_dqrcgp_rate_section',
			__( 'Rate Limiting', 'creatives-dual-qr-code-generator-pro' ),
			function () {
				printf( '<p>%s</p>', esc_html__( 'Applies to the public shortcode form only. The admin generator is not rate limited.', 'creatives-dual-qr-code-generator-pro' ) );
			},
			'creatives_dqrcgp_settings'
		);

		add_settings_field(
			'creatives_dqrcgp_rate_limit_count',
			__( 'Codes Per Visitor', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_rate_limit_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_rate_section'
		);

		add_settings_field(
			'creatives_dqrcgp_behind_cloudflare',
			__( 'Visitor IP Source', 'creatives-dual-qr-code-generator-pro' ),
			'creatives_dqrcgp_render_cloudflare_field',
			'creatives_dqrcgp_settings',
			'creatives_dqrcgp_rate_section'
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_show_credit' ) ) :
	/**
	 * Whether the public form carries the plugin credit line.
	 *
	 * Applies to the shortcode only. The admin screens keep their credit
	 * either way, since those are seen by the site owner rather than by
	 * their visitors.
	 *
	 * @return bool
	 */
	function creatives_dqrcgp_show_credit() {
		return (bool) get_option( 'creatives_dqrcgp_show_credit', 1 );
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_front_credit_html' ) ) :
	/**
	 * Markup for the note at the foot of the public form.
	 *
	 * Returns the site owner's own text when they have written some, the
	 * plugin credit when they have not, and nothing at all when the note is
	 * switched off. Their text is stored through wp_kses_post() and printed
	 * through it again, so it carries links and basic formatting but no
	 * scripts, and {company} is filled in the same way the terms are.
	 *
	 * @return string
	 */
	function creatives_dqrcgp_front_credit_html() {
		if ( ! creatives_dqrcgp_show_credit() ) {
			return '';
		}

		$custom = trim( (string) get_option( 'creatives_dqrcgp_credit_text', '' ) );

		if ( '' === $custom ) {
			return creatives_dqrcgp_credit_line();
		}

		return '<div class="creatives-qr-credit">' . creatives_dqrcgp_expand_terms_tokens( wpautop( $custom ) ) . '</div>';
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_credit_text_field' ) ) :
	/**
	 * Render the custom footer-note editor.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_credit_text_field() {
		$value = (string) get_option( 'creatives_dqrcgp_credit_text', '' );
		?>
		<textarea name="creatives_dqrcgp_credit_text" rows="4" class="large-text" placeholder="<?php echo esc_attr__( 'Questions about this tool? Email hello@example.com', 'creatives-dual-qr-code-generator-pro' ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Replaces the plugin credit with your own wording. Links and basic formatting are allowed. Leave it empty to show the plugin credit instead.', 'creatives-dual-qr-code-generator-pro' ); ?>
			<?php
			printf(
				/* translators: %s: the {company} placeholder, shown literally. */
				esc_html__( 'The %s placeholder works here too.', 'creatives-dual-qr-code-generator-pro' ),
				'<code>{company}</code>'
			);
			?>
		</p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_show_credit_field' ) ) :
	/**
	 * Render the credit-line checkbox.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_show_credit_field() {
		$enabled = (int) get_option( 'creatives_dqrcgp_show_credit', 1 );
		?>
		<label>
			<input type="checkbox" name="creatives_dqrcgp_show_credit" value="1" <?php checked( 1, $enabled ); ?> />
			<?php esc_html_e( 'Show a note at the foot of the public form', 'creatives-dual-qr-code-generator-pro' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Ticked shows a MidState Design credit, unless you fill in your own text below. Unticked, the foot of the form is empty.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_turnstile_enabled' ) ) :
	/**
	 * Whether the public form should present a CAPTCHA.
	 *
	 * Enabled means enabled AND usable: without both keys there is nothing
	 * to render and nothing to verify against, so the answer is false and
	 * the form works rather than dead-ending on a missing key.
	 *
	 * @return bool
	 */
	function creatives_dqrcgp_turnstile_enabled() {
		if ( ! get_option( 'creatives_dqrcgp_turnstile_enabled', 1 ) ) {
			return false;
		}

		return creatives_dqrcgp_turnstile_configured();
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_turnstile_configured' ) ) :
	/**
	 * Whether both Turnstile keys are present.
	 *
	 * @return bool
	 */
	function creatives_dqrcgp_turnstile_configured() {
		$site   = trim( (string) get_option( 'creatives_dqrcgp_turnstile_site_key', '' ) );
		$secret = trim( (string) get_option( 'creatives_dqrcgp_turnstile_secret_key', '' ) );

		return '' !== $site && '' !== $secret;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_secret_key' ) ) :
	/**
	 * Sanitize the Turnstile secret, treating an empty submission as
	 * "leave what is stored alone".
	 *
	 * The field is never populated with the stored value, so an empty POST
	 * is the normal case on every save that is not changing the key. Taking
	 * it literally would wipe the secret every time an unrelated setting
	 * was saved.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	function creatives_dqrcgp_sanitize_secret_key( $value ) {
		$submitted = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

		// An explicit clear, sent by the Remove button next to the field.
		if ( '__clear__' === $submitted ) {
			return '';
		}

		if ( '' === $submitted ) {
			return (string) get_option( 'creatives_dqrcgp_turnstile_secret_key', '' );
		}

		// Remember that this save carried a real key, so a removal checkbox
		// left ticked from a previous visit cannot silently discard it.
		creatives_dqrcgp_secret_was_set( true );

		return $submitted;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_secret_was_set' ) ) :
	/**
	 * Whether a new secret was submitted during this request.
	 *
	 * @param bool|null $set Internal: record that one was.
	 * @return bool
	 */
	function creatives_dqrcgp_secret_was_set( $set = null ) {
		static $was = false;

		if ( true === $set ) {
			$was = true;
		}

		return $was;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_handle_secret_clear' ) ) :
	/**
	 * Act on the "remove the saved secret key" checkbox.
	 *
	 * Reached only through options.php, which has already verified the
	 * nonce and the manage_options capability. The checkbox itself is not
	 * worth storing, so it clears the secret and always saves as 0.
	 *
	 * @param mixed $value Submitted value.
	 * @return int Always 0.
	 */
	function creatives_dqrcgp_handle_secret_clear( $value ) {
		// A key typed into the field wins over the checkbox. The two together
		// are contradictory, and discarding what someone just typed is the
		// worse reading of it.
		if ( is_scalar( $value ) && '1' === (string) $value && ! creatives_dqrcgp_secret_was_set() ) {
			// The sentinel, not an empty string. update_option() runs the
			// secret's own registered sanitize callback, and that callback
			// reads an empty submission as "keep the stored key" so that
			// saving any other setting does not wipe it. Writing '' here
			// would therefore be turned straight back into the old value
			// and the removal would silently do nothing.
			update_option( 'creatives_dqrcgp_turnstile_secret_key', '__clear__' );
		}

		return 0;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_turnstile_section' ) ) :
	/**
	 * Intro text for the Turnstile section.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_turnstile_section() {
		printf(
			'<p>%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></p>',
			esc_html__( 'Get free API keys:', 'creatives-dual-qr-code-generator-pro' ),
			esc_url( 'https://dash.cloudflare.com/' ),
			esc_html__( 'Cloudflare Dashboard', 'creatives-dual-qr-code-generator-pro' )
		);
		printf(
			'<p>%s</p>',
			esc_html__( 'Use your own Turnstile keys. They are free, and a widget is tied to the hostnames you list in the Cloudflare dashboard, so keys from another site will not work on yours.', 'creatives-dual-qr-code-generator-pro' )
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_turnstile_enabled_field' ) ) :
	/**
	 * Render the CAPTCHA on/off checkbox and its warning.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_turnstile_enabled_field() {
		$enabled = (int) get_option( 'creatives_dqrcgp_turnstile_enabled', 1 );
		?>
		<label>
			<input type="checkbox" name="creatives_dqrcgp_turnstile_enabled" value="1" <?php checked( 1, $enabled ); ?> />
			<?php esc_html_e( 'Require a Turnstile CAPTCHA on the public form', 'creatives-dual-qr-code-generator-pro' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Recommended. The public form is unauthenticated, and the CAPTCHA is the only check an attacker cannot simply script past. Rate limiting keys on IP, which a rotating proxy walks straight through.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
		<?php
		if ( ! $enabled ) {
			printf(
				'<div class="notice notice-warning inline"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'CAPTCHA is off.', 'creatives-dual-qr-code-generator-pro' ),
				esc_html__( 'Anyone can generate codes on this site as fast as the rate limit allows, from as many addresses as they control. Consider a tighter per-visitor limit below.', 'creatives-dual-qr-code-generator-pro' )
			);
		} elseif ( ! creatives_dqrcgp_turnstile_configured() ) {
			printf(
				'<div class="notice notice-warning inline"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Keys missing.', 'creatives-dual-qr-code-generator-pro' ),
				esc_html__( 'Both keys are needed before the CAPTCHA can run. Until they are set, the public form works without it.', 'creatives-dual-qr-code-generator-pro' )
			);
		}
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_site_key_field' ) ) :
	/**
	 * Render the site-key field.
	 *
	 * Autofill is refused deliberately: a text input
	 * directly above a password input reads as a login form to browser
	 * password managers, which will happily fill both with a saved
	 * credential. Saving the page then writes that credential into the
	 * options as if it were a key.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_site_key_field() {
		$value = (string) get_option( 'creatives_dqrcgp_turnstile_site_key', '' );
		?>
		<input type="text"
			id="creatives-qr-turnstile-site-key"
			name="creatives_dqrcgp_turnstile_site_key"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text code"
			autocomplete="off"
			autocorrect="off"
			autocapitalize="off"
			spellcheck="false" />
		<p class="description"><?php esc_html_e( 'Public by design. It appears in the page source wherever the form is shown.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_secret_key_field' ) ) :
	/**
	 * Render the secret-key field.
	 *
	 * Write-only: the stored secret is never printed back into the page, so
	 * it cannot be read from the DOM, from view-source, or from a saved
	 * copy of the settings screen by anyone who can open it.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_secret_key_field() {
		$stored = '' !== trim( (string) get_option( 'creatives_dqrcgp_turnstile_secret_key', '' ) );
		?>
		<input type="password"
			id="creatives-qr-turnstile-secret-key"
			name="creatives_dqrcgp_turnstile_secret_key"
			value=""
			class="regular-text code"
			autocomplete="new-password"
			autocorrect="off"
			autocapitalize="off"
			spellcheck="false"
			placeholder="<?php echo $stored ? esc_attr__( 'Saved. Type a new key to replace it.', 'creatives-dual-qr-code-generator-pro' ) : esc_attr__( 'Not set', 'creatives-dual-qr-code-generator-pro' ); ?>" />
		<?php if ( $stored ) : ?>
			<label style="display:block;margin-top:8px;">
				<input type="checkbox" name="creatives_dqrcgp_turnstile_secret_clear" value="1" />
				<?php esc_html_e( 'Remove the saved secret key', 'creatives-dual-qr-code-generator-pro' ); ?>
			</label>
		<?php endif; ?>
		<p class="description">
			<?php
			echo $stored
				? esc_html__( 'A key is saved. It is never shown again; leave this blank to keep it.', 'creatives-dual-qr-code-generator-pro' )
				: esc_html__( 'Kept in the database and never printed back to the screen.', 'creatives-dual-qr-code-generator-pro' );
			?>
		</p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_rate_windows' ) ) :
	/**
	 * Selectable rate-limit windows, keyed by length in seconds.
	 *
	 * The labels double as the {window} replacement inside the terms text,
	 * so they read as the tail of a sentence: "five codes per day".
	 *
	 * @return array<int,string>
	 */
	function creatives_dqrcgp_rate_windows() {
		return array(
			(int) HOUR_IN_SECONDS / 6 => '10 minutes',
			(int) HOUR_IN_SECONDS     => 'hour',
			(int) DAY_IN_SECONDS      => 'day',
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_checkbox' ) ) :
	/**
	 * Reduce a checkbox submission to 1 or 0.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	function creatives_dqrcgp_sanitize_checkbox( $value ) {
		return ( is_scalar( $value ) && '1' === (string) $value ) ? 1 : 0;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_rich_text' ) ) :
	/**
	 * Sanitize an editable terms field.
	 *
	 * Stored through wp_kses_post(), so the markup a site owner can save is
	 * limited to what a post can already contain. Only users with
	 * manage_options reach this callback, and the result is escaped again
	 * on output.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	function creatives_dqrcgp_sanitize_rich_text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( wp_kses_post( (string) $value ) );
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_rate_count' ) ) :
	/**
	 * Constrain the per-visitor allowance to a sane whole number.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	function creatives_dqrcgp_sanitize_rate_count( $value ) {
		$count = is_scalar( $value ) ? absint( $value ) : 5;

		if ( $count < 1 ) {
			$count = 1;
		}

		if ( $count > 1000 ) {
			$count = 1000;
		}

		return $count;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_rate_window' ) ) :
	/**
	 * Constrain the rate-limit window to one of the offered lengths.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	function creatives_dqrcgp_sanitize_rate_window( $value ) {
		$window  = is_scalar( $value ) ? absint( $value ) : 0;
		$windows = creatives_dqrcgp_rate_windows();

		return isset( $windows[ $window ] ) ? $window : (int) DAY_IN_SECONDS;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_terms_company' ) ) :
	/**
	 * The name the terms text speaks in.
	 *
	 * Falls back to the site title, so a fresh install reads correctly
	 * before anyone visits the settings screen.
	 *
	 * @return string
	 */
	function creatives_dqrcgp_terms_company() {
		$name = trim( (string) get_option( 'creatives_dqrcgp_terms_company', '' ) );

		if ( '' === $name ) {
			$name = trim( (string) get_bloginfo( 'name' ) );
		}

		return '' === $name ? 'this site' : $name;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_default_terms_intro' ) ) :
	/**
	 * Shipped opening text, shown above the expandable panel.
	 *
	 * @return string
	 */
	function creatives_dqrcgp_default_terms_intro() {
		return '<p><strong>1. Non-Competitor Agreement:</strong> You represent that you are not a competitor of {company} and are not acting on behalf of, employed by, or representing a competitor. Competitors and their representatives are not granted permission to access or use this QR Code Generator.</p>';
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_default_terms_body' ) ) :
	/**
	 * Shipped full terms, shown inside the expandable panel.
	 *
	 * @return string
	 */
	function creatives_dqrcgp_default_terms_body() {
		return '<p><strong>2. Lawful &amp; Respectful Use</strong><br>
This tool may only be used for lawful and legitimate purposes. You may not use it to generate QR codes that facilitate or promote fraud, phishing, malware distribution, impersonation, spam, copyright infringement, or any other malicious, deceptive, or illegal activity.<br>
You are solely responsible for the content, URLs, files, or other data encoded within any QR code you generate.</p>

<p><strong>3. Usage Limits &amp; Fair Use</strong><br>
You may generate up to {limit} QR codes per {window}.<br>
Use of bots, scripts, automated systems, or other methods designed to circumvent this limit or otherwise abuse the service is strictly prohibited.<br>
{company} reserves the right to temporarily or permanently restrict access to any user whose activity places an unreasonable burden on this service or otherwise interferes with its intended operation, even if the stated usage limits have not been exceeded.</p>

<p><strong>4. Verification</strong><br>
While every effort has been made to provide a reliable service, {company} does not guarantee that generated QR codes will function in every circumstance. You are responsible for verifying that each QR code works correctly before distributing, printing, or publishing it.</p>

<p><strong>5. Availability</strong><br>
This free service is provided &ldquo;as is&rdquo; and &ldquo;as available,&rdquo; without warranties of any kind, express or implied.<br>
{company} reserves the right to modify, limit, suspend, or discontinue this service, in whole or in part, at any time and without prior notice.</p>

<p><strong>6. Right to Refuse Service</strong><br>
{company} reserves the right to deny access, block users, limit usage, or terminate access to this tool at its sole discretion, including but not limited to suspected abuse, excessive use, automated access, or violations of these Terms of Use.</p>

<p><strong>7. Privacy &amp; Abuse Prevention</strong><br>
Limited technical information, including IP addresses, timestamps, browser information, and usage statistics, may be collected solely to:</p>
<ul>
<li>Enforce usage limits.</li>
<li>Detect and prevent abuse or malicious activity.</li>
<li>Maintain and improve the reliability and security of this service.</li>
</ul>
<p>This information is not sold to third parties.</p>

<p><strong>8. Commercial Use</strong><br>
This QR Code Generator is provided as a free resource for individuals, creatives, and small businesses. It may not be incorporated into another QR code generation service, offered as a competing service, resold, or used to operate a commercial QR generation platform without the express written permission of {company}.</p>

<p><strong>9. Changes to These Terms</strong><br>
{company} reserves the right to modify these Terms of Use at any time without prior notice. Continued use of this QR Code Generator after any changes have been posted constitutes your acceptance of the revised Terms of Use.</p>

<p><strong>10. Acceptance</strong><br>
By accessing or using this QR Code Generator, you acknowledge that you have read, understood, and agree to these Terms of Use. If you do not agree with these terms, you must discontinue use of this service.</p>';
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_expand_terms_tokens' ) ) :
	/**
	 * Replace the placeholders a site owner can use in the terms text.
	 *
	 * Runs after wp_kses_post() on the way in and before esc/kses on the
	 * way out, so a replacement value can never introduce markup: the
	 * company name arrives through sanitize_text_field() and the other two
	 * are generated from integers.
	 *
	 * @param string $text Stored or default terms text.
	 * @return string
	 */
	function creatives_dqrcgp_expand_terms_tokens( $text ) {
		$windows = creatives_dqrcgp_rate_windows();
		$window  = creatives_dqrcgp_sanitize_rate_window( get_option( 'creatives_dqrcgp_rate_limit_window', DAY_IN_SECONDS ) );
		$count   = creatives_dqrcgp_sanitize_rate_count( get_option( 'creatives_dqrcgp_rate_limit_count', 5 ) );

		return strtr(
			(string) $text,
			array(
				'{company}' => esc_html( creatives_dqrcgp_terms_company() ),
				'{limit}'   => number_format_i18n( $count ),
				'{window}'  => esc_html( $windows[ $window ] ),
			)
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_terms_text' ) ) :
	/**
	 * Fetch one terms field, falling back to the shipped wording.
	 *
	 * @param string $which Either 'intro' or 'body'.
	 * @return string Ready-to-output markup with tokens expanded.
	 */
	function creatives_dqrcgp_terms_text( $which ) {
		$default = 'intro' === $which
			? creatives_dqrcgp_default_terms_intro()
			: creatives_dqrcgp_default_terms_body();

		// The shipped wording is the registered default, so it is what a
		// fresh install shows in the editor. Clearing the field therefore
		// means the site owner wants that part gone, not that they want the
		// default back: an empty Full Terms drops the expandable panel, and
		// the server-side "you must expand it" check stands down with it.
		$stored = (string) get_option( 'creatives_dqrcgp_terms_' . $which, $default );

		return creatives_dqrcgp_expand_terms_tokens( trim( $stored ) );
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_terms_enabled_field' ) ) :
	/**
	 * Render the Terms of Use on/off checkbox.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_terms_enabled_field() {
		$enabled = (int) get_option( 'creatives_dqrcgp_terms_enabled', 1 );
		?>
		<label>
			<input type="checkbox" name="creatives_dqrcgp_terms_enabled" value="1" <?php checked( 1, $enabled ); ?> />
			<?php esc_html_e( 'Require visitors to accept the Terms of Use before generating a code', 'creatives-dual-qr-code-generator-pro' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Turn this off and the public form drops the terms block, the agreement checkbox and the matching server-side check. Rate limiting and the CAPTCHA are unaffected.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_terms_company_field' ) ) :
	/**
	 * Render the company-name field.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_terms_company_field() {
		$value = (string) get_option( 'creatives_dqrcgp_terms_company', '' );
		?>
		<input type="text" name="creatives_dqrcgp_terms_company" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
		<p class="description">
			<?php
			printf(
				/* translators: %s: the {company} placeholder, shown literally. */
				esc_html__( 'Replaces %s throughout the terms. Empty uses the site title.', 'creatives-dual-qr-code-generator-pro' ),
				'<code>{company}</code>'
			);
			?>
		</p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_terms_intro_field' ) ) :
	/**
	 * Render the opening-text editor.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_terms_intro_field() {
		$value = (string) get_option( 'creatives_dqrcgp_terms_intro', creatives_dqrcgp_default_terms_intro() );
		?>
		<textarea name="creatives_dqrcgp_terms_intro" rows="5" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Always visible, above the expandable panel. Clear it to show nothing there.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_terms_body_field' ) ) :
	/**
	 * Render the full-terms editor.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_terms_body_field() {
		$value = (string) get_option( 'creatives_dqrcgp_terms_body', creatives_dqrcgp_default_terms_body() );

		wp_editor(
			$value,
			'creatives_dqrcgp_terms_body',
			array(
				'textarea_name' => 'creatives_dqrcgp_terms_body',
				'textarea_rows' => 14,
				'media_buttons' => false,
				'teeny'         => true,
			)
		);
		?>
		<p class="description"><?php esc_html_e( 'Shown inside the expandable panel. Clear this and the panel disappears, along with the requirement that visitors open it before agreeing. Saved through the same filter WordPress uses for post content.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_rate_limit_field' ) ) :
	/**
	 * Render the allowance and window controls.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_rate_limit_field() {
		$count   = creatives_dqrcgp_sanitize_rate_count( get_option( 'creatives_dqrcgp_rate_limit_count', 5 ) );
		$window  = creatives_dqrcgp_sanitize_rate_window( get_option( 'creatives_dqrcgp_rate_limit_window', DAY_IN_SECONDS ) );
		$windows = creatives_dqrcgp_rate_windows();
		?>
		<input type="number" name="creatives_dqrcgp_rate_limit_count" value="<?php echo esc_attr( (string) $count ); ?>" min="1" max="1000" step="1" class="small-text" />
		<?php esc_html_e( 'per', 'creatives-dual-qr-code-generator-pro' ); ?>
		<select name="creatives_dqrcgp_rate_limit_window">
			<?php foreach ( $windows as $seconds => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $seconds ); ?>" <?php selected( $window, $seconds ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php
			printf(
				/* translators: 1: the {limit} placeholder, 2: the {window} placeholder, both shown literally. */
				esc_html__( 'Counted per visitor IP over a rolling window, not a calendar day. The %1$s and %2$s placeholders in the terms text follow whatever is set here.', 'creatives-dual-qr-code-generator-pro' ),
				'<code>{limit}</code>',
				'<code>{window}</code>'
			);
			?>
		</p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_cloudflare_field' ) ) :
	/**
	 * Render the Cloudflare real-IP checkbox.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_cloudflare_field() {
		$enabled = (int) get_option( 'creatives_dqrcgp_behind_cloudflare', 0 );
		?>
		<label>
			<input type="checkbox" name="creatives_dqrcgp_behind_cloudflare" value="1" <?php checked( 1, $enabled ); ?> />
			<?php esc_html_e( 'This site sits behind Cloudflare', 'creatives-dual-qr-code-generator-pro' ); ?>
		</label>
		<p class="description"><strong><?php esc_html_e( 'Only tick this if it is true.', 'creatives-dual-qr-code-generator-pro' ); ?></strong> <?php esc_html_e( 'It tells the rate limiter to read the visitor IP from the CF-Connecting-IP header instead of the connecting address. Behind Cloudflare without it, every visitor shares a handful of proxy addresses and they lock each other out. Not behind Cloudflare with it, anyone can send that header themselves and sidestep the limit entirely.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_logo_mode' ) ) :
	/**
	 * Constrain the logo mode to a known value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	function creatives_dqrcgp_sanitize_logo_mode( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : 'off';
		return in_array( $value, array( 'off', 'site', 'visitor' ), true ) ? $value : 'off';
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_logo_attachment' ) ) :
	/**
	 * Only accept an attachment ID that actually resolves to an image this
	 * plugin can decode. A stale or bogus ID is stored as 0 rather than
	 * left to fail silently at render time.
	 *
	 * @param mixed $value Submitted attachment ID.
	 * @return int Validated attachment ID, or 0.
	 */
	function creatives_dqrcgp_sanitize_logo_attachment( $value ) {
		$id = absint( $value );

		if ( $id <= 0 ) {
			return 0;
		}

		if ( 'attachment' !== get_post_type( $id ) ) {
			add_settings_error( 'creatives_dqrcgp_logo_attachment_id', 'not_attachment', 'The selected logo is not a media library item.' );
			return 0;
		}

		$path = get_attached_file( $id );
		$logo = $path ? Creatives_DQRCGP_Logo::from_path( $path ) : new WP_Error( 'missing', 'The selected logo file is missing.' );

		if ( is_wp_error( $logo ) ) {
			add_settings_error( 'creatives_dqrcgp_logo_attachment_id', 'bad_logo', 'Site logo not saved: ' . $logo->get_error_message() );
			return 0;
		}

		imagedestroy( $logo );

		return $id;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_sanitize_logo_size' ) ) :
	/**
	 * Constrain the logo size to a known value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	function creatives_dqrcgp_sanitize_logo_size( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : Creatives_DQRCGP_Logo::DEFAULT_SIZE;
		return isset( Creatives_DQRCGP_Logo::AREA_BUDGETS[ $value ] ) ? $value : Creatives_DQRCGP_Logo::DEFAULT_SIZE;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_logo_size_field' ) ) :
	/**
	 * Render the logo-size selector.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_logo_size_field() {
		$current = get_option( 'creatives_dqrcgp_logo_size', Creatives_DQRCGP_Logo::DEFAULT_SIZE );
		$sizes   = array(
			'small'  => array(
				'label' => __( 'Small', 'creatives-dual-qr-code-generator-pro' ),
				'desc'  => __( 'Most conservative; use for very long URLs.', 'creatives-dual-qr-code-generator-pro' ),
			),
			'medium' => array(
				'label' => __( 'Medium', 'creatives-dual-qr-code-generator-pro' ),
				'desc'  => __( 'Recommended. Comfortable margin on every code tested.', 'creatives-dual-qr-code-generator-pro' ),
			),
			'large'  => array(
				'label' => __( 'Large', 'creatives-dual-qr-code-generator-pro' ),
				'desc'  => __( 'Top of the measured-safe range. Always test-scan before printing.', 'creatives-dual-qr-code-generator-pro' ),
			),
		);

		echo '<fieldset>';
		foreach ( $sizes as $value => $meta ) {
			printf(
				'<label style="display:block;margin-bottom:8px;"><input type="radio" name="creatives_dqrcgp_logo_size" value="%1$s" %2$s> <strong>%3$s</strong> &mdash; <span class="description">%4$s</span></label>',
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $meta['label'] ),
				esc_html( $meta['desc'] )
			);
		}
		echo '</fieldset>';
		printf( '<p class="description">%s</p>', esc_html__( 'The logo is scaled to fill its space after any empty margin baked into the image file is trimmed off, and the white plate is shaped to the logo, so tall or wide marks are not shrunk to fit a square.', 'creatives-dual-qr-code-generator-pro' ) );
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_logo_mode_field' ) ) :
	/**
	 * Render the logo-mode radio group.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_logo_mode_field() {
		$mode  = get_option( 'creatives_dqrcgp_logo_mode', 'off' );
		$modes = array(
			'off'     => array(
				'label' => __( 'No logo', 'creatives-dual-qr-code-generator-pro' ),
				'desc'  => __( 'Generated QR codes stay plain.', 'creatives-dual-qr-code-generator-pro' ),
			),
			'site'    => array(
				'label' => __( 'Always use the site logo', 'creatives-dual-qr-code-generator-pro' ),
				'desc'  => __( 'Every QR code generated from the shortcode carries the logo set below. Visitors cannot upload their own.', 'creatives-dual-qr-code-generator-pro' ),
			),
			'visitor' => array(
				'label' => __( 'Let visitors upload their own logo', 'creatives-dual-qr-code-generator-pro' ),
				'desc'  => __( 'Adds an optional file field to the frontend form. Visitor uploads are validated, used to draw the code, and discarded &mdash; nothing is written to the media library or the uploads folder. If a visitor supplies no logo, the site logo below is used when one is set.', 'creatives-dual-qr-code-generator-pro' ),
			),
		);

		echo '<fieldset>';
		foreach ( $modes as $value => $meta ) {
			printf(
				'<label style="display:block;margin-bottom:10px;"><input type="radio" name="creatives_dqrcgp_logo_mode" value="%1$s" %2$s> <strong>%3$s</strong><br><span class="description" style="margin-left:24px;display:block;">%4$s</span></label>',
				esc_attr( $value ),
				checked( $mode, $value, false ),
				esc_html( $meta['label'] ),
				wp_kses_post( $meta['desc'] )
			);
		}
		echo '</fieldset>';
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_render_logo_image_field' ) ) :
	/**
	 * Render the media-library picker for the site logo.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_render_logo_image_field() {
		$attachment_id = (int) get_option( 'creatives_dqrcgp_logo_attachment_id', 0 );
		$preview       = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		?>
		<div id="creatives-qr-logo-field">
			<input type="hidden" name="creatives_dqrcgp_logo_attachment_id" id="creatives_dqrcgp_logo_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>">
			<div id="creatives-qr-logo-admin-preview" style="margin-bottom:10px;<?php echo $preview ? '' : 'display:none;'; ?>">
				<img src="<?php echo esc_url( $preview ); ?>" alt="" style="max-width:120px;max-height:120px;background:#fff;border:1px solid #dcdcde;padding:6px;">
			</div>
			<button type="button" class="button" id="creatives-qr-logo-select"><?php echo $attachment_id ? esc_html__( 'Change Logo', 'creatives-dual-qr-code-generator-pro' ) : esc_html__( 'Select Logo', 'creatives-dual-qr-code-generator-pro' ); ?></button>
			<button type="button" class="button-link" id="creatives-qr-logo-remove" style="margin-left:10px;color:#b32d2e;<?php echo $attachment_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'creatives-dual-qr-code-generator-pro' ); ?></button>
			<p class="description">
				<?php esc_html_e( "Square PNG, JPG, or WebP. A favicon or a simple mark reads best. At the Medium size it is drawn at roughly one fifth of the code's width on a white plate, so fine detail and thin text will not survive. 2 MB max.", 'creatives-dual-qr-code-generator-pro' ); ?>
			</p>
		</div>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_admin_assets' ) ) :
	/**
	 * Load each admin screen's assets on that screen only.
	 *
	 * Hook suffixes are compared against the values WordPress handed back
	 * when the pages were registered, rather than hard-coded strings that
	 * break the moment a menu title changes.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	function creatives_dqrcgp_admin_assets( $hook ) {
		$hooks = creatives_dqrcgp_page_hooks();

		if ( isset( $hooks['generate'] ) && $hook === $hooks['generate'] ) {
			Creatives_DQRCGP_Admin_Tool::enqueue_assets();
			return;
		}

		if ( ! isset( $hooks['settings'] ) || $hook !== $hooks['settings'] ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'creatives-dqrcgp-admin-generator',
			CREATIVES_DQRCGP_URL . 'assets/css/admin-generator.css',
			array(),
			CREATIVES_DQRCGP_VERSION
		);
		wp_enqueue_script(
			'creatives-dqrcgp-admin',
			CREATIVES_DQRCGP_URL . 'assets/js/admin-logo.js',
			array( 'jquery' ),
			CREATIVES_DQRCGP_VERSION,
			true
		);
		wp_localize_script(
			'creatives-dqrcgp-admin',
			'creativesDqrcgpLogoPicker',
			array(
				'i18n' => array(
					'frameTitle'  => __( 'Select QR Center Logo', 'creatives-dual-qr-code-generator-pro' ),
					'frameButton' => __( 'Use this logo', 'creatives-dual-qr-code-generator-pro' ),
					'change'      => __( 'Change Logo', 'creatives-dual-qr-code-generator-pro' ),
					'select'      => __( 'Select Logo', 'creatives-dual-qr-code-generator-pro' ),
				),
			)
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_page_hooks' ) ) :
	/**
	 * Hook suffixes of this plugin's admin screens.
	 *
	 * @param array|null $set Internal: store the suffixes at registration time.
	 * @return array
	 */
	function creatives_dqrcgp_page_hooks( $set = null ) {
		static $hooks = array();

		if ( is_array( $set ) ) {
			$hooks = $set;
		}

		return $hooks;
	}
endif;

if ( ! function_exists( 'creatives_features_register_parent_menu' ) ) :
	/**
	 * Register the shared "Creatives Features" top-level menu, once.
	 *
	 * Runs at priority 5 — before the default priority-10 admin_menu hooks
	 * most plugins (including this one) use to add their own screens — so
	 * the parent exists by the time any plugin tries to nest under it,
	 * regardless of plugin load order. Whichever Creatives plugin happens
	 * to load first in a given install is the one that actually creates
	 * the parent entry; every other plugin sees it already exists and
	 * skips straight to adding its own submenu.
	 *
	 * @return void
	 */
	function creatives_features_register_parent_menu() {
		if ( ! empty( $GLOBALS['admin_page_hooks']['creatives-features'] ) ) {
			return; // Another active Creatives plugin already registered it.
		}

		add_menu_page(
			__( 'Creatives Features', 'creatives-dual-qr-code-generator-pro' ),
			__( 'Creatives Features', 'creatives-dual-qr-code-generator-pro' ),
			'read',
			'creatives-features',
			'creatives_features_render_hub_page',
			'dashicons-star-filled',
			58
		);

		remove_submenu_page( 'creatives-features', 'creatives-features' );
	}
endif;
add_action( 'admin_menu', 'creatives_features_register_parent_menu', 5 );

if ( ! function_exists( 'creatives_features_render_hub_page' ) ) :
	/**
	 * Renders the shared "Creatives Features" landing page.
	 *
	 * Lists every active Creatives plugin's settings screen so clicking
	 * the top-level menu link itself doesn't show a blank page.
	 *
	 * @return void
	 */
	function creatives_features_render_hub_page() {
		global $submenu;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Creatives Features', 'creatives-dual-qr-code-generator-pro' ); ?></h1>
			<p><?php esc_html_e( 'Choose a plugin below to manage its settings.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
			<?php if ( ! empty( $submenu['creatives-features'] ) ) : ?>
				<ul>
					<?php foreach ( $submenu['creatives-features'] as $item ) : ?>
						<?php
						if ( ! current_user_can( $item[1] ) ) {
							continue;
						}
						?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item[2] ) ); ?>">
								<?php echo esc_html( wp_strip_all_tags( $item[0] ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No Creatives plugins are active yet.', 'creatives-dual-qr-code-generator-pro' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_add_menu' ) ) :
	/**
	 * Nest this plugin's two screens under the shared "Creatives Features"
	 * top-level menu.
	 *
	 * The generator is open to editors and up; settings stay locked to
	 * administrators.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_add_menu() {
		$generate_cap = Creatives_DQRCGP_Admin_Tool::capability();

		$generate_hook = add_submenu_page(
			'creatives-features',
			__( 'QR Code Generator', 'creatives-dual-qr-code-generator-pro' ),
			__( 'Dual QR Code Generator', 'creatives-dual-qr-code-generator-pro' ),
			$generate_cap,
			Creatives_DQRCGP_Admin_Tool::PAGE_SLUG,
			array( 'Creatives_DQRCGP_Admin_Tool', 'render_page' )
		);

		$settings_hook = add_submenu_page(
			'creatives-features',
			__( 'Creatives Dual QR Code Generator Pro', 'creatives-dual-qr-code-generator-pro' ),
			__( 'Dual QR Code Generator: Settings', 'creatives-dual-qr-code-generator-pro' ),
			'manage_options',
			'creatives-qr-pro',
			'creatives_dqrcgp_settings_page'
		);

		creatives_dqrcgp_page_hooks(
			array(
				'generate' => $generate_hook,
				'settings' => $settings_hook,
			)
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_settings_page' ) ) :
	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'creatives-dual-qr-code-generator-pro' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Creatives Dual QR Code Generator Pro', 'creatives-dual-qr-code-generator-pro' ); ?></h1>
			<?php
			// On a top-level menu page WordPress does not print these for us.
			settings_errors();
			?>
			<p>
				<?php
				printf(
					/* translators: %s: link to the admin generator screen, labelled "Generate". */
					esc_html__( 'These settings control the public form only. To make a code yourself, go to %s.', 'creatives-dual-qr-code-generator-pro' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=' . Creatives_DQRCGP_Admin_Tool::PAGE_SLUG ) ) . '">' . esc_html__( 'Generate', 'creatives-dual-qr-code-generator-pro' ) . '</a>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: the shortcode, shown literally. */
					esc_html__( 'Add the shortcode %s to any page to show the public generator.', 'creatives-dual-qr-code-generator-pro' ),
					'<code>[creatives_qr_frontend]</code>'
				);
				?>
			</p>
			<form action="options.php" method="POST">
				<?php
				settings_fields( 'creatives_dqrcgp_settings' );
				do_settings_sections( 'creatives_dqrcgp_settings' );
				submit_button();
				?>
			</form>
			<hr>
			<?php echo wp_kses_post( creatives_dqrcgp_credit_line() ); ?>
		</div>
		<?php
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_plugin_links' ) ) :
	/**
	 * Add a Settings link to the plugin row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	function creatives_dqrcgp_plugin_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . Creatives_DQRCGP_Admin_Tool::PAGE_SLUG ) ) . '">' . esc_html__( 'Generate', 'creatives-dual-qr-code-generator-pro' ) . '</a>';
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=creatives-qr-pro' ) ) . '">' . esc_html__( 'Settings', 'creatives-dual-qr-code-generator-pro' ) . '</a>';
		return $links;
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_other_tier_notice' ) ) :
	/**
	 * Advise, without enforcing, when another Creatives QR tier is active.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_other_tier_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$other_active = defined( 'CREATIVES_QRCG_FILE' ) || defined( 'CREATIVES_QRCGP_FILE' );

		if ( ! $other_active ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__( 'Another Creatives QR Code Generator plugin is also active. They will not conflict, but you likely only need one — consider deactivating the one you are not using.', 'creatives-dual-qr-code-generator-pro' )
		);
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_migrate_legacy_settings' ) ) :
	/**
	 * Copy settings forward, once, from an older install.
	 *
	 * Runs on activation. Every field is checked against two possible
	 * sources and copied from whichever has it: the option prefix used by
	 * Creatives QR Code Generator Pro (a lower tier a site may already have
	 * installed and configured a Center Logo on), and the legacy
	 * `creatives_qr_*` prefix used by the single, unsplit plugin this line
	 * was split out of — the only source for Turnstile, Terms of Use and
	 * rate-limit settings, since those exist only on this tier. Recorded
	 * with a dedicated flag so a later deactivate/reactivate does not
	 * re-copy over settings the site owner has since changed.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_migrate_legacy_settings() {
		if ( get_option( 'creatives_dqrcgp_migrated_legacy', false ) ) {
			return;
		}

		// new option => array of sources to try in order.
		$map = array(
			'creatives_dqrcgp_turnstile_enabled'    => array( 'creatives_qr_turnstile_enabled' ),
			'creatives_dqrcgp_turnstile_site_key'   => array( 'creatives_qr_turnstile_site_key' ),
			'creatives_dqrcgp_turnstile_secret_key' => array( 'creatives_qr_turnstile_secret_key' ),
			'creatives_dqrcgp_logo_mode'            => array( 'creatives_qr_logo_mode' ),
			'creatives_dqrcgp_logo_size'            => array( 'creatives_qrcgp_logo_size', 'creatives_qr_logo_size' ),
			'creatives_dqrcgp_logo_attachment_id'   => array( 'creatives_qrcgp_logo_attachment_id', 'creatives_qr_logo_attachment_id' ),
			'creatives_dqrcgp_terms_enabled'        => array( 'creatives_qr_terms_enabled' ),
			'creatives_dqrcgp_terms_company'        => array( 'creatives_qr_terms_company' ),
			'creatives_dqrcgp_terms_intro'          => array( 'creatives_qr_terms_intro' ),
			'creatives_dqrcgp_terms_body'           => array( 'creatives_qr_terms_body' ),
			'creatives_dqrcgp_show_credit'          => array( 'creatives_qr_show_credit' ),
			'creatives_dqrcgp_credit_text'          => array( 'creatives_qr_credit_text' ),
			'creatives_dqrcgp_rate_limit_count'     => array( 'creatives_qr_rate_limit_count' ),
			'creatives_dqrcgp_rate_limit_window'    => array( 'creatives_qr_rate_limit_window' ),
			'creatives_dqrcgp_behind_cloudflare'    => array( 'creatives_qr_behind_cloudflare' ),
		);

		$copied = false;

		foreach ( $map as $new_key => $sources ) {
			// Only copy into an option that has never been set on this
			// install; do not clobber a value someone already configured.
			if ( false !== get_option( $new_key, false ) ) {
				continue;
			}

			foreach ( $sources as $legacy_key ) {
				$legacy_value = get_option( $legacy_key, null );

				if ( null !== $legacy_value ) {
					update_option( $new_key, $legacy_value );
					$copied = true;
					break;
				}
			}
		}

		update_option( 'creatives_dqrcgp_migrated_legacy', 1 );

		if ( $copied ) {
			update_option( 'creatives_dqrcgp_show_migration_notice', 1 );
		}
	}
endif;

if ( ! function_exists( 'creatives_dqrcgp_migration_notice' ) ) :
	/**
	 * One-time notice confirming settings carried over from an older build.
	 *
	 * @return void
	 */
	function creatives_dqrcgp_migration_notice() {
		if ( ! get_option( 'creatives_dqrcgp_show_migration_notice', false ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		delete_option( 'creatives_dqrcgp_show_migration_notice' );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Creatives Dual QR Code Generator Pro found settings from an earlier install and carried them forward.', 'creatives-dual-qr-code-generator-pro' )
		);
	}
endif;

register_activation_hook( __FILE__, 'creatives_dqrcgp_migrate_legacy_settings' );
add_action( 'plugins_loaded', 'creatives_dqrcgp_init' );
add_action( 'admin_init', 'creatives_dqrcgp_register_settings' );
add_action( 'admin_menu', 'creatives_dqrcgp_add_menu' );
add_action( 'admin_enqueue_scripts', 'creatives_dqrcgp_admin_assets' );
add_action( 'admin_notices', 'creatives_dqrcgp_other_tier_notice' );
add_action( 'admin_notices', 'creatives_dqrcgp_migration_notice' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'creatives_dqrcgp_plugin_links' );
