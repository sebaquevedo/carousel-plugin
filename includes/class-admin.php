<?php
/**
 * Admin settings page: carousel registry and per-category image overrides.
 *
 * @package CWC_Carousel
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the Carousel settings page under WooCommerce and the two data paths.
 *
 * 1. Named carousel instances live in one sanitized keyed option,
 *    `cwc_carousel_registry` ({ slug => full_config }, autoload off, AS-5).
 *    Edits to an existing instance go through the Settings API and the
 *    per-slug `sanitize_registry()` callback; create and delete run on
 *    admin_init through handle_registry_actions() — mirroring
 *    save_category_images() (D7) — so the option sanitizer stays a pure
 *    per-slug edit surface (D4).
 * 2. Per-category image overrides are stored as the `cwc_cat_image` term meta
 *    key that CWC_Renderer reads at render time (AS-3). They are saved on
 *    admin_init, guarded by a nonce and the `manage_woocommerce` capability,
 *    so term side-effects never leak into the pure option sanitizer (D7).
 *
 * The page and all saves are gated by `manage_woocommerce` (AS-1).
 *
 * @since 0.1.0
 */
class CWC_Admin {

	/**
	 * Option group for register_setting + settings_fields.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $option_group = 'cwc_options_group';

	/**
	 * Slug for the Settings API page + submenu.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $page_slug = 'cwc-carousel';

	/**
	 * Nonce action used when saving per-category image overrides.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $image_nonce_action = 'cwc_save_category_image';

	/**
	 * Name of the nonce field used when saving category image overrides.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $image_nonce_field = 'cwc_category_image_nonce';

	/**
	 * Nonce action used when creating a new registry instance.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $create_nonce_action = 'cwc_create_carousel';

	/**
	 * Nonce action used when deleting a registry instance.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $delete_nonce_action = 'cwc_delete_carousel';

	/**
	 * Name of the nonce field used by the create/delete forms.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $registry_nonce_field = 'cwc_registry_nonce';

	/**
	 * Term meta key holding the custom category image attachment id.
	 *
	 * Must match the renderer's lookup (CR-5) so an uploaded override is what
	 * the category card actually shows (AS-3).
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $image_meta_key = 'cwc_cat_image';

	/**
	 * Term meta key holding the per-category overlay title.
	 *
	 * Must match the renderer's cover-title lookup (CR-9) so a saved title is
	 * what the cover card actually shows (AS-10). An empty value deletes the
	 * meta so the renderer falls back to the term name.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $title_meta_key = 'cwc_cat_title';

	/**
	 * Shared settings model instance backing every registry read/write.
	 *
	 * @since 0.1.0
	 *
	 * @var CWC_Settings
	 */
	private $settings;

	/**
	 * Registers the admin hooks.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->settings = new CWC_Settings();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'save_category_images' ) );
		add_action( 'admin_init', array( $this, 'handle_registry_actions' ) );
	}

	/**
	 * Adds the Carousel submenu page under the WooCommerce menu (AS-1).
	 *
	 * The page is gated by the `manage_woocommerce` capability via the menu
	 * argument; the save handlers repeat the same check (WD-4).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Carousel', 'cwc-carousel' ),
			__( 'Carousel', 'cwc-carousel' ),
			'manage_woocommerce',
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the registry option and its per-slug sanitize callback.
	 *
	 * `cwc_carousel_registry` is stored with autoload disabled and sanitized by
	 * a single callback (AS-5). The static Settings API section/fields are gone:
	 * the editor renders the same fields inline, prefixed per instance
	 * (`cwc_carousel_registry[slug][key]`). The legacy `cwc_carousel_options`
	 * option is no longer registered here — it stays untouched as the rollback
	 * path (PB-4) and is only ever read by CWC_Settings.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			$this->option_group,
			'cwc_carousel_registry',
			array(
				'type'              => 'array',
				'autoload'          => false,
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_registry' ),
			)
		);

		// Align the options.php save gate with the page/menu capability so a
		// Shop Manager (manage_woocommerce) who can open the page can also save
		// it (R1-W1). By default options.php requires manage_options, which
		// would 403 the same button that already wrote category images.
		add_filter(
			"option_page_capability_{$this->option_group}",
			static function () {
				return 'manage_woocommerce';
			}
		);
	}

	/**
	 * Renders the page by routing on the `cwc_action` query arg (AS-6, AS-7).
	 *
	 * No `cwc_action` renders the list; `edit`/`create` render the per-instance
	 * editor; `delete` renders the confirmation. Unknown values fall back to
	 * the list. The whole page stays gated by `manage_woocommerce` (AS-1).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing; state changes carry nonces.
		$action = sanitize_key( (string) wp_unslash( $_GET['cwc_action'] ?? '' ) );

		if ( 'edit' === $action || 'create' === $action ) {
			$this->render_editor( $action );
		} elseif ( 'delete' === $action ) {
			$this->render_delete_confirm();
		} else {
			$this->render_list();
		}
	}

	/**
	 * Renders the list of registered instances (AS-6).
	 *
	 * Every instance renders a row with its slug, type, slide ramp, controls
	 * and max items, plus Edit/Delete actions. The reserved `default` instance
	 * appears without delete (it is not deletable or renamable, AS-7).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_list() {
		$registry   = $this->settings->registry();
		$create_url = add_query_arg(
			array(
				'page'       => $this->page_slug,
				'cwc_action' => 'create',
			),
			admin_url( 'admin.php' )
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Carousels', 'cwc-carousel' ); ?></h1>
			<?php settings_errors(); ?>
			<p><?php esc_html_e( 'Each named carousel holds its own full configuration. Render one with [cwc_carousel name="slug"]; without a name the reserved "default" instance applies.', 'cwc-carousel' ); ?></p>
			<p><a href="<?php echo esc_url( $create_url ); ?>" class="button button-primary"><?php esc_html_e( 'Add new carousel', 'cwc-carousel' ); ?></a></p>

			<?php if ( empty( $registry ) ) : ?>
				<p><?php esc_html_e( 'No carousels registered yet.', 'cwc-carousel' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'cwc-carousel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'cwc-carousel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Slides (desktop / tablet / mobile)', 'cwc-carousel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Controls', 'cwc-carousel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Max items', 'cwc-carousel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'cwc-carousel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $registry as $slug => $instance ) : ?>
							<?php
							if ( ! is_array( $instance ) ) {
								continue;
							}

							$slug       = (string) $slug;
							$config     = $this->instance_current( $slug );
							$edit_url   = add_query_arg(
								array(
									'page'       => $this->page_slug,
									'cwc_action' => 'edit',
									'slug'       => $slug,
								),
								admin_url( 'admin.php' )
							);
							$delete_url = add_query_arg(
								array(
									'page'       => $this->page_slug,
									'cwc_action' => 'delete',
									'slug'       => $slug,
								),
								admin_url( 'admin.php' )
							);

							$controls = array();
							if ( $config['arrows'] ) {
								$controls[] = __( 'Arrows', 'cwc-carousel' );
							}
							if ( $config['pagination'] ) {
								$controls[] = __( 'Pagination', 'cwc-carousel' );
							}
							?>
							<tr>
								<td><strong><?php echo esc_html( $slug ); ?></strong></td>
								<td><?php echo ( 'category' === $config['type'] ) ? esc_html__( 'Categories', 'cwc-carousel' ) : esc_html__( 'Products', 'cwc-carousel' ); ?></td>
								<td><?php echo esc_html( $config['slides'] . ' / ' . $config['slides_tablet'] . ' / ' . $config['slides_mobile'] ); ?></td>
								<td><?php echo esc_html( $controls ? implode( ', ', $controls ) : __( 'None', 'cwc-carousel' ) ); ?></td>
								<td><?php echo esc_html( (string) $config['count'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'cwc-carousel' ); ?></a>
									<?php if ( 'default' === $slug ) : ?>
										<span class="description"><?php esc_html_e( '(reserved — not deletable or renamable)', 'cwc-carousel' ); ?></span>
									<?php else : ?>
										| <a href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'cwc-carousel' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the per-instance editor for `edit` or `create` (AS-7).
	 *
	 * The editor renders four nav-tab panels — Content / Behavior /
	 * Navigation / Style — over the ONE form and ONE submit (AS-16): every
	 * panel's fields stay in the DOM regardless of the active tab, so the
	 * Settings API nonce and the single submit never depend on JS (D5). Tab
	 * switching is a pure client-side visibility toggle in admin.js; without
	 * JS all panels render stacked and the form saves as today. Conditional
	 * rows stay in Content: the type=product products picker (AS-12) and the
	 * type=category cover field (AS-9) render only inside the Content panel.
	 * Edit mode also shows a read-only header shortcode field with a copy
	 * button (AS-17).
	 *
	 * The field renderers are reused with a per-instance name prefix
	 * (`cwc_carousel_registry[slug][key]`, or `... [__new__][key]` while
	 * creating). Edit posts to options.php through the Settings API; create
	 * posts back to this page so handle_registry_actions() can slugify and
	 * write the new key without ever overwriting an existing one (AS-8).
	 *
	 * @since 0.1.0
	 *
	 * @param string $mode `edit` or `create`.
	 * @return void
	 */
	public function render_editor( string $mode ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing; the edit form carries the Settings API nonce, create carries wp_nonce_field.
		$slug = isset( $_GET['slug'] ) ? $this->settings->slugify( sanitize_text_field( wp_unslash( $_GET['slug'] ) ) ) : '';

		if ( 'edit' === $mode ) {
			$registry = $this->settings->registry();

			if ( '' === $slug || ( 'default' !== $slug && ! isset( $registry[ $slug ] ) ) ) {
				add_settings_error( $this->option_group, 'cwc_unknown_slug', __( 'The requested carousel was not found.', 'cwc-carousel' ), 'error' );
				$this->render_list();
				return;
			}
		}

		if ( 'edit' === $mode ) {
			$current = $this->instance_current( $slug );
		} else {
			// The create form posts back to this page, so a rejected submission
			// re-renders on the same request with $_POST still populated.
			// Re-fill the form from the posted `__new__` values so the user can
			// correct and resubmit instead of silently reverting to the
			// "default" instance (create-form data-loss fix). A first load has
			// no posted `__new__` and keeps the "default" instance pre-fill.
			$defaults = $this->instance_current( 'default' );
			$posted   = ( isset( $_POST['cwc_carousel_registry']['__new__'] ) && is_array( $_POST['cwc_carousel_registry']['__new__'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only create re-render after a rejected submission; the nonce-verified handler wrote nothing on rejection.
				? wp_unslash( $_POST['cwc_carousel_registry']['__new__'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only create re-render after a rejected submission; every value is escaped on output by the field renderers below.
				: array();

			if ( empty( $posted ) ) {
				// First load: no posted `__new__` keeps the "default" instance pre-fill.
				$current = $defaults;
			} else {
				// Rejected submission: re-fill from the posted `__new__` values.
				// wp_parse_args() alone would let raw POST strings reach the
				// int-typed render_number() parameter (TypeError on PHP 8 when a
				// numeric field is empty/non-numeric) and would silently re-select
				// the default's categories when the multi-select posts no key at
				// all; coerce both before rendering.
				$current = wp_parse_args( $posted, $defaults );

				// Coerce numeric fields with the exact same clamp bound() applies
				// on save, so the re-render shows precisely what a successful
				// save would persist (empty -> absint 0 -> clamped to the field
				// minimum). Non-scalar posted values (crafted multi-value
				// fields) fall back to the default and never reach absint().
				$numeric_bounds = array(
					'slides'        => array( 1, 12 ),
					'slides_tablet' => array( 1, 12 ),
					'slides_mobile' => array( 1, 12 ),
					'gap'           => array( 8, 64 ),
					'count'         => array( 0, PHP_INT_MAX ),
				);

				foreach ( $numeric_bounds as $numeric_key => $range ) {
					$current[ $numeric_key ] = is_scalar( $current[ $numeric_key ] )
						? $this->bound( $current, $numeric_key, $range[0], $range[1], $defaults[ $numeric_key ] )
						: $defaults[ $numeric_key ];
				}

				$current['categories'] = isset( $posted['categories'] )
					? array_map( 'absint', (array) $posted['categories'] )
					: array();

				// Same coercion for the products picker (AS-12): a rejected
				// create re-renders the product field with the posted values
				// exactly as sanitize_instance() would persist them. An absent
				// key (e.g. the picker never rendered for type=category) stays
				// empty.
				$current['products'] = isset( $posted['products'] )
					? array_map( 'absint', (array) $posted['products'] )
					: array();

				// New-key re-fill (D3, AS-18): route the whole re-rendered
				// config through the shared normalize() — the same coerce
				// point a successful save applies — so slides_laptop, timeout
				// and speed display at exactly the clamped values a save would
				// persist and the new booleans round-trip through parse_bool.
				// Idempotent on the bounded keys above (absint of an
				// already-clamped int is a no-op); the legacy ranges stay
				// literal here only because normalize() does not clamp
				// slides / slides_tablet / slides_mobile (CM-13).
				$current = $this->settings->normalize( $current );
			}
		}
		$prefix = ( 'edit' === $mode ) ? 'cwc_carousel_registry[' . $slug . ']' : 'cwc_carousel_registry[__new__]';

		$action_url = ( 'edit' === $mode )
			? admin_url( 'options.php' )
			: add_query_arg(
				array(
					'page'       => $this->page_slug,
					'cwc_action' => 'create',
				),
				admin_url( 'admin.php' )
			);

		$heading = ( 'edit' === $mode )
			? sprintf(
				/* translators: %s: instance slug. */
				__( 'Edit carousel: %s', 'cwc-carousel' ),
				$slug
			)
			: __( 'New carousel', 'cwc-carousel' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $heading ); ?></h1>
			<?php settings_errors(); ?>
			<p><a href="<?php echo esc_url( add_query_arg( 'page', $this->page_slug, admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'Back to carousels', 'cwc-carousel' ); ?></a></p>

			<?php if ( 'edit' === $mode ) : ?>
				<?php
				// Editor header shortcode field (AS-17): a read-only rendering of
				// the exact shortcode to paste, with a copy button. The button's
				// two labels travel as data-* attributes so no new localized
				// strings are needed in class-assets.php (D8); admin.js swaps
				// them after a successful copy.
				$shortcode_id = 'cwc-shortcode-' . $slug;
				?>
				<div class="cwc-shortcode-field">
					<label for="<?php echo esc_attr( $shortcode_id ); ?>"><?php esc_html_e( 'Shortcode', 'cwc-carousel' ); ?></label>
					<div class="cwc-shortcode-row">
						<input type="text" id="<?php echo esc_attr( $shortcode_id ); ?>" class="cwc-shortcode-input" value="<?php echo esc_attr( sprintf( '[cwc_carousel name="%s"]', $slug ) ); ?>" readonly />
						<button type="button" class="button cwc-shortcode-copy" data-copy-target="<?php echo esc_attr( $shortcode_id ); ?>" data-copy-label="<?php echo esc_attr__( 'Copy', 'cwc-carousel' ); ?>" data-copied-label="<?php echo esc_attr__( 'Copied!', 'cwc-carousel' ); ?>"><?php esc_html_e( 'Copy', 'cwc-carousel' ); ?></button>
					</div>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<?php
				if ( 'edit' === $mode ) {
					settings_fields( $this->option_group );
				} else {
					echo '<input type="hidden" name="cwc_registry_action" value="create" />';
					wp_nonce_field( $this->create_nonce_action, $this->registry_nonce_field );

					// Re-fill the name with the rejected submission so the user
					// can correct it; a first load has no POST and stays empty.
					$posted_name = isset( $_POST['cwc_new_slug'] ) ? wp_unslash( $_POST['cwc_new_slug'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only create re-render after a rejected submission; escaped on output with esc_attr().

					echo '<p><label for="cwc_new_slug">' . esc_html__( 'Name', 'cwc-carousel' ) . ' </label>'
						. '<input type="text" id="cwc_new_slug" name="cwc_new_slug" maxlength="40" value="' . esc_attr( $posted_name ) . '" /></p>';
					echo '<p class="description">' . esc_html__( 'Lowercase letters, numbers and hyphens. This is the value of the name="…" attribute in [cwc_carousel].', 'cwc-carousel' ) . '</p>';
				}

				// Four nav-tab panels over the ONE form and ONE submit (AS-16):
				// every panel's fields stay in the DOM regardless of the active
				// tab, so the Settings API nonce and the single submit never
				// depend on JS (D5). Panels render WITHOUT the hidden attribute
				// so a JS-free browser sees all four stacked; admin.js hides the
				// inactive ones and wires the roving-tabindex tab behavior.
				$tabs = array(
					'content'    => __( 'Content', 'cwc-carousel' ),
					'behavior'   => __( 'Behavior', 'cwc-carousel' ),
					'navigation' => __( 'Navigation', 'cwc-carousel' ),
					'style'      => __( 'Style', 'cwc-carousel' ),
				);
				?>
				<div class="nav-tab-wrapper cwc-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Carousel settings', 'cwc-carousel' ); ?>">
					<?php
					$first_tab = true;
					foreach ( $tabs as $tab_key => $tab_label ) :
						?>
						<button type="button" role="tab" id="cwc-tab-<?php echo esc_attr( $tab_key ); ?>" class="nav-tab<?php echo $first_tab ? ' nav-tab-active' : ''; ?>" aria-controls="cwc-panel-<?php echo esc_attr( $tab_key ); ?>" aria-selected="<?php echo $first_tab ? 'true' : 'false'; ?>" data-cwc-tab="<?php echo esc_attr( $tab_key ); ?>" tabindex="<?php echo $first_tab ? '0' : '-1'; ?>"><?php echo esc_html( $tab_label ); ?></button>
						<?php
						$first_tab = false;
					endforeach;
					?>
				</div>

				<div class="cwc-panel" id="cwc-panel-content" role="tabpanel" aria-labelledby="cwc-tab-content" data-cwc-panel="content">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Title', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_title_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Carousel type', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_type_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Categories', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_categories_field( $prefix, $current, 'edit' === $mode ); ?></td>
						</tr>
						<!-- Type-conditional rows (AS-17 PR 6 amendment): both rows
							always render and carry data-cwc-type-row; the row that
							does not match the SAVED type starts hidden, and
							admin.js initTypeRows() toggles `hidden` as the type
							select changes (D8 split — no wp.media dependency).
							Hidden rows still POST their fields; the sanitizer
							accepts both keys for either type, so switching the
							type keeps the other side's values intact. -->
						<tr data-cwc-type-row="product"<?php echo 'product' !== $current['type'] ? ' hidden' : ''; ?>>
							<th scope="row"><?php esc_html_e( 'Products', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_products_field( $prefix, $current ); ?></td>
						</tr>
						<tr data-cwc-type-row="category"<?php echo 'category' !== $current['type'] ? ' hidden' : ''; ?>>
							<th scope="row"><?php esc_html_e( 'Cover mode', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_cover_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Subcategories', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_subcategories_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Buy button', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_buy_field( $prefix, $current ); ?></td>
						</tr>
					</table>
				</div>
				<div class="cwc-panel" id="cwc-panel-behavior" role="tabpanel" aria-labelledby="cwc-tab-behavior" data-cwc-panel="behavior">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Slides', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_device_slides_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Gap (px)', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_gap_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Maximum items', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_count_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Autoplay', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_toggle_field( $prefix, $current, 'autoplay', __( 'Automatically advance to the next slide', 'cwc-carousel' ), __( 'Advances every timeout interval (1000–60000 ms).', 'cwc-carousel' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Pause on hover', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_toggle_field( $prefix, $current, 'stop_on_hover', __( 'Stop autoplay while the pointer is over the carousel', 'cwc-carousel' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Autoplay timeout', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_number_field( $prefix, $current, 'timeout', 1000, 60000, __( 'Milliseconds each slide stays before autoplay advances (1000–60000).', 'cwc-carousel' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Transition speed', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_number_field( $prefix, $current, 'speed', 100, 5000, __( 'Milliseconds the slide transition takes (100–5000).', 'cwc-carousel' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Loop', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_toggle_field( $prefix, $current, 'loop', __( 'Restart from the first slide after the last one', 'cwc-carousel' ) ); ?></td>
						</tr>
					</table>
				</div>
				<div class="cwc-panel" id="cwc-panel-navigation" role="tabpanel" aria-labelledby="cwc-tab-navigation" data-cwc-panel="navigation">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Controls', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_controls_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Navigation position', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_nav_position_field( $prefix, $current ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Navigation colors', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_color_group_field( $prefix, $current ); ?></td>
						</tr>
					</table>
				</div>
				<div class="cwc-panel" id="cwc-panel-style" role="tabpanel" aria-labelledby="cwc-tab-style" data-cwc-panel="style">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Title alignment', 'cwc-carousel' ); ?></th>
							<td><?php $this->render_title_align_field( $prefix, $current ); ?></td>
						</tr>
					</table>
				</div>
				<?php
				submit_button();

				// Per-category image/title overrides ride the same form with a
				// dedicated nonce + capability (AS-3): the chip controls post
				// `cwc_cat_images[term_id]` / `cwc_cat_titles[term_id]`, which
				// save_category_images() persists on admin_init. Edit only —
				// create has no persisted categories yet, and a create-mode
				// nonce would wp_die on save (BC).
				if ( 'edit' === $mode ) {
					wp_nonce_field( $this->image_nonce_action, $this->image_nonce_field );
				}
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the delete confirmation for one instance (AS-7).
	 *
	 * Warns that pages referencing the removed slug fall back to the reserved
	 * `default` instance (never fatal, CM-8). The reserved `default` is never
	 * offered for deletion (AS-7, AS-8).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_delete_confirm() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing; the delete form carries wp_nonce_field.
		$slug     = isset( $_GET['slug'] ) ? $this->settings->slugify( sanitize_text_field( wp_unslash( $_GET['slug'] ) ) ) : '';
		$registry = $this->settings->registry();

		if ( '' === $slug || 'default' === $slug || ! isset( $registry[ $slug ] ) ) {
			add_settings_error( $this->option_group, 'cwc_unknown_slug', __( 'The requested carousel was not found.', 'cwc-carousel' ), 'error' );
			$this->render_list();
			return;
		}

		$list_url = add_query_arg( 'page', $this->page_slug, admin_url( 'admin.php' ) );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Delete carousel', 'cwc-carousel' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: instance slug. */
					esc_html__( 'You are about to delete the "%s" carousel.', 'cwc-carousel' ),
					'<strong>' . esc_html( $slug ) . '</strong>'
				);
				?>
			</p>
			<p><?php esc_html_e( 'Pages and shortcodes that reference this name will fall back to the reserved "default" carousel — they keep rendering and never fail.', 'cwc-carousel' ); ?></p>
			<form method="post" action="<?php echo esc_url( $list_url ); ?>">
				<input type="hidden" name="cwc_registry_action" value="delete" />
				<input type="hidden" name="cwc_delete_slug" value="<?php echo esc_attr( $slug ); ?>" />
				<?php wp_nonce_field( $this->delete_nonce_action, $this->registry_nonce_field ); ?>
				<p>
					<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'cwc-carousel' ); ?></button>
					<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'cwc-carousel' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the type select field.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_type_field( string $prefix, array $current ) {
		$selected = ( 'category' === $current['type'] ) ? 'category' : 'product';

		echo '<select name="' . esc_attr( $prefix ) . '[type]" data-cwc-type-select>';
		echo '<option value="product"' . selected( $selected, 'product', false ) . '>' . esc_html__( 'Products', 'cwc-carousel' ) . '</option>';
		echo '<option value="category"' . selected( $selected, 'category', false ) . '>' . esc_html__( 'Categories', 'cwc-carousel' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Whether the carousel shows products or category cards (product_cat terms).', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the display title text field.
	 *
	 * The title is the heading shown above the carousel on the front end; it
	 * is independent of the instance slug (the shortcode `name`). An empty
	 * title makes the renderer omit the heading entirely (CR-1).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_title_field( string $prefix, array $current ) {
		echo '<input type="text" class="regular-text" name="' . esc_attr( $prefix ) . '[title]" value="' . esc_attr( $current['title'] ) . '" maxlength="100" />';
		echo '<p class="description">' . esc_html__( 'Display title shown above the carousel. Leave empty to hide it.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the product_cat search picker (AS-11).
	 *
	 * A Select2 picker backed by WooCommerce's own
	 * `woocommerce_json_search_categories` endpoint. The select renders with
	 * the `enhanced` marker so wc-enhanced-select.js skips it: WC 11.0's
	 * stock auto-init does not forward `show_empty` to the endpoint (the
	 * previous native field listed empty categories — hide_empty=false) and
	 * binds an alphabetical reorder on multiple selects that would fight the
	 * chip drag order (D1). admin.js initializes selectWoo with WC's own
	 * nonce/format strings plus `show_empty: 1` and `data-return_id="id"`
	 * keeps term ids as values (sanitize_ids, AS-11).
	 *
	 * Stored selections pre-render as `<option selected>` in STORED order —
	 * no AJAX on load. Each option carries the per-term image/title meta
	 * (cwc_cat_image / cwc_cat_title, AS-3) as data attributes so the chip
	 * controls render without extra requests. When `$edit_mode` is true the
	 * picker renders `data-edit="1"` and admin.js appends the inline
	 * upload/overlay-title controls per chip (D5); the save nonce is
	 * emitted separately by render_editor() (AS-3).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix    Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current   Instance config to pre-fill.
	 * @param bool   $edit_mode Whether inline chip edit controls render
	 *                          (edit mode only; D5).
	 * @return void
	 */
	public function render_categories_field( string $prefix, array $current, bool $edit_mode ) {
		$selected = array_map( 'absint', $current['categories'] );

		echo '<div class="cwc-picker"' . ( $edit_mode ? ' data-edit="1"' : '' ) . '>';
		echo '<select name="' . esc_attr( $prefix ) . '[categories][]" multiple="multiple" class="wc-category-search enhanced" data-action="woocommerce_json_search_categories" data-minimum_input_length="1" data-return_id="id" data-placeholder="' . esc_attr__( 'Search categories…', 'cwc-carousel' ) . '">';

		// get_terms() with an empty `include` list returns ALL terms, so the
		// query only runs when something is stored; a bare picker renders as
		// an empty select and admin.js still creates the chip list + CTA.
		if ( ! empty( $selected ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'include'    => $selected,
					'hide_empty' => false,
					'orderby'    => 'include',
				)
			);

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! $term instanceof WP_Term ) {
						continue;
					}

					// Chip thumbnail: the custom upload overrides the WC
					// thumbnail — same priority as CWC_Renderer (AS-3).
					$image_id = (int) get_term_meta( $term->term_id, $this->image_meta_key, true );

					if ( $image_id <= 0 ) {
						$image_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
					}

					$thumb = ( $image_id > 0 ) ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
					$title = sanitize_text_field( (string) get_term_meta( $term->term_id, $this->title_meta_key, true ) );

					echo '<option value="' . esc_attr( $term->term_id ) . '"'
						. selected( in_array( (int) $term->term_id, $selected, true ), true, false )
						. ' data-thumb="' . esc_url( $thumb ) . '"'
						. ' data-image-id="' . esc_attr( (string) $image_id ) . '"'
						. ' data-title="' . esc_attr( $title ) . '">'
						. esc_html( $term->name )
						. '</option>';
				}
			}
		}

		echo '</select>';
		echo '<ul class="cwc-chip-list"></ul>';
		echo '<button type="button" class="button cwc-empty-cta"' . ( empty( $selected ) ? '' : ' hidden' ) . '>' . esc_html__( 'Add categories', 'cwc-carousel' ) . '</button>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Search and select categories; drag the chips to set the carousel order.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the product/variation search picker (AS-12).
	 *
	 * A Select2 picker backed by WooCommerce's own
	 * `woocommerce_json_search_products_and_variations` endpoint, so both
	 * products and variations are selectable. Mirrors the categories picker
	 * (AS-11): the select renders with the `enhanced` marker so
	 * wc-enhanced-select.js skips it, and admin.js initializes selectWoo with
	 * WC's own `search_products_nonce` plus `data-return_id="id"` so product
	 * ids stay as values (sanitize_ids, AS-5). The field only renders for
	 * `type=product` carousels — render_editor() gates the row — so a
	 * `type=category` instance never shows it (AS-12, D2).
	 *
	 * Stored selections pre-render as `<option selected>` in STORED order —
	 * no AJAX on load. Each option carries the product's featured image as
	 * `data-thumb` so the chip thumbnail renders without extra requests.
	 * Variations render too: the query passes the same `type` array the
	 * renderer uses (every registered product type plus `variation`, D4) and
	 * `limit` is lifted to the list length so the pre-render never truncates
	 * a long manual list (bf49167 pattern). The label mirrors what the search
	 * endpoint returns (`get_formatted_name()`, "Parent — Attribute: Value
	 * (SKU)" for variations).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_products_field( string $prefix, array $current ) {
		$selected = array_map( 'absint', $current['products'] );

		echo '<div class="cwc-picker">';
		echo '<select name="' . esc_attr( $prefix ) . '[products][]" multiple="multiple" class="wc-product-search enhanced" data-action="woocommerce_json_search_products_and_variations" data-minimum_input_length="1" data-return_id="id" data-placeholder="' . esc_attr__( 'Search products…', 'cwc-carousel' ) . '">';

		// A non-empty list pre-renders; an empty one leaves the select bare
		// and admin.js still creates the chip list + CTA (same as categories).
		if ( ! empty( $selected ) ) {
			$products = wc_get_products(
				array(
					'include' => $selected,
					'limit'   => count( $selected ),
					'orderby' => 'post__in',
					'status'  => 'publish',
					'type'    => array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) ),
				)
			);

			if ( ! empty( $products ) ) {
				foreach ( $products as $product ) {
					if ( ! $product instanceof WC_Product ) {
						continue;
					}

					$image_id = (int) $product->get_image_id();
					$thumb    = ( $image_id > 0 ) ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

					echo '<option value="' . esc_attr( (string) $product->get_id() ) . '"'
						. selected( in_array( (int) $product->get_id(), $selected, true ), true, false )
						. ' data-thumb="' . esc_url( $thumb ) . '">'
						. esc_html( $product->get_formatted_name() )
						. '</option>';
				}
			}
		}

		echo '</select>';
		echo '<ul class="cwc-chip-list"></ul>';
		echo '<button type="button" class="button cwc-empty-cta"' . ( empty( $selected ) ? '' : ' hidden' ) . '>' . esc_html__( 'Add products', 'cwc-carousel' ) . '</button>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Add products to show exactly these items in this order — the category filter above is ignored. Leave it empty to automatically show the latest products from the selected categories.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the per-device slides picker for the four breakpoints (AS-17).
	 *
	 * Four number inputs binding `slides` / `slides_laptop` / `slides_tablet`
	 * / `slides_mobile`, each with a device icon (core dashicons — no icon
	 * font or build step) and its label. The laptop ramp is the 13-key
	 * extension's field (CM-12); the other three are the legacy desktop /
	 * tablet / mobile ramps. All four clamp 1–12 via normalize() on save
	 * (CM-13) and via the create re-fill bounds (AS-18).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_device_slides_field( string $prefix, array $current ) {
		$devices = array(
			'slides'        => array( 'desktop', __( 'Desktop', 'cwc-carousel' ) ),
			'slides_laptop' => array( 'laptop', __( 'Laptop', 'cwc-carousel' ) ),
			'slides_tablet' => array( 'tablet', __( 'Tablet', 'cwc-carousel' ) ),
			'slides_mobile' => array( 'smartphone', __( 'Mobile', 'cwc-carousel' ) ),
		);

		$output = '';

		foreach ( $devices as $device_key => $device ) {
			$output .= '<label class="cwc-device-field">'
				. '<span class="dashicons dashicons-' . esc_attr( $device[0] ) . '" aria-hidden="true"></span>'
				. '<span class="cwc-device-label">' . esc_html( $device[1] ) . '</span>'
				. $this->render_number( $prefix . '[' . $device_key . ']', $current[ $device_key ], 1, 12 )
				. '</label><br />';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput -- render_number() escapes every attribute; labels escaped above.
		echo $output;
		echo '<p class="description">' . esc_html__( 'Screen sizes: Mobile < 768px, Tablet ≥ 768px, Laptop 992–1023px, Desktop ≥ 1024px.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the gap field (8-64 px) with its helper text (AS-17).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_gap_field( string $prefix, array $current ) {
		$this->render_number_field( $prefix, $current, 'gap', 8, 64, __( 'Space between slides in pixels (8–64).', 'cwc-carousel' ) );
	}

	/**
	 * Renders the count field (0+) with its helper text (AS-17).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_count_field( string $prefix, array $current ) {
		$this->render_number_field( $prefix, $current, 'count', 0, PHP_INT_MAX, __( '0 renders an empty carousel.', 'cwc-carousel' ) );
	}

	/**
	 * Renders a bounded number input with optional helper text (AS-17).
	 *
	 * Every number field carries a helper under the input — the slides ramps
	 * get one group helper, and gap / count / timeout / speed get their own —
	 * so the range a save clamps to (CM-13) is visible before submitting. The
	 * bounds mirror normalize()'s clamps where they exist (timeout 1000–60000,
	 * speed 100–5000) and the legacy bound() ranges elsewhere.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix      Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current     Instance config to pre-fill.
	 * @param string $key         Registry key being edited (e.g. 'timeout').
	 * @param int    $min         Inclusive minimum.
	 * @param int    $max         Inclusive maximum.
	 * @param string $description Optional helper text under the input.
	 * @return void
	 */
	public function render_number_field( string $prefix, array $current, string $key, int $min, int $max, string $description = '' ) {
		echo $this->render_number( $prefix . '[' . $key . ']', (int) $current[ $key ], $min, $max ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_number() returns escaped HTML.

		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Renders the buy toggle and label text fields.
	 *
	 * The boolean half uses the shared toggle renderer (hidden `0` + visible
	 * `1`, `.cwc-toggle`, AS-17); the buy_text input sits below it.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_buy_field( string $prefix, array $current ) {
		$this->render_toggle_field( $prefix, $current, 'buy', __( 'Show the Buy button on product cards', 'cwc-carousel' ) );
		echo '<label>' . esc_html__( 'Buy text', 'cwc-carousel' ) . ' '
			. '<input type="text" name="' . esc_attr( $prefix ) . '[buy_text]" value="' . esc_attr( $current['buy_text'] ) . '" />'
			. '</label>';
	}

	/**
	 * Renders the arrows and pagination toggle switches.
	 *
	 * Two independent boolean defaults ("Show arrows" / "Show pagination"),
	 * each rendered by the shared toggle renderer: a hidden `value="0"`
	 * companion posts `'0'` when unchecked so sanitize_instance() round-trips
	 * `false` losslessly (AS-4, D6), and the visible `1` checkbox doubles as
	 * the switch (AS-17).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_controls_field( string $prefix, array $current ) {
		$this->render_toggle_field( $prefix, $current, 'arrows', __( 'Show arrows', 'cwc-carousel' ) );
		echo '<br />';
		$this->render_toggle_field( $prefix, $current, 'pagination', __( 'Show pagination', 'cwc-carousel' ) );
	}

	/**
	 * Renders the cover-mode toggle switch (category carousels only).
	 *
	 * Only rendered for `type=category` carousels (CCC-1/AS-9): render_editor()
	 * gates the row on `type === category` before calling this renderer, so the
	 * method needs no second guard. The toggle posts a hidden `value="0"`
	 * companion when unchecked so sanitize_instance() round-trips `false`
	 * losslessly (AS-4/D6); the visible switch is the `1` checkbox (AS-17).
	 * Product carousels never see the field — `cover` is ignored for
	 * `type=product` at render time.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_cover_field( string $prefix, array $current ) {
		$this->render_toggle_field( $prefix, $current, 'cover', __( 'Show cover cards', 'cwc-carousel' ), __( 'Render portrait cards with a full-bleed image and a centered overlay title.', 'cwc-carousel' ) );
	}

	/**
	 * Renders the subcategories toggle switch.
	 *
	 * When checked, a `type=category` carousel lists the parent's direct child
	 * terms instead of the explicit `categories` selection (CCC-5). The hidden
	 * `value="0"` companion keeps the round-trip lossless (AS-4/D6); the
	 * visible switch is the `1` checkbox (AS-17).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_subcategories_field( string $prefix, array $current ) {
		$this->render_toggle_field( $prefix, $current, 'subcategories', __( 'Show subcategories', 'cwc-carousel' ), __( 'Lists child terms of the parent category and ignores the selected categories.', 'cwc-carousel' ) );
	}

	/**
	 * Renders a Yes/No toggle switch for one boolean registry key.
	 *
	 * The native checkbox stays the real input (visually hidden but focusable)
	 * so the form posts `0`/`1` under the registry key without any JS: a hidden
	 * `value="0"` companion posts `'0'` when unchecked (a native checkbox omits
	 * its key entirely), and the visible `1` checkbox posts `'1'` when checked.
	 * sanitize_instance()/normalize() reuse parse_bool() on save, so the stored
	 * boolean round-trips losslessly (AS-17). The `.cwc-toggle-switch` track is
	 * pure decoration (green = on, red = off); admin.css draws it from the
	 * `:checked` state, so no script is needed for the visual either. Used by
	 * the re-skinned legacy booleans (buy / arrows / pagination / cover /
	 * subcategories) and the three new autoplay-family keys (AS-17).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix      Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current     Instance config to pre-fill.
	 * @param string $key         Registry key being toggled (e.g. 'autoplay').
	 * @param string $label       Visible label next to the switch.
	 * @param string $description Optional helper text under the switch.
	 * @return void
	 */
	public function render_toggle_field( string $prefix, array $current, string $key, string $label, string $description = '' ) {
		$name = $prefix . '[' . $key . ']';

		echo '<label class="cwc-toggle">'
			. '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />'
			. '<input type="checkbox" class="cwc-toggle-input" name="' . esc_attr( $name ) . '" value="1"'
			. checked( ! empty( $current[ $key ] ), true, false ) . ' />'
			. '<span class="cwc-toggle-switch" aria-hidden="true"></span>'
			. '<span class="cwc-toggle-label">' . esc_html( $label ) . '</span>'
			. '</label>';

		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Renders the title-alignment select (center | left | right).
	 *
	 * The options are built from the shared CWC_Settings::title_alignments()
	 * enum (D2), so the select can never drift from the settings model's
	 * whitelist: any value outside the enum sanitizes back to `left` on save,
	 * and the re-render selects `left` too. Applies to the carousel heading
	 * for every type (CR-8).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_title_align_field( string $prefix, array $current ) {
		$selected = $this->settings->sanitize_title_align( $current['title_align'] );

		$labels = array(
			'left'   => __( 'Left', 'cwc-carousel' ),
			'center' => __( 'Center', 'cwc-carousel' ),
			'right'  => __( 'Right', 'cwc-carousel' ),
		);

		echo '<select name="' . esc_attr( $prefix ) . '[title_align]">';

		foreach ( $this->settings->title_alignments() as $align ) {
			echo '<option value="' . esc_attr( $align ) . '"' . selected( $selected, $align, false ) . '>' . esc_html( $labels[ $align ] ) . '</option>';
		}

		echo '</select>';
	}

	/**
	 * Renders the navigation-position select (AS-17, PR 6 amendment).
	 *
	 * The options are built from the shared CWC_Settings::nav_positions()
	 * enum (D2), so the select can never drift from the settings model's
	 * whitelist: any value outside the six positions sanitizes back to
	 * `bottom-right` on save (CM-13), and the re-render selects it too
	 * (title_alignments() pattern). The two side modes added in PR 6 label
	 * themselves "Sides (inside)" / "Sides (outside)" (CR-11 amendment).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_nav_position_field( string $prefix, array $current ) {
		$selected = $this->settings->sanitize_nav_position( $current['nav_position'] );

		$labels = array(
			'bottom-right'  => __( 'Bottom right', 'cwc-carousel' ),
			'bottom-left'   => __( 'Bottom left', 'cwc-carousel' ),
			'top-right'     => __( 'Top right', 'cwc-carousel' ),
			'top-left'      => __( 'Top left', 'cwc-carousel' ),
			'sides-inside'  => __( 'Sides (inside)', 'cwc-carousel' ),
			'sides-outside' => __( 'Sides (outside)', 'cwc-carousel' ),
		);

		echo '<select name="' . esc_attr( $prefix ) . '[nav_position]">';

		foreach ( $this->settings->nav_positions() as $position ) {
			echo '<option value="' . esc_attr( $position ) . '"' . selected( $selected, $position, false ) . '>' . esc_html( $labels[ $position ] ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Where the navigation arrows sit inside the carousel.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the six navigation-color rows (AS-17).
	 *
	 * One row per nav_color_* key from the shared CWC_Settings::nav_color_keys()
	 * list (D2): a label, an input[type=color] picker and a hex text input.
	 * The hex text input is the POSTING field (D4) — the color picker carries
	 * NO `name` attribute, because browsers coerce an empty color value to
	 * #000000 on submit and would store a black arrow for every unset key.
	 * An empty hex value posts '' and normalize() keeps it empty (theme
	 * default, CM-13); admin.js keeps the two inputs in sync (D4, D8).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_color_group_field( string $prefix, array $current ) {
		$labels = array(
			'nav_color_arrow'        => __( 'Arrow', 'cwc-carousel' ),
			'nav_color_bg'           => __( 'Background', 'cwc-carousel' ),
			'nav_color_border'       => __( 'Border', 'cwc-carousel' ),
			'nav_color_arrow_hover'  => __( 'Arrow on hover', 'cwc-carousel' ),
			'nav_color_bg_hover'     => __( 'Background on hover', 'cwc-carousel' ),
			'nav_color_border_hover' => __( 'Border on hover', 'cwc-carousel' ),
		);

		foreach ( $this->settings->nav_color_keys() as $color_key ) {
			$hex       = (string) $current[ $color_key ];
			$picker_id = 'cwc-color-' . $color_key;
			$hex_id    = 'cwc-color-hex-' . $color_key;

			echo '<p class="cwc-color-row">'
				. '<label for="' . esc_attr( $hex_id ) . '">' . esc_html( $labels[ $color_key ] ) . '</label> '
				. '<input type="color" class="cwc-color-picker" id="' . esc_attr( $picker_id ) . '" value="' . esc_attr( '' === $hex ? '#000000' : $hex ) . '" data-cwc-hex="' . esc_attr( $hex_id ) . '" /> '
				. '<input type="text" class="cwc-color-hex" id="' . esc_attr( $hex_id ) . '" name="' . esc_attr( $prefix ) . '[' . esc_attr( $color_key ) . ']" value="' . esc_attr( $hex ) . '" maxlength="7" />'
				. '</p>';
		}

		echo '<p class="description">' . esc_html__( 'Leave a color empty to use the theme default.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Persists per-category image overrides and overlay titles submitted with
	 * the settings form.
	 *
	 * Runs on admin_init so it sees the same POST the Settings API processes;
	 * the form passes a dedicated nonce and capability so term writes never
	 * cross into the pure option sanitizer (D7). An empty value clears the
	 * override, the uploads the value comes from the media library. The
	 * `cwc_cat_titles` array (AS-10) rides the same nonce + capability gate as
	 * `cwc_cat_images` (D5): each title is sanitized with sanitize_text_field,
	 * the term is re-validated via get_term(), and an empty title deletes the
	 * meta so the renderer falls back to the term name (CR-9).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function save_category_images() {
		$has_images = isset( $_POST['cwc_cat_images'] ) && is_array( $_POST['cwc_cat_images'] );
		$has_titles = isset( $_POST['cwc_cat_titles'] ) && is_array( $_POST['cwc_cat_titles'] );

		if ( ! $has_images && ! $has_titles ) {
			return;
		}

		// Capability first (R1-W3): fail gracefully for unauthorized users
		// instead of a hard wp-die from check_admin_referer.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		check_admin_referer( $this->image_nonce_action, $this->image_nonce_field );

		if ( $has_images ) {
			$images = array_map( 'absint', wp_unslash( $_POST['cwc_cat_images'] ) );

			foreach ( $images as $term_id => $attachment_id ) {
				if ( $term_id <= 0 ) {
					continue;
				}

				$term = get_term( $term_id, 'product_cat' );

				if ( ! $term instanceof WP_Term ) {
					continue;
				}

				// Only accept an existing image attachment (R1-W2); anything else
				// clears the override so the renderer falls back to the thumbnail.
				if ( $attachment_id > 0 && ! wp_attachment_is_image( $attachment_id ) ) {
					continue;
				}

				if ( $attachment_id > 0 ) {
					update_term_meta( $term_id, $this->image_meta_key, $attachment_id );
				} else {
					delete_term_meta( $term_id, $this->image_meta_key );
				}
			}
		}

		if ( $has_titles ) {
			// Coerce every posted value to text up-front (mirrors the absint
			// map on cwc_cat_images). sanitize_text_field() does NOT return ''
			// for crafted non-scalar values — it fatals with a TypeError under
			// PHP 8 — so the is_scalar guard clears them to '' first, which
			// then deletes the meta like an empty title (AS-10).
			$titles = array_map(
				static fn( $value ) => is_scalar( $value ) ? sanitize_text_field( $value ) : '',
				wp_unslash( $_POST['cwc_cat_titles'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashed here; the closure sanitizes every scalar with sanitize_text_field() and clears non-scalars to ''.
			);

			foreach ( $titles as $term_id => $title ) {
				$term_id = absint( $term_id );

				if ( $term_id <= 0 ) {
					continue;
				}

				$term = get_term( $term_id, 'product_cat' );

				if ( ! $term instanceof WP_Term ) {
					continue;
				}

				if ( '' === $title ) {
					delete_term_meta( $term_id, $this->title_meta_key );
				} else {
					update_term_meta( $term_id, $this->title_meta_key, $title );
				}
			}
		}
	}

	/**
	 * Handles the registry create/delete actions posted to the page.
	 *
	 * Runs on admin_init (D4), mirroring save_category_images(): capability
	 * first, then check_admin_referer, then the write. The Settings API stays
	 * the pure per-slug edit surface; create and delete both redirect back to
	 * the list after a successful write.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function handle_registry_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This read only branches; create/delete each verify their own nonce via check_admin_referer() before writing.
		if ( ! isset( $_POST['cwc_registry_action'] ) ) {
			return;
		}

		// Capability first (R1-W3): fail gracefully for unauthorized users
		// instead of a hard wp-die from check_admin_referer.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The value only selects the nonce-verified handler (create_instance/delete_instance) that runs below.
		$action = sanitize_key( (string) wp_unslash( $_POST['cwc_registry_action'] ?? '' ) );

		if ( 'create' === $action ) {
			$this->create_instance();
		} elseif ( 'delete' === $action ) {
			$this->delete_instance();
		}
	}

	/**
	 * Creates a new registry instance from the posted slug + `__new__` fields.
	 *
	 * Slugifies the posted name (AS-8) and rejects blank, reserved (`default`)
	 * and duplicate slugs via add_settings_error — never overwriting an
	 * existing instance. The write goes through update_option(), which always
	 * re-enters the edit-only sanitizer via the sanitize_option_* filter, so
	 * the sanitizer is bypassed for this already-validated write (D4).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function create_instance() {
		check_admin_referer( $this->create_nonce_action, $this->registry_nonce_field );

		$list_url = add_query_arg( 'page', $this->page_slug, admin_url( 'admin.php' ) );
		$raw_slug = isset( $_POST['cwc_new_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['cwc_new_slug'] ) ) : '';
		$slug     = $this->settings->slugify( $raw_slug );

		$fields = ( isset( $_POST['cwc_carousel_registry']['__new__'] ) && is_array( $_POST['cwc_carousel_registry']['__new__'] ) )
			? wp_unslash( $_POST['cwc_carousel_registry']['__new__'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashed here; every value is sanitized per-key by sanitize_instance() below.
			: array();

		$registry = get_option( 'cwc_carousel_registry', array() );
		$registry = is_array( $registry ) ? $registry : array();

		if ( '' === $slug ) {
			add_settings_error( $this->option_group, 'cwc_invalid_slug', __( 'The carousel name must contain at least one letter, number, or hyphen.', 'cwc-carousel' ) );
		} elseif ( 'default' === $slug ) {
			add_settings_error( $this->option_group, 'cwc_reserved_slug', __( '"default" is a reserved name and cannot be used.', 'cwc-carousel' ) );
		} elseif ( isset( $registry[ $slug ] ) ) {
			add_settings_error( $this->option_group, 'cwc_duplicate_slug', __( 'A carousel with that name already exists.', 'cwc-carousel' ) );
		} else {
			$registry[ $slug ] = $this->settings->normalize( $this->sanitize_instance( $fields ) );

			// update_option() always runs sanitize_option(), which re-enters the
			// edit-only sanitize_registry() via the sanitize_option_* filter and
			// would strip this NEW slug (create already validated it via nonce +
			// capability + slug checks + normalize), so bypass the sanitizer for
			// this one write and restore it afterwards.
			remove_filter( 'sanitize_option_cwc_carousel_registry', array( $this, 'sanitize_registry' ) );
			update_option( 'cwc_carousel_registry', $registry, false );
			add_filter( 'sanitize_option_cwc_carousel_registry', array( $this, 'sanitize_registry' ) );

			wp_safe_redirect( $list_url );
			exit;
		}

		// Rejections fall through WITHOUT a redirect: the create form posts to
		// ?page=cwc-carousel&cwc_action=create, so render_page() re-renders the
		// editor on this same request and settings_errors() shows the rejection
		// added above (AS-8 clear admin error).
	}

	/**
	 * Deletes a registry instance after its confirmation form submits.
	 *
	 * Refuses the reserved `default` and unknown slugs (AS-7, AS-8), removes
	 * the slug from the registry, then redirects to the list. Pages referencing
	 * the removed name resolve the `default` instance afterwards (CM-8).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function delete_instance() {
		check_admin_referer( $this->delete_nonce_action, $this->registry_nonce_field );

		$list_url = add_query_arg( 'page', $this->page_slug, admin_url( 'admin.php' ) );
		$slug     = isset( $_POST['cwc_delete_slug'] ) ? $this->settings->slugify( sanitize_text_field( wp_unslash( $_POST['cwc_delete_slug'] ) ) ) : '';

		$registry = get_option( 'cwc_carousel_registry', array() );
		$registry = is_array( $registry ) ? $registry : array();

		if ( '' === $slug || 'default' === $slug || ! isset( $registry[ $slug ] ) ) {
			wp_safe_redirect( $list_url );
			exit;
		}

		unset( $registry[ $slug ] );

		// update_option() always runs sanitize_option(), which re-enters the
		// edit-only sanitize_registry() via the sanitize_option_* filter and
		// would resurrect this removed slug (delete already validated it via
		// nonce + capability + slug checks), so bypass the sanitizer for this
		// one write and restore it afterwards.
		remove_filter( 'sanitize_option_cwc_carousel_registry', array( $this, 'sanitize_registry' ) );
		update_option( 'cwc_carousel_registry', $registry, false );
		add_filter( 'sanitize_option_cwc_carousel_registry', array( $this, 'sanitize_registry' ) );

		wp_safe_redirect( $list_url );
		exit;
	}

	/**
	 * Sanitizes a submitted registry update, replacing only the posted slugs.
	 *
	 * Runs per slug (AS-5) and is strictly EDIT-ONLY (D4): a posted key whose
	 * slug is not already present in the current registry is rejected — the
	 * sanitizer must never create a new registry key (creation only happens
	 * through the AS-8-guarded create flow in handle_registry_actions()).
	 * Each posted slug is slugified into its storage key and sanitized via
	 * sanitize_instance(); every other registry key is left untouched.
	 * Non-edited contract keys (title/category/mix) are preserved from the
	 * stored value, and the merged result is normalized to the full resolved
	 * contract via CWC_Settings::normalize() (D5). The create placeholder
	 * `__new__` is never written here.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $input Raw submitted registry array.
	 * @return array Sanitized registry map.
	 */
	public function sanitize_registry( $input ) {
		$input    = ( is_array( $input ) ) ? $input : array();
		$registry = get_option( 'cwc_carousel_registry', array() );
		$registry = is_array( $registry ) ? $registry : array();

		$seen = array();

		foreach ( $input as $raw_slug => $instance ) {
			if ( ! is_array( $instance ) ) {
				continue;
			}

			$slug = $this->settings->slugify( (string) $raw_slug );

			// Blank slugs and posted keys that slugify to the same canonical
			// slug twice (duplicates) are rejected; the first one wins.
			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			// Unknown slugs are rejected instead of being silently merged
			// (never create via the sanitizer), so a crafted options.php POST
			// cannot bypass the create flow's AS-8 guards. Surface the reason
			// instead of dropping it silently (R4-01).
			if ( ! isset( $registry[ $slug ] ) || ! is_array( $registry[ $slug ] ) ) {
				add_settings_error(
					$this->option_group,
					'cwc_edit_rejected',
					sprintf(
						/* translators: %s: instance slug. */
						__( 'Cannot edit "%s": it does not exist in the registry.', 'cwc-carousel' ),
						$slug
					),
					'error'
				);
				continue;
			}

			$seen[ $slug ] = true;

			$existing          = $registry[ $slug ];
			$registry[ $slug ] = $this->settings->normalize(
				wp_parse_args( $this->sanitize_instance( $instance ), $existing )
			);
		}

		return $registry;
	}

	/**
	 * Sanitizes one instance submission into one well-formed config.
	 *
	 * Repurposed from the legacy global-option sanitizer (AS-5): every value is
	 * validated/coerced here, exactly once — slides 1-12, gap 8-64, count int
	 * ≥ 0, categories as positive ids, products as positive ids, buy as a
	 * boolean, buy_text as text, type within {product, category}. Unknown or
	 * invalid keys are normalized to their defaults, never resurrected from
	 * the raw post (CM-2). The cover keys (`cover`, `subcategories`,
	 * `title_align`) and the 13-key extension (`autoplay` through
	 * `slides_laptop`) pass through raw: CWC_Settings::normalize() runs
	 * unconditionally downstream on both call sites (create_instance() and
	 * sanitize_registry()) and owns their coercion — parse_bool for the
	 * booleans, the clamp ranges and the corner/hex whitelists (D1, D2/D5) —
	 * so this method does not repeat them.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $input Raw submitted instance array.
	 * @return array Sanitized instance config.
	 */
	private function sanitize_instance( $input ): array {
		$input = ( is_array( $input ) ) ? $input : array();
		$built = $this->settings->builtins();

		$type       = isset( $input['type'] ) ? $input['type'] : $built['type'];
		$arrows     = isset( $input['arrows'] ) ? $input['arrows'] : $built['arrows'];
		$pagination = isset( $input['pagination'] ) ? $input['pagination'] : $built['pagination'];
		$buy        = isset( $input['buy'] ) ? $input['buy'] : $built['buy'];
		$text       = isset( $input['buy_text'] ) ? $input['buy_text'] : $built['buy_text'];

		return array(
			'title'                  => $this->clean_text( isset( $input['title'] ) ? $input['title'] : '', $built['title'] ),
			'type'                   => ( 'category' === $type ) ? 'category' : 'product',
			'categories'             => $this->settings->sanitize_ids( isset( $input['categories'] ) ? $input['categories'] : array() ),
			'products'               => $this->settings->sanitize_ids( isset( $input['products'] ) ? $input['products'] : array() ),
			'slides'                 => $this->bound( $input, 'slides', 1, 12, $built['slides'] ),
			'slides_tablet'          => $this->bound( $input, 'slides_tablet', 1, 12, $built['slides_tablet'] ),
			'slides_mobile'          => $this->bound( $input, 'slides_mobile', 1, 12, $built['slides_mobile'] ),
			'gap'                    => $this->bound( $input, 'gap', 8, 64, $built['gap'] ),
			'count'                  => $this->bound( $input, 'count', 0, PHP_INT_MAX, $built['count'] ),
			'arrows'                 => $this->settings->parse_bool( $arrows ),
			'pagination'             => $this->settings->parse_bool( $pagination ),
			'buy'                    => $this->settings->parse_bool( $buy ),
			'buy_text'               => $this->clean_text( $text, $built['buy_text'] ),
			'cover'                  => $input['cover'] ?? $built['cover'],
			'subcategories'          => $input['subcategories'] ?? $built['subcategories'],
			'title_align'            => $input['title_align'] ?? $built['title_align'],
			// 13-key extension (D1): raw pass-through in the cover-keys
			// pattern — normalize() downstream owns every coercion (parse_bool,
			// clamps, corner enum, hex whitelist), so the new keys never
			// duplicate the shared ranges (4R WARNING debt stays untouched for
			// the legacy bound() paths).
			'autoplay'               => $input['autoplay'] ?? $built['autoplay'],
			'stop_on_hover'          => $input['stop_on_hover'] ?? $built['stop_on_hover'],
			'timeout'                => $input['timeout'] ?? $built['timeout'],
			'speed'                  => $input['speed'] ?? $built['speed'],
			'loop'                   => $input['loop'] ?? $built['loop'],
			'nav_position'           => $input['nav_position'] ?? $built['nav_position'],
			'nav_color_arrow'        => $input['nav_color_arrow'] ?? $built['nav_color_arrow'],
			'nav_color_bg'           => $input['nav_color_bg'] ?? $built['nav_color_bg'],
			'nav_color_border'       => $input['nav_color_border'] ?? $built['nav_color_border'],
			'nav_color_arrow_hover'  => $input['nav_color_arrow_hover'] ?? $built['nav_color_arrow_hover'],
			'nav_color_bg_hover'     => $input['nav_color_bg_hover'] ?? $built['nav_color_bg_hover'],
			'nav_color_border_hover' => $input['nav_color_border_hover'] ?? $built['nav_color_border_hover'],
			'slides_laptop'          => $input['slides_laptop'] ?? $built['slides_laptop'],
		);
	}

	/**
	 * Returns the config for one registry instance.
	 *
	 * Reads the registry via the shared settings model and normalizes the
	 * stored value so every contract key is present for the renderers; falls
	 * back to CWC_Settings::defaults() (legacy option / built-ins) when the
	 * slug is absent — the same fallback resolve() uses (CM-8).
	 *
	 * @since 0.1.0
	 *
	 * @param string $slug Instance slug (may be 'default').
	 * @return array Normalized instance config keyed by resolved config keys.
	 */
	private function instance_current( string $slug ): array {
		$registry = $this->settings->registry();

		if ( isset( $registry[ $slug ] ) && is_array( $registry[ $slug ] ) ) {
			return $this->settings->normalize( $registry[ $slug ] );
		}

		return $this->settings->defaults();
	}

	/**
	 * Coerces a raw numeric field into an integer within the given bounds.
	 *
	 * @since 0.1.0
	 *
	 * @param array  $input    Raw option value.
	 * @param string $key      Field key.
	 * @param int    $min      Inclusive minimum.
	 * @param int    $max      Inclusive maximum.
	 * @param int    $fallback Fallback when the key is absent.
	 * @return int Bounded integer.
	 */
	private function bound( array $input, string $key, int $min, int $max, int $fallback ): int {
		if ( ! isset( $input[ $key ] ) ) {
			return $fallback;
		}

		return min( $max, max( $min, absint( $input[ $key ] ) ) );
	}

	/**
	 * Sanitizes a short text value, falling back to the provided default.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed  $value    Raw text value.
	 * @param string $fallback Fallback text.
	 * @return string Sanitized text.
	 */
	private function clean_text( $value, string $fallback ): string {
		$text = sanitize_text_field( (string) $value );

		return ( '' === $text ) ? $fallback : $text;
	}

	/**
	 * Renders a bounded number input.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name  Input name.
	 * @param int    $value Current value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @return string Escaped input HTML.
	 */
	private function render_number( string $name, int $value, int $min, int $max ): string {
		return sprintf(
			'<input type="number" name="%1$s" value="%2$d" min="%3$d" max="%4$d" />',
			esc_attr( $name ),
			$value,
			$min,
			$max
		);
	}
}
