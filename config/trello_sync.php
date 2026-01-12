<?php

return [
    'registry_board_id' => env('TRELLO_REGISTRY_BOARD_ID'),

    'sprint_control' => [
        // Custom Field IDs from the *registry board*
        'control_field_ids' => [
            // dropdown: Planned | Active | Closed
            'status' => env('TRELLO_CF_SPRINT_STATUS', '5fd1234567890abc12345678'),

            // date field
            'starts_at' => env('TRELLO_CF_SPRINT_STARTS_AT', '5fd1234567890abc12345679'),

            // date field
            'ends_at' => env('TRELLO_CF_SPRINT_ENDS_AT', '5fd1234567890abc1234567a'),

            // url/text field containing the sprint board id OR board url
            'sprint_board' => env('TRELLO_CF_SPRINT_BOARD', '5fd1234567890abc1234567b'),

            // (optional) a JSON/text field for done list ids, OR omit and infer by list name "Done"
            'done_list_ids' => env('TRELLO_CF_DONE_LIST_IDS', '5fd1234567890abc1234567c'),
        ],

        // For dropdown mapping: option-id -> our canonical status slug
        // (You can also just store option id, but mapping keeps local DB readable)
        'status_option_map' => [
            // these are Trello dropdown *option ids*
            '64a0aaaabbbbccccddddeeee' => 'planned',
            '64a0aaaabbbbccccddddeeef' => 'active',
            '64a0aaaabbbbccccddddee00' => 'closed',
        ],
    ],

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
