<?php
/**
 * Discount rule administration screens and actions.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides the private administration interface for discount rules.
 */
class BSP_Discount_Rule_Admin {

	/**
	 * Main page slug.
	 */
	const PAGE_SLUG = 'bsp-discount-rules';

	/**
	 * Create page slug.
	 */
	const CREATE_PAGE_SLUG = 'bsp-discount-rule-create';

	/**
	 * Edit page slug.
	 */
	const EDIT_PAGE_SLUG = 'bsp-discount-rule-edit';

	/**
	 * Validator.
	 *
	 * @var BSP_Discount_Rule_Validator
	 */
	private $validator;

	/**
	 * Term helper.
	 *
	 * @var BSP_Discount_Rule_Terms
	 */
	private $terms;

	/**
	 * Initializes dependencies.
	 */
	public function __construct() {
		$this->validator = new BSP_Discount_Rule_Validator();
		$this->terms     = new BSP_Discount_Rule_Terms();
	}

	/**
	 * Registers administration hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_bsp_create_rule', array( $this, 'handle_create' ) );
		add_action( 'admin_post_bsp_update_rule', array( $this, 'handle_update' ) );
		add_action( 'admin_post_bsp_activate_rule', array( $this, 'handle_activate' ) );
		add_action( 'admin_post_bsp_deactivate_rule', array( $this, 'handle_deactivate' ) );
		add_action( 'admin_post_bsp_trash_rule', array( $this, 'handle_trash' ) );
		add_action( 'admin_post_bsp_restore_rule', array( $this, 'handle_restore' ) );
		add_action( 'admin_post_bsp_delete_rule', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_bsp_empty_trash', array( $this, 'handle_empty_trash' ) );
	}

	/**
	 * Registers administration pages under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Bulk Sale Pricing', 'bulk-sale-pricing' ),
			__( 'Bulk Sale Pricing', 'bulk-sale-pricing' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_list_page' )
		);

		add_submenu_page( null, __( 'Add Discount Rule', 'bulk-sale-pricing' ), __( 'Add Discount Rule', 'bulk-sale-pricing' ), 'manage_woocommerce', self::CREATE_PAGE_SLUG, array( $this, 'render_create_page' ) );
		add_submenu_page( null, __( 'Edit Discount Rule', 'bulk-sale-pricing' ), __( 'Edit Discount Rule', 'bulk-sale-pricing' ), 'manage_woocommerce', self::EDIT_PAGE_SLUG, array( $this, 'render_edit_page' ) );
	}

	/**
	 * Enqueues assets for plugin screens.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! in_array( $page, array( self::PAGE_SLUG, self::CREATE_PAGE_SLUG, self::EDIT_PAGE_SLUG ), true ) ) {
			return;
		}

		$style_dependencies  = array();
		$script_dependencies = array( 'jquery' );

		if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
			$style_dependencies[] = 'woocommerce_admin_styles';
		}

		if ( ! wp_style_is( 'select2', 'registered' ) && function_exists( 'WC' ) ) {
			$woocommerce = WC();
			if ( $woocommerce ) {
				wp_register_style( 'select2', $woocommerce->plugin_url() . '/assets/css/select2.css', array(), $woocommerce->version );
			}
		}

		if ( wp_style_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2' );
			$style_dependencies[] = 'select2';
		}

		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			$script_dependencies[] = 'wc-enhanced-select';
		}

		wp_enqueue_style( 'bsp-admin', BSP_URL . 'assets/css/admin.css', $style_dependencies, BSP_VERSION );
		wp_enqueue_script( 'bsp-admin', BSP_URL . 'assets/js/admin.js', $script_dependencies, BSP_VERSION, true );
	}

	/**
	 * Renders the rule list.
	 *
	 * @return void
	 */
	public function render_list_page() {
		$this->require_capability();
		$view   = $this->current_view();
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$sorting = $this->current_sorting();

		$args = array(
			'post_type'      => BSP_Discount_Rule_Post_Type::POST_TYPE,
			'posts_per_page' => 20,
			'paged'          => $paged,
			'order'          => $sorting['order'],
			's'              => $search,
		);

		switch ( $sorting['orderby'] ) {
			case 'rule':
				$args['orderby'] = 'title';
				break;
			case 'discount':
				$args['meta_key'] = '_bsp_discount_percentage';
				$args['orderby']  = 'meta_value_num';
				break;
			case 'status':
				$args['meta_key'] = '_bsp_active';
				$args['orderby']  = 'meta_value_num';
				break;
			case 'id':
			default:
				$args['orderby'] = 'ID';
				break;
		}

		if ( 'trash' === $view ) {
			$args['post_status'] = 'trash';
		} else {
			$args['post_status'] = 'publish';
			if ( 'active' === $view || 'inactive' === $view ) {
				$args['meta_query'] = array(
					array(
						'key'   => '_bsp_active',
						'value' => 'active' === $view ? '1' : '0',
					),
				);
			}
		}

		$query = new WP_Query( $args );
		?>
		<div class="wrap bsp-rules-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bulk Sale Pricing', 'bulk-sale-pricing' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $this->page_url( self::CREATE_PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Add Rule', 'bulk-sale-pricing' ); ?></a>
			<hr class="wp-header-end" />
			<?php $this->render_notice(); ?>
			<?php $this->render_views( $view ); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $sorting['orderby'] ); ?>" />
				<input type="hidden" name="order" value="<?php echo esc_attr( $sorting['order'] ); ?>" />
				<p class="search-box">
					<label class="screen-reader-text" for="bsp-rule-search"><?php esc_html_e( 'Search rules', 'bulk-sale-pricing' ); ?></label>
					<input type="search" id="bsp-rule-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
					<?php submit_button( __( 'Search Rules', 'bulk-sale-pricing' ), '', '', false ); ?>
				</p>
			</form>
			<?php if ( 'trash' === $view && $query->found_posts > 0 ) : ?>
				<p><a class="button" href="<?php echo esc_url( $this->page_url( self::PAGE_SLUG, array( 'view' => 'trash', 'confirm' => 'empty-trash' ) ) ); ?>"><?php esc_html_e( 'Empty Trash', 'bulk-sale-pricing' ); ?></a></p>
			<?php endif; ?>
			<?php if ( isset( $_GET['confirm'] ) && 'empty-trash' === sanitize_key( wp_unslash( $_GET['confirm'] ) ) && 'trash' === $view ) : ?>
				<?php $this->render_empty_trash_confirmation(); ?>
			<?php else : ?>
				<?php $this->render_table( $query, $sorting, $view, $search ); ?>
				<?php $this->render_pagination( $query, $view, $search, $paged, $sorting ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the create screen.
	 *
	 * @return void
	 */
	public function render_create_page() {
		$this->require_capability();
		?>
		<div class="wrap bsp-rule-form-wrap">
			<h1><?php esc_html_e( 'Add Discount Rule', 'bulk-sale-pricing' ); ?></h1>
			<?php $this->render_notice(); ?>
			<form class="bsp-rule-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bsp_create_rule" />
				<?php wp_nonce_field( 'bsp_create_rule' ); ?>
				<?php $this->render_rule_fields(); ?>
				<p class="submit">
					<?php submit_button( __( 'Create Rule', 'bulk-sale-pricing' ), 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( $this->page_url( self::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Back to Rules', 'bulk-sale-pricing' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the edit screen or a permanent-delete confirmation.
	 *
	 * @return void
	 */
	public function render_edit_page() {
		$this->require_capability();
		$rule = $this->rule_from_query();
		if ( ! $rule ) {
			wp_die( esc_html__( 'The requested discount rule could not be found.', 'bulk-sale-pricing' ), '', array( 'response' => 404 ) );
		}

		if ( isset( $_GET['confirm'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['confirm'] ) ) ) {
			$this->render_delete_confirmation( $rule );
			return;
		}
		?>
		<div class="wrap bsp-rule-form-wrap">
			<h1><?php echo esc_html( sprintf( __( 'Edit Rule: %s', 'bulk-sale-pricing' ), $rule->post_title ) ); ?></h1>
			<?php $this->render_notice(); ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Rule ID: #%d', 'bulk-sale-pricing' ), $rule->ID ) ); ?></p>
			<form class="bsp-rule-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bsp_update_rule" />
				<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->ID ); ?>" />
				<?php wp_nonce_field( 'bsp_update_rule_' . $rule->ID ); ?>
				<?php $this->render_rule_fields( $rule ); ?>
				<p class="submit">
					<?php submit_button( __( 'Update Rule', 'bulk-sale-pricing' ), 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( $this->page_url( self::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Back to Rules', 'bulk-sale-pricing' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders common rule fields.
	 *
	 * @param WP_Post|null $rule Existing rule, if editing.
	 * @return void
	 */
	private function render_rule_fields( $rule = null ) {
		$is_edit    = $rule instanceof WP_Post;
		$name       = $is_edit ? $rule->post_title : '';
		$percentage = $is_edit ? get_post_meta( $rule->ID, '_bsp_discount_percentage', true ) : '';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bsp-rule-name"><?php esc_html_e( 'Rule name', 'bulk-sale-pricing' ); ?></label></th>
				<td><input class="regular-text" id="bsp-rule-name" name="bsp_rule_name" type="text" value="<?php echo esc_attr( $name ); ?>" required="required" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="bsp-percentage"><?php esc_html_e( 'Discount percentage', 'bulk-sale-pricing' ); ?></label></th>
				<td><input class="small-text" id="bsp-percentage" name="bsp_percentage" type="number" min="1" max="99" step="1" value="<?php echo esc_attr( $percentage ); ?>" required="required" /> <span aria-hidden="true">%</span></td>
			</tr>
			<?php if ( $is_edit ) : ?>
				<?php $this->render_read_only_scope( $rule ); ?>
			<?php else : ?>
				<?php $this->render_term_select( 'product_cat', 'bsp_categories', __( 'Product categories', 'bulk-sale-pricing' ) ); ?>
				<?php $this->render_term_select( 'product_tag', 'bsp_tags', __( 'Product tags', 'bulk-sale-pricing' ) ); ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'bulk-sale-pricing' ); ?></th>
					<td><label><input name="bsp_activate" type="checkbox" value="1" /> <?php esc_html_e( 'Activate rule immediately', 'bulk-sale-pricing' ); ?></label></td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Renders a multiple taxonomy selector.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $field Field name.
	 * @param string $label Field label.
	 * @return void
	 */
	private function render_term_select( $taxonomy, $field, $label ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( 'product_cat' === $taxonomy ) {
			$uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
			if ( $uncategorized && ! is_wp_error( $uncategorized ) ) {
				$args['exclude'] = array( (int) $uncategorized->term_id );
			}
		}

		$terms = get_terms( $args );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select class="wc-enhanced-select" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>[]" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Search and select…', 'bulk-sale-pricing' ); ?>">
					<?php if ( ! is_wp_error( $terms ) ) : ?>
						<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select one or more categories or tags. These selections cannot be changed after the rule is created.', 'bulk-sale-pricing' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders immutable scope on the edit screen.
	 *
	 * @param WP_Post $rule Rule post.
	 * @return void
	 */
	private function render_read_only_scope( $rule ) {
		$categories = $this->terms->display_terms( get_post_meta( $rule->ID, '_bsp_category_terms', true ), 'product_cat' );
		$tags       = $this->terms->display_terms( get_post_meta( $rule->ID, '_bsp_tag_terms', true ), 'product_tag' );
		?>
		<tr><th scope="row"><?php esc_html_e( 'Product categories', 'bulk-sale-pricing' ); ?></th><td><?php $this->render_terms( $categories ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Product tags', 'bulk-sale-pricing' ); ?></th><td><?php $this->render_terms( $tags ); ?></td></tr>
		<?php
	}

	/**
	 * Handles rule creation.
	 *
	 * @return void
	 */
	public function handle_create() {
		$this->require_post_and_capability();
		check_admin_referer( 'bsp_create_rule' );

		$name       = $this->validator->rule_name( $this->post_value( 'bsp_rule_name' ) );
		$percentage = $this->validator->percentage( $this->post_value( 'bsp_percentage' ) );
		$categories = $this->terms->snapshots( $this->post_value( 'bsp_categories', array() ), 'product_cat' );
		$tags       = $this->terms->snapshots( $this->post_value( 'bsp_tags', array() ), 'product_tag' );

		if ( is_wp_error( $name ) || is_wp_error( $percentage ) || is_wp_error( $categories ) || is_wp_error( $tags ) || ( empty( $categories ) && empty( $tags ) ) ) {
			$this->redirect_with_notice( self::CREATE_PAGE_SLUG, 'invalid-input' );
		}

		$rule_id = wp_insert_post(
			array(
				'post_type'   => BSP_Discount_Rule_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $name,
			),
			true
		);

		if ( is_wp_error( $rule_id ) || ! $rule_id ) {
			$this->redirect_with_notice( self::CREATE_PAGE_SLUG, 'save-failed' );
		}

		update_post_meta( $rule_id, '_bsp_discount_percentage', (string) $percentage );
		update_post_meta( $rule_id, '_bsp_category_terms', $categories );
		update_post_meta( $rule_id, '_bsp_tag_terms', $tags );
		update_post_meta( $rule_id, '_bsp_active', '1' === $this->post_value( 'bsp_activate' ) ? '1' : '0' );

		$this->redirect_with_notice( self::PAGE_SLUG, 'created' );
	}

	/**
	 * Handles name and percentage updates.
	 *
	 * @return void
	 */
	public function handle_update() {
		$this->require_post_and_capability();
		$rule = $this->rule_from_post();
		check_admin_referer( 'bsp_update_rule_' . $rule->ID );

		if ( 'publish' !== $rule->post_status ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'invalid-action' );
		}

		$name       = $this->validator->rule_name( $this->post_value( 'bsp_rule_name' ) );
		$percentage = $this->validator->percentage( $this->post_value( 'bsp_percentage' ) );
		if ( is_wp_error( $name ) || is_wp_error( $percentage ) ) {
			$this->redirect_with_notice( self::EDIT_PAGE_SLUG, 'invalid-input', array( 'rule_id' => $rule->ID ) );
		}

		$updated_rule = wp_update_post(
			array(
				'ID'         => $rule->ID,
				'post_title' => $name,
			),
			true
		);
		if ( is_wp_error( $updated_rule ) || ! $updated_rule ) {
			$this->redirect_with_notice( self::EDIT_PAGE_SLUG, 'save-failed', array( 'rule_id' => $rule->ID ) );
		}

		update_post_meta( $rule->ID, '_bsp_discount_percentage', (string) $percentage );
		$this->redirect_with_notice( self::PAGE_SLUG, 'updated' );
	}

	/** Handles activation. @return void */
	public function handle_activate() {
		$rule = $this->rule_for_action( 'activate' );
		if ( 'publish' !== $rule->post_status || '1' === get_post_meta( $rule->ID, '_bsp_active', true ) ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'invalid-action' );
		}
		update_post_meta( $rule->ID, '_bsp_active', '1' );
		$this->redirect_with_notice( self::PAGE_SLUG, 'activated' );
	}

	/** Handles deactivation. @return void */
	public function handle_deactivate() {
		$rule = $this->rule_for_action( 'deactivate' );
		if ( 'publish' !== $rule->post_status || '1' !== get_post_meta( $rule->ID, '_bsp_active', true ) ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'invalid-action' );
		}
		update_post_meta( $rule->ID, '_bsp_active', '0' );
		$this->redirect_with_notice( self::PAGE_SLUG, 'deactivated' );
	}

	/** Handles moving an inactive rule to trash. @return void */
	public function handle_trash() {
		$rule = $this->rule_for_action( 'trash' );
		if ( 'publish' !== $rule->post_status || '1' === get_post_meta( $rule->ID, '_bsp_active', true ) ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'invalid-action' );
		}
		update_post_meta( $rule->ID, '_bsp_active', '0' );
		if ( false === wp_trash_post( $rule->ID ) ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'save-failed' );
		}

		$this->redirect_with_notice( self::PAGE_SLUG, 'trashed' );
	}

	/** Handles restoration as inactive. @return void */
	public function handle_restore() {
		$rule = $this->rule_for_action( 'restore' );
		if ( 'trash' !== $rule->post_status ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'invalid-action' );
		}
		if ( false === wp_untrash_post( $rule->ID ) ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'save-failed' );
		}

		update_post_meta( $rule->ID, '_bsp_active', '0' );
		$this->redirect_with_notice( self::PAGE_SLUG, 'restored' );
	}

	/** Handles permanent deletion. @return void */
	public function handle_delete() {
		$rule = $this->rule_for_action( 'delete' );
		if ( 'trash' !== $rule->post_status || '1' !== $this->post_value( 'bsp_confirm_delete' ) ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'invalid-action' );
		}
		wp_delete_post( $rule->ID, true );
		$this->redirect_with_notice( self::PAGE_SLUG, 'deleted' );
	}

	/** Handles confirmed empty trash. @return void */
	public function handle_empty_trash() {
		$this->require_post_and_capability();
		check_admin_referer( 'bsp_empty_trash' );
		if ( '1' !== $this->post_value( 'bsp_confirm_empty_trash' ) ) {
			$this->redirect_with_notice( self::PAGE_SLUG, 'invalid-action' );
		}

		do {
			$query = new WP_Query(
				array(
					'post_type'      => BSP_Discount_Rule_Post_Type::POST_TYPE,
					'post_status'    => 'trash',
					'posts_per_page' => 100,
					'paged'          => 1,
					'fields'         => 'ids',
				)
			);
			foreach ( $query->posts as $rule_id ) {
				if ( false === wp_delete_post( $rule_id, true ) ) {
					$this->redirect_with_notice( self::PAGE_SLUG, 'trash-empty-failed', array( 'view' => 'trash' ) );
				}
			}
		} while ( ! empty( $query->posts ) );

		$this->redirect_with_notice( self::PAGE_SLUG, 'trash-emptied' );
	}

	/**
	 * Renders list view links.
	 *
	 * @param string $current Current view.
	 * @return void
	 */
	private function render_views( $current ) {
		$views = array(
			'all'      => __( 'All', 'bulk-sale-pricing' ),
			'active'   => __( 'Active', 'bulk-sale-pricing' ),
			'inactive' => __( 'Inactive', 'bulk-sale-pricing' ),
			'trash'    => __( 'Trash', 'bulk-sale-pricing' ),
		);
		?>
		<ul class="subsubsub">
			<?php foreach ( $views as $view => $label ) : ?>
				<li><a href="<?php echo esc_url( $this->page_url( self::PAGE_SLUG, array( 'view' => $view ) ) ); ?>"<?php echo $current === $view ? ' class="current"' : ''; ?>><?php echo esc_html( $label ); ?></a><?php echo 'trash' === $view ? '' : ' | '; ?></li>
			<?php endforeach; ?>
		</ul>
		<br class="clear" />
		<?php
	}

	/**
	 * Renders the custom table.
	 *
	 * @param WP_Query            $query Rule query.
	 * @param array<string,string> $sorting Current sorting.
	 * @param string              $view Current view.
	 * @param string              $search Search term.
	 * @return void
	 */
	private function render_table( $query, $sorting, $view, $search ) {
		?>
		<table class="wp-list-table widefat fixed striped table-view-list bsp-rules-table">
			<thead><tr>
				<?php $this->render_sortable_column_header( __( 'Rule ID', 'bulk-sale-pricing' ), 'id', $sorting, $view, $search, 'bsp-rule-id-column' ); ?>
				<?php $this->render_sortable_column_header( __( 'Rule', 'bulk-sale-pricing' ), 'rule', $sorting, $view, $search ); ?>
				<th scope="col"><?php esc_html_e( 'Categories', 'bulk-sale-pricing' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Tags', 'bulk-sale-pricing' ); ?></th>
				<?php $this->render_sortable_column_header( __( 'Discount', 'bulk-sale-pricing' ), 'discount', $sorting, $view, $search ); ?>
				<?php $this->render_sortable_column_header( __( 'Status', 'bulk-sale-pricing' ), 'status', $sorting, $view, $search ); ?>
				<th scope="col"><?php esc_html_e( 'Actions', 'bulk-sale-pricing' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( $query->have_posts() ) : ?>
				<?php foreach ( $query->posts as $rule ) : ?>
					<?php $this->render_rule_row( $rule ); ?>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No discount rules found.', 'bulk-sale-pricing' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders a sortable table header.
	 *
	 * @param string               $label Column label.
	 * @param string               $column Column key.
	 * @param array<string,string> $sorting Current sorting.
	 * @param string               $view Current view.
	 * @param string               $search Search term.
	 * @param string               $extra_class Additional CSS class.
	 * @return void
	 */
	private function render_sortable_column_header( $label, $column, $sorting, $view, $search, $extra_class = '' ) {
		$is_current = $column === $sorting['orderby'];
		$next_order = $is_current && 'ASC' === $sorting['order'] ? 'DESC' : 'ASC';
		$classes    = trim( 'manage-column sortable ' . $extra_class );

		if ( $is_current ) {
			$classes .= ' sorted ' . strtolower( $sorting['order'] );
		}

		$url = $this->page_url(
			self::PAGE_SLUG,
			array_filter(
				array(
					'view'    => $view,
					's'       => $search,
					'orderby' => $column,
					'order'   => $next_order,
				)
			)
		);
		?>
		<th class="<?php echo esc_attr( $classes ); ?>" scope="col"<?php echo $is_current ? ' aria-sort="' . esc_attr( 'ASC' === $sorting['order'] ? 'ascending' : 'descending' ) . '"' : ''; ?>><a href="<?php echo esc_url( $url ); ?>"><span><?php echo esc_html( $label ); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
		<?php
	}

	/**
	 * Renders one rule row.
	 *
	 * @param WP_Post $rule Rule post.
	 * @return void
	 */
	private function render_rule_row( $rule ) {
		$categories = $this->terms->display_terms( get_post_meta( $rule->ID, '_bsp_category_terms', true ), 'product_cat' );
		$tags       = $this->terms->display_terms( get_post_meta( $rule->ID, '_bsp_tag_terms', true ), 'product_tag' );
		$status     = $this->status( $rule );
		?>
		<tr>
			<td class="bsp-rule-id-column">#<?php echo esc_html( $rule->ID ); ?></td>
			<td><strong><a href="<?php echo esc_url( $this->page_url( self::EDIT_PAGE_SLUG, array( 'rule_id' => $rule->ID ) ) ); ?>"><?php echo esc_html( $rule->post_title ); ?></a></strong></td>
			<td><?php $this->render_terms( $categories ); ?></td>
			<td><?php $this->render_terms( $tags ); ?></td>
			<td><?php echo esc_html( get_post_meta( $rule->ID, '_bsp_discount_percentage', true ) ); ?>%</td>
			<td><?php echo esc_html( $status ); ?></td>
			<td><?php $this->render_actions( $rule ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Renders terms separated by commas.
	 *
	 * @param array<int, array{name:string,deleted:bool}> $terms Resolved terms.
	 * @return void
	 */
	private function render_terms( $terms ) {
		if ( empty( $terms ) ) {
			echo '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$parts = array();
		foreach ( $terms as $term ) {
			$name = esc_html( $term['name'] );
			if ( $term['deleted'] ) {
				$name .= ' <span class="bsp-deleted-term">' . esc_html__( '(deleted)', 'bulk-sale-pricing' ) . '</span>';
			}
			$parts[] = $name;
		}

		echo wp_kses_post( implode( ', ', $parts ) );
	}

	/**
	 * Renders state-appropriate individual action forms.
	 *
	 * @param WP_Post $rule Rule post.
	 * @return void
	 */
	private function render_actions( $rule ) {
		if ( 'trash' === $rule->post_status ) {
			$this->action_form( $rule, 'restore', __( 'Restore', 'bulk-sale-pricing' ) );
			?> <a class="button-link-delete" href="<?php echo esc_url( $this->page_url( self::EDIT_PAGE_SLUG, array( 'rule_id' => $rule->ID, 'confirm' => 'delete' ) ) ); ?>"><?php esc_html_e( 'Delete Permanently', 'bulk-sale-pricing' ); ?></a><?php
			return;
		}

		?>
		<a class="button-link" href="<?php echo esc_url( $this->page_url( self::EDIT_PAGE_SLUG, array( 'rule_id' => $rule->ID ) ) ); ?>"><?php esc_html_e( 'Edit', 'bulk-sale-pricing' ); ?></a>
		<?php if ( '1' === get_post_meta( $rule->ID, '_bsp_active', true ) ) : ?>
			<?php $this->action_form( $rule, 'deactivate', __( 'Deactivate', 'bulk-sale-pricing' ) ); ?>
		<?php else : ?>
			<?php $this->action_form( $rule, 'activate', __( 'Activate', 'bulk-sale-pricing' ) ); ?>
			<?php $this->action_form( $rule, 'trash', __( 'Move to Trash', 'bulk-sale-pricing' ), true ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders an individual POST action form.
	 *
	 * @param WP_Post $rule Rule post.
	 * @param string  $action Action suffix.
	 * @param string  $label Button label.
	 * @param bool    $destructive Whether the action is destructive.
	 * @return void
	 */
	private function action_form( $rule, $action, $label, $destructive = false ) {
		?>
		<form class="bsp-inline-action" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( 'bsp_' . $action . '_rule' ); ?>" />
			<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->ID ); ?>" />
			<?php wp_nonce_field( 'bsp_' . $action . '_rule_' . $rule->ID ); ?>
			<button type="submit" class="button-link<?php echo $destructive ? ' button-link-delete' : ''; ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Renders pagination.
	 *
	 * @param WP_Query            $query Query.
	 * @param string              $view Current view.
	 * @param string              $search Search term.
	 * @param int                 $paged Current page.
	 * @param array<string,string> $sorting Current sorting.
	 * @return void
	 */
	private function render_pagination( $query, $view, $search, $paged, $sorting ) {
		if ( $query->max_num_pages < 2 ) {
			return;
		}

		$base = $this->page_url( self::PAGE_SLUG, array_filter( array( 'view' => $view, 's' => $search, 'orderby' => $sorting['orderby'], 'order' => $sorting['order'] ) ) ) . '%_%';
		echo '<div class="tablenav"><div class="tablenav-pages">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => $base,
					'format'    => '&paged=%#%',
					'current'   => $paged,
					'total'     => $query->max_num_pages,
					'prev_text' => __( '&laquo;', 'bulk-sale-pricing' ),
					'next_text' => __( '&raquo;', 'bulk-sale-pricing' ),
				)
			)
		);
		echo '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Renders permanent-deletion confirmation.
	 *
	 * @param WP_Post $rule Rule post.
	 * @return void
	 */
	private function render_delete_confirmation( $rule ) {
		if ( 'trash' !== $rule->post_status ) {
			wp_die( esc_html__( 'Only trashed rules can be deleted permanently.', 'bulk-sale-pricing' ), '', array( 'response' => 400 ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Delete Discount Rule Permanently', 'bulk-sale-pricing' ); ?></h1>
			<p><?php echo esc_html( sprintf( __( 'Permanently delete “%s”? This action cannot be undone.', 'bulk-sale-pricing' ), $rule->post_title ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bsp_delete_rule" />
				<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->ID ); ?>" />
				<input type="hidden" name="bsp_confirm_delete" value="1" />
				<?php wp_nonce_field( 'bsp_delete_rule_' . $rule->ID ); ?>
				<?php submit_button( __( 'Delete Permanently', 'bulk-sale-pricing' ), 'delete', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( $this->page_url( self::PAGE_SLUG, array( 'view' => 'trash' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'bulk-sale-pricing' ); ?></a>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders empty-trash confirmation.
	 *
	 * @return void
	 */
	private function render_empty_trash_confirmation() {
		?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Permanently delete all discount rules in Trash? This action cannot be undone.', 'bulk-sale-pricing' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bsp_empty_trash" />
				<input type="hidden" name="bsp_confirm_empty_trash" value="1" />
				<?php wp_nonce_field( 'bsp_empty_trash' ); ?>
				<?php submit_button( __( 'Empty Trash', 'bulk-sale-pricing' ), 'delete', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( $this->page_url( self::PAGE_SLUG, array( 'view' => 'trash' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'bulk-sale-pricing' ); ?></a>
			</form>
		</div>
		<?php
	}

	/**
	 * Gets a rule supplied in the query string.
	 *
	 * @return WP_Post|null
	 */
	private function rule_from_query() {
		$rule_id = isset( $_GET['rule_id'] ) ? absint( wp_unslash( $_GET['rule_id'] ) ) : 0;
		return $this->get_rule( $rule_id );
	}

	/**
	 * Gets a rule supplied in a POST action.
	 *
	 * @return WP_Post
	 */
	private function rule_from_post() {
		$rule = $this->get_rule( absint( $this->post_value( 'rule_id' ) ) );
		if ( ! $rule ) {
			wp_die( esc_html__( 'The requested discount rule could not be found.', 'bulk-sale-pricing' ), '', array( 'response' => 404 ) );
		}
		return $rule;
	}

	/**
	 * Gets and verifies a rule for an individual action.
	 *
	 * @param string $action Action suffix.
	 * @return WP_Post
	 */
	private function rule_for_action( $action ) {
		$this->require_post_and_capability();
		$rule = $this->rule_from_post();
		check_admin_referer( 'bsp_' . $action . '_rule_' . $rule->ID );
		return $rule;
	}

	/**
	 * Returns an owned rule.
	 *
	 * @param int $rule_id Post ID.
	 * @return WP_Post|null
	 */
	private function get_rule( $rule_id ) {
		$rule = get_post( $rule_id );
		return $rule instanceof WP_Post && BSP_Discount_Rule_Post_Type::POST_TYPE === $rule->post_type ? $rule : null;
	}

	/**
	 * Gets the requested list view.
	 *
	 * @return string
	 */
	private function current_view() {
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all';
		return in_array( $view, array( 'all', 'active', 'inactive', 'trash' ), true ) ? $view : 'all';
	}

	/**
	 * Gets validated table sorting parameters.
	 *
	 * @return array{orderby:string,order:string}
	 */
	private function current_sorting() {
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$order   = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';

		if ( ! in_array( $orderby, array( 'id', 'rule', 'discount', 'status' ), true ) ) {
			$orderby = 'id';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		return array(
			'orderby' => $orderby,
			'order'   => $order,
		);
	}

	/**
	 * Gets a request value after removing slashes.
	 *
	 * @param string $key Request key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function post_value( $key, $default = null ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
	}

	/**
	 * Gets a human-readable status.
	 *
	 * @param WP_Post $rule Rule post.
	 * @return string
	 */
	private function status( $rule ) {
		if ( 'trash' === $rule->post_status ) {
			return __( 'Trash', 'bulk-sale-pricing' );
		}
		return '1' === get_post_meta( $rule->ID, '_bsp_active', true ) ? __( 'Active', 'bulk-sale-pricing' ) : __( 'Inactive', 'bulk-sale-pricing' );
	}

	/**
	 * Redirects to an admin page with a notice.
	 *
	 * @param string               $page Page slug.
	 * @param string               $notice Notice key.
	 * @param array<string, mixed> $args Extra arguments.
	 * @return never
	 */
	private function redirect_with_notice( $page, $notice, $args = array() ) {
		$args['bsp_notice'] = $notice;
		wp_safe_redirect( $this->page_url( $page, $args ) );
		exit;
	}

	/**
	 * Builds a plugin admin-page URL.
	 *
	 * @param string               $page Page slug.
	 * @param array<string, mixed> $args Extra arguments.
	 * @return string
	 */
	private function page_url( $page, $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
	}

	/**
	 * Displays a whitelisted notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		$notice = isset( $_GET['bsp_notice'] ) ? sanitize_key( wp_unslash( $_GET['bsp_notice'] ) ) : '';
		$notices = array(
			'created'       => array( 'success', __( 'Discount rule created.', 'bulk-sale-pricing' ) ),
			'updated'       => array( 'success', __( 'Discount rule updated.', 'bulk-sale-pricing' ) ),
			'activated'     => array( 'success', __( 'Discount rule activated.', 'bulk-sale-pricing' ) ),
			'deactivated'   => array( 'success', __( 'Discount rule deactivated.', 'bulk-sale-pricing' ) ),
			'trashed'       => array( 'success', __( 'Discount rule moved to Trash.', 'bulk-sale-pricing' ) ),
			'restored'      => array( 'success', __( 'Discount rule restored as inactive. Activate it manually if needed.', 'bulk-sale-pricing' ) ),
			'deleted'       => array( 'success', __( 'Discount rule deleted permanently.', 'bulk-sale-pricing' ) ),
			'trash-emptied' => array( 'success', __( 'Discount rule Trash emptied.', 'bulk-sale-pricing' ) ),
			'invalid-input' => array( 'error', __( 'Review the required fields and try again.', 'bulk-sale-pricing' ) ),
			'invalid-action'=> array( 'error', __( 'That action is not available for this discount rule.', 'bulk-sale-pricing' ) ),
			'save-failed'   => array( 'error', __( 'The discount rule could not be saved. Please try again.', 'bulk-sale-pricing' ) ),
			'trash-empty-failed' => array( 'error', __( 'The Trash could not be emptied. Remaining rules were not deleted.', 'bulk-sale-pricing' ) ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $notice ][0] ), esc_html( $notices[ $notice ][1] ) );
	}

	/** Ensures a user can manage rules. @return void */
	private function require_capability() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage discount rules.', 'bulk-sale-pricing' ), '', array( 'response' => 403 ) );
		}
	}

	/** Ensures an action is a privileged POST request. @return void */
	private function require_post_and_capability() {
		$this->require_capability();
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'bulk-sale-pricing' ), '', array( 'response' => 405 ) );
		}
	}
}
