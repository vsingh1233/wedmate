<?php
/**
 * Conditions: ConditionsRenderer.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WP_Block;


/**
 * Conditions option custom renderer.
 */
class ConditionsRenderer {

	/**
	 * Runtime-only attrs key used to cache the current block's renderability.
	 *
	 * `render_block_data` computes `evaluate_runtime_renderability()` once (Conditional Display
	 * plus full `disabledOn` for non-interaction targets), then `render_callback` reuses it
	 * through the existing `divi_module_library_register_module_render_block` filter.
	 *
	 * @var string
	 */
	private const SHOULD_RENDER_CACHE_ATTR = '__divi_should_render';

	/**
	 * Per-block markers for whether this render began runtime-asset suppress.
	 *
	 * Paired with `render_block_data` (begin) and `render_block` (end) so non-renderable
	 * Divi containers suppress descendants before WordPress renders inner blocks.
	 *
	 * @var array<int, bool>
	 */
	private static $_began_runtime_asset_suppress_stack = [];

	/**
	 * Register HTML discard and early runtime-asset suppress hooks.
	 *
	 * `divi_module_library_register_module_render_block` still decides HTML discard inside
	 * the module render callback (always-render for styles). Runtime ScriptData/Fonts
	 * suppress must begin on `render_block_data` (top-down) so non-renderable containers,
	 * such as Conditional Display failures and fully `disabledOn` sections, gate
	 * descendants before inner blocks render.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'divi_module_library_register_module_render_block', [ __CLASS__, 'should_render' ], 10, 3 );
		add_filter( 'render_block_data', [ __CLASS__, 'maybe_begin_runtime_asset_suppress' ], 5, 3 );
		add_filter( 'render_block', [ __CLASS__, 'maybe_end_runtime_asset_suppress' ], 5, 3 );
	}

	/**
	 * Begin ScriptData/Fonts suppress before a non-renderable Divi block's subtree renders.
	 *
	 * Must return the parsed block unchanged and must not short-circuit render so styles
	 * still collect for Dynamic Assets / D4 parity.
	 *
	 * @since ??
	 *
	 * @param array         $parsed_block The block being rendered.
	 * @param array         $source_block The unmodified source block.
	 * @param WP_Block|null $parent_block Parent block instance when nested.
	 *
	 * @return array
	 */
	public static function maybe_begin_runtime_asset_suppress( array $parsed_block, array $source_block, $parent_block = null ): array {
		$block_name = $parsed_block['blockName'] ?? '';

		if ( ! is_string( $block_name ) || 0 !== strpos( $block_name, 'divi/' ) ) {
			return $parsed_block;
		}

		$attrs = is_array( $parsed_block['attrs'] ?? null ) ? $parsed_block['attrs'] : [];

		// Resolve defaults + module/group preset renderAttrs before caching. Preset-only
		// Conditional Display / full disabledOn are invisible on raw saved attrs.
		$resolved_attrs = ModuleRegistration::resolve_module_attrs_for_renderability( $block_name, $attrs );
		$should_render  = self::evaluate_runtime_renderability( $resolved_attrs );

		$attrs[ self::SHOULD_RENDER_CACHE_ATTR ] = $should_render;
		$parsed_block['attrs']                   = $attrs;

		if ( ! $should_render ) {
			ModuleRegistration::begin_suppress_runtime_assets();
			self::$_began_runtime_asset_suppress_stack[] = true;
		} else {
			self::$_began_runtime_asset_suppress_stack[] = false;
		}

		return $parsed_block;
	}

	/**
	 * End ScriptData/Fonts suppress after a block that began suppress finishes rendering.
	 *
	 * @since ??
	 *
	 * @param string        $block_content Block HTML.
	 * @param array         $block         Parsed block.
	 * @param WP_Block|null $instance      Block instance.
	 *
	 * @return string
	 */
	public static function maybe_end_runtime_asset_suppress( string $block_content, array $block, $instance = null ): string {
		$block_name = $block['blockName'] ?? '';

		if ( ! is_string( $block_name ) || 0 !== strpos( $block_name, 'divi/' ) ) {
			return $block_content;
		}

		if ( empty( self::$_began_runtime_asset_suppress_stack ) ) {
			return $block_content;
		}

		$began_suppress = array_pop( self::$_began_runtime_asset_suppress_stack );

		if ( true === $began_suppress ) {
			ModuleRegistration::end_suppress_runtime_assets();
		}

		return $block_content;
	}

	/**
	 * Clear pairing stack used by early runtime-asset suppress hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_runtime_asset_suppress_pairing(): void {
		self::$_began_runtime_asset_suppress_stack = [];
	}

	/**
	 * Determines if a module should be rendered based on its conditions.
	 *
	 * This function checks the conditions option of a module and decides whether the module should be rendered or not.
	 *
	 * @since ??
	 *
	 * @param bool      $is_displayable Check if the module is displayable.
	 * @param \WP_Block $block          The block object (required by filter signature, unused in method).
	 * @param array     $attrs          The block attributes.
	 *
	 * @return bool The original block content if the module is conditionally displayable, empty string otherwise.
	 *
	 * @example:
	 * ```php
	 * $attrs = [
	 *    'module' => [
	 *        'decoration' => [
	 *            'conditions' => [
	 *                'desktop' => [
	 *                    'value' => [
	 *                        [
	 *                            'id'                => '10ba038e-48da-487b-96e8-8d3b99b6d18a',
	 *                            'conditionName'     => 'loggedInStatus',
	 *                            'conditionSettings' => [
	 *                                'displayRule'     => 'loggedIn',
	 *                                'adminLabel'      => 'Logged In Status',
	 *                                'enableCondition' => 'on',
	 *                            ],
	 *                            'operator'          => 'OR',
	 *                        ]
	 *                    ],
	 *                ],
	 *            ],
	 *        ],
	 *    ],
	 * ];
	 * $block = new \WP_Block();
	 * $displayable = ConditionsRenderer::should_render($is_displayable, $block, $attrs);
	 *
	 * // Result: true
	 * ```
	 */
	public static function should_render( bool $is_displayable, \WP_Block $block, array $attrs ): bool {
		$cached_should_render = $attrs[ self::SHOULD_RENDER_CACHE_ATTR ] ?? null;

		if ( is_bool( $cached_should_render ) ) {
			return $cached_should_render;
		}

		return self::evaluate_runtime_renderability( $attrs );
	}

	/**
	 * Public wrapper for Conditional Display evaluation outside render callbacks.
	 *
	 * Used by supporting surfaces such as Dynamic Assets font extraction so they can
	 * skip content that would never render on the frontend.
	 *
	 * @since ??
	 *
	 * @param array $attrs The block attributes.
	 *
	 * @return bool True when the block should render.
	 */
	public static function is_displayable_attrs( array $attrs ): bool {
		return self::evaluate_displayability( $attrs );
	}

	/**
	 * Evaluate whether a block should render for runtime output concerns.
	 *
	 * Conditional Display false and fully `disabledOn` non-interaction targets both need
	 * top-down runtime-asset suppress so descendants cannot leak ScriptData or Fonts.
	 *
	 * @since ??
	 *
	 * @param array $attrs The block attributes.
	 *
	 * @return bool True when the block should render.
	 */
	private static function evaluate_runtime_renderability( array $attrs ): bool {
		if ( ! self::evaluate_displayability( $attrs ) ) {
			return false;
		}

		if (
			ModuleRegistration::is_disabled_on_all_breakpoints( $attrs )
			&& ! ModuleRegistration::is_interaction_target( $attrs )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Evaluate Conditional Display without relying on render-callback context.
	 *
	 * @since ??
	 *
	 * @param array $attrs The block attributes.
	 *
	 * @return bool True when the block should render.
	 */
	private static function evaluate_displayability( array $attrs ): bool {
		static $is_display_conditions_enabled = null;

		// We only need to run this filter this once,
		// especially because we dont even send params to this filter.
		if ( null === $is_display_conditions_enabled ) {
			/**
			 * Filters "Display Conditions" functionality to determine whether to enable or disable the functionality or not.
			 *
			 * Useful for disabling/enabling "Display Condition" feature site-wide.
			 *
			 * @since ??
			 *
			 * @param boolean True to enable the functionality, False to disable it.
			 */
			$is_display_conditions_enabled = apply_filters( 'et_is_display_conditions_functionality_enabled', true );
		}

		if ( ! $is_display_conditions_enabled ) {
			return true;
		}

		$conditions_attrs_value = $attrs['module']['decoration']['conditions']['desktop']['value'] ?? [];
		// Check if the block has conditions and if it is displayable,
		// if this module even has conditions enabled.
		if ( ! empty( $conditions_attrs_value ) ) {
			$display_conditions = new Conditions();
			return (bool) $display_conditions->is_displayable( $conditions_attrs_value );
		}

		return true;
	}
}
