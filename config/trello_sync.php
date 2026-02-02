<?php

return [
    'registry_board_id' => env('TRELLO_REGISTRY_BOARD_ID'),

    'sprint_control' => [
        // Custom Field IDs from the *registry board* (optional; name-based lookup used if missing)
        'control_field_ids' => [
            'status' => env('TRELLO_REGISTRY_STATUS_FIELD_ID', ''),
            'starts_at' => env('TRELLO_REGISTRY_STARTS_AT_FIELD_ID', ''),
            'ends_at' => env('TRELLO_REGISTRY_ENDS_AT_FIELD_ID', ''),
            'sprint_board' => env('TRELLO_REGISTRY_SPRINT_BOARD_FIELD_ID', ''),
            'done_list_ids' => env('TRELLO_REGISTRY_DONE_LIST_IDS_FIELD_ID', ''),
        ],

        // Custom Field names from the *registry board* (default lookup)
        'control_field_names' => [
            'status' => env('TRELLO_REGISTRY_STATUS_FIELD_NAME', 'Sprint Status'),
            'starts_at' => env('TRELLO_REGISTRY_STARTS_AT_FIELD_NAME', 'Starts At'),
            'ends_at' => env('TRELLO_REGISTRY_ENDS_AT_FIELD_NAME', 'Ends At'),
            'sprint_board' => env('TRELLO_REGISTRY_SPRINT_BOARD_FIELD_NAME', 'Sprint Board'),
            'done_list_ids' => env('TRELLO_REGISTRY_DONE_LIST_IDS_FIELD_NAME', 'Done List Ids'),
        ],

        // For dropdown mapping: option-id -> our canonical status slug
        // (You can also just store option id, but mapping keeps local DB readable)
        'status_option_map' => [
            // these are Trello dropdown *option ids*
            '64a0aaaabbbbccccddddeeee' => 'planned',
            '6968f84ab66ae5522fa850cf' => 'active',
            '6968f84ab66ae5522fa850d0' => 'closed',
        ],
    ],

    'sprint_board' => [
        'done_list_names' => array_values(array_filter(array_map('trim', explode(',', env('TRELLO_DONE_LIST_NAMES', 'Done'))))),
        'remakes_list_name' => env('TRELLO_REMAKES_LIST_NAME', 'Remakes'),
        'sprint_admin_list_name' => env('TRELLO_SPRINT_ADMIN_LIST_NAME', 'Sprint Admin'),
        'control_card_name' => env('TRELLO_SPRINT_CONTROL_CARD_NAME', 'Sprint Control'),
        'starts_at_field_name' => env('TRELLO_SPRINT_STARTS_AT_FIELD_NAME', 'Starts at'),
        'ends_at_field_name' => env('TRELLO_SPRINT_ENDS_AT_FIELD_NAME', 'Ends at'),
        'status_field_name' => env('TRELLO_SPRINT_STATUS_FIELD_NAME', 'Sprint Status'),
        'remake_label_field_name' => env('TRELLO_REMAKE_LABEL_FIELD_NAME', 'Remake Label'),
        'closed_status_label' => env('TRELLO_SPRINT_CLOSED_STATUS_LABEL', 'Closed'),
        'diverged_label_name' => env('TRELLO_SPRINT_DIVERGED_LABEL_NAME', 'Diverged Dates'),
        'diverged_label_color' => env('TRELLO_SPRINT_DIVERGED_LABEL_COLOR', 'red'),
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
        'addLabelToCard',
        'removeLabelFromCard',
    ],

    /******************************
     * TRELLO REMAKE LABEL HANDLING
     * ****************************
     *
     * There are two separate concepts here driven by a common functionality on trello's side.
     *
     * A remake label can represent different outcomes. A label that appears in the 'remake_reason_labels' list
     * represents an 'accepted' remake and so the amount of work required to complete it should be defined on the
     * card and therefore cannot be standardised. The label itself WILL need to affect the Remake Reasons pie-chart
     *
     * A remake label that appears in the 'remake_label_actions.remove' list is essentially telling us to disregard
     * any impacts to the Remake Reasons pie-chart and any of the overall remake figures BUT may have required some
     * work by the team to handle the request - which we have standardised with the story points defined.
     */

    // Labels that influence remake tracking when added/removed on cards.
    // Map label name => points override (0 disables points for that remake).
    'remake_label_actions' => [
        'remove' => [
            'RM Cancelled' => 1,
            'RM Accidental' => 1,
            'RM Rejected' => 2,
            'RM Test' => 0,
            'RM Duplicate' => 0,
        ],
        'restore' => [
            'RM Restored',
        ],
    ],

    // Labels used to categorize remake reasons (used in wallboard breakdown).
    'remake_reason_labels' => [
        'RM Programming Related',
        'RM Punch',
        'RM Shakeout',
        'RM Folding',
        'RM Welding',
        'RM Spraying',
        'RM Assembly',
        'RM Dispatch'
    ],

    // Trello card cover options for manual edits in the admin UI.
    'card_cover_colors' => [
        'none',
        'pink',
        'yellow',
        'lime',
        'blue',
        'black',
        'orange',
        'red',
        'purple',
        'sky',
        'green',
    ],
    'card_cover_sizes' => [
        'normal',
        'full',
    ],
    'card_cover_brightness' => [
        'light',
        'dark',
    ],

    // Users allowed to access Trello editing tools in the Remakes UI.
    'trello_actions_allowed_emails' => [
        config('auth.admin_user.email'),
    ],

    // How many actions to request per poll
    'poll_limit' => 200,

    // Snapshot policy (you can tune later)
    'take_ad_hoc_snapshots' => true,
    'ad_hoc_snapshot_every_minutes' => 60,
];
