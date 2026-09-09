<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Client\Models\Client;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class MoneybirdListContactsTool extends MoneybirdTool
{
    protected string $name = 'moneybird_list_contacts';

    protected string $title = 'List Moneybird contacts';

    protected string $description = 'Lists contacts in the Moneybird administration (50 per page) with their id, company or person name, email and customer number, plus the Kingtime client that is linked to the contact, if any. `query` is Moneybird\'s fuzzy search over name, email and customer id. Use the Moneybird contact id as `contact_id` in the invoice tools.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search term matched fuzzily against company name, person name, email and customer id.'),
            'page' => $schema->integer()->description('Page number, starting at 1.')->default(1),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $this->page($request);
        $contacts = $this->moneybird($user)->contacts(self::optionalString($request, 'query'), $page);

        $contactIds = array_values(array_filter(array_map(fn (array $contact): ?string => self::id($contact['id'] ?? null), $contacts)));
        $linkedClients = Client::query()
            ->whereIn('moneybird_contact_id', $contactIds)
            ->get(['id', 'name', 'moneybird_contact_id'])
            ->keyBy('moneybird_contact_id');

        return Response::structured([
            'page' => $page,
            'per_page' => 50,
            'count' => count($contacts),
            'has_more' => count($contacts) >= 50,
            'contacts' => array_values(array_map(function (array $contact) use ($linkedClients): array {
                $client = $linkedClients->get((string) ($contact['id'] ?? ''));

                return self::contactPayload($contact) + [
                    'kingtime_client_id' => $client?->id,
                    'kingtime_client' => $client?->name,
                ];
            }, $contacts)),
        ]);
    }
}
