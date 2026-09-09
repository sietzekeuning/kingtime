<?php

declare(strict_types=1);

namespace App\Domain\Client\Data;

use App\Domain\Client\Models\Client;
use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ClientData extends BaseData
{
    public function __construct(
        public ?int $id,
        #[Required, Max(255)]
        public string $name,
        #[Email, Max(255)]
        public ?string $email,
        public ?string $address,
        #[Max(3)]
        public string $currency = 'EUR',
        public bool $is_active = true,
        public ?int $harvest_id = null,
        public ?string $moneybird_contact_id = null,
        public ?string $notes = null,
        #[Derived]
        public ?int $projects_count = null,
    ) {}

    public static function fromModel(Client $client): self
    {
        return new self(
            id: $client->id,
            name: $client->name,
            email: $client->email,
            address: $client->address,
            currency: $client->currency,
            is_active: $client->is_active,
            harvest_id: $client->harvest_id,
            moneybird_contact_id: $client->moneybird_contact_id,
            notes: $client->notes,
            projects_count: $client->hasAttribute('projects_count') ? $client->getAttribute('projects_count') : null,
        );
    }
}
