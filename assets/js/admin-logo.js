/**
 * Media-library picker for the site-wide QR center logo.
 *
 * @package Creatives_Dual_QR_Code_Generator_Pro
 */

( function( $ ) {
	'use strict';

	$( function() {
		var strings = ( window.creativesDqrcgpLogoPicker && window.creativesDqrcgpLogoPicker.i18n ) || {};

		/**
		 * Localized string, falling back to the English source text.
		 */
		function t( key, fallback ) {
			return strings[ key ] || fallback;
		}

		var frame;
		var $field = $( '#creatives_dqrcgp_logo_attachment_id' );
		var $preview = $( '#creatives-dqrcgp-logo-admin-preview' );
		var $previewImg = $preview.find( 'img' );
		var $selectBtn = $( '#creatives-dqrcgp-logo-select' );
		var $removeBtn = $( '#creatives-dqrcgp-logo-remove' );

		if ( ! $field.length ) {
			return;
		}

		$selectBtn.on( 'click', function( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: t( 'frameTitle', 'Select QR Center Logo' ),
				button: { text: t( 'frameButton', 'Use this logo' ) },
				library: { type: [ 'image/png', 'image/jpeg', 'image/webp' ] },
				multiple: false
			} );

			frame.on( 'select', function() {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = attachment.url;

				// Prefer a scaled size for the preview when one exists.
				if ( attachment.sizes && attachment.sizes.medium ) {
					url = attachment.sizes.medium.url;
				} else if ( attachment.sizes && attachment.sizes.thumbnail ) {
					url = attachment.sizes.thumbnail.url;
				}

				$field.val( attachment.id );
				$previewImg.attr( 'src', url );
				$preview.show();
				$removeBtn.show();
				$selectBtn.text( t( 'change', 'Change Logo' ) );
			} );

			frame.open();
		} );

		$removeBtn.on( 'click', function( e ) {
			e.preventDefault();
			$field.val( '' );
			$previewImg.attr( 'src', '' );
			$preview.hide();
			$removeBtn.hide();
			$selectBtn.text( t( 'select', 'Select Logo' ) );
		} );
	} );
} )( jQuery );
