<?php
/**
 * Capture-then-replay of the featured_item post type and featured_item_category taxonomy.
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registration snapshot and replay.
 */
final class NT_Compat_Registry {

	public const CPT = 'featured_item';
	public const TAX = 'featured_item_category';

	/**
	 * Set once this plugin has registered the types itself in the current request.
	 *
	 * @var bool
	 */
	private static bool $replayed = false;

	/**
	 * Verified facts from the audit used ONLY when no snapshot exists: terms are served from
	 * /bo-suu-tap/{term}/ and are hierarchical in the admin but NOT in the URL. The single-item
	 * permalink is not verified, so the default is the WordPress standard (post type name).
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'post_type' => array(
				'name' => self::CPT,
				'args' => array(
					'labels'             => array(
						'name'          => 'Bộ sưu tập',
						'singular_name' => 'Mục bộ sưu tập',
						'menu_name'     => 'Bộ sưu tập',
					),
					'public'             => true,
					'hierarchical'       => false,
					'publicly_queryable' => true,
					'show_ui'            => true,
					'show_in_menu'       => true,
					'show_in_nav_menus'  => true,
					'show_in_rest'       => true,
					'has_archive'        => false,
					'menu_icon'          => 'dashicons-images-alt2',
					'capability_type'    => 'post',
					'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions', 'custom-fields' ),
					'rewrite'            => array(
						'slug'       => self::CPT,
						'with_front' => true,
					),
					'taxonomies'         => array( self::TAX ),
				),
			),
			'taxonomy'  => array(
				'name'        => self::TAX,
				'object_type' => array( self::CPT ),
				'args'        => array(
					'labels'            => array(
						'name'          => 'Danh mục bộ sưu tập',
						'singular_name' => 'Danh mục bộ sưu tập',
					),
					'public'            => true,
					'hierarchical'      => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_nav_menus' => true,
					'show_in_rest'      => true,
					'rewrite'           => array(
						'slug'         => 'bo-suu-tap',
						'with_front'   => true,
						'hierarchical' => false,
					),
				),
			),
			'extra'     => array(),
		);
	}

	/**
	 * Stored snapshot (empty array when none).
	 *
	 * @return array<string,mixed>
	 */
	public static function snapshot(): array {
		$stored = get_option( NT_COMPAT_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Whether another component (the previous theme) registered the types in this request.
	 */
	public static function registered_by_other(): bool {
		return ! self::$replayed && post_type_exists( self::CPT );
	}

	/**
	 * Whether this plugin registered them in this request.
	 */
	public static function replay_active(): bool {
		return self::$replayed;
	}

	/**
	 * Read the live registration objects into a storable definition.
	 *
	 * @return array<string,mixed>|null Null when the post type or taxonomy is missing.
	 */
	public static function runtime_definition(): ?array {
		$pt  = get_post_type_object( self::CPT );
		$tax = get_taxonomy( self::TAX );
		if ( ! $pt instanceof WP_Post_Type || ! $tax instanceof WP_Taxonomy ) {
			return null;
		}

		$supports = array_keys( (array) get_all_post_type_supports( self::CPT ) );
		$pt_args  = array(
			'labels'              => array_map( 'strval', (array) $pt->labels ),
			'description'         => (string) $pt->description,
			'public'              => (bool) $pt->public,
			'hierarchical'        => (bool) $pt->hierarchical,
			'exclude_from_search' => (bool) $pt->exclude_from_search,
			'publicly_queryable'  => (bool) $pt->publicly_queryable,
			'show_ui'             => (bool) $pt->show_ui,
			'show_in_menu'        => is_string( $pt->show_in_menu ) ? $pt->show_in_menu : (bool) $pt->show_in_menu,
			'show_in_nav_menus'   => (bool) $pt->show_in_nav_menus,
			'show_in_admin_bar'   => (bool) $pt->show_in_admin_bar,
			'menu_position'       => $pt->menu_position,
			'menu_icon'           => is_string( $pt->menu_icon ) ? $pt->menu_icon : '',
			'capability_type'     => $pt->capability_type,
			'map_meta_cap'        => (bool) $pt->map_meta_cap,
			'has_archive'         => is_string( $pt->has_archive ) ? $pt->has_archive : (bool) $pt->has_archive,
			'rewrite'             => is_array( $pt->rewrite ) ? $pt->rewrite : (bool) $pt->rewrite,
			'query_var'           => is_string( $pt->query_var ) ? $pt->query_var : (bool) $pt->query_var,
			'can_export'          => (bool) $pt->can_export,
			'delete_with_user'    => $pt->delete_with_user,
			'show_in_rest'        => (bool) $pt->show_in_rest,
			'rest_base'           => is_string( $pt->rest_base ) ? $pt->rest_base : '',
			'supports'            => $supports,
			'taxonomies'          => array_values( get_object_taxonomies( self::CPT ) ),
		);

		$extra = array();
		foreach ( get_object_taxonomies( self::CPT, 'objects' ) as $name => $obj ) {
			if ( self::TAX === $name || ! $obj instanceof WP_Taxonomy || $obj->_builtin ) {
				continue;
			}
			$extra[ $name ] = array(
				'name'        => $name,
				'object_type' => array_values( (array) $obj->object_type ),
				'args'        => self::taxonomy_args( $obj ),
			);
		}

		return array(
			'post_type' => array(
				'name' => self::CPT,
				'args' => $pt_args,
			),
			'taxonomy'  => array(
				'name'        => self::TAX,
				'object_type' => array_values( (array) $tax->object_type ),
				'args'        => self::taxonomy_args( $tax ),
			),
			'extra'     => $extra,
		);
	}

	/**
	 * Storable arguments of one taxonomy.
	 *
	 * @param WP_Taxonomy $tax Taxonomy object.
	 * @return array<string,mixed>
	 */
	private static function taxonomy_args( WP_Taxonomy $tax ): array {
		return array(
			'labels'             => array_map( 'strval', (array) $tax->labels ),
			'description'        => (string) $tax->description,
			'public'             => (bool) $tax->public,
			'publicly_queryable' => (bool) $tax->publicly_queryable,
			'hierarchical'       => (bool) $tax->hierarchical,
			'show_ui'            => (bool) $tax->show_ui,
			'show_in_menu'       => (bool) $tax->show_in_menu,
			'show_in_nav_menus'  => (bool) $tax->show_in_nav_menus,
			'show_tagcloud'      => (bool) $tax->show_tagcloud,
			'show_in_quick_edit' => (bool) $tax->show_in_quick_edit,
			'show_admin_column'  => (bool) $tax->show_admin_column,
			'rewrite'            => is_array( $tax->rewrite ) ? $tax->rewrite : (bool) $tax->rewrite,
			'query_var'          => is_string( $tax->query_var ) ? $tax->query_var : (bool) $tax->query_var,
			'show_in_rest'       => (bool) $tax->show_in_rest,
			'rest_base'          => is_string( $tax->rest_base ) ? $tax->rest_base : '',
			'sort'               => (bool) $tax->sort,
		);
	}

	/**
	 * Matching rewrite rules currently stored by WordPress (evidence for the migration report).
	 *
	 * @return array<string,string> regex => query
	 */
	public static function stored_rules(): array {
		$rules = get_option( 'rewrite_rules' );
		$out   = array();
		if ( is_array( $rules ) ) {
			foreach ( $rules as $regex => $query ) {
				if ( false !== strpos( (string) $query, self::CPT ) || false !== strpos( (string) $query, self::TAX ) ) {
					$out[ (string) $regex ] = (string) $query;
				}
			}
		}
		return $out;
	}

	/**
	 * Registration order of the two permastructs. When the post type and the taxonomy share a URL
	 * prefix the earlier one wins, so replay must keep the original order.
	 *
	 * @return string[]
	 */
	public static function permastruct_order(): array {
		global $wp_rewrite;
		$keys = ( $wp_rewrite instanceof WP_Rewrite && is_array( $wp_rewrite->extra_permastructs ) ) ? array_keys( $wp_rewrite->extra_permastructs ) : array();
		return array_values( array_intersect( $keys, array( self::CPT, self::TAX ) ) );
	}

	/**
	 * Capture the registration made by another component and store it.
	 *
	 * @param bool $force Overwrite an existing snapshot.
	 * @return array{ok:bool,message:string,source:string}
	 */
	public static function capture( bool $force = false ): array {
		$existing = self::snapshot();
		if ( ! $force && ! empty( $existing['definition'] ) && 'runtime' === ( $existing['source'] ?? '' ) ) {
			return array(
				'ok'      => true,
				'message' => 'Đã có bản ghi từ runtime (' . ( $existing['captured_at'] ?? '' ) . '). Dùng --force để ghi đè.',
				'source'  => 'runtime',
			);
		}
		if ( ! self::registered_by_other() ) {
			if ( empty( $existing['definition'] ) ) {
				update_option(
					NT_COMPAT_OPTION,
					array(
						'version'     => NT_COMPAT_VERSION,
						'captured_at' => gmdate( 'c' ),
						'source'      => 'defaults',
						'definition'  => self::defaults(),
						'rules'       => array(),
					),
					false
				);
				return array(
					'ok'      => false,
					'message' => 'Không có theme/plugin nào đang đăng ký featured_item: đã dùng cấu hình mặc định (CHƯA xác minh với site thật). Chạy lại "capture" khi Flatsome còn hoạt động.',
					'source'  => 'defaults',
				);
			}
			return array(
				'ok'      => false,
				'message' => 'featured_item đang do plugin này đăng ký, không thể ghi lại từ runtime.',
				'source'  => (string) ( $existing['source'] ?? '' ),
			);
		}

		$definition = self::runtime_definition();
		if ( null === $definition ) {
			return array(
				'ok'      => false,
				'message' => 'Có post type featured_item nhưng thiếu taxonomy featured_item_category.',
				'source'  => 'none',
			);
		}
		update_option(
			NT_COMPAT_OPTION,
			array(
				'version'     => NT_COMPAT_VERSION,
				'captured_at' => gmdate( 'c' ),
				'source'      => 'runtime',
				'definition'  => $definition,
				'order'       => self::permastruct_order(),
				'rules'       => self::stored_rules(),
			),
			false
		);
		return array(
			'ok'      => true,
			'message' => 'Đã ghi lại cấu hình đăng ký featured_item từ runtime.',
			'source'  => 'runtime',
		);
	}

	/**
	 * Activation: capture when the previous registration is present, otherwise store defaults.
	 */
	public static function on_activate(): void {
		self::capture( false );
	}

	/**
	 * Register the post type and taxonomy from the snapshot when nobody else has.
	 */
	public static function replay(): void {
		if ( post_type_exists( self::CPT ) || taxonomy_exists( self::TAX ) ) {
			return; // Previous theme (or another plugin) still provides them: stay dormant.
		}
		$snapshot   = self::snapshot();
		$definition = ! empty( $snapshot['definition'] ) && is_array( $snapshot['definition'] ) ? $snapshot['definition'] : self::defaults();
		$definition = (array) apply_filters( 'nguyentu_compat_definition', $definition );

		$tax = $definition['taxonomy'] ?? array();
		$pt  = $definition['post_type'] ?? array();
		if ( empty( $tax['name'] ) || empty( $pt['name'] ) ) {
			return;
		}

		foreach ( (array) ( $definition['extra'] ?? array() ) as $extra ) {
			if ( ! empty( $extra['name'] ) && ! taxonomy_exists( $extra['name'] ) ) {
				register_taxonomy( $extra['name'], (array) ( $extra['object_type'] ?? array( self::CPT ) ), (array) ( $extra['args'] ?? array() ) );
			}
		}
		$order     = ! empty( $snapshot['order'] ) && is_array( $snapshot['order'] ) ? $snapshot['order'] : array( self::TAX, self::CPT );
		$taxonomy  = static function () use ( $tax ): void {
			register_taxonomy( $tax['name'], (array) ( $tax['object_type'] ?? array( self::CPT ) ), (array) ( $tax['args'] ?? array() ) );
		};
		$post_type = static function () use ( $pt ): void {
			register_post_type( $pt['name'], (array) ( $pt['args'] ?? array() ) );
		};
		if ( isset( $order[0] ) && self::CPT === $order[0] ) {
			$post_type();
			$taxonomy();
		} else {
			$taxonomy();
			$post_type();
		}
		self::$replayed = true;
	}
}
