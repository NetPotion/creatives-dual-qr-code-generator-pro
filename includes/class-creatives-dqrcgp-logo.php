<?php
/**
 * Center-logo handling for QR codes.
 *
 * Everything here works on raw image bytes and GD resources in memory.
 * Visitor-supplied uploads are read from the PHP temp file, validated,
 * re-encoded through GD (which discards any non-pixel payload smuggled
 * inside the original file) and then discarded. Nothing a visitor
 * uploads is ever written into wp-content/uploads.
 *
 * The only persisted logo is the one an administrator picks in
 * Settings → QR Generator Pro, which is a normal media-library
 * attachment and is read from its existing path.
 *
 * @package Creatives_Dual_QR_Code_Generator_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Creatives_DQRCGP_Logo' ) ) :

	/**
	 * Validates, normalises and composites center logos for QR codes.
	 */
	class Creatives_DQRCGP_Logo {

		/**
		 * Maximum accepted upload size in bytes (2 MB).
		 */
		const MAX_BYTES = 2097152;

		/**
		 * Absolute sanity ceiling on a source dimension, per side.
		 *
		 * This is not a quality judgement about the upload, only a guard
		 * against a header declaring something absurd. What actually
		 * decides whether an image can be handled is whether GD has the
		 * memory to decode it — see fits_in_memory(). An ordinary phone
		 * photo is expected to pass; earlier releases rejected one at
		 * just over 4 megapixels while telling the user the limit was
		 * 3000 x 3000, which was both wrong and unactionable.
		 */
		const MAX_SIDE = 10000;

		/**
		 * Absolute sanity ceiling on total source pixels.
		 */
		const MAX_PIXELS = 50000000;

		/**
		 * Bytes GD needs per pixel of a decoded truecolor image, with
		 * headroom for the copy made while resizing.
		 */
		const BYTES_PER_PIXEL = 5;

		/**
		 * Longest side kept after decoding.
		 *
		 * The logo is drawn at a few hundred pixels at most, so nothing
		 * larger is ever needed. Reducing immediately after decode means
		 * peak memory is brief and everything downstream is cheap.
		 */
		const WORK_MAX_SIDE = 1024;

		/**
		 * Share of the QR code's total module area the backing plate may
		 * cover, per size setting.
		 *
		 * Area is the constraint that matters, not width. Measured by
		 * sweeping coverage from 2% to 22% across QR versions 4-21 with
		 * square, tall and wide logos, scoring each against a no-logo
		 * baseline on two independent decoders (OpenCV and ZBar) over a
		 * range of render sizes and blur. Coverage up to 6% cost nothing
		 * (mean 0.95-1.00 of baseline-readable conditions), 8% stayed
		 * strong (0.91-0.96), 12% began to cost (0.79-0.92) and 15% and
		 * above degraded clearly. 'large' therefore sits at the top of
		 * the measured-safe band rather than past it.
		 */
		const AREA_BUDGETS = array(
			'small'  => 0.040,
			'medium' => 0.065,
			'large'  => 0.090,
		);

		/**
		 * Default size setting when none is configured.
		 */
		const DEFAULT_SIZE = 'medium';

		/**
		 * Largest share of the code's width or height one plate side may
		 * span, whatever the area budget allows. Keeps a very elongated
		 * logo from reaching toward the finder patterns.
		 */
		const MAX_SIDE_RATIO = 0.42;

		/**
		 * Floor on a plate side in QR modules — below this there is no
		 * room for a recognisable mark.
		 */
		const MIN_PLATE_MODULES = 5;

		/**
		 * Tolerance, per 0-255 channel, for treating a border pixel as
		 * part of a uniform margin that should be trimmed.
		 */
		const TRIM_TOLERANCE = 12;

		/**
		 * Never trim more than this share off any single side, so an
		 * image that is legitimately mostly background is not destroyed.
		 */
		const MAX_TRIM_RATIO = 0.45;

		/**
		 * Longest side, in pixels, of the reduced copy the margin scan
		 * runs on.
		 *
		 * The scan reads pixels one at a time from PHP, so its cost grows
		 * with area: at the 4-megapixel ceiling this class accepts, a
		 * full-resolution scan measured around 500 ms of CPU per request
		 * on a public, unauthenticated endpoint. The scan only needs a
		 * bounding box that is then scaled down to roughly a dozen QR
		 * modules, so a reduced copy gives the same answer for about 10
		 * ms. The box is mapped back to source coordinates with a pixel
		 * of slack to absorb resampling error.
		 */
		const SCAN_MAX_SIDE = 256;

		/**
		 * Whether this server can composite a logo at all.
		 *
		 * @return bool
		 */
		public static function is_supported() {
			return extension_loaded( 'gd' )
				&& function_exists( 'imagecreatetruecolor' )
				&& function_exists( 'imagecopyresampled' )
				&& function_exists( 'imagecreatefromstring' );
		}

		/**
		 * Image types this plugin accepts as a logo source.
		 *
		 * WebP is only offered when the GD build can actually decode it.
		 * SVG is deliberately excluded: it is a script-bearing document
		 * format and has no business being accepted on a public,
		 * unauthenticated endpoint. ICO is excluded because GD cannot
		 * decode it.
		 *
		 * @return array Map of IMAGETYPE_* constant => human label.
		 */
		public static function allowed_types() {
			$types = array(
				IMAGETYPE_PNG  => 'PNG',
				IMAGETYPE_JPEG => 'JPG',
			);

			if ( defined( 'IMAGETYPE_WEBP' ) && function_exists( 'imagecreatefromwebp' ) ) {
				$types[ IMAGETYPE_WEBP ] = 'WebP';
			}

			return $types;
		}

		/**
		 * Human-readable list of accepted formats, for form labels.
		 *
		 * @return string
		 */
		public static function allowed_types_label() {
			return implode( ', ', self::allowed_types() );
		}

		/**
		 * Accept-attribute value for the file input.
		 *
		 * @return string
		 */
		public static function accept_attribute() {
			$accept = array( 'image/png', 'image/jpeg' );

			if ( isset( self::allowed_types()[ IMAGETYPE_WEBP ] ) ) {
				$accept[] = 'image/webp';
			}

			return implode( ',', $accept );
		}

		/**
		 * Validate and load a visitor upload from the $_FILES array.
		 *
		 * @param array $file One entry from $_FILES.
		 * @return resource|GdImage|WP_Error GD image on success.
		 */
		public static function from_upload( $file ) {
			if ( ! is_array( $file ) || ! isset( $file['error'] ) ) {
				return new WP_Error( 'logo_missing', __( 'No logo file was received.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			// A multi-file field (logo[]) gives arrays for every member.
			// Only a single scalar upload is ever accepted.
			if ( ! is_scalar( $file['error'] ) || ! isset( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) ) {
				return new WP_Error( 'logo_invalid', __( 'Please choose a single logo image.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( UPLOAD_ERR_INI_SIZE === (int) $file['error'] || UPLOAD_ERR_FORM_SIZE === (int) $file['error'] ) {
				return new WP_Error(
					'logo_too_large',
					sprintf(
						/* translators: %d: maximum upload size in megabytes. */
						__( 'That logo is larger than the server allows. Please use a file under %d MB.', 'creatives-dual-qr-code-generator-pro' ),
						(int) ( self::MAX_BYTES / 1048576 )
					)
				);
			}

			if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
				return new WP_Error( 'logo_upload_failed', __( 'The logo upload did not complete. Please try again.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				return new WP_Error( 'logo_invalid', __( 'The logo upload could not be verified.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( isset( $file['size'] ) && is_scalar( $file['size'] ) && (int) $file['size'] > self::MAX_BYTES ) {
				return new WP_Error(
					'logo_too_large',
					sprintf(
						/* translators: %d: maximum upload size in megabytes. */
						__( 'Logo file must be %d MB or smaller.', 'creatives-dual-qr-code-generator-pro' ),
						(int) ( self::MAX_BYTES / 1048576 )
					)
				);
			}

			return self::from_path( $file['tmp_name'] );
		}

		/**
		 * Validate and load a logo from a path on disk.
		 *
		 * Used both for visitor temp uploads and for the administrator's
		 * media-library selection.
		 *
		 * @param string $path Absolute path to an image file.
		 * @return resource|GdImage|WP_Error GD image on success.
		 */
		public static function from_path( $path ) {
			if ( ! self::is_supported() ) {
				return new WP_Error( 'logo_unsupported', __( 'This server cannot add a logo to QR codes (the GD image library is unavailable).', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
				return new WP_Error( 'logo_unreadable', __( 'The logo image could not be read.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			if ( filesize( $path ) > self::MAX_BYTES ) {
				return new WP_Error(
					'logo_too_large',
					sprintf(
						/* translators: %d: maximum upload size in megabytes. */
						__( 'Logo file must be %d MB or smaller.', 'creatives-dual-qr-code-generator-pro' ),
						(int) ( self::MAX_BYTES / 1048576 )
					)
				);
			}

			// Suppression is deliberate: a malformed or truncated image makes
			// getimagesize() emit a warning AND return false. The return value
			// is checked on the next line, so the warning is noise that would
			// otherwise be echoed into an AJAX response body.
			$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
				return new WP_Error( 'logo_not_image', __( 'That file is not a readable image.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			list( $width, $height ) = $info;
			$type                   = isset( $info[2] ) ? (int) $info[2] : 0;

			if ( ! array_key_exists( $type, self::allowed_types() ) ) {
				return new WP_Error(
					'logo_bad_type',
					sprintf(
						/* translators: %s: comma-separated list of accepted image formats. */
						__( 'Logo must be one of: %s.', 'creatives-dual-qr-code-generator-pro' ),
						self::allowed_types_label()
					)
				);
			}

			if ( $width > self::MAX_SIDE || $height > self::MAX_SIDE || ( $width * $height ) > self::MAX_PIXELS ) {
				return new WP_Error(
					'logo_too_big',
					sprintf(
						/* translators: 1: image width, 2: image height, 3: maximum pixels per side. */
						__( 'That image is %1$d x %2$d pixels, which is beyond what this tool can process. Please use an image no larger than %3$d pixels on a side.', 'creatives-dual-qr-code-generator-pro' ),
						$width,
						$height,
						self::MAX_SIDE
					)
				);
			}

			// The real constraint is whether GD can hold the decoded image.
			// Checked against the actual memory limit rather than a fixed
			// pixel count, and reported with the size that would fit.
			if ( ! self::fits_in_memory( $width, $height ) ) {
				return new WP_Error(
					'logo_too_big',
					sprintf(
						/* translators: 1: image width, 2: image height, 3: image size in megapixels, 4: megapixels that would fit. */
						__( 'That image is %1$d x %2$d pixels (%3$s megapixels), which is more than this server has memory to process. Please resize it to about %4$s megapixels or less and try again.', 'creatives-dual-qr-code-generator-pro' ),
						$width,
						$height,
						number_format( ( $width * $height ) / 1000000, 1 ),
						number_format( max( 1, self::max_pixels_for_memory() ) / 1000000, 1 )
					)
				);
			}

			$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( false === $bytes || '' === $bytes ) {
				return new WP_Error( 'logo_unreadable', __( 'The logo image could not be read.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			// imagecreatefromstring decodes pixels only. Whatever else was in
			// the original container (EXIF, appended archives, PHP tags in a
			// comment block) does not survive into the GD resource, and the
			// re-encoded output is written fresh from that resource.
			// Same reasoning: imagecreatefromstring() warns and returns false
			// on data it cannot decode, and that false is handled below.
			$image = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			unset( $bytes );

			if ( ! $image ) {
				return new WP_Error( 'logo_decode_failed', __( 'That image could not be decoded. Try re-saving it as a PNG.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			// 8-bit palette PNGs (most favicons and small marks) decode as
			// palette images, where imagecolorat() returns a palette index
			// rather than packed RGBA. content_box() reads pixels as RGBA,
			// so on a palette image the margin trim silently found nothing
			// and the logo was drawn at a fraction of its plate. Promote to
			// truecolor once, here, so every later step sees real channels.
			if ( ! imageistruecolor( $image ) ) {
				imagepalettetotruecolor( $image );
			}

			// A logo is drawn at a few hundred pixels at most, so the
			// full-resolution decode is released straight away.
			return self::to_working_size( $image );
		}

		/**
		 * Reduce a decoded image to the working size, freeing the original.
		 *
		 * @param resource|GdImage $image Decoded image.
		 * @return resource|GdImage
		 */
		private static function to_working_size( $image ) {
			$w   = imagesx( $image );
			$h   = imagesy( $image );
			$max = max( $w, $h );

			if ( $max <= self::WORK_MAX_SIDE ) {
				return $image;
			}

			$scale = self::WORK_MAX_SIDE / $max;
			$nw    = max( 1, (int) round( $w * $scale ) );
			$nh    = max( 1, (int) round( $h * $scale ) );

			$small = imagecreatetruecolor( $nw, $nh );

			if ( ! $small ) {
				return $image; // Could not reduce; carry on with the original.
			}

			// Preserve transparency through the reduction.
			imagealphablending( $small, false );
			imagesavealpha( $small, true );
			imagefill( $small, 0, 0, imagecolorallocatealpha( $small, 0, 0, 0, 127 ) );
			imagecopyresampled( $small, $image, 0, 0, 0, 0, $nw, $nh, $w, $h );
			imagedestroy( $image );

			return $small;
		}

		/**
		 * Bytes of memory still available to this request, or null when
		 * the limit is unlimited or unreadable.
		 *
		 * @return int|null
		 */
		private static function memory_available() {
			$limit = ini_get( 'memory_limit' );

			if ( false === $limit || '' === $limit ) {
				return null;
			}

			$limit = trim( $limit );

			if ( '-1' === $limit ) {
				return null; // No limit.
			}

			$unit  = strtoupper( substr( $limit, -1 ) );
			$bytes = (int) $limit;

			if ( 'G' === $unit ) {
				$bytes *= 1024 * 1024 * 1024;
			} elseif ( 'M' === $unit ) {
				$bytes *= 1024 * 1024;
			} elseif ( 'K' === $unit ) {
				$bytes *= 1024;
			}

			if ( $bytes <= 0 ) {
				return null;
			}

			$free = $bytes - memory_get_usage( true );

			return $free > 0 ? $free : 0;
		}

		/**
		 * Largest pixel count this request could decode within the memory
		 * still available to it.
		 *
		 * @return int
		 */
		public static function max_pixels_for_memory() {
			$free = self::memory_available();

			if ( null === $free ) {
				return self::MAX_PIXELS;
			}

			// Leave a quarter of the remaining budget for everything else
			// this request still has to do.
			return (int) max( 0, floor( ( $free * 0.75 ) / self::BYTES_PER_PIXEL ) );
		}

		/**
		 * Whether an image of these dimensions can be decoded safely.
		 *
		 * @param int $width  Image width.
		 * @param int $height Image height.
		 * @return bool
		 */
		public static function fits_in_memory( $width, $height ) {
			$free = self::memory_available();

			if ( null === $free ) {
				return true; // Unlimited or unknown — let GD try.
			}

			return ( $width * $height * self::BYTES_PER_PIXEL ) <= ( $free * 0.75 );
		}

		/**
		 * Load the administrator-configured site logo, if one is set.
		 *
		 * @return resource|GdImage|WP_Error|null GD image, error, or null when none is configured.
		 */
		public static function from_site_setting() {
			$attachment_id = (int) get_option( 'creatives_dqrcgp_logo_attachment_id', 0 );

			if ( $attachment_id <= 0 ) {
				return null;
			}

			$path = get_attached_file( $attachment_id );

			if ( ! $path ) {
				return null;
			}

			return self::from_path( $path );
		}

		/**
		 * Find the bounding box of actual content in an image, ignoring a
		 * uniform or fully transparent border.
		 *
		 * Logos and favicons are very often exported with generous empty
		 * margins baked in. Scaling such a file to fit the plate scales
		 * the margin too, so the visible mark ends up far smaller than
		 * the space reserved for it. Measuring the real ink first is what
		 * lets the mark actually fill its plate.
		 *
		 * @param resource|GdImage $source Source image.
		 * @return array{x:int,y:int,w:int,h:int} Content box (the full image if nothing to trim).
		 */
		public static function content_box( $source ) {
			$full_w = imagesx( $source );
			$full_h = imagesy( $source );
			$full   = array(
				'x' => 0,
				'y' => 0,
				'w' => $full_w,
				'h' => $full_h,
			);

			if ( $full_w < 3 || $full_h < 3 ) {
				return $full;
			}

			// Scan a reduced copy so the cost stays flat regardless of
			// upload size; see SCAN_MAX_SIDE. $ratio maps a scan pixel
			// back to source pixels (1.0 when no reduction happened).
			$scan  = self::scan_copy( $source );
			$image = $scan['image'];
			$ratio = $scan['ratio'];
			$w     = imagesx( $image );
			$h     = imagesy( $image );

			if ( $w < 3 || $h < 3 ) {
				if ( $scan['temp'] ) {
					imagedestroy( $image );
				}
				return $full;
			}

			// The corner pixel defines the background to trim. If the four
			// corners disagree the image has no uniform margin worth
			// trimming, so it is left alone.
			$corners = array(
				imagecolorat( $image, 0, 0 ),
				imagecolorat( $image, $w - 1, 0 ),
				imagecolorat( $image, 0, $h - 1 ),
				imagecolorat( $image, $w - 1, $h - 1 ),
			);

			$ref = self::rgba( $image, $corners[0] );

			foreach ( $corners as $c ) {
				$px = self::rgba( $image, $c );

				// Two fully transparent pixels match regardless of colour.
				if ( $ref['a'] > 100 && $px['a'] > 100 ) {
					continue;
				}

				if ( ! self::pixels_match( $ref, $px ) ) {
					if ( $scan['temp'] ) {
						imagedestroy( $image );
					}
					return $full;
				}
			}

			$min_x = $w;
			$min_y = $h;
			$max_x = -1;
			$max_y = -1;

			for ( $y = 0; $y < $h; $y++ ) {
				for ( $x = 0; $x < $w; $x++ ) {
					$px = self::rgba( $image, imagecolorat( $image, $x, $y ) );

					// Transparent background, or a colour close to the corner.
					if ( $ref['a'] > 100 && $px['a'] > 100 ) {
						continue;
					}

					if ( $ref['a'] <= 100 && self::pixels_match( $ref, $px ) ) {
						continue;
					}

					if ( $x < $min_x ) {
						$min_x = $x;
					}
					if ( $x > $max_x ) {
						$max_x = $x;
					}
					if ( $y < $min_y ) {
						$min_y = $y;
					}
					if ( $y > $max_y ) {
						$max_y = $y;
					}
				}
			}

			if ( $scan['temp'] ) {
				imagedestroy( $image );
			}

			if ( $max_x < $min_x || $max_y < $min_y ) {
				return $full; // Entirely background — nothing to trim to.
			}

			// Map the box from scan space back to source pixels, with a
			// pixel of slack each way to absorb resampling softness at the
			// content edge, then clamp to the image.
			if ( $ratio > 1.0 ) {
				$min_x = (int) floor( $min_x * $ratio ) - 1;
				$min_y = (int) floor( $min_y * $ratio ) - 1;
				$max_x = (int) ceil( ( $max_x + 1 ) * $ratio ) + 1;
				$max_y = (int) ceil( ( $max_y + 1 ) * $ratio ) + 1;
			}

			$min_x = max( 0, min( $min_x, $full_w - 1 ) );
			$min_y = max( 0, min( $min_y, $full_h - 1 ) );
			$max_x = max( $min_x, min( $max_x, $full_w - 1 ) );
			$max_y = max( $min_y, min( $max_y, $full_h - 1 ) );

			// Refuse to trim so aggressively that a mostly-background image
			// (a photo with a plain sky, say) is reduced to a detail.
			$limit_x = (int) floor( $full_w * self::MAX_TRIM_RATIO );
			$limit_y = (int) floor( $full_h * self::MAX_TRIM_RATIO );

			$min_x = min( $min_x, $limit_x );
			$min_y = min( $min_y, $limit_y );
			$max_x = max( $max_x, $full_w - 1 - $limit_x );
			$max_y = max( $max_y, $full_h - 1 - $limit_y );

			return array(
				'x' => $min_x,
				'y' => $min_y,
				'w' => $max_x - $min_x + 1,
				'h' => $max_y - $min_y + 1,
			);
		}

		/**
		 * Return an image small enough to scan cheaply.
		 *
		 * Gives back the original untouched when it is already within
		 * SCAN_MAX_SIDE, so small logos cost nothing extra. The caller
		 * destroys the copy only when 'temp' is true.
		 *
		 * @param resource|GdImage $source Source image.
		 * @return array{image:resource|GdImage,ratio:float,temp:bool}
		 */
		private static function scan_copy( $source ) {
			$w   = imagesx( $source );
			$h   = imagesy( $source );
			$max = max( $w, $h );

			if ( $max <= self::SCAN_MAX_SIDE ) {
				return array(
					'image' => $source,
					'ratio' => 1.0,
					'temp'  => false,
				);
			}

			$scale = self::SCAN_MAX_SIDE / $max;
			$sw    = max( 3, (int) round( $w * $scale ) );
			$sh    = max( 3, (int) round( $h * $scale ) );

			$small = imagecreatetruecolor( $sw, $sh );

			if ( ! $small ) {
				return array(
					'image' => $source,
					'ratio' => 1.0,
					'temp'  => false,
				);
			}

			// Alpha must survive the reduction, or a transparent margin
			// would come back opaque black and defeat the trim entirely.
			imagealphablending( $small, false );
			imagesavealpha( $small, true );
			imagefill( $small, 0, 0, imagecolorallocatealpha( $small, 0, 0, 0, 127 ) );
			imagecopyresampled( $small, $source, 0, 0, 0, 0, $sw, $sh, $w, $h );

			return array(
				'image' => $small,
				'ratio' => $w / $sw,
				'temp'  => true,
			);
		}

		/**
		 * Split a GD colour index into channels.
		 *
		 * @param resource|GdImage $image Image the index belongs to.
		 * @param int              $index Colour index.
		 * @return array{r:int,g:int,b:int,a:int}
		 */
		private static function rgba( $image, $index ) {
			return array(
				'r' => ( $index >> 16 ) & 0xFF,
				'g' => ( $index >> 8 ) & 0xFF,
				'b' => $index & 0xFF,
				'a' => ( $index >> 24 ) & 0x7F,
			);
		}

		/**
		 * Whether two pixels are within the trim tolerance of each other.
		 *
		 * @param array $a First pixel.
		 * @param array $b Second pixel.
		 * @return bool
		 */
		private static function pixels_match( $a, $b ) {
			return abs( $a['r'] - $b['r'] ) <= self::TRIM_TOLERANCE
				&& abs( $a['g'] - $b['g'] ) <= self::TRIM_TOLERANCE
				&& abs( $a['b'] - $b['b'] ) <= self::TRIM_TOLERANCE
				&& abs( $a['a'] - $b['a'] ) <= self::TRIM_TOLERANCE;
		}

		/**
		 * Resolve a size setting to an area budget.
		 *
		 * @param string $size One of the AREA_BUDGETS keys.
		 * @return float
		 */
		public static function area_budget( $size ) {
			return isset( self::AREA_BUDGETS[ $size ] )
				? self::AREA_BUDGETS[ $size ]
				: self::AREA_BUDGETS[ self::DEFAULT_SIZE ];
		}

		/**
		 * Build the centered white plate with the logo drawn inside it.
		 *
		 * The plate is shaped to the logo, not forced square. A square
		 * plate around a tall mark spends its whole area budget on white
		 * space either side of the ink; matching the plate to the trimmed
		 * logo's aspect ratio spends that same budget on the mark itself.
		 *
		 * Both sides are forced odd. QR codes always have an odd module
		 * count, so odd plate sides land exactly on the grid centre with
		 * no half-module offset — the difference between a logo that
		 * looks placed and one that looks pasted.
		 *
		 * @param resource|GdImage $logo         Source logo image.
		 * @param int              $module_count QR module count per side.
		 * @param int              $module_size  Pixels per module for the target render.
		 * @param string           $size         Size setting key.
		 * @return array{image:resource|GdImage,modules_w:int,modules_h:int,pixels_w:int,pixels_h:int}|WP_Error
		 */
		public static function build_plate( $logo, $module_count, $module_size, $size = self::DEFAULT_SIZE ) {
			$src_w = imagesx( $logo );
			$src_h = imagesy( $logo );

			if ( $src_w < 1 || $src_h < 1 ) {
				return new WP_Error( 'logo_bad_dimensions', __( 'The logo image has no usable dimensions.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$box    = self::content_box( $logo );
			$ink_w  = max( 1, $box['w'] );
			$ink_h  = max( 1, $box['h'] );
			$aspect = $ink_w / $ink_h;

			// Area the plate may cover, in whole modules.
			$area = self::area_budget( $size ) * $module_count * $module_count;

			// Shape that area to the logo, then add the one-module pad on
			// each side that separates the mark from the surrounding code.
			$inner_h = sqrt( $area / $aspect );
			$inner_w = $inner_h * $aspect;

			$plate_h = (int) round( $inner_h ) + 2;
			$plate_w = (int) round( $inner_w ) + 2;

			$max_side = max(
				self::MIN_PLATE_MODULES,
				min( $module_count - 16, (int) floor( $module_count * self::MAX_SIDE_RATIO ) )
			);

			$plate_w = max( self::MIN_PLATE_MODULES, min( $plate_w, $max_side ) );
			$plate_h = max( self::MIN_PLATE_MODULES, min( $plate_h, $max_side ) );

			// Force odd so the plate centres exactly on the module grid.
			if ( 0 === $plate_w % 2 ) {
				--$plate_w;
			}
			if ( 0 === $plate_h % 2 ) {
				--$plate_h;
			}

			// If the code is too small to hold a plate with room to spare,
			// skip the logo rather than crowding the finder patterns.
			if ( $plate_w > $module_count - 16 || $plate_h > $module_count - 16 ) {
				return new WP_Error( 'logo_no_room', __( 'This QR code is too small to carry a center logo.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$pixels_w = $plate_w * $module_size;
			$pixels_h = $plate_h * $module_size;
			$pad      = $module_size;
			$inner_pw = $pixels_w - ( $pad * 2 );
			$inner_ph = $pixels_h - ( $pad * 2 );

			if ( $inner_pw < 1 || $inner_ph < 1 ) {
				return new WP_Error( 'logo_no_room', __( 'There is not enough room in this QR code for a logo.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$plate = imagecreatetruecolor( $pixels_w, $pixels_h );

			if ( ! $plate ) {
				return new WP_Error( 'logo_plate_failed', __( 'Could not build the logo backing plate.', 'creatives-dual-qr-code-generator-pro' ) );
			}

			$white = imagecolorallocate( $plate, 255, 255, 255 );
			imagefilledrectangle( $plate, 0, 0, $pixels_w, $pixels_h, $white );

			// Alpha blending on: a transparent PNG logo composites down onto
			// the white plate instead of punching holes through it.
			imagealphablending( $plate, true );

			// Scale the trimmed content — not the padded original — so the
			// mark fills the plate it was given.
			$scale = min( $inner_pw / $ink_w, $inner_ph / $ink_h );
			$dst_w = max( 1, (int) round( $ink_w * $scale ) );
			$dst_h = max( 1, (int) round( $ink_h * $scale ) );
			$dst_x = (int) round( ( $pixels_w - $dst_w ) / 2 );
			$dst_y = (int) round( ( $pixels_h - $dst_h ) / 2 );

			imagecopyresampled(
				$plate,
				$logo,
				$dst_x,
				$dst_y,
				$box['x'],
				$box['y'],
				$dst_w,
				$dst_h,
				$ink_w,
				$ink_h
			);

			return array(
				'image'     => $plate,
				'modules_w' => $plate_w,
				'modules_h' => $plate_h,
				'pixels_w'  => $pixels_w,
				'pixels_h'  => $pixels_h,
			);
		}

		/**
		 * Encode a GD image as raw PNG bytes.
		 *
		 * @param resource|GdImage $image Image to encode.
		 * @return string|false
		 */
		public static function to_png_bytes( $image ) {
			ob_start();
			imagepng( $image );
			return ob_get_clean();
		}
	}

endif;
