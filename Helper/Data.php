<?php

namespace BlueExpress\Shipping\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

     /**
      * Data constructor.
      *
      * @param ScopeConfigInterface $scopeConfig
      */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig  = $scopeConfig;
    }

    /**
     * Function para conexion Url Servicio Blue
     *
     * @return string
     */
    public function getUrlBx()
    {
        return $this->_scopeConfig->getValue(
            'carriers/bluexpress/urlBx',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Function para obtener peso del producto
     *
     * @return string
     */
    public function getWeightUnit()
    {
        return $this->_scopeConfig->getValue(
            'general/locale/weight_unit',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getIsPudo()
    {
        return $this->_scopeConfig->isSetFlag(
            'carriers/pudo/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getKeyGoogle()
    {
        return $this->_scopeConfig->getValue(
            'carriers/pudo/bxKeyGoogle',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

}