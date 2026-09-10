<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Translation;

/**
 * The cheap guarantee that a translated body still has the same code and
 * the same links as its source.
 *
 * Two failures ship silently to readers when nobody looks: a model that
 * drops a fenced block (or wraps the whole body in one) and a model that
 * "translates" a link target, turning roles.md into rollen.md and breaking
 * every cross-reference. Both are invisible in a diff of prose but obvious
 * in a multiset comparison.
 *
 * Multisets, not sequences: a translated sentence may legitimately put two
 * links in the other order, and German word order does that often, so the
 * lists are sorted before they are compared. Link text and image alt text
 * are prose and are supposed to change, so only the target inside the
 * parentheses is read, and a link title ("Roles") is ignored the same way.
 *
 * This class only answers the question. Whether the translator acts on the
 * answer is the lin-codex.ai.check_structure toggle, read there, not here.
 */
final class StructureCheck
{
    /**
     * True when the multisets of fenced code blocks and of link and image
     * targets are the same in both texts.
     */
    public function passes(string $source, string $output): bool
    {
        $sourceBlocks = $this->codeBlocks($source);
        $outputBlocks = $this->codeBlocks($output);
        sort($sourceBlocks);
        sort($outputBlocks);

        if ($sourceBlocks !== $outputBlocks) {
            return false;
        }

        $sourceTargets = $this->linkTargets($source);
        $outputTargets = $this->linkTargets($output);
        sort($sourceTargets);
        sort($outputTargets);

        return $sourceTargets === $outputTargets;
    }

    /**
     * The contents of every fenced block (``` or ~~~ fences, any info
     * string), in document order. Inline code is not a block.
     *
     * @return list<string>
     */
    public function codeBlocks(string $markdown): array
    {
        preg_match_all('/^(`{3,}|~{3,})[^\n]*\n(.*?)^\1[ \t]*$/ms', $markdown, $matches);

        $blocks = [];

        foreach ($matches[2] as $block) {
            $blocks[] = rtrim($block, "\n");
        }

        return $blocks;
    }

    /**
     * Every [text](target) and ![alt](target) target, in document order,
     * without a title part.
     *
     * @return list<string>
     */
    public function linkTargets(string $markdown): array
    {
        preg_match_all('/!?\[[^\]]*\]\(\s*([^\s)]+)(?:\s+"[^"]*")?\s*\)/', $markdown, $matches);

        return $matches[1];
    }
}
