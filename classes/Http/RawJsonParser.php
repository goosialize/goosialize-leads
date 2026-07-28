<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

final class RawJsonParser
{
    public function __construct()
    {
    }

    public function parse(string $bytes, int $maximumBytes, int $maximumDepth): ApiParseResult
    {
        if ($bytes === '') return ApiParseResult::failure('EMPTY_BODY');
        if (strlen($bytes) > $maximumBytes) return ApiParseResult::failure('PAYLOAD_TOO_LARGE');
        if (preg_match('//u', $bytes) !== 1) return ApiParseResult::failure('INVALID_UTF8');
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) return ApiParseResult::failure('MALFORMED_JSON');
        try {
            $index = 0;
            $this->scanValue($bytes, $index, 1, $maximumDepth);
            $this->skipWhitespace($bytes, $index);
            if ($index !== strlen($bytes)) throw new \JsonException();
            $root = json_decode($bytes, false, $maximumDepth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            $decoded = json_decode($bytes, true, $maximumDepth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\OverflowException) {
            return ApiParseResult::failure('TOO_DEEP');
        } catch (\DomainException) {
            return ApiParseResult::failure('DUPLICATE_JSON_KEY');
        } catch (\Throwable) {
            return ApiParseResult::failure('MALFORMED_JSON');
        }
        if (!$root instanceof \stdClass || !is_array($decoded)) {
            return ApiParseResult::failure('OBJECT_REQUIRED');
        }
        return ApiParseResult::valid($decoded);
    }

    private function scanValue(string $json, int &$index, int $depth, int $maximumDepth): void
    {
        $this->skipWhitespace($json, $index);
        if ($depth > $maximumDepth) throw new \OverflowException();
        $char = $json[$index] ?? '';
        if ($char === '{') { $this->scanObject($json, $index, $depth, $maximumDepth); return; }
        if ($char === '[') { $this->scanArray($json, $index, $depth, $maximumDepth); return; }
        if ($char === '"') { $this->scanString($json, $index); return; }
        foreach (['true', 'false', 'null'] as $literal) {
            if (substr($json, $index, strlen($literal)) === $literal) {
                $index += strlen($literal);
                return;
            }
        }
        if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/A', $json, $match, 0, $index) === 1) {
            $index += strlen($match[0]);
            return;
        }
        throw new \JsonException();
    }

    private function scanObject(string $json, int &$index, int $depth, int $maximumDepth): void
    {
        $index++;
        $keys = [];
        $this->skipWhitespace($json, $index);
        if (($json[$index] ?? '') === '}') { $index++; return; }
        while (true) {
            if (($json[$index] ?? '') !== '"') throw new \JsonException();
            $raw = $this->scanString($json, $index);
            $key = json_decode($raw, true, 2, JSON_THROW_ON_ERROR);
            if (array_key_exists($key, $keys)) throw new \DomainException();
            $keys[$key] = true;
            $this->skipWhitespace($json, $index);
            if (($json[$index] ?? '') !== ':') throw new \JsonException();
            $index++;
            $this->scanValue($json, $index, $depth + 1, $maximumDepth);
            $this->skipWhitespace($json, $index);
            $char = $json[$index] ?? '';
            if ($char === '}') { $index++; return; }
            if ($char !== ',') throw new \JsonException();
            $index++;
            $this->skipWhitespace($json, $index);
        }
    }

    private function scanArray(string $json, int &$index, int $depth, int $maximumDepth): void
    {
        $index++;
        $this->skipWhitespace($json, $index);
        if (($json[$index] ?? '') === ']') { $index++; return; }
        while (true) {
            $this->scanValue($json, $index, $depth + 1, $maximumDepth);
            $this->skipWhitespace($json, $index);
            $char = $json[$index] ?? '';
            if ($char === ']') { $index++; return; }
            if ($char !== ',') throw new \JsonException();
            $index++;
        }
    }

    private function scanString(string $json, int &$index): string
    {
        $start = $index++;
        while ($index < strlen($json)) {
            $ord = ord($json[$index]);
            if ($ord < 0x20) throw new \JsonException();
            if ($json[$index] === '"') {
                $index++;
                return substr($json, $start, $index - $start);
            }
            if ($json[$index] === '\\') {
                $index++;
                $escape = $json[$index] ?? '';
                if ($escape === 'u') {
                    if (preg_match('/\A[0-9A-Fa-f]{4}/', substr($json, $index + 1, 4)) !== 1) throw new \JsonException();
                    $index += 5;
                    continue;
                }
                if (!str_contains('"\\/bfnrt', $escape)) throw new \JsonException();
            }
            $index++;
        }
        throw new \JsonException();
    }

    private function skipWhitespace(string $json, int &$index): void
    {
        while (isset($json[$index]) && str_contains(" \t\r\n", $json[$index])) $index++;
    }
}
