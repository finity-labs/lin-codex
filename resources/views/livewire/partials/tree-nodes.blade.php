{{-- Expects $nodes (list<FinityLabs\LinCodex\Data\TreeNode>) and $current (?string, the slug being shown). Recurses for children. --}}
<ul class="codex-tree">
    @foreach ($nodes as $node)
        <li class="codex-tree__node" wire:key="tree-{{ $node->slug }}" data-codex-tree-node="{{ $node->slug }}" @if ($node->isFallback) data-fallback @endif>
            @if ($node->isGroup())
                <span class="codex-tree__group">{{ $node->label }}</span>
            @else
                <a href="{{ \FinityLabs\LinCodex\Rendering\ArticlePath::href($node->slug) }}" @class(['codex-tree__article', 'codex-tree__article--active' => $node->slug === $current]) wire:click.prevent="show('{{ $node->slug }}')" @if ($node->slug === $current) aria-current="page" @endif>{{ $node->label }}</a>
            @endif
            @if ($node->children !== [])
                <div class="codex-tree__children">
                    @include('lin-codex::livewire.partials.tree-nodes', ['nodes' => $node->children, 'current' => $current])
                </div>
            @endif
        </li>
    @endforeach
</ul>
