<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

use Beziworld\Przelewy24\Support\Arr;

/**
 * Payment method available for the merchant, returned by GET /payment/methods/{lang}.
 */
final readonly class PaymentMethod
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $group = null,
        public ?string $subgroup = null,
        public bool $available = false,
        public ?string $imageUrl = null,
        public ?string $mobileImageUrl = null,
        public bool $mobile = false,
    ) {}

    /**
     * @param array<array-key, mixed> $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            id: Arr::int($item, 'id') ?? 0,
            name: Arr::string($item, 'name') ?? '',
            group: Arr::string($item, 'group'),
            subgroup: Arr::string($item, 'subgroup'),
            available: Arr::bool($item, 'status') ?? false,
            imageUrl: Arr::string($item, 'imgUrl'),
            mobileImageUrl: Arr::string($item, 'mobileImgUrl'),
            mobile: Arr::bool($item, 'mobile') ?? false,
        );
    }
}
