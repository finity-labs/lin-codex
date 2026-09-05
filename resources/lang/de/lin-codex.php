<?php

declare(strict_types=1);

return [

    'enums' => [
        'article_format' => [
            'markdown' => 'Markdown',
            'html' => 'HTML',
        ],
        'visibility' => [
            'public' => 'Öffentlich',
            'authenticated' => 'Angemeldet',
        ],
        'context_type' => [
            'class' => 'Seitenklasse',
            'route' => 'Route',
            'url' => 'URL',
        ],
        'revision_reason' => [
            'manual' => 'Manuell',
            'import' => 'Import',
            'ai_rewrite' => 'KI-Überarbeitung',
            'restore' => 'Wiederherstellung',
        ],
        'fallback_behaviour' => [
            'show_default' => 'Standardsprache anzeigen',
            'hide' => 'Ausblenden',
        ],
        'source_warning_kind' => [
            'invalid_front_matter' => 'Ungültige Front Matter',
            'shared_key_ignored' => 'Gemeinsamer Schlüssel ignoriert',
            'missing_default_locale' => 'Standardsprache fehlt',
            'unknown_value' => 'Unbekannter Wert',
            'invalid_context' => 'Ungültiger Kontext',
            'duplicate_slug' => 'Doppelter Slug',
            'unknown_key' => 'Unbekannter Schlüssel',
            'invalid_slug' => 'Ungültiger Slug',
        ],
        'search_field' => [
            'title' => 'Titel',
            'keywords' => 'Schlagwörter',
            'excerpt' => 'Auszug',
            'body' => 'Inhalt',
        ],
        'search_strategy' => [
            'full_text' => 'Volltext',
            'like' => 'LIKE',
        ],
    ],

    'source_warnings' => [
        'invalid_front_matter' => ':path: die Front Matter konnte nicht gelesen werden (:detail); die Datei wurde übersprungen.',
        'shared_key_ignored' => ':path: :detail zählen nur in der Datei der Standardsprache und wurden ignoriert.',
        'missing_default_locale' => ':path: für diesen Artikel gibt es keine Datei in der Standardsprache, daher liefert diese Datei seine Metadaten.',
        'unknown_value' => ':path: :detail; der Standardwert wurde verwendet.',
        'invalid_context' => ':path: ":detail" hat nicht die Form [panel:]class|route|url:key und wurde verworfen.',
        'duplicate_slug' => ':path: der Slug ":detail" ist bereits durch eine frühere Datei belegt; diese Datei wurde übersprungen.',
        'unknown_key' => ':path: ":detail" ist kein Front-Matter-Schlüssel; der Ordner bestimmt den übergeordneten Artikel.',
        'invalid_slug' => ':path: :detail',
    ],

    'callouts' => [
        'note' => 'Hinweis',
        'tip' => 'Tipp',
        'important' => 'Wichtig',
        'warning' => 'Warnung',
        'caution' => 'Achtung',
    ],

    'anchor_label' => 'Link zu :heading',
    'details_default' => 'Details',

    'fallback_notice' => 'Dieser Artikel ist in Ihrer Sprache noch nicht verfügbar. Angezeigt wird die Fassung in :language.',

    'api' => [
        'not_found' => 'Artikel nicht gefunden.',
        'rate_limited' => 'Zu viele Suchanfragen. Versuchen Sie es in :seconds Sekunden erneut.',
        'missing_query' => 'Der Parameter q ist erforderlich.',
        'invalid_limit' => 'Der Parameter limit muss eine ganze Zahl sein.',
    ],

    'ui' => [
        'help' => 'Hilfe',
        'title' => 'Hilfe',
        'close' => 'Schließen',
        'back' => 'Zurück',
        'this_page' => 'Diese Seite',
        'also_on_this_page' => 'Ebenfalls auf dieser Seite',
        'no_help_for_page' => 'Für diese Seite gibt es noch keine Hilfe.',
        'pick_a_topic' => 'Wählen Sie ein Thema aus der Liste.',
        'search' => 'Suchen',
        'search_placeholder' => 'Hilfe durchsuchen',
        'no_results' => 'Nichts gefunden.',
        'rate_limited' => 'Zu viele Suchanfragen. Versuchen Sie es in :seconds Sekunden erneut.',
        'browse' => 'Durchsuchen',
        'on_this_page' => 'Auf dieser Seite',
        'related' => 'Verwandte Artikel',
        'not_found' => 'Dieser Artikel ist nicht verfügbar.',
        'help_center' => 'Hilfe-Center',
        'back_to_app' => 'Zurück zu :app',
        'toggle_tree' => 'Themen',
        'open_help_center' => 'Hilfe-Center öffnen',
        'lightbox_close' => 'Bild schließen',
        'shortcut_hint' => 'Drücken Sie :shortcut',
    ],

    /*
     * Startertext, den codex:make in einen neuen Artikel schreibt. Eine
     * `make`-Gruppe unter lang/vendor/lin-codex/{locale}/lin-codex.php
     * übersetzt ihn; eine Sprache ohne eigene Gruppe bekommt den englischen
     * Text.
     */
    'make' => [
        'heading' => 'Überblick',
        'intro' => 'Beschreiben Sie, wozu diese Seite dient und was sich hier erledigen lässt.',
        'step_one' => 'Öffnen Sie die Seite über das Menü.',
        'step_two' => 'Füllen Sie das Formular aus und speichern Sie.',
        'figure_alt' => 'Screenshot',
        'figure_caption' => 'Was nach dem Speichern zu sehen ist.',
        'tip' => 'Halten Sie Artikel kurz und verlinken Sie verwandte Artikel, statt sie zu wiederholen.',
    ],

    /*
     * Bezeichnungen für Ordnergruppen (Ordner ohne Indexdatei), abgelegt
     * unter dem vollständigen Slug der Gruppe: 'users' => 'Benutzer',
     * 'billing/archive' => 'Archiv'. Ohne Eintrag wird der letzte
     * Ordnername in lesbarer Form verwendet.
     */
    'groups' => [],

];
