{{-- Expects $read (FinityLabs\LinCodex\Reading\ReadArticle), $fallbackNotice (?string) and $showToc (bool). The body HTML was sanitised by the renderer. --}}
<article class="codex-article" data-codex-slug="{{ $read->article->slug }}" @if ($read->isFallback) data-fallback @endif>
    @if ($read->breadcrumbs !== [])
        <nav class="codex-breadcrumbs" aria-label="{{ __('lin-codex::lin-codex.ui.browse') }}">
            @foreach ($read->breadcrumbs as $crumb)
                <a class="codex-breadcrumb" href="{{ \FinityLabs\LinCodex\Rendering\ArticlePath::href($crumb['slug']) }}" wire:click.prevent="show('{{ $crumb['slug'] }}')">{{ $crumb['title'] }}</a>
            @endforeach
        </nav>
    @endif
    <h2 class="codex-article__title">{{ $read->translation->title }}</h2>
    @if ($fallbackNotice !== null)
        <p class="codex-fallback-notice">{{ $fallbackNotice }}</p>
    @endif
    @if ($showToc && $read->rendered->toc !== [])
        {{-- Collapsed under three headings, open from three on. --}}
        <details class="codex-toc" @if (count($read->rendered->toc) >= 3) open @endif>
            <summary class="codex-toc__toggle">{{ __('lin-codex::lin-codex.ui.on_this_page') }}</summary>
            <ul class="codex-toc__list">
                @foreach ($read->rendered->toc as $entry)
                    <li class="codex-toc__item" data-level="{{ $entry['level'] }}"><a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a></li>
                @endforeach
            </ul>
        </details>
    @endif
    <div class="codex-article__body" lang="{{ $read->locale }}">{!! $read->rendered->html !!}</div>
    @if ($read->related !== [])
        <section class="codex-related">
            <h3 class="codex-related__title">{{ __('lin-codex::lin-codex.ui.related') }}</h3>
            <ul>
                @foreach ($read->related as $entry)
                    <li><a class="codex-related__link" href="{{ \FinityLabs\LinCodex\Rendering\ArticlePath::href($entry['slug']) }}" wire:click.prevent="show('{{ $entry['slug'] }}')">{{ $entry['title'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</article>
