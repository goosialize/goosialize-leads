<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

final class LeadSummary
{
    /** @param array{id:string,created_at:string,name:?string,email:?string,source:string,form_name:string,status:string} $data */
    private function __construct(private readonly array $data)
    {
    }

    /** @param array<string,mixed> $record */
    public static function fromRecord(array $record): self
    {
        $valid = isset($record['id'], $record['created_at'], $record['source'], $record['form_name'], $record['status'])
            && preg_match('/\A[0-9a-f]{32}\z/D', (string) $record['id']) === 1
            && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', (string) $record['created_at']) === 1
            && is_string($record['source']) && is_string($record['form_name'])
            && in_array($record['status'], ['new', 'contacted', 'qualified', 'closed'], true)
            && array_key_exists('full_name', $record) && ($record['full_name'] === null || is_string($record['full_name']))
            && array_key_exists('email', $record)
            && ($record['email'] === null || is_string($record['email']))
            && array_key_exists('phone', $record)
            && ($record['phone'] === null || is_string($record['phone']))
            && array_key_exists('resource_id', $record)
            && ($record['resource_id'] === null || is_string($record['resource_id']));
        if (!$valid) throw new \InvalidArgumentException('Invalid Lead summary record.');

        return new self([
            'id' => $record['id'],
            'created_at' => $record['created_at'],
            'name' => $record['full_name'],
            'email' => $record['email'],
            'phone' => $record['phone'],
            'source' => $record['source'],
            'form_name' => $record['form_name'],
            'resource_id' => $record['resource_id'],
            'status' => $record['status'],
        ]);
    }

    public function id(): string { return $this->data['id']; }
    public function createdAt(): string { return $this->data['created_at']; }
    public function name(): ?string { return $this->data['name']; }
    public function email(): ?string { return $this->data['email']; }
    public function phone(): ?string { return $this->data['phone']; }
    public function source(): string { return $this->data['source']; }
    public function formName(): string { return $this->data['form_name']; }
    public function resourceId(): ?string { return $this->data['resource_id']; }
    public function status(): string { return $this->data['status']; }

    /** @return array{id:string,created_at:string,name:?string,email:?string,source:string,form_name:string,status:string} */
    public function toArray(): array
    {
        $data = $this->data;
        $data['form_or_resource'] =
            $this->resourceId() ?? $this->formName();

        return $data;
    }
}
