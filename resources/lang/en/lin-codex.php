<?php

declare(strict_types=1);

return [

    'enums' => [
        'article_format' => [
            'markdown' => 'Markdown',
            'html' => 'HTML',
        ],
        'visibility' => [
            'public' => 'Public',
            'authenticated' => 'Authenticated',
        ],
        'context_type' => [
            'class' => 'Page class',
            'route' => 'Route',
            'url' => 'URL',
        ],
        'revision_reason' => [
            'manual' => 'Manual',
            'import' => 'Import',
            'ai_rewrite' => 'AI rewrite',
        ],
        'fallback_behaviour' => [
            'show_default' => 'Show default language',
            'hide' => 'Hide',
        ],
        'source_warning_kind' => [
            'invalid_front_matter' => 'Invalid front matter',
            'shared_key_ignored' => 'Shared key ignored',
            'missing_default_locale' => 'Missing default locale',
            'unknown_value' => 'Unknown value',
            'invalid_context' => 'Invalid context',
            'duplicate_slug' => 'Duplicate slug',
            'unknown_key' => 'Unknown key',
            'invalid_slug' => 'Invalid slug',
        ],
        'search_field' => [
            'title' => 'Title',
            'keywords' => 'Keywords',
            'excerpt' => 'Excerpt',
            'body' => 'Body',
        ],
        'search_strategy' => [
            'full_text' => 'Full text',
            'like' => 'LIKE',
        ],
    ],

    'source_warnings' => [
        'invalid_front_matter' => ':path: the front matter could not be parsed (:detail); the file was skipped.',
        'shared_key_ignored' => ':path: :detail only count in the default-locale file and were ignored.',
        'missing_default_locale' => ':path: there is no default-locale file for this article, so this file supplies its metadata.',
        'unknown_value' => ':path: :detail; the default was used.',
        'invalid_context' => ':path: ":detail" is not [panel:]class|route|url:key and was dropped.',
        'duplicate_slug' => ':path: slug ":detail" is already taken by an earlier file; this file was skipped.',
        'unknown_key' => ':path: ":detail" is not a front matter key; the folder decides the parent.',
        'invalid_slug' => ':path: :detail',
    ],

    'callouts' => [
        'note' => 'Note',
        'tip' => 'Tip',
        'important' => 'Important',
        'warning' => 'Warning',
        'caution' => 'Caution',
    ],

    'anchor_label' => 'Link to :heading',
    'details_default' => 'Details',

    'fallback_notice' => 'This article is not yet available in your language. Showing the :language version.',

    'api' => [
        'not_found' => 'Article not found.',
        'rate_limited' => 'Too many searches. Try again in :seconds seconds.',
        'missing_query' => 'The q parameter is required.',
        'invalid_limit' => 'The limit parameter must be a whole number.',
    ],

    'ui' => [
        'help' => 'Help',
        'title' => 'Help',
        'close' => 'Close',
        'back' => 'Back',
        'this_page' => 'This page',
        'also_on_this_page' => 'Also on this page',
        'no_help_for_page' => 'No help for this page yet.',
        'pick_a_topic' => 'Pick a topic from the list.',
        'search' => 'Search',
        'search_placeholder' => 'Search the help',
        'no_results' => 'Nothing found.',
        'rate_limited' => 'Too many searches. Try again in :seconds seconds.',
        'browse' => 'Browse',
        'on_this_page' => 'On this page',
        'related' => 'Related',
        'not_found' => 'This article is not available.',
        'help_center' => 'Help center',
        'back_to_app' => 'Back to :app',
        'toggle_tree' => 'Topics',
        'open_help_center' => 'Open the help center',
        'lightbox_close' => 'Close image',
        'shortcut_hint' => 'Press :shortcut',
    ],

    /*
     * Starter text codex:make writes into a new article. Add a `make` group
     * under lang/vendor/lin-codex/{locale}/lin-codex.php to localise it; a
     * locale without one gets the English text.
     */
    'make' => [
        'heading' => 'Overview',
        'intro' => 'Say what this page is for and what the reader can do here.',
        'step_one' => 'Open the page from the menu.',
        'step_two' => 'Fill in the form and save.',
        'figure_alt' => 'Screenshot',
        'figure_caption' => 'What the reader sees after saving.',
        'tip' => 'Keep articles short and link to related articles instead of repeating them.',
    ],

    /*
     * Labels for folder groups (folders without an index file), keyed by the
     * group's full slug: 'users' => 'Users', 'billing/archive' => 'Archive'.
     * A missing key falls back to the humanised last folder name.
     */
    'groups' => [],

];
