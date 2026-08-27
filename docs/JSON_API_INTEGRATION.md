# Public JSON API integration

Goosialize Leads provides an optional public, anonymous JSON endpoint. It does
not use an Admin2 session or API token. Before enabling it, read
[Configuration](CONFIGURATION.md#public-json-api) and
[Security](SECURITY.md#public-json-api).

> **Audience:** Developers integrating an external HTTP client. This endpoint
> is optional and anonymous; it does not require an Admin2 token.

## Endpoint construction

The route suffix is always:

```text
/goosialize-leads/capture
```

The complete path is built from the API plugin's configured `route` and
`version_prefix` values:

```text
/<route>/<version_prefix>/goosialize-leads/capture
```

With the **default API configuration**, the endpoint is:

```http
POST /api/v1/goosialize-leads/capture
```

That path is a default example, not a universal route. For example, API
configuration using route `/service` and version prefix `edge` produces:

```http
POST /service/edge/goosialize-leads/capture
```

The public-route classification and raw-body middleware use the same derived
path. A client using a stale hard-coded base will not reach the configured
public endpoint.

## Security prerequisites

The endpoint is disabled until all of these are valid:

- `public_api.enabled: true`;
- at least one exact canonical allowed Origin;
- a positive `idempotency.active_key_version`;
- a matching `idempotency.keys.<version>.secret` containing canonical Base64
  encoding of exactly 32 random bytes.

The endpoint is public/anonymous: do not send an Admin2 API token. Protection
comes from:

- exact Origin allowlisting with no wildcards;
- mandatory keyed idempotency;
- a fixed-window rate limit;
- the configured secret key ring;
- fixed raw-body and JSON-depth bounds.

An idempotency key is not the key-ring secret. Send a new opaque request key
for each logical submission and keep the configured secret on the server.

## Tested curl request

This example uses the **default API configuration** path:

```bash
curl --request POST \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --header 'Origin: https://www.example.com' \
  --header 'Idempotency-Key: synthetic-lead-0001' \
  --data '{
    "full_name": "Example Person",
    "email": "person@example.com",
    "message": "Please contact me about the product.",
    "consent": {
      "granted": true
    }
  }' \
  https://www.example.com/api/v1/goosialize-leads/capture
```

Replace the URL with the path derived from your API configuration and make the
Origin exactly match an `allowed_origins` entry.

## Headers

- `Content-Type: application/json` is required.
- `Accept: application/json` is required by the JSON endpoint contract.
- `Origin` must be present and exactly match one configured canonical Origin.
- Exactly one `Idempotency-Key` is required. It must be 16-128 ASCII
  characters using letters, digits, `.`, `_`, `~` or `-`.

## Request fields

Unknown members are rejected. The accepted JSON members are:

- `full_name`: string or null;
- `first_name`: string or null;
- `last_name`: string or null;
- `email`: string or null;
- `phone`: string or null;
- `company`: string or null;
- `message`: string or null;
- `resource_id`: string or null;
- `source_path`: string or null;
- `campaign`: object or null;
- `consent`: exactly `{ "granted": true }`.

Use either a non-empty `full_name` or both `first_name` and `last_name`. At
least one of `email` or `phone` is required, and at least one of `message` or
`resource_id` is required.

`campaign` may contain only string-or-null values for `utm_source`,
`utm_medium`, `utm_campaign`, `utm_term` and `utm_content`.

The server assigns trusted context:

```text
source=public_api
form_name=public_api
```

Submitted data cannot override trusted source, form, locale or consent-version
context.

## Responses

All responses are JSON, `Cache-Control: no-store`, and omit filesystem paths,
secrets and internal exceptions.

### 201 created

```json
{
  "ok": true,
  "code": "created",
  "message": "Lead captured.",
  "lead_id": "01010101010101010101010101010101"
}
```

### 200 replayed

An exact retry with the same idempotency key and canonical payload does not
create or rewrite a Lead:

```json
{
  "ok": true,
  "code": "replayed",
  "message": "Lead already captured.",
  "lead_id": "01010101010101010101010101010101"
}
```

### 409 conflict

The key was already used for a different canonical payload:

```json
{
  "ok": false,
  "code": "idempotency_conflict",
  "message": "Request conflicts with an earlier submission."
}
```

### 422 validation failure

```json
{
  "ok": false,
  "code": "validation_failed",
  "message": "Please correct the request and try again.",
  "errors": [
    {
      "field": "email",
      "code": "invalid_email"
    }
  ]
}
```

Treat error codes as stable machine-readable values; applications own their
human-readable field messages.

### 429 rate limited

```json
{
  "ok": false,
  "code": "rate_limited",
  "message": "Too many requests.",
  "retry_after": 42
}
```

The response also includes a matching `Retry-After` header.

## Status code reference

| HTTP status | Meaning |
| --- | --- |
| `200` | Exact Idempotency replay; the existing Lead is returned. |
| `201` | A new Lead was created. |
| `400` | Malformed JSON, invalid schema, missing/invalid Idempotency key or another invalid request. |
| `403` | Origin is missing, invalid or not allowlisted. |
| `405` | Method is not POST; the response includes `Allow: POST`. |
| `406` | The client does not accept the required JSON response. |
| `409` | The Idempotency key conflicts with an earlier canonical payload. |
| `413` | Raw request body exceeds 16,384 bytes. |
| `415` | Request media type is not JSON. |
| `422` | One or more accepted fields failed validation. |
| `429` | Fixed-window rate limit exceeded; honour `Retry-After`. |
| `500` | Unexpected error contained behind a stable response. |
| `503` | Key ring, rate limiter, storage or API dependency is safely unavailable. |

The fixed JSON depth is 4 and the fixed local rate limit is 10 requests per 60
seconds.

## Idempotency

Reuse an idempotency key only to retry the same logical submission. Exact
replay returns `200`; conflicting reuse returns `409` and leaves the existing
primary record unchanged.

Do not log raw idempotency headers or server key-ring secrets.

## CORS behavior

Only an approved exact Origin can be reflected in
`Access-Control-Allow-Origin`. Wildcards are not accepted, and the response
varies by Origin.

For common failures, see
[Troubleshooting](TROUBLESHOOTING.md#public-json-api-key-configuration-is-missing)
and [Custom API route mismatch](TROUBLESHOOTING.md#custom-api-route-mismatch).

---

## Navigation

[← Back to README](../README.md) · [Previous: Security](SECURITY.md) ·
[Next: Notification delivery →](NOTIFICATION_DELIVERY.md)

Related documentation: [Configuration](CONFIGURATION.md#public-json-api) ·
[Idempotency](SECURITY.md#idempotency) ·
[Troubleshooting](TROUBLESHOOTING.md#public-json-request-is-rejected)
