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
	private $meta_key = 'cwc_cat_image';

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
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Carousel type', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_type_field( $prefix, $current ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Categories', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_categories_field( $prefix, $current ); ?></td>
					</tr>
					<?php if ( 'category' === $current['type'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cover mode', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_cover_field( $prefix, $current ); ?></td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Subcategories', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_subcategories_field( $prefix, $current ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Title alignment', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_title_align_field( $prefix, $current ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Slides', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_slides_field( $prefix, $current ); ?></td>
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
						<th scope="row"><?php esc_html_e( 'Buy button', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_buy_field( $prefix, $current ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Controls', 'cwc-carousel' ); ?></th>
						<td><?php $this->render_controls_field( $prefix, $current ); ?></td>
					</tr>
				</table>
				<?php
				submit_button();

				// Per-category image overrides belong to the instance being
				// edited (its selected categories drive which terms can get an
				// override); create has no persisted categories yet (AS-3).
				if ( 'edit' === $mode ) {
					$this->render_category_images( $current['categories'] );
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

		echo '<select name="' . esc_attr( $prefix ) . '[type]">';
		echo '<option value="product"' . selected( $selected, 'product', false ) . '>' . esc_html__( 'Products', 'cwc-carousel' ) . '</option>';
		echo '<option value="category"' . selected( $selected, 'category', false ) . '>' . esc_html__( 'Categories', 'cwc-carousel' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Whether the carousel shows products or category cards (product_cat terms).', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the product_cat multiselect field.
	 *
	 * The chosen term ids are stored as the `categories` config used as the
	 * mix seed and the category-card set.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_categories_field( string $prefix, array $current ) {
		$selected = array_map( 'absint', $current['categories'] );
		$terms    = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p class="description">' . esc_html__( 'No product categories found.', 'cwc-carousel' ) . '</p>';
			return;
		}

		echo '<select name="' . esc_attr( $prefix ) . '[categories][]" multiple="multiple" size="6" class="cwc-categories-select">';
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			echo '<option value="' . esc_attr( $term->term_id ) . '"'
				. selected( in_array( (int) $term->term_id, $selected, true ), true, false )
				. '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Hold Ctrl (Cmd on Mac) to select several.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the desktop/tablet/mobile slides number fields (1-12).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_slides_field( string $prefix, array $current ) {
		$output = esc_html__( 'Desktop', 'cwc-carousel' ) . ' '
			. $this->render_number( $prefix . '[slides]', $current['slides'], 1, 12 ) . '<br />'
			. esc_html__( 'Tablet', 'cwc-carousel' ) . ' '
			. $this->render_number( $prefix . '[slides_tablet]', $current['slides_tablet'], 1, 12 ) . '<br />'
			. esc_html__( 'Mobile', 'cwc-carousel' ) . ' '
			. $this->render_number( $prefix . '[slides_mobile]', $current['slides_mobile'], 1, 12 );

		// phpcs:ignore WordPress.Security.EscapeOutput -- render_number() escapes every attribute; labels escaped above.
		echo $output;
	}

	/**
	 * Renders the gap field (8-64 px).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_gap_field( string $prefix, array $current ) {
		echo $this->render_number( $prefix . '[gap]', $current['gap'], 8, 64 ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_number() returns escaped HTML.
	}

	/**
	 * Renders the count field (0+).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_count_field( string $prefix, array $current ) {
		echo $this->render_number( $prefix . '[count]', $current['count'], 0, PHP_INT_MAX ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_number() returns escaped HTML.
		echo '<p class="description">' . esc_html__( '0 renders an empty carousel.', 'cwc-carousel' ) . '</p>';
	}

	/**
	 * Renders the buy toggle and label text fields.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_buy_field( string $prefix, array $current ) {
		$output = '<label><input type="hidden" name="' . esc_attr( $prefix ) . '[buy]" value="0" />'
			. '<input type="checkbox" name="' . esc_attr( $prefix ) . '[buy]" value="1"'
			. checked( ! empty( $current['buy'] ), true, false ) . ' /> '
			. esc_html__( 'Show the Buy button on product cards', 'cwc-carousel' ) . '</label><br />'
			. '<label>' . esc_html__( 'Buy text', 'cwc-carousel' ) . ' '
			. '<input type="text" name="' . esc_attr( $prefix ) . '[buy_text]" value="' . esc_attr( $current['buy_text'] ) . '" />'
			. '</label>';

		// phpcs:ignore WordPress.Security.EscapeOutput -- checked() returns escaped HTML (core escaping function).
		echo $output;
	}

	/**
	 * Renders the arrows and pagination toggle checkboxes.
	 *
	 * Two independent boolean defaults ("Show arrows" / "Show pagination"),
	 * each preceded by a hidden `value="0"` companion so an unchecked box posts
	 * `'0'` (a native checkbox omits its key entirely when unchecked) and
	 * sanitize_instance() round-trips `false` losslessly (AS-4, D6).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_controls_field( string $prefix, array $current ) {
		$output = '<label><input type="hidden" name="' . esc_attr( $prefix ) . '[arrows]" value="0" />'
			. '<input type="checkbox" name="' . esc_attr( $prefix ) . '[arrows]" value="1"'
			. checked( ! empty( $current['arrows'] ), true, false ) . ' /> '
			. esc_html__( 'Show arrows', 'cwc-carousel' ) . '</label><br />'
			. '<label><input type="hidden" name="' . esc_attr( $prefix ) . '[pagination]" value="0" />'
			. '<input type="checkbox" name="' . esc_attr( $prefix ) . '[pagination]" value="1"'
			. checked( ! empty( $current['pagination'] ), true, false ) . ' /> '
			. esc_html__( 'Show pagination', 'cwc-carousel' ) . '</label>';

		// phpcs:ignore WordPress.Security.EscapeOutput -- checked() returns escaped HTML (core escaping function).
		echo $output;
	}

	/**
	 * Renders the cover-mode checkbox (category carousels only).
	 *
	 * Only rendered for `type=category` carousels (CCC-1/AS-9): a hidden
	 * `value="0"` companion posts '0' when unchecked so sanitize_instance()
	 * round-trips `false` losslessly (same pattern as the controls/buy fields,
	 * AS-4/D6). Product carousels never see the field — `cover` is ignored for
	 * `type=product` at render time.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_cover_field( string $prefix, array $current ) {
		if ( 'category' !== $current['type'] ) {
			return;
		}

		$output = '<label><input type="hidden" name="' . esc_attr( $prefix ) . '[cover]" value="0" />'
			. '<input type="checkbox" name="' . esc_attr( $prefix ) . '[cover]" value="1"'
			. checked( ! empty( $current['cover'] ), true, false ) . ' /> '
			. esc_html__( 'Show cover cards', 'cwc-carousel' ) . '</label>'
			. '<p class="description">' . esc_html__( 'Render portrait cards with a full-bleed image and a centered overlay title.', 'cwc-carousel' ) . '</p>';

		// phpcs:ignore WordPress.Security.EscapeOutput -- checked() returns escaped HTML (core escaping function).
		echo $output;
	}

	/**
	 * Renders the subcategories checkbox.
	 *
	 * When checked, a `type=category` carousel lists the parent's direct child
	 * terms instead of the explicit `categories` selection (CCC-5). A hidden
	 * `value="0"` companion keeps the round-trip lossless (AS-4/D6).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_subcategories_field( string $prefix, array $current ) {
		$output = '<label><input type="hidden" name="' . esc_attr( $prefix ) . '[subcategories]" value="0" />'
			. '<input type="checkbox" name="' . esc_attr( $prefix ) . '[subcategories]" value="1"'
			. checked( ! empty( $current['subcategories'] ), true, false ) . ' /> '
			. esc_html__( 'Show subcategories', 'cwc-carousel' ) . '</label>'
			. '<p class="description">' . esc_html__( 'List direct child categories instead of the selected categories.', 'cwc-carousel' ) . '</p>';

		// phpcs:ignore WordPress.Security.EscapeOutput -- checked() returns escaped HTML (core escaping function).
		echo $output;
	}

	/**
	 * Renders the title-alignment select (center | left | right).
	 *
	 * Mirrors the sanitize_instance()/normalize() whitelist (D2): any value
	 * outside the enum falls back to `left` on save, and the re-render selects
	 * `left` too. Applies to the carousel heading for every type (CR-8).
	 *
	 * @since 0.1.0
	 *
	 * @param string $prefix  Field name prefix (`cwc_carousel_registry[slug]`).
	 * @param array  $current Instance config to pre-fill.
	 * @return void
	 */
	public function render_title_align_field( string $prefix, array $current ) {
		$selected = in_array( (string) $current['title_align'], array( 'center', 'right' ), true )
			? (string) $current['title_align']
			: 'left';

		echo '<select name="' . esc_attr( $prefix ) . '[title_align]">';
		echo '<option value="left"' . selected( $selected, 'left', false ) . '>' . esc_html__( 'Left', 'cwc-carousel' ) . '</option>';
		echo '<option value="center"' . selected( $selected, 'center', false ) . '>' . esc_html__( 'Center', 'cwc-carousel' ) . '</option>';
		echo '<option value="right"' . selected( $selected, 'right', false ) . '>' . esc_html__( 'Right', 'cwc-carousel' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Renders the per-category custom image override group (AS-3).
	 *
	 * A row with a hidden attachment-id input, a preview, an Upload button
	 * renders for each chosen category. The hidden input carries the term id so
	 * admin.js can drive wp.media per term. Each row also carries a text input
	 * for the cover overlay title (`cwc_cat_titles[term_id]`), pre-filled from
	 * the existing `cwc_cat_title` term meta (AS-10); the renderer reads it for
	 * the cover card's centered title (CR-9). Saving is handled separately by
	 * save_category_images() to keep term side-effects out of the sanitizer.
	 *
	 * @since 0.1.0
	 *
	 * @param array|null $categories Optional category ids for the edited
	 *                               instance; when null the legacy global
	 *                               option drives the list (fallback).
	 * @return void
	 */
	public function render_category_images( $categories = null ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$current = $this->current();

		if ( is_array( $categories ) ) {
			$current['categories'] = $categories;
		}

		$term_ids = array_map( 'absint', $current['categories'] );

		if ( empty( $term_ids ) ) {
			echo '<p>' . esc_html__( 'Select categories above to set a custom image per category.', 'cwc-carousel' ) . '</p>';
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $term_ids,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'No selected categories were found.', 'cwc-carousel' ) . '</p>';
			return;
		}

		wp_nonce_field( $this->image_nonce_action, $this->image_nonce_field );

		echo '<h2>' . esc_html__( 'Category images', 'cwc-carousel' ) . '</h2>';
		echo '<p>' . esc_html__( 'Optionally set a custom image that overrides the WooCommerce category thumbnail.', 'cwc-carousel' ) . '</p>';

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$image_id = (int) get_term_meta( $term->term_id, $this->meta_key, true );
			$preview  = ( $image_id > 0 ) ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';
			$title    = sanitize_text_field( (string) get_term_meta( $term->term_id, $this->title_meta_key, true ) );

			printf(
				'<div class="cwc-category-image-row" data-term-id="%1$d">'
				. '<span class="cwc-cat-image-preview">%2$s</span>'
				. '<input type="hidden" name="cwc_cat_images[%1$d]" class="cwc-cat-image-id" value="%3$d" />'
				. '<button type="button" class="button cwc-cat-image-upload">%4$s</button>'
				. '<button type="button" class="button-link-delete cwc-cat-image-remove">%5$s</button>'
				. '<p class="cwc-cat-image-term">%6$s</p>'
				. '<p><label for="cwc-cat-title-%1$d">' . esc_html__( 'Overlay title', 'cwc-carousel' ) . '</label> '
				. '<input type="text" id="cwc-cat-title-%1$d" name="cwc_cat_titles[%1$d]" value="%7$s" class="cwc-cat-title-input" /></p>'
				. '</div>',
				(int) $term->term_id,
				$preview, // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() escapes internally.
				absint( $image_id ),
				esc_html__( 'Choose image', 'cwc-carousel' ),
				esc_html__( 'Remove image', 'cwc-carousel' ),
				esc_html( $term->name ),
				esc_attr( $title )
			);
		}
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
					update_term_meta( $term_id, $this->meta_key, $attachment_id );
				} else {
					delete_term_meta( $term_id, $this->meta_key );
				}
			}
		}

		if ( $has_titles ) {
			// Coerce every posted value to text up-front (mirrors the absint
			// map on cwc_cat_images); sanitize_text_field() returns '' for
			// crafted non-scalar values, which clears the meta like an empty
			// title (AS-10).
			$titles = array_map( 'sanitize_text_field', wp_unslash( $_POST['cwc_cat_titles'] ) );

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
	 * ≥ 0, categories as positive ids, buy as a boolean, buy_text as text, type
	 * within {product, category}. Unknown or invalid keys are normalized to
	 * their defaults, never resurrected from the raw post (CM-2). The
	 * cover-mode keys coerce like the rest of the contract (AS-9): bools via
	 * parse_bool, `title_align` whitelisted to {center, right} else `left` —
	 * the exact same rules CWC_Settings::normalize() applies (D2/D5).
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $input Raw submitted instance array.
	 * @return array Sanitized instance config.
	 */
	private function sanitize_instance( $input ): array {
		$input = ( is_array( $input ) ) ? $input : array();
		$built = $this->settings->builtins();

		$type          = isset( $input['type'] ) ? $input['type'] : $built['type'];
		$arrows        = isset( $input['arrows'] ) ? $input['arrows'] : $built['arrows'];
		$pagination    = isset( $input['pagination'] ) ? $input['pagination'] : $built['pagination'];
		$buy           = isset( $input['buy'] ) ? $input['buy'] : $built['buy'];
		$text          = isset( $input['buy_text'] ) ? $input['buy_text'] : $built['buy_text'];
		$cover         = isset( $input['cover'] ) ? $input['cover'] : $built['cover'];
		$subcategories = isset( $input['subcategories'] ) ? $input['subcategories'] : $built['subcategories'];
		$title_align   = isset( $input['title_align'] ) ? $input['title_align'] : $built['title_align'];

		return array(
			'type'          => ( 'category' === $type ) ? 'category' : 'product',
			'categories'    => $this->settings->sanitize_ids( isset( $input['categories'] ) ? $input['categories'] : array() ),
			'slides'        => $this->bound( $input, 'slides', 1, 12, $built['slides'] ),
			'slides_tablet' => $this->bound( $input, 'slides_tablet', 1, 12, $built['slides_tablet'] ),
			'slides_mobile' => $this->bound( $input, 'slides_mobile', 1, 12, $built['slides_mobile'] ),
			'gap'           => $this->bound( $input, 'gap', 8, 64, $built['gap'] ),
			'count'         => $this->bound( $input, 'count', 0, PHP_INT_MAX, $built['count'] ),
			'arrows'        => $this->settings->parse_bool( $arrows ),
			'pagination'    => $this->settings->parse_bool( $pagination ),
			'buy'           => $this->settings->parse_bool( $buy ),
			'buy_text'      => $this->clean_text( $text, $built['buy_text'] ),
			'cover'         => $this->settings->parse_bool( $cover ),
			'subcategories' => $this->settings->parse_bool( $subcategories ),
			'title_align'   => in_array( (string) $title_align, array( 'center', 'right' ), true )
				? (string) $title_align : 'left',
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
	 * Returns the current stored legacy option merged over built-in defaults.
	 *
	 * Reuses CWC_Settings::defaults() so rollback via delete_option() is exact
	 * (the option reads back to built-ins). Only drives the category-images
	 * fallback now (the instance editor reads the registry via
	 * instance_current()).
	 *
	 * @since 0.1.0
	 *
	 * @return array Current global option keyed by resolved config keys.
	 */
	private function current() {
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
