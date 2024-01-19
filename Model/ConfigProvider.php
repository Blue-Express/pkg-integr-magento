<?php

namespace BlueExpress\Shipping\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use BlueExpress\Shipping\Helper\Data;

class ConfigProvider implements ConfigProviderInterface
{

    /**
     * @var Data
     */
      protected $helper;

    /**
     * @param Data $helper
     */

    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }


    /**
     * @return array[]
     */
    public function getConfig()
    {

        return [
           'bxpudo' => [
               'pudo_enabled' =>  $this->helper->getIsPudo(),
               'key_google' => $this->helper->getKeyGoogle()
           ]
        ];
    }

}
