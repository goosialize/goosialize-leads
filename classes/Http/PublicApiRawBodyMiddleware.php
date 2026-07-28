<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PublicApiRawBodyMiddleware implements MiddlewareInterface
{
    /**
     * @param array{
     *   enabled:bool,allowed_origins:list<string>,body_max_bytes:int,json_max_depth:int,
     *   rate_limit_count:int,rate_limit_window_seconds:int
     * } $config
     */
    public function __construct(
        private readonly RawJsonParser $parser,
        private readonly OriginPolicy $originPolicy,
        private readonly EndpointRateLimiter $rateLimiter,
        private readonly ApiResponseMapper $responses,
        private readonly array $config
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== '/api/v1/goosialize-leads/capture') {
            return $handler->handle($request);
        }
        if ($request->getMethod() !== 'POST') return $this->responses->failure('unsupported_method');
        if (!$this->acceptsJson($request)) return $this->responses->failure('not_acceptable');
        if (!$this->contentTypeIsJson($request)) return $this->responses->failure('unsupported_media_type');

        $stream = $request->getBody();
        try {
            if (!$stream->isSeekable()) return $this->responses->failure('request_unavailable');
            $stream->rewind();
            $bytes = $stream->read($this->config['body_max_bytes'] + 1);
            $stream->rewind();
        } catch (\Throwable) {
            return $this->responses->failure('request_unavailable');
        }
        if (strlen($bytes) > $this->config['body_max_bytes']) return $this->responses->failure('payload_too_large');
        $originCode = $this->originPolicy->evaluate($request, $this->config['allowed_origins']);
        if ($originCode !== null) return $this->responses->failure(strtolower($originCode));
        $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if (!is_string($address)) return $this->responses->failure('rate_limit_unavailable');
        $limit = $this->rateLimiter->check(
            $address,
            $this->config['rate_limit_count'],
            $this->config['rate_limit_window_seconds']
        );
        if (!$limit->isAllowed()) {
            return $this->responses->failure(strtolower((string) $limit->code()), [], $limit->retryAfter());
        }
        $result = $this->parser->parse($bytes, $this->config['body_max_bytes'], $this->config['json_max_depth']);
        if (!$result->isValid()) {
            $map = ['MALFORMED_JSON' => 'invalid_json'];
            return $this->responses->failure($map[$result->code()] ?? strtolower((string) $result->code()));
        }
        $response = $handler->handle($request->withAttribute('goosialize_leads.api_parse_result', $result));
        $origin = $request->getHeaderLine('Origin');
        $direct = $this->directOrigin($request);
        if ($direct !== null && $this->normalizeOrigin($origin) !== $direct) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $this->normalizeOrigin($origin) ?? '');
        }
        return $response;
    }

    private function contentTypeIsJson(ServerRequestInterface $request): bool
    {
        $values = $request->getHeader('Content-Type');
        if (count($values) !== 1) return false;
        $parts = array_map('trim', explode(';', strtolower($values[0])));
        if (array_shift($parts) !== 'application/json') return false;
        return $parts === [] || (count($parts) === 1 && $parts[0] === 'charset=utf-8');
    }

    private function acceptsJson(ServerRequestInterface $request): bool
    {
        $line = trim($request->getHeaderLine('Accept'));
        if ($line === '') return true;
        foreach (explode(',', strtolower($line)) as $range) {
            $media = trim(explode(';', $range, 2)[0]);
            if ($media === '*/*' || $media === 'application/json') return true;
        }
        return false;
    }

    private function directOrigin(ServerRequestInterface $request): ?string
    {
        $uri = $request->getUri();
        return $this->normalizeOrigin($uri->getScheme() . '://' . $uri->getHost()
            . ($uri->getPort() === null ? '' : ':' . $uri->getPort()));
    }

    private function normalizeOrigin(string $origin): ?string
    {
        $parts = parse_url($origin);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return null;
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = $parts['port'] ?? null;
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) $port = null;
        return $scheme . '://' . $host . ($port === null ? '' : ':' . $port);
    }
}
