/**
 * Admin scripts: per-category media uploader and picker chip scaffolding.
 *
 * Two independent concerns, both scoped to the Carousel settings screen by
 * CWC_Assets (FA-5):
 *
 * 1. Media uploader — wires a wp.media frame onto each "Choose image" button
 *    the CWC_Admin page renders for a selected product_cat term. Opening the
 *    frame selects an image from the library; on selection the hidden
 *    `cwc_cat_images[<term_id>]` attachment-id input is updated and the row
 *    preview refreshes. Saving happens through the settings form (the
 *    admin_init handler) as usual, so this script only mutates the DOM — it
 *    performs no AJAX (AS-3). Each row is matched by `.cwc-category-image-row`
 *    and identified by its `data-term-id` attribute.
 *
 * 2. Picker scaffolding — keeps the visible `.cwc-chip-list` in sync with the
 *    enhanced <select> options and the native form POST (D1, D3): the list is
 *    rebuilt from `option:checked` on change, and drag-sorted chips reorder
 *    the select's options so the submitted order is the visible order. Inert
 *    until PRs 3-4 render the `.cwc-picker` markup: with no picker on screen
 *    the init matches nothing and changes nothing.
 *
 * All user-facing strings come from the `cwcCarouselAdmin` object that
 * wp_localize_script registers with this script — no hardcoded literals
 * (FA-5).
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

( function () {
	'use strict';

	// Localized strings (FA-5). Always present on this screen because
	// wp_localize_script runs in the same enqueue that loads the script; the
	// empty fallback keeps the code safe if the script is ever reused
	// without the object.
	var strings = window.cwcCarouselAdmin || {};

	// wp.media is only present after wp_enqueue_media() has primed it; without
	// it the uploader has nothing to open, so bail silently on other admin
	// screens still reached by a race (defensive, FA-5).
	if ( typeof window.wp === 'undefined' || typeof window.wp.media === 'undefined' ) {
		return;
	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			initUploaders();
			initPickers();
		}
	);

	/**
	 * Wires the upload button inside every category row.
	 *
	 * @return {void}
	 */
	function initUploaders() {
		var rows = document.querySelectorAll( '.cwc-category-image-row' );

		rows.forEach( function ( row ) {
			attachUploader( row );
		} );
	}

	/**
	 * Wires picker behavior for every enhanced select on the screen.
	 *
	 * PRs 3-4 render each picker as a `.cwc-picker` wrapper holding an
	 * enhanced <select> (selectWoo AJAX search), an empty `.cwc-chip-list`
	 * and the empty-state CTA. Until that markup exists this loop matches
	 * nothing, so the current screen keeps its pre-picker behavior (FA-5).
	 *
	 * @return {void}
	 */
	function initPickers() {
		var pickers = document.querySelectorAll( '.cwc-picker' );

		pickers.forEach( function ( picker ) {
			var select = picker.querySelector( 'select[multiple]' );
			var list   = picker.querySelector( '.cwc-chip-list' );
			var cta    = picker.querySelector( '.cwc-empty-cta' );

			if ( ! select || ! list ) {
				return;
			}

			makeSortable( list, select );
			rebuildChipList( list, select, cta );

			// selectWoo fires `change` when options are added or removed via
			// AJAX search; rebuilding from it keeps the list and the select in
			// sync (D3).
			select.addEventListener(
				'change',
				function () {
					rebuildChipList( list, select, cta );
				}
			);
		} );
	}

	/**
	 * Makes the chip list sortable and reorders the select on drop.
	 *
	 * jQuery UI sortable (enqueued as `jquery-ui-sortable`) drives the drag;
	 * the handle keeps the thumb/name/remove row from hijacking the gesture.
	 * On sortstop the select options are reordered to match the chips, so the
	 * native form POST serializes the visible DOM order (D1).
	 *
	 * @param {HTMLElement} list   Chip list element (.cwc-chip-list).
	 * @param {HTMLElement} select Enhanced select element.
	 * @return {void}
	 */
	function makeSortable( list, select ) {
		if ( typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.sortable === 'undefined' ) {
			return;
		}

		window.jQuery( list ).sortable( {
			handle: '.cwc-chip-handle',
			axis: 'y',
			stop: function () {
				syncSelectOrder( list, select );
			}
		} );
	}

	/**
	 * Reorders the select's options to match the visible chip order.
	 *
	 * Iterates the chips' `data-id` order and re-appends each matching
	 * option; appending an existing option moves it to the end in DOM terms,
	 * so the final option order equals the chip order and the form POST
	 * carries it (D1).
	 *
	 * @param {HTMLElement} list   Chip list element.
	 * @param {HTMLElement} select Enhanced select element.
	 * @return {void}
	 */
	function syncSelectOrder( list, select ) {
		var chips = list.querySelectorAll( 'li[data-id]' );

		chips.forEach( function ( chip ) {
			var option = select.querySelector( 'option[value="' + chip.getAttribute( 'data-id' ) + '"]' );

			if ( option ) {
				select.appendChild( option );
			}
		} );
	}

	/**
	 * Rebuilds the chip list from the select's selected options.
	 *
	 * The select is the source of truth for the selection; the list is the
	 * visible, sortable projection of it (D3). Each selected option renders
	 * one `li[data-id]` chip, and the empty-state CTA shows only while
	 * nothing is selected (AS-12).
	 *
	 * @param {HTMLElement}  list   Chip list element.
	 * @param {HTMLElement}  select Enhanced select element.
	 * @param {?HTMLElement} cta    Empty-state CTA element (may be absent).
	 * @return {void}
	 */
	function rebuildChipList( list, select, cta ) {
		var options = select.querySelectorAll( 'option:checked' );

		while ( list.firstChild ) {
			list.removeChild( list.firstChild );
		}

		options.forEach( function ( option ) {
			list.appendChild( createChip( option ) );
		} );

		if ( cta ) {
			cta.hidden = options.length > 0;
		}
	}

	/**
	 * Builds one chip element from a selected option.
	 *
	 * The chip mirrors the option: the value in `data-id`, the label as the
	 * chip name, and an optional `data-thumb` url for the thumbnail (PRs 3-4
	 * populate it from the term/product data the PHP pre-render already
	 * holds). The remove button deselects the option and fires a bubbling
	 * `change`, which rebuilds the list; PRs 3-4 can swap this for selectWoo's
	 * own change trigger once the enhanced select owns the visible state.
	 *
	 * @param {HTMLElement} option Selected option element.
	 * @return {HTMLElement} The chip li element.
	 */
	function createChip( option ) {
		var li     = document.createElement( 'li' );
		var handle = document.createElement( 'span' );
		var thumb  = document.createElement( 'span' );
		var name   = document.createElement( 'span' );
		var remove = document.createElement( 'button' );

		li.className = 'cwc-chip';
		li.setAttribute( 'data-id', option.value );

		handle.className = 'cwc-chip-handle';
		handle.title = strings.sortHandle || '';
		li.appendChild( handle );

		thumb.className = 'cwc-chip-thumb';

		if ( option.getAttribute( 'data-thumb' ) ) {
			var img = document.createElement( 'img' );
			img.src = option.getAttribute( 'data-thumb' );
			img.alt = '';
			thumb.appendChild( img );
		}

		li.appendChild( thumb );

		name.className = 'cwc-chip-name';
		name.textContent = option.textContent;
		li.appendChild( name );

		remove.type = 'button';
		remove.className = 'cwc-chip-remove';
		remove.textContent = strings.removeChip || '';
		remove.addEventListener(
			'click',
			function () {
				option.selected = false;
				option.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
			}
		);
		li.appendChild( remove );

		return li;
	}

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
			title: strings.mediaTitle,
			button: {
				text: strings.mediaButton
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