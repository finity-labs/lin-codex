{{-- Expects $result (FinityLabs\LinCodex\Search\SearchResult). The snippet is built by SnippetBuilder: everything escaped, <mark> only. --}}
@if ($result->rateLimited)
    <p class="codex-rate-limited">{{ __('lin-codex::lin-codex.ui.rate_limited', ['seconds' => $result->retryAfterSeconds]) }}</p>
@elseif ($result->hits === [])
    <p class="codex-empty">{{ __('lin-codex::lin-codex.ui.no_results') }}</p>
@else
    <ul class="codex-search-results">
        @foreach ($result->hits as $hit)
            <li wire:key="hit-{{ $hit->slug }}" @if ($hit->isFallback) data-fallback @endif>
                <a href="{{ \FinityLabs\LinCodex\Rendering\ArticlePath::href($hit->slug) }}" class="codex-search-hit" data-codex-hit="{{ $hit->slug }}" wire:click.prevent="show('{{ $hit->slug }}')">
                    <span class="codex-search-hit__title">{{ $hit->title }}</span>
                    @if ($hit->sectionPath !== [])
                        <span class="codex-search-hit__path">{{ implode(' › ', $hit->sectionPath) }}</span>
                    @endif
                    <span class="codex-search-hit__snippet">{!! $hit->snippet !!}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
