<?php
/**
 * Module: DynamicContentFixes class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Security\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;

/**
 * Module: DynamicContentFixes class.
 *
 * This class provides a set of security fixes for dynamic content.
 *
 * @since ??
 */
class DynamicContentFixes {
	/**
	 * Disable html for dynamic content.
	 *
	 * We need to disable the enable_html flag from the dynamic content item,
	 * and then re-encode it and put the new value back in the post content.
	 *
	 * @since ??
	 *
	 * @param array $data  An array of slashed post data.
	 *
	 * @return array $data Modified post data.
	 */
	public static function disable_html( $data ) {
		$data = self::disable_html_tokens( $data );

		return self::disable_html_legacy_json( $data );
	}

	/**
	 * Disable enable_html in dynamic content tokens.
	 *
	 * @since ??
	 *
	 * @param array $data An array of slashed post data.
	 *
	 * @return array
	 */
	public static function disable_html_tokens( array $data ): array {
		$post_content = wp_unslash( $data['post_content'] );
		$replace      = [];

		foreach ( self::get_token_matches( $post_content ) as $token ) {
			$original = $token['body'];
			$value    = self::decode_token_value( $original );
			$flag     = $value['value']['settings']['enable_html'] ?? null;

			if (
				'content' !== ( $value['type'] ?? null )
				|| ! is_string( $flag )
				|| 'on' !== wp_kses_post( $flag )
			) {
				continue;
			}

			$value['value']['settings']['enable_html'] = 'off';

			$serialized                  = self::serialize_token_value( $value, $original );
			$replace[ $token['wrapper'] ] = $token['prefix'] . $serialized . $token['suffix'];
		}

		$data['post_content'] = wp_slash( strtr( $post_content, $replace ) );

		return $data;
	}

	/**
	 * Find directly readable and block-escaped Dynamic Content tokens.
	 *
	 * @since ??
	 *
	 * @param string $content Content containing Dynamic Content tokens.
	 *
	 * @return array
	 */
	private static function get_token_matches( string $content ): array {
		$tokens = [];

		foreach ( DynamicData::get_variable_values( $content ) as $body ) {
			$wrapper            = '$variable(' . $body . ')$';
			$tokens[ $wrapper ] = [
				'wrapper' => '$variable(' . $body . ')$',
				'prefix'  => '$variable(',
				'body'    => $body,
				'suffix'  => ')$',
			];
		}

		$block_escaped_pattern = '/((?:\$|\\\\u0024)(?:v|\\\\u0076)(?:a|\\\\u0061)(?:r|\\\\u0072)(?:i|\\\\u0069)(?:a|\\\\u0061)(?:b|\\\\u0062)(?:l|\\\\u006[cC])(?:e|\\\\u0065)(?:\(|\\\\u0028))(.+?)((?<!\\\\)(?:\)|\\\\u0029)(?:\$|\\\\u0024))/';

		preg_match_all( $block_escaped_pattern, $content, $block_escaped_matches, PREG_SET_ORDER );

		foreach ( $block_escaped_matches as $match ) {
			$tokens[ $match[0] ] = [
				'wrapper' => $match[0],
				'prefix'  => $match[1],
				'body'    => $match[2],
				'suffix'  => $match[3],
			];
		}

		return array_values( $tokens );
	}

	/**
	 * Decode a token before and after block-attribute normalization.
	 *
	 * WordPress block parsing decodes the outer attribute string before the
	 * Dynamic Content decoder runs. Mirror that extra JSON string layer when
	 * the token is not directly readable.
	 *
	 * @since ??
	 *
	 * @param string $original Original token body.
	 *
	 * @return array
	 */
	private static function decode_token_value( string $original ): array {
		$value = DynamicData::get_data_value( $original );

		if ( [] !== $value ) {
			return $value;
		}

		$block_attribute_value = json_decode( '"' . $original . '"' );

		if ( ! is_string( $block_attribute_value ) ) {
			return [];
		}

		return DynamicData::get_data_value( $block_attribute_value );
	}

	/**
	 * Serialize a normalized dynamic content token value.
	 *
	 * @since ??
	 *
	 * @param array  $value    Decoded dynamic content value.
	 * @param string $original Original token body.
	 *
	 * @return string
	 */
	private static function serialize_token_value( array $value, string $original ): string {
		$json = wp_json_encode( $value );

		if ( ! is_string( $json ) ) {
			throw new \UnexpectedValueException( 'Unable to encode a safe Dynamic Content token.' );
		}

		$json       = self::escape_block_unsafe_characters( $json );
		$serialized = self::serialize_in_original_family( $json, $original );

		if ( $value === self::decode_token_value( $serialized ) ) {
			return $serialized;
		}

		// Keep this inverse paired with DynamicData::construct_json_string().
		$serialized = self::serialize_with_unicode_quotes( $json );

		if ( $value === self::decode_token_value( $serialized ) ) {
			return $serialized;
		}

		throw new \UnexpectedValueException( 'Unable to serialize a safe Dynamic Content token.' );
	}

	/**
	 * Escape characters that can break a serialized block comment.
	 *
	 * This matches the block-safe replacements in serialize_block_attributes().
	 *
	 * @since ??
	 *
	 * @param string $json Encoded JSON.
	 *
	 * @return string
	 */
	private static function escape_block_unsafe_characters( string $json ): string {
		return str_replace(
			[ '--', '<', '>', '&' ],
			[ '\u002d\u002d', '\u003c', '\u003e', '\u0026' ],
			$json
		);
	}

	/**
	 * Serialize JSON in the original token's structural quote family.
	 *
	 * @since ??
	 *
	 * @param string $json     Encoded JSON.
	 * @param string $original Original token body.
	 *
	 * @return string
	 */
	private static function serialize_in_original_family( string $json, string $original ): string {
		$json_whitespace_length = strspn( $original, " \t\n\r" );
		$json_whitespace        = substr( $original, 0, $json_whitespace_length );
		$inspection             = substr( $original, $json_whitespace_length );

		if ( 0 === strpos( $inspection, '{&quot;' ) ) {
			return $json_whitespace . str_replace( '"', '&quot;', $json );
		}

		if ( 0 === strpos( $inspection, '{\u0022' ) ) {
			return $json_whitespace . self::serialize_with_unicode_quotes( $json );
		}

		if ( 0 === strpos( $inspection, '{\"' ) ) {
			return $json_whitespace . self::serialize_with_unicode_quotes( $json );
		}

		if ( 0 === strpos( $inspection, '{"' ) ) {
			return $json_whitespace . $json;
		}

		return $json_whitespace . self::serialize_with_unicode_quotes( $json );
	}

	/**
	 * Serialize JSON with Builder 5 Unicode structural quotes.
	 *
	 * @since ??
	 *
	 * @param string $json Encoded JSON.
	 *
	 * @return string
	 */
	private static function serialize_with_unicode_quotes( string $json ): string {
		$serialized = '';
		$length     = strlen( $json );

		for ( $index = 0; $index < $length; $index++ ) {
			if ( '"' !== $json[ $index ] ) {
				$serialized .= $json[ $index ];
				continue;
			}

			$backslash_count = 0;

			for ( $offset = $index - 1; 0 <= $offset && '\\' === $json[ $offset ]; $offset-- ) {
				$backslash_count++;
			}

			if ( 1 === $backslash_count % 2 ) {
				$serialized  = substr( $serialized, 0, -1 );
				$serialized .= '\u005c\u0022';
				continue;
			}

			$serialized .= '\u0022';
		}

		return $serialized;
	}

	/**
	 * Disable enable_html in legacy raw JSON dynamic payloads.
	 *
	 * @since ??
	 *
	 * @param array $data An array of slashed post data.
	 *
	 * @return array
	 */
	public static function disable_html_legacy_json( array $data ): array {
		// Test Regex: https://regex101.com/r/ogR9t2/1.
		$legacy_json_pattern = '/"enable_html"\s*:\s*"on"/';
		$content             = wp_unslash( $data['post_content'] );
		$has_legacy_json     = 1 === preg_match( $legacy_json_pattern, $content );

		// Replace legacy enable_html flag.
		if ( $has_legacy_json ) {
			$content              = preg_replace( '/"enable_html"\s*:\s*"on"/', '"enable_html":"off"', $content );
			$data['post_content'] = wp_slash( $content );
		}

		return $data;
	}
}
