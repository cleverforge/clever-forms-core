# Clever Forms commercial add-on model

Clever Forms Core is the shared form platform. Paid capabilities should be shipped as separate WordPress plugins or delivered through a substantive external service.

## Recommended commercial structure

1. **Clever Forms Core**
   - Free or freely licensed WordPress plugin.
   - No local Core feature is disabled because a subscription expires.
   - Defines the form, entry, hook, REST, and add-on interfaces.

2. **Commercial add-on plugin**
   - Separate ZIP, such as `clever-connect-salesforce.zip`.
   - Declares Clever Forms as a dependency.
   - Registers itself with `clever_forms_register_addon()`.
   - Owns its settings, credentials, queues, logs, and licensing client.

3. **CleverForge subscription service**
   - Issues activation codes.
   - Tracks subscription status and allowed site activations.
   - Provides update metadata and authenticated package downloads.
   - Can provide substantive cloud functionality when appropriate.

4. **Customer portal**
   - Purchase and renewal management.
   - License/activation management.
   - Downloads and release history.
   - Support entitlement.

## Add-on API

An add-on can register itself after both plugins are loaded:

```php
clever_forms_register_addon(
    [
        'slug'        => 'clever-connect-example',
        'name'        => 'Clever Connect for Example',
        'version'     => '1.0.0',
        'description' => 'Send Clever Forms entries to Example.',
        'category'    => 'integration',
        'status'      => 'active',
    ]
);
```

Useful Core hooks include:

- `clever_forms_loaded`
- `clever_forms_sanitized_values`
- `clever_forms_validation_errors`
- `clever_forms_after_submission`
- `clever_forms_registered_addons`

## Licensing rule

Activation should control commercial services such as update access, support, API capacity, hosted OAuth/token services, cloud queues, or other paid add-on functionality. The Core plugin should not contain dormant premium code that is merely unlocked by a key if Core is intended for the public WordPress plugin directory.

## Update delivery

Commercial add-ons should use a unique `Update URI` and an authenticated update API. Update metadata should include the latest version, minimum WordPress/PHP requirements, changelog, package URL, and a short-lived download token or signed URL for licensed customers.

## Salesforce add-on recommendation

The Salesforce connector should remain a separate paid plugin. A production release should add:

- OAuth 2.0 connected-app authorization.
- Secure token storage and refresh.
- Per-form object and field mapping.
- Create/update/upsert modes.
- External ID support.
- Background synchronization queue.
- Retries with exponential backoff.
- API-limit awareness.
- Per-entry sync status and Salesforce record ID.
- Error logs with redaction.
- Test connection and test mapping tools.
- Optional substantive CleverForge cloud relay for managed OAuth, monitoring, or enterprise queueing.
