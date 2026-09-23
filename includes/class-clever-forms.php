<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Clever_Forms {
	private static ?Clever_Forms $instance = null;
	private const FORM_CPT                 = 'clever_form';
	private const ENTRY_CPT                = 'clever_entry';

	public static function instance(): Clever_Forms {
		return self::$instance ??= new self(); }
	public static function activate(): void {
		self::register_types();
		self::ensure_upload_dir();
		flush_rewrite_rules(); }

	private function __construct() {
		add_action( 'init', array( self::class, 'register_types' ) );
		add_action( 'init', array( $this, 'register_form_shortcodes' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
		add_shortcode( 'clever_form', array( $this, 'shortcode' ) );
		add_action( 'admin_post_nopriv_clever_forms_submit', array( $this, 'handle_submission' ) );
		add_action( 'admin_post_clever_forms_submit', array( $this, 'handle_submission' ) );
		add_action( 'rest_api_init', array( $this, 'rest_routes' ) );
		add_action( 'before_delete_post', array( $this, 'before_delete_post' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
			add_action( 'save_post_' . self::FORM_CPT, array( $this, 'save_form' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
			add_action( 'admin_post_clever_download_pdf', array( $this, 'download_pdf' ) );
			add_action( 'admin_post_clever_signature_image', array( $this, 'signature_image' ) );
			add_action( 'admin_post_clever_export_entries', array( $this, 'export_entries' ) );
			add_action( 'admin_post_clever_export_form', array( $this, 'export_form' ) );
			add_action( 'admin_post_clever_import_form', array( $this, 'import_form' ) );
			add_action( 'admin_post_clever_forms_save_settings', array( $this, 'save_plugin_settings' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_filter( 'manage_' . self::FORM_CPT . '_posts_columns', array( $this, 'form_columns' ) );
			add_action( 'manage_' . self::FORM_CPT . '_posts_custom_column', array( $this, 'form_column' ), 10, 2 );
			add_filter( 'manage_' . self::ENTRY_CPT . '_posts_columns', array( $this, 'entry_columns' ) );
			add_action( 'manage_' . self::ENTRY_CPT . '_posts_custom_column', array( $this, 'entry_column' ), 10, 2 );
			add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		}
	}

	public static function register_types(): void {
		register_post_type(
			self::FORM_CPT,
			array(
				'labels'       => array(
					'name'          => 'Clever Forms',
					'singular_name' => 'Form',
					'add_new_item'  => 'Create Form',
					'edit_item'     => 'Edit Form',
					'menu_name'     => 'Clever Forms',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-feedback',
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
		register_post_type(
			self::ENTRY_CPT,
			array(
				'labels'       => array(
					'name'          => 'Entries',
					'singular_name' => 'Entry',
					'edit_item'     => 'View Entry',
					'menu_name'     => 'Entries',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . self::FORM_CPT,
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
	}

	public function register_front_assets(): void {
		wp_register_style( 'clever-forms', CLEVER_FORMS_URL . 'assets/forms.css', array(), CLEVER_FORMS_VERSION );
		wp_register_script( 'clever-signature', CLEVER_FORMS_URL . 'assets/signature-pad.min.js', array(), CLEVER_FORMS_VERSION, true );
		wp_register_script( 'clever-forms', CLEVER_FORMS_URL . 'assets/forms.js', array( 'clever-signature' ), CLEVER_FORMS_VERSION, true );
	}

	public function admin_assets( string $hook ): void {
		global $post_type;
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $post_type !== self::FORM_CPT && ! str_starts_with( $page, 'clever-' ) ) {
			return;
		}
		wp_enqueue_style( 'clever-admin', CLEVER_FORMS_URL . 'assets/admin.css', array(), CLEVER_FORMS_VERSION );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'clever-builder', CLEVER_FORMS_URL . 'assets/builder.js', array( 'jquery', 'jquery-ui-sortable' ), CLEVER_FORMS_VERSION, true );
		wp_localize_script( 'clever-builder', 'CLEVER_FORMS_BUILDER', array( 'fieldTypes' => $this->field_types() ) );
	}

	public function meta_boxes(): void {
		add_meta_box( 'clever_builder', 'Form Builder', array( $this, 'builder_box' ), self::FORM_CPT, 'normal', 'high' );
		add_meta_box( 'clever_settings', 'Form Settings', array( $this, 'settings_box' ), self::FORM_CPT, 'side', 'default' );
		add_meta_box( 'clever_entry', 'Entry Details', array( $this, 'entry_box' ), self::ENTRY_CPT, 'normal', 'high' );
	}

	private function field_types(): array {
		return array(
			'text'        => 'Single Line Text',
			'email'       => 'Email',
			'phone'       => 'Phone',
			'number'      => 'Number',
			'date'        => 'Date',
			'time'        => 'Time',
			'url'         => 'Website',
			'textarea'    => 'Paragraph Text',
			'select'      => 'Dropdown',
			'multiselect' => 'Multi Select',
			'radio'       => 'Radio Buttons',
			'checkbox'    => 'Checkboxes',
			'name'        => 'Name',
			'address'     => 'Address',
			'file'        => 'File Upload',
			'signature'   => 'Signature',
			'consent'     => 'Consent',
			'terms'       => 'Terms of Service',
			'hidden'      => 'Hidden',
			'html'        => 'HTML',
			'section'     => 'Section',
			'page'        => 'Page Break',
			'slider'      => 'Slider',
			'unique_id'   => 'Unique ID',
			'list'        => 'Repeatable List',
			'booking'     => 'Booking Date & Time',
			'product'     => 'Product',
			'subtotal'    => 'Subtotal',
			'discount'    => 'Discount',
			'tax'         => 'Tax',
		);
	}

	public function builder_box( WP_Post $post ): void {
		wp_nonce_field( 'clever_save_form', 'clever_form_nonce' );
		$fields = get_post_meta( $post->ID, '_clever_fields', true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}
		echo '<div class="clever-builder"><div class="clever-palette"><h3>Fields</h3>';
		foreach ( $this->field_types() as $type => $label ) {
			echo '<button type="button" class="button clever-add-field" data-type="' . esc_attr( $type ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</div><div class="clever-workspace"><div class="clever-canvas-wrap"><div class="clever-pane-heading"><div><h3>Structure</h3><p class="description">Add fields, drag to reorder, and click a field to edit it.</p></div></div><div id="clever-canvas"></div></div><div class="clever-preview-wrap"><div class="clever-pane-heading"><div><h3>Live Preview</h3><p class="description">Preview updates as you build. Inputs are disabled in the editor.</p></div><div class="clever-preview-devices" role="group" aria-label="Preview size"><button type="button" class="button clever-preview-device is-active" data-device="desktop" aria-label="Desktop preview"><span class="dashicons dashicons-desktop"></span></button><button type="button" class="button clever-preview-device" data-device="tablet" aria-label="Tablet preview"><span class="dashicons dashicons-tablet"></span></button><button type="button" class="button clever-preview-device" data-device="mobile" aria-label="Mobile preview"><span class="dashicons dashicons-smartphone"></span></button></div></div><div class="clever-preview-stage"><div id="clever-live-preview" data-device="desktop"></div></div></div></div></div>';
		echo '<input type="hidden" id="clever-fields-json" name="clever_fields_json" value="' . esc_attr( wp_json_encode( $fields ) ) . '">';
		echo '<div id="clever-field-editor" class="clever-field-editor" hidden></div>';
	}

	private function settings_defaults(): array {
		return array(
			'shortcode_alias'          => '',
			'description'              => '',
			'button_text'              => 'Submit',
			'success_message'          => 'Thank you. Your submission has been received.',
			'save_continue'            => 1,
			'preview'                  => 0,
			'honeypot'                 => 1,
			'custom_css'               => '',
			'notification_enabled'     => 1,
			'notification_to'          => get_option( 'admin_email' ),
			'notification_subject'     => 'New submission: {form_title}',
			'notification_message'     => "A new submission was received.\n\n{all_fields}",
			'notification_route_field' => '',
			'notification_route_value' => '',
			'notification_route_to'    => '',
			'confirmation_enabled'     => 1,
			'confirmation_subject'     => 'We received your submission',
			'confirmation_message'     => "Thank you, {field:first_name}.\n\nReference: {entry_id}",
			'webhook_enabled'          => 0,
			'webhook_url'              => '',
			'pdf_enabled'              => 0,
			'pdf_attach'               => 0,
			'pdf_save'                 => 0,
			'organization_name'        => get_bloginfo( 'name' ),
			'logo_url'                 => '',
			'h1_color'                 => '#17213c',
			'h2_color'                 => '#17213c',
			'h3_color'                 => '#17213c',
			'text_color'               => '#17213c',
			'font_family'              => 'inherit',
			'page_transitions'         => 0,
			'auto_save'                => 0,
		);
	}
	private function get_form_settings( int $id ): array {
		$s = get_post_meta( $id, '_clever_settings', true );
		return wp_parse_args( is_array( $s ) ? $s : array(), $this->settings_defaults() ); }

	public function settings_box( WP_Post $post ): void {
		$s  = $this->get_form_settings( $post->ID );
		$f  = function ( $label, $name, $type = 'text' ) use ( $s ) {
			echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="clever-full-width" type="' . esc_attr( $type ) . '" name="clever_settings[' . esc_attr( $name ) . ']" value="' . esc_attr( $s[ $name ] ?? '' ) . '"></label></p>';
		};
		$cb = function ( $label, $name ) use ( $s ) {
			echo '<p><label><input type="checkbox" name="clever_settings[' . esc_attr( $name ) . ']" value="1" ' . checked( ! empty( $s[ $name ] ), true, false ) . '> ' . esc_html( $label ) . '</label></p>';
		};
		$f( 'Shortcode alias', 'shortcode_alias' );
		echo '<p class="description">Example: job_application creates [job_application].</p>';
		$f( 'Submit button', 'button_text' );
		$cb( 'Save & Continue', 'save_continue' );
		$cb( 'Auto-save progress', 'auto_save' );
		$cb( 'Preview before submit', 'preview' );
		$cb( 'Animate page transitions', 'page_transitions' );
		$cb( 'Honeypot anti-spam', 'honeypot' );
		echo '<hr><h4>Notification</h4>';
		$cb( 'Send notification', 'notification_enabled' );
		$f( 'Send to', 'notification_to', 'email' );
		$f( 'Subject', 'notification_subject' );
		echo '<p><label><strong>Message</strong><textarea class="clever-full-width" rows="5" name="clever_settings[notification_message]">' . esc_textarea( $s['notification_message'] ) . '</textarea></label></p>';
		echo '<p><small>Merge tags: {all_fields}, {entry_id}, {form_title}, {date}, {site_name}, {field:key}</small></p>';
		echo '<h4>Conditional Email Route</h4>';
		$f( 'Field key', 'notification_route_field' );
		$f( 'Equals', 'notification_route_value' );
		$f( 'Then send to', 'notification_route_to', 'email' );
		echo '<hr><h4>Submitter Confirmation</h4>';
		$cb( 'Send confirmation to submitter', 'confirmation_enabled' );
		$f( 'Confirmation subject', 'confirmation_subject' );
		echo '<p><label><strong>Confirmation message</strong><textarea class="clever-full-width" rows="5" name="clever_settings[confirmation_message]">' . esc_textarea( $s['confirmation_message'] ) . '</textarea></label></p>';
		echo '<hr><h4>PDF</h4>';
		$cb( 'Generate PDF', 'pdf_enabled' );
		$cb( 'Save PDF', 'pdf_save' );
		$cb( 'Attach PDF to notification', 'pdf_attach' );
		echo '<hr><h4>Webhook</h4>';
		$cb( 'POST entry to webhook', 'webhook_enabled' );
		$f( 'Webhook URL', 'webhook_url', 'url' );
		echo '<hr><h4>Brand</h4>';
		$f( 'Organization name', 'organization_name' );
		$f( 'Logo URL', 'logo_url', 'url' );
		$f( 'H1 color', 'h1_color', 'color' );
		$f( 'H2 color', 'h2_color', 'color' );
		$f( 'H3 color', 'h3_color', 'color' );
		$f( 'Text color', 'text_color', 'color' );
		$f( 'Font family', 'font_family' );
		echo '<hr><h4>Custom CSS</h4><textarea class="clever-full-width" rows="5" name="clever_settings[custom_css]">' . esc_textarea( $s['custom_css'] ) . '</textarea>';
	}

	public function save_form( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['clever_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['clever_form_nonce'] ) ), 'clever_save_form' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$json   = isset( $_POST['clever_fields_json'] ) ? wp_unslash( $_POST['clever_fields_json'] ) : '[]';
		$fields = json_decode( $json, true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}
		update_post_meta( $post_id, '_clever_fields', $this->sanitize_fields( $fields ) );
		$raw = isset( $_POST['clever_settings'] ) && is_array( $_POST['clever_settings'] ) ? wp_unslash( $_POST['clever_settings'] ) : array();
		if ( is_array( $raw ) ) {
			update_post_meta( $post_id, '_clever_settings', $this->sanitize_form_settings( $raw ) );
		}
	}

	private function sanitize_fields( array $fields ): array {
		$allowed = array_keys( $this->field_types() );
		$out     = array();
		foreach ( array_slice( $fields, 0, 200 ) as $i => $f ) {
			if ( ! is_array( $f ) || ! in_array( $f['type'] ?? '', $allowed, true ) ) {
				continue;
			}
			$key = sanitize_key( $f['key'] ?? ( 'field_' . ( $i + 1 ) ) );
			if ( $key === '' ) {
				$key = 'field_' . ( $i + 1 );
			}
			$choices = array();
			foreach ( (array) ( $f['choices'] ?? array() ) as $c ) {
				$c = sanitize_text_field( (string) $c );
				if ( $c !== '' ) {
					$choices[] = $c;
				}
			}
			$out[] = array(
				'id'                 => sanitize_key( $f['id'] ?? wp_generate_uuid4() ),
				'type'               => sanitize_key( $f['type'] ),
				'key'                => $key,
				'label'              => sanitize_text_field( $f['label'] ?? 'Field' ),
				'description'        => sanitize_text_field( $f['description'] ?? '' ),
				'placeholder'        => sanitize_text_field( $f['placeholder'] ?? '' ),
				'required'           => ! empty( $f['required'] ) ? 1 : 0,
				'width'              => in_array( (string) ( $f['width'] ?? '100' ), array( '100', '50', '33', '66' ), true ) ? (string) $f['width'] : '100',
				'choices'            => $choices,
				'default'            => sanitize_text_field( $f['default'] ?? '' ),
				'html'               => wp_kses_post( $f['html'] ?? '' ),
				'condition_field'    => sanitize_key( $f['condition_field'] ?? '' ),
				'condition_operator' => in_array( $f['condition_operator'] ?? 'equals', array( 'equals', 'not_equals', 'contains', 'not_empty' ), true ) ? $f['condition_operator'] : 'equals',
				'condition_value'    => sanitize_text_field( $f['condition_value'] ?? '' ),
				'accept'             => sanitize_text_field( $f['accept'] ?? 'pdf,doc,docx,jpg,jpeg,png' ),
				'read_only'          => ! empty( $f['read_only'] ) ? 1 : 0,
				'min_words'          => max( 0, (int) ( $f['min_words'] ?? 0 ) ),
				'max_words'          => max( 0, (int) ( $f['max_words'] ?? 0 ) ),
				'max_selections'     => max( 0, (int) ( $f['max_selections'] ?? 0 ) ),
				'min_date'           => sanitize_text_field( $f['min_date'] ?? '' ),
				'max_date'           => sanitize_text_field( $f['max_date'] ?? '' ),
				'copy_from'          => sanitize_key( $f['copy_from'] ?? '' ),
				'unique_mode'        => in_array( ( $f['unique_mode'] ?? 'alphanumeric' ), array( 'alphanumeric', 'numeric', 'sequential' ), true ) ? $f['unique_mode'] : 'alphanumeric',
				'min_value'          => is_numeric( $f['min_value'] ?? null ) ? (string) $f['min_value'] : '',
				'max_value'          => is_numeric( $f['max_value'] ?? null ) ? (string) $f['max_value'] : '',
				'step'               => is_numeric( $f['step'] ?? null ) ? (string) $f['step'] : '1',
				'price'              => is_numeric( $f['price'] ?? null ) ? (string) $f['price'] : '0',
				'rename_template'    => sanitize_text_field( $f['rename_template'] ?? '' ),
			);
		}
		return $out;
	}

	private function sanitize_form_settings( array $r ): array {
		$d = $this->settings_defaults();
		$o = array();
		foreach ( array( 'shortcode_alias', 'button_text', 'notification_subject', 'notification_route_field', 'notification_route_value', 'organization_name', 'font_family' ) as $k ) {
			$o[ $k ] = sanitize_text_field( wp_unslash( $r[ $k ] ?? $d[ $k ] ) );
		}
		$o['shortcode_alias'] = sanitize_key( $o['shortcode_alias'] );
		foreach ( array( 'notification_to', 'notification_route_to' ) as $k ) {
			$e       = sanitize_email( $r[ $k ] ?? '' );
			$o[ $k ] = is_email( $e ) ? $e : ''; }
		foreach ( array( 'webhook_url', 'logo_url' ) as $k ) {
			$o[ $k ] = esc_url_raw( $r[ $k ] ?? '' );
		}
		foreach ( array( 'notification_message', 'custom_css' ) as $k ) {
			$o[ $k ] = sanitize_textarea_field( wp_unslash( $r[ $k ] ?? $d[ $k ] ) );
		}
		foreach ( array( 'save_continue', 'preview', 'honeypot', 'notification_enabled', 'confirmation_enabled', 'webhook_enabled', 'pdf_enabled', 'pdf_attach', 'pdf_save', 'page_transitions', 'auto_save' ) as $k ) {
			$o[ $k ] = empty( $r[ $k ] ) ? 0 : 1;
		}
		foreach ( array( 'h1_color', 'h2_color', 'h3_color', 'text_color' ) as $k ) {
			$o[ $k ] = sanitize_hex_color( $r[ $k ] ?? '' ) ?: $d[ $k ];
		}
		$o['success_message']      = sanitize_text_field( $r['success_message'] ?? $d['success_message'] );
		$o['confirmation_subject'] = sanitize_text_field( $r['confirmation_subject'] ?? $d['confirmation_subject'] );
		$o['confirmation_message'] = sanitize_textarea_field( wp_unslash( $r['confirmation_message'] ?? $d['confirmation_message'] ) );
		return wp_parse_args( $o, $d );
	}

	public function register_form_shortcodes(): void {
		$forms = get_posts(
			array(
				'post_type'   => self::FORM_CPT,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $forms as $id ) {
			$s = $this->get_form_settings( (int) $id );
			if ( ! empty( $s['shortcode_alias'] ) && ! shortcode_exists( $s['shortcode_alias'] ) ) {
				add_shortcode( $s['shortcode_alias'], fn( $atts = array() )=>$this->render_form( (int) $id ) );
			}
		}
	}

	public function shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'clever_form'
		);
		$id   = (int) $atts['id'];
		if ( ! $id && $atts['slug'] ) {
			$p  = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, self::FORM_CPT );
			$id = $p ? (int) $p->ID : 0; }
		return $id ? $this->render_form( $id ) : '<p>Form not found.</p>';
	}

	private function render_form( int $id ): string {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== self::FORM_CPT || $post->post_status !== 'publish' ) {
			return '<p>Form not available.</p>';
		}
		$fields = get_post_meta( $id, '_clever_fields', true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		} $s = $this->get_form_settings( $id );
		wp_enqueue_style( 'clever-forms' );
		wp_enqueue_script( 'clever-forms' );
		if ( array_filter( $fields, fn( $f )=>( $f['type'] ?? '' ) === 'signature' ) ) {
			wp_enqueue_script( 'clever-signature' );
		}
		$css = '.clever-form-' . $id . '{--a4-h1:' . esc_attr( $s['h1_color'] ) . ';--a4-h2:' . esc_attr( $s['h2_color'] ) . ';--a4-h3:' . esc_attr( $s['h3_color'] ) . ';--a4-text:' . esc_attr( $s['text_color'] ) . ';--a4-font:' . esc_attr( $s['font_family'] ) . ';}';
		if ( $s['custom_css'] ) {
			$css .= "\n" . $s['custom_css'];
		} wp_add_inline_style( 'clever-forms', $css );
		$submitted = isset( $_GET['clever_submitted'] ) && (int) ( $_GET['clever_form'] ?? 0 ) === $id;
		$ref       = sanitize_text_field( wp_unslash( $_GET['clever_ref'] ?? '' ) );
		ob_start();
		echo '<div class="clever-form-wrap clever-form-' . esc_attr( (string) $id ) . '" data-form-id="' . esc_attr( (string) $id ) . '" data-save="' . ( ! empty( $s['save_continue'] ) ? '1' : '0' ) . '" data-preview="' . ( ! empty( $s['preview'] ) ? '1' : '0' ) . '" data-auto-save="' . ( ! empty( $s['auto_save'] ) ? '1' : '0' ) . '" data-transitions="' . ( ! empty( $s['page_transitions'] ) ? '1' : '0' ) . '">';
		if ( $submitted ) {
			echo '<div class="clever-success"><h2>' . esc_html( $s['success_message'] ) . '</h2>' . ( $ref ? '<p>Reference: <strong>' . esc_html( $ref ) . '</strong></p>' : '' ) . '</div></div>';
			return (string) ob_get_clean(); }
		if ( $s['logo_url'] ) {
			echo '<div class="clever-logo"><img src="' . esc_url( $s['logo_url'] ) . '" alt=""></div>';
		}
		echo '<h1>' . esc_html( get_the_title( $id ) ) . '</h1>';
		echo '<form class="clever-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="clever_forms_submit"><input type="hidden" name="form_id" value="' . esc_attr( (string) $id ) . '">';
		wp_nonce_field( 'clever_submit_' . $id, 'clever_nonce' );
		if ( $s['honeypot'] ) {
			echo '<div class="clever-hp" aria-hidden="true"><label>Website <input type="text" name="clever_website" tabindex="-1" autocomplete="off"></label></div>';
		}
		$page = 1;
		echo '<div class="clever-page is-active" data-page="1"><div class="clever-grid">';
		foreach ( $fields as $f ) {
			if ( $f['type'] === 'page' ) {
				echo '</div></div>';
				++$page;
				echo '<div class="clever-page" data-page="' . esc_attr( (string) $page ) . '"><div class="clever-grid">';
				continue; }
			$this->render_field( $f );
		}
		echo '</div></div>';
		if ( $page > 1 ) {
			echo '<div class="clever-progress" role="progressbar" aria-valuemin="1" aria-valuenow="1" aria-valuemax="' . esc_attr( (string) $page ) . '"><span></span></div><div class="clever-nav"><button type="button" class="button clever-prev" hidden>Previous</button><button type="button" class="button clever-next">Next</button></div>';
		}
		if ( $s['save_continue'] ) {
			echo '<p><button type="button" class="button clever-save">Save & Continue Later</button> <button type="button" class="button clever-clear">Clear Saved Progress</button></p>';
		}
		echo '<div class="clever-preview" hidden><h2>Review Your Submission</h2><div class="clever-preview-body"></div><button type="button" class="button clever-edit">Back to Form</button></div>';
		echo '<p class="clever-submit-row"><button type="submit" class="clever-submit">' . esc_html( $s['button_text'] ) . '</button></p></form></div>';
		return (string) ob_get_clean();
	}

	private function query_default( array $f ): string {
		$k = $f['key'];
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : (string) $f['default']; }

	private function render_field( array $f ): void {
		$type  = $f['type'];
		$key   = $f['key'];
		$label = $f['label'];
		$req   = ! empty( $f['required'] );
		$width = $f['width'];
		$cond  = '';
		if ( $f['condition_field'] ) {
			$cond = ' data-condition-field="' . esc_attr( $f['condition_field'] ) . '" data-condition-operator="' . esc_attr( $f['condition_operator'] ) . '" data-condition-value="' . esc_attr( $f['condition_value'] ) . '"';
		}
		if ( $type === 'section' ) {
			echo '<div class="clever-field clever-section w100"' . $cond . '><h2>' . esc_html( $label ) . '</h2>' . ( $f['description'] ? '<p>' . esc_html( $f['description'] ) . '</p>' : '' ) . '</div>';
			return; }
		if ( $type === 'html' ) {
			echo '<div class="clever-field clever-html w' . esc_attr( $width ) . '"' . $cond . '>' . wp_kses_post( $f['html'] ) . '</div>';
			return; }
		$name  = 'fields[' . $key . ']';
		$id    = 'clever-' . $key . '-' . wp_rand( 1000, 9999 );
		$value = $this->query_default( $f );
		echo '<div class="clever-field w' . esc_attr( $width ) . '"' . $cond . ' data-field-key="' . esc_attr( $key ) . '"' . ( ! empty( $f['copy_from'] ) ? ' data-copy-from="' . esc_attr( $f['copy_from'] ) . '"' : '' ) . '>';
		if ( ! in_array( $type, array( 'hidden', 'unique_id', 'subtotal', 'discount', 'tax' ), true ) ) {
			echo '<label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . ( $req ? ' <span class="req">*</span>' : '' ) . '</strong></label>';
		}
		$readonly = ! empty( $f['read_only'] ) ? ' readonly' : '';
		$attr     = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( $req ? ' required' : '' ) . ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' . $readonly;
		if ( $type === 'date' ) {
			if ( ! empty( $f['min_date'] ) ) {
				$attr .= ' min="' . esc_attr( $f['min_date'] ) . '"';
			} if ( ! empty( $f['max_date'] ) ) {
				$attr .= ' max="' . esc_attr( $f['max_date'] ) . '"';
			}
		}
		if ( in_array( $type, array( 'text', 'email', 'number', 'date', 'time', 'url' ), true ) ) {
			echo '<input type="' . esc_attr( $type ) . '"' . $attr . ' value="' . esc_attr( $value ) . '">';
		} elseif ( $type === 'phone' ) {
			echo '<input type="tel"' . $attr . ' value="' . esc_attr( $value ) . '" inputmode="tel">';
		} elseif ( $type === 'hidden' ) {
			echo '<input type="hidden"' . $attr . ' value="' . esc_attr( $value ) . '">';
		} elseif ( $type === 'textarea' ) {
			echo '<textarea' . $attr . ' rows="5" data-min-words="' . esc_attr( (string) ( $f['min_words'] ?? 0 ) ) . '" data-max-words="' . esc_attr( (string) ( $f['max_words'] ?? 0 ) ) . '">' . esc_textarea( $value ) . '</textarea>';
		} elseif ( $type === 'select' ) {
			echo '<select' . $attr . '><option value="">Select...</option>';
			foreach ( $f['choices'] as $c ) {
				echo '<option ' . selected( $value, $c, false ) . '>' . esc_html( $c ) . '</option>';
			} echo '</select>'; } elseif ( $type === 'multiselect' ) {
			echo '<select' . $attr . ' name="fields[' . esc_attr( $key ) . '][]" multiple size="6" class="clever-multiselect">';
			foreach ( $f['choices'] as $c ) {
				echo '<option>' . esc_html( $c ) . '</option>';
			} echo '</select>'; } elseif ( $type === 'radio' ) {
				foreach ( $f['choices'] as $c ) {
					echo '<label class="choice"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $c ) . '" ' . checked( $value, $c, false ) . ( $req ? ' required' : '' ) . '> ' . esc_html( $c ) . '</label>';
				}
			} elseif ( $type === 'checkbox' ) {
				$max = (int) ( $f['max_selections'] ?? 0 );
				foreach ( $f['choices'] as $i => $c ) {
					echo '<label class="choice"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $c ) . '"' . ( $max ? ' data-max-selections="' . esc_attr( (string) $max ) . '"' : '' ) . '> ' . esc_html( $c ) . '</label>';
				}
			} elseif ( $type === 'consent' ) {
				echo '<label class="choice"><input type="checkbox" name="' . esc_attr( $name ) . '" value="Yes"' . ( $req ? ' required' : '' ) . '> ' . esc_html( $f['description'] ?: 'I agree.' ) . '</label>';
			} elseif ( $type === 'terms' ) {
				echo '<div class="clever-terms-text">' . wp_kses_post( $f['html'] ?: '<p>Please review the terms.</p>' ) . '</div><label class="choice"><input type="checkbox" name="' . esc_attr( $name ) . '" value="Accepted"' . ( $req ? ' required' : '' ) . '> I have read and accept the terms.</label>';
			} elseif ( $type === 'file' ) {
				echo '<input type="file"' . $attr . ' accept="' . esc_attr( $this->accept_attr( $f['accept'] ) ) . '">';
			} elseif ( $type === 'signature' ) {
				echo '<div class="clever-signature" data-signature-field="' . esc_attr( $key ) . '"><canvas class="clever-signature-canvas" width="640" height="180" role="img" tabindex="0" aria-label="' . esc_attr( sprintf( __( 'Signature pad for %s', 'clever-forms' ), $label ) ) . '"></canvas><input type="hidden" name="' . esc_attr( $name ) . '" class="clever-signature-data"' . ( $req ? ' data-required="1"' : '' ) . '><div class="clever-signature-actions"><button type="button" class="button clever-signature-clear">' . esc_html__( 'Clear signature', 'clever-forms' ) . '</button><span class="clever-signature-status" aria-live="polite"></span></div></div>';
			} elseif ( $type === 'name' ) {
				echo '<div class="clever-compound"><input type="text" name="fields[' . esc_attr( $key ) . '_first]" placeholder="First"' . ( $req ? ' required' : '' ) . $readonly . '><input type="text" name="fields[' . esc_attr( $key ) . '_last]" placeholder="Last"' . ( $req ? ' required' : '' ) . $readonly . '></div>'; } elseif ( $type === 'address' ) {
				echo '<div class="clever-compound"><input type="text" name="fields[' . esc_attr( $key ) . '_street]" placeholder="Street"' . ( $req ? ' required' : '' ) . $readonly . '><input type="text" name="fields[' . esc_attr( $key ) . '_city]" placeholder="City"' . ( $req ? ' required' : '' ) . $readonly . '><input type="text" name="fields[' . esc_attr( $key ) . '_state]" placeholder="State / Region"' . $readonly . '><input type="text" name="fields[' . esc_attr( $key ) . '_zip]" placeholder="Postal Code"' . $readonly . '></div>'; } elseif ( $type === 'slider' ) {
					$min  = $f['min_value'] !== '' ? $f['min_value'] : '0';
					$max  = $f['max_value'] !== '' ? $f['max_value'] : '100';
					$step = $f['step'] ?: '1';
					echo '<div class="clever-slider-wrap"><input type="range"' . $attr . ' min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $value !== '' ? $value : $min ) . '" class="clever-slider"><output>' . esc_html( $value !== '' ? $value : $min ) . '</output></div>';
				} elseif ( $type === 'unique_id' ) {
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
				} elseif ( $type === 'list' ) {
					echo '<div class="clever-list" data-list-key="' . esc_attr( $key ) . '"><div class="clever-list-row"><input type="text" name="fields[' . esc_attr( $key ) . '][]" placeholder="' . esc_attr( $f['placeholder'] ?: 'Item' ) . '"> <button type="button" class="button clever-list-remove">Remove</button></div><button type="button" class="button clever-list-add">Add Row</button></div>';
				} elseif ( $type === 'booking' ) {
					echo '<div class="clever-compound"><input type="date" name="fields[' . esc_attr( $key ) . '_date]"' . ( $req ? ' required' : '' ) . '><input type="time" name="fields[' . esc_attr( $key ) . '_time]"' . ( $req ? ' required' : '' ) . '></div>';
				} elseif ( $type === 'product' ) {
					$price = (float) ( $f['price'] ?? 0 );
					echo '<label class="choice"><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $price ) . '" data-product-price="' . esc_attr( (string) $price ) . '"> ' . esc_html( $label ) . ' — ' . esc_html( number_format_i18n( $price, 2 ) ) . '</label>';
				} elseif ( in_array( $type, array( 'subtotal', 'discount', 'tax' ), true ) ) {
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" class="clever-calc-' . esc_attr( $type ) . '"><div class="clever-money-output" data-money-type="' . esc_attr( $type ) . '">' . esc_html( $label ) . ': <strong>0.00</strong></div>';
				}
				if ( $f['description'] && ! in_array( $type, array( 'consent', 'terms' ), true ) ) {
					echo '<small>' . esc_html( $f['description'] ) . '</small>';
				}
				if ( ( $f['min_words'] ?? 0 ) || ( $f['max_words'] ?? 0 ) ) {
					echo '<small class="clever-word-count" data-for="' . esc_attr( $id ) . '">0 words</small>';
				}
							echo '</div>';
	}

	private function accept_attr( string $accept ): string {
		$ext = array_filter( array_map( 'sanitize_key', preg_split( '/[,\s]+/', $accept ) ?: array() ) );
		return implode( ',', array_map( fn( $e )=>'.' . $e, $ext ) ); }

	public function handle_submission(): void {
		$id = (int) ( $_POST['form_id'] ?? 0 );
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['clever_nonce'] ?? '' ) ), 'clever_submit_' . $id ) ) {
			wp_die( 'Security validation failed.' );
		}
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== self::FORM_CPT ) {
			wp_die( 'Invalid form.' );
		} $s = $this->get_form_settings( $id );
		if ( $s['honeypot'] && ! empty( $_POST['clever_website'] ) ) {
			wp_die( esc_html__( 'Spam rejected.', 'clever-forms' ) );
		}
		if ( ! $this->submission_rate_allowed( $id ) ) {
			wp_die( esc_html__( 'Please wait a moment before submitting again.', 'clever-forms' ), esc_html__( 'Submission limited', 'clever-forms' ), array( 'response' => 429 ) );
		}
		$fields = get_post_meta( $id, '_clever_fields', true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		} $raw = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$values = $this->sanitize_submission( $fields, $raw );
		foreach ( $fields as $f ) {
			if ( ( $f['type'] ?? '' ) === 'unique_id' ) {
				$k    = $f['key'];
				$mode = $f['unique_mode'] ?? 'alphanumeric';
				if ( $mode === 'sequential' ) {
					$n = (int) get_option( 'clever_forms_sequence_' . $id . '_' . $k, 0 ) + 1;
					update_option( 'clever_forms_sequence_' . $id . '_' . $k, $n, false );
					$values[ $k ] = (string) $n;
				} elseif ( $mode === 'numeric' ) {
					$values[ $k ] = (string) wp_rand( 100000, 999999999 );
				} else {
					$values[ $k ] = strtoupper( wp_generate_password( 10, false, false ) );
				}
			}
		} $errors = array_merge( $this->validate_submission( $fields, $values ), $this->validate_required_files( $fields ) );
		$errors   = (array) apply_filters( 'clever_forms_submission_errors', $errors, $id, $fields, $values );
		if ( $errors ) {
			wp_die( esc_html( implode( ' ', $errors ) ), 'Form validation', array( 'response' => 400 ) );
		}
		$entry_id = wp_insert_post(
			array(
				'post_type'   => self::ENTRY_CPT,
				'post_status' => 'private',
				'post_title'  => get_the_title( $id ) . ' — ' . current_time( 'mysql' ),
			)
		);
		if ( is_wp_error( $entry_id ) ) {
			wp_die( 'Could not save entry.' );
		}
		$reference                       = 'CF-' . wp_date( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
		[$uploads, $upload_store_errors] = $this->handle_uploads( (int) $entry_id, $id, $fields, $values, $reference );
		if ( $upload_store_errors ) {
			wp_delete_post( (int) $entry_id, true );
			wp_die( esc_html( implode( ' ', array_unique( $upload_store_errors ) ) ), esc_html__( 'Upload storage failed', 'clever-forms' ), array( 'response' => 500 ) );
		}
		[$values, $signatures, $signature_store_errors] = $this->store_signatures( (int) $entry_id, $fields, $values );
		if ( $signature_store_errors ) {
			wp_delete_post( (int) $entry_id, true );
			wp_die( esc_html( implode( ' ', array_unique( $signature_store_errors ) ) ), esc_html__( 'Signature storage failed', 'clever-forms' ), array( 'response' => 500 ) );
		}
		update_post_meta( $entry_id, '_clever_form_id', $id );
		update_post_meta( $entry_id, '_clever_reference', $reference );
		update_post_meta( $entry_id, '_clever_values', $values );
		update_post_meta( $entry_id, '_clever_uploads', $uploads );
		update_post_meta( $entry_id, '_clever_signatures', $signatures );
		update_post_meta( $entry_id, '_clever_submitted_at', current_time( 'mysql' ) );
		update_post_meta( $entry_id, '_clever_form_snapshot', $fields );
		update_post_meta( $entry_id, '_clever_settings_snapshot', $s );
		$pdf = '';
		if ( $s['pdf_enabled'] && ( $s['pdf_save'] || $s['pdf_attach'] ) ) {
			$pdf = $this->write_pdf( (int) $entry_id, $s['pdf_save'] );
		}
		$this->send_notification( (int) $entry_id, $id, $fields, $values, $s, $reference, $pdf );
		$this->send_confirmation( (int) $entry_id, $id, $fields, $values, $s, $reference );
		$this->send_webhook( (int) $entry_id, $id, $values, $s, $reference );
		if ( $pdf && ! $s['pdf_save'] && is_file( $pdf ) ) {
			@unlink( $pdf );
		}
		do_action( 'clever_forms_after_submission', $entry_id, $id, $values );
		$url = wp_get_referer() ?: home_url( '/' );
		$url = remove_query_arg( array( 'clever_submitted', 'clever_form', 'clever_ref' ), $url );
		$url = add_query_arg(
			array(
				'clever_submitted' => 1,
				'clever_form'      => $id,
				'clever_ref'       => $reference,
			),
			$url
		);
		$url = (string) apply_filters( 'clever_forms_post_submit_redirect', $url, (int) $entry_id, $id, $values, $reference );
		wp_safe_redirect( $url );
		exit;
	}

	private function sanitize_submission( array $fields, array $raw ): array {
		$out = array();
		foreach ( $fields as $f ) {
			$k = $f['key'];
			$t = $f['type'];
			if ( in_array( $t, array( 'section', 'page', 'html', 'file', 'subtotal', 'discount', 'tax' ), true ) ) {
				continue;
			}
			if ( $t === 'name' ) {
				$out[ $k . '_first' ] = sanitize_text_field( $raw[ $k . '_first' ] ?? '' );
				$out[ $k . '_last' ]  = sanitize_text_field( $raw[ $k . '_last' ] ?? '' );
				continue; }
			if ( $t === 'address' ) {
				foreach ( array( 'street', 'city', 'state', 'zip' ) as $p ) {
					$out[ $k . '_' . $p ] = sanitize_text_field( $raw[ $k . '_' . $p ] ?? '' );
				} continue; }
			if ( $t === 'booking' ) {
				$out[ $k . '_date' ] = sanitize_text_field( $raw[ $k . '_date' ] ?? '' );
				$out[ $k . '_time' ] = sanitize_text_field( $raw[ $k . '_time' ] ?? '' );
				continue; }
			$v = $raw[ $k ] ?? '';
			if ( is_array( $v ) ) {
				$out[ $k ] = array_values( array_map( fn( $x )=>sanitize_text_field( $x ), $v ) );
			} elseif ( $t === 'email' ) {
				$out[ $k ] = sanitize_email( $v );
			} elseif ( $t === 'textarea' ) {
				$out[ $k ] = sanitize_textarea_field( $v );
			} elseif ( $t === 'signature' ) {
				$out[ $k ] = preg_match( '#^data:image/png;base64,#', (string) $v ) ? (string) $v : '';
			} else {
				$out[ $k ] = sanitize_text_field( $v );
			}
		}
		return apply_filters( 'clever_forms_sanitized_values', $out, $fields );
	}

	private function validate_submission( array $fields, array $values ): array {
		$e = array();
		foreach ( $fields as $f ) {
			if ( empty( $f['required'] ) ) {
				continue;
			} $k = $f['key'];
			$t   = $f['type'];
			if ( $t === 'name' && ( empty( $values[ $k . '_first' ] ) || empty( $values[ $k . '_last' ] ) ) ) {
				$e[] = $f['label'] . ' is required.';
			} elseif ( $t === 'address' && empty( $values[ $k . '_street' ] ) ) {
				$e[] = $f['label'] . ' is required.';
			} elseif ( ! in_array( $t, array( 'section', 'page', 'html', 'file' ), true ) && empty( $values[ $k ] ) ) {
				$e[] = $f['label'] . ' is required.';
			}
		}
		foreach ( $fields as $f ) {
			$k = $f['key'];
			$t = $f['type'];
			if ( $t === 'booking' && ! empty( $f['required'] ) && ( empty( $values[ $k . '_date' ] ) || empty( $values[ $k . '_time' ] ) ) ) {
				$e[] = $f['label'] . ' is required.';
			}
			if ( $t === 'textarea' && isset( $values[ $k ] ) ) {
				$wc = str_word_count( wp_strip_all_tags( (string) $values[ $k ] ) );
				if ( ! empty( $f['min_words'] ) && $wc < (int) $f['min_words'] ) {
					$e[] = $f['label'] . ' must contain at least ' . (int) $f['min_words'] . ' words.';
				} if ( ! empty( $f['max_words'] ) && $wc > (int) $f['max_words'] ) {
					$e[] = $f['label'] . ' cannot exceed ' . (int) $f['max_words'] . ' words.';
				}
			}
			if ( $t === 'signature' && ! empty( $values[ $k ] ) && $this->decode_signature( (string) $values[ $k ] ) === '' ) {
				$e[] = sprintf( __( '%s contains an invalid signature.', 'clever-forms' ), (string) $f['label'] );
			}
			if ( $t === 'email' && ! empty( $values[ $k ] ) && ! is_email( (string) $values[ $k ] ) ) {
				$e[] = sprintf( __( '%s must contain a valid email address.', 'clever-forms' ), (string) $f['label'] );
			}
			if ( $t === 'checkbox' && ! empty( $f['max_selections'] ) && is_array( $values[ $k ] ?? null ) && count( $values[ $k ] ) > (int) $f['max_selections'] ) {
				$e[] = $f['label'] . ' allows no more than ' . (int) $f['max_selections'] . ' selections.';
			}
		}
		return apply_filters( 'clever_forms_validation_errors', $e, $fields, $values );
	}

	private function validate_required_files( array $fields ): array {
		$errors = array();
		foreach ( $fields as $f ) {
			if ( ( $f['type'] ?? '' ) !== 'file' || empty( $f['required'] ) ) {
				continue;
			}
			$key   = (string) $f['key'];
			$name  = $_FILES['fields']['name'][ $key ] ?? '';
			$error = (int) ( $_FILES['fields']['error'][ $key ] ?? UPLOAD_ERR_NO_FILE );
			if ( $name === '' || $error !== UPLOAD_ERR_OK ) {
				$errors[] = sprintf( __( '%s is required.', 'clever-forms' ), (string) $f['label'] );
			}
		}
		return $errors;
	}

	private function decode_signature( string $data_url ): string {
		if ( ! preg_match( '#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $data_url, $m ) ) {
			return '';
		}
		if ( strlen( $m[1] ) > 2800000 ) {
			return '';
		}
		$binary = base64_decode( $m[1], true );
		if ( $binary === false || strlen( $binary ) < 100 || strlen( $binary ) > 2000000 ) {
			return '';
		}
		$info = @getimagesizefromstring( $binary );
		if ( ! is_array( $info ) || ( $info['mime'] ?? '' ) !== 'image/png' ) {
			return '';
		}
		return $binary;
	}

	private function store_signatures( int $entry, array $fields, array $values ): array {
		$stored = array();
		$errors = array();
		$dir    = $this->entry_dir( $entry );
		foreach ( $fields as $f ) {
			if ( ( $f['type'] ?? '' ) !== 'signature' ) {
				continue;
			}
			$key = (string) $f['key'];
			$raw = (string) ( $values[ $key ] ?? '' );
			if ( $raw === '' ) {
				continue;
			}
			$binary = $this->decode_signature( $raw );
			if ( $binary === '' ) {
				$errors[] = sprintf( __( '%s contains an invalid signature.', 'clever-forms' ), (string) $f['label'] );
				continue; }
			if ( $dir === '' ) {
				$errors[] = sprintf( __( '%s could not be stored securely.', 'clever-forms' ), (string) $f['label'] );
				continue; }
			$filename = sanitize_file_name( $key . '-signature.png' );
			$path     = trailingslashit( $dir ) . wp_generate_password( 24, false, false ) . '-' . $filename;
			if ( file_put_contents( $path, $binary ) !== false && self::private_path_allowed( $path ) ) {
				chmod( $path, 0640 );
				$stored[ $key ] = array(
					'name' => $filename,
					'path' => $path,
					'type' => 'image/png',
					'size' => strlen( $binary ),
				);
				$values[ $key ] = 'Captured';
			} else {
				if ( is_file( $path ) ) {
					wp_delete_file( $path );
				}
				$errors[] = sprintf( __( '%s could not be stored securely.', 'clever-forms' ), (string) $f['label'] );
			}
		}
		return array( $values, $stored, $errors );
	}

	public function signature_image(): void {
		$entry = (int) ( $_GET['entry'] ?? 0 );
		$field = sanitize_key( wp_unslash( $_GET['field'] ?? '' ) );
		if ( ! $entry || $field === '' || ! current_user_can( 'edit_post', $entry ) ) {
			wp_die( esc_html__( 'Not permitted.', 'clever-forms' ) );
		}
		check_admin_referer( 'clever_signature_' . $entry . '_' . $field );
		$signatures = get_post_meta( $entry, '_clever_signatures', true );
		$path       = is_array( $signatures ) && isset( $signatures[ $field ]['path'] ) ? (string) $signatures[ $field ]['path'] : '';
		if ( $path === '' || ! is_readable( $path ) || ! self::private_path_allowed( $path ) ) {
			wp_die( esc_html__( 'Signature unavailable.', 'clever-forms' ) );
		}
		nocache_headers();
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $field . '-signature.png' ) . '"' );
		readfile( $path );
		exit;
	}

	private static function ensure_upload_dir(): string {
		$configured = defined( 'CLEVER_FORMS_PRIVATE_DIR' ) ? (string) CLEVER_FORMS_PRIVATE_DIR : '';
		$d          = $configured !== '' ? $configured : dirname( dirname( untrailingslashit( ABSPATH ) ) ) . '/clever-forms-private';
		$d          = wp_normalize_path( untrailingslashit( $d ) );
		$web_root   = trailingslashit( wp_normalize_path( untrailingslashit( ABSPATH ) ) );
		if ( $d === '' || str_starts_with( trailingslashit( $d ), $web_root ) ) {
			return '';
		}
		if ( ! is_dir( $d ) && ! wp_mkdir_p( $d ) ) {
			return '';
		}
		if ( ! is_writable( $d ) ) {
			return '';
		}
		return $d;
	}
	private static function private_path_allowed( string $path ): bool {
		$base = self::ensure_upload_dir();
		if ( $base === '' ) {
			return false;
		}
		$base_real = realpath( $base );
		$path_real = realpath( $path );
		if ( $base_real === false || $path_real === false ) {
			return false;
		}
		$base_norm = trailingslashit( wp_normalize_path( $base_real ) );
		$path_norm = wp_normalize_path( $path_real );
		return str_starts_with( $path_norm, $base_norm );
	}
	private function entry_dir( int $entry ): string {
		$base = self::ensure_upload_dir();
		if ( $base === '' ) {
			return '';
		}
		$d = trailingslashit( $base ) . hash_hmac( 'sha256', (string) $entry, wp_salt( 'auth' ) );
		if ( ! is_dir( $d ) && ! wp_mkdir_p( $d ) ) {
			return '';
		}
		return is_dir( $d ) && is_writable( $d ) ? $d : '';
	}

	private function handle_uploads( int $entry, int $form, array $fields, array $values = array(), string $reference = '' ): array {
		$out    = array();
		$errors = array();
		$map    = array();
		foreach ( $fields as $f ) {
			if ( $f['type'] === 'file' ) {
				$map[ $f['key'] ] = $f;
			}
		}
		$dir = $this->entry_dir( $entry );
		foreach ( $map as $key => $f ) {
			$file = $_FILES['fields']['name'][ $key ] ?? '';
			if ( ! $file ) {
				continue;
			}
			$tmp  = $_FILES['fields']['tmp_name'][ $key ] ?? '';
			$err  = $_FILES['fields']['error'][ $key ] ?? UPLOAD_ERR_NO_FILE;
			$size = (int) ( $_FILES['fields']['size'][ $key ] ?? 0 );
			if ( $err !== UPLOAD_ERR_OK || ! $tmp || ! is_uploaded_file( $tmp ) ) {
				$errors[] = sprintf( __( '%s could not be persisted.', 'clever-forms' ), (string) $f['label'] );
				continue; }
			$allowed = array_filter( array_map( 'sanitize_key', preg_split( '/[,\s]+/', $f['accept'] ) ?: array() ) );
			$check   = wp_check_filetype_and_ext( $tmp, $file );
			if ( ! $check['ext'] || ! in_array( strtolower( $check['ext'] ), $allowed, true ) ) {
				$errors[] = sprintf( __( '%s has a file type that is not allowed.', 'clever-forms' ), (string) $f['label'] );
				continue; }
			if ( $dir === '' ) {
				$errors[] = sprintf( __( '%s could not be stored securely.', 'clever-forms' ), (string) $f['label'] );
				continue; }
			$name = sanitize_file_name( $file );
			if ( ! empty( $f['rename_template'] ) ) {
				$template = (string) $f['rename_template'];
				$replace  = array(
					'{reference}' => $reference,
					'{form_id}'   => (string) $form,
				);
				foreach ( $values as $value_key => $value ) {
					if ( ! is_array( $value ) ) {
						$replace[ '{field:' . $value_key . '}' ] = (string) $value;
					}
				}
				$base = sanitize_file_name( strtr( $template, $replace ) );
				$ext  = strtolower( (string) $check['ext'] );
				if ( $base !== '' ) {
					$name = preg_match( '/\.' . preg_quote( $ext, '/' ) . '$/i', $base ) ? $base : $base . '.' . $ext;
				}
			}
			$dest = trailingslashit( $dir ) . wp_generate_password( 24, false, false ) . '-' . $name;
			if ( move_uploaded_file( $tmp, $dest ) && self::private_path_allowed( $dest ) ) {
				chmod( $dest, 0640 );
				$out[ $key ] = array(
					'name' => $name,
					'path' => $dest,
					'type' => $check['type'],
					'size' => $size,
				);
			} else {
				if ( is_file( $dest ) ) {
					wp_delete_file( $dest );
				}
				$errors[] = sprintf( __( '%s could not be stored securely.', 'clever-forms' ), (string) $f['label'] );
			}
		}
		return array( $out, $errors );
	}

	private function display_items( array $fields, array $values, array $uploads = array() ): array {
		$items = array();
		foreach ( $fields as $f ) {
			$t = $f['type'];
			$k = $f['key'];
			if ( $t === 'page' || $t === 'html' || $t === 'hidden' ) {
				continue;
			} if ( $t === 'section' ) {
				$items[] = array(
					'type'  => 'section',
					'label' => $f['label'],
					'value' => '',
				);
				continue; }
			if ( $t === 'name' ) {
				$v = trim( ( $values[ $k . '_first' ] ?? '' ) . ' ' . ( $values[ $k . '_last' ] ?? '' ) );
			} elseif ( $t === 'address' ) {
				$v = implode( ', ', array_filter( array( $values[ $k . '_street' ] ?? '', $values[ $k . '_city' ] ?? '', $values[ $k . '_state' ] ?? '', $values[ $k . '_zip' ] ?? '' ) ) );
			} elseif ( $t === 'file' ) {
				$v = $uploads[ $k ]['name'] ?? '';
			} elseif ( $t === 'signature' ) {
				$v = ! empty( $values[ $k ] ) ? __( 'Signature captured', 'clever-forms' ) : '';
			} elseif ( $t === 'booking' ) {
				$v = trim( ( $values[ $k . '_date' ] ?? '' ) . ' ' . ( $values[ $k . '_time' ] ?? '' ) );
			} else {
				$v = $values[ $k ] ?? '';
				if ( is_array( $v ) ) {
					$v = implode( ', ', $v );
				}
			}
			if ( (string) $v !== '' ) {
				$items[] = array(
					'type'  => 'field',
					'label' => $f['label'],
					'value' => (string) $v,
					'key'   => $k,
				);
			}
		} return $items;
	}

	private function merge_tags( string $text, int $entry, int $form, array $fields, array $values, string $ref ): string {
		$items       = $this->display_items( $fields, $values, get_post_meta( $entry, '_clever_uploads', true ) ?: array() );
		$field_items = array();
		foreach ( $items as $i ) {
			if ( ( $i['type'] ?? '' ) === 'field' ) {
				$field_items[] = $i;
			}
		}
		$render = function ( array $selected ): string {
			$lines = array();
			foreach ( $selected as $i ) {
				$lines[] = $i['label'] . ': ' . $i['value'];
			} return implode( "\n", $lines );
		};
		$repl   = array(
			'{all_fields}' => $render( $field_items ),
			'{entry_id}'   => $ref,
			'{form_title}' => get_the_title( $form ),
			'{date}'       => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'{site_name}'  => get_bloginfo( 'name' ),
		);
		foreach ( $values as $k => $v ) {
			$repl[ '{field:' . $k . '}' ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}
		$text = strtr( $text, $repl );
		$text = preg_replace_callback(
			'/\{all_fields:(include|exclude|filter)\[([^\]]*)\]\}/',
			function ( array $m ) use ( $field_items, $render ): string {
				$mode     = $m[1];
				$keys     = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $m[2] ) ) ) );
				$selected = array_values(
					array_filter(
						$field_items,
						function ( array $item ) use ( $mode, $keys ): bool {
							$in = in_array( sanitize_key( (string) ( $item['key'] ?? '' ) ), $keys, true );
							return 'exclude' === $mode ? ! $in : $in;
						}
					)
				);
				return $render( $selected );
			},
			$text
		) ?? $text;
		return (string) apply_filters( 'clever_forms_merge_tags', $text, $entry, $form, $fields, $values, $ref );
	}

	private function send_notification( int $entry, int $form, array $fields, array $values, array $s, string $ref, string $pdf ): void {
		if ( ! $s['notification_enabled'] ) {
			return;
		} $to = $s['notification_to'];
		if ( $s['notification_route_field'] && isset( $values[ $s['notification_route_field'] ] ) && (string) $values[ $s['notification_route_field'] ] === $s['notification_route_value'] && is_email( $s['notification_route_to'] ) ) {
			$to = $s['notification_route_to'];
		}
		if ( ! is_email( $to ) ) {
			return;
		} $subject = preg_replace( '/[\r\n]+/', ' ', $this->merge_tags( $s['notification_subject'], $entry, $form, $fields, $values, $ref ) );
		$message   = $this->merge_tags( $s['notification_message'], $entry, $form, $fields, $values, $ref );
		$headers   = array();
		foreach ( $values as $k => $v ) {
			if ( str_contains( $k, 'email' ) && is_email( $v ) ) {
				$headers[] = 'Reply-To: ' . $v;
				break; }
		}
		$att = ( $s['pdf_attach'] && $pdf && is_file( $pdf ) ) ? array( $pdf ) : array();
		wp_mail( $to, $subject, $message, $headers, $att );
	}
	private function first_submitter_email( array $fields, array $values ): string {
		foreach ( $fields as $f ) {
			if ( ( $f['type'] ?? '' ) !== 'email' ) {
				continue;
			}
			$value = sanitize_email( (string) ( $values[ $f['key'] ] ?? '' ) );
			if ( is_email( $value ) ) {
				return $value;
			}
		}
		foreach ( $values as $key => $value ) {
			if ( str_contains( (string) $key, 'email' ) && is_string( $value ) ) {
				$email = sanitize_email( $value );
				if ( is_email( $email ) ) {
					return $email;
				}
			}
		}
		return '';
	}

	private function send_confirmation( int $entry, int $form, array $fields, array $values, array $s, string $ref ): void {
		if ( empty( $s['confirmation_enabled'] ) ) {
			return;
		}
		$to = $this->first_submitter_email( $fields, $values );
		if ( ! is_email( $to ) ) {
			return;
		}
		$subject = preg_replace( '/[\r\n]+/', ' ', $this->merge_tags( (string) $s['confirmation_subject'], $entry, $form, $fields, $values, $ref ) );
		$message = $this->merge_tags( (string) $s['confirmation_message'], $entry, $form, $fields, $values, $ref );
		wp_mail( $to, $subject, $message );
	}

	private function send_webhook( int $entry, int $form, array $values, array $s, string $ref ): void {
		if ( ! $s['webhook_enabled'] || ! $s['webhook_url'] ) {
			return;
		} wp_safe_remote_post(
			$s['webhook_url'],
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode(
					array(
						'entry_id'   => $entry,
						'reference'  => $ref,
						'form_id'    => $form,
						'form_title' => get_the_title( $form ),
						'values'     => $values,
					)
				),
			)
		); }

	private function write_pdf( int $entry, bool $permanent ): string {
		$form      = (int) get_post_meta( $entry, '_clever_form_id', true );
		$fields    = get_post_meta( $entry, '_clever_form_snapshot', true ) ?: array();
		$values    = get_post_meta( $entry, '_clever_values', true ) ?: array();
		$uploads   = get_post_meta( $entry, '_clever_uploads', true ) ?: array();
		$s         = get_post_meta( $entry, '_clever_settings_snapshot', true ) ?: $this->settings_defaults();
		$ref       = get_post_meta( $entry, '_clever_reference', true );
		$submitted = get_post_meta( $entry, '_clever_submitted_at', true );
		$items     = $this->display_items( $fields, $values, $uploads );
		$pdf       = new Clever_Forms_PDF();
		$bin       = $pdf->render( get_the_title( $form ), $ref, $submitted, $items, $s['organization_name'] ?? '' );
		$bin       = (string) apply_filters( 'clever_forms_pdf_binary', $bin, $entry, $form, $items, $s, $ref, $submitted );
		$dir       = $this->entry_dir( $entry );
		if ( $dir === '' ) {
			return '';
		}
		$path = trailingslashit( $dir ) . sanitize_file_name( $ref ) . '.pdf';
		if ( file_put_contents( $path, $bin ) === false || ! self::private_path_allowed( $path ) ) {
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			} return ''; }
		chmod( $path, 0640 );
		if ( $permanent ) {
			update_post_meta( $entry, '_clever_pdf', $path );
		} return $path;
	}
	public function download_pdf(): void {
		$entry = (int) ( $_GET['entry'] ?? 0 );
		if ( ! $entry || ! current_user_can( 'edit_post', $entry ) ) {
			wp_die( 'Not permitted.' );
		}
		$path = get_post_meta( $entry, '_clever_pdf', true );
		if ( ! $path || ! is_readable( $path ) ) {
			$path = $this->write_pdf( $entry, false );
		}
		if ( ! $path || ! self::private_path_allowed( (string) $path ) ) {
			wp_die( 'PDF unavailable.' );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
		readfile( $path );
		exit;
	}

	public function entry_box( WP_Post $post ): void {
		$form       = (int) get_post_meta( $post->ID, '_clever_form_id', true );
		$fields     = get_post_meta( $post->ID, '_clever_form_snapshot', true ) ?: array();
		$values     = get_post_meta( $post->ID, '_clever_values', true ) ?: array();
		$uploads    = get_post_meta( $post->ID, '_clever_uploads', true ) ?: array();
		$signatures = get_post_meta( $post->ID, '_clever_signatures', true ) ?: array();
		$ref        = get_post_meta( $post->ID, '_clever_reference', true );
		echo '<p><strong>' . esc_html__( 'Reference:', 'clever-forms' ) . '</strong> ' . esc_html( $ref ) . ' &nbsp; <strong>' . esc_html__( 'Form:', 'clever-forms' ) . '</strong> ' . esc_html( get_the_title( $form ) ) . '</p><table class="widefat striped"><tbody>';
		foreach ( $this->display_items( $fields, $values, $uploads ) as $i ) {
			if ( $i['type'] === 'section' ) {
				echo '<tr><th colspan="2"><h3>' . esc_html( $i['label'] ) . '</h3></th></tr>';
			} else {
				echo '<tr><th class="clever-entry-label">' . esc_html( $i['label'] ) . '</th><td>' . nl2br( esc_html( $i['value'] ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
		if ( is_array( $signatures ) && $signatures ) {
			echo '<h3>' . esc_html__( 'Signatures', 'clever-forms' ) . '</h3><div class="clever-signature-admin-grid">';
			foreach ( $signatures as $field => $signature ) {
				$label = $field;
				foreach ( $fields as $f ) {
					if ( ( $f['key'] ?? '' ) === $field ) {
						$label = (string) $f['label'];
						break;}
				}
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=clever_signature_image&entry=' . $post->ID . '&field=' . rawurlencode( (string) $field ) ), 'clever_signature_' . $post->ID . '_' . $field );
				echo '<figure class="clever-signature-admin"><figcaption><strong>' . esc_html( $label ) . '</strong></figcaption><img src="' . esc_url( $url ) . '" alt="' . esc_attr( sprintf( __( 'Captured signature for %s', 'clever-forms' ), $label ) ) . '"></figure>';
			}
			echo '</div>';
		}
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=clever_download_pdf&entry=' . $post->ID ), 'clever_pdf_' . $post->ID ) ) . '">' . esc_html__( 'Download PDF', 'clever-forms' ) . '</a></p>';
	}

	public function admin_menu(): void {
		add_submenu_page( 'edit.php?post_type=' . self::FORM_CPT, 'Templates', 'Templates', 'edit_posts', 'clever-templates', array( $this, 'templates_page' ) );
		add_submenu_page( 'edit.php?post_type=' . self::FORM_CPT, 'Add-Ons', 'Add-Ons', 'manage_options', 'clever-addons', array( 'Clever_Forms_Addons', 'admin_page' ) );
		add_submenu_page( 'edit.php?post_type=' . self::FORM_CPT, 'Import / Export', 'Import / Export', 'manage_options', 'clever-import-export', array( $this, 'import_export_page' ) );
		add_submenu_page( 'edit.php?post_type=' . self::FORM_CPT, 'Settings', 'Settings', 'manage_options', 'clever-settings', array( $this, 'plugin_settings_page' ) ); }

	public function templates_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		} if ( isset( $_POST['clever_create_template'] ) && check_admin_referer( 'clever_template' ) ) {
			$key       = sanitize_key( $_POST['template_key'] ?? '' );
			$templates = $this->templates();
			if ( isset( $templates[ $key ] ) ) {
				$t  = $templates[ $key ];
				$id = wp_insert_post(
					array(
						'post_type'   => self::FORM_CPT,
						'post_status' => 'draft',
						'post_title'  => $t['title'],
					)
				);
				if ( ! is_wp_error( $id ) ) {
					update_post_meta( $id, '_clever_fields', $t['fields'] );
					update_post_meta( $id, '_clever_settings', wp_parse_args( $t['settings'] ?? array(), $this->settings_defaults() ) );
					echo '<div class="notice notice-success"><p>Template created. <a href="' . esc_url( get_edit_post_link( $id ) ) . '">Edit form</a></p></div>'; }
			}
		}
		echo '<div class="wrap"><h1>Form Templates</h1><p>Clever Forms templates for common organizational workflows.</p><div class="clever-template-grid">';
		foreach ( $this->templates() as $k => $t ) {
			echo '<div class="clever-template-card"><h2>' . esc_html( $t['title'] ) . '</h2><p>' . esc_html( $t['description'] ) . '</p><form method="post">';
			wp_nonce_field( 'clever_template' );
			echo '<input type="hidden" name="template_key" value="' . esc_attr( $k ) . '"><button class="button button-primary" name="clever_create_template" value="1">Use Template</button></form></div>';
		} echo '</div></div>';
	}

	private function templates(): array {
		$F = fn( $type, $key, $label, $opts = array() )=>array_merge(
			array(
				'id'                 => wp_generate_uuid4(),
				'type'               => $type,
				'key'                => $key,
				'label'              => $label,
				'description'        => '',
				'placeholder'        => '',
				'required'           => 0,
				'width'              => '100',
				'choices'            => array(),
				'default'            => '',
				'html'               => '',
				'condition_field'    => '',
				'condition_operator' => 'equals',
				'condition_value'    => '',
				'accept'             => 'pdf,doc,docx,jpg,jpeg,png',
			),
			$opts
		);
		return array(
			'contact'                      => array(
				'title'       => 'Contact Form',
				'description' => 'General contact form with topic routing.',
				'fields'      => array(
					$F(
						'name',
						'name',
						'Your Name',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'email',
						'email',
						'Email',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'select',
						'topic',
						'Topic',
						array(
							'choices'  => array( 'General', 'Billing', 'Support' ),
							'required' => 1,
						)
					),
					$F( 'textarea', 'message', 'Message', array( 'required' => 1 ) ),
				),
			),
			'employment'                   => array(
				'title'       => 'Employment Application',
				'description' => 'Multi-page application with conditional sections, uploads, and signature.',
				'fields'      => array(
					$F( 'section', 'personal_section', 'Personal Information' ),
					$F(
						'name',
						'applicant_name',
						'Full Name',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'email',
						'email',
						'Email',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'phone',
						'phone',
						'Phone',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F( 'address', 'address', 'Address', array( 'width' => '50' ) ),
					$F( 'page', 'page2', 'Page 2' ),
					$F( 'section', 'position_section', 'Position' ),
					$F( 'text', 'position', 'Position Applied For', array( 'required' => 1 ) ),
					$F(
						'select',
						'work_auth',
						'Authorized to work in the U.S.?',
						array(
							'choices'  => array( 'Yes', 'No' ),
							'required' => 1,
						)
					),
					$F( 'page', 'page3', 'Page 3' ),
					$F( 'section', 'documents', 'Documents' ),
					$F( 'file', 'resume', 'Resume', array( 'accept' => 'pdf,doc,docx' ) ),
					$F( 'signature', 'signature', 'Electronic Signature', array( 'required' => 1 ) ),
				),
				'settings'    => array(
					'shortcode_alias' => 'clever_job_application',
					'pdf_enabled'     => 1,
					'pdf_save'        => 1,
					'pdf_attach'      => 1,
				),
			),
			'behavioral_health_employment' => array(
				'title'       => 'Behavioral Health Employment Application',
				'description' => 'Comprehensive behavioral health employment application with personal information, emergency contact, military service, position and licensing, education, professional references, three employment-history sections, document uploads, certification, and electronic signature.',
				'fields'      => array(
					$F( 'section', 'personal_section', 'Your Personal Information' ),
					$F( 'name', 'applicant_name', 'Your Name', array( 'required' => 1 ) ),
					$F( 'address', 'home_address', 'Your Home Address', array( 'required' => 1 ) ),
					$F(
						'email',
						'email',
						'Your Email',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'phone',
						'phone',
						'Your Phone Number',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),

					$F( 'section', 'emergency_section', 'Emergency Contact Information' ),
					$F(
						'text',
						'emergency_contact_name',
						'Emergency Contact Name',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'phone',
						'emergency_contact_phone',
						'Emergency Contact Phone Number',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F( 'select', 'best_time_to_call', 'Best Time To Call You', array( 'choices' => array( 'Morning', 'Afternoon', 'Early Evening', 'Late Evening' ) ) ),

					$F( 'section', 'military_section', 'Military Service' ),
					$F(
						'radio',
						'served_military',
						'Have you ever served in the military?',
						array(
							'choices'  => array( 'Yes', 'No' ),
							'required' => 1,
						)
					),
					$F(
						'text',
						'military_branch',
						'Branch',
						array(
							'condition_field'    => 'served_military',
							'condition_operator' => 'equals',
							'condition_value'    => 'Yes',
							'width'              => '50',
						)
					),
					$F(
						'date',
						'military_start_date',
						'Start Date',
						array(
							'condition_field'    => 'served_military',
							'condition_operator' => 'equals',
							'condition_value'    => 'Yes',
							'width'              => '50',
						)
					),
					$F(
						'date',
						'military_end_date',
						'End Date',
						array(
							'condition_field'    => 'served_military',
							'condition_operator' => 'equals',
							'condition_value'    => 'Yes',
							'width'              => '50',
						)
					),
					$F(
						'text',
						'military_rank_discharge',
						'Rank at Discharge',
						array(
							'condition_field'    => 'served_military',
							'condition_operator' => 'equals',
							'condition_value'    => 'Yes',
							'width'              => '50',
						)
					),
					$F(
						'text',
						'military_discharge_type',
						'Type of Discharge',
						array(
							'condition_field'    => 'served_military',
							'condition_operator' => 'equals',
							'condition_value'    => 'Yes',
						)
					),

					$F( 'page', 'position_page', 'Position and Qualifications' ),
					$F( 'section', 'position_section', 'Position Requested' ),
					$F( 'text', 'position', 'Position You’re Applying For', array( 'required' => 1 ) ),
					$F( 'text', 'location_interest', 'Location of Interest' ),
					$F( 'textarea', 'professional_licenses', 'Professional Licenses' ),
					$F(
						'radio',
						'work_authorized',
						'Are you authorized to work in the U.S.?',
						array(
							'choices'  => array( 'Yes', 'No' ),
							'required' => 1,
						)
					),
					$F( 'textarea', 'hours_available', 'Hours You Are Available for Work' ),
					$F( 'radio', 'received_services', 'Have you ever received services in this company?', array( 'choices' => array( 'Yes', 'No' ) ) ),
					$F( 'radio', 'worked_company', 'Have you ever worked for this company?', array( 'choices' => array( 'Yes', 'No' ) ) ),

					$F( 'section', 'education_section', 'Education' ),
					$F( 'textarea', 'degree_1', 'Degree 1' ),
					$F( 'textarea', 'degree_2', 'Degree 2' ),
					$F( 'textarea', 'degree_3', 'Degree 3' ),

					$F( 'page', 'references_page', 'Professional References' ),
					$F( 'section', 'references_section', 'Professional References' ),
					$F( 'text', 'reference_1_name', 'Name of Professional Reference 1', array( 'required' => 1 ) ),
					$F( 'text', 'reference_1_company', 'Company', array( 'width' => '50' ) ),
					$F(
						'select',
						'reference_1_years',
						'How long have you known this person?',
						array(
							'choices' => array( 'Less than a year', '1 to 3 years', '4 to 6 years', '7 to 10 years', 'More than 10 years' ),
							'width'   => '50',
						)
					),
					$F( 'email', 'reference_1_email', 'Email', array( 'width' => '50' ) ),
					$F( 'phone', 'reference_1_phone', 'Phone Number', array( 'width' => '50' ) ),
					$F( 'section', 'reference_2_section', 'Professional Reference 2' ),
					$F( 'text', 'reference_2_name', 'Name of Professional Reference 2' ),
					$F( 'text', 'reference_2_company', 'Company', array( 'width' => '50' ) ),
					$F(
						'select',
						'reference_2_years',
						'How long have you known this person?',
						array(
							'choices' => array( 'Less than a year', '1 to 3 years', '4 to 6 years', '7 to 10 years', 'More than 10 years' ),
							'width'   => '50',
						)
					),
					$F( 'email', 'reference_2_email', 'Email', array( 'width' => '50' ) ),
					$F( 'phone', 'reference_2_phone', 'Phone Number', array( 'width' => '50' ) ),
					$F( 'section', 'reference_3_section', 'Professional Reference 3' ),
					$F( 'text', 'reference_3_name', 'Name of Professional Reference 3' ),
					$F( 'text', 'reference_3_company', 'Company', array( 'width' => '50' ) ),
					$F(
						'select',
						'reference_3_years',
						'How long have you known this person?',
						array(
							'choices' => array( 'Less than a year', '1 to 3 years', '4 to 6 years', '7 to 10 years', 'More than 10 years' ),
							'width'   => '50',
						)
					),
					$F( 'email', 'reference_3_email', 'Email', array( 'width' => '50' ) ),
					$F( 'phone', 'reference_3_phone', 'Phone Number', array( 'width' => '50' ) ),

					$F( 'page', 'employment_page', 'Employment History' ),
					$F( 'section', 'employment_section', 'Employment History' ),
					$F( 'section', 'employment_1_section', 'Most Recent Employment' ),
					$F(
						'text',
						'employment_1_company',
						'Company Name',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F( 'text', 'employment_1_supervisor', 'Supervisor Name', array( 'width' => '50' ) ),
					$F( 'phone', 'employment_1_phone', 'Phone Number', array( 'width' => '50' ) ),
					$F( 'email', 'employment_1_email', 'Email', array( 'width' => '50' ) ),
					$F( 'address', 'employment_1_address', 'Address' ),
					$F( 'text', 'employment_1_title', 'Job Title', array( 'width' => '50' ) ),
					$F( 'text', 'employment_1_salary', 'Salary', array( 'width' => '50' ) ),
					$F( 'textarea', 'employment_1_responsibilities', 'Responsibilities' ),
					$F( 'date', 'employment_1_start', 'Initial Date', array( 'width' => '50' ) ),
					$F( 'date', 'employment_1_end', 'End Date', array( 'width' => '50' ) ),
					$F( 'textarea', 'employment_1_reason', 'Reason for Leaving' ),
					$F( 'radio', 'employment_1_contact', 'Can we contact this previous employer for a reference?', array( 'choices' => array( 'Yes', 'No' ) ) ),

					$F( 'section', 'employment_2_section', 'Second Most Recent Employment' ),
					$F( 'text', 'employment_2_company', 'Company Name', array( 'width' => '50' ) ),
					$F( 'text', 'employment_2_supervisor', 'Supervisor Name', array( 'width' => '50' ) ),
					$F( 'phone', 'employment_2_phone', 'Phone Number', array( 'width' => '50' ) ),
					$F( 'email', 'employment_2_email', 'Email', array( 'width' => '50' ) ),
					$F( 'address', 'employment_2_address', 'Address' ),
					$F( 'text', 'employment_2_title', 'Job Title', array( 'width' => '50' ) ),
					$F( 'text', 'employment_2_salary', 'Salary', array( 'width' => '50' ) ),
					$F( 'textarea', 'employment_2_responsibilities', 'Responsibilities' ),
					$F( 'date', 'employment_2_start', 'Initial Date', array( 'width' => '50' ) ),
					$F( 'date', 'employment_2_end', 'End Date', array( 'width' => '50' ) ),
					$F( 'textarea', 'employment_2_reason', 'Reason for Leaving' ),
					$F( 'radio', 'employment_2_contact', 'Can we contact this previous employer for a reference?', array( 'choices' => array( 'Yes', 'No' ) ) ),

					$F( 'section', 'employment_3_section', 'Next Most Recent Employment' ),
					$F( 'text', 'employment_3_company', 'Company Name', array( 'width' => '50' ) ),
					$F( 'text', 'employment_3_supervisor', 'Supervisor Name', array( 'width' => '50' ) ),
					$F( 'phone', 'employment_3_phone', 'Phone Number', array( 'width' => '50' ) ),
					$F( 'email', 'employment_3_email', 'Email', array( 'width' => '50' ) ),
					$F( 'address', 'employment_3_address', 'Address' ),
					$F( 'text', 'employment_3_title', 'Job Title', array( 'width' => '50' ) ),
					$F( 'text', 'employment_3_salary', 'Salary', array( 'width' => '50' ) ),
					$F( 'textarea', 'employment_3_responsibilities', 'Responsibilities' ),
					$F( 'date', 'employment_3_start', 'Initial Date', array( 'width' => '50' ) ),
					$F( 'date', 'employment_3_end', 'End Date', array( 'width' => '50' ) ),
					$F( 'textarea', 'employment_3_reason', 'Reason for Leaving' ),
					$F( 'radio', 'employment_3_contact', 'Can we contact this previous employer for a reference?', array( 'choices' => array( 'Yes', 'No' ) ) ),

					$F( 'page', 'documents_page', 'Documents and Signature' ),
					$F( 'section', 'documents_section', 'Resume & Cover Letter' ),
					$F(
						'file',
						'resume',
						'Upload Resume',
						array(
							'required' => 1,
							'accept'   => 'pdf,doc,docx',
						)
					),
					$F( 'file', 'cover_letter', 'Upload Cover Letter (Optional)', array( 'accept' => 'pdf,doc,docx' ) ),
					$F( 'file', 'professional_documents', 'Upload Other Professional Documents (Optional)', array( 'accept' => 'pdf,doc,docx,jpg,jpeg,png' ) ),
					$F( 'section', 'signature_section', 'Disclaimer and Signature' ),
					$F( 'html', 'employment_disclaimer', 'Employment Application Disclaimer', array( 'html' => '<p>I certify that my answers are true and complete to the best of my knowledge.</p><p>If this application leads to employment, I understand that false or misleading information in my application or interview may result in my release.</p>' ) ),
					$F(
						'consent',
						'certification',
						'Certification',
						array(
							'required'    => 1,
							'description' => 'I certify that the information provided in this application is true and complete to the best of my knowledge.',
						)
					),
					$F( 'signature', 'signature', 'Signature', array( 'required' => 1 ) ),
					$F( 'date', 'signature_date', 'Today’s Date', array( 'required' => 1 ) ),
				),
				'settings'    => array(
					'shortcode_alias'      => 'behavioral_health_employment_application',
					'button_text'          => 'Submit Application',
					'success_message'      => 'Thank you. Your employment application has been submitted.',
					'preview'              => 1,
					'save_continue'        => 1,
					'auto_save'            => 1,
					'pdf_enabled'          => 1,
					'pdf_save'             => 1,
					'pdf_attach'           => 1,
					'notification_subject' => 'Employment application completed — {field:applicant_name_first} {field:applicant_name_last} — {field:position}',
					'notification_message' => "{field:applicant_name_first} {field:applicant_name_last} has completed an application for the {field:position} position on {date}.\n\nReference: {entry_id}\n\n{all_fields}",
					'confirmation_subject' => 'We received your employment application',
					'confirmation_message' => "Thank you, {field:applicant_name_first}.\n\nWe received your employment application for {field:position}.\n\nReference: {entry_id}",
				),
			),
			'volunteer'                    => array(
				'title'       => 'Volunteer Signup',
				'description' => 'Volunteer interests, availability, emergency contact, and consent.',
				'fields'      => array(
					$F( 'name', 'name', 'Name', array( 'required' => 1 ) ),
					$F(
						'email',
						'email',
						'Email',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F( 'phone', 'phone', 'Phone', array( 'width' => '50' ) ),
					$F( 'checkbox', 'interests', 'Volunteer Interests', array( 'choices' => array( 'Events', 'Youth Programs', 'Food Distribution', 'Administration' ) ) ),
					$F( 'textarea', 'availability', 'Availability' ),
					$F(
						'consent',
						'consent',
						'Volunteer Agreement',
						array(
							'required'    => 1,
							'description' => 'I agree to follow organization policies.',
						)
					),
				),
			),
			'grant'                        => array(
				'title'       => 'Grant Application',
				'description' => 'Organization and project information with document uploads.',
				'fields'      => array( $F( 'section', 'org_section', 'Organization' ), $F( 'text', 'organization', 'Organization Name', array( 'required' => 1 ) ), $F( 'email', 'contact_email', 'Contact Email', array( 'required' => 1 ) ), $F( 'section', 'project_section', 'Project' ), $F( 'text', 'project_title', 'Project Title', array( 'required' => 1 ) ), $F( 'textarea', 'project_summary', 'Project Summary', array( 'required' => 1 ) ), $F( 'number', 'amount', 'Amount Requested', array( 'required' => 1 ) ), $F( 'file', 'budget', 'Budget', array( 'accept' => 'pdf,xls,xlsx' ) ), $F( 'file', 'proposal', 'Proposal', array( 'accept' => 'pdf,doc,docx' ) ) ),
			),
			'event'                        => array(
				'title'       => 'Event Registration',
				'description' => 'Event RSVP with attendee preferences.',
				'fields'      => array(
					$F( 'name', 'name', 'Attendee Name', array( 'required' => 1 ) ),
					$F( 'email', 'email', 'Email', array( 'required' => 1 ) ),
					$F(
						'select',
						'ticket',
						'Registration Type',
						array(
							'choices'  => array( 'General', 'Student', 'VIP' ),
							'required' => 1,
						)
					),
					$F( 'select', 'meal', 'Meal Preference', array( 'choices' => array( 'No Preference', 'Vegetarian', 'Vegan', 'Gluten-Free' ) ) ),
					$F( 'textarea', 'accessibility', 'Accessibility or Accommodation Needs' ),
				),
			),
			'student'                      => array(
				'title'       => 'Student Enrollment',
				'description' => 'Student enrollment with emergency contact and program selection.',
				'fields'      => array(
					$F( 'name', 'student_name', 'Student Name', array( 'required' => 1 ) ),
					$F( 'date', 'dob', 'Date of Birth', array( 'required' => 1 ) ),
					$F(
						'select',
						'program',
						'Program',
						array(
							'choices'  => array( 'Program A', 'Program B', 'Program C' ),
							'required' => 1,
						)
					),
					$F( 'section', 'emergency', 'Emergency Contact' ),
					$F(
						'text',
						'emergency_name',
						'Contact Name',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'phone',
						'emergency_phone',
						'Contact Phone',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
				),
			),
			'project'                      => array(
				'title'       => 'Project Inquiry',
				'description' => 'Project scope, service needs, budget, and timeline.',
				'fields'      => array( $F( 'name', 'name', 'Contact Name', array( 'required' => 1 ) ), $F( 'email', 'email', 'Email', array( 'required' => 1 ) ), $F( 'text', 'organization', 'Organization' ), $F( 'checkbox', 'services', 'Services', array( 'choices' => array( 'Strategy', 'Web Development', 'Automation', 'AI Consulting' ) ) ), $F( 'select', 'budget', 'Budget Range', array( 'choices' => array( 'Under $5,000', '$5,000–$15,000', '$15,000–$50,000', '$50,000+' ) ) ), $F( 'textarea', 'scope', 'Project Description', array( 'required' => 1 ) ) ),
			),
			'support'                      => array(
				'title'       => 'Website Support Request',
				'description' => 'Support request with priority and attachment.',
				'fields'      => array(
					$F(
						'name',
						'name',
						'Name',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'email',
						'email',
						'Email',
						array(
							'required' => 1,
							'width'    => '50',
						)
					),
					$F(
						'select',
						'priority',
						'Priority',
						array(
							'choices'  => array( 'Low', 'Medium', 'High', 'Critical' ),
							'required' => 1,
						)
					),
					$F( 'select', 'category', 'Category', array( 'choices' => array( 'Content', 'Bug', 'Access', 'Performance', 'Other' ) ) ),
					$F( 'textarea', 'details', 'Issue Details', array( 'required' => 1 ) ),
					$F( 'file', 'screenshot', 'Screenshot', array( 'accept' => 'jpg,jpeg,png,pdf' ) ),
				),
			),
			'lead'                         => array(
				'title'       => 'Lead Generation',
				'description' => 'B2B lead capture and qualification.',
				'fields'      => array( $F( 'name', 'name', 'Name', array( 'required' => 1 ) ), $F( 'email', 'email', 'Work Email', array( 'required' => 1 ) ), $F( 'text', 'company', 'Company', array( 'required' => 1 ) ), $F( 'select', 'company_size', 'Company Size', array( 'choices' => array( '1–10', '11–50', '51–250', '251–1,000', '1,000+' ) ) ), $F( 'select', 'timeline', 'Timeline', array( 'choices' => array( 'Immediately', '1–3 months', '3–6 months', '6+ months' ) ) ), $F( 'textarea', 'need', 'What are you trying to solve?' ) ),
			),
			'survey'                       => array(
				'title'       => 'Customer Survey',
				'description' => 'Simple customer satisfaction survey.',
				'fields'      => array(
					$F(
						'radio',
						'rating',
						'Overall Satisfaction',
						array(
							'choices'  => array( '1', '2', '3', '4', '5' ),
							'required' => 1,
						)
					),
					$F(
						'radio',
						'recommend',
						'How likely are you to recommend us?',
						array(
							'choices'  => array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10' ),
							'required' => 1,
						)
					),
					$F( 'textarea', 'feedback', 'What should we improve?' ),
				),
			),
			'newsletter'                   => array(
				'title'       => 'Newsletter Signup',
				'description' => 'Simple subscription and interests form.',
				'fields'      => array(
					$F( 'email', 'email', 'Email', array( 'required' => 1 ) ),
					$F( 'checkbox', 'topics', 'Topics', array( 'choices' => array( 'News', 'Events', 'Resources', 'Opportunities' ) ) ),
					$F(
						'consent',
						'consent',
						'Email Consent',
						array(
							'required'    => 1,
							'description' => 'I agree to receive email communications.',
						)
					),
				),
			),
		);
	}

	public function import_export_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		} echo '<div class="wrap"><h1>Import / Export</h1><h2>Import Clever Forms JSON</h2><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="clever_import_form">';
		wp_nonce_field( 'clever_import_form' );
		echo '<input type="file" name="form_json" accept="application/json,.json" required> <button class="button button-primary">Import</button></form><h2>Export</h2><p>Use the Export link in the Forms list. Entries can be exported from each form row.</p></div>'; }
	public function export_form(): void {
		$id = (int) ( $_GET['form'] ?? 0 );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Not permitted.' );
		} check_admin_referer( 'clever_export_' . $id );
		$data = array(
			'format'   => 'clever-forms',
			'version'  => CLEVER_FORMS_VERSION,
			'title'    => get_the_title( $id ),
			'fields'   => get_post_meta( $id, '_clever_fields', true ) ?: array(),
			'settings' => $this->get_form_settings( $id ),
		);
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_the_title( $id ) ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit; }
	public function import_form(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not permitted.', 'clever-forms' ) );
		}
		check_admin_referer( 'clever_import_form' );
		if ( empty( $_FILES['form_json']['tmp_name'] ) || ! isset( $_FILES['form_json']['error'] ) ) {
			wp_die( esc_html__( 'Missing file.', 'clever-forms' ) );
		}
		$upload = $_FILES['form_json'];
		if ( (int) $upload['error'] !== UPLOAD_ERR_OK || (int) ( $upload['size'] ?? 0 ) > 1048576 || ! is_uploaded_file( $upload['tmp_name'] ) ) {
			wp_die( esc_html__( 'The import file is invalid or too large.', 'clever-forms' ) );
		}
		$filename = sanitize_file_name( wp_unslash( $upload['name'] ?? '' ) );
		if ( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) !== 'json' ) {
			wp_die( esc_html__( 'Please upload a JSON file.', 'clever-forms' ) );
		}
		$contents = file_get_contents( $upload['tmp_name'] );
		if ( false === $contents ) {
			wp_die( esc_html__( 'Could not read the import file.', 'clever-forms' ) );
		}
		$data = json_decode( $contents, true, 64 );
		if ( ! is_array( $data ) || ! in_array( ( $data['format'] ?? '' ), array( 'clever-forms', 'clever-forms' ), true ) ) {
			wp_die( esc_html__( 'Invalid Clever Forms JSON.', 'clever-forms' ) );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::FORM_CPT,
				'post_status' => 'draft',
				'post_title'  => sanitize_text_field( $data['title'] ?? __( 'Imported Form', 'clever-forms' ) ),
			)
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html__( 'Import failed.', 'clever-forms' ) );
		}
		update_post_meta( $id, '_clever_fields', $this->sanitize_fields( is_array( $data['fields'] ?? null ) ? $data['fields'] : array() ) );
		update_post_meta( $id, '_clever_settings', $this->sanitize_form_settings( is_array( $data['settings'] ?? null ) ? $data['settings'] : array() ) );
		wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
		exit;
	}

	public function export_entries(): void {
		$form = (int) ( $_GET['form'] ?? 0 );
		if ( ! $form || ! current_user_can( 'edit_post', $form ) ) {
			wp_die( 'Not permitted.' );
		} check_admin_referer( 'clever_entries_' . $form );
		$fields = get_post_meta( $form, '_clever_fields', true ) ?: array();
		$keys   = array();
		foreach ( $fields as $f ) {
			if ( ! in_array( $f['type'], array( 'section', 'page', 'html' ), true ) ) {
				$keys[ $f['key'] ] = $f['label'];
			}
		} $entries = get_posts(
			array(
				'post_type'   => self::ENTRY_CPT,
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_key'    => '_clever_form_id',
				'meta_value'  => $form,
			)
		);
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_the_title( $form ) ) . '-entries.csv"' );
		$fh = fopen( 'php://output', 'w' );
		fputcsv( $fh, array_merge( array( 'Reference', 'Submitted' ), array_values( $keys ) ) );
		foreach ( $entries as $e ) {
			$v   = get_post_meta( $e->ID, '_clever_values', true ) ?: array();
			$row = array( get_post_meta( $e->ID, '_clever_reference', true ), get_post_meta( $e->ID, '_clever_submitted_at', true ) );
			foreach ( array_keys( $keys ) as $k ) {
				$x     = $v[ $k ] ?? '';
				$cell  = is_array( $x ) ? implode( '; ', $x ) : (string) $x;
				$row[] = $this->csv_safe( $cell );
			}fputcsv( $fh, $row );
		} fclose( $fh );
		exit; }

	public function form_columns( array $c ): array {
		return array(
			'cb'        => $c['cb'],
			'title'     => 'Form',
			'shortcode' => 'Shortcode',
			'entries'   => 'Entries',
			'date'      => 'Date',
		); }
	public function form_column( string $col, int $id ): void {
		if ( $col === 'shortcode' ) {
			$s = $this->get_form_settings( $id );
			echo '<code>[clever_form id="' . esc_html( (string) $id ) . '"]</code>';
			if ( $s['shortcode_alias'] ) {
				echo '<br><code>[' . esc_html( $s['shortcode_alias'] ) . ']</code>';
			}
		} elseif ( $col === 'entries' ) {
			$q = new WP_Query(
				array(
					'post_type'      => self::ENTRY_CPT,
					'post_status'    => 'private',
					'posts_per_page' => 1,
					'meta_key'       => '_clever_form_id',
					'meta_value'     => $id,
				)
			);
			echo esc_html( (string) $q->found_posts ); } }
	public function entry_columns( array $c ): array {
		return array(
			'cb'        => $c['cb'],
			'title'     => 'Entry',
			'form'      => 'Form',
			'reference' => 'Reference',
			'submitted' => 'Submitted',
		); }
	public function entry_column( string $col, int $id ): void {
		if ( $col === 'form' ) {
			$f = (int) get_post_meta( $id, '_clever_form_id', true );
			echo esc_html( get_the_title( $f ) );
		} elseif ( $col === 'reference' ) {
			echo esc_html( get_post_meta( $id, '_clever_reference', true ) );
		} elseif ( $col === 'submitted' ) {
			echo esc_html( get_post_meta( $id, '_clever_submitted_at', true ) );
		} }
	public function row_actions( array $a, WP_Post $p ): array {
		if ( $p->post_type === self::FORM_CPT ) {
			$a['export']      = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=clever_export_form&form=' . $p->ID ), 'clever_export_' . $p->ID ) ) . '">Export JSON</a>';
			$a['entries_csv'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=clever_export_entries&form=' . $p->ID ), 'clever_entries_' . $p->ID ) ) . '">Export Entries</a>';
		} return $a; }

	private function submission_rate_allowed( int $form_id ): bool {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key    = 'clever_forms_rate_' . md5( $form_id . '|' . $remote . '|' . wp_salt( 'nonce' ) );
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 10 );
		return true;
	}

	private function csv_safe( string $value ): string {
		if ( $value !== '' && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	public function before_delete_post( int $post_id ): void {
		if ( get_post_type( $post_id ) !== self::ENTRY_CPT ) {
			return;
		}
		$uploads = get_post_meta( $post_id, '_clever_uploads', true );
		if ( is_array( $uploads ) ) {
			foreach ( $uploads as $upload ) {
				$path = is_array( $upload ) ? ( $upload['path'] ?? '' ) : '';
				if ( $path && is_file( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}
		$signatures = get_post_meta( $post_id, '_clever_signatures', true );
		if ( is_array( $signatures ) ) {
			foreach ( $signatures as $signature ) {
				$path = is_array( $signature ) ? ( $signature['path'] ?? '' ) : '';
				if ( $path && is_file( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}
		$pdf = get_post_meta( $post_id, '_clever_pdf', true );
		if ( $pdf && is_file( $pdf ) ) {
			wp_delete_file( $pdf );
		}
		$dir = $this->entry_dir( $post_id );
		if ( is_dir( $dir ) ) {
			@rmdir( $dir );
		}
	}

	public function plugin_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$delete = (bool) get_option( 'clever_forms_delete_data_on_uninstall', false );
		echo '<div class="wrap"><h1>' . esc_html__( 'Clever Forms Settings', 'clever-forms' ) . '</h1>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="clever_forms_save_settings">';
		wp_nonce_field( 'clever_forms_save_settings' );
		echo '<h2>' . esc_html__( 'Data Retention', 'clever-forms' ) . '</h2>';
		echo '<p><label><input type="checkbox" name="delete_data" value="1" ' . checked( $delete, true, false ) . '> ' . esc_html__( 'Delete all forms, entries, settings, and private files when the plugin is uninstalled.', 'clever-forms' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Deactivation never deletes data. Uninstall deletion occurs only when this option is enabled.', 'clever-forms' ) . '</p>';
		submit_button( __( 'Save Settings', 'clever-forms' ) );
		echo '</form></div>';
	}

	public function save_plugin_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not permitted.', 'clever-forms' ) );
		}
		check_admin_referer( 'clever_forms_save_settings' );
		update_option( 'clever_forms_delete_data_on_uninstall', ! empty( $_POST['delete_data'] ), false );
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => self::FORM_CPT,
					'page'      => 'clever-settings',
					'updated'   => '1',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	public function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'Clever Forms may store information submitted through forms, including personal information and uploaded files, in the WordPress database and a protected uploads directory. Depending on form configuration, submitted information may also be sent by email or transmitted to a webhook or enabled integration. Site administrators control these destinations and retention settings.', 'clever-forms' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Clever Forms', 'clever-forms' ), wp_kses_post( $content ) );
	}

	public function register_privacy_exporter( array $exporters ): array {
		$exporters['clever-forms'] = array(
			'exporter_friendly_name' => __( 'Clever Forms Entries', 'clever-forms' ),
			'callback'               => array( $this, 'privacy_exporter' ),
		);
		return $exporters;
	}

	public function register_privacy_eraser( array $erasers ): array {
		$erasers['clever-forms'] = array(
			'eraser_friendly_name' => __( 'Clever Forms Entries', 'clever-forms' ),
			'callback'             => array( $this, 'privacy_eraser' ),
		);
		return $erasers;
	}

	private function entries_matching_email( string $email, int $page ): array {
		$query   = new WP_Query(
			array(
				'post_type'      => self::ENTRY_CPT,
				'post_status'    => 'private',
				'posts_per_page' => 50,
				'paged'          => max( 1, $page ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$matched = array();
		foreach ( $query->posts as $entry_id ) {
			$values = get_post_meta( $entry_id, '_clever_values', true );
			if ( ! is_array( $values ) ) {
				continue;
			}
			foreach ( $values as $key => $value ) {
				if ( str_contains( (string) $key, 'email' ) && is_string( $value ) && strtolower( $value ) === strtolower( $email ) ) {
					$matched[] = (int) $entry_id;
					break; }
			}
		}
		return array(
			'ids'  => $matched,
			'done' => $page >= (int) $query->max_num_pages,
		);
	}

	public function privacy_exporter( string $email_address, int $page = 1 ): array {
		$result = $this->entries_matching_email( sanitize_email( $email_address ), $page );
		$data   = array();
		foreach ( $result['ids'] as $entry_id ) {
			$fields  = get_post_meta( $entry_id, '_clever_form_snapshot', true ) ?: array();
			$values  = get_post_meta( $entry_id, '_clever_values', true ) ?: array();
			$uploads = get_post_meta( $entry_id, '_clever_uploads', true ) ?: array();
			$items   = array();
			foreach ( $this->display_items( $fields, $values, $uploads ) as $item ) {
				if ( $item['type'] === 'field' ) {
					$items[] = array(
						'name'  => $item['label'],
						'value' => $item['value'],
					);
				}
			}
			$data[] = array(
				'group_id'    => 'clever-forms',
				'group_label' => __( 'Clever Forms Entries', 'clever-forms' ),
				'item_id'     => 'clever-form-entry-' . $entry_id,
				'data'        => $items,
			);
		}
		return array(
			'data' => $data,
			'done' => $result['done'],
		);
	}

	public function privacy_eraser( string $email_address, int $page = 1 ): array {
		$result  = $this->entries_matching_email( sanitize_email( $email_address ), $page );
		$removed = false;
		foreach ( $result['ids'] as $entry_id ) {
			if ( wp_delete_post( $entry_id, true ) ) {
				$removed = true;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $result['done'],
		);
	}

	public function rest_routes(): void {
		register_rest_route(
			'clever-forms/v1',
			'/forms/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => function ( WP_REST_Request $request ): bool {
					$id = (int) $request['id'];
					return $id > 0 && get_post_type( $id ) === self::FORM_CPT && current_user_can( 'edit_post', $id );
				},
				'callback'            => function ( WP_REST_Request $request ) {
					$id = (int) $request['id'];
					return rest_ensure_response(
						array(
							'id'       => $id,
							'title'    => get_the_title( $id ),
							'fields'   => get_post_meta( $id, '_clever_fields', true ) ?: array(),
							'settings' => $this->get_form_settings( $id ),
						)
					);
				},
			)
		);
	}
}
