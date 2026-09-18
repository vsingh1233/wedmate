<?php
/**
 * SiteSettings class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SiteSettings class.
 *
 * This class contains methods to work with site settings.
 *
 * @since ??
 */
class SiteSettings {

	/**
	 * Get GMT offset string from options.
	 *
	 * @param string $gmt_offset GMT offset.
	 *
	 * @since ??
	 *
	 * @return string GMT offset string in the format of `GMT+HHMM` or `GMT-HHMM`.
	 */
	public static function get_gmt_offset_string( $gmt_offset = '0' ) {

		$gmt_divider       = strpos( $gmt_offset, '-' ) === 0 ? '-' : '+';
		$gmt_offset_hour   = str_pad( abs( (int) $gmt_offset ), 2, '0', STR_PAD_LEFT );
		$gmt_offset_minute = str_pad( ( ( abs( $gmt_offset ) * 100 ) % 100 ) * ( 60 / 100 ), 2, '0', STR_PAD_LEFT );

		return "GMT{$gmt_divider}{$gmt_offset_hour}{$gmt_offset_minute}";
	}

	/**
	 * Get the GMT offset string for the site's active timezone.
	 *
	 * Uses the runtime WordPress timezone so named zones such as `America/New_York`
	 * resolve to the correct current offset (including daylight saving time).
	 *
	 * @since ??
	 *
	 * @return string GMT offset string in the format of `GMT+HHMM` or `GMT-HHMM`.
	 */
	public static function get_gmt_offset_string_from_timezone(): string {
		$timezone      = wp_timezone();
		$offset        = $timezone->getOffset( new \DateTimeImmutable( 'now', $timezone ) );
		$offset_hours  = $offset / HOUR_IN_SECONDS;

		return self::get_gmt_offset_string( (string) $offset_hours );
	}

	/**
	 * Get the configured WordPress timezone city/region string.
	 *
	 * @since ??
	 *
	 * @return string IANA timezone such as `America/New_York`, or an empty string when the site uses a UTC offset only.
	 */
	public static function get_timezone_string(): string {
		return (string) get_option( 'timezone_string', '' );
	}

	/**
	 * Get the current UTC offset for the site's active timezone.
	 *
	 * @since ??
	 *
	 * @return string Offset in `+HH:MM` or `-HH:MM` format.
	 */
	public static function get_timezone_offset_string(): string {
		$timezone = wp_timezone();
		$offset   = $timezone->getOffset( new \DateTimeImmutable( 'now', $timezone ) );
		$hours    = (int) floor( abs( $offset ) / HOUR_IN_SECONDS );
		$minutes  = (int) floor( ( abs( $offset ) % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$sign     = 0 > $offset ? '-' : '+';

		return sprintf( '%s%02d:%02d', $sign, $hours, $minutes );
	}

	/**
	 * Resolve the best available site logo URL.
	 *
	 * Prefers the Divi theme logo option, then the WordPress custom logo, then the site icon.
	 *
	 * @since ??
	 *
	 * @return string Escaped logo URL, or an empty string when none is configured.
	 */
	public static function get_site_logo_url(): string {
		global $shortname;

		$divi_logo         = et_get_option( ( $shortname ?: 'divi' ) . '_logo' );
		$wp_custom_logo_id = get_theme_mod( 'custom_logo' );
		$wp_custom_logo    = $wp_custom_logo_id ? wp_get_attachment_image_url( $wp_custom_logo_id, 'full' ) : '';
		$wp_site_icon      = get_site_icon_url();

		if ( ! empty( $divi_logo ) ) {
			return esc_url( $divi_logo );
		}

		if ( ! empty( $wp_custom_logo ) ) {
			return esc_url( $wp_custom_logo );
		}

		if ( ! empty( $wp_site_icon ) ) {
			return esc_url( $wp_site_icon );
		}

		return '';
	}

	/**
	 * Get the configured site icon URL.
	 *
	 * @since ??
	 *
	 * @return string Escaped site icon URL, or an empty string when none is configured.
	 */
	public static function get_site_icon_url(): string {
		$site_icon_url = get_site_icon_url();

		return ! empty( $site_icon_url ) ? esc_url( $site_icon_url ) : '';
	}

	/**
	 * Get site identity and configuration values for the Visual Builder settings store.
	 *
	 * @since ??
	 *
	 * @return array{
	 *   title: string,
	 *   description: string,
	 *   logoUrl: string,
	 *   siteIconUrl: string,
	 *   frontPageId: int,
	 *   postsPageId: int,
	 *   showOnFront: string,
	 *   timezoneString: string,
	 *   timezoneOffset: string,
	 *   language: string,
	 *   activeTheme: string,
	 *   activeThemeStylesheet: string,
	 * }
	 */
	public static function get_identity_settings(): array {
		$theme = wp_get_theme();

		return [
			'title'                 => get_bloginfo( 'name' ),
			'description'           => get_bloginfo( 'description' ),
			'logoUrl'               => self::get_site_logo_url(),
			'siteIconUrl'           => self::get_site_icon_url(),
			'frontPageId'           => (int) get_option( 'page_on_front', 0 ),
			'postsPageId'           => (int) get_option( 'page_for_posts', 0 ),
			'showOnFront'           => (string) get_option( 'show_on_front', 'posts' ),
			'timezoneString'        => self::get_timezone_string(),
			'timezoneOffset'        => self::get_timezone_offset_string(),
			'language'              => get_locale(),
			'activeTheme'           => $theme->get( 'Name' ),
			'activeThemeStylesheet' => $theme->get_stylesheet(),
		];
	}
}
