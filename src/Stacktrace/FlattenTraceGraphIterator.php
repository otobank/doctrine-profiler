<?php
namespace Otobank\Doctrine\Profiler\Stacktrace;

/**
 * The following code are derived from code of the pixers/PixersDoctrineProfilerBundle
 * Code subject to the BSD-3-Clause license https://github.com/pixers/PixersDoctrineProfilerBundle/blob/master/LICENSE
 * Copyright (c) PIXERS Ltd.
 *
 * FlattenTraceGraphIterator.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class FlattenTraceGraphIterator extends \ArrayIterator implements \RecursiveIterator
{
    /**
     * {@inheritdoc}
     */
    public function getChildren()
    {
        return new self($this->getFlattenChildren($this->current()));
    }

    /**
     * {@inheritdoc}
     */
    public function hasChildren()
    {
        return $this->current() && ! empty($this->current()->getNodes());
    }

    /**
     * @param Node $node
     *
     * @return array
     */
    public function getFlattenChildren(Node $node)
    {
        $flatten = [];
        foreach ($node->getNodes() as $subNode) {
            if (count($subNode->getNodes()) > 1 || isset($subNode->getTrace()['query_source']) || ! empty($subNode->getValues())) {
                $flatten[] = $subNode;
            } else {
                $flatten = array_merge($flatten, $this->getFlattenChildren($subNode));
            }
        }

        return $flatten;
    }
}
