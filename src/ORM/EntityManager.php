<?php
namespace Otobank\Doctrine\Profiler\ORM;

use Doctrine\Common\EventManager;
use Doctrine\DBAL;
use Doctrine\ORM;
use Otobank\Doctrine\Profiler\Logging\StackTraceLogger;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * The following code are derived from code of the pixers/PixersDoctrineProfilerBundle
 * Code subject to the BSD-3-Clause license https://github.com/pixers/PixersDoctrineProfilerBundle/blob/master/LICENSE
 * Copyright (c) PIXERS Ltd.
 *
 * Profiler Entity Manager.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class EntityManager extends ORM\EntityManager
{
    /**
     * @var Stopwatch
     */
    protected $stopWatch;

    /**
     * @var StackTraceLogger
     */
    protected $logger;

    /**
     * {@inheritdoc}
     * @phpstan-return EntityManager
     */
    public static function create($conn, ORM\Configuration $config, EventManager $eventManager = null)
    {
        if (! $config->getMetadataDriverImpl()) {
            throw ORM\ORMException::missingMappingDriverImpl();
        }

        switch (true) {
            case is_array($conn):
                $conn = DBAL\DriverManager::getConnection(
                    $conn,
                    $config,
                    ($eventManager ?: new EventManager())
                );
                break;
            case $conn instanceof DBAL\Connection:
                if ($eventManager !== null && $conn->getEventManager() !== $eventManager) {
                    throw ORM\ORMException::mismatchedEventManager();
                }
                break;
            default:
                throw new \InvalidArgumentException('Invalid argument: ' . $conn);
        }

        return new self($conn, $config, $conn->getEventManager());
    }

    /**
     * @param Stopwatch $stopWatch
     *
     * @return EntityManager
     */
    public function setStopWatch(Stopwatch $stopWatch)
    {
        $this->stopWatch = $stopWatch;

        return $this;
    }

    public function setLogger(StackTraceLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function newHydrator($hydrationMode)
    {
        return new Hydrator($this, parent::newHydrator($hydrationMode), $this->logger, $this->stopWatch);
    }
}
