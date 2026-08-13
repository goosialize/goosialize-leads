# Public Integration Contract

## 1. Status

This document defines the normative version 1 plugin-to-plugin Lead capture
contract for Goosialize Leads.

The contract is public for compatible local Grav plugins. It is distinct from
the anonymous JSON HTTP API and does not require an HTTP request.

The contract is exposed by the runtime through the service and public types
defined below.

## 2. Capability Identity

The exact capability identifier is:

`goosialize-leads.capture`

The exact contract version is integer:

`1`

The exact Grav container service key is:

`goosialize-leads.public-capture.v1`

Version 1 is additive and backward-compatible within the published version 1
surface. Breaking changes require a new capability version and a new service
key.

## 3. Dependency Direction

Allowed:

`consumer plugin -> public Goosialize Leads integration contract`

Forbidden:

- direct access to Lead storage;
- construction of internal repositories;
- direct use of HTTP or Admin controllers;
- direct use of private application services;
- inspection of plugin folders as proof of compatibility;
- class-name existence as the sole compatibility check;
- provider-addon or notification internals.

Goosialize Leads must not depend on any consumer plugin.

## 4. Capability Detection

A consumer may treat the integration as available only when all of the
following are true:

1. the Grav container contains the exact service key
   `goosialize-leads.public-capture.v1`;
2. the resolved service implements
   `Grav\Plugin\GoosializeLeads\Integration\LeadCaptureCapabilityV1`;
3. `capabilityId()` returns exactly `goosialize-leads.capture`;
4. `contractVersion()` returns exactly integer `1`;
5. `available()` returns `true`.

A plugin directory, plugin enabled flag, route, controller, arbitrary class or
service-name guess is not capability detection.

Any exception or mismatch means unavailable.

## 5. Public Runtime Types

Version 1 exposes only these public integration types:

- `Integration\LeadCaptureCapabilityV1`;
- `Integration\LeadCaptureRequestV1`;
- `Integration\LeadCaptureContextV1`;
- `Integration\LeadCaptureResultV1`.

They must not expose repositories, filesystem paths, canonical stored records,
notification objects or internal application services.

### 5.1 LeadCaptureCapabilityV1

The public methods are exactly:

```php
public function capabilityId(): string;
public function contractVersion(): int;
public function available(): bool;
public function capture(
    LeadCaptureRequestV1 $request,
    LeadCaptureContextV1 $context
): LeadCaptureResultV1;
```

The implementation must reuse the existing validation, idempotency and secure
persistence pipeline. It must not create a second Lead validation or storage
pipeline.

### 5.2 LeadCaptureRequestV1

The request accepts these submitted fields only:

- `full_name`;
- `first_name`;
- `last_name`;
- `email`;
- `phone`;
- `company`;
- `message`;
- `resource_id`;
- `source_path`;
- `campaign`;
- `consent`.

Unknown fields fail validation.

Name normalization follows these published rules:

- when `full_name` is a non-empty string, it is used as the canonical name;
- otherwise `first_name` and `last_name` are trimmed and joined with one space;
- when the composed value is empty, canonical `full_name` is null;
- `first_name` and `last_name` are compatibility input fields only and are not
  persisted as separate canonical Lead fields.

Scalar fields are strings or null. `campaign` is null or a map containing only:

- `utm_source`;
- `utm_medium`;
- `utm_campaign`;
- `utm_term`;
- `utm_content`.

Each campaign value is string or null.

Consent is exactly affirmative:

```php
['granted' => true]
```

The request object performs structural validation only. Canonical
normalization and domain validation remain owned by the existing shared Lead
validation pipeline.

## 6. Trusted Context

`LeadCaptureContextV1` contains exactly:

- `source`;
- `form_name`;
- `locale`;
- `consent_version`;
- `idempotency_key`.

`source`, `form_name`, `consent_version` and `idempotency_key` are required
non-empty strings. `locale` is string or null.

The consumer supplies trusted context separately from submitted fields.
Submitted values cannot override trusted context.

The idempotency key is opaque, 16-128 ASCII characters matching:

`[A-Za-z0-9._~-]+`

It must not be logged or stored raw.

For Goosialize Links version 1 the expected context values are:

- `source`: `goosialize_links`;
- `form_name`: `goosialize_links_contact`;
- `locale`: the active page locale or null;
- `consent_version`: the configured consent version;
- `idempotency_key`: a consumer-generated opaque submission key.

The contract does not authorize Goosialize Leads to depend on those consumer
constants.

## 7. Public Result

`LeadCaptureResultV1` exposes exactly one of these outcomes:

- `created`;
- `replayed`;
- `validation_failed`;
- `idempotency_conflict`;
- `unavailable`.

The public result contains exactly:

- `outcome`;
- `errors`.

`outcome` is one of the five published outcome strings.
`errors` is an empty map or a validation error map keyed by public field name.

The public result must not expose a Lead ID, stored filename, storage path,
canonical record, notification state or internal exception.

Outcome semantics are:

- `created`: a new Lead was accepted and durably persisted;
- `replayed`: the same idempotent submission was accepted previously and no
  duplicate Lead was created.
- `validation_failed`: the request or trusted context failed public or shared
  validation and no Lead was persisted;
- `idempotency_conflict`: the idempotency key was already used for different
  normalized submission data and no new Lead was persisted;
- `unavailable`: the capability could not safely complete the capture request
  and no successful persistence result is asserted.

For `created`, `replayed`, `idempotency_conflict` and `unavailable`,
`errors` is empty.

For `validation_failed`, `errors` is a map whose keys are public request
or context field names and whose values are non-empty lists of unique stable
public error-code strings. Keys are ordered lexicographically and codes retain
their first-seen order. When the shared validation pipeline returns an error
without a specific field, the reserved `_request` key is used. Human-readable
messages are owned by the consumer.

## 8. Exception Boundary

No exception or throwable may cross the public capability boundary.

Expected public validation and idempotency conditions must be converted to
their corresponding published outcomes.

Unexpected runtime, persistence or integration failures must be contained and
returned as `unavailable` with an empty `errors` map.

Internal failures may be logged by Goosialize Leads through its existing secure
logging policy.

Logs must not contain raw idempotency keys, consent payloads, submitted message
content or other unnecessary personal data.

Consumers must not infer internal failure categories from the `unavailable`
outcome.

## 9. Shared Processing Pipeline

The public capability must use the same canonical normalization, domain
validation, idempotency and secure persistence pipeline as the supported HTTP
capture endpoint.

The capability must not emulate an HTTP request, invoke the HTTP controller or
parse an HTTP response.

Equivalent normalized input and trusted context must produce the same
validation, idempotency and persistence behavior regardless of whether capture
originates from the HTTP endpoint or the public capability.

The capability must not expose or permit direct consumer access to repositories,
storage adapters, idempotency records or notification delivery services.

Successful capture may trigger the same configured post-persistence processing
as other supported capture entry points.

Notification or downstream delivery failure after durable Lead persistence must
not change `created` or `replayed` into `unavailable`.

The public result reports capture persistence semantics only. It does not report
notification, retry or downstream integration status.

## 10. Versioning and Compatibility

Contract version 1 may receive additive changes that do not alter existing
field meanings, accepted value semantics, outcomes or consumer obligations.

The following changes require a new contract version and service key:

- removing or renaming a public type, method, field or outcome;
- changing accepted field types or validation semantics;
- changing idempotency or persistence outcome semantics;
- exposing additional required consumer obligations.

Consumers must require the exact capability identifier and contract version they
support.

A missing service, mismatched capability identifier, unsupported contract version
or incompatible runtime type must be treated as unavailable.

Consumers must not guess compatibility from plugin version strings, class names
or the presence of internal services.

## 11. Consumer Obligations

A consumer must:

- detect the exact capability and contract version before capture;
- construct only the published request and trusted context types;
- generate a new opaque idempotency key for each logical submission;
- reuse the same idempotency key only when retrying that same submission;
- present consent and validation messaging in its own user interface;
- treat unavailable capability or capture outcomes as non-fatal integration
  states.

A consumer must not:

- bypass the capability through internal repositories or storage services;
- submit trusted context values as user-controlled request fields;
- reuse an idempotency key for different logical submission data;
- expose internal exceptions, storage identifiers or delivery state to users;
- treat successful capture as proof of notification or downstream delivery.

## 12. Contract Verification

Goosialize Leads must provide automated public contract tests covering:

- exact capability identifier, contract version and service registration;
- capability availability and safe unavailable behavior;
- accepted request fields, campaign fields and trusted context validation;
- consent validation and unknown-field rejection;
- all five public result outcomes and their error-map rules;
- the no-throwable public boundary.
- created, replayed and idempotency-conflict persistence behavior;
- behavior parity between the public capability and supported HTTP capture;
- containment of persistence, runtime and downstream delivery failures;
- absence of Lead IDs, storage details and internal exceptions in results.

At least one integration test must act as an external consumer using only the
published service key, interface and DTOs.

That test must not instantiate internal repositories, controllers, storage
adapters or application services directly.
