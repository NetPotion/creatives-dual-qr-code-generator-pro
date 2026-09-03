/**
 * Admin QR generator screen.
 *
 * @package Creatives_Dual_QR_Code_Generator_Pro
 */

( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		var settings = window.creativesDqrcgpAdmin || {};
		var strings = settings.i18n || {};

		/**
		 * Localized string, falling back to the English source text.
		 */
		function t( key, fallback ) {
			return strings[ key ] || fallback;
		}
		var form = document.getElementById( 'creatives-qr-admin-form' );

		if ( ! form ) {
			return;
		}

		var urlInput = document.getElementById( 'creatives-qr-admin-url' );
		var submitBtn = document.getElementById( 'creatives-qr-admin-submit' );
		var spinner = document.getElementById( 'creatives-qr-admin-spinner' );
		var errorBox = document.getElementById( 'creatives-qr-admin-error' );
		var resultBox = document.getElementById( 'creatives-qr-admin-result' );

		var sourceRadios = form.querySelectorAll( 'input[name="logo_source"]' );
		var libraryPanel = document.getElementById( 'creatives-qr-admin-library-panel' );
		var uploadPanel = document.getElementById( 'creatives-qr-admin-upload-panel' );
		var sizeRow = form.querySelector( '.creatives-qr-admin-size-row' );

		var attachmentField = document.getElementById( 'creatives-qr-admin-attachment-id' );
		var libraryPreview = document.getElementById( 'creatives-qr-admin-library-preview' );
		var librarySelectBtn = document.getElementById( 'creatives-qr-admin-library-select' );

		var fileInput = document.getElementById( 'creatives-qr-admin-logo-file' );
		var uploadPreview = document.getElementById( 'creatives-qr-admin-upload-preview' );
		var uploadPreviewUrl = null;
		var mediaFrame = null;

		function currentSource() {
			for ( var i = 0; i < sourceRadios.length; i++ ) {
				if ( sourceRadios[ i ].checked ) {
					return sourceRadios[ i ].value;
				}
			}
			return 'none';
		}

		function syncPanels() {
			var source = currentSource();

			if ( libraryPanel ) {
				libraryPanel.hidden = 'library' !== source;
			}
			if ( uploadPanel ) {
				uploadPanel.hidden = 'upload' !== source;
			}
			if ( sizeRow ) {
				sizeRow.hidden = 'none' === source;
			}
		}

		for ( var i = 0; i < sourceRadios.length; i++ ) {
			sourceRadios[ i ].addEventListener( 'change', syncPanels );
		}
		syncPanels();

		if ( librarySelectBtn && window.wp && window.wp.media ) {
			librarySelectBtn.addEventListener( 'click', function( e ) {
				e.preventDefault();

				if ( ! mediaFrame ) {
					mediaFrame = window.wp.media( {
						title: settings.mediaTitle || 'Select center logo',
						button: { text: settings.mediaButton || 'Use this logo' },
						library: { type: settings.mediaTypes || [ 'image/png', 'image/jpeg' ] },
						multiple: false
					} );

					mediaFrame.on( 'select', function() {
						var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
						var previewUrl = attachment.url;

						if ( attachment.sizes && attachment.sizes.medium ) {
							previewUrl = attachment.sizes.medium.url;
						} else if ( attachment.sizes && attachment.sizes.thumbnail ) {
							previewUrl = attachment.sizes.thumbnail.url;
						}

						if ( attachmentField ) {
							attachmentField.value = attachment.id;
						}
						if ( libraryPreview ) {
							libraryPreview.querySelector( 'img' ).src = previewUrl;
							libraryPreview.hidden = false;
						}
						librarySelectBtn.textContent = settings.changeLabel || 'Change image';
					} );
				}

				mediaFrame.open();
			} );
		}

		if ( fileInput ) {
			fileInput.addEventListener( 'change', function() {
				clearUploadPreview();
				hideError();

				var file = fileInput.files && fileInput.files[ 0 ];
				if ( ! file ) {
					return;
				}

				var maxSize = settings.logoMaxSize || 2097152;

				if ( file.size > maxSize ) {
					showError( t( 'logoTooLarge', 'That logo is larger than 2 MB. Choose a smaller file.' ) );
					fileInput.value = '';
					return;
				}

				if ( ! /^image\/(png|jpeg|webp)$/.test( file.type ) ) {
					showError( t( 'logoWrongType', 'Logo must be a PNG, JPG, or WebP image.' ) );
					fileInput.value = '';
					return;
				}

				uploadPreviewUrl = URL.createObjectURL( file );
				if ( uploadPreview ) {
					uploadPreview.querySelector( 'img' ).src = uploadPreviewUrl;
					uploadPreview.hidden = false;
				}
			} );
		}

		function clearUploadPreview() {
			if ( uploadPreviewUrl ) {
				URL.revokeObjectURL( uploadPreviewUrl );
				uploadPreviewUrl = null;
			}
			if ( uploadPreview ) {
				uploadPreview.querySelector( 'img' ).removeAttribute( 'src' );
				uploadPreview.hidden = true;
			}
		}

		function showError( message ) {
			if ( ! errorBox ) {
				return;
			}
			errorBox.querySelector( 'p' ).textContent = message;
			errorBox.hidden = false;
		}

		function hideError() {
			if ( ! errorBox ) {
				return;
			}
			errorBox.hidden = true;
			errorBox.querySelector( 'p' ).textContent = '';
		}

		function setBusy( busy ) {
			submitBtn.disabled = busy;
			if ( spinner ) {
				spinner.classList.toggle( 'is-active', busy );
			}
		}

		form.addEventListener( 'submit', function( e ) {
			e.preventDefault();
			hideError();

			var url = ( urlInput.value || '' ).trim();

			if ( ! url ) {
				showError( t( 'urlRequired', 'Enter a URL first.' ) );
				return;
			}

			var data = new FormData();
			data.append( 'action', settings.action );
			data.append( 'nonce', settings.nonce );
			data.append( 'url', url );

			var source = currentSource();
			data.append( 'logo_source', source );

			var sizeInput = form.querySelector( 'input[name="logo_size"]:checked' );
			if ( sizeInput ) {
				data.append( 'logo_size', sizeInput.value );
			}

			if ( 'library' === source && attachmentField ) {
				data.append( 'logo_attachment_id', attachmentField.value || '0' );
			}

			if ( 'upload' === source && fileInput && fileInput.files && fileInput.files[ 0 ] ) {
				data.append( 'logo', fileInput.files[ 0 ], fileInput.files[ 0 ].name );
			}

			setBusy( true );

			fetch( settings.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} ).then( function( response ) {
				return response.json();
			} ).then( function( payload ) {
				if ( payload && payload.success ) {
					renderResult( payload.data );
				} else {
					showError( ( payload && payload.data && payload.data.message ) || t( 'failed', 'Generation failed. Try again.' ) );
				}
			} ).catch( function() {
				showError( t( 'networkError', 'Network error. Try again.' ) );
			} ).then( function() {
				setBusy( false );
			} );
		} );

		function renderResult( data ) {
			resultBox.textContent = '';

			var stem = data.filename || 'qr-code';

			var card = document.createElement( 'div' );
			card.className = 'creatives-qr-admin-card';

			var heading = document.createElement( 'h2' );
			heading.textContent = t( 'resultHeading', 'Your QR code' );
			card.appendChild( heading );

			var encoded = document.createElement( 'p' );
			encoded.className = 'creatives-qr-admin-encoded';
			encoded.textContent = data.url;
			card.appendChild( encoded );

			var img = document.createElement( 'img' );
			img.className = 'creatives-qr-admin-image';
			img.src = data.png;
			img.alt = t( 'qrAlt', 'Generated QR code' );
			card.appendChild( img );

			var actions = document.createElement( 'p' );
			actions.className = 'creatives-qr-admin-actions';
			actions.appendChild( downloadLink( data.png, stem + '.png', t( 'downloadPng', 'Download PNG' ) ) );
			actions.appendChild( downloadLink( data.svg, stem + '.svg', t( 'downloadSvg', 'Download SVG' ) ) );
			card.appendChild( actions );

			resultBox.appendChild( card );
		}

		function downloadLink( href, filename, label ) {
			var link = document.createElement( 'a' );
			link.className = 'button button-secondary';
			link.href = href;
			link.download = filename;
			link.textContent = label;
			return link;
		}
	} );
} )();
