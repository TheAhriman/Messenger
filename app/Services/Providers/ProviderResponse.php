<?php

declare(strict_types=1);

namespace App\Services\Providers;

final readonly class ProviderResponse
{
    public function __construct(
        public string $providerMessageId,
        public bool $wasDuplicate = false,
    ) {}
}
