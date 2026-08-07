/**
 * Admin media uploader for per-category custom images.
 *
 * Wires a wp.media frame onto each "Choose image" button the CWC_Admin page
 * renders for a selected product_cat term. Opening the frame selects an image
 * from the library; on selection the hidden `cwc_cat_images[<term_id>]`
 * attachment-id input is updated and the row preview refreshes. Saving happens
 * through the settings form (the admin_init handler) as usual, so this script
 * only mutates the DOM — it performs no AJAX (AS-3).
 *
 * Each row is matched by `.cwc-category-image-row` and identified by its
 * `data-term-id` attribute so multiple categories each get their own frame.
 * The uploader loads only on the Carousel screen because CWC_Assets enqueues
 * it there behind `wp_enqueue_media` (FA-5, D7).
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

( function () {
	'use strict';

	// wp.media is only present after wp_enqueue_media() has primed it; without
	// it the uploader has nothing to open, so bail silently on other admin
	// screens still reached by a race (defensive, FA-5).
	if ( typeof window.wp === 'undefined' || typeof window.wp.media === 'undefined' ) {
		return;
	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			var rows = document.querySelectorAll( '.cwc-category-image-row' );

			rows.forEach( function ( row ) {
				attachUploader( row );
			} );
		}
	);

	/**
	 * Wires the upload button inside one category row.
	 *
	 * Creates a reusable wp.media frame (image-only, single select). On
	 * selection it writes the attachment id into the row's hidden input and
	 * refreshes the preview thumbnail. A "remove" button clears both.
	 *
	 * @param {HTMLElement} row Category row element.
	 * @return {void}
	 */
	function attachUploader( row ) {
		var trigger = row.querySelector( '.cwc-cat-image-upload' );
		var remove  = row.querySelector( '.cwc-cat-image-remove' );
		var input   = row.querySelector( '.cwc-cat-image-id' );
		var preview = row.querySelector( '.cwc-cat-image-preview' );
		var frame;

		if ( ! trigger || ! input || ! preview ) {
			return;
		}

		frame = window.wp.media( {
			title: 'Selecciona una imagen para la categoría',
			button: {
				text: 'Usar esta imagen'
			},
			library: {
				type: 'image'
			},
			multiple: false
		} );

		trigger.addEventListener(
			'click',
			function ( event ) {
				event.preventDefault();
				frame.open();
			}
		);

		frame.on(
			'select',
			function () {
				var selection = frame.state().get( 'selection' ).first();
				var attachment = selection ? selection.toJSON() : {};

				if ( ! attachment.id ) {
					return;
				}

				input.value = attachment.id;
				refreshPreview( preview, attachment );
			}
		);

		if ( remove ) {
			remove.addEventListener(
				'click',
				function ( event ) {
					event.preventDefault();
					input.value = '';
					preview.textContent = '';
				}
			);
		}

		// Restore the preview from a previously saved attachment id on load.
		if ( input.value && Number( input.value ) > 0 ) {
			loadAttachment( Number( input.value ), preview );
		}
	}

	/**
	 * Updates the preview element from an attachment object.
	 *
	 * Uses the resolved full-size or thumbnail url when present, otherwise
	 * mirrors the "no image" empty state.
	 *
	 * @param {HTMLElement} preview    Preview container or image element.
	 * @param {Object}      attachment Selected attachment data.
	 * @return {void}
	 */
	function refreshPreview( preview, attachment ) {
		var url = ( attachment.sizes && attachment.sizes.thumbnail )
			? attachment.sizes.thumbnail.url
			: attachment.url;

		setPreview( preview, url || '' );
	}

	/**
	 * Renders an image into the preview, or clears it when there is no url.
	 *
	 * The PHP renderer prints either an attached `<img>` or an empty span
	 * inside `.cwc-cat-image-preview`; replace its content with an `<img>` so
	 * the live preview always shows one source.
	 *
	 * @param {HTMLElement} container Preview container element.
	 * @param {string}      url      Image url to show, or '' to clear.
	 * @return {void}
	 */
	function setPreview( container, url ) {
		container.textContent = '';

		if ( url ) {
			var img = document.createElement( 'img' );
			img.src = url;
			img.className = 'cwc-cat-image-preview-img';
			img.alt = '';
			container.appendChild( img );
		}
	}

	/**
	 * Loads an attachment by id for the preview element.
	 *
	 * Used to restore the stored thumbnail on page load. The call is
	 * read-only against the media library and harmless if the attachment was
	 * deleted (a missing one simply leaves the empty state).
	 *
	 * @param {number}      id      Attachment id.
	 * @param {HTMLElement} preview Preview container element.
	 * @return {void}
	 */
	function loadAttachment( id, preview ) {
		var attachment = window.wp.media.attachment( id );

		attachment.fetch().done( function () {
			setPreview( preview, attachment.get( 'url' ) || '' );
		} );
	}
} )();