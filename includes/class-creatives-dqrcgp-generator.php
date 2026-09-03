<?php
/**
 * QR Code Generator — fully local, no external API calls.
 *
 * @package Creatives_DQRCGP_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Creatives_DQRCGP_Generator' ) ) :

	/**
	 * Encodes URLs as QR codes and renders them to PNG or SVG data URIs.
	 */
	class Creatives_DQRCGP_Generator {

		/**
		 * Pixel size of one QR module (before margin) for raster output.
		 */
		const MODULE_SIZE = 8;

		/**
		 * Quiet-zone margin in pixels for raster (PNG) output.
		 */
		const MARGIN = 16;

		/**
		 * SVG units per module.
		 */
		const SVG_MODULE_SIZE = 4;

		/**
		 * SVG units of quiet zone.
		 */
		const SVG_MARGIN = 8;

		/**
		 * Logo image (GD resource) to composite in the centre, or null.
		 *
		 * @var resource|GdImage|null
		 */
		private $logo = null;

		/**
		 * Logo size setting key ('small', 'medium' or 'large').
		 *
		 * @var string
		 */
		private $logo_size = Creatives_DQRCGP_Logo::DEFAULT_SIZE;

		/**
		 * Set the centre logo for subsequent generate_* calls.
		 *
		 * Passing a logo also raises the error-correction level from M
		 * (15% recoverable) to H (30%), which is what makes covering the
		 * middle of the code safe. Without that bump, a logo overlay is
		 * a coin flip on real-world scanners.
		 *
		 * @param resource|GdImage|null $logo GD image, or null to clear.
		 * @param string                $size Size setting key.
		 * @return void
		 */
		public function set_logo( $logo, $size = Creatives_DQRCGP_Logo::DEFAULT_SIZE ) {
			$this->logo      = $logo ? $logo : null;
			$this->logo_size = $size;
		}

		/**
		 * Whether a logo is currently set.
		 *
		 * @return bool
		 */
		public function has_logo() {
			return null !== $this->logo;
		}

		/**
		 * Generate QR code as PNG data URI.
		 *
		 * @param string $url URL to encode.
		 * @return string PNG as data URI.
		 * @throws Exception If encoding fails.
		 */
		public function generate_png_data_uri( $url ) {
			if ( ! extension_loaded( 'gd' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
				// No GD available — fall back to SVG output even though a PNG was requested,
				// since a data-URI SVG can still be displayed and downloaded.
				return $this->generate_svg_data_uri( $url );
			}

			$qr = $this->build_qr( $url );

			$image = $qr->createImage( self::MODULE_SIZE, self::MARGIN, 0x000000, 0xFFFFFF, false );

			if ( $this->logo ) {
				$this->stamp_logo_on_image( $image, $qr->getModuleCount() );
			}

			ob_start();
			imagepng( $image );
			$png_data = ob_get_clean();
			imagedestroy( $image );

			if ( false === $png_data || '' === $png_data ) {
				throw new Exception( 'PNG rendering produced no data.' );
			}

			// Not obfuscation: base64 is the required transport encoding for a
			// data: URI, which is how the code is shown and downloaded inline.
			return 'data:image/png;base64,' . base64_encode( $png_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		/**
		 * Generate QR code as SVG data URI.
		 *
		 * When a logo is present it is embedded as a base64 PNG <image>
		 * element, so the downloaded SVG is self-contained and still
		 * shows the logo with no external file to lose.
		 *
		 * @param string $url URL to encode.
		 * @return string SVG as data URI.
		 * @throws Exception If encoding fails.
		 */
		public function generate_svg_data_uri( $url ) {
			$qr = $this->build_qr( $url );

			$module_count = $qr->getModuleCount();
			$size         = self::SVG_MODULE_SIZE;
			$margin       = self::SVG_MARGIN;
			$total        = $module_count * $size + $margin * 2;

			$svg  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
			$svg .= sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" shape-rendering="crispEdges">',
				$total
			) . "\n";
			$svg .= '<rect width="100%" height="100%" fill="#ffffff"/>' . "\n";

			for ( $row = 0; $row < $module_count; $row++ ) {
				for ( $col = 0; $col < $module_count; $col++ ) {
					if ( $qr->isDark( $row, $col ) ) {
						$x    = $margin + ( $col * $size );
						$y    = $margin + ( $row * $size );
						$svg .= sprintf( '<rect x="%d" y="%d" width="%d" height="%d" fill="#000000"/>', $x, $y, $size, $size ) . "\n";
					}
				}
			}

			if ( $this->logo ) {
				$svg .= $this->build_logo_svg_fragment( $module_count );
			}

			$svg .= '</svg>';

			// See the note in generate_png_data_uri(): data: URI transport encoding.
			return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		/**
		 * Composite the logo plate onto a rendered raster QR image.
		 *
		 * @param resource|GdImage $image        Rendered QR image.
		 * @param int              $module_count QR module count per side.
		 * @return void
		 */
		private function stamp_logo_on_image( $image, $module_count ) {
			$plate = Creatives_DQRCGP_Logo::build_plate( $this->logo, $module_count, self::MODULE_SIZE, $this->logo_size );

			if ( is_wp_error( $plate ) ) {
				// A logo that cannot be placed must never cost the visitor
				// their QR code — fall through and return the plain one.
				return;
			}

			$image_size = imagesx( $image );
			$offset_x   = (int) round( ( $image_size - $plate['pixels_w'] ) / 2 );
			$offset_y   = (int) round( ( $image_size - $plate['pixels_h'] ) / 2 );

			imagecopy( $image, $plate['image'], $offset_x, $offset_y, 0, 0, $plate['pixels_w'], $plate['pixels_h'] );
			imagedestroy( $plate['image'] );
		}

		/**
		 * Build the <image> fragment that carries the logo inside the SVG.
		 *
		 * @param int $module_count QR module count per side.
		 * @return string SVG markup, or an empty string on failure.
		 */
		private function build_logo_svg_fragment( $module_count ) {
			if ( ! Creatives_DQRCGP_Logo::is_supported() ) {
				return '';
			}

			// Render the plate at raster resolution so the embedded bitmap
			// stays sharp when the SVG is scaled up for print.
			$plate = Creatives_DQRCGP_Logo::build_plate( $this->logo, $module_count, self::MODULE_SIZE * 4, $this->logo_size );

			if ( is_wp_error( $plate ) ) {
				return '';
			}

			$png = Creatives_DQRCGP_Logo::to_png_bytes( $plate['image'] );
			imagedestroy( $plate['image'] );

			if ( false === $png || '' === $png ) {
				return '';
			}

			$svg_w    = $plate['modules_w'] * self::SVG_MODULE_SIZE;
			$svg_h    = $plate['modules_h'] * self::SVG_MODULE_SIZE;
			$total    = $module_count * self::SVG_MODULE_SIZE + self::SVG_MARGIN * 2;
			$offset_x = ( $total - $svg_w ) / 2;
			$offset_y = ( $total - $svg_h ) / 2;
			// See the note in generate_png_data_uri(): data: URI transport encoding.
			$data_uri = 'data:image/png;base64,' . base64_encode( $png ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			// href for SVG2 renderers, xlink:href for older ones (Illustrator,
			// Inkscape and a fair number of print RIPs still want it).
			return sprintf(
				'<image x="%1$s" y="%2$s" width="%3$d" height="%4$d" href="%5$s" xlink:href="%5$s" preserveAspectRatio="xMidYMid meet"/>' . "\n",
				self::svg_number( $offset_x ),
				self::svg_number( $offset_y ),
				$svg_w,
				$svg_h,
				esc_attr( $data_uri )
			);
		}

		/**
		 * Format a coordinate for SVG output without trailing zeros.
		 *
		 * @param float $value Coordinate value.
		 * @return string
		 */
		private static function svg_number( $value ) {
			$out = rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
			return '' === $out ? '0' : $out;
		}

		/**
		 * Build and encode a QR matrix for the given URL using the bundled
		 * local QRCode library (no network calls).
		 *
		 * The bundled library signals invalid input or capacity overflow via
		 * trigger_error( ..., E_USER_ERROR ), which by default is a fatal,
		 * script-halting PHP error that a normal try/catch cannot intercept.
		 * A temporary error handler converts it into a catchable exception so
		 * a bad or oversized URL degrades to a clean error message instead of
		 * crashing the page.
		 *
		 * @param string $url URL to encode.
		 * @return \CreativesDQRCGP\QRCode
		 * @throws Exception If the data is invalid or too long to encode.
		 */
		private function build_qr( $url ) {
			require_once CREATIVES_DQRCGP_DIR . 'lib/qrcode.php';

			$level = $this->logo
				? \CreativesDQRCGP\QR_ERROR_CORRECT_LEVEL_H
				: \CreativesDQRCGP\QR_ERROR_CORRECT_LEVEL_M;

			// Not debug code. The bundled library reports encoding failures via
			// trigger_error( E_USER_ERROR ), which is fatal and uncatchable; this
			// converts it to an exception so a bad URL degrades to a message
			// instead of a white screen. Always undone in the finally block.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			set_error_handler(
				function ( $errno, $errstr ) {
					// $errstr is the library's own error text and is thrown, not
					// printed; the caller replaces it with a user-facing message.
					throw new Exception( $errstr ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				},
				E_USER_ERROR
			);

			try {
				$qr = \CreativesDQRCGP\QRCode::getMinimumQRCode( $url, $level );
			} catch ( \Exception $e ) {
				throw new Exception( 'URL could not be encoded as a QR code (it may be too long).' );
			} finally {
				restore_error_handler();
			}

			return $qr;
		}
	}

endif;
