# Clever Forms developer notes

## Shortcode

`[clever_form id="123"]`

A published form may also define its own shortcode alias.

## Submission hook

```php
add_action( 'clever_forms_after_submission', function ( $entry_id, $form_id, $values ) {
    // Queue integration work. Avoid slow remote API calls in the visitor request.
}, 10, 3 );
```

## Validation

Use `clever_forms_validation_errors` to append validation errors before an entry is saved.

## Sanitized values

Use `clever_forms_sanitized_values` to normalize values after Core sanitization.

## Add-ons

External plugins can call `clever_forms_register_addon()` to appear on the Clever Forms Add-Ons screen.

Network integrations should use the WordPress HTTP API, explicit opt-in configuration, safe remote request functions where appropriate, and background processing for retryable work.
