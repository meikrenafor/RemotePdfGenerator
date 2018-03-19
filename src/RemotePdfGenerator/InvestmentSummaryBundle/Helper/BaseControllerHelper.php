<?php

namespace RemotePdfGenerator\InvestmentSummaryBundle\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BaseControllerHelper
 * @package InvestmentSummaryBundle
 */
abstract class BaseControllerHelper
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * BaseControllerHelper constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface & $container)
    {
        $this->container = $container;
    }
}
