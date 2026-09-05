<?php

declare(strict_types=1);

return [

    'enums' => [
        'article_format' => [
            'markdown' => 'Markdown',
            'html' => 'HTML',
        ],
        'visibility' => [
            'public' => 'Nyilvános',
            'authenticated' => 'Bejelentkezett',
        ],
        'context_type' => [
            'class' => 'Oldalosztály',
            'route' => 'Útvonal',
            'url' => 'URL',
        ],
        'revision_reason' => [
            'manual' => 'Kézi',
            'import' => 'Importálás',
            'ai_rewrite' => 'MI-átírás',
            'restore' => 'Visszaállítás',
        ],
        'fallback_behaviour' => [
            'show_default' => 'Alapértelmezett nyelv mutatása',
            'hide' => 'Elrejtés',
        ],
        'source_warning_kind' => [
            'invalid_front_matter' => 'Érvénytelen front matter',
            'shared_key_ignored' => 'Közös kulcs figyelmen kívül hagyva',
            'missing_default_locale' => 'Hiányzó alapértelmezett nyelv',
            'unknown_value' => 'Ismeretlen érték',
            'invalid_context' => 'Érvénytelen kontextus',
            'duplicate_slug' => 'Ismétlődő slug',
            'unknown_key' => 'Ismeretlen kulcs',
            'invalid_slug' => 'Érvénytelen slug',
        ],
        'search_field' => [
            'title' => 'Cím',
            'keywords' => 'Kulcsszavak',
            'excerpt' => 'Kivonat',
            'body' => 'Törzsszöveg',
        ],
        'search_strategy' => [
            'full_text' => 'Teljes szöveges',
            'like' => 'LIKE',
        ],
    ],

    'source_warnings' => [
        'invalid_front_matter' => ':path: a front matter nem olvasható (:detail); a fájl kimaradt.',
        'shared_key_ignored' => ':path: :detail csak az alapértelmezett nyelv fájljában számít, ezért figyelmen kívül maradt.',
        'missing_default_locale' => ':path: ehhez a cikkhez nincs fájl az alapértelmezett nyelven, ezért a metaadatokat ez a fájl adja.',
        'unknown_value' => ':path: :detail; helyette az alapértelmezett érték szerepel.',
        'invalid_context' => ':path: a(z) ":detail" nem [panel:]class|route|url:key alakú, ezért kimaradt.',
        'duplicate_slug' => ':path: a(z) ":detail" slugot már egy korábbi fájl használja; ez a fájl kimaradt.',
        'unknown_key' => ':path: a(z) ":detail" nem front matter kulcs; a szülőt a mappa határozza meg.',
        'invalid_slug' => ':path: :detail',
    ],

    'callouts' => [
        'note' => 'Megjegyzés',
        'tip' => 'Tipp',
        'important' => 'Fontos',
        'warning' => 'Figyelmeztetés',
        'caution' => 'Vigyázat',
    ],

    'anchor_label' => 'Hivatkozás ide: :heading',
    'details_default' => 'Részletek',

    'fallback_notice' => 'Ez a cikk az Ön nyelvén még nem érhető el. A(z) :language nyelvű változatot mutatjuk.',

    'api' => [
        'not_found' => 'A cikk nem található.',
        'rate_limited' => 'Túl sok keresés. Próbálja újra :seconds másodperc múlva.',
        'missing_query' => 'A q paraméter kötelező.',
        'invalid_limit' => 'A limit paraméter csak egész szám lehet.',
    ],

    'ui' => [
        'help' => 'Súgó',
        'title' => 'Súgó',
        'close' => 'Bezárás',
        'back' => 'Vissza',
        'this_page' => 'Ez az oldal',
        'also_on_this_page' => 'Szintén ezen az oldalon',
        'no_help_for_page' => 'Ehhez az oldalhoz még nincs súgó.',
        'pick_a_topic' => 'Válasszon egy témát a listából.',
        'search' => 'Keresés',
        'search_placeholder' => 'Keresés a súgóban',
        'no_results' => 'Nincs találat.',
        'rate_limited' => 'Túl sok keresés. Próbálja újra :seconds másodperc múlva.',
        'browse' => 'Tallózás',
        'on_this_page' => 'Ezen az oldalon',
        'related' => 'Kapcsolódó cikkek',
        'not_found' => 'Ez a cikk nem érhető el.',
        'help_center' => 'Súgóközpont',
        'back_to_app' => 'Vissza ide: :app',
        'toggle_tree' => 'Témák',
        'open_help_center' => 'Súgóközpont megnyitása',
        'lightbox_close' => 'Kép bezárása',
        'shortcut_hint' => 'Nyomja meg: :shortcut',
    ],

    /*
     * A kezdőszöveg, amelyet a codex:make egy új cikkbe ír. A
     * lang/vendor/lin-codex/{locale}/lin-codex.php alatti `make` csoport
     * fordítja le; amelyik nyelvhez nincs ilyen, az az angol szöveget kapja.
     */
    'make' => [
        'heading' => 'Áttekintés',
        'intro' => 'Írja le, mire való ez az oldal, és mit tehet itt az olvasó.',
        'step_one' => 'Nyissa meg az oldalt a menüből.',
        'step_two' => 'Töltse ki az űrlapot, és mentse el.',
        'figure_alt' => 'Képernyőkép',
        'figure_caption' => 'Ezt látja az olvasó mentés után.',
        'tip' => 'Tartsa rövidre a cikkeket, és ismétlés helyett hivatkozzon a kapcsolódó cikkekre.',
    ],

    /*
     * A mappacsoportok (indexfájl nélküli mappák) címkéi a csoport teljes
     * slugja szerint: 'users' => 'Felhasználók', 'billing/archive' =>
     * 'Archívum'. Hiányzó kulcs esetén az utolsó mappanév olvasható alakja
     * jelenik meg.
     */
    'groups' => [],

];
