<?php
/**
 * SVG sanitizer utility.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SvgSanitizer class.
 *
 * Applies a strict allowlist for inline SVG markup.
 *
 * @since ??
 */
class SvgSanitizer {
	/**
	 * Same-document fragment reference pattern for `<use>` href attributes.
	 *
	 * Regex test: https://regex101.com/r/83bkyN/1.
	 */
	private const SAME_DOCUMENT_FRAGMENT_REFERENCE_PATTERN = '/^#[A-Za-z_][\w:.-]*$/';

	/**
	 * CSS `url()` that points only at a same-document paint server.
	 *
	 * Regex test: https://regex101.com/r/TMaYzJ/1.
	 */
	private const CSS_FRAGMENT_PAINT_SERVER_PATTERN = '/^url\s*\(\s*([\'"]?)(#[A-Za-z_][\w:.-]*)\1\s*\)$/i';

	/**
	 * Temporary carrier used to move fill styles past `wp_kses` / `safecss_filter_attr`.
	 *
	 * WordPress before 7.1 drops `fill:url(#…)` because `url(` fails the safecss test
	 * string. This attribute is allowlisted only for the sanitizer kses pass and is
	 * converted back to `style` afterward.
	 */
	private const STYLE_FILL_CARRIER_ATTRIBUTE = 'data-et-svg-fill';

	/**
	 * Tag-to-attribute map that requires same-document fragment references.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const FRAGMENT_REFERENCE_ATTRIBUTES_BY_TAG = [
		'use'      => [ 'href', 'xlink:href' ],
		'textpath' => [ 'href', 'xlink:href' ],
	];

	/**
	 * Sanitize inline SVG markup.
	 *
	 * @param string $markup Raw SVG markup.
	 *
	 * @return string
	 */
	public static function sanitize_markup( string $markup ): string {
		$markup = trim( $markup );

		if ( '' === $markup || false === stripos( $markup, '<svg' ) ) {
			return '';
		}

		// Materialize simple class CSS paint rules onto presentation attrs before
		// wp_kses strips `<style>` tags.
		$markup = SvgClassStyleExpander::expand( $markup );

		$prepared_markup = self::_stash_fill_style_attributes( $markup );
		$allowed_html    = SvgAllowedList::get_allowed_svg_html();

		if ( $prepared_markup !== $markup ) {
			foreach ( $allowed_html as $tag_name => $attributes ) {
				$normalized_attributes = array_change_key_case( $attributes, CASE_LOWER );

				if ( isset( $normalized_attributes['style'] ) ) {
					$allowed_html[ $tag_name ][ self::STYLE_FILL_CARRIER_ATTRIBUTE ] = true;
				}
			}
		}

		$sanitized_markup = wp_kses( $prepared_markup, wp_kses_array_lc( $allowed_html ) );

		// Apply a value-level guard for fragment reference attributes after allowlisting.
		// This prevents external/non-fragment references from surviving sanitization.
		$sanitized_markup = self::_sanitize_fragment_reference_attributes( $sanitized_markup );

		// Keep only `fill` inside leftover `style` attributes (per-path color).
		// Other declarations (stroke, opacity, layout CSS) are dropped.
		return self::_sanitize_style_attributes( $sanitized_markup );
	}

	/**
	 * Extract a fill-only CSS declaration from an inline style value.
	 *
	 * @param string $style_value Style attribute value.
	 *
	 * @return string Fill declaration or empty string when fill is absent.
	 */
	private static function _extract_fill_style_declaration( string $style_value ): string {
		if ( self::_contains_css_escape_or_comment( $style_value ) ) {
			return '';
		}

		$declarations     = explode( ';', $style_value );
		$fill_declaration = '';

		foreach ( $declarations as $declaration ) {
			$declaration = trim( $declaration );

			if ( '' === $declaration ) {
				continue;
			}

			$parts = explode( ':', $declaration, 2 );

			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$property = strtolower( trim( $parts[0] ) );
			$value    = trim( $parts[1] );

			if ( 'fill' === $property && '' !== $value && self::_is_safe_fill_style_value( $value ) ) {
				$fill_declaration = 'fill:' . $value;
			}
		}

		return $fill_declaration;
	}

	/**
	 * Whether a CSS value contains comment or escape tokens.
	 *
	 * @param string $value CSS value or declaration list.
	 *
	 * @return bool
	 */
	private static function _contains_css_escape_or_comment( string $value ): bool {
		return false !== strpos( $value, '\\' )
			|| false !== strpos( $value, '/*' )
			|| false !== strpos( $value, '*/' );
	}

	/**
	 * Whether a fill value is safe to keep inside a sanitizer `style` attribute.
	 *
	 * Non-url colors are kept. `url()` is limited to same-document paint servers so
	 * Visual Builder and `wp_kses` stay aligned without allowing external CSS fetches.
	 *
	 * CSS comments and escapes that hide `url()` (comment-split functions,
	 * `\75rl(…)`) still parse as `url()` in the browser, so those tokens are
	 * rejected before the literal `url(` check.
	 *
	 * @param string $value Fill declaration value.
	 *
	 * @return bool
	 */
	private static function _is_safe_fill_style_value( string $value ): bool {
		if ( self::_contains_css_escape_or_comment( $value ) ) {
			return false;
		}

		// Regex test: https://regex101.com/r/fORnAd/1.
		if ( 1 !== preg_match( '/url\s*\(/i', $value ) ) {
			return true;
		}

		if ( 1 !== preg_match( self::CSS_FRAGMENT_PAINT_SERVER_PATTERN, $value, $matches ) ) {
			return false;
		}

		return 1 === preg_match( self::SAME_DOCUMENT_FRAGMENT_REFERENCE_PATTERN, $matches[2] );
	}

	/**
	 * Whether the sanitizer allowlist permits a `style` attribute on a tag.
	 *
	 * @param string $tag_name Element tag name.
	 *
	 * @return bool
	 */
	private static function _tag_allows_style_attribute( string $tag_name ): bool {
		$allowed_html = SvgAllowedList::get_allowed_svg_html();
		$normalized   = strtolower( $tag_name );

		foreach ( $allowed_html as $allowed_tag => $attributes ) {
			if ( strtolower( $allowed_tag ) !== $normalized ) {
				continue;
			}

			$attributes = array_change_key_case( $attributes, CASE_LOWER );

			return isset( $attributes['style'] );
		}

		return false;
	}

	/**
	 * Remove style and fill-carrier attributes when XML parsing cannot run.
	 *
	 * Fill-only filtering needs a DOM. Returning the original markup would let
	 * `wp_kses` keep safecss-filtered non-fill CSS (layout, background urls).
	 *
	 * @param string $markup SVG markup.
	 *
	 * @return string Markup without style or fill-carrier attributes.
	 */
	private static function _strip_style_and_carrier_attributes( string $markup ): string {
		// Regex test: https://regex101.com/r/cEvZNa/1.
		$stripped = preg_replace(
			'/\s(?:style|' . preg_quote( self::STYLE_FILL_CARRIER_ATTRIBUTE, '/' ) . ')\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
			'',
			$markup
		);

		return is_string( $stripped ) ? $stripped : '';
	}

	/**
	 * Move fill-only styles onto a temporary carrier before `wp_kses`.
	 *
	 * @param string $markup Raw SVG markup.
	 *
	 * @return string Markup with fill styles stashed, or style-stripped markup on parse failure.
	 */
	private static function _stash_fill_style_attributes( string $markup ): string {
		if ( '' === $markup || false === stripos( $markup, 'style=' ) ) {
			return $markup;
		}

		$internal_errors = libxml_use_internal_errors( true );
		$document        = new \DOMDocument();
		$loaded          = $document->loadXML( $markup, LIBXML_NONET | LIBXML_NOBLANKS );

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return self::_strip_style_and_carrier_attributes( $markup );
		}

		$elements = $document->getElementsByTagName( '*' );

		for ( $index = 0; $index < $elements->length; $index++ ) {
			$node = $elements->item( $index );

			if ( ! ( $node instanceof \DOMElement ) ) {
				continue;
			}

			$node->removeAttribute( self::STYLE_FILL_CARRIER_ATTRIBUTE );

			// Match Visual Builder: do not stash fill styles onto tags whose allowlist omits `style`.
			if ( ! self::_tag_allows_style_attribute( $node->tagName ) ) {
				continue;
			}

			if ( ! $node->hasAttribute( 'style' ) ) {
				continue;
			}

			$fill_declaration = self::_extract_fill_style_declaration( $node->getAttribute( 'style' ) );
			$node->removeAttribute( 'style' );

			if ( '' !== $fill_declaration ) {
				$node->setAttribute( self::STYLE_FILL_CARRIER_ATTRIBUTE, $fill_declaration );
			}
		}

		$serialized_markup = $document->saveXML( $document->documentElement );

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		return is_string( $serialized_markup ) ? $serialized_markup : self::_strip_style_and_carrier_attributes( $markup );
	}

	/**
	 * Restrict sanitizer-owned style attributes to a fill declaration only.
	 *
	 * @param string $markup Sanitized SVG markup.
	 *
	 * @return string
	 */
	private static function _sanitize_style_attributes( string $markup ): string {
		$has_style   = false !== stripos( $markup, 'style=' );
		$has_carrier = false !== stripos( $markup, self::STYLE_FILL_CARRIER_ATTRIBUTE );

		if ( '' === $markup || ( ! $has_style && ! $has_carrier ) ) {
			return $markup;
		}

		$internal_errors = libxml_use_internal_errors( true );
		$document        = new \DOMDocument();
		$loaded          = $document->loadXML( $markup, LIBXML_NONET | LIBXML_NOBLANKS );

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return self::_strip_style_and_carrier_attributes( $markup );
		}

		$elements = $document->getElementsByTagName( '*' );

		for ( $index = 0; $index < $elements->length; $index++ ) {
			$node = $elements->item( $index );

			if ( ! ( $node instanceof \DOMElement ) ) {
				continue;
			}

			$allows_style = self::_tag_allows_style_attribute( $node->tagName );
			$style_value  = $node->hasAttribute( 'style' ) ? $node->getAttribute( 'style' ) : '';

			if ( $node->hasAttribute( self::STYLE_FILL_CARRIER_ATTRIBUTE ) ) {
				$carrier_value = $node->getAttribute( self::STYLE_FILL_CARRIER_ATTRIBUTE );
				$node->removeAttribute( self::STYLE_FILL_CARRIER_ATTRIBUTE );

				if ( $allows_style && '' === $style_value ) {
					$style_value = $carrier_value;
				}
			}

			if ( ! $allows_style ) {
				$node->removeAttribute( 'style' );
				continue;
			}

			if ( '' === $style_value ) {
				continue;
			}

			$fill_declaration = self::_extract_fill_style_declaration( $style_value );

			if ( '' === $fill_declaration ) {
				$node->removeAttribute( 'style' );
			} else {
				$node->setAttribute( 'style', $fill_declaration );
			}
		}

		$serialized_markup = $document->saveXML( $document->documentElement );

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		return is_string( $serialized_markup ) ? $serialized_markup : self::_strip_style_and_carrier_attributes( $markup );
	}

	/**
	 * Restrict fragment reference attributes to same-document fragments.
	 *
	 * @param string $markup Sanitized SVG markup.
	 *
	 * @return string
	 */
	private static function _sanitize_fragment_reference_attributes( string $markup ): string {
		$has_relevant_tag = false;

		foreach ( array_keys( self::FRAGMENT_REFERENCE_ATTRIBUTES_BY_TAG ) as $tag_name ) {
			if ( false !== stripos( $markup, '<' . $tag_name ) ) {
				$has_relevant_tag = true;
				break;
			}
		}

		if ( '' === $markup || ! $has_relevant_tag ) {
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

		foreach ( self::FRAGMENT_REFERENCE_ATTRIBUTES_BY_TAG as $tag_name => $reference_attributes ) {
			if ( false === stripos( $markup, '<' . $tag_name ) ) {
				continue;
			}

			$nodes = $document->getElementsByTagName( $tag_name );

			if ( 0 === $nodes->length && 'textpath' === $tag_name ) {
				$nodes = $document->getElementsByTagName( 'textPath' );
			}

			for ( $index = 0; $index < $nodes->length; $index++ ) {
				$node = $nodes->item( $index );

				if ( null === $node || ! $node->hasAttributes() ) {
					continue;
				}

				$attributes_to_remove = [];

				foreach ( $node->attributes as $attribute ) {
					$attribute_name = strtolower( $attribute->nodeName );

					if ( ! in_array( $attribute_name, $reference_attributes, true ) ) {
						continue;
					}

					$attribute_value = trim( (string) $attribute->nodeValue );
					$is_fragment_ref = 1 === preg_match( self::SAME_DOCUMENT_FRAGMENT_REFERENCE_PATTERN, $attribute_value );

					if ( ! $is_fragment_ref ) {
						$attributes_to_remove[] = $attribute->nodeName;
					}
				}

				foreach ( $attributes_to_remove as $attribute_name ) {
					$node->removeAttribute( $attribute_name );
				}
			}
		}

		$serialized_markup = $document->saveXML( $document->documentElement );

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		return is_string( $serialized_markup ) ? $serialized_markup : $markup;
	}
}
