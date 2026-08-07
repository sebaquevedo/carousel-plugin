<?php
/**
 * Settings model: pure configuration defaults and per-instance resolution.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Resolves carousel configuration from global defaults and shortcode atts.
 *
 * Pure and side-effect-free (CM-4): `defaults()` reads the sanitized
 * `cwc_carousel_options` option with autoload off and falls back to built-in
 * defaults when the option is absent or partial (CM-1); `resolve()` merges
 * administrative defaults with explicit shortcode attributes, dropping any
 * key outside the known config set (CM-2). `count` is numeric-only via
 * absint, so a zero count yields an empty carousel and no "all" sentinel
 * exists (CM-3).
 *
 * @since 0.1.0
 */
class CWC_Settings {

	/**
	 * Returns the global default configuration.
	 *
	 * Reads the sanitized `cwc_carousel_options` option, aligning it over the
	 * built-in defaults so every resolved key is always present. The method
	 * never writes the option (CM-1): it falls back to built-ins when the
	 * option does not exist.
	 *
	 * @since 0.1.0
	 *
	 * @return array Global default config keyed by resolved config key.
	 */
	public function defaults(): array {
		$option = get_option( 'cwc_carousel_options', array() );

		if ( ! is_array( $option ) ) {
			$option = array();
		}

		return $this->normalize( wp_parse_args( $option, $this->builtins() ) );
	}

	/**
	 * Resolves one carousel instance's configuration.
	 *
	 * Starts from the global defaults and whitelists the shortcode attributes
	 * (unknown atts are dropped), so an explicitly supplied attribute overrides
	 * the administrative default (CM-2). Shortcode attributes that are empty
	 * strings are treated as "not provided" and fall through to the defaults —
	 * shortcode_atts() fills absent attrs with "" so they must not override
	 * the administrative values. Each named instance resolves its own config —
	 * nothing global is mutated (CM-4, SC-5).
	 *
	 * @since 0.1.0
	 *
	 * @param array $atts Raw shortcode attributes.
	 * @return array Resolved config keyed by resolved config key.
	 */
	public function resolve( array $atts ): array {
		$defaults = $this->defaults();

		// shortcode_atts() fills attributes the shortcode does not set with ""
		// (not null), so an empty string means "not provided": drop it so the
		// administrative default applies. A string "0" is kept — count=0 still
		// yields an empty carousel per CM-3.
		$atts = array_filter(
			$atts,
			static function ( $value ) {
				return '' !== $value;
			}
		);

		$known = array(
			'type'          => $defaults['type'],
			'title'         => $defaults['title'],
			'category'      => $defaults['category'],
			'categories'    => $defaults['categories'],
			'mix'           => $defaults['mix'],
			'count'         => $defaults['count'],
			'slides'        => $defaults['slides'],
			'slides_tablet' => $defaults['slides_tablet'],
			'slides_mobile' => $defaults['slides_mobile'],
			'gap'           => $defaults['gap'],
			'buy'           => $defaults['buy'],
			'buy_text'      => $defaults['buy_text'],
		);

		return $this->normalize( wp_parse_args( $atts, $known ) );
	}

	/**
	 * Returns the hardcoded fallback defaults.
	 *
	 * These equal yesterday's hardcoded presentation values (slides 3/2/1,
	 * gap 16, count 8, buy on with "Comprar") so an absent option renders the
	 * same as slice 1 (CM-1, D3).
	 *
	 * @since 0.1.0
	 *
	 * @return array Built-in default values keyed by resolved config key.
	 */
	private function builtins(): array {
		return array(
			'type'          => 'product',
			'title'         => '',
			'category'      => 0,
			'categories'    => array(),
			'mix'           => false,
			'count'         => 8,
			'slides'        => 3,
			'slides_tablet' => 2,
			'slides_mobile' => 1,
			'gap'           => 16,
			'buy'           => true,
			'buy_text'      => 'Comprar',
		);
	}

	/**
	 * Sanitizes and coerces a raw config array into the resolved contract.
	 *
	 * Every key on the resolved shape is present regardless of input, and each
	 * value is coerced to its documented type so the result always serializes
	 * cleanly with wp_json_encode() (CM-4). Unknown input keys are dropped.
	 *
	 * @since 0.1.0
	 *
	 * @param array $config Raw config array (option or shortcode atts subset).
	 * @return array Normalized resolved config array.
	 */
	private function normalize( array $config ): array {
		$merged = wp_parse_args( $config, $this->builtins() );

		$normalized = array(
			'type'          => ( 'category' === $merged['type'] ) ? 'category' : 'product',
			'title'         => sanitize_text_field( (string) $merged['title'] ),
			'category'      => absint( $merged['category'] ),
			'categories'    => $this->sanitize_ids( $merged['categories'] ),
			'mix'           => $this->parse_bool( $merged['mix'] ),
			'count'         => absint( $merged['count'] ),
			'slides'        => absint( $merged['slides'] ),
			'slides_tablet' => absint( $merged['slides_tablet'] ),
			'slides_mobile' => absint( $merged['slides_mobile'] ),
			'gap'           => absint( $merged['gap'] ),
			'buy'           => $this->parse_bool( $merged['buy'] ),
			'buy_text'      => sanitize_text_field( (string) $merged['buy_text'] ),
		);

		if ( '' === $normalized['buy_text'] ) {
			$normalized['buy_text'] = $this->builtins()['buy_text'];
		}

		return $normalized;
	}

	/**
	 * Parses a truthy/falsy shortcode value into a boolean.
	 *
	 * Accepts the string tokens WordPress conventions use for on/off ("yes",
	 * "1", "true", "on") plus actual booleans, so both administrative defaults
	 * and shortcode attributes normalize identically.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value Raw boolean-ish value.
	 * @return bool Normalized boolean.
	 */
	private function parse_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Coerces a raw ID list into non-zero positive integers.
	 *
	 * Accepts an int[], a comma/whitespace-separated string, or a scalar, and
	 * re-indexes the result so only valid term or product ids remain.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $raw Raw categories value.
	 * @return int[] Sanitized positive integer IDs (may be empty).
	 */
	private function sanitize_ids( $raw ): array {
		if ( is_array( $raw ) ) {
			$ids = array_map( 'absint', $raw );
		} else {
			$ids = array_map( 'absint', wp_parse_id_list( (string) $raw ) );
		}

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return $id > 0;
				}
			)
		);
	}
}
