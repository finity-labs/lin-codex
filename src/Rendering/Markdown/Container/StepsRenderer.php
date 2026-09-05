<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Rendering\Markdown\Container;

use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * <ol class="codex-steps"> from the first ordered list inside a :::steps
 * container. Each item's first paragraph is the step title; the rest is the
 * body (screenshots, callouts, code). Native <ol> numbering stays for
 * assistive tech; the aria-hidden badge repeats it for CSS. A :::steps
 * without an ordered list renders its children unchanged.
 */
final class StepsRenderer
{
    public function render(FencedContainer $node, ChildNodeRendererInterface $childRenderer): string
    {
        $list = $this->orderedList($node);

        if ($list === null) {
            return $childRenderer->renderNodes($node->children());
        }

        $list->setTight(false);
        $parts = [];

        foreach ($node->children() as $child) {
            $parts[] = $child === $list
                ? $this->renderList($list, $childRenderer)
                : $childRenderer->renderNodes([$child]);
        }

        return implode($childRenderer->getBlockSeparator(), $parts);
    }

    private function orderedList(FencedContainer $node): ?ListBlock
    {
        foreach ($node->children() as $child) {
            if ($child instanceof ListBlock && $child->getListData()->type === ListBlock::TYPE_ORDERED) {
                return $child;
            }
        }

        return null;
    }

    private function renderList(ListBlock $list, ChildNodeRendererInterface $childRenderer): string
    {
        $separator = $childRenderer->getInnerSeparator();
        $start = $list->getListData()->start ?? 1;
        $attributes = ['class' => 'codex-steps'];

        if ($start !== 1) {
            $attributes['start'] = (string) $start;
        }

        $items = [];
        $number = $start;

        foreach ($list->children() as $item) {
            if ($item instanceof ListItem) {
                $items[] = $this->renderItem($item, $number++, $childRenderer);
            }
        }

        return (string) new HtmlElement('ol', $attributes, $separator.implode($childRenderer->getBlockSeparator(), $items).$separator);
    }

    private function renderItem(ListItem $item, int $number, ChildNodeRendererInterface $childRenderer): string
    {
        $separator = $childRenderer->getInnerSeparator();
        $children = $item->children();
        $first = $children[0] ?? null;
        $title = '';

        if ($first instanceof Paragraph) {
            $title = $childRenderer->renderNodes($first->children());
            array_shift($children);
        }

        $body = $childRenderer->renderNodes($children);

        $badge = new HtmlElement('span', ['class' => 'codex-step__number', 'aria-hidden' => 'true'], (string) $number);
        $heading = new HtmlElement('div', ['class' => 'codex-step__title'], $title);
        $content = new HtmlElement('div', ['class' => 'codex-step__body'], $body === '' ? '' : $separator.$body.$separator);

        return (string) new HtmlElement('li', ['class' => 'codex-step'], $separator.$badge.$separator.$heading.$separator.$content.$separator);
    }
}
