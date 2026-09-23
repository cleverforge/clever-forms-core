<?php
/**
 * Security hardening helpers for Clever Forms Core.
 *
 * @package CleverForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Clever_Forms_Security {
	private const DEFAULT_MAX_UPLOAD_BYTES = 10485760;

	public static function boot(): void {
		add_filter( 'clever_forms_validation_errors', array( self::class, 'validate_choice_values' ), 20, 3 );
		add_filter( 'clever_forms_submission_errors', array( self::class, 'validate_uploads' ), 20, 4 );
		add_action( 'admin_post_clever_download_pdf', array( self::class, 'guard_pdf_download' ), 1 );
	}

	/**
	 * Enforce configured choice values on the server.
	 *
	 * @param array $errors Existing validation errors.
	 * @param array $fields Form fields.
	 * @param array $values Sanitized submission values.
	 * @return array
	 */
	public static function validate_choice_values( array $errors, array $fields, array $values ): array {
		foreach ( $fields as $field ) {
			$type = (string) ( $field['type'] ?? '' );
			if ( ! in_array( $type, array( 'select', 'radio', 'checkbox', 'multiselect' ), true ) ) {
				continue;
			}

			$key     = (string) ( $field['key'] ?? '' );
			$label   = (string) ( $field['label'] ?? $key );
			$allowed = array_values(
				array_unique(
					array_map(
						static fn( $choice ): string => sanitize_text_field( (string) $choice ),
						(array) ( $field['choices'] ?? array() )
					)
				)
			);

			$submitted = $values[ $key ] ?? '';
			if ( '' === $submitted || array() === $submitted ) {
				continue;
			}

			$submitted_values = is_array( $submitted ) ? array_values( $submitted ) : array( $submitted );
			$submitted_values = array_map( static fn( $value ): string => sanitize_text_field( (string) $value ), $submitted_values );

			if ( count( $submitted_values ) !== count( array_unique( $submitted_values ) ) ) {
				$errors[] = sprintf( __( '%s contains duplicate selections.', 'clever-forms' ), $label );
				continue;
			}

			foreach ( $submitted_values as $submitted_value ) {
				if ( ! in_array( $submitted_value, $allowed, true ) ) {
					$errors[] = sprintf( __( '%s contains an invalid selection.', 'clever-forms' ), $label );
					break;
				}
			}
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * Validate every supplied file before entry creation.
	 *
	 * @param array $errors Existing errors.
	 * @param int   $form_id Form ID.
	 * @param array $fields Form fields.
	 * @param array $values Sanitized values.
	 * @return array
	 */
	public static function validate_uploads( array $errors, int $form_id, array $fields, array $values ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$file_fields = array();
		foreach ( $fields as $field ) {
			if ( 'file' === ( $field['type'] ?? '' ) ) {
				$file_fields[ (string) ( $field['key'] ?? '' ) ] = $field;
			}
		}

		foreach ( $file_fields as $key => $field ) {
			$label = (string) ( $field['label'] ?? $key );
			$name  = isset( $_FILES['fields']['name'][ $key ] ) ? sanitize_file_name( wp_unslash( $_FILES['fields']['name'][ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$error = isset( $_FILES['fields']['error'][ $key ] ) ? (int) $_FILES['fields']['error'][ $key ] : UPLOAD_ERR_NO_FILE; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( UPLOAD_ERR_NO_FILE === $error || '' === $name ) {
				if ( ! empty( $field['required'] ) ) {
					$errors[] = sprintf( __( '%s is required.', 'clever-forms' ), $label );
				}
				continue;
			}

			if ( UPLOAD_ERR_OK !== $error ) {
				$errors[] = sprintf( __( '%s could not be uploaded.', 'clever-forms' ), $label );
				continue;
			}

			$tmp  = isset( $_FILES['fields']['tmp_name'][ $key ] ) ? (string) $_FILES['fields']['tmp_name'][ $key ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$size = isset( $_FILES['fields']['size'][ $key ] ) ? (int) $_FILES['fields']['size'][ $key ] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$max  = (int) apply_filters( 'clever_forms_max_upload_bytes', self::DEFAULT_MAX_UPLOAD_BYTES, $form_id, $field );

			if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
				$errors[] = sprintf( __( '%s is not a valid uploaded file.', 'clever-forms' ), $label );
				continue;
			}

			if ( $size <= 0 || $size > $max ) {
				$errors[] = sprintf( __( '%s exceeds the permitted upload size.', 'clever-forms' ), $label );
				continue;
			}

			$allowed = array_values(
				array_filter(
					array_map( 'sanitize_key', preg_split( '/[,\s]+/', (string) ( $field['accept'] ?? '' ) ) ?: array() )
				)
			);
			$check   = wp_check_filetype_and_ext( $tmp, $name );
			$ext     = strtolower( (string) ( $check['ext'] ?? '' ) );

			if ( '' === $ext || ! in_array( $ext, $allowed, true ) ) {
				$errors[] = sprintf( __( '%s has a file type that is not allowed.', 'clever-forms' ), $label );
			}
		}

		return array_values( array_unique( $errors ) );
	}

	/**
	 * Require the nonce already generated by the entry screen before the
	 * legacy Core download callback is allowed to stream a PDF.
	 */
	public static function guard_pdf_download(): void {
		$entry = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		if ( ! $entry || ! current_user_can( 'edit_post', $entry ) ) {
			wp_die( esc_html__( 'Not permitted.', 'clever-forms' ), 403 );
		}

		check_admin_referer( 'clever_pdf_' . $entry );
	}
}
