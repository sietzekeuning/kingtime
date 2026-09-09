<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Time\Actions\DeleteTimeEntryAction;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteTimeEntryTool extends KingtimeTool
{
    public function __construct(private DeleteTimeEntryAction $deleteTimeEntry) {}

    protected string $name = 'delete_time_entry';

    protected string $title = 'Delete time entry';

    protected string $description = 'Deletes one of your time entries. Entries that are billed or locked (on an invoice) cannot be deleted. Confirm with the user before deleting.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'time_entry_id' => $schema->integer()->description('Id of the entry to delete.')->required(),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['time_entry_id' => ['required', 'integer']]);

        $entry = $this->findEntry($user, (int) $request->get('time_entry_id'));
        $payload = $this->entryPayload($entry);

        $this->deleteTimeEntry->handle($entry);

        return Response::structured([
            'message' => "Time entry #{$entry->id} deleted.",
            'deleted_entry' => $payload,
        ]);
    }
}
