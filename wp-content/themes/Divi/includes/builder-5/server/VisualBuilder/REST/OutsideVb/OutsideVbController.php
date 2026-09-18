<?php
/**
 * REST: Outside Visual Builder operations for AI agent tools.
 *
 * Theme Builder "templates" are Divi Theme Builder assignments (et_template posts), not WordPress navigation menus.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\OutsideVb;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Framework\Utility\RemoteRequestUtility;
use ET\Builder\VisualBuilder\AiAgent\AiAgentApprovalTokens;
use ET\Builder\VisualBuilder\REST\Portability\PortabilityController;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Outside-VB REST controller for Theme Builder, theme options, layout export, and applying layouts to posts.
 */
class OutsideVbController extends RESTController {

	/**
	 * Option keys allowed for read/update via REST (Divi theme options / `et_get_option`).
	 *
	 * @var string[]
	 */
	private const THEME_OPTION_ALLOWLIST = [
		'divi_blog_style',
		'divi_disable_translations',
		'heading_font',
		'body_font',
		'heading_font_weight',
		'body_font_weight',
		'body_font_height',
		'body_font_size',
		'body_header_size',
		'content_width',
		'accent_color',
		'et_pb_static_css_file',
		'et_pb_css_in_footer',
		'gutter_width',
		'vertical_nav',
		'header_style',
		'color_schemes',
	];

	/**
	 * Maximum number of bytes accepted from remote web-page fetch responses.
	 *
	 * @var int
	 */
	private const WEB_PAGE_FETCH_MAX_BYTES = 1000000;

	/**
	 * Timeout for remote web-page fetch requests, in seconds.
	 *
	 * @var int
	 */
	private const WEB_PAGE_FETCH_TIMEOUT = 8;

	/**
	 * Redirect limit for remote web-page fetch requests.
	 *
	 * Allow a small number of canonical redirects (e.g. http->https, www)
	 * while still bounding request chains for safety and latency.
	 *
	 * @var int
	 */
	private const WEB_PAGE_FETCH_MAX_REDIRECTS = 3;

	/**
	 * Default maximum number of characters returned to the AI tool.
	 *
	 * @var int
	 */
	private const WEB_PAGE_FETCH_DEFAULT_MAX_CHARS = 12000;

	/**
	 * Upper bound for maxChars accepted by the REST args layer.
	 *
	 * @var int
	 */
	private const WEB_PAGE_FETCH_MAX_CHARS = 20000;

	/**
	 * Allowlisted content types for web-page reads.
	 *
	 * @var string[]
	 */
	private const WEB_PAGE_ALLOWED_CONTENT_TYPES = [
		'text/html',
		'application/xhtml+xml',
	];

	/**
	 * Maximum number of linked stylesheets to fetch in design mode.
	 *
	 * @var int
	 */
	private const WEB_PAGE_CSS_MAX_FILES = 12;

	/**
	 * Timeout for each stylesheet fetch in design mode, in seconds.
	 *
	 * @var int
	 */
	private const WEB_PAGE_CSS_FETCH_TIMEOUT = 2;

	/**
	 * Redirect limit for stylesheet fetch requests.
	 *
	 * @var int
	 */
	private const WEB_PAGE_CSS_FETCH_MAX_REDIRECTS = 3;

	/**
	 * Total time budget for all stylesheet fetches in design mode, in seconds.
	 *
	 * @var int
	 */
	private const WEB_PAGE_CSS_TOTAL_TIMEOUT = 8;

	/**
	 * Maximum number of bytes accepted from each stylesheet response.
	 *
	 * @var int
	 */
	private const WEB_PAGE_CSS_MAX_BYTES_PER_FILE = 250000;

	/**
	 * Maximum number of characters retained from each stylesheet.
	 *
	 * @var int
	 */
	private const WEB_PAGE_CSS_MAX_CHARS_PER_FILE = 30000;

	/**
	 * Maximum number of HTML characters returned in design mode.
	 *
	 * @var int
	 */
	private const WEB_PAGE_HTML_MAX_CHARS = 100000;

	/**
	 * Non-autoloaded option that stores read-web-page quota counters.
	 *
	 * @var string
	 */
	private const WEB_PAGE_QUOTA_OPTION = 'et_builder_5_read_web_page_quota';

	/**
	 * Quota window length in seconds.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_WINDOW = 60;

	/**
	 * Seconds to wait when acquiring the quota advisory lock.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_LOCK_TIMEOUT = 2;

	/**
	 * Maximum logical endpoint calls per user per window.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_USER_LOGICAL = 5;

	/**
	 * Maximum logical endpoint calls per site per window.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_SITE_LOGICAL = 20;

	/**
	 * Maximum concurrent endpoint executions per user.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_USER_CONCURRENT = 2;

	/**
	 * Maximum concurrent endpoint executions per site.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_SITE_CONCURRENT = 4;

	/**
	 * Maximum outbound HTTP hops per user per window.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_USER_OUTBOUND = 30;

	/**
	 * Maximum outbound HTTP hops per site per window.
	 *
	 * @var int
	 */
	private const WEB_PAGE_QUOTA_SITE_OUTBOUND = 100;

	/**
	 * Regex Tests:
	 * server/__TESTS__/wpunit/VisualBuilder/REST/V1/RESTOutsideVbTest.php.
	 */
	public const REGEX_HTML_TITLE_TAG         = '/<title[^>]*>(.*?)<\/title>/is';
	public const REGEX_COLLAPSE_WHITESPACE    = '/\s+/u';
	public const REGEX_HTML_LINK_TAG          = '/<link\b[^>]*>/i';
	public const REGEX_STYLESHEET_REL_ATTR    = '/\brel\s*=\s*["\'][^"\']*stylesheet[^"\']*["\']/i';
	public const REGEX_HTML_HREF_ATTR         = '/\bhref\s*=\s*["\']([^"\']+)["\']/i';
	public const REGEX_BASE_PATH_TO_DIRECTORY = '~/[^/]*$~';

	/**
	 * Whether the current user may manage Theme Builder templates.
	 *
	 * @return bool
	 */
	public static function theme_builder_permission(): bool {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return false;
		}

		if ( function_exists( 'et_pb_is_allowed' ) && ! et_pb_is_allowed( 'theme_builder' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the current user may read or update theme options.
	 *
	 * @return bool
	 */
	public static function theme_options_permission(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Permission for create_template. Theme Builder caps, plus an approval token
	 * when the AI Agent credential header is present.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|\WP_Error
	 */
	public static function create_template_permission( WP_REST_Request $request ) {
		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'create_template',
			$request->get_params(),
			self::theme_builder_permission()
		);
	}

	/**
	 * Permission for update_theme_option. Theme-option caps, plus an approval
	 * token when the AI Agent credential header is present.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|\WP_Error
	 */
	public static function update_theme_option_permission( WP_REST_Request $request ) {
		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'update_theme_option',
			$request->get_params(),
			self::theme_options_permission()
		);
	}

	/**
	 * List Theme Builder templates (draft or live).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function list_templates( WP_REST_Request $request ) {
		$live = (bool) $request->get_param( 'live' );

		$theme_builder_id = et_theme_builder_get_theme_builder_post_id( $live, false );

		if ( 0 === $theme_builder_id ) {
			return self::response_success(
				[
					'themeBuilderId' => 0,
					'templates'      => [],
				]
			);
		}

		$templates = et_theme_builder_get_theme_builder_templates( $live, $theme_builder_id );

		return self::response_success(
			[
				'themeBuilderId' => $theme_builder_id,
				'templates'      => array_values( $templates ),
			]
		);
	}

	/**
	 * Create a Theme Builder template.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_template( WP_REST_Request $request ) {
		$live             = (bool) $request->get_param( 'live' );
		$theme_builder_id = et_theme_builder_get_theme_builder_post_id( $live, true );
		$title            = $request->get_param( 'title' );
		$existing_ids     = et_theme_builder_get_theme_builder_template_ids( $live, $theme_builder_id );
		$layout_ids       = [
			'header' => 0,
			'body'   => 0,
			'footer' => 0,
		];
		$create_requests  = array_filter(
			[
				'header' => (bool) $request->get_param( 'create_header_layout' ),
				'body'   => (bool) $request->get_param( 'create_body_layout' ),
				'footer' => (bool) $request->get_param( 'create_footer_layout' ),
			]
		);

		foreach ( array_keys( $create_requests ) as $layout_type ) {
			$post_type = et_theme_builder_get_valid_layout_post_type( $layout_type );
			$inserted  = '' !== $post_type ? et_theme_builder_insert_layout( [ 'post_type' => $post_type ] ) : false;

			if ( ! $inserted || is_wp_error( $inserted ) ) {
				// Roll back any layouts already created for this template.
				foreach ( $layout_ids as $created_id ) {
					if ( 0 < $created_id ) {
						wp_trash_post( $created_id );
					}
				}

				$layout_type_label = '';
				switch ( $layout_type ) {
					case 'header':
						$layout_type_label = esc_html_x( 'header', 'Theme Builder Layout Type', 'et_builder_5' );
						break;
					case 'body':
						$layout_type_label = esc_html_x( 'body', 'Theme Builder Layout Type', 'et_builder_5' );
						break;
					case 'footer':
						$layout_type_label = esc_html_x( 'footer', 'Theme Builder Layout Type', 'et_builder_5' );
						break;
				}

				return self::response_error(
					'create_layout_failed',
					/* translators: %s: header, body, or footer */
					sprintf( esc_html__( 'Failed to create Theme Builder %s layout.', 'et_builder_5' ), $layout_type_label )
				);
			}

			$layout_ids[ $layout_type ] = (int) $inserted;
		}

		$template = [
			'id'                  => 0,
			'title'               => $title,
			'autogenerated_title' => '' === $title ? '1' : '0',
			'default'             => '0',
			'enabled'             => '1',
			'layouts'             => [
				'header' => [
					'id'      => $layout_ids['header'],
					'enabled' => true,
				],
				'body'   => [
					'id'      => $layout_ids['body'],
					'enabled' => true,
				],
				'footer' => [
					'id'      => $layout_ids['footer'],
					'enabled' => true,
				],
			],
			'use_on'              => [],
			'exclude_from'        => [],
		];

		/*
		 * Ensure there is always an explicit default template before adding the first
		 * non-default custom template. Without this, the Theme Builder UI may coerce
		 * the first template to default at render time and hide the intended custom
		 * assignment card.
		 */
		if ( empty( $existing_ids ) && ! self::_create_default_template( $theme_builder_id, $live ) ) {
			// Roll back any layouts already created for this template.
			foreach ( $layout_ids as $created_id ) {
				if ( 0 < $created_id ) {
					wp_trash_post( $created_id );
				}
			}

			return self::response_error(
				'create_default_failed',
				esc_html__( 'Failed to initialize Theme Builder default template.', 'et_builder_5' )
			);
		}

		$new_post_id = et_theme_builder_store_template( $theme_builder_id, $template, true );

		if ( ! $new_post_id ) {
			foreach ( $layout_ids as $created_id ) {
				if ( 0 < $created_id ) {
					wp_trash_post( $created_id );
				}
			}

			return self::response_error( 'create_failed', esc_html__( 'Failed to create Theme Builder template.', 'et_builder_5' ) );
		}

		if ( ! self::_append_template_id( $theme_builder_id, (int) $new_post_id, $live ) ) {
			// Roll back the template post and any created layouts.
			wp_delete_post( (int) $new_post_id, true );

			foreach ( $layout_ids as $created_id ) {
				if ( 0 < $created_id ) {
					wp_trash_post( $created_id );
				}
			}

			return self::response_error(
				'create_attach_failed',
				esc_html__( 'Failed to attach Theme Builder template to the Theme Builder.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'id'             => (int) $new_post_id,
				'themeBuilderId' => $theme_builder_id,
				'template'       => et_theme_builder_get_template( (int) $new_post_id ),
			]
		);
	}

	/**
	 * Update a Theme Builder template.
	 *
	 * The template post is updated in place: `et_theme_builder_store_template` performs an
	 * `$exists` check against `$store['id']`, so we pin the target id to `$template_id` to
	 * guarantee in-place update semantics even if a caller tries to override `id` in the
	 * incoming payload. If the existing template cannot be loaded we fail fast rather than
	 * letting `array_replace_recursive` synthesize a new structure and insert a fresh post.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_template( WP_REST_Request $request ) {
		$live             = (bool) $request->get_param( 'live' );
		$template_id      = (int) $request->get_param( 'template_id' );
		$incoming         = $request->get_param( 'template' );
		$theme_builder_id = et_theme_builder_get_theme_builder_post_id( $live, true );

		$existing = et_theme_builder_get_template( $template_id );

		// TOCTOU guard (not a validation duplicate): the args `validate_callback` already
		// confirmed the template existed at request-validation time, but the template could
		// have been trashed or deleted between validation and this controller call. Falling
		// through with `empty( $existing )` would cause `et_theme_builder_store_template` to
		// treat the payload as a fresh insert and create an orphan post, so we fail closed.
		if ( empty( $existing ) || ! isset( $existing['id'] ) || (int) $existing['id'] !== $template_id ) {
			return self::response_error(
				'template_not_found',
				esc_html__( 'Theme Builder template was not found.', 'et_builder_5' ),
				[],
				404
			);
		}

		$merged       = array_replace_recursive( $existing, is_array( $incoming ) ? $incoming : [] );
		$merged['id'] = $template_id; // Pin id to prevent orphan creation via overridden id.
		$store        = self::_template_to_store_format( $merged );

		$new_id = et_theme_builder_store_template( $theme_builder_id, $store, true );

		if ( ! is_int( $new_id ) || $new_id !== $template_id ) {
			return self::response_error( 'update_failed', esc_html__( 'Failed to update Theme Builder template.', 'et_builder_5' ) );
		}

		return self::response_success(
			[
				'id'       => $new_id,
				'template' => et_theme_builder_get_template( $new_id ),
			]
		);
	}

	/**
	 * Delete (trash) a Theme Builder template and detach it from the Theme Builder post.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_template( WP_REST_Request $request ) {
		$live            = (bool) $request->get_param( 'live' );
		$template_id     = (int) $request->get_param( 'template_id' );
		$tb_id           = et_theme_builder_get_theme_builder_post_id( $live, false );
		$template_post   = get_post( $template_id );
		$original_status = $template_post instanceof \WP_Post ? $template_post->post_status : '';

		// Trash first; if it fails there is nothing to detach and no partial state.
		$trashed = wp_trash_post( $template_id );

		if ( empty( $trashed ) ) {
			return self::response_error(
				'delete_failed',
				esc_html__( 'Failed to trash Theme Builder template.', 'et_builder_5' )
			);
		}

		if ( $tb_id > 0 ) {
			$ids = et_theme_builder_get_theme_builder_template_ids( $live, $tb_id );
			$ids = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $template_id ) {
						return (int) $id !== $template_id;
					}
				)
			);

			if ( ! self::_replace_template_ids( $tb_id, $ids ) ) {
				/*
				 * Best-effort rollback: restore the trashed template post so the Theme
				 * Builder row is not dangling.
				 */
				$restored = wp_untrash_post( $template_id );

				if ( ! ( $restored instanceof \WP_Post ) ) {
					return self::response_error(
						'delete_detach_failed',
						esc_html__( 'Template was trashed but failed to detach from the Theme Builder and automatic rollback also failed.', 'et_builder_5' ),
						[
							'rollback' => 'failed',
						]
					);
				}

				/*
				 * WordPress 5.6+ untrashes posts to 'draft' by default. Always restore
				 * the original status explicitly. Pass `true` so wp_update_post returns
				 * a WP_Error (instead of 0) on failure, allowing proper error detection.
				 */
				if ( '' !== $original_status ) {
					$restored_status = wp_update_post(
						[
							'ID'          => $template_id,
							'post_status' => $original_status,
						],
						true
					);

					if ( is_wp_error( $restored_status ) || 0 === $restored_status ) {
						return self::response_error(
							'delete_detach_failed',
							esc_html__( 'Template was trashed and failed to detach from the Theme Builder; rollback status restore failed.', 'et_builder_5' ),
							[
								'rollback' => 'failed',
							],
						);
					}
				}

				return self::response_error(
					'delete_detach_failed',
					esc_html__( 'Failed to detach template from the Theme Builder; the template was restored.', 'et_builder_5' ),
					[
						'rollback' => 'restored',
					]
				);
			}
		}

		return self::response_success( [ 'id' => $template_id ] );
	}

	/**
	 * Assign display conditions (use_on / exclude_from) for a Theme Builder template.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function assign_template( WP_REST_Request $request ) {
		$live             = (bool) $request->get_param( 'live' );
		$template_id      = (int) $request->get_param( 'template_id' );
		$theme_builder_id = et_theme_builder_get_theme_builder_post_id( $live, true );

		$existing = et_theme_builder_get_template( $template_id );

		// TOCTOU guard (same rationale as `update_template`): the args `validate_callback`
		// confirmed the template existed at validation time, but it could have been trashed
		// between then and this controller call. Calling `et_theme_builder_store_template`
		// without a concrete existing template would create an orphan post, so fail closed.
		if ( empty( $existing ) || ! isset( $existing['id'] ) || (int) $existing['id'] !== $template_id ) {
			return self::response_error(
				'template_not_found',
				esc_html__( 'Theme Builder template was not found.', 'et_builder_5' ),
				[],
				404
			);
		}

		$use_on            = $request->get_param( 'use_on' );
		$exclude_from      = $request->get_param( 'exclude_from' );
		$confirm_overwrite = (bool) $request->get_param( 'confirm_overwrite' );

		if ( null !== $use_on && is_array( $use_on ) ) {
			$conflict = self::_find_template_assignment_conflict( $live, $template_id, $use_on );

			if ( null !== $conflict ) {
				if ( ! $confirm_overwrite ) {
					return self::response_error(
						'assignment_conflict',
						esc_html__( 'Another Theme Builder template already uses one or more of these display conditions.', 'et_builder_5' ),
						array_merge(
							$conflict,
							[
								'requires_confirmation' => true,
							]
						),
						409
					);
				}

				$templates  = et_theme_builder_get_theme_builder_templates( $live, $theme_builder_id );
				$use_on_set = array_fill_keys( array_map( 'sanitize_text_field', $use_on ), true );

				$mutations = [];
				$snapshots = [];

				foreach ( $templates as $template ) {
					if ( ! is_array( $template ) ) {
						continue;
					}

					$candidate_id = (int) ( $template['id'] ?? 0 );

					if ( $candidate_id === $template_id || 0 >= $candidate_id ) {
						continue;
					}

					$existing_use_on = $template['use_on'] ?? [];

					if ( ! is_array( $existing_use_on ) ) {
						continue;
					}

					$overlap = array_filter(
						$existing_use_on,
						function ( $condition ) use ( $use_on_set ) {
							return isset( $use_on_set[ (string) $condition ] );
						}
					);

					if ( ! empty( $overlap ) ) {
						if ( ! current_user_can( 'edit_post', $candidate_id ) ) {
							return self::response_error(
								'conflict_overwrite_forbidden',
								esc_html__( 'You do not have permission to modify a conflicting Theme Builder template.', 'et_builder_5' ),
								[ 'conflicting_template_id' => $candidate_id ],
								403
							);
						}

						$snapshots[]        = $template;
						$template['use_on'] = array_values( array_diff( $existing_use_on, array_keys( $use_on_set ) ) );
						$mutations[]        = $template;
					}
				}

				$applied_snapshots = [];

				foreach ( $mutations as $index => $mutated_template ) {
					$stored_id = et_theme_builder_store_template( $theme_builder_id, self::_template_to_store_format( $mutated_template ), true );

					if ( ! is_int( $stored_id ) || $stored_id !== (int) $mutated_template['id'] ) {
						$rollback_status = 'none';
						$message         = esc_html__( 'Failed to resolve Theme Builder template conflict.', 'et_builder_5' );

						if ( ! empty( $applied_snapshots ) ) {
							$rollback_status = 'restored';
							foreach ( $applied_snapshots as $snapshot ) {
								$restored_id = et_theme_builder_store_template( $theme_builder_id, self::_template_to_store_format( $snapshot ), true );
								if ( ! is_int( $restored_id ) || $restored_id !== (int) $snapshot['id'] ) {
									$rollback_status = 'failed';
								}
							}

							if ( 'failed' === $rollback_status ) {
								$message = esc_html__( 'Failed to resolve Theme Builder template conflict and automatic rollback also failed.', 'et_builder_5' );
							} else {
								$message = esc_html__( 'Failed to resolve Theme Builder template conflict; conflicting templates were restored.', 'et_builder_5' );
							}
						}

						return self::response_error(
							'conflict_overwrite_failed',
							$message,
							[
								'rollback' => $rollback_status,
							],
							500
						);
					}

					$applied_snapshots[] = $snapshots[ $index ];
				}
			}
		}

		if ( null !== $use_on ) {
			$existing['use_on'] = array_map( 'sanitize_text_field', $use_on );
		}

		if ( null !== $exclude_from ) {
			$existing['exclude_from'] = array_map( 'sanitize_text_field', $exclude_from );
		}

		$existing['id'] = $template_id; // Pin id to prevent orphan creation.
		$store          = self::_template_to_store_format( $existing );
		$new_id         = et_theme_builder_store_template( $theme_builder_id, $store, true );

		if ( ! is_int( $new_id ) || $new_id !== $template_id ) {
			$error_data = [];
			$message    = esc_html__( 'Failed to assign Theme Builder template conditions.', 'et_builder_5' );

			if ( isset( $applied_snapshots ) && ! empty( $applied_snapshots ) ) {
				$rollback_status = 'restored';
				foreach ( $applied_snapshots as $snapshot ) {
					$restored_id = et_theme_builder_store_template( $theme_builder_id, self::_template_to_store_format( $snapshot ), true );
					if ( ! is_int( $restored_id ) || $restored_id !== (int) $snapshot['id'] ) {
						$rollback_status = 'failed';
					}
				}
				$error_data['rollback'] = $rollback_status;

				if ( 'failed' === $rollback_status ) {
					$message = esc_html__( 'Failed to assign Theme Builder template conditions and automatic rollback of conflicting templates also failed.', 'et_builder_5' );
				} else {
					$message = esc_html__( 'Failed to assign Theme Builder template conditions; conflicting templates were restored.', 'et_builder_5' );
				}
			}

			return self::response_error(
				'assign_failed',
				$message,
				$error_data
			);
		}

		return self::response_success(
			[
				'id'       => $new_id,
				'template' => et_theme_builder_get_template( $new_id ),
			]
		);
	}

	/**
	 * Get allowlisted Divi theme options (`et_get_option`).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_theme_options( WP_REST_Request $request ): WP_REST_Response {
		$keys = $request->get_param( 'keys' );
		$keys = is_array( $keys ) ? $keys : self::THEME_OPTION_ALLOWLIST;
		$keys = array_intersect( array_map( 'sanitize_text_field', $keys ), self::THEME_OPTION_ALLOWLIST );

		if ( empty( $keys ) ) {
			$keys = self::THEME_OPTION_ALLOWLIST;
		}

		$options = [];

		foreach ( $keys as $key ) {
			$options[ $key ] = et_get_option( $key, '', '', false );
		}

		return self::response_success( [ 'options' => $options ] );
	}

	/**
	 * Update a single allowlisted theme option.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_theme_option( WP_REST_Request $request ) {
		$key   = $request->get_param( 'key' );
		$value = $request->get_param( 'value' );

		/*
		 * `et_update_option` is historically void/mixed; verify the write by re-reading the
		 * option and comparing to the sanitized value. Using loose comparison here because
		 * `et_get_option` may coerce types across storage layers (e.g. serialized scalars).
		 */
		et_update_option( $key, $value );

		$stored = et_get_option( $key, '', '', false );

		if ( (string) $stored !== (string) $value ) {
			return self::response_error(
				'update_failed',
				esc_html__( 'Theme option write could not be verified.', 'et_builder_5' ),
				[
					'key'      => $key,
					'expected' => $value,
					'stored'   => $stored,
				]
			);
		}

		return self::response_success(
			[
				'key'   => $key,
				'value' => $stored,
			]
		);
	}

	/**
	 * Fetch and normalize a public web page into compact plain text for the AI agent.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function read_web_page( WP_REST_Request $request ) {
		$lease = self::_acquire_read_web_page_quota();

		if ( is_wp_error( $lease ) ) {
			return $lease;
		}

		$outbound_hops = 0;

		try {
			return self::_read_web_page_after_quota( $request, $lease, $outbound_hops );
		} finally {
			self::_release_read_web_page_quota( $lease, $outbound_hops );
		}
	}

	/**
	 * Fetch and normalize a remote web page after quota leases are held.
	 *
	 * @param WP_REST_Request      $request       Request.
	 * @param array<string, mixed> $lease         Quota lease data.
	 * @param int                  $outbound_hops Outbound hops counter.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	private static function _read_web_page_after_quota( WP_REST_Request $request, array $lease, int &$outbound_hops ) {
		$url               = (string) $request->get_param( 'url' );
		$max_chars         = (int) $request->get_param( 'maxChars' );
		$mode              = (string) $request->get_param( 'mode' );
		$mode              = in_array( $mode, [ 'text', 'design' ], true ) ? $mode : 'text';
		$include_full_html = (bool) $request->get_param( 'includeFullHtml' );
		$max_chars         = $max_chars > 0 ? min( $max_chars, self::WEB_PAGE_FETCH_MAX_CHARS ) : self::WEB_PAGE_FETCH_DEFAULT_MAX_CHARS;
		$user_outbound     = (array) ( $lease['user_outbound'] ?? [] );
		$site_outbound     = (array) ( $lease['site_outbound'] ?? [] );

		$before_hop = static function () use ( &$outbound_hops, $user_outbound, $site_outbound ) {
			$now = time();

			if ( ( count( $user_outbound ) + $outbound_hops ) >= self::WEB_PAGE_QUOTA_USER_OUTBOUND
				|| ( count( $site_outbound ) + $outbound_hops ) >= self::WEB_PAGE_QUOTA_SITE_OUTBOUND
			) {
				$timestamps  = ( count( $user_outbound ) + $outbound_hops ) >= self::WEB_PAGE_QUOTA_USER_OUTBOUND ? $user_outbound : $site_outbound;
				$retry_after = self::_quota_retry_after_from_timestamps( $timestamps, $now );

				return self::_quota_rate_error( $retry_after );
			}

			$outbound_hops++;

			return true;
		};

		$response = RemoteRequestUtility::get(
			$url,
			[
				'timeout'       => self::WEB_PAGE_FETCH_TIMEOUT,
				'max_bytes'     => self::WEB_PAGE_FETCH_MAX_BYTES,
				'max_redirects' => self::WEB_PAGE_FETCH_MAX_REDIRECTS,
				'headers'       => [
					'Accept' => 'text/html,application/xhtml+xml',
				],
				'before_hop'    => $before_hop,
			]
		);

		if ( is_wp_error( $response ) ) {
			return self::_map_web_page_fetch_error( $response );
		}

		$content_type_header = (string) $response['content_type'];
		$content_type        = self::_normalize_content_type( $content_type_header );

		if ( ! in_array( $content_type, self::WEB_PAGE_ALLOWED_CONTENT_TYPES, true ) ) {
			return self::response_error(
				'unsupported_content_type',
				esc_html__( 'The target URL did not return a supported text content type.', 'et_builder_5' ),
				[
					'contentType' => $content_type_header,
				],
				415
			);
		}

		$body      = (string) $response['body'];
		$text      = self::_extract_readable_text( $body );
		$final_url = (string) $response['final_url'];

		if ( 'text' === $mode && '' === $text ) {
			return self::response_error(
				'empty_content',
				esc_html__( 'No readable text content was found on the target page.', 'et_builder_5' ),
				[],
				422
			);
		}

		$text_length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		$truncated   = false;

		if ( $text_length > $max_chars ) {
			$text      = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_chars ) : substr( $text, 0, $max_chars );
			$truncated = true;
		}

		$response_payload = [
			'url'           => $url,
			'finalUrl'      => $final_url,
			'mode'          => $mode,
			'title'         => self::_extract_html_title( $body ),
			'contentType'   => $content_type,
			'content'       => $text,
			'contentLength' => $text_length,
			'truncated'     => $truncated,
		];

		if ( 'design' === $mode ) {
			$html           = $body;
			$html_length    = function_exists( 'mb_strlen' ) ? mb_strlen( $html ) : strlen( $html );
			$html_truncated = false;

			if ( $html_length > self::WEB_PAGE_HTML_MAX_CHARS ) {
				$html           = function_exists( 'mb_substr' ) ? mb_substr( $html, 0, self::WEB_PAGE_HTML_MAX_CHARS ) : substr( $html, 0, self::WEB_PAGE_HTML_MAX_CHARS );
				$html_truncated = true;
			}

			$css_result = self::_extract_linked_css_files( $final_url, $body, $before_hop );

			if ( $include_full_html ) {
				$response_payload['html'] = $html;
			}
			$response_payload['htmlLength']    = $html_length;
			$response_payload['htmlTruncated'] = $html_truncated;
			$response_payload['cssFiles']      = $css_result['files'];
			$response_payload['cssCount']      = count( $css_result['files'] );
			$response_payload['warnings']      = $css_result['warnings'];
		}

		return self::response_success( $response_payload );
	}

	/**
	 * Export layout JSON for a post via portability (wraps `PortabilityController::show`).
	 *
	 * Supports the canonical portability pagination contract: when the portability layer
	 * needs to paginate image encoding it returns `{ page, totalPages, timestamp }`. The
	 * client is expected to loop, forwarding the optional `page` and `timestamp` params on
	 * subsequent requests until the final `{ timestamp }`-only response is received.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function export_layout( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		if ( ! ( $post instanceof \WP_Post ) ) {
			return self::response_error(
				'post_not_found',
				esc_html__( 'Post not found.', 'et_builder_5' )
			);
		}

		$sub = new WP_REST_Request( 'POST' );
		$sub->set_param( 'context', 'et_builder' );
		$sub->set_param( 'post', (string) $post_id );
		$sub->set_param( 'content', $post->post_content );
		$sub->set_param( 'portability_type', 'post' );

		// Forward optional pagination params so the client can continue an in-progress
		// paginated export without re-POSTing the full payload.
		if ( $request->has_param( 'page' ) ) {
			$sub->set_param( 'page', $request->get_param( 'page' ) );
		}

		if ( $request->has_param( 'timestamp' ) ) {
			$sub->set_param( 'timestamp', $request->get_param( 'timestamp' ) );
		}

		return PortabilityController::show( $sub );
	}

	/**
	 * Apply Divi builder content to a post or page (does not create posts; use dedicated page/post tools for CRUD).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set_post_layout( WP_REST_Request $request ) {
		$post_id   = (int) $request->get_param( 'post_id' );
		$source_id = (int) $request->get_param( 'source_layout_post_id' );
		$content   = $request->get_param( 'layout_content' );

		// Args layer (set_post_layout_args) already enforced: post exists, source post
		// exists when provided, and exactly one of source/content is set. So the controller
		// only needs to pick the branch.
		if ( $source_id > 0 ) {
			$source = get_post( $source_id );
			if ( ! ( $source instanceof \WP_Post ) ) {
				return self::response_error(
					'source_layout_not_found',
					esc_html__( 'Source layout post was removed before applying the layout.', 'et_builder_5' ),
					[],
					404
				);
			}
			$new_content = $source->post_content;
		} else {
			$new_content = (string) $content;
		}

		/*
		 * Snapshot the original state before mutating so we can roll back if the
		 * builder-flag write fails. `wp_update_post` + `update_post_meta` cannot be done as a
		 * single transaction, so the best we can do is restore the prior `post_content` and
		 * meta value to avoid leaving the post in an inconsistent state (content updated but
		 * builder flag unset would render Divi shortcodes as raw text on the frontend).
		 * `post_id` was already validated as an existing WP_Post by the args layer.
		 */
		$original_post    = get_post( $post_id );
		$original_content = $original_post->post_content;
		$original_flag    = get_post_meta( $post_id, '_et_pb_use_builder', true );

		$updated = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => wp_slash( $new_content ),
			],
			true
		);

		if ( is_wp_error( $updated ) ) {
			return self::response_error( 'update_failed', esc_html__( 'Failed to update post content.', 'et_builder_5' ) );
		}

		/*
		 * Verify the builder flag write succeeds before returning success.
		 * `update_post_meta` returns false both on DB failure AND when the new value matches
		 * the current one, so skip the write when the flag is already set to avoid a false
		 * negative.
		 */
		$current_flag = get_post_meta( $post_id, '_et_pb_use_builder', true );

		if ( 'on' !== $current_flag && false === update_post_meta( $post_id, '_et_pb_use_builder', 'on' ) ) {
			// Roll back the post_content update so the post is not left half-migrated.
			wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => wp_slash( $original_content ),
				]
			);

			if ( '' !== $original_flag ) {
				update_post_meta( $post_id, '_et_pb_use_builder', $original_flag );
			} else {
				delete_post_meta( $post_id, '_et_pb_use_builder' );
			}

			return self::response_error(
				'builder_flag_failed',
				esc_html__( 'Builder flag could not be set; post was rolled back to its previous state.', 'et_builder_5' ),
				[
					'rollback' => 'restored',
				]
			);
		}

		if ( function_exists( 'et_builder_enable_for_post' ) ) {
			et_builder_enable_for_post( $post_id, false );
		}

		return self::response_success(
			[
				'post_id' => $post_id,
			]
		);
	}

	/**
	 * Args for list_templates.
	 *
	 * @return array
	 */
	public static function list_templates_args(): array {
		return [
			'live' => [
				'required'          => false,
				'default'           => false,
				'sanitize_callback' => function ( $value ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				},
			],
		];
	}

	/**
	 * Args for create_template.
	 *
	 * @return array
	 */
	public static function create_template_args(): array {
		return [
			'live'                 => [
				'required'          => false,
				'default'           => true,
				'sanitize_callback' => function ( $value ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				},
			],
			'create_header_layout' => [
				'required'          => false,
				'default'           => false,
				'sanitize_callback' => function ( $value ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				},
			],
			'create_body_layout'   => [
				'required'          => false,
				'default'           => false,
				'sanitize_callback' => function ( $value ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				},
			],
			'create_footer_layout' => [
				'required'          => false,
				'default'           => false,
				'sanitize_callback' => function ( $value ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				},
			],
			'title'                => [
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Args for update_template.
	 *
	 * @return array
	 */
	public static function update_template_args(): array {
		return [
			'live'        => [
				'required' => false,
				'default'  => false,
				'type'     => 'boolean',
			],
			'template_id' => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'validate_callback' => function ( $value ) {
					return ! empty( et_theme_builder_get_template( (int) $value ) ) && 'publish' === get_post_status( (int) $value );
				},
				'sanitize_callback' => 'absint',
			],
			'template'    => [
				'required'          => true,
				'type'              => 'object',
				'validate_callback' => [ self::class, '_validate_template_shape' ],
			],
		];
	}

	/**
	 * Args for delete_template.
	 *
	 * @return array
	 */
	public static function delete_template_args(): array {
		return [
			'live'        => [
				'required' => false,
				'default'  => false,
				'type'     => 'boolean',
			],
			'template_id' => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'validate_callback' => function ( $value ) {
					$template_post = get_post( (int) $value );
					return $template_post instanceof \WP_Post && ET_THEME_BUILDER_TEMPLATE_POST_TYPE === $template_post->post_type;
				},
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Args for assign_template.
	 *
	 * @return array
	 */
	public static function assign_template_args(): array {
		return [
			'live'              => [
				'required' => false,
				'default'  => true,
				'type'     => 'boolean',
			],
			'template_id'       => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'validate_callback' => function ( $value ) {
					return ! empty( et_theme_builder_get_template( (int) $value ) ) && 'publish' === get_post_status( (int) $value );
				},
				'sanitize_callback' => 'absint',
			],
			'use_on'            => [
				'required' => false,
				'type'     => 'array',
				'items'    => [
					'type' => 'string',
				],
			],
			'exclude_from'      => [
				'required' => false,
				'type'     => 'array',
				'items'    => [
					'type' => 'string',
				],
			],
			'confirm_overwrite' => [
				'required' => false,
				'type'     => 'boolean',
				'default'  => false,
			],
		];
	}

	/**
	 * Args for get_theme_options.
	 *
	 * @return array
	 */
	public static function get_theme_options_args(): array {
		return [
			'keys' => [
				'required' => false,
			],
		];
	}

	/**
	 * Args for update_theme_option.
	 *
	 * @return array
	 */
	public static function update_theme_option_args(): array {
		return [
			'key'   => [
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return in_array( $value, self::THEME_OPTION_ALLOWLIST, true );
				},
				'sanitize_callback' => 'sanitize_text_field',
			],
			'value' => [
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => function ( $value ) {
					return is_string( $value );
				},
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Args for read_web_page.
	 *
	 * @return array
	 */
	public static function read_web_page_args(): array {
		return [
			'url'             => [
				'required'          => true,
				'type'              => 'string',
				'format'            => 'uri',
				'sanitize_callback' => 'esc_url_raw',
				'validate_callback' => [ self::class, 'validate_web_page_url_param' ],
			],
			'maxChars'        => [
				'required'          => false,
				'type'              => 'integer',
				'default'           => self::WEB_PAGE_FETCH_DEFAULT_MAX_CHARS,
				'minimum'           => 1000,
				'maximum'           => self::WEB_PAGE_FETCH_MAX_CHARS,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'mode'            => [
				'required'          => false,
				'type'              => 'string',
				'default'           => 'text',
				'enum'              => [ 'text', 'design' ],
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'includeFullHtml' => [
				'required'          => false,
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => function ( $value ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				},
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Permission for read_web_page.
	 *
	 * @return bool
	 */
	public static function read_web_page_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_posts' );
	}

	/**
	 * Args for export_layout.
	 *
	 * @return array
	 */
	public static function export_layout_args(): array {
		return [
			'post_id'   => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'validate_callback' => function ( $value ) {
					return get_post( (int) $value ) instanceof \WP_Post;
				},
				'sanitize_callback' => 'absint',
			],
			// Optional pagination params forwarded to PortabilityController::show().
			// The portability layer may return an interim { page, totalPages, timestamp }
			// response while encoding images; the client loops with these params until
			// it receives the final { timestamp }-only payload.
			'page'      => [
				'required'          => false,
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
			'timestamp' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Args for set_post_layout.
	 *
	 * @return array
	 */
	public static function set_post_layout_args(): array {
		return [

			/*
			 * Cross-field "exactly one of" validation (PHP-1.5) is attached to `post_id`
			 * because WP_REST_Server only runs validate_callback for params that are present
			 * in the request, and `post_id` is the one `required` key that is guaranteed to
			 * be set. Hanging the XOR check off `source_layout_post_id` would silently
			 * bypass when only `layout_content` is sent, and vice versa. Resource existence
			 * checks for the source post live here too so the args layer fully owns
			 * validation (business logic stays in the controller per the architecture rule).
			 */
			'post_id'               => [
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'validate_callback' => function ( $value, $request ) {
					if ( ! ( get_post( (int) $value ) instanceof \WP_Post ) ) {
						return new \WP_Error(
							'rest_invalid_param',
							esc_html__( 'Post not found.', 'et_builder_5' ),
							[ 'status' => 400 ]
						);
					}

					$source_id   = (int) $request->get_param( 'source_layout_post_id' );
					$content     = $request->get_param( 'layout_content' );
					$has_source  = $source_id > 0;
					$has_content = is_string( $content ) && '' !== $content;

					if ( ! $has_source && ! $has_content ) {
						return new \WP_Error(
							'rest_invalid_param',
							esc_html__( 'Provide source_layout_post_id or layout_content.', 'et_builder_5' ),
							[ 'status' => 400 ]
						);
					}

					if ( $has_source && $has_content ) {
						return new \WP_Error(
							'rest_invalid_param',
							esc_html__( 'Provide only one of source_layout_post_id or layout_content.', 'et_builder_5' ),
							[ 'status' => 400 ]
						);
					}

					if ( $has_source && ! ( get_post( $source_id ) instanceof \WP_Post ) ) {
						return new \WP_Error(
							'rest_invalid_param',
							esc_html__( 'Source layout post not found.', 'et_builder_5' ),
							[ 'status' => 400 ]
						);
					}

					return true;
				},
				'sanitize_callback' => 'absint',
			],
			'source_layout_post_id' => [
				'required'          => false,
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			],
			'layout_content'        => [
				'required' => false,
				'type'     => 'string',
			],
		];
	}

	/**
	 * Permission for export_layout (aligned with `PortabilityController::show_permission` for `et_builder`).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool
	 */
	public static function export_layout_permission( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$sub_request = new WP_REST_Request( 'POST' );
		$sub_request->set_param( 'context', 'et_builder' );

		$result = PortabilityController::show_permission( $sub_request );

		return true === $result;
	}

	/**
	 * Permission for set_post_layout.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|\WP_Error
	 */
	public static function set_post_layout_permission( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$source_id = (int) $request->get_param( 'source_layout_post_id' );

		if ( $source_id > 0 && ! current_user_can( 'read_post', $source_id ) ) {
			return false;
		}

		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'set_divi_layout',
			$request->get_params(),
			current_user_can( 'edit_posts' )
		);
	}

	/**
	 * Validate a requested URL for remote web-page reads.
	 *
	 * Rejects non-http(s), local hostnames, and private/reserved IP targets to reduce SSRF risk.
	 *
	 * @param mixed $value Raw URL value.
	 *
	 * @return bool|\WP_Error
	 */
	public static function validate_web_page_url_param( $value ) {
		$validated = RemoteRequestUtility::validate_public_url( $value );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		return true;
	}

	/**
	 * Permission for delete_template. Requires the coarse Theme Builder feature
	 * capability PLUS the resource-scoped `delete_post` capability for the target
	 * template (PHP-1.13).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|\WP_Error
	 */
	public static function delete_template_permission( WP_REST_Request $request ) {
		if ( ! self::theme_builder_permission() ) {
			return false;
		}

		$template_id = (int) $request->get_param( 'template_id' );

		if ( $template_id > 0 && ! current_user_can( 'delete_post', $template_id ) ) {
			return false;
		}

		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'delete_template',
			$request->get_params(),
			true
		);
	}

	/**
	 * Permission for update_template. Requires the coarse Theme Builder feature
	 * capability PLUS the resource-scoped `edit_post` capability for the target
	 * template (PHP-1.13). Coarse-only checks would grant any Theme Builder user
	 * write access to every template on the site.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|\WP_Error
	 */
	public static function update_template_permission( WP_REST_Request $request ) {
		if ( ! self::theme_builder_permission() ) {
			return false;
		}

		$template_id = (int) $request->get_param( 'template_id' );

		if ( $template_id > 0 && ! current_user_can( 'edit_post', $template_id ) ) {
			return false;
		}

		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'update_template',
			$request->get_params(),
			true
		);
	}

	/**
	 * Permission for assign_template. Same resource-scoped model as
	 * `update_template_permission` — assigning display conditions mutates the
	 * template post's meta via `et_theme_builder_store_template`, so the caller
	 * must be able to `edit_post` that specific template (PHP-1.13).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|\WP_Error
	 */
	public static function assign_template_permission( WP_REST_Request $request ) {
		if ( ! self::theme_builder_permission() ) {
			return false;
		}

		$template_id = (int) $request->get_param( 'template_id' );

		if ( $template_id > 0 && ! current_user_can( 'edit_post', $template_id ) ) {
			return false;
		}

		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'assign_template',
			$request->get_params(),
			true,
			true === rest_sanitize_boolean( $request->get_param( 'confirm_overwrite' ) )
		);
	}

	/**
	 * Replace Theme Builder template ID list on the Theme Builder post.
	 *
	 * Only writes deltas (removed/added IDs) rather than delete-then-reinsert to
	 * minimize database writes and reduce the window for partial-failure states.
	 * Returns `false` on the first failed meta write so callers can surface the error.
	 *
	 * @param int   $theme_builder_id Theme Builder post ID.
	 * @param int[] $template_ids     Desired template post IDs.
	 *
	 * @return bool True on success, false if any meta write failed.
	 */
	private static function _replace_template_ids( int $theme_builder_id, array $template_ids ): bool {
		$existing = get_post_meta( $theme_builder_id, '_et_template', false );
		$existing = is_array( $existing ) ? array_map( 'intval', $existing ) : [];

		// Dedupe both sides so the diff yields one delete/add per distinct ID.
		// `delete_post_meta( $pid, $key, $value )` already removes every row matching
		// that value in a single call, and duplicate adds are never desired.
		$existing_unique = array_values( array_unique( $existing ) );
		$target_unique   = array_values( array_unique( array_map( 'intval', $template_ids ) ) );

		$to_remove = array_diff( $existing_unique, $target_unique );
		$to_add    = array_diff( $target_unique, $existing_unique );

		foreach ( $to_remove as $tid ) {
			if ( false === delete_post_meta( $theme_builder_id, '_et_template', (int) $tid ) ) {
				return false;
			}
		}

		foreach ( $to_add as $tid ) {
			if ( false === add_post_meta( $theme_builder_id, '_et_template', (int) $tid ) ) {
				return false;
			}
		}

		// REST writes make post meta authoritative; clear any stale interrupted-save backup
		// so Theme Builder reads and the admin UI stay in sync with AI agent changes.
		$backup = get_option( 'et_tb_templates_backup_' . $theme_builder_id, false );
		if ( false !== $backup ) {
			$backup_ids = is_array( $backup ) ? array_map( 'intval', $backup ) : [];
			// If the backup matches the existing database state before this write, it's stale.
			if ( $backup_ids === $existing ) {
				delete_option( 'et_tb_templates_backup_' . $theme_builder_id );
			} else {
				// If it's different, update the backup to include our new changes, preserving the backup's role
				// as recovery state for any in-flight saves.
				$merged_backup = array_values( array_unique( array_merge( $backup_ids, $to_add ) ) );
				$merged_backup = array_diff( $merged_backup, $to_remove );
				update_option( 'et_tb_templates_backup_' . $theme_builder_id, array_values( $merged_backup ) );
			}
		}

		return true;
	}

	/**
	 * Append a template ID to the Theme Builder post.
	 *
	 * Uses the same template ID source as Theme Builder reads (including any interrupted-save
	 * backup) so appended templates are not lost when a backup option is still present.
	 *
	 * @param int  $theme_builder_id Theme Builder post ID.
	 * @param int  $template_id      Template post ID.
	 * @param bool $live             Whether the Theme Builder post is live or draft.
	 *
	 * @return bool True on success, false if the meta write failed.
	 */
	private static function _append_template_id( int $theme_builder_id, int $template_id, bool $live ): bool {
		$ids = et_theme_builder_get_theme_builder_template_ids( $live, $theme_builder_id );

		if ( ! in_array( $template_id, $ids, true ) ) {
			$ids[] = $template_id;
		}

		return self::_replace_template_ids( $theme_builder_id, $ids );
	}

	/**
	 * Create and attach a default template for an empty Theme Builder.
	 *
	 * @param int  $theme_builder_id Theme Builder post ID.
	 * @param bool $live             Whether the Theme Builder post is live or draft.
	 *
	 * @return bool True on success, false on failure.
	 */
	private static function _create_default_template( int $theme_builder_id, bool $live ): bool {
		$default_template_id = et_theme_builder_store_template(
			$theme_builder_id,
			[
				'id'                  => 0,
				'title'               => '',
				'autogenerated_title' => '1',
				'default'             => '1',
				'enabled'             => '1',
				'layouts'             => [
					'header' => [
						'id'      => 0,
						'enabled' => true,
					],
					'body'   => [
						'id'      => 0,
						'enabled' => true,
					],
					'footer' => [
						'id'      => 0,
						'enabled' => true,
					],
				],
				'use_on'              => [],
				'exclude_from'        => [],
			],
			true
		);

		if ( ! $default_template_id ) {
			return false;
		}

		if ( ! self::_append_template_id( $theme_builder_id, (int) $default_template_id, $live ) ) {
			wp_delete_post( (int) $default_template_id, true );
			return false;
		}

		return true;
	}

	/**
	 * Validate the shape of an incoming `template` payload to `update_template`.
	 *
	 * We keep this conservative: reject obviously wrong types and unknown top-level
	 * keys so callers cannot feed arbitrary values into `array_replace_recursive` and
	 * persist them as template meta.
	 *
	 * @param mixed $value Incoming value.
	 *
	 * @return bool|\WP_Error
	 */
	public static function _validate_template_shape( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		$allowed_top = [
			'id',
			'item_id',
			'title',
			'default',
			'enabled',
			'autogenerated_title',
			'layouts',
			'use_on',
			'exclude_from',
		];

		foreach ( array_keys( $value ) as $key ) {
			if ( ! in_array( $key, $allowed_top, true ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					/* translators: %s: disallowed key */
					sprintf( esc_html__( 'Unknown template key "%s".', 'et_builder_5' ), $key ),
					[ 'status' => 400 ]
				);
			}
		}

		if ( isset( $value['title'] ) && ! is_string( $value['title'] ) ) {
			return false;
		}

		foreach ( [ 'use_on', 'exclude_from' ] as $list_key ) {
			if ( isset( $value[ $list_key ] ) && ! is_array( $value[ $list_key ] ) ) {
				return false;
			}
		}

		if ( isset( $value['layouts'] ) ) {
			if ( ! is_array( $value['layouts'] ) ) {
				return false;
			}

			foreach ( [ 'header', 'body', 'footer' ] as $slot ) {
				if ( ! isset( $value['layouts'][ $slot ] ) ) {
					continue;
				}

				if ( ! is_array( $value['layouts'][ $slot ] ) ) {
					return false;
				}

				if ( isset( $value['layouts'][ $slot ]['id'] ) && ! is_numeric( $value['layouts'][ $slot ]['id'] ) ) {
					return false;
				}

				if ( isset( $value['layouts'][ $slot ]['enabled'] ) && ! is_bool( $value['layouts'][ $slot ]['enabled'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Finds another template that already uses one or more requested display conditions.
	 *
	 * @since ??
	 *
	 * @param bool       $live Whether to inspect the live Theme Builder.
	 * @param int        $template_id Template being assigned.
	 * @param array|null $use_on Requested display conditions.
	 *
	 * @return array|null Conflict details when another template overlaps; otherwise null.
	 */
	private static function _find_template_assignment_conflict( bool $live, int $template_id, ?array $use_on ): ?array {
		if ( empty( $use_on ) || ! is_array( $use_on ) ) {
			return null;
		}

		$theme_builder_id = et_theme_builder_get_theme_builder_post_id( $live, false );

		if ( 0 === $theme_builder_id ) {
			return null;
		}

		$templates  = et_theme_builder_get_theme_builder_templates( $live, $theme_builder_id );
		$use_on_set = array_fill_keys( array_map( 'sanitize_text_field', $use_on ), true );

		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}

			$candidate_id = (int) ( $template['id'] ?? 0 );

			if ( $candidate_id === $template_id || 0 >= $candidate_id ) {
				continue;
			}

			$existing_use_on = $template['use_on'] ?? [];

			if ( ! is_array( $existing_use_on ) ) {
				continue;
			}

			$overlap = array_values(
				array_filter(
					$existing_use_on,
					function ( $condition ) use ( $use_on_set ) {
						return isset( $use_on_set[ (string) $condition ] );
					}
				)
			);

			if ( ! empty( $overlap ) ) {
				return [
					'template_id'            => $candidate_id,
					'template_title'         => isset( $template['title'] ) ? sanitize_text_field( (string) $template['title'] ) : '',
					'overlapping_conditions' => $overlap,
				];
			}
		}

		return null;
	}

	/**
	 * Convert a merged template array (from `et_theme_builder_get_template` + updates) to the structure expected by `et_theme_builder_store_template`.
	 *
	 * @param array $t Template.
	 *
	 * @return array
	 */
	private static function _template_to_store_format( array $t ): array {
		$title = isset( $t['title'] ) ? sanitize_text_field( (string) $t['title'] ) : '';

		$default = ! empty( $t['default'] ) && '0' !== $t['default'];
		$enabled = ! empty( $t['enabled'] ) && '0' !== $t['enabled'];
		$header  = isset( $t['layouts']['header'] ) ? $t['layouts']['header'] : [];
		$body    = isset( $t['layouts']['body'] ) ? $t['layouts']['body'] : [];
		$footer  = isset( $t['layouts']['footer'] ) ? $t['layouts']['footer'] : [];

		return [
			'id'                  => isset( $t['id'] ) ? (int) $t['id'] : 0,
			'title'               => $title,
			'autogenerated_title' => '' === $title ? '1' : '0',
			'default'             => $default ? '1' : '0',
			'enabled'             => $enabled ? '1' : '0',
			'layouts'             => [
				'header' => [
					'id'      => isset( $header['id'] ) ? (int) $header['id'] : 0,
					'enabled' => ! empty( $header['enabled'] ),
				],
				'body'   => [
					'id'      => isset( $body['id'] ) ? (int) $body['id'] : 0,
					'enabled' => ! empty( $body['enabled'] ),
				],
				'footer' => [
					'id'      => isset( $footer['id'] ) ? (int) $footer['id'] : 0,
					'enabled' => ! empty( $footer['enabled'] ),
				],
			],
			'use_on'              => isset( $t['use_on'] ) && is_array( $t['use_on'] ) ? array_map( 'sanitize_text_field', $t['use_on'] ) : [],
			'exclude_from'        => isset( $t['exclude_from'] ) && is_array( $t['exclude_from'] ) ? array_map( 'sanitize_text_field', $t['exclude_from'] ) : [],
		];
	}

	/**
	 * Normalize a content-type header value to its mime-type token.
	 *
	 * @param string $content_type_header Raw content-type header.
	 *
	 * @return string
	 */
	private static function _normalize_content_type( string $content_type_header ): string {
		$parts = explode( ';', strtolower( trim( $content_type_header ) ) );

		return isset( $parts[0] ) ? trim( $parts[0] ) : '';
	}

	/**
	 * Extract a compact page title from raw HTML.
	 *
	 * @param string $html Raw HTML body.
	 *
	 * @return string
	 */
	private static function _extract_html_title( string $html ): string {
		if ( preg_match( self::REGEX_HTML_TITLE_TAG, $html, $matches ) ) {
			$title = html_entity_decode( wp_strip_all_tags( (string) $matches[1], true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$title = preg_replace( self::REGEX_COLLAPSE_WHITESPACE, ' ', $title );

			return is_string( $title ) ? trim( $title ) : '';
		}

		return '';
	}

	/**
	 * Extract readable plain text from a remote web-page response body.
	 *
	 * @param string $body Raw response body.
	 *
	 * @return string
	 */
	private static function _extract_readable_text( string $body ): string {
		$text = wp_strip_all_tags( $body, false );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( self::REGEX_COLLAPSE_WHITESPACE, ' ', $text );

		return is_string( $text ) ? trim( $text ) : '';
	}

	/**
	 * Extract and fetch linked stylesheet files from an HTML document.
	 *
	 * @param string        $page_url   Page URL used to resolve relative stylesheet links.
	 * @param string        $html       Raw HTML document.
	 * @param callable|null $before_hop Optional hop admission callback.
	 *
	 * @return array{files: array<int, array<string, mixed>>, warnings: array<int, string>}
	 */
	private static function _extract_linked_css_files( string $page_url, string $html, $before_hop = null ): array {
		$files    = [];
		$warnings = [];
		$hrefs    = self::_extract_stylesheet_hrefs( $html );
		$deadline = microtime( true ) + self::WEB_PAGE_CSS_TOTAL_TIMEOUT;

		if ( count( $hrefs ) > self::WEB_PAGE_CSS_MAX_FILES ) {
			$warnings[] = sprintf(
				/* translators: %d: max number of CSS files to fetch. */
				esc_html__( 'Stylesheet list exceeded the fetch limit of %d files.', 'et_builder_5' ),
				self::WEB_PAGE_CSS_MAX_FILES
			);
		}

		foreach ( array_slice( $hrefs, 0, self::WEB_PAGE_CSS_MAX_FILES ) as $href ) {
			if ( microtime( true ) >= $deadline ) {
				$warnings[] = sprintf(
					/* translators: %d: total CSS fetch time budget in seconds. */
					esc_html__( 'Stopped fetching additional stylesheets after reaching the %d second time budget.', 'et_builder_5' ),
					self::WEB_PAGE_CSS_TOTAL_TIMEOUT
				);
				break;
			}

			$resolved_url = self::_resolve_url( $page_url, $href );

			if ( '' === $resolved_url ) {
				$warnings[] = esc_html__( 'Skipped an invalid stylesheet URL.', 'et_builder_5' );
				continue;
			}

			$validation = self::validate_web_page_url_param( $resolved_url );
			if ( is_wp_error( $validation ) ) {
				$warnings[] = sprintf(
					/* translators: %s: stylesheet URL. */
					esc_html__( 'Skipped blocked stylesheet URL: %s', 'et_builder_5' ),
					esc_url_raw( $resolved_url )
				);
				continue;
			}

			$css_response = RemoteRequestUtility::get(
				$resolved_url,
				[
					'timeout'       => self::WEB_PAGE_CSS_FETCH_TIMEOUT,
					'max_bytes'     => self::WEB_PAGE_CSS_MAX_BYTES_PER_FILE,
					'max_redirects' => self::WEB_PAGE_CSS_FETCH_MAX_REDIRECTS,
					'headers'       => [
						'Accept' => 'text/css,text/plain;q=0.9',
					],
					'before_hop'    => $before_hop,
				]
			);

			if ( is_wp_error( $css_response ) ) {
				$error_code = $css_response->get_error_code();

				if ( in_array( $error_code, [ 'rate_limit_exceeded', 'fetch_capacity_exceeded' ], true ) ) {
					$warnings[] = esc_html__( 'Stopped fetching additional stylesheets after reaching the outbound request budget.', 'et_builder_5' );
					break;
				}

				$warnings[] = sprintf(
					/* translators: %s: stylesheet URL. */
					esc_html__( 'Failed to fetch stylesheet: %s', 'et_builder_5' ),
					esc_url_raw( $resolved_url )
				);
				continue;
			}

			$css_content_type_header = (string) $css_response['content_type'];
			$css_content_type        = self::_normalize_content_type( $css_content_type_header );
			$css_final_url           = (string) $css_response['final_url'];
			$css_path                = (string) wp_parse_url( $css_final_url, PHP_URL_PATH );
			$looks_like_css_file     = str_ends_with( strtolower( $css_path ), '.css' );
			$is_allowed_css_type     = in_array( $css_content_type, [ 'text/css', 'text/plain' ], true );

			if ( ! $is_allowed_css_type && ! $looks_like_css_file ) {
				$warnings[] = sprintf(
					/* translators: %s: stylesheet URL. */
					esc_html__( 'Skipped stylesheet with unsupported content type: %s', 'et_builder_5' ),
					esc_url_raw( $resolved_url )
				);
				continue;
			}

			$css_content   = (string) $css_response['body'];
			$css_length    = function_exists( 'mb_strlen' ) ? mb_strlen( $css_content ) : strlen( $css_content );
			$css_truncated = false;

			if ( $css_length > self::WEB_PAGE_CSS_MAX_CHARS_PER_FILE ) {
				$css_content   = function_exists( 'mb_substr' ) ? mb_substr( $css_content, 0, self::WEB_PAGE_CSS_MAX_CHARS_PER_FILE ) : substr( $css_content, 0, self::WEB_PAGE_CSS_MAX_CHARS_PER_FILE );
				$css_truncated = true;
			}

			$files[] = [
				'url'           => $css_final_url,
				'contentType'   => '' !== $css_content_type ? $css_content_type : 'unknown',
				'content'       => $css_content,
				'contentLength' => $css_length,
				'truncated'     => $css_truncated,
			];
		}

		return [
			'files'    => $files,
			'warnings' => $warnings,
		];
	}

	/**
	 * Extract stylesheet link href attributes from an HTML document.
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return string[]
	 */
	private static function _extract_stylesheet_hrefs( string $html ): array {
		$hrefs = [];

		if ( preg_match_all( self::REGEX_HTML_LINK_TAG, $html, $link_matches ) ) {
			foreach ( $link_matches[0] as $link_tag ) {
				if ( ! preg_match( self::REGEX_STYLESHEET_REL_ATTR, $link_tag ) ) {
					continue;
				}

				if ( preg_match( self::REGEX_HTML_HREF_ATTR, $link_tag, $href_match ) ) {
					$href = trim( (string) $href_match[1] );
					if ( '' !== $href ) {
						$hrefs[] = $href;
					}
				}
			}
		}

		return array_values( array_unique( $hrefs ) );
	}

	/**
	 * Resolve a stylesheet reference URL relative to the page URL.
	 *
	 * @param string $page_url Base page URL.
	 * @param string $href Raw stylesheet href.
	 *
	 * @return string
	 */
	private static function _resolve_url( string $page_url, string $href ): string {
		$href = trim( $href );

		if ( '' === $href ) {
			return '';
		}

		if ( str_starts_with( $href, '#' ) || str_starts_with( strtolower( $href ), 'data:' ) || str_starts_with( strtolower( $href ), 'javascript:' ) ) {
			return '';
		}

		if ( str_starts_with( $href, 'http://' ) || str_starts_with( $href, 'https://' ) ) {
			return $href;
		}

		$page_parts = wp_parse_url( $page_url );
		if ( ! is_array( $page_parts ) || empty( $page_parts['scheme'] ) || empty( $page_parts['host'] ) ) {
			return '';
		}

		$scheme = (string) $page_parts['scheme'];
		$host   = (string) $page_parts['host'];
		$port   = isset( $page_parts['port'] ) ? ':' . (string) $page_parts['port'] : '';
		$origin = $scheme . '://' . $host . $port;

		if ( str_starts_with( $href, '//' ) ) {
			return $scheme . ':' . $href;
		}

		if ( str_starts_with( $href, '/' ) ) {
			return $origin . $href;
		}

		$base_path  = isset( $page_parts['path'] ) ? (string) $page_parts['path'] : '/';
		$base_dir   = preg_replace( self::REGEX_BASE_PATH_TO_DIRECTORY, '/', $base_path );
		$base_dir   = is_string( $base_dir ) ? $base_dir : '/';
		$href_parts = wp_parse_url( $href );
		$href_path  = is_array( $href_parts ) && isset( $href_parts['path'] ) ? (string) $href_parts['path'] : '';

		if ( '' === $href_path && ( str_starts_with( $href, '?' ) || str_starts_with( $href, '#' ) ) ) {
			$resolved_path = $base_path;
		} elseif ( str_starts_with( $href_path, '/' ) ) {
			$resolved_path = $href_path;
		} else {
			$resolved_path = rtrim( $base_dir, '/' ) . '/' . ltrim( $href_path, '/' );
		}

		$resolved_path = self::_normalize_path_segments( '/' . ltrim( $resolved_path, '/' ) );
		$query         = is_array( $href_parts ) && isset( $href_parts['query'] ) ? '?' . (string) $href_parts['query'] : '';
		$fragment      = is_array( $href_parts ) && isset( $href_parts['fragment'] ) ? '#' . (string) $href_parts['fragment'] : '';

		return $origin . $resolved_path . $query . $fragment;
	}

	/**
	 * Normalize dot-segments in a URL path.
	 *
	 * @param string $path Raw path.
	 *
	 * @return string
	 */
	private static function _normalize_path_segments( string $path ): string {
		$segments = explode( '/', $path );
		$stack    = [];

		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				array_pop( $stack );
				continue;
			}

			$stack[] = $segment;
		}

		return '/' . implode( '/', $stack );
	}

	/**
	 * Map hop-GET failures onto the read-web-page REST error contract.
	 *
	 * @param \WP_Error $error Utility or quota error.
	 *
	 * @return \WP_Error
	 */
	private static function _map_web_page_fetch_error( \WP_Error $error ): \WP_Error {
		$code = $error->get_error_code();

		if ( in_array( $code, [ 'rate_limit_exceeded', 'fetch_capacity_exceeded' ], true ) ) {
			return $error;
		}

		return self::response_error(
			'fetch_failed',
			esc_html__( 'Failed to fetch the web page.', 'et_builder_5' ),
			[],
			502
		);
	}

	/**
	 * Acquire logical-call and concurrency leases for one endpoint invocation.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function _acquire_read_web_page_quota() {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return self::_quota_capacity_error();
		}

		$lease_id = self::_create_read_web_page_lease_id();

		return self::_with_read_web_page_quota_lock(
			static function ( $state ) use ( $user_id, $lease_id ) {
				$now   = time();
				$state = self::_prune_read_web_page_quota_state( $state, $now );

				$user_logical = self::_quota_timestamps( $state, 'logical', 'users', (string) $user_id );
				$site_logical = self::_quota_timestamps( $state, 'logical', 'site', '' );

				if ( count( $user_logical ) >= self::WEB_PAGE_QUOTA_USER_LOGICAL || count( $site_logical ) >= self::WEB_PAGE_QUOTA_SITE_LOGICAL ) {
					$retry_after = self::_quota_retry_after_from_timestamps(
						count( $user_logical ) >= self::WEB_PAGE_QUOTA_USER_LOGICAL ? $user_logical : $site_logical,
						$now
					);

					return [
						'error' => self::_quota_rate_error( $retry_after ),
					];
				}

				$user_concurrent = self::_quota_leases( $state, 'users', (string) $user_id );
				$site_concurrent = self::_quota_leases( $state, 'site', '' );

				if ( count( $user_concurrent ) >= self::WEB_PAGE_QUOTA_USER_CONCURRENT || count( $site_concurrent ) >= self::WEB_PAGE_QUOTA_SITE_CONCURRENT ) {
					$retry_after = self::_quota_retry_after_from_leases(
						count( $user_concurrent ) >= self::WEB_PAGE_QUOTA_USER_CONCURRENT ? $user_concurrent : $site_concurrent,
						$now
					);

					return [
						'error' => self::_quota_capacity_error( $retry_after ),
					];
				}

				$user_outbound = self::_quota_timestamps( $state, 'outbound', 'users', (string) $user_id );
				$site_outbound = self::_quota_timestamps( $state, 'outbound', 'site', '' );

				if ( count( $user_outbound ) >= self::WEB_PAGE_QUOTA_USER_OUTBOUND || count( $site_outbound ) >= self::WEB_PAGE_QUOTA_SITE_OUTBOUND ) {
					$retry_after = self::_quota_retry_after_from_timestamps(
						count( $user_outbound ) >= self::WEB_PAGE_QUOTA_USER_OUTBOUND ? $user_outbound : $site_outbound,
						$now
					);

					return [
						'error' => self::_quota_rate_error( $retry_after ),
					];
				}

				$expires_at = $now + self::WEB_PAGE_QUOTA_WINDOW;

				$state['logical']['users'][ (string) $user_id ][]              = $now;
				$state['logical']['site'][]                                   = $now;
				$state['concurrent']['users'][ (string) $user_id ][ $lease_id ] = $expires_at;
				$state['concurrent']['site'][ $lease_id ]                      = $expires_at;

				return [
					'state' => $state,
					'lease' => [
						'user_id'       => $user_id,
						'lease_id'      => $lease_id,
						'user_outbound' => $user_outbound,
						'site_outbound' => $site_outbound,
					],
				];
			}
		);
	}

	/**
	 * Release concurrency leases and persist outbound hops for a finished invocation.
	 *
	 * @param array<string, mixed> $lease         Lease identifiers and initial quota data.
	 * @param int                  $outbound_hops Outbound hops consumed during the invocation.
	 *
	 * @return void
	 */
	private static function _release_read_web_page_quota( array $lease, int $outbound_hops = 0 ): void {
		$user_id  = isset( $lease['user_id'] ) ? (int) $lease['user_id'] : 0;
		$lease_id = isset( $lease['lease_id'] ) ? (string) $lease['lease_id'] : '';

		if ( $user_id <= 0 || '' === $lease_id ) {
			return;
		}

		$mutator = static function ( $state ) use ( $user_id, $lease_id, $outbound_hops ) {
			$now   = time();
			$state = self::_prune_read_web_page_quota_state( $state, $now );

			if ( isset( $state['concurrent']['users'][ (string) $user_id ][ $lease_id ] ) ) {
				unset( $state['concurrent']['users'][ (string) $user_id ][ $lease_id ] );
			}

			if ( isset( $state['concurrent']['site'][ $lease_id ] ) ) {
				unset( $state['concurrent']['site'][ $lease_id ] );
			}

			if ( $outbound_hops > 0 ) {
				if ( ! isset( $state['outbound']['users'][ (string) $user_id ] ) || ! is_array( $state['outbound']['users'][ (string) $user_id ] ) ) {
					$state['outbound']['users'][ (string) $user_id ] = [];
				}
				if ( ! isset( $state['outbound']['site'] ) || ! is_array( $state['outbound']['site'] ) ) {
					$state['outbound']['site'] = [];
				}

				for ( $i = 0; $i < $outbound_hops; $i++ ) {
					$state['outbound']['users'][ (string) $user_id ][] = $now;
					$state['outbound']['site'][]                      = $now;
				}
			}

			return [
				'state' => $state,
				'lease' => true,
			];
		};

		$result = self::_with_read_web_page_quota_lock( $mutator );

		if ( is_wp_error( $result ) ) {
			self::_with_read_web_page_quota_lock( $mutator );
		}
	}

	/**
	 * Run a quota mutation while holding a namespaced advisory lock.
	 *
	 * @param callable $mutator Callback receiving the current state array.
	 *
	 * @return mixed|\WP_Error
	 */
	private static function _with_read_web_page_quota_lock( callable $mutator ) {
		global $wpdb;

		$lock_name = self::_read_web_page_quota_lock_name();

		/**
		 * Filter the advisory-lock result for the read-web-page quota guard.
		 *
		 * Returning null keeps the real lock query. Any other value is treated
		 * as the GET_LOCK result (`1` succeeds; anything else fails closed).
		 * Same-connection GET_LOCK is reentrant, so WPUnit uses this seam to
		 * simulate lock contention without leaking a held lock.
		 *
		 * @since ??
		 *
		 * @param string|null $got_lock  Raw lock result or null to query.
		 * @param string      $lock_name Namespaced lock name.
		 */
		$got_lock = apply_filters( 'et_builder_5_read_web_page_quota_got_lock', null, $lock_name );

		if ( null === $got_lock ) {
			$got_lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, self::WEB_PAGE_QUOTA_LOCK_TIMEOUT ) );
		}

		if ( '1' !== (string) $got_lock ) {
			return self::_quota_capacity_error();
		}

		try {
			$state  = self::_get_read_web_page_quota_state();
			$result = $mutator( $state );

			if ( ! is_array( $result ) ) {
				return self::_quota_capacity_error();
			}

			if ( isset( $result['error'] ) && $result['error'] instanceof \WP_Error ) {
				return $result['error'];
			}

			if ( isset( $result['state'] ) && is_array( $result['state'] ) ) {
				self::_save_read_web_page_quota_state( $result['state'] );
			}

			return $result['lease'] ?? true;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/**
	 * Namespaced advisory-lock name for the current site.
	 *
	 * @return string
	 */
	private static function _read_web_page_quota_lock_name(): string {
		global $wpdb;

		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		return $wpdb->prefix . 'et_b5_rwp_quota_' . $blog_id;
	}

	/**
	 * Load the quota option payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function _get_read_web_page_quota_state(): array {
		$state = get_option( self::WEB_PAGE_QUOTA_OPTION, [] );

		if ( ! is_array( $state ) ) {
			$state = [];
		}

		foreach ( [ 'logical', 'outbound' ] as $bucket ) {
			if ( ! isset( $state[ $bucket ] ) || ! is_array( $state[ $bucket ] ) ) {
				$state[ $bucket ] = [];
			}

			if ( ! isset( $state[ $bucket ]['users'] ) || ! is_array( $state[ $bucket ]['users'] ) ) {
				$state[ $bucket ]['users'] = [];
			}

			if ( ! isset( $state[ $bucket ]['site'] ) || ! is_array( $state[ $bucket ]['site'] ) ) {
				$state[ $bucket ]['site'] = [];
			}
		}

		if ( ! isset( $state['concurrent'] ) || ! is_array( $state['concurrent'] ) ) {
			$state['concurrent'] = [];
		}

		if ( ! isset( $state['concurrent']['users'] ) || ! is_array( $state['concurrent']['users'] ) ) {
			$state['concurrent']['users'] = [];
		}

		if ( ! isset( $state['concurrent']['site'] ) || ! is_array( $state['concurrent']['site'] ) ) {
			$state['concurrent']['site'] = [];
		}

		return $state;
	}

	/**
	 * Persist the quota option without autoload.
	 *
	 * @param array<string, mixed> $state Quota state.
	 *
	 * @return void
	 */
	private static function _save_read_web_page_quota_state( array $state ): void {
		$updated = update_option( self::WEB_PAGE_QUOTA_OPTION, $state, false );

		if ( false === $updated && false === get_option( self::WEB_PAGE_QUOTA_OPTION, false ) ) {
			add_option( self::WEB_PAGE_QUOTA_OPTION, $state, '', 'no' );
		}
	}

	/**
	 * Drop expired timestamps and concurrency leases.
	 *
	 * @param array<string, mixed> $state Quota state.
	 * @param int                  $now   Current unix time.
	 *
	 * @return array<string, mixed>
	 */
	private static function _prune_read_web_page_quota_state( array $state, int $now ): array {
		$cutoff = $now - self::WEB_PAGE_QUOTA_WINDOW;

		foreach ( [ 'logical', 'outbound' ] as $bucket ) {
			$state[ $bucket ]['site'] = self::_filter_quota_timestamps( $state[ $bucket ]['site'], $cutoff );

			foreach ( $state[ $bucket ]['users'] as $user_id => $timestamps ) {
				$state[ $bucket ]['users'][ $user_id ] = self::_filter_quota_timestamps( $timestamps, $cutoff );

				if ( [] === $state[ $bucket ]['users'][ $user_id ] ) {
					unset( $state[ $bucket ]['users'][ $user_id ] );
				}
			}
		}

		$state['concurrent']['site'] = self::_filter_quota_leases(
			is_array( $state['concurrent']['site'] ) ? $state['concurrent']['site'] : [],
			$now
		);

		foreach ( $state['concurrent']['users'] as $user_id => $leases ) {
			$state['concurrent']['users'][ $user_id ] = self::_filter_quota_leases( is_array( $leases ) ? $leases : [], $now );

			if ( [] === $state['concurrent']['users'][ $user_id ] ) {
				unset( $state['concurrent']['users'][ $user_id ] );
			}
		}

		return $state;
	}

	/**
	 * Timestamp list for a quota bucket.
	 *
	 * @param array<string, mixed> $state  Quota state.
	 * @param string               $bucket logical or outbound.
	 * @param string               $scope  users or site.
	 * @param string               $key    User id when scope is users.
	 *
	 * @return int[]
	 */
	private static function _quota_timestamps( array $state, string $bucket, string $scope, string $key ): array {
		if ( 'users' === $scope ) {
			$timestamps = $state[ $bucket ]['users'][ $key ] ?? [];
		} else {
			$timestamps = $state[ $bucket ]['site'] ?? [];
		}

		return self::_filter_quota_timestamps( $timestamps, time() - self::WEB_PAGE_QUOTA_WINDOW );
	}

	/**
	 * Live concurrency leases for a scope.
	 *
	 * @param array<string, mixed> $state Quota state.
	 * @param string               $scope users or site.
	 * @param string               $key   User id when scope is users.
	 *
	 * @return array<string, int>
	 */
	private static function _quota_leases( array $state, string $scope, string $key ): array {
		if ( 'users' === $scope ) {
			$leases = $state['concurrent']['users'][ $key ] ?? [];
		} else {
			$leases = $state['concurrent']['site'] ?? [];
		}

		return self::_filter_quota_leases( is_array( $leases ) ? $leases : [], time() );
	}

	/**
	 * Keep timestamps inside the active window.
	 *
	 * @param mixed $timestamps Raw timestamps.
	 * @param int   $cutoff     Inclusive oldest unix time.
	 *
	 * @return int[]
	 */
	private static function _filter_quota_timestamps( $timestamps, int $cutoff ): array {
		if ( ! is_array( $timestamps ) ) {
			return [];
		}

		$kept = [];

		foreach ( $timestamps as $timestamp ) {
			if ( is_numeric( $timestamp ) && (int) $timestamp > $cutoff ) {
				$kept[] = (int) $timestamp;
			}
		}

		return $kept;
	}

	/**
	 * Keep concurrency leases that have not expired.
	 *
	 * Does not delete a newer live lease while pruning stale ones.
	 *
	 * @param array<string, mixed> $leases Lease map.
	 * @param int                  $now    Current unix time.
	 *
	 * @return array<string, int>
	 */
	private static function _filter_quota_leases( array $leases, int $now ): array {
		$kept = [];

		foreach ( $leases as $lease_id => $expires_at ) {
			if ( ( ! is_string( $lease_id ) && ! is_int( $lease_id ) ) || ! is_numeric( $expires_at ) ) {
				continue;
			}

			if ( (int) $expires_at > $now ) {
				$kept[ (string) $lease_id ] = (int) $expires_at;
			}
		}

		return $kept;
	}

	/**
	 * Seconds until the oldest timestamp leaves the window.
	 *
	 * @param int[] $timestamps Active timestamps.
	 * @param int   $now        Current unix time.
	 *
	 * @return int
	 */
	private static function _quota_retry_after_from_timestamps( array $timestamps, int $now ): int {
		if ( [] === $timestamps ) {
			return 1;
		}

		return max( 1, min( $timestamps ) + self::WEB_PAGE_QUOTA_WINDOW - $now );
	}

	/**
	 * Seconds until the soonest concurrency lease expires.
	 *
	 * @param array<string, int> $leases Live leases.
	 * @param int                $now    Current unix time.
	 *
	 * @return int
	 */
	private static function _quota_retry_after_from_leases( array $leases, int $now ): int {
		if ( [] === $leases ) {
			return 1;
		}

		return max( 1, min( $leases ) - $now );
	}

	/**
	 * Unique concurrency lease identifier.
	 *
	 * @return string
	 */
	private static function _create_read_web_page_lease_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return uniqid( 'rwp', true );
	}

	/**
	 * Rate-limit REST error.
	 *
	 * @param int $retry_after Seconds to wait.
	 *
	 * @return \WP_Error
	 */
	private static function _quota_rate_error( int $retry_after = 1 ): \WP_Error {
		return self::response_error(
			'rate_limit_exceeded',
			esc_html__( 'Too many web page requests. Please try again shortly.', 'et_builder_5' ),
			[
				'retryAfter' => max( 1, $retry_after ),
			],
			429
		);
	}

	/**
	 * Capacity REST error for concurrency or lock failure.
	 *
	 * @param int $retry_after Seconds to wait.
	 *
	 * @return \WP_Error
	 */
	private static function _quota_capacity_error( int $retry_after = 1 ): \WP_Error {
		return self::response_error(
			'fetch_capacity_exceeded',
			esc_html__( 'The site is busy fetching web pages. Please try again shortly.', 'et_builder_5' ),
			[
				'retryAfter' => max( 1, $retry_after ),
			],
			429
		);
	}
}

