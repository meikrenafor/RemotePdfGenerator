<?php

namespace RemotePdfGenerator\InvestmentSummaryBundle\Helper;

use JMS\Serializer\SerializationContext;

/**
 * Class JsonSerializerHelper
 * @package InvestmentSummaryBundle\Helper
 */
class JsonSerializerHelper extends BaseControllerHelper
{
    /**
     * @param $object
     * @param null|string $group
     * @return mixed|null|string
     */
    public function getJson($object, $group = null)
    {
        $serializer = $this->container->get('jms_serializer');

        $result = null;
        if (!is_null($object)) {
            if (!is_null($group)) {
                $result = $serializer->serialize($object, 'json', SerializationContext::create()->setGroups([$group]));
            } else {
                $result = $serializer->serialize($object, 'json');
            }
        }

        return $result;
    }
}
