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
 * Resolves carousel configuration from the named-instance registry and
 * shortcode atts.
 *
 * Pure and side-effect-free (CM-4): `defaults()` reads the sanitized
 * `cwc_carousel_options` option with autoload off and falls back to built-in
 * defaults when the option is absent or partial (CM-1); `resolve()` merges
 * administrative defaults with explicit shortcode attributes, dropping any
 * key outside the known config set (CM-2). `count` is numeric-only via
 * absint, so a zero count yields an empty carousel and no "all" sentinel
 * exists (CM-3).
 *
 * The model is registry-aware: `registry()` reads the `cwc_carousel_registry`
 * option (autoload off, CM-7) and `resolve()` selects the named instance — or
 * the reserved `default`, never fatal (CM-8) — as the merge base. The
 * registry is read-only here: the admin writes it (AS-5) and the bootstrap
 * seeds it once (PB-4). Every resolved instance exposes exactly the 14-key
 * contract via `normalize()` (CM-9).
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
	 * Starts from the named instance's config (the reserved `default` when
	 * `name` is absent or unknown — never fatal, CM-8) and whitelists the
	 * shortcode attributes (unknown atts are dropped), so an explicitly
	 * supplied attribute overrides the instance default (CM-2). Shortcode
	 * attributes that are empty strings are treated as "not provided" and
	 * fall through to the instance defaults — shortcode_atts() fills absent
	 * attrs with "" so they must not override the administrative values. Each
	 * named instance resolves its own config — nothing global is mutated
	 * (CM-4, SC-5). `name` is consumed here and never serializes into the
	 * resolved contract (SC-8, CM-9).
	 *
	 * @since 0.1.0
	 *
	 * @param array $atts Raw shortcode attributes.
	 * @return array Resolved config keyed by resolved config key.
	 */
	public function resolve( array $atts ): array {
		// The `name` attribute selects the instance whose config becomes the
		// merge base. It is consumed before the empty-string filter so it can
		// never participate in attribute layering or leak into the 14-key
		// contract (SC-8, CM-9). An absent/empty name resolves the reserved
		// `default` instance — today's behavior (CM-8).
		$name = isset( $atts['name'] ) ? (string) $atts['name'] : '';
		unset( $atts['name'] );

		// Normalize the name through the shared slugify() so the shortcode
		// `name` and the admin's registry slugs normalize identically (AS-8):
		// sanitize_title + underscores to hyphens. `name="Productos"` or
		// `name="mi_carousel"` therefore resolve to the stored lowercase
		// hyphenated slug instead of silently falling back to `default`.
		$name = $this->slugify( $name );

		$base = $this->instance_base( $name );

		// shortcode_atts() fills attributes the shortcode does not set with ""
		// (not null), so an empty string means "not provided": drop it so the
		// instance default applies. A string "0" is kept — count=0 still
		// yields an empty carousel per CM-3.
		$atts = array_filter(
			$atts,
			static function ( $value ) {
				return '' !== $value;
			}
		);

		$known = array(
			'type'          => $base['type'],
			'title'         => $base['title'],
			'category'      => $base['category'],
			'categories'    => $base['categories'],
			'mix'           => $base['mix'],
			'count'         => $base['count'],
			'slides'        => $base['slides'],
			'slides_tablet' => $base['slides_tablet'],
			'slides_mobile' => $base['slides_mobile'],
			'gap'           => $base['gap'],
			'arrows'        => $base['arrows'],
			'pagination'    => $base['pagination'],
			'buy'           => $base['buy'],
			'buy_text'      => $base['buy_text'],
		);

		return $this->normalize( wp_parse_args( $atts, $known ) );
	}

	/**
	 * Slugifies a raw name into a registry key (AS-8).
	 *
	 * Applies sanitize_title() first, then folds any remaining underscore into
	 * a hyphen so stored slugs only ever contain lowercase letters, digits and
	 * hyphens (AS-8 charset). This is the single source of truth for slug
	 * normalization: the shortcode `name` attribute in resolve() and the
	 * admin's create/edit/delete flows (class-admin.php) all funnel through
	 * here.
	 *
	 * @since 0.1.0
	 *
	 * @param string $raw Raw name.
	 * @return string Slug (lowercase letters, numbers and hyphens).
	 */
	public function slugify( string $raw ): string {
		return str_replace( '_', '-', sanitize_title( $raw, '', 'save' ) );
	}

	/**
	 * Returns the named-instance registry as a slug-keyed map.
	 *
	 * Reads the `cwc_carousel_registry` option (autoload off). A missing or
	 * non-array option reads as an empty map; reading never writes or creates
	 * the option (CM-7) — the registry is written by the admin (AS-5) and
	 * seeded once by the bootstrap migration (PB-4).
	 *
	 * @since 0.1.0
	 *
	 * @return array { slug => full_config } registry map (may be empty).
	 */
	public function registry(): array {
		$registry = get_option( 'cwc_carousel_registry', array() );

		return is_array( $registry ) ? $registry : array();
	}

	/**
	 * Returns the config base for a named instance.
	 *
	 * The supplied slug wins when present; otherwise the reserved `default`
	 * instance is used, and as a last resort the legacy defaults() — so an
	 * unknown or missing name never fails (CM-8, D3). An edited `default`
	 * takes precedence over the stale legacy option.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Instance slug (may be empty for the default).
	 * @return array Normalized 14-key config for the base.
	 */
	public function instance_base( string $name ): array {
		$registry = $this->registry();

		if ( isset( $registry[ $name ] ) && is_array( $registry[ $name ] ) ) {
			return $this->normalize( $registry[ $name ] );
		}

		if ( isset( $registry['default'] ) && is_array( $registry['default'] ) ) {
			return $this->normalize( $registry['default'] );
		}

		return $this->defaults();
	}

	/**
	 * Returns the default named instances seeded on first migration.
	 *
	 * Only a defaults source for the bootstrap migration (PB-4): the registry
	 * is never auto-overwritten once present, so user edits survive. The
	 * values match CM-10: `productos` is a product carousel at 3/2/1 with
	 * arrows, pagination and buy on; `categorias` is a category carousel at
	 * 4/2/1 with arrows and pagination on (buy/buy_text carry their builtins).
	 *
	 * @since 0.1.0
	 *
	 * @return array { slug => normalized 14-key config } for the two seeds.
	 */
	public function seeds(): array {
		return array(
			'productos'  => $this->normalize(
				array(
					'type'          => 'product',
					'slides'        => 3,
					'slides_tablet' => 2,
					'slides_mobile' => 1,
					'gap'           => 16,
					'count'         => 8,
					'arrows'        => true,
					'pagination'    => true,
					'buy'           => true,
				)
			),
			'categorias' => $this->normalize(
				array(
					'type'          => 'category',
					'slides'        => 4,
					'slides_tablet' => 2,
					'slides_mobile' => 1,
					'gap'           => 16,
					'count'         => 8,
					'arrows'        => true,
					'pagination'    => true,
				)
			),
		);
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
	public function builtins(): array {
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
			'arrows'        => true,
			'pagination'    => true,
			'buy'           => true,
			'buy_text'      => 'Comprar',
		);
	}

	/**
	 * Sanitizes and coerces a raw config array into the resolved contract.
	 *
	 * Every key on the resolved shape is present regardless of input, and each
	 * value is coerced to its documented type so the result always serializes
	 * cleanly with wp_json_encode() (CM-4). Unknown input keys are dropped —
	 * including `name`, which resolve() consumes before merging (SC-8, CM-9).
	 *
	 * Shared coerce point (D5): resolve(), instance_base(), the bootstrap
	 * migration (PB-4), and the admin all funnel through this method so
	 * per-instance shapes never drift.
	 *
	 * @since 0.1.0
	 *
	 * @param array $config Raw config array (option or shortcode atts subset).
	 * @return array Normalized resolved config array.
	 */
	public function normalize( array $config ): array {
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
			'arrows'        => $this->parse_bool( $merged['arrows'] ),
			'pagination'    => $this->parse_bool( $merged['pagination'] ),
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
	public function parse_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Coerces a raw ID list into non-zero positive integers.
	 *
	 * Accepts an int[], a comma/whitespace-separated string, or a scalar, and
	 * re-indexes the result so only valid term or product ids remain. Shared
	 * with the admin sanitizer (class-admin.php) so both coerce identically.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $raw Raw categories value.
	 * @return int[] Sanitized positive integer IDs (may be empty).
	 */
	public function sanitize_ids( $raw ): array {
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
