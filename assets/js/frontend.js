/**
 * Frontend carousel bootstrap (vanilla JS, no jQuery).
 *
 * Initializes every `.cwc-carousel.swiper` container on DOMContentLoaded after
 * the vendored Swiper bundle (printed in the footer before this script, via
 * the cwc-swiper dependency) has defined the global `Swiper`.
 *
 * Each container carries its own resolved config in a `data-cwc-config` JSON
 * attribute emitted by the renderer (CR-7). Swiper options — slides per view,
 * responsive breakpoints, and gap — are built from that config per container
 * (FA-4), so two carousels of the same shortcode on one page each use their own
 * setup. When the attribute is missing or malformed, the module falls back to
 * default values (1/2/3 slides, 16px gap), keeping slice-1 shortcodes working.
 *
 * The compound selector `.cwc-carousel.swiper` matches the single container the
 * renderer emits (`<div class="cwc-carousel swiper">`); a descendant selector
 * would match zero nodes since both classes live on one element. Navigation and
 * pagination selectors are resolved per container, so multiple carousels on one
 * page each get their own controls.
 *
 * @package CWC_Carousel
 */

( function () {
	'use strict';

	// Fallbacks keep slice-1 carousels (no data attribute) rendering like before.
	var DEFAULT_OPTIONS = {
		slidesMobile: 1,
		slidesTablet: 2,
		slidesDesktop: 3,
		spaceBetween: 16
	};

	document.addEventListener(
		'DOMContentLoaded',
		function () {
			var carousels = document.querySelectorAll( '.cwc-carousel.swiper' );

			carousels.forEach(
				function ( carousel ) {
					// The cwc-swiper handle guarantees Swiper is loaded; this guard
					// keeps an init error from breaking other frontend scripts.
					if ( typeof Swiper === 'undefined' ) {
						return;
					}

					new Swiper( carousel, buildOptions( readConfig( carousel ) ) );
				}
			);
		}
	);

	/**
	 * Reads the container's resolved config.
	 *
	 * Tolerates a missing or malformed attribute (FA-4 fallback) by returning
	 * an empty object so buildOptions() uses its defaults.
	 *
	 * @param {HTMLElement} carousel Carousel container element.
	 * @return {Object} Parsed config object, or an empty object.
	 */
	function readConfig( carousel ) {
		if ( ! carousel.dataset.cwcConfig ) {
			return {};
		}

		try {
			var config = JSON.parse( carousel.dataset.cwcConfig );
			return config && typeof config === 'object' ? config : {};
		} catch ( err ) {
			return {};
		}
	}

	/**
	 * Builds the Swiper options for one container from its config.
	 *
	 * The resolved config exposes `slides_mobile`, `slides_tablet`, and `slides`
	 * (desktop) plus `gap`. The constructor mirrors the `--cwc-gap` custom
	 * property from carousel.css and the responsive 1/2/3 ramp.
	 *
	 * @param {Object} config Resolved per-container config.
	 * @return {Object} Swiper options.
	 */
	function buildOptions( config ) {
		var slidesMobile  = numberOr( config.slides_mobile, DEFAULT_OPTIONS.slidesMobile );
		var slidesTablet  = numberOr( config.slides_tablet, DEFAULT_OPTIONS.slidesTablet );
		var slidesDesktop = numberOr( config.slides, DEFAULT_OPTIONS.slidesDesktop );
		var gap           = numberOr( config.gap, DEFAULT_OPTIONS.spaceBetween );

		// Only a resolved `false` disables a control; `undefined` (legacy
		// containers without data-cwc-config) keeps today's always-on look
		// (FA-6), so DEFAULT_OPTIONS paths still get both controls.
		var options = {
			// With breakpointsBase defaulting to "window", breakpoints resolve
			// against the viewport, but Swiper's default resizeObserver:true only
			// watches the container element and skips re-evaluation when its width
			// does not change in step with the window. Disabling it lets Swiper
			// listen to window resize and re-evaluate breakpoints (FA-4).
			resizeObserver: false,
			slidesPerView: slidesMobile,
			spaceBetween: gap,
			breakpoints: {
				768: {
					slidesPerView: slidesTablet
				},
				1024: {
					slidesPerView: slidesDesktop
				}
			}
		};

		if ( config.arrows !== false ) {
			options.navigation = {
				nextEl: '.swiper-button-next',
				prevEl: '.swiper-button-prev'
			};
		}

		if ( config.pagination !== false ) {
			options.pagination = {
				el: '.swiper-pagination',
				clickable: true
			};
		}

		return options;
	}

	/**
	 * Returns a finite positive number, or the fallback default.
	 *
	 * @param {*} value Raw config value.
	 * @param {number} fallback Default when value is not usable.
	 * @return {number} Usable slides/gap number.
	 */
	function numberOr( value, fallback ) {
		var number = Number( value );
		return isFinite( number ) && number > 0 ? number : fallback;
	}
} )();