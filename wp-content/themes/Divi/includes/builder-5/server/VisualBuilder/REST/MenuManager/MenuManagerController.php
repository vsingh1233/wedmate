<?php
/**
 * REST: MenuManagerController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\MenuManager;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\VisualBuilder\AiAgent\AiAgentApprovalTokens;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Menu manager REST Controller class.
 *
 * @since ??
 */
class MenuManagerController extends RESTController {

	/**
	 * Creates a menu.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$menu_name = (string) $request->get_param( 'name' );

		$result = wp_update_nav_menu_object(
			0,
			wp_slash(
				[
					'menu-name' => $menu_name,
				]
			)
		);

		if ( is_wp_error( $result ) ) {
			return self::response_error(
				'menu_create_failed',
				$result->get_error_message(),
				[
					'details' => $result->get_error_data(),
				]
			);
		}

		$menu = wp_get_nav_menu_object( (int) $result );

		if ( ! $menu ) {
			return self::response_error( 'menu_not_found', esc_html__( 'Created menu could not be loaded.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success(
			[
				'menu' => self::_format_menu( $menu ),
			],
			[],
			201
		);
	}

	/**
	 * Deletes a menu.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'id' );

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return self::response_error( 'menu_not_found', esc_html__( 'Menu not found.', 'et_builder_5' ), [], 404 );
		}

		$previous = self::_format_menu( $menu );
		$result   = wp_delete_nav_menu( $menu );

		if ( ! $result || is_wp_error( $result ) ) {
			return self::response_error( 'menu_delete_failed', esc_html__( 'The menu cannot be deleted.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success(
			[
				'deleted'  => true,
				'previous' => $previous,
			]
		);
	}

	/**
	 * Assigns a menu to a location.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function assign_location( WP_REST_Request $request ) {
		$menu_id  = (int) $request->get_param( 'menu_id' );
		$location = self::_resolve_location_slug( (string) $request->get_param( 'location' ) );

		if ( null === $location ) {
			return self::response_error(
				'invalid_menu_location',
				esc_html__( 'Invalid menu location.', 'et_builder_5' ),
				[
					'location' => (string) $request->get_param( 'location' ),
				]
			);
		}

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return self::response_error( 'menu_not_found', esc_html__( 'Menu not found.', 'et_builder_5' ), [], 404 );
		}

		$confirm_overwrite = (bool) $request->get_param( 'confirm_overwrite' );
		$assigned_menu     = get_nav_menu_locations();
		$existing_menu_id  = (int) ( $assigned_menu[ $location ] ?? 0 );

		if ( 0 < $existing_menu_id && $existing_menu_id !== $menu_id && ! $confirm_overwrite ) {
			$existing_menu = wp_get_nav_menu_object( $existing_menu_id );

			return self::response_error(
				'assignment_conflict',
				esc_html__( 'This menu location already has a menu assigned.', 'et_builder_5' ),
				[
					'location'              => $location,
					'existing_menu_id'      => $existing_menu_id,
					'existing_menu_name'    => $existing_menu ? $existing_menu->name : '',
					'requires_confirmation' => true,
				],
				409
			);
		}

		$assigned_menu[ $location ] = $menu_id;

		set_theme_mod( 'nav_menu_locations', $assigned_menu );

		return self::response_success(
			[
				'menu_id'   => $menu_id,
				'location'  => $location,
				'assigned'  => true,
				'locations' => get_nav_menu_locations(),
			]
		);
	}

	/**
	 * Unassigns a menu from a location.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function unassign_location( WP_REST_Request $request ) {
		$location_input = (string) $request->get_param( 'location' );
		$location       = self::_resolve_location_slug( $location_input );

		if ( null === $location ) {
			$location = sanitize_key( $location_input );
		}

		$assigned_menu = get_nav_menu_locations();
		$previous_menu = $assigned_menu[ $location ] ?? 0;

		if ( isset( $assigned_menu[ $location ] ) ) {
			unset( $assigned_menu[ $location ] );
			set_theme_mod( 'nav_menu_locations', $assigned_menu );
		}

		return self::response_success(
			[
				'location'      => $location,
				'previous_menu' => (int) $previous_menu,
				'unassigned'    => true,
				'locations'     => get_nav_menu_locations(),
				'was_assigned'  => 0 < (int) $previous_menu,
			]
		);
	}

	/**
	 * Resolves menu by name or location.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function resolve( WP_REST_Request $request ) {
		$menu_name        = (string) $request->get_param( 'name' );
		$location_input   = (string) $request->get_param( 'location' );
		$location         = self::_resolve_location_slug( $location_input );

		if ( '' !== $location_input ) {
			if ( null === $location ) {
				return self::response_error(
					'invalid_menu_location',
					esc_html__( 'Invalid menu location.', 'et_builder_5' ),
					[
						'location' => $location_input,
					]
				);
			}
			$locations = get_nav_menu_locations();
			$menu_id   = (int) ( $locations[ $location ] ?? 0 );

			if ( 0 >= $menu_id ) {
				return self::response_success(
					[
						'strategy' => 'location',
						'menu'     => null,
					]
				);
			}

			$menu = wp_get_nav_menu_object( $menu_id );

			if ( ! $menu ) {
				return self::response_error( 'menu_not_found', esc_html__( 'Assigned menu could not be loaded.', 'et_builder_5' ), [], 404 );
			}

			return self::response_success(
				[
					'strategy' => 'location',
					'menu'     => self::_format_menu( $menu ),
				]
			);
		}

		$menus = get_terms(
			[
				'taxonomy'   => 'nav_menu',
				'hide_empty' => false,
				'name'       => $menu_name,
			]
		);

		if ( is_wp_error( $menus ) ) {
			return self::response_error(
				'menu_resolve_failed',
				$menus->get_error_message(),
				[
					'details' => $menus->get_error_data(),
				]
			);
		}

		if ( 0 === count( $menus ) ) {
			return self::response_error( 'menu_not_found', esc_html__( 'Menu not found.', 'et_builder_5' ), [], 404 );
		}

		// Fail fast on ambiguous name resolution when multiple menus share the same name.
		if ( count( $menus ) > 1 ) {
			$menu_ids = array_map(
				function ( $menu ) {
					return (int) $menu->term_id;
				},
				$menus
			);

			return self::response_error(
				'menu_name_ambiguous',
				sprintf(
					/* translators: %s: menu name */
					esc_html__( 'Multiple menus found with name "%s". Use location or menu ID to disambiguate.', 'et_builder_5' ),
					$menu_name
				),
				[
					'menu_name' => $menu_name,
					'menu_ids'  => $menu_ids,
					'count'     => count( $menus ),
				],
				400
			);
		}

		$menu = wp_get_nav_menu_object( (int) $menus[0]->term_id );

		if ( ! $menu ) {
			return self::response_error( 'menu_not_found', esc_html__( 'Resolved menu could not be loaded.', 'et_builder_5' ), [], 404 );
		}

		return self::response_success(
			[
				'strategy' => 'name',
				'menu'     => self::_format_menu( $menu ),
			]
		);
	}

	/**
	 * Get the arguments for create action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function create_args(): array {
		return [
			'name' => [
				'required'          => true,
				'type'              => 'string',
				'format'            => 'text-field', // Sanitized using sanitize_text_field.
				'minLength'         => 1, // Prevent empty string.
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static function ( $param ) {
					$menu_name = trim( (string) sanitize_text_field( $param ) );

					if ( '' === $menu_name ) {
						return new WP_Error( 'missing_menu_name', esc_html__( 'Menu name is required.', 'et_builder_5' ) );
					}

					return true;
				},
			],
		];
	}

	/**
	 * Get the arguments for delete action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function delete_args(): array {
		return [
			'id' => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1, // Prevent zero or negative IDs.
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Get the arguments for assign location action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function assign_location_args(): array {
		return [
			'menu_id'  => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1, // Prevent zero or negative IDs.
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'location' => [
				'required'          => true,
				'type'              => 'string',
				'format'            => 'text-field',
				'minLength'         => 1, // Prevent empty string.
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					// Must be a registered menu location (requires DB/theme lookup, cannot use schema alone).
					return self::_validate_location( sanitize_text_field( (string) $param ) );
				},
			],
			'confirm_overwrite' => [
				'required' => false,
				'type'     => 'boolean',
				'default'  => false,
			],
		];
	}

	/**
	 * Get the arguments for unassign location action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function unassign_location_args(): array {
		return [
			'location' => [
				'required'          => true,
				'type'              => 'string',
				'format'            => 'text-field',
				'minLength'         => 1, // Prevent empty string.
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					// Unassign accepts any non-empty slug so stale theme-mod keys can be cleaned up.
					return self::_validate_non_empty_location( sanitize_text_field( (string) $param ) );
				},
			],
		];
	}

	/**
	 * Get the arguments for resolve action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function resolve_args(): array {
		return [
			'name'     => [
				'required'          => false,
				'type'              => 'string',
				'format'            => 'text-field', // Sanitized using sanitize_text_field.
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param, $request ) {
					// Cross-field: at least one of name or location must be provided (cannot express in schema alone).
					$location_input = sanitize_text_field( (string) $request->get_param( 'location' ) );
					$location       = self::_resolve_location_slug( $location_input );
					$name           = trim( (string) sanitize_text_field( $param ) );

					if ( '' === $name && null === $location && '' === trim( $location_input ) ) {
						return new \WP_Error(
							'missing_resolve_input',
							esc_html__( 'Menu name or location is required.', 'et_builder_5' )
						);
					}

					return true;
				},
			],
			'location' => [
				'required'          => false,
				'type'              => 'string',
				'format'            => 'text-field',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					$location_input = sanitize_text_field( (string) $param );

					if ( '' === $location_input ) {
						return true;
					}

					// Must be a registered menu location (requires DB/theme lookup, cannot use schema alone).
					return self::_validate_location( $location_input );
				},
			],
		];
	}

	/**
	 * Permission callback for all menu manager actions.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function menu_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_theme_options' );
	}

	/**
	 * Permission for create. Menu caps, plus an approval token when the AI Agent
	 * credential header is present.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool|\WP_Error
	 */
	public static function create_permission( WP_REST_Request $request ) {
		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'create_menu',
			$request->get_params(),
			self::menu_permission()
		);
	}

	/**
	 * Permission for delete. Menu caps, plus an approval token when the AI Agent
	 * credential header is present.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool|\WP_Error
	 */
	public static function delete_permission( WP_REST_Request $request ) {
		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'delete_menu',
			$request->get_params(),
			self::menu_permission()
		);
	}

	/**
	 * Permission for assign-location. Menu caps, plus an approval token when the
	 * AI Agent credential header is present.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool|\WP_Error
	 */
	public static function assign_location_permission( WP_REST_Request $request ) {
		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'assign_menu_location',
			$request->get_params(),
			self::menu_permission(),
			true === rest_sanitize_boolean( $request->get_param( 'confirm_overwrite' ) )
		);
	}

	/**
	 * Permission callback for resolve action.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function resolve_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && ( current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_posts' ) );
	}

	/**
	 * Validates menu location slug.
	 *
	 * @since ??
	 *
	 * @param string $location Location slug.
	 *
	 * @return true|\WP_Error
	 */
	private static function _validate_location( string $location ) {
		if ( '' === $location ) {
			return new WP_Error(
				'missing_location',
				esc_html__( 'Location is required.', 'et_builder_5' )
			);
		}

		if ( null === self::_resolve_location_slug( $location ) ) {
			return new WP_Error(
				'invalid_menu_location',
				esc_html__( 'Invalid menu location.', 'et_builder_5' ),
				[ 'location' => $location ]
			);
		}

		return true;
	}

	/**
	 * Resolves a user-facing menu location phrase to a registered theme location slug.
	 *
	 * Accepts exact slugs (for example secondary-menu), short names (secondary), and
	 * registered location labels (Secondary Menu).
	 *
	 * @since ??
	 *
	 * @param string $location_input Raw location slug or label from the request.
	 *
	 * @return string|null Canonical registered location slug, or null when unresolved.
	 */
	private static function _resolve_location_slug( string $location_input ): ?string {
		$registered = get_registered_nav_menus();

		if ( empty( $registered ) ) {
			return null;
		}

		$location = sanitize_key( $location_input );

		if ( '' !== $location && array_key_exists( $location, $registered ) ) {
			return $location;
		}

		if ( '' !== $location ) {
			$prefix_matches = [];

			foreach ( array_keys( $registered ) as $slug ) {
				if ( $slug === $location || 0 === strpos( $slug, $location . '-' ) ) {
					$prefix_matches[] = $slug;
				}
			}

			if ( 1 === count( $prefix_matches ) ) {
				return $prefix_matches[0];
			}
		}

		$normalized_input = strtolower( trim( $location_input ) );

		if ( '' === $normalized_input ) {
			return null;
		}

		$compact_input = preg_replace( '/[^a-z0-9]/', '', $normalized_input );
		$label_matches = [];

		foreach ( $registered as $slug => $label ) {
			$normalized_label = strtolower( $label );
			$compact_label    = preg_replace( '/[^a-z0-9]/', '', $normalized_label );

		if (
			$normalized_label === $normalized_input
			|| $compact_label === $compact_input
			|| ( '' !== $compact_input && false !== strpos( $compact_label, $compact_input ) )
		) {
				$label_matches[] = $slug;
			}
		}

		if ( 1 === count( $label_matches ) ) {
			return $label_matches[0];
		}

		return null;
	}

	/**
	 * Validates non-empty location slug.
	 *
	 * @since ??
	 *
	 * @param string $location Location slug.
	 *
	 * @return true|\WP_Error
	 */
	private static function _validate_non_empty_location( string $location ) {
		if ( '' === $location ) {
			return new WP_Error(
				'missing_location',
				esc_html__( 'Location is required.', 'et_builder_5' )
			);
		}

		return true;
	}

	/**
	 * Formats menu data for response.
	 *
	 * @since ??
	 *
	 * @param \WP_Term $menu Menu object.
	 *
	 * @return array
	 */
	private static function _format_menu( \WP_Term $menu ): array {
		return [
			'id'        => (int) $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'locations' => self::_get_menu_locations( (int) $menu->term_id ),
		];
	}

	/**
	 * Gets location slugs assigned to a menu.
	 *
	 * @since ??
	 *
	 * @param int $menu_id Menu ID.
	 *
	 * @return array
	 */
	private static function _get_menu_locations( int $menu_id ): array {
		$locations = [];

		foreach ( get_nav_menu_locations() as $location => $assigned_menu_id ) {
			if ( $menu_id === (int) $assigned_menu_id ) {
				$locations[] = $location;
			}
		}

		return $locations;
	}
}
