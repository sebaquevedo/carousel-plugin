/**
 * Frontend carousel bootstrap (vanilla JS, no jQuery).
 *
 * Initializes every `.cwc-carousel.swiper` container on DOMContentLoaded after
 * the vendored Swiper bundle (printed in the footer before this script, via
 * the cwc-swiper dependency) has defined the global `Swiper`.
 *
 * Responsive columns use the resolved defaults (Resolved Decision 2, D4):
 * 1 slide per view on mobile, 2 from >=768px, 3 from >=1024px, with a 16px
 * gap that mirrors the `--cwc-gap` custom property in carousel.css (FA-3).
 *
 * The compound selector `.cwc-carousel.swiper` matches the single container
 * the renderer emits (`<div class="cwc-carousel swiper">`); a descendant
 * selector would match zero nodes since both classes live on one element.
 * Navigation/pagination selectors are scoped to each container, so multiple
 * carousels on one page each get their own controls.
 *
 * @package CWC_Carousel
 */

( function () {
	'use strict';

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

					new Swiper(
						carousel,
						{
							slidesPerView: 1,
							spaceBetween: 16,
							navigation: {
								nextEl: '.swiper-button-next',
								prevEl: '.swiper-button-prev'
							},
							pagination: {
								el: '.swiper-pagination',
								clickable: true
							},
							breakpoints: {
								768: {
									slidesPerView: 2
								},
								1024: {
									slidesPerView: 3
								}
							}
						}
					);
				}
			);
		}
	);
} )();