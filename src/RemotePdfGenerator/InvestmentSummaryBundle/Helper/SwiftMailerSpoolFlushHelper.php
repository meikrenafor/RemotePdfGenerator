<?php

namespace RemotePdfGenerator\InvestmentSummaryBundle\Helper;

/**
 * Class SwiftMailerSpoolFlushHelper
 * @package InvestmentSummaryBundle\Helper
 */
class SwiftMailerSpoolFlushHelper extends BaseControllerHelper
{
    /**
     * @return int|null
     */
    public function flushSpoolQueue() {
        $mailer = $this->container->get('mailer');
        $transport = $mailer->getTransport();

        if ($transport instanceof \Swift_Transport_SpoolTransport) {
            $spool = $transport->getSpool();
            
            return $spool->flushQueue($this->container->get('swiftmailer.transport.real'));
        }

        return null;
    }
}
