<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Application;

final class CaptureCommand
{
    /**
     * @param array{
     *   consent:array{granted:true},
     *   full_name:?string,email:?string,phone:?string,company:?string,message:?string,
     *   resource_id:?string,source_path:?string,
     *   campaign:?array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_term:?string,utm_content:?string}
     * } $submitted
     * @param array{source:string,form_name:string,locale:?string,consent_version:string} $trusted
     */
    private function __construct(
        private readonly array $submitted,
        private readonly array $trusted
    ) {
    }

    /**
     * @param array{
     *   consent:array{granted:true},
     *   full_name:?string,email:?string,phone:?string,company:?string,message:?string,
     *   resource_id:?string,source_path:?string,
     *   campaign:?array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_term:?string,utm_content:?string}
     * } $submitted
     * @param array{source:string,form_name:string,locale:?string,consent_version:string} $trusted
     */
    public static function fromValidated(array $submitted, array $trusted): self
    {
        $submittedKeys = ['consent', 'full_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign'];
        $trustedKeys = ['source', 'form_name', 'locale', 'consent_version'];
        if (array_keys($submitted) !== $submittedKeys || array_keys($trusted) !== $trustedKeys) {
            throw new \InvalidArgumentException('Invalid command shape.');
        }
        if ($submitted['consent'] !== ['granted' => true]) {
            throw new \InvalidArgumentException('Invalid command consent.');
        }
        foreach (['full_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path'] as $key) {
            if ($submitted[$key] !== null && !is_string($submitted[$key])) {
                throw new \InvalidArgumentException('Invalid command value.');
            }
        }
        if ($submitted['campaign'] !== null) {
            if (!is_array($submitted['campaign'])) {
                throw new \InvalidArgumentException('Invalid command campaign.');
            }
            if (array_keys($submitted['campaign']) !== ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']) {
                throw new \InvalidArgumentException('Invalid command campaign.');
            }
            foreach ($submitted['campaign'] as $value) {
                if ($value !== null && !is_string($value)) {
                    throw new \InvalidArgumentException('Invalid command campaign value.');
                }
            }
        }
        if (!is_string($trusted['source']) || !is_string($trusted['form_name'])
            || ($trusted['locale'] !== null && !is_string($trusted['locale']))
            || !is_string($trusted['consent_version'])
        ) {
            throw new \InvalidArgumentException('Invalid trusted command value.');
        }

        return new self($submitted, $trusted);
    }

    /**
     * @return array{
     *   consent:array{granted:true},
     *   full_name:?string,email:?string,phone:?string,company:?string,message:?string,
     *   resource_id:?string,source_path:?string,
     *   campaign:?array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_term:?string,utm_content:?string}
     * }
     */
    public function submitted(): array
    {
        return $this->submitted;
    }

    /** @return array{source:string,form_name:string,locale:?string,consent_version:string} */
    public function trusted(): array
    {
        return $this->trusted;
    }

    /**
     * @return array{
     *   source:string,form_name:string,locale:?string,consent:array{granted:true},
     *   full_name:?string,email:?string,phone:?string,company:?string,message:?string,
     *   resource_id:?string,source_path:?string,
     *   campaign:?array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_term:?string,utm_content:?string}
     * }
     */
    public function toArray(): array
    {
        return [
            'source' => $this->trusted['source'],
            'form_name' => $this->trusted['form_name'],
            'locale' => $this->trusted['locale'],
            'consent' => $this->submitted['consent'],
            'full_name' => $this->submitted['full_name'],
            'email' => $this->submitted['email'],
            'phone' => $this->submitted['phone'],
            'company' => $this->submitted['company'],
            'message' => $this->submitted['message'],
            'resource_id' => $this->submitted['resource_id'],
            'source_path' => $this->submitted['source_path'],
            'campaign' => $this->submitted['campaign'],
        ];
    }
}
