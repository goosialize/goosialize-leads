# Public JSON API integration

## Endpoint

```http
POST <configured API route>/<configured version prefix>/goosialize-leads/capture
```

The API is disabled by default.

## Required headers

```http
Content-Type: application/json
Accept: application/json
Origin: https://approved.example
Idempotency-Key: synthetic-unique-key
```

`Origin` must exactly match one configured canonical Origin.

## Fixed security bounds

Version 1.0.0 enforces:

- maximum raw body: 16,384 bytes;
- maximum JSON depth: 4;
- requests per rate-limit window: 10;
- rate-limit window: 60 seconds.

## Processing pipeline

The request passes through:

- raw-body middleware;
- rate limiting;
- canonical Origin validation;
- JSON request mapping;
- shared validation and normalization;
- idempotency processing;
- contained filesystem persistence;
- stable JSON response mapping.

The mapper assigns the canonical values:

```text
source=public_api
form_name=public_api
```

## Idempotency

The request must include exactly one valid `Idempotency-Key` header.

Exact replay returns the existing Lead. Reusing the same key with a conflicting
payload returns an idempotency conflict and does not rewrite the existing Lead.

## Response categories

Expected HTTP categories include:

- success for created or exact replay;
- `400` for malformed or invalid request data;
- `403` for missing, invalid or forbidden Origin;
- `409` for idempotency conflict;
- `429` for rate limiting;
- `503` for unavailable security or storage dependencies.

A rate-limited response includes a positive `retry_after` value.

## CORS behavior

The response varies by `Origin`. Only an approved canonical Origin can be
reflected in `Access-Control-Allow-Origin`.

Wildcard Origins are not accepted.

## Failure behavior

The API must not expose:

- filesystem paths;
- stored Lead values;
- configuration secrets;
- raw exceptions;
- notification routing details.
