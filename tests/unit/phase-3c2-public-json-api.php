<?php

declare(strict_types=1);

if (is_file('/app/www/public/vendor/autoload.php')) {
    require '/app/www/public/vendor/autoload.php';
}
require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Notification\NotificationEnqueueResult;
use Grav\Plugin\GoosializeLeads\Notification\NotificationEvent;
use Grav\Plugin\GoosializeLeads\Notification\NotificationOutbox;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Http\ApiParseResult;
use Grav\Plugin\GoosializeLeads\Http\ApiRequestMapper;
use Grav\Plugin\GoosializeLeads\Http\ApiRequestResult;
use Grav\Plugin\GoosializeLeads\Http\ApiResponseMapper;
use Grav\Plugin\GoosializeLeads\Http\EndpointRateLimiter;
use Grav\Plugin\GoosializeLeads\Http\OriginPolicy;
use Grav\Plugin\GoosializeLeads\Http\PublicApiRawBodyMiddleware;
use Grav\Plugin\GoosializeLeads\Http\PublicLeadApiController;
use Grav\Plugin\GoosializeLeads\Http\RateLimitResult;
use Grav\Plugin\GoosializeLeads\Http\RawJsonParser;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\LeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

function apiCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

if (($argv[1] ?? null) === '--rate-check') {
    $result = (new EndpointRateLimiter((string) ($argv[2] ?? ''), static fn (): int => 2000))
        ->check('192.0.2.55', 10, 60);
    echo $result->isAllowed() ? "allowed\n" : $result->code() . "\n";
    exit(0);
}

$parser = new RawJsonParser();
apiCheck($parser->parse('', 16384, 4)->code() === 'EMPTY_BODY', 'empty body');
apiCheck($parser->parse(str_repeat('x', 16385), 16384, 4)->code() === 'PAYLOAD_TOO_LARGE', 'body limit');
apiCheck($parser->parse("\xff", 16384, 4)->code() === 'INVALID_UTF8', 'UTF-8');
apiCheck($parser->parse("\xEF\xBB\xBF{}", 16384, 4)->code() === 'MALFORMED_JSON', 'BOM');
apiCheck($parser->parse('{"a":1,"a":2}', 16384, 4)->code() === 'DUPLICATE_JSON_KEY', 'duplicate');
apiCheck($parser->parse('{"a":1,"\u0061":2}', 16384, 4)->code() === 'DUPLICATE_JSON_KEY', 'decoded duplicate');
apiCheck($parser->parse('{"a":{"b":{"c":{"d":true}}}}', 16384, 4)->code() === 'TOO_DEEP', 'depth overflow');
apiCheck($parser->parse('[]', 16384, 4)->code() === 'OBJECT_REQUIRED', 'root shape');
apiCheck($parser->parse('{"a":true}', 16384, 4)->isValid(), 'valid JSON');
apiCheck($parser->parse('{}', 16384, 4)->isValid(), 'empty object');

$object = [
    'full_name' => 'Synthetic Lead',
    'email' => 'lead@example.test',
    'phone' => null,
    'company' => null,
    'message' => 'Synthetic request',
    'resource_id' => null,
    'source_path' => null,
    'campaign' => null,
    'consent' => ['granted' => true],
];
$mapper = new ApiRequestMapper();
$mapped = $mapper->map($object, 'synthetic-key-01', ['locale' => null, 'consent_version' => 'privacy-v1']);
apiCheck($mapped->isValid(), 'schema mapping');
apiCheck($mapped->trusted() === ['source'=>'public_api','form_name'=>'public_api','locale'=>null,'consent_version'=>'privacy-v1'], 'trusted mapping');
apiCheck($mapper->map($object + ['source'=>'visitor'], 'synthetic-key-01', ['locale'=>null,'consent_version'=>'privacy-v1'])->code() === 'UNKNOWN_MEMBER', 'unknown member');
$bad = $object; $bad['message'] = 3;
apiCheck($mapper->map($bad, 'synthetic-key-01', ['locale'=>null,'consent_version'=>'privacy-v1'])->code() === 'REQUEST_SCHEMA_INVALID', 'schema type');

$keyBytes = str_repeat('K', 32);
$ring = new IdempotencyKeyRing(1, [1 => base64_encode($keyBytes)]);
apiCheck(
    $ring->deriveApiIdempotencyKey('synthetic-key-01')
        === hash_hmac('sha256', "public-api-v1\nsynthetic-key-01\n", $keyBytes),
    'API HMAC'
);

$repository = new class implements LeadRepository {
    public string $mode = 'created';
    public function persist(PersistenceRequest $request): PersistenceResult
    {
        return match ($this->mode) {
            'replayed' => PersistenceResult::replayed($request->record()->toArray()),
            'conflict' => PersistenceResult::failure('idempotency_conflict'),
            default => PersistenceResult::created($request->record()),
        };
    }
};
$service = new LeadCaptureService(
    new LeadPersistenceCoordinator(new LeadInputValidator(new LeadNormalizer()), $repository, $ring),
    $ring,
    new class implements NotificationOutbox {
        public int $calls = 0;
        public function ensure(NotificationEvent $event): NotificationEnqueueResult
        {
            $this->calls++;
            return NotificationEnqueueResult::existing();
        }
    }
);
$entropy = static fn (int $n): string => str_repeat("\x01", $n);
$clock = static fn (): DateTimeInterface => new DateTimeImmutable('2026-07-28T00:00:00.000000Z');
$created = $service->captureApi($mapped->submitted(), $mapped->trusted(), 'synthetic-key-01', $entropy, $clock);
apiCheck($created->isSuccess() && !$created->replayed(), 'API created');
$repository->mode = 'replayed';
apiCheck($service->captureApi($mapped->submitted(), $mapped->trusted(), 'synthetic-key-01', $entropy, $clock)->replayed(), 'API replay');
$repository->mode = 'conflict';
apiCheck($service->captureApi($mapped->submitted(), $mapped->trusted(), 'synthetic-key-01', $entropy, $clock)->code() === 'idempotency_conflict', 'API conflict');
$invalidInquiry = $mapped->submitted(); $invalidInquiry['message'] = null; $invalidInquiry['resource_id'] = null;
apiCheck($service->captureApi($invalidInquiry, $mapped->trusted(), 'synthetic-key-02', $entropy, $clock)->code() === 'validation_failed', 'inquiry invariant');

$responses = new ApiResponseMapper();
$createdResponse = $responses->success($created);
apiCheck($createdResponse->getStatusCode() === 201, 'created status');
apiCheck($createdResponse->getHeaderLine('Content-Type') === 'application/json; charset=utf-8', 'response content type');
apiCheck(json_decode((string) $createdResponse->getBody(), true, 4, JSON_THROW_ON_ERROR)['code'] === 'created', 'created body');
$limitedResponse = $responses->failure('rate_limited', [], 42);
apiCheck($limitedResponse->getStatusCode() === 429 && $limitedResponse->getHeaderLine('Retry-After') === '42', 'rate response');

$middlewareRoot = sys_get_temp_dir() . '/phase-3c2-middleware-' . bin2hex(random_bytes(6));
mkdir($middlewareRoot, 0700);
$middleware = new PublicApiRawBodyMiddleware(
    $parser,
    new OriginPolicy(),
    new EndpointRateLimiter($middlewareRoot, static fn (): int => 1000),
    $responses,
    ['enabled'=>true,'allowed_origins'=>[],'body_max_bytes'=>16384,'json_max_depth'=>4,'rate_limit_count'=>10,'rate_limit_window_seconds'=>60]
);
$handler = new class implements RequestHandlerInterface {
    public bool $handled = false;
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handled = $request->getAttribute('goosialize_leads.api_parse_result') instanceof ApiParseResult;
        return new \Grav\Framework\Psr7\Response(204);
    }
};
$request = new ServerRequest('POST', 'https://example.test/api/v1/goosialize-leads/capture', [
    'Content-Type'=>'application/json', 'Accept'=>'application/json', 'Origin'=>'https://example.test',
    'X-Forwarded-Host'=>'evil.example.test', 'X-Forwarded-For'=>'203.0.113.9',
], Stream::create('{"full_name":"Synthetic Lead"}'), '1.1', ['REMOTE_ADDR'=>'192.0.2.8']);
apiCheck($middleware->process($request, $handler)->getStatusCode() === 204 && $handler->handled, 'middleware pass');
apiCheck($middleware->process($request->withMethod('GET'), $handler)->getStatusCode() === 405, 'method');
apiCheck($middleware->process($request->withHeader('Content-Type','text/plain'), $handler)->getStatusCode() === 415, 'media');
apiCheck($middleware->process($request->withHeader('Accept','text/html'), $handler)->getStatusCode() === 406, 'accept');
apiCheck($middleware->process($request->withoutHeader('Origin'), $handler)->getStatusCode() === 403, 'origin required');
apiCheck($middleware->process($request->withBody(Stream::create("\xff")), $handler)->getStatusCode() === 400, 'middleware UTF-8');
apiCheck($middleware->process($request->withBody(Stream::create(str_repeat('x', 16385))), $handler)->getStatusCode() === 413, 'middleware limit');
apiCheck($middleware->process($request->withBody(Stream::create('{"a":1,"a":2}')), $handler)->getStatusCode() === 400, 'middleware duplicate');

$temporary = sys_get_temp_dir() . '/phase-3c2-rate-' . bin2hex(random_bytes(6));
mkdir($temporary, 0700);
try {
    $now = 1000;
    $limiter = new EndpointRateLimiter($temporary, static function () use (&$now): int { return $now; });
    for ($i = 0; $i < 10; $i++) apiCheck($limiter->check('192.0.2.1', 10, 60)->isAllowed(), 'rate allowance');
    $limited = $limiter->check('192.0.2.1', 10, 60);
    apiCheck($limited->code() === 'RATE_LIMITED' && $limited->retryAfter() === 60, 'rate boundary');
    $now = 1060;
    apiCheck($limiter->check('192.0.2.1', 10, 60)->isAllowed(), 'rate reset');
    apiCheck($limiter->check('invalid', 10, 60)->code() === 'RATE_LIMIT_UNAVAILABLE', 'invalid address');
    apiCheck(
        (new EndpointRateLimiter(__FILE__, static fn (): int => 1000))
            ->check('192.0.2.2', 10, 60)->code() === 'RATE_LIMIT_UNAVAILABLE',
        'rate storage failure'
    );
} finally {
    $state = $temporary . '/goosialize-leads/v1/api-rate-limit';
    foreach (glob($state . '/*') ?: [] as $path) unlink($path);
    if (is_file($state . '/.cleanup.lock')) unlink($state . '/.cleanup.lock');
    if (is_dir($state)) rmdir($state);
    if (is_dir(dirname($state))) rmdir(dirname($state));
    if (is_dir(dirname(dirname($state)))) rmdir(dirname(dirname($state)));
    if (is_dir($temporary)) rmdir($temporary);
}
foreach (glob($middlewareRoot . '/goosialize-leads/v1/api-rate-limit/*') ?: [] as $path) unlink($path);
@unlink($middlewareRoot . '/goosialize-leads/v1/api-rate-limit/.cleanup.lock');
@rmdir($middlewareRoot . '/goosialize-leads/v1/api-rate-limit');
@rmdir($middlewareRoot . '/goosialize-leads/v1');
@rmdir($middlewareRoot . '/goosialize-leads');
@rmdir($middlewareRoot);

$apis = [
    ApiParseResult::class => ['valid','failure','isValid','object','code'],
    RawJsonParser::class => ['__construct','parse'],
    ApiRequestResult::class => ['valid','failure','isValid','submitted','trusted','idempotencyKey','code'],
    ApiRequestMapper::class => ['__construct','map'],
    OriginPolicy::class => ['__construct','evaluate'],
    RateLimitResult::class => ['allowed','limited','unavailable','isAllowed','limit','remaining','retryAfter','code'],
    EndpointRateLimiter::class => ['__construct','check'],
    ApiResponseMapper::class => ['__construct','success','failure'],
    PublicApiRawBodyMiddleware::class => ['__construct','process'],
    PublicLeadApiController::class => ['__construct','capture'],
];
foreach ($apis as $class => $expected) {
    $reflection = new ReflectionClass($class);
    apiCheck($reflection->isFinal(), 'not final: ' . $class);
    $actual = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class) $actual[] = $method->getName();
    }
    apiCheck($actual === $expected, 'public API mismatch: ' . $class);
}

echo "PASS_PHASE_3C2_SHARED_API\n";
