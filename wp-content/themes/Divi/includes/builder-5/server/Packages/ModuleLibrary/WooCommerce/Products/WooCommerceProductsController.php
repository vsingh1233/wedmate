<?php
/**
 * Module Library: WooCommerce Products Module REST Controller class
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\Products;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * WooCommerce Products REST Controller class.
 *
 * @since ??
 */
class WooCommerceProductsController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Products module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML.
	 *                                  If the request is invalid, a `WP_Error` object is returned.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$conditional_tags = $common_required_params['conditional_tags'];
		$current_page     = $common_required_params['current_page'];

		// Extract legacy field parameters with camelCase naming.
		$type               = $request->get_param( 'type' );
		$include_categories = $request->get_param( 'includeCategories' );
		$posts_number       = $request->get_param( 'postsNumber' );
		$orderby            = $request->get_param( 'orderby' );
		$columns_number     = $request->get_param( 'columnsNumber' );
		$show_pagination    = $request->get_param( 'showPagination' );
		$use_current_loop   = $request->get_param( 'useCurrentLoop' );
		$offset_number      = $request->get_param( 'offsetNumber' );

		$args = [];

		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		if ( ! empty( $include_categories ) ) {
			$args['includeCategories'] = $include_categories;
		}

		if ( 0 === $posts_number || ! empty( $posts_number ) ) {
			$args['postsNumber'] = $posts_number;
		}

		if ( ! empty( $orderby ) ) {
			$args['orderby'] = $orderby;
		}

		if ( 0 === $columns_number || ! empty( $columns_number ) ) {
			$args['columnsNumber'] = $columns_number;
		}

		if ( ! empty( $show_pagination ) ) {
			$args['showPagination'] = $show_pagination;
		}

		if ( ! empty( $use_current_loop ) ) {
			$args['useCurrentLoop'] = $use_current_loop;
		}

		if ( 0 === $offset_number || ! empty( $offset_number ) ) {
			$args['offsetNumber'] = $offset_number;
		}

		// Retrieve the shop products using the WooCommerceProductsModule class.
		$shop_html = WooCommerceProductsModule::get_shop_html( $args, $conditional_tags, $current_page );

		$response = [
			'html' => $shop_html,
		];

		return self::response_success( $response );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * This function returns an array that defines the arguments for the index action,
	 * which is used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [
			'type'              => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'recent',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					$allowed_types = [
						'recent',
						'featured',
						'sale',
						'best_selling',
						'top_rated',
						'latest',
						'product_category',
					];
					return in_array( $param, $allowed_types, true );
				},
			],
			'includeCategories' => [
				'type'              => 'array',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return array_map( 'sanitize_text_field', $param ?? [] );
				},
				'validate_callback' => function ( $param ) {
					/**
					 * Validate includeCategories parameter as array.
					 *
					 * Accepts either:
					 * - Empty array: No category filtering (show products from all categories)
					 * - Array of strings: Valid category slugs/IDs without empty elements
					 *
					 * @since ??
					 */
					if ( [] === $param ) {
						return true;
					}

					if ( ! is_array( $param ) ) {
						return false;
					}

					// Ensure no empty strings in the array.
					foreach ( $param as $category ) {
						if ( empty( $category ) || ! is_string( $category ) ) {
							return false;
						}
					}

					return true;
				},
			],
			'postsNumber'       => [
				'type'              => 'integer',
				'required'          => false,
				'default'           => 12,
				'minimum'           => 0,
				'maximum'           => 1000, // Security: Prevent excessive database queries and memory usage.
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					// Security: Validate range to prevent resource exhaustion.
					return is_numeric( $param ) && intval( $param ) >= 0 && intval( $param ) <= 1000;
				},
			],
			'orderby'           => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'default',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					$allowed_orderby = [
						'default',
						'menu_order',
						'popularity',
						'rating',
						'date',
						'price',
						'date-desc',
						'price-desc',
						'latest',
					];
					return in_array( $param, $allowed_orderby, true );
				},
			],
			'columnsNumber'     => [
				'type'              => 'integer',
				'required'          => false,
				'default'           => 0,
				'minimum'           => 0,
				'maximum'           => 6,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && intval( $param ) >= 0 && intval( $param ) <= 6;
				},
			],
			'showPagination'    => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'off',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'useCurrentLoop'    => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'off',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'offsetNumber'      => [
				'type'              => 'integer',
				'required'          => false,
				'default'           => 0,
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && intval( $param ) >= 0;
				},
			],
			'requestType'       => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			],
			'mainLoopType'      => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function ( $value ) {
					return sanitize_key( (string) $value );
				},
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			],
		];
	}

	/**
	 * Provides the permission status for the index action.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return bool Returns `true` if the current user has the permission to use the rest endpoint, otherwise `false`.
	 */
	public static function index_permission( WP_REST_Request $request ): bool {
		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return false;
		}

		$current_page   = $request->get_param( 'currentPage' ) ?? [];
		$request_type   = sanitize_text_field( (string) ( $request->get_param( 'requestType' ) ?? '' ) );
		$main_loop_type = sanitize_key( (string) ( $request->get_param( 'mainLoopType' ) ?? '' ) );

		if ( '' === $main_loop_type && is_array( $current_page ) && isset( $current_page['mainLoopType'] ) ) {
			$main_loop_type = sanitize_key( (string) $current_page['mainLoopType'] );
		}

		if ( '' === $request_type && '' !== $main_loop_type ) {
			if ( '404' === $main_loop_type ) {
				$request_type = '404';
			} elseif ( 'home' === $main_loop_type ) {
				$request_type = 'home';
			} elseif ( 'singular' !== $main_loop_type ) {
				$request_type = 'archive';
			}
		}

		$is_non_singular_request = in_array( $request_type, [ '404', 'archive', 'home' ], true )
			|| ( '' !== $main_loop_type && 'singular' !== $main_loop_type );

		if ( $is_non_singular_request ) {
			return et_pb_is_allowed( 'theme_builder' );
		}

		$page_id_raw = is_array( $current_page ) ? ( $current_page['id'] ?? 0 ) : 0;
		$post_id     = is_numeric( $page_id_raw ) ? absint( $page_id_raw ) : 0;

		if ( 0 < $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		}

		return false;
	}
}
