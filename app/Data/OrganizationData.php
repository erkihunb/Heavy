<?php

namespace App\Data;

/**
 * Validated organization profile, as passed from a FormRequest to an Action.
 */
final readonly class OrganizationData
{
    /**
     * @param  string  $phone  international format, see App\Support\EthiopianPhone
     */
    public function __construct(
        public string $nameAm,
        public ?string $nameEn,
        public ?string $tin,
        public string $phone,
        public string $email,
        public string $address,
    ) {}

    /**
     * @return array{name_am: string, name_en: string|null, tin: string|null, phone: string, email: string, address: string}
     */
    public function toAttributes(): array
    {
        return [
            'name_am' => $this->nameAm,
            'name_en' => $this->nameEn,
            'tin' => $this->tin,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
        ];
    }
}
