<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;
use App\Services\Trello\TrelloClient;

final class DetectAndCloseSprintAction
{
    public function __construct(
        private TrelloClient $trello,
        private TakeSprintSnapshotAction $takeSnapshot,
    ) {}

    public function run(Sprint $sprint): void
    {
        if ($sprint->isClosed()) return;

        if (!$sprint->trello_control_card_id || !$sprint->trello_status_custom_field_id || !$sprint->trello_closed_option_id) {
            // Not configured; do nothing
            return;
        }

        // Read the control card’s custom field items
        $card = $this->trello->get("/cards/{$sprint->trello_control_card_id}", [
            'customFieldItems' => 'true',
            'fields' => 'id,name',
        ]);

        $items = $card['customFieldItems'] ?? [];
        $statusValueId = null;

        foreach ($items as $item) {
            if (($item['idCustomField'] ?? null) === $sprint->trello_status_custom_field_id) {
                $statusValueId = $item['idValue'] ?? null; // dropdown option id
                break;
            }
        }

        if ($statusValueId && $statusValueId === $sprint->trello_closed_option_id) {
            app(ReconcileSprintBoardStateAction::class)->run($sprint);

            // Take end snapshot then mark closed
            $this->takeSnapshot->run($sprint, 'end', 'trello_flag');
            $sprint->closed_at = now();
            $sprint->save();
        }
    }
}
