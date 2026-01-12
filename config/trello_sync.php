<?php

return [
    // Poll cadence is handled by scheduler; this is what we consider "relevant"
    'action_types' => [
        // Keep this list editable without code changes
        'createCard',
        'updateCard',
        'deleteCard',
        'commentCard',
        'addAttachmentToCard',
        'updateCustomFieldItem',
        'moveCardToBoard',
        'moveCardFromBoard',
        'updateCard:idList', // some Trello types are more specific; keep flexible
    ],

    // How many actions to request per poll
    'poll_limit' => 200,

    // Snapshot policy (you can tune later)
    'take_ad_hoc_snapshots' => true,
    'ad_hoc_snapshot_every_minutes' => 60,
];
