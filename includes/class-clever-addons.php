<?php
/**
 * Add-on registry for Clever Forms.
 *
 * Core features are never license-gated here. Commercial add-ons are separate
 * plugins that register themselves at runtime and manage their own licensing,
 * support and update entitlement.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class Clever_Forms_Addons {
	private static array $registered = array();

	public static function register( array $addon ): bool {
		$slug = sanitize_key( (string) ( $addon['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return false;
		}
		$defaults                  = array(
			'name'        => $slug,
			'version'     => '',
			'description' => '',
			'category'    => 'integration',
			'status'      => 'active',
			'url'         => '',
		);
		$addon                     = wp_parse_args( $addon, $defaults );
		$addon['slug']             = $slug;
		$addon['name']             = sanitize_text_field( (string) $addon['name'] );
		$addon['version']          = sanitize_text_field( (string) $addon['version'] );
		$addon['description']      = sanitize_text_field( (string) $addon['description'] );
		$addon['category']         = sanitize_key( (string) $addon['category'] );
		$addon['status']           = sanitize_key( (string) $addon['status'] );
		$addon['url']              = esc_url_raw( (string) $addon['url'] );
		self::$registered[ $slug ] = $addon;
		return true;
	}

	public static function all(): array {
		$addons = self::$registered;
		/**
		 * Filters add-ons registered with Clever Forms.
		 *
		 * @param array $addons Registered add-on metadata keyed by slug.
		 */
		return (array) apply_filters( 'clever_forms_registered_addons', $addons );
	}

	public static function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$addons = self::all();
		echo '<div class="wrap clever-addons"><h1>' . esc_html__( 'Clever Forms Add-Ons', 'clever-forms' ) . '</h1>';
		echo '<p>' . esc_html__( 'Clever Forms Core includes the complete form-building foundation. Optional integrations and specialized workflows can be installed as separate add-on plugins.', 'clever-forms' ) . '</p>';
		if ( ! $addons ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No external Clever Forms add-ons are currently active.', 'clever-forms' ) . '</p></div>';
		} else {
			echo '<div class="clever-addon-grid">';
			foreach ( $addons as $addon ) {
				echo '<div class="clever-addon-card">';
				echo '<div class="clever-addon-toggle"><strong>' . esc_html( (string) $addon['name'] ) . '</strong></div>';
				if ( ! empty( $addon['version'] ) ) {
					echo '<p><small>' . esc_html( sprintf( __( 'Version %s', 'clever-forms' ), (string) $addon['version'] ) ) . '</small></p>';
				}
				if ( ! empty( $addon['description'] ) ) {
					echo '<p>' . esc_html( (string) $addon['description'] ) . '</p>';
				}
				echo '<p><strong>' . esc_html( ucfirst( (string) $addon['status'] ) ) . '</strong></p>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '<hr><h2>' . esc_html__( 'Recommended Add-On Categories', 'clever-forms' ) . '</h2>';
		echo '<p>' . esc_html__( 'For a competitive production ecosystem, keep these capabilities outside Core when they require external credentials, specialized infrastructure, or ongoing vendor maintenance:', 'clever-forms' ) . '</p>';
		echo '<ul class="ul-disc">';
		$items = array(
			__( 'CRM connectors, including Salesforce and other enterprise CRMs', 'clever-forms' ),
			__( 'Payment gateways and subscription billing', 'clever-forms' ),
			__( 'Advanced address autocomplete and geolocation providers', 'clever-forms' ),
			__( 'SMS, OTP, and identity-verification providers', 'clever-forms' ),
			__( 'Booking and calendar synchronization', 'clever-forms' ),
			__( 'AI processing and content-generation services', 'clever-forms' ),
			__( 'Advanced e-signature and certificate services', 'clever-forms' ),
		);
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul></div>';
	}
}

if ( ! function_exists( 'clever_forms_register_addon' ) ) {
	function clever_forms_register_addon( array $addon ): bool {
		return Clever_Forms_Addons::register( $addon );
	}
}
