<?php declare(strict_types=1);

namespace BlueExpress\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use BlueExpress\Shipping\Model\Blueservice;
use Magento\Store\Model\ScopeInterface;

/**
 * Custom shipping model
 */
class ShippingEx extends AbstractCarrier implements CarrierInterface
{
    /**
     * Get country path
     */
    const COUNTRY_CODE_PATH = 'general/country/default';

    /**
     * @var string
     */
    protected $_code = 'bxexpress';

    /**
     * @var string
     */
    protected $_blueservice;

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Framework\App\RequestInterface $request
     * @param Blueservice $blueservice
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Framework\App\RequestInterface $request,
        Blueservice $blueservice,
        array $data = []
    ) {
        $this->_blueservice       = $blueservice;
        $this->rateResultFactory  = $rateResultFactory;
        $this->rateMethodFactory  = $rateMethodFactory;
        $this->_request           = $request;
        $this->scopeConfig        = $scopeConfig;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return bool
     */
    public function isTrackingAvailable(){

        return true;
    }

    /**
     * Is City required
     *
     * @return bool
     */
    public function isCityRequired(){

        return true;
    }

    /**
     * Is state required
     *
     * @return bool
     */
    public function isStateProvinceRequired()
    {
        return true;
    }

    /**
     * Custom Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $errorTitle = __('There are no quotes for the commune entered');
        $blueservice = $this->_blueservice;

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        /**
         * We get the ID of the country selected in the store
         */
        $countryID	= $this->getCountryByWebsite();
	    $agencyId	= null;
        $citydest   = '';

        /**
         * Base Url Store
         */
        $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager   = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $baseUrl        = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $bdRegion       = $objectManager->create('\Magento\Directory\Model\Region');

        /**
        * I look for the ID corresponding to the commune selected in admin
        */
        $storeCity      = $this->scopeConfig->getValue('general/store_information/city',ScopeInterface::SCOPE_STORE);
	    $storeRegion    = $this->scopeConfig->getValue('general/store_information/region_id',ScopeInterface::SCOPE_STORE);
        $originRegion   = $bdRegion->load($storeRegion)->getCode();
        $cityOrigin     = $this->getCodeGeoBx($storeCity,$originRegion,$baseUrl,$agencyId);

        if($countryID != 'CL' || empty($cityOrigin)){
            return false;
        }

        /**
         * I get the product data
         */
        $itemProduct = [];

        foreach ($request->getAllItems() as $_item) {
            if ($_item->getProductType() == 'configurable')
                continue;

                $_product = $_item->getProduct();

            if ($_item->getParentItem())
                $_item = $_item->getParentItem();

                $blueAlto = (int) $_product->getResource()
                    ->getAttributeRawValue($_product->getId(), 'height', $_product->getStoreId());

                if($blueAlto == '' || $blueAlto == 0){
                    $blueAlto = 10;
                }

                $blueLargo = (int) $_product->getResource()
                    ->getAttributeRawValue($_product->getId(), 'large', $_product->getStoreId());

                if($blueLargo == '' || $blueLargo == 0){
                        $blueLargo = 10;
                }

                $blueAncho = (int) $_product->getResource()
                    ->getAttributeRawValue($_product->getId(), 'width', $_product->getStoreId());

                if($blueAncho == '' || $blueAncho == 0){
                        $blueAncho = 10;
                }


                $itemProduct[] = [
                    'largo'         => $blueAlto,
                    'ancho'         => $blueAncho,
                    'alto'          => $blueLargo,
                    'pesoFisico'    => (int)$_product->getWeight(),
                    'cantidad'      => $_item->getQty()
                ];
        }

        /**
         * I look for the ID corresponding to the commune selected at checkout
         */
        $addressCity	= $request->getDestCity();
        $destRegionId   = $bdRegion->load($request->getDestRegionId())->getCode();
        if($addressCity !=''){
            $citydest       = $this->getCodeGeoBx($addressCity,$destRegionId,$baseUrl,$agencyId);
        }

        if($citydest !=''){
            /**
            * I GENERATE THE ARRAY TO PASS IT TO THE API THAT WILL LOOK FOR THE PRICE
            */
            $seteoDatos = [
                "from" => [ "country" => "{$countryID}", "district" => "{$cityOrigin['districtCode']}" ],
                "to" => [ "country" => "{$countryID}", "state" => "{$citydest['regionCode']}", "district" => "{$citydest['districtCode']}" ],
                "serviceType" => "EX",
                "domain" => "{$baseUrl}",
                "datosProducto" => [
                    "producto" => "P",
                    "familiaProducto" => "PAQU",
                    "bultos" =>$itemProduct
                ]
            ];

            $costoEnvio = $blueservice->getBXCosto($seteoDatos);

            /*
            * We format the data of the JSON String
            */
            $json = json_decode($costoEnvio,true);
            $costo = 0;
            foreach ($json as $key => $datos){
                if($key == 'data'){
                    if(is_array($datos) && array_key_exists('total',$datos) ){
                        if($datos['total'] != '' && $datos['total'] != 0){
                            $method->setPrice((int)$datos['total']);
                            $method->setCost((int)$datos['total']);
                        }else{
                            $costo = -1;
                        }
                    }else{
                        $costo = -1;
                    }
                }
            }

            $result->append($method);

            if($costo != -1){
                return $result;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * Get Country code by website scope
     *
     * @return string
     */
    public function getCountryByWebsite(): string
    {
        return $this->scopeConfig->getValue(
            self::COUNTRY_CODE_PATH,
            ScopeInterface::SCOPE_WEBSITES
        );
    }

     /**
     * Returns value of given variable
     *
     * @param string|int $origValue
     * @param string $pathToValue
     * @return string|int|null
     */
    protected function _getDefaultValue($origValue, $pathToValue)
    {
        if (!$origValue) {
            $origValue = $this->_scopeConfig->getValue(
                $pathToValue,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->getStore()
            );
        }

        return $origValue;
    }

     /**
     * Funcion obtener la comuna
     * @param string $city
     * @param string $regionCode
     * @param string $shop
     * @param string $agencyId
     * @return array|null
     */
    public function getCodeGeoBx($city,$regionCode,$shop, $agencyId = null)
    {
        $region = substr($regionCode,3,2);

        $Comuna = [
            "address" => $city,
            "type" => "shopify",
            "shop" => $shop,
            "regionCode" => $region,
            "agencyId" => $agencyId
        ];
        
        $cityOrigin= $this->_blueservice->getGeolocation($Comuna);

        return $cityOrigin;
    }
}
