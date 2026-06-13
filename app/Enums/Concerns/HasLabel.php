<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use ValueError;

trait HasLabel
{
    public function label(): string
    {
        return strtolower($this->name);
    }

    public static function fromLabel(string $label): static
    {
        return static::tryFromLabel($label)
            ?? throw new ValueError(sprintf('"%s" is not a valid label for enum %s', $label, static::class));
    }

    public static function tryFromLabel(?string $label): ?static
    {
        foreach (static::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(static fn (self $case) => $case->label(), static::cases());
    }
}
