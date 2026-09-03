/**
 * Creatives QR Generator - Frontend JavaScript
 *
 * @package Creatives_Dual_QR_Code_Generator_Pro
 */

( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		const form = document.getElementById( 'creatives-qr-frontend-form' );
		const errorDiv = document.getElementById( 'creatives-qr-error' );
		const resultDiv = document.getElementById( 'creatives-qr-result' );
		const submitBtn = form?.querySelector( 'button[type="submit"]' );

		if ( ! form ) {
			return;
		}

		// Track whether the visitor has actually opened the full Terms of
		// Use details before allowing generation. Once opened, stays
		// true even if they collapse it again — the point is that they've
		// seen it, not that it stays open.
		const termsDetails = document.getElementById( 'creatives-qr-terms-details' );
		const termsViewedInput = document.getElementById( 'creatives-qr-terms-viewed' );
		const termsCheckbox = document.getElementById( 'creatives-qr-terms-agree' );

		// The checkbox starts disabled (grayed out, unclickable) in the HTML.
		// It only becomes enabled once the visitor actually opens the full
		// Terms of Use — it stays enabled after that even if they collapse
		// the details again.
		// Optional centre-logo upload. Only rendered when the site is
		// configured to let visitors supply their own.
		const logoInput = document.getElementById( 'creatives-qr-logo' );
		const logoPreview = document.getElementById( 'creatives-qr-logo-preview' );
		const logoPreviewImg = document.getElementById( 'creatives-qr-logo-preview-img' );
		const logoClearBtn = document.getElementById( 'creatives-qr-logo-clear' );
		const logoMaxSize = window.creativesDqrcgpFrontend?.logoMaxSize || 2097152;
		const strings = window.creativesDqrcgpFrontend?.i18n || {};

		/**
		 * Localized string, falling back to the English source text.
		 */
		function t( key, fallback ) {
			return strings[ key ] || fallback;
		}
		let logoPreviewUrl = null;

		if ( logoInput ) {
			logoInput.addEventListener( 'change', function() {
				clearLogoPreview();

				const file = logoInput.files && logoInput.files[ 0 ];
				if ( ! file ) {
					return;
				}

				// Client-side checks are a courtesy so the visitor finds out
				// before a round trip. The server revalidates regardless.
				if ( file.size > logoMaxSize ) {
					showError( t( 'logoTooLarge', 'That logo is larger than 2 MB. Please choose a smaller file.' ) );
					logoInput.value = '';
					return;
				}

				if ( ! /^image\/(png|jpeg|webp)$/.test( file.type ) ) {
					showError( t( 'logoWrongType', 'Logo must be a PNG, JPG, or WebP image.' ) );
					logoInput.value = '';
					return;
				}

				errorDiv.style.display = 'none';
				errorDiv.textContent = '';

				logoPreviewUrl = URL.createObjectURL( file );
				if ( logoPreviewImg ) {
					logoPreviewImg.src = logoPreviewUrl;
					logoPreviewImg.alt = t( 'logoPreview', 'Selected logo preview' );
				}
				if ( logoPreview ) {
					logoPreview.hidden = false;
				}
			} );
		}

		if ( logoClearBtn ) {
			logoClearBtn.addEventListener( 'click', function() {
				if ( logoInput ) {
					logoInput.value = '';
				}
				clearLogoPreview();
			} );
		}

		function clearLogoPreview() {
			if ( logoPreviewUrl ) {
				URL.revokeObjectURL( logoPreviewUrl );
				logoPreviewUrl = null;
			}
			if ( logoPreviewImg ) {
				logoPreviewImg.removeAttribute( 'src' );
				logoPreviewImg.alt = '';
			}
			if ( logoPreview ) {
				logoPreview.hidden = true;
			}
		}

		if ( termsDetails ) {
			termsDetails.addEventListener( 'toggle', function() {
				if ( termsDetails.open ) {
					if ( termsViewedInput ) {
						termsViewedInput.value = '1';
					}
					if ( termsCheckbox ) {
						termsCheckbox.disabled = false;
					}
				}
			} );
		}

		form.addEventListener( 'submit', async function( e ) {
			e.preventDefault();

			// Clear previous messages
			errorDiv.style.display = 'none';
			errorDiv.textContent = '';
			resultDiv.textContent = '';

			// Disable button
			submitBtn.disabled = true;
			submitBtn.textContent = t( 'generating', 'Generating...' );

			const formData = new FormData( form );
			const url = formData.get( 'url' ).trim();

			// Validate URL on frontend (basic)
			if ( ! url || ! isValidUrl( url ) ) {
				showError( t( 'invalidUrl', 'Please enter a valid URL (e.g., https://example.com)' ) );
				submitBtn.disabled = false;
				submitBtn.textContent = t( 'generate', 'Generate QR Code' );
				return;
			}

			// The terms block is optional: a site can switch it off, in which
			// case none of these elements exist and the server skips the
			// matching checks too.
			const termsRequired = !! termsCheckbox;

			if ( termsRequired ) {
				// Only demanded when there is a panel to expand.
				const termsViewed = termsViewedInput ? termsViewedInput.value === '1' : true;
				if ( ! termsViewed ) {
					showError( t( 'termsUnread', 'Please expand and review the full Terms of Use before continuing.' ) );
					submitBtn.disabled = false;
					submitBtn.textContent = t( 'generate', 'Generate QR Code' );
					return;
				}

				if ( ! termsCheckbox.checked ) {
					showError( t( 'termsUnchecked', 'Please agree to the Terms of Use to generate a QR code.' ) );
					submitBtn.disabled = false;
					submitBtn.textContent = t( 'generate', 'Generate QR Code' );
					return;
				}
			}

			// The CAPTCHA is optional. The server decides whether a token is
			// required; this only avoids asking for one that will not exist.
			const captchaRequired = window.creativesDqrcgpFrontend?.captcha !== false;
			let turnstileToken = '';

			if ( captchaRequired ) {
				if ( typeof window.turnstile === 'undefined' ) {
					showError( t( 'captchaMissing', 'CAPTCHA not loaded. Please refresh and try again.' ) );
					submitBtn.disabled = false;
					submitBtn.textContent = t( 'generate', 'Generate QR Code' );
					return;
				}

				turnstileToken = window.turnstile.getResponse();
				if ( ! turnstileToken ) {
					showError( t( 'captchaUnsolved', 'Please complete the CAPTCHA verification.' ) );
					submitBtn.disabled = false;
					submitBtn.textContent = t( 'generate', 'Generate QR Code' );
					return;
				}
			}

			// Prepare AJAX request
			const ajaxData = new FormData();
			ajaxData.append( 'action', window.creativesDqrcgpFrontend.action );
			ajaxData.append( 'url', url );
			ajaxData.append( 'nonce', formData.get( 'nonce' ) );
			if ( termsRequired ) {
				ajaxData.append( 'terms_agree', '1' );
				ajaxData.append( 'terms_viewed', '1' );
			}
			if ( captchaRequired ) {
				ajaxData.append( 'cf-turnstile-response', turnstileToken );
			}

			// Attach the logo file itself, not a data URL — FormData sends
			// it as a normal multipart upload so PHP puts it in $_FILES.
			if ( logoInput && logoInput.files && logoInput.files[ 0 ] ) {
				ajaxData.append( 'logo', logoInput.files[ 0 ], logoInput.files[ 0 ].name );
			}

			try {
				const response = await fetch( window.creativesDqrcgpFrontend.ajaxUrl, {
					method: 'POST',
					body: ajaxData,
				} );

				const data = await response.json();

				if ( data.success ) {
					renderQRResult( data.data );
					form.style.display = 'none';
				} else {
					showError( data.data?.message || t( 'genericError', 'An error occurred. Please try again.' ) );
				}
			} catch ( error ) {
				console.error( 'QR Generation Error:', error );
				showError( t( 'networkError', 'Network error. Please try again.' ) );
			} finally {
				// Re-enable button and reset Turnstile
				submitBtn.disabled = false;
				submitBtn.textContent = t( 'generate', 'Generate QR Code' );
				if ( captchaRequired && typeof window.turnstile !== 'undefined' ) {
					window.turnstile.reset();
				}
			}
		} );

		/**
		 * Validate URL format
		 */
		function isValidUrl( urlString ) {
			try {
				new URL( urlString );
				return true;
			} catch ( error ) {
				return false;
			}
		}

		/**
		 * Display error message
		 */
		function showError( message ) {
			errorDiv.style.display = 'block';
			errorDiv.textContent = message;
		}

		/**
		 * Render QR codes and download buttons, replacing the form so the
		 * result is immediately visible instead of requiring a scroll.
		 */
		function renderQRResult( data ) {
			const { png, svg, url } = data;

			resultDiv.textContent = '';

			const card = document.createElement( 'div' );
			card.className = 'creatives-qr-success';

			const heading = document.createElement( 'h3' );
			heading.textContent = t( 'resultHeading', 'QR Code Generated' );
			card.appendChild( heading );

			const urlLine = document.createElement( 'p' );
			urlLine.className = 'creatives-qr-url';
			urlLine.appendChild( document.createTextNode( t( 'urlLabel', 'URL: ' ) ) );
			const urlStrong = document.createElement( 'strong' );
			urlStrong.textContent = url;
			urlLine.appendChild( urlStrong );
			card.appendChild( urlLine );

			const display = document.createElement( 'div' );
			display.className = 'creatives-qr-display';
			const img = document.createElement( 'img' );
			img.className = 'creatives-qr-image';
			img.src = png;
			img.alt = t( 'qrAlt', 'Generated QR Code' );
			display.appendChild( img );
			card.appendChild( display );

			const downloads = document.createElement( 'div' );
			downloads.className = 'creatives-qr-downloads';
			downloads.appendChild( downloadLink( png, 'qr-code.png', t( 'downloadPng', 'Download PNG' ), 'creatives-qr-download-png' ) );
			downloads.appendChild( downloadLink( svg, 'qr-code.svg', t( 'downloadSvg', 'Download SVG' ), 'creatives-qr-download-svg' ) );
			card.appendChild( downloads );

			const againBtn = document.createElement( 'button' );
			againBtn.type = 'button';
			againBtn.className = 'creatives-qr-generate-another';
			againBtn.textContent = t( 'again', 'Generate another QR code' );
			againBtn.addEventListener( 'click', resetForGeneration );
			card.appendChild( againBtn );

			resultDiv.appendChild( card );
		}

		/**
		 * Build one download button.
		 */
		function downloadLink( href, filename, label, modifier ) {
			const link = document.createElement( 'a' );
			link.className = 'creatives-qr-download-btn ' + modifier;
			link.href = href;
			link.download = filename;
			link.textContent = label;
			return link;
		}

		/**
		 * Bring the form back for a new URL after a successful generation.
		 * Terms agreement/viewed state is intentionally left as-is — the
		 * visitor already read and agreed to it this session.
		 */
		function resetForGeneration() {
			resultDiv.textContent = '';
			errorDiv.style.display = 'none';
			errorDiv.textContent = '';
			form.style.display = '';

			const urlInput = document.getElementById( 'creatives-qr-url' );
			if ( urlInput ) {
				urlInput.value = '';
				urlInput.focus();
			}

			if ( window.creativesDqrcgpFrontend?.captcha !== false && typeof window.turnstile !== 'undefined' ) {
				window.turnstile.reset();
			}
		}
	} );
} )();
