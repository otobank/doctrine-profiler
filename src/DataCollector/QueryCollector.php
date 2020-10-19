<?php
namespace Otobank\Doctrine\Profiler\DataCollector;

use Exception;
use Otobank\Doctrine\Profiler\Logging\StackTraceLogger;
use Otobank\Doctrine\Profiler\Stacktrace\FlattenTraceGraphIterator;
use Otobank\Doctrine\Profiler\Stacktrace\Node;
use Otobank\Doctrine\Profiler\Stacktrace\ValueAccessor;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * The following code are derived from code of the pixers/PixersDoctrineProfilerBundle
 * Code subject to the BSD-3-Clause license https://github.com/pixers/PixersDoctrineProfilerBundle/blob/master/LICENSE
 * Copyright (c) PIXERS Ltd.
 *
 * QueryCollector.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class QueryCollector // extends DataCollector
{
    /**
     * @var StackTraceLogger
     */
    protected $logger;

    /**
     * @var Stopwatch
     */
    protected $stopwatch;
    private $data;

    /**
     * @param StackTraceLogger $logger
     * @param Stopwatch        $stopwatch
     */
    public function __construct(StackTraceLogger $logger, Stopwatch $stopwatch)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * @param Exception $exception
     */
    public function collect(Exception $exception = null)
    {
        $this->data['queries'] = $this->logger->getQueries();
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        $queries = [];
        foreach ($this->data['queries'] as $query) {
            $key = $query['trace_hash'] . $query['sql_hash'];
            $this->selectQuerySource($query);
            if (isset($queries[$key])) {
                $queries[$key]['count'] += 1;
                $queries[$key]['memory'] += $query['memory'];
                $queries[$key]['hydration_time'] += $query['hydration_time'];
                $queries[$key]['execution_time'] += $query['execution_time'];
            } else {
                $queries[$key] = $query;
                $queries[$key]['count'] = 1;
            }
        }

        return $queries;
    }

    /**
     * Returns query count.
     *
     * @return int
     */
    public function getCount()
    {
        return count($this->data['queries']);
    }

    /**
     * Returns duplicated queries count.
     *
     * @return int
     */
    public function getDuplicatedCount()
    {
        $count = 0;
        foreach ($this->getQueries() as $query) {
            if ($query['count'] > 1) {
                $count += $query['count'] - 1;
            }
        }

        return $count;
    }

    /**
     * Returns queries execution time.
     *
     * @return int
     */
    public function getExecutionTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += $query['execution_time'];
        }

        return $time;
    }

    /**
     * Returns queries memory usage.
     *
     * @return int
     */
    public function getMemoryUsage()
    {
        $memory = 0;
        foreach ($this->data['queries'] as $query) {
            $memory += $query['memory'];
        }

        return $memory;
    }

    /**
     * Return queries hydration time.
     *
     * @return int
     */
    public function getHydrationTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += $query['hydration_time'];
        }

        return $time;
    }

    /**
     * Returns queries stacktrace tree root node
     *
     * @return Node
     */
    public function getCallGraph()
    {
        $node = $root = new Node([]);
        foreach ($this->getQueries() as $query) {
            foreach (array_reverse($query['trace']) as $trace) {
                $node = $node->push($trace);
            }
            $node->addValue($query);
            $node = $root;
        }

        return $root;
    }

    /**
     * Returns filtered/flatten queries stacktrace tree iterator
     *
     * @param int $mode
     *
     * @return FlattenTraceGraphIterator
     */
    public function getFlattenCallGraph($mode = \RecursiveIteratorIterator::SELF_FIRST)
    {
        return new FlattenTraceGraphIterator($this->getCallGraph()->getNodes(), $mode);
    }

    /**
     * @return ValueAccessor
     */
    public function getNodeValueAccessor()
    {
        return new ValueAccessor();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'doctrine_profiler';
    }

    public function reset()
    {
        $this->data = [];
    }

    /**
     * Marks selected trace item as query source based on invoking class namespace
     *
     * @param array $query
     */
    protected function selectQuerySource(&$query)
    {
        foreach ($query['trace'] as $i => &$trace) {
            $isSource = true;
            foreach ($this->getNamespacesCutoff() as $namespace) {
                $namespace = trim($namespace, '/\\') . '\\';
                if (isset($trace['class']) && strpos($trace['class'], $namespace) !== false) {
                    $isSource = false;
                }
            }
            if ($isSource) {
                $query['trace'][$i - 1]['query_source'] = true;
                break;
            }
        }
    }

    /**
     * Returns "internal" namespaces for query source selection
     *
     * @return array
     */
    protected function getNamespacesCutoff()
    {
        return [
            'Otobank\Doctrine\Profiler',
            'Doctrine',
            'Symfony',
        ];
    }
}
