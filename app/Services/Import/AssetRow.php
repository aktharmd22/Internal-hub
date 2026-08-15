<?php

declare(strict_types=1);

namespace App\Services\Import;

class AssetRow
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $line,
        public array $attributes,
        public array $errors = [],
        public bool $newClient = false,
        public bool $duplicate = false,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
