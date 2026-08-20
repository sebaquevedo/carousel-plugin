/**
 * Admin scripts: tabbed editor chrome, shortcode copy, and the enhanced-search
 * picker chips with inline per-chip uploader.
 *
 * Three modules, all scoped to the Carousel settings screen by CWC_Assets
 * (FA-5):
 *
 * 1. Tabs — the four-panel editor (AS-16, D5) renders as nav-tab <button>s
 *    plus one .cwc-panel per section, all inside the single form. The panels
 *    start VISIBLE (no hidden attribute) so a JS-free browser sees every
 *    field and the one nonce + submit still save (AS-16); this module hides
 *    the inactive panels and wires the roving-tabindex tab behavior (role /
 *    aria-selected / arrow keys).
 *
 * 2. Shortcode copy — the edit-mode header field (AS-17): clicking the
 *    read-only input selects its text, the copy button writes it to the
 *    clipboard (with a legacy execCommand fallback) and flashes its
 *    "Copied!" label. The two labels travel as data-* attributes, so no new
 *    localized strings are needed (D8).
 *
 * 3. Color sync — the six navigation-color rows (AS-17, D4): an
 *    input[type=color] picker next to a hex text input. The hex input is the
 *    posting field; the picker carries no `name`, so it can never post the
 *    #000000 value browsers coerce from an empty color (D4). This module
 *    keeps the two in sync both ways — picking writes the hex, typing a
 *    valid 6-digit hex moves the picker.
 *
 * 4. Picker + inline media uploader — each `.cwc-picker` wrapper (CWC_Admin)
 *    holds a multiple enhanced <select> (selectWoo AJAX search), the visible
 *    sortable `.cwc-chip-list`, and the empty-state CTA. This script takes
 *    over the selectWoo init from WooCommerce for both pickers: the category
 *    search (`.wc-category-search`, AS-11) and the product/variation search
 *    (`.wc-product-search`, AS-12). The selects render with the `enhanced`
 *    marker so wc-enhanced-select.js skips them (WC 11.0's stock init neither
 *    forwards `show_empty` to the categories endpoint nor a stable value type
 *    without `data-return_id="id"`). The native option order is kept in sync
 *    with the chip order so the form POST serializes the visible order (D1,
 *    D3); the list rebuilds from `option:checked` on change (AS-11, AS-12).
 *    In edit mode each chip carries a "Choose image" button driving a
 *    wp.media frame (AS-3); on selection the hidden `cwc_cat_images[<term_id>]`
 *    attachment-id input updates and the chip thumbnail preview refreshes. An
 *    overlay-title text input posts `cwc_cat_titles[<term_id>]` (D5). Saving
 *    happens through the settings form's admin_init handler as usual — this
 *    script only mutates the DOM, it performs no AJAX (AS-3).
 *
 * The wp.media guard wraps ONLY the picker/uploader module: tabs and copy
 * run regardless, so the new behavior never depends on the media library
 * being primed (D8 module split — the old whole-script early return killed
 * everything on any other admin screen reached by a race).
 *
 * All user-facing strings come from the `cwcCarouselAdmin` object that
 * wp_localize_script registers with this script — no hardcoded literals
 * (FA-5). The picker dropdown messages mirror WC 11.0's
 * getEnhancedSelectFormatString() from WooCommerce's localized
 * `wc_enhanced_select_params`, so they stay translated (es_ES included).
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

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			initTabs();
			initShortcodeCopy();
			initColorSync();

			// wp.media is only present after wp_enqueue_media() has primed it;
			// without it the uploader has nothing to open, so the picker/
			// uploader module bails silently on screens where that race
			// happens (defensive, FA-5). Tabs, copy and color sync above
			// already ran.
			if ( typeof window.wp !== 'undefined' && typeof window.wp.media !== 'undefined' ) {
				initPickers();
			}
		}
	);

	/**
	 * Wires the four-panel tabbed editor (AS-16, D5).
	 *
	 * The nav-tab <button>s and their `.cwc-panel` sections are both inside
	 * the one settings form. The panels render WITHOUT the hidden attribute
	 * (a JS-free browser sees every field and the single submit still saves —
	 * AS-16); this function hides the inactive panels and drives the
	 * roving-tabindex pattern: only the active tab is focusable, ArrowLeft /
	 * ArrowRight activate the neighbour, and Enter/Space on a focused tab
	 * also activates it (the native click would anyway). Clicking a tab
	 * updates aria-selected on the tab and the hidden/visible state of the
	 * panels.
	 *
	 * @return {void}
	 */
	function initTabs() {
		var tablist = document.querySelector( '.cwc-tabs' );
		var panels  = document.querySelectorAll( '.cwc-panel' );

		if ( ! tablist || ! panels.length ) {
			return;
		}

		var tabs = tablist.querySelectorAll( '[role="tab"]' );

		function deactivate( tab ) {
			tab.classList.remove( 'nav-tab-active' );
			tab.setAttribute( 'aria-selected', 'false' );
			tab.setAttribute( 'tabindex', '-1' );

			var panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );
			if ( panel ) {
				panel.hidden = true;
			}
		}

		function activate( tab ) {
			tab.classList.add( 'nav-tab-active' );
			tab.setAttribute( 'aria-selected', 'true' );
			tab.setAttribute( 'tabindex', '0' );
			tab.focus();

			var panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );
			if ( panel ) {
				panel.hidden = false;
			}
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener(
				'click',
				function () {
					tabs.forEach( deactivate );
					activate( tab );
				}
			);

			tab.addEventListener(
				'keydown',
				function ( event ) {
					// ArrowLeft/ArrowRight rove between tabs (D5); Enter and
					// Space fall through to the native click activation.
					if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) {
						return;
					}

					event.preventDefault();

					var index = Array.prototype.indexOf.call( tabs, tab );
					var next  = 'ArrowRight' === event.key ? index + 1 : index - 1;

					if ( next < 0 ) {
						next = tabs.length - 1;
					} else if ( next >= tabs.length ) {
						next = 0;
					}

					tabs.forEach( deactivate );
					activate( tabs[ next ] );
				}
			);
		} );

		// Initial state (AS-16): hide every panel except the active tab's, and
		// keep the active tab's aria-selected in sync with its markup.
		tabs.forEach( function ( tab ) {
			if ( 'true' !== tab.getAttribute( 'aria-selected' ) ) {
				deactivate( tab );
			}
		} );
	}

	/**
	 * Wires the edit-mode shortcode copy header field (AS-17).
	 *
	 * The read-only input selects its text on click; the copy button reads the
	 * input's value, writes it to the clipboard and swaps its label from
	 * data-copy-label to data-copied-label for two seconds. A legacy
	 * document.execCommand( 'copy' ) fallback keeps the button useful where
	 * navigator.clipboard is unavailable (non-secure contexts, older
	 * browsers) — the input is already focused by the preceding select().
	 * The two labels live on the button as data-* attributes, so the PHP side
	 * needs no new localized strings (D8).
	 *
	 * @return {void}
	 */
	function initShortcodeCopy() {
		var buttons = document.querySelectorAll( '.cwc-shortcode-copy' );

		if ( ! buttons.length ) {
			return;
		}

		buttons.forEach( function ( button ) {
			var input = document.getElementById( button.getAttribute( 'data-copy-target' ) );

			if ( ! input ) {
				return;
			}

			input.addEventListener(
				'click',
				function () {
					input.select();
				}
			);

			button.addEventListener(
				'click',
				function () {
					input.select();
					copyText( input.value, button );
				}
			);
		} );
	}

	/**
	 * Copies text to the clipboard, then flashes the button's copied label.
	 *
	 * @param {string}      text   The text to copy.
	 * @param {HTMLElement} button The copy button (data-copy-label / data-copied-label).
	 * @return {void}
	 */
	function copyText( text, button ) {
		var copied = button.getAttribute( 'data-copied-label' );
		var label  = button.getAttribute( 'data-copy-label' );

		function restore() {
			button.textContent = label;
		}

		function flash() {
			button.textContent = copied;
			window.setTimeout( restore, 2000 );
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( flash, flash );
			return;
		}

		// Legacy fallback: the input was just selected by the click handler,
		// so execCommand( 'copy' ) copies its value.
		if ( document.execCommand( 'copy' ) ) {
			flash();
		}
	}

	/**
	 * Wires the navigation-color pickers to their hex inputs (AS-17, D4).
	 *
	 * Each color row renders an input[type=color] picker plus a hex text
	 * input that IS the posting field: the picker carries no `name`, because
	 * browsers coerce an empty color value to #000000 on submit and would
	 * store a black arrow for every unset key (D4). Picking a color writes
	 * its #rrggbb value into the hex input; typing a valid 6-digit hex in
	 * the text input moves the picker. An empty hex input posts '' and
	 * normalize() keeps it empty (theme default, CM-13), so the picker's own
	 * value never matters for storage. Runs unconditionally — this module
	 * needs no wp.media (D8 module split).
	 *
	 * @return {void}
	 */
	function initColorSync() {
		var pickers = document.querySelectorAll( '.cwc-color-picker' );

		pickers.forEach( function ( picker ) {
			var hex = document.getElementById( picker.getAttribute( 'data-cwc-hex' ) );

			if ( ! hex ) {
				return;
			}

			picker.addEventListener(
				'input',
				function () {
					hex.value = picker.value;
				}
			);

			hex.addEventListener(
				'input',
				function () {
					var value = hex.value.trim();

					if ( /^#[0-9a-f]{6}$/i.test( value ) ) {
						picker.value = value;
					}
				}
			);
		} );
	}

	/**
	 * Wires picker behavior for every enhanced select on the screen.
	 *
	 * Each picker renders as a `.cwc-picker` wrapper holding an enhanced
	 * <select> (selectWoo AJAX search), an empty `.cwc-chip-list` and the
	 * empty-state CTA. The selectWoo init runs here — not through
	 * wc-enhanced-select.js — so the picker can pass `show_empty` and a
	 * stable value type (AS-11; the `enhanced` class in the PHP markup
	 * blocks WooCommerce's own init, WC 11.0).
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

			initEnhancedSelect( picker, select );
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

			// selectWoo's native `change` does not always fire synchronously
			// for AJAX multiple selects, so also listen to the canonical
			// `select2:select` / `select2:unselect` events and rebuild from
			// there (D3). Both paths converge on rebuildChipList, which reads
			// the select's `option:checked` as the single source of truth.
			window.jQuery( select ).on(
				'select2:select select2:unselect',
				function () {
					rebuildChipList( list, select, cta );
				}
			);

			if ( cta ) {
				cta.addEventListener(
					'click',
					function () {
						focusSearch( picker );
					}
				);
			}
		} );
	}

	/**
	 * Initializes a picker's select with selectWoo AJAX search.
	 *
	 * Mirrors WooCommerce's own `wc-category-search` / `wc-product-search`
	 * init (WC 11.0): same endpoints, same nonces, same localized dropdown
	 * strings. Two deliberate additions — `show_empty: 1` keeps empty
	 * categories selectable (the previous native field listed them,
	 * hide_empty=false, AS-11) and `data-return_id="id"` keeps term ids as
	 * option values (sanitize_ids). The products branch (`.wc-product-search`,
	 * AS-12) uses the `_and_variations` endpoint so variations are selectable
	 * and maps its flat `{ id: formatted_name }` response. The `enhanced`
	 * marker in the PHP markup blocks wc-enhanced-select.js from
	 * initializing these selects itself.
	 *
	 * @param {HTMLElement} picker Picker wrapper element.
	 * @param {HTMLElement} select Enhanced select element.
	 * @return {void}
	 */
	function initEnhancedSelect( picker, select ) {
		if (
			typeof window.jQuery === 'undefined' ||
			typeof window.jQuery.fn.selectWoo === 'undefined'
		) {
			return;
		}

		// WC localizes its enhanced-select params (ajax_url, nonces, i18n
		// strings) onto the handle CWC_Assets enqueues, so the object is
		// present on this screen; bail defensively otherwise.
		var params = window.wc_enhanced_select_params || {};

		// Branch on the search type: `.wc-product-search` (products +
		// variations, AS-12) or `.wc-category-search` (terms, AS-11). Each
		// has its own endpoint and nonce key.
		var isProductSearch = select.classList.contains( 'wc-product-search' );

		if ( ! isProductSearch && ! select.classList.contains( 'wc-category-search' ) ) {
			return;
		}

		var action = isProductSearch
			? 'woocommerce_json_search_products_and_variations'
			: 'woocommerce_json_search_categories';

		var nonce = isProductSearch ? params.search_products_nonce : params.search_categories_nonce;

		if ( ! params.ajax_url || ! nonce ) {
			return;
		}

		var minLength = parseInt( select.getAttribute( 'data-minimum_input_length' ) || '1', 10 ) || 1;
		var returnId  = 'id' === select.getAttribute( 'data-return_id' );

		window.jQuery( select )
			.selectWoo( {
				width: '100%',
				allowClear: false,
				placeholder: select.getAttribute( 'data-placeholder' ),
				minimumInputLength: minLength,
				escapeMarkup: function ( m ) {
					return m;
				},
				ajax: {
					url: params.ajax_url,
					dataType: 'json',
					delay: 250,
					data: function ( request ) {
						var data = {
							term: request.term,
							action: action,
							security: nonce
						};

						// Categories: keep empty terms selectable (AS-11).
						// The products endpoint has no such flag.
						if ( ! isProductSearch ) {
							data.show_empty = 1;
						}

						return data;
					},
					processResults: function ( data ) {
						var results = [];

						if ( data ) {
							Object.keys( data ).forEach( function ( key ) {
								var item = data[ key ];

								if ( ! item ) {
									return;
								}

								if ( isProductSearch ) {
									// The products endpoint returns a flat
									// { id: formatted_name } map — id/text
									// straight through (WC_AJAX).
									results.push( {
										id: key,
										text: item
									} );
								} else {
									results.push( {
										id: returnId ? String( item.term_id ) : item.slug,
										text: item.formatted_name || item.name
									} );
								}
							} );
						}

						return { results: results };
					},
					cache: true
				},
				language: enhancedSelectLanguage( params )
			} )
			.addClass( 'enhanced' );
	}

	/**
	 * Builds selectWoo's language strings from WC's localized params.
	 *
	 * Faithful mirror of WC 11.0's getEnhancedSelectFormatString() so the
	 * dropdown messages match what the stock enhanced selects show (the
	 * `i18n_*` keys of `wc_enhanced_select_params`, es_ES included). The
	 * empty fallback keeps the code safe if any key is missing.
	 *
	 * @param {Object} params Localized wc_enhanced_select_params object.
	 * @return {Object} selectWoo `language` option.
	 */
	function enhancedSelectLanguage( params ) {
		return {
			errorLoading: function () {
				// Workaround for select2#4355, same as WC 11.0.
				return params.i18n_searching || '';
			},
			inputTooLong: function ( args ) {
				var overChars = args.input.length - args.maximum;

				if ( 1 === overChars ) {
					return params.i18n_input_too_long_1 || '';
				}

				return ( params.i18n_input_too_long_n || '' ).replace( '%qty%', overChars );
			},
			inputTooShort: function ( args ) {
				var remainingChars = args.minimum - args.input.length;

				if ( 1 === remainingChars ) {
					return params.i18n_input_too_short_1 || '';
				}

				return ( params.i18n_input_too_short_n || '' ).replace( '%qty%', remainingChars );
			},
			loadingMore: function () {
				return params.i18n_load_more || '';
			},
			maximumSelected: function ( args ) {
				if ( args.maximum === 1 ) {
					return params.i18n_selection_too_long_1 || '';
				}

				return ( params.i18n_selection_too_long_n || '' ).replace( '%qty%', args.maximum );
			},
			noResults: function () {
				return params.i18n_no_matches || '';
			},
			searching: function () {
				return params.i18n_searching || '';
			}
		};
	}

	/**
	 * Opens the picker's Select2 dropdown and focuses its search input.
	 *
	 * The empty-state CTA ("Add categories" / "Add products") leads here:
	 * clicking it is the equivalent of focusing the select, which is
	 * otherwise invisible while the enhanced select is hidden (AS-12).
	 *
	 * @param {HTMLElement} picker Picker wrapper element.
	 * @return {void}
	 */
	function focusSearch( picker ) {
		if (
			typeof window.jQuery === 'undefined' ||
			typeof window.jQuery.fn.selectWoo === 'undefined'
		) {
			return;
		}

		var select = picker.querySelector( 'select[multiple]' );

		if ( ! select ) {
			return;
		}

		window.jQuery( select ).selectWoo( 'open' );

		var field = picker.querySelector( '.select2-search__field' );

		if ( field ) {
			field.focus();
		}
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
	 * chip name, and an optional `data-thumb` url for the thumbnail (the PHP
	 * pre-render populates it from the term's stored image meta, AS-3). In
	 * edit mode (picker carries `data-edit`) the inline upload/title
	 * controls render too (D5). The remove button deselects the option and
	 * fires a bubbling `change`; the native option state drives both the
	 * visible list and the form POST (D3).
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
		var picker = option.closest( '.cwc-picker' );

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

		// Edit-only inline controls (D5): the upload button, hidden
		// attachment-id input and overlay-title input render only when the
		// picker carries data-edit (edit mode). The form only emits the save
		// nonce in edit mode, so create stays BC (AS-3).
		if ( picker && picker.hasAttribute( 'data-edit' ) ) {
			appendEditControls( li, option );
		}

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
	 * Appends the inline upload + overlay-title controls to a chip.
	 *
	 * The hidden input posts `cwc_cat_images[term_id]`; the upload button
	 * drives the same wp.media frame as the former "Category images" rows
	 * (AS-3), using the chip thumbnail as its live preview. The title input
	 * posts `cwc_cat_titles[term_id]` and pre-fills from the option's stored
	 * meta (`data-title`); an empty value deletes the meta on save
	 * (sanitize_text_field, CR-9). Removing the chip does NOT clear the term
	 * image — that meta is global to the term, not per carousel instance.
	 *
	 * @param {HTMLElement} li     Chip element being built.
	 * @param {HTMLElement} option Selected option with data attributes.
	 * @return {void}
	 */
	function appendEditControls( li, option ) {
		var imageId = document.createElement( 'input' );
		var upload  = document.createElement( 'button' );
		var title   = document.createElement( 'input' );

		imageId.type = 'hidden';
		imageId.className = 'cwc-cat-image-id';
		imageId.name = 'cwc_cat_images[' + option.value + ']';
		imageId.value = option.getAttribute( 'data-image-id' ) || '';

		upload.type = 'button';
		upload.className = 'button cwc-cat-image-upload';
		upload.textContent = strings.chooseImage || '';

		title.type = 'text';
		title.className = 'cwc-cat-title-input';
		title.name = 'cwc_cat_titles[' + option.value + ']';
		title.value = option.getAttribute( 'data-title' ) || '';
		title.placeholder = strings.overlayTitle || '';

		li.appendChild( imageId );
		li.appendChild( upload );
		li.appendChild( title );

		// The chip thumb doubles as the live preview; attachUploader restores
		// the stored override from the hidden input when it carries an id.
		attachUploader( li, li.querySelector( '.cwc-chip-thumb' ) );
	}

	/**
	 * Wires the upload button inside one chip (or row).
	 *
	 * Creates a reusable wp.media frame (image-only, single select). On
	 * selection it writes the attachment id into the element's hidden input
	 * and refreshes the preview. A "remove image" button (when present)
	 * clears both.
	 *
	 * @param {HTMLElement}  row     Chip or row element with the upload
	 *                               controls.
	 * @param {?HTMLElement} preview Preview element to refresh; defaults to
	 *                               .cwc-cat-image-preview within the row.
	 * @return {void}
	 */
	function attachUploader( row, preview ) {
		var trigger = row.querySelector( '.cwc-cat-image-upload' );
		var remove  = row.querySelector( '.cwc-cat-image-remove' );
		var input   = row.querySelector( '.cwc-cat-image-id' );
		var frame;

		if ( ! trigger || ! input ) {
			return;
		}

		if ( ! preview ) {
			preview = row.querySelector( '.cwc-cat-image-preview' );
		}

		if ( ! preview ) {
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
	 * inside the chip thumb / `.cwc-cat-image-preview`; replace its content
	 * with an `<img>` so the live preview always shows one source.
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