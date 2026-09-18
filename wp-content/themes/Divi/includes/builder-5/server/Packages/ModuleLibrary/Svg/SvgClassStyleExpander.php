<?php
/**
 * Expands simple SVG class stylesheet paint rules onto presentation attributes.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SvgClassStyleExpander class.
 *
 * Converts narrow `.class { paint: value }` rules into allowlisted presentation
 * attributes before the SVG sanitizer strips `<style>` tags.
 *
 * @since ??
 */
class SvgClassStyleExpander {
	/**
	 * Paint presentation attributes that may be expanded from simple class CSS.
	 *
	 * Keep this list aligned with VB `expand-svg-class-styles`.
	 *
	 * @var array<string, bool>
	 */
	private const EXPANDABLE_PAINT_PROPERTIES = [
		'fill'              => true,
		'stroke'            => true,
		'stroke-width'      => true,
		'fill-opacity'      => true,
		'stroke-opacity'    => true,
		'stroke-linecap'    => true,
		'stroke-linejoin'   => true,
		'stroke-miterlimit' => true,
		'stroke-dasharray'  => true,
		'stroke-dashoffset' => true,
		'opacity'           => true,
	];

	/**
	 * Expand safe class-based paint CSS onto missing presentation attributes.
	 *
	 * @param string $markup Raw SVG markup.
	 *
	 * @return string Markup with paint attrs expanded when possible.
	 */
	public static function expand( string $markup ): string {
		if ( '' === $markup || false === stripos( $markup, '<style' ) ) {
			return $markup;
		}

		$internal_errors = libxml_use_internal_errors( true );
		$document        = new \DOMDocument();
		$loaded          = $document->loadXML( $markup, LIBXML_NONET | LIBXML_NOBLANKS );

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return $markup;
		}

		$style_text = self::_collect_style_text( $document );

		if ( '' === trim( $style_text ) ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return $markup;
		}

		$applications = self::_parse_class_paint_applications( $style_text );

		if ( empty( $applications ) ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return $markup;
		}

		$did_expand = self::_apply_applications( $document, $applications );

		if ( ! $did_expand ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return $markup;
		}

		$serialized_markup = $document->saveXML( $document->documentElement );

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		return is_string( $serialized_markup ) ? $serialized_markup : $markup;
	}

	/**
	 * Collect stylesheet text from all style elements.
	 *
	 * @param \DOMDocument $document SVG document.
	 *
	 * @return string
	 */
	private static function _collect_style_text( \DOMDocument $document ): string {
		$style_nodes = $document->getElementsByTagName( 'style' );
		$chunks      = [];

		for ( $index = 0; $index < $style_nodes->length; $index++ ) {
			$style_node = $style_nodes->item( $index );

			if ( null === $style_node ) {
				continue;
			}

			$chunks[] = (string) $style_node->textContent;
		}

		return implode( "\n", $chunks );
	}

	/**
	 * Remove at-rule blocks so nested `.class` paints are not expanded.
	 *
	 * Brace-balanced `@media` / `@supports` blocks and semicolon-terminated
	 * at-rules such as `@import` are dropped. Top-level class rules after an
	 * at-block are preserved.
	 *
	 * @param string $css_text Stylesheet text without comments.
	 *
	 * @return string
	 */
	private static function _strip_at_rule_blocks( string $css_text ): string {
		$result = '';
		$length = strlen( $css_text );
		$index  = 0;

		while ( $index < $length ) {
			if ( '@' !== $css_text[ $index ] ) {
				$result .= $css_text[ $index ];
				$index++;
				continue;
			}

			$open_brace = strpos( $css_text, '{', $index );
			$semicolon  = strpos( $css_text, ';', $index );

			if ( false !== $semicolon && ( false === $open_brace || $semicolon < $open_brace ) ) {
				$index = $semicolon + 1;
				continue;
			}

			if ( false === $open_brace ) {
				break;
			}

			$depth  = 1;
			$cursor = $open_brace + 1;

			while ( $cursor < $length && 0 < $depth ) {
				if ( '{' === $css_text[ $cursor ] ) {
					$depth++;
				} elseif ( '}' === $css_text[ $cursor ] ) {
					$depth--;
				}

				$cursor++;
			}

			$index = $cursor;
		}

		return $result;
	}

	/**
	 * Parse simple `.class` paint rules into ordered applications.
	 *
	 * @param string $css_text Stylesheet text.
	 *
	 * @return array<int, array{className: string, property: string, value: string}>
	 */
	private static function _parse_class_paint_applications( string $css_text ): array {
		$applications     = [];
		$without_comments = preg_replace( '/\/\*.*?\*\//s', '', $css_text );

		if ( ! is_string( $without_comments ) ) {
			return [];
		}

		$without_at_rules = self::_strip_at_rule_blocks( $without_comments );

		if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $without_at_rules, $rule_matches, PREG_SET_ORDER ) ) {
			return [];
		}

		foreach ( $rule_matches as $rule_match ) {
			$selector_list     = $rule_match[1];
			$declaration_block = $rule_match[2];
			$declarations      = [];

			foreach ( explode( ';', $declaration_block ) as $declaration ) {
				$separator_index = strpos( $declaration, ':' );

				if ( false === $separator_index ) {
					continue;
				}

				$property = strtolower( trim( substr( $declaration, 0, $separator_index ) ) );
				$value    = self::_normalize_paint_value( substr( $declaration, $separator_index + 1 ) );

				if ( ! isset( self::EXPANDABLE_PAINT_PROPERTIES[ $property ] ) || ! self::_is_safe_paint_value( $value ) ) {
					continue;
				}

				$declarations[] = [
					'property' => $property,
					'value'    => $value,
				];
			}

			if ( empty( $declarations ) ) {
				continue;
			}

			foreach ( explode( ',', $selector_list ) as $raw_selector ) {
				$selector = trim( $raw_selector );

				if ( 1 !== preg_match( '/^\.([A-Za-z_][\w-]*)$/', $selector, $class_match ) ) {
					continue;
				}

				$class_name = $class_match[1];

				foreach ( $declarations as $declaration ) {
					$applications[] = [
						'className' => $class_name,
						'property'  => $declaration['property'],
						'value'     => $declaration['value'],
					];
				}
			}
		}

		return $applications;
	}

	/**
	 * Apply ordered paint applications onto matching elements.
	 *
	 * @param \DOMDocument $document     SVG document.
	 * @param array        $applications Ordered applications.
	 *
	 * @return bool Whether any presentation attribute was written.
	 */
	private static function _apply_applications( \DOMDocument $document, array $applications ): bool {
		// Use getElementsByTagName so default-namespaced SVG nodes and the svg root are included.
		$elements   = $document->getElementsByTagName( '*' );
		$did_expand = false;

		for ( $index = 0; $index < $elements->length; $index++ ) {
			$element = $elements->item( $index );

			if ( ! $element instanceof \DOMElement || ! $element->hasAttribute( 'class' ) ) {
				continue;
			}

			$class_attribute = trim( (string) $element->getAttribute( 'class' ) );

			if ( '' === $class_attribute ) {
				continue;
			}

			$class_tokens      = preg_split( '/\s+/', $class_attribute );
			$element_classes   = array_fill_keys( is_array( $class_tokens ) ? $class_tokens : [], true );
			$merged_properties = [];

			foreach ( $applications as $application ) {
				if ( isset( $element_classes[ $application['className'] ] ) ) {
					$merged_properties[ $application['property'] ] = $application['value'];
				}
			}

			foreach ( $merged_properties as $property => $value ) {
				if ( ! $element->hasAttribute( $property ) ) {
					$element->setAttribute( $property, $value );
					$did_expand = true;
				}
			}
		}

		return $did_expand;
	}

	/**
	 * Normalize a CSS declaration value for expansion.
	 *
	 * @param string $value Raw CSS declaration value.
	 *
	 * @return string
	 */
	private static function _normalize_paint_value( string $value ): string {
		$value = trim( $value );
		$value = preg_replace( '/\s*!important\s*$/i', '', $value );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Whether a CSS paint value is safe to copy onto a presentation attribute.
	 *
	 * @param string $value Normalized CSS declaration value.
	 *
	 * @return bool
	 */
	private static function _is_safe_paint_value( string $value ): bool {
		if ( '' === $value ) {
			return false;
		}

		$lower = strtolower( $value );

		if (
			false !== strpos( $lower, 'url(' )
			|| false !== strpos( $lower, 'expression' )
			|| false !== strpos( $lower, 'javascript:' )
			|| false !== strpos( $lower, 'behavior' )
			|| false !== strpos( $value, '@' )
			|| false !== strpos( $value, '\\' )
			|| false !== strpos( $value, '"' )
			|| false !== strpos( $value, "'" )
		) {
			return false;
		}

		return 1 === preg_match( '/^[a-z0-9#.,%()\/\s+-]+$/i', $value );
	}
}
