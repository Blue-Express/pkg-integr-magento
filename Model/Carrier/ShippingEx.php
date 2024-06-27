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

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setMethod($this->_code);

        /**
         * We get the ID of the country selected in the store
         */
        $countryID	= $this->getCountryByWebsite();
        $pudo		= false;
        $arrayAgency	= explode(' - ',$request->getDestStreet());
        if(!empty($arrayAgency) && array_key_exists(1, $arrayAgency)){
            $agencyId   = $arrayAgency[1];
            $this->_logger->info(print_r($arrayAgency, true));
        }else {
            $agencyId       = null;
        }

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
            $this->_logger->error('Error: Invalid country or empty city origin.');
            $result->setError(__('There was an error calculating the shipping rate. Please contact support.'));
            return $result;
        }


        /**
         * I get the product data
         */
        $itemProduct = $this->getProductBx($request->getAllItems());

        /**
         * I look for the ID corresponding to the commune selected at checkout
         */
        $addressCity	= $request->getDestCity();
        $destRegionId   = $bdRegion->load($request->getDestRegionId())->getCode();
        if($addressCity !=''){
		    $citydest	= $this->getCodeGeoBx($addressCity,$destRegionId,$baseUrl,$agencyId);
		    $pudo		= $citydest['pickup'];
        }

        if($citydest !=''){
            /**
            * I GENERATE THE ARRAY TO PASS IT TO THE API THAT WILL LOOK FOR THE PRICE
            */

            if($pudo === true){
                $familiaProducto = 'PUDO';
                $rate = $this->getPriceBx($method,$countryID,$cityOrigin,$citydest,$baseUrl,$itemProduct,$familiaProducto);

            }else{
                $familiaProducto = 'PAQU';
                $rate = $this->getPriceBx($method,$countryID,$cityOrigin,$citydest,$baseUrl,$itemProduct,$familiaProducto);
            }

            $result->append($rate['method']);

            if($rate['costo'] != -1){
                return $result;
            }else{
                $this->_logger->error('Error: There was an error calculating the shipping rate.');
                $result->setError(__('There was an error calculating the shipping rate. Please contact support.'));
                return $result;
            }
        }else{
            $this->_logger->error('Error: Invalid country or empty destination city.');
            $result->setError(__('There was an error calculating the shipping rate. Please contact support.'));
            return $result;
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

    public function getProductBx($productBx)
    {
        $itemProduct = [];

        foreach ($productBx as $_item) {
            if ($_item->getProductType() == 'configurable')
                continue;

                $_product = $_item->getProduct();

            if ($_item->getParentItem())
                $_item = $_item->getParentItem();

                $blueAlto   = $this->getValidDimension($_product, 'height');
                $blueLargo  = $this->getValidDimension($_product, 'large');
                $blueAncho  = $this->getValidDimension($_product, 'width');

                $itemProduct[] = [
                    'largo'         => $blueAlto,
                    'ancho'         => $blueAncho,
                    'alto'          => $blueLargo,
                    'pesoFisico'    => (int)$_product->getWeight(),
                    'cantidad'      => $_item->getQty()
                ];
        }

        return $itemProduct;
    }

     /**
     * Obtiene y valida una dimensión del producto.
     *
     * @param object $_product El producto del cual obtener la dimensión.
     * @param string $attribute El atributo de la dimensión ('height', 'large', 'width').
     * @return int La dimensión validada.
     */
    private function getValidDimension($_product, $attribute)
    {
        $dimension = $_product->getResource()
            ->getAttributeRawValue($_product->getId(), $attribute, $_product->getStoreId());

        $dimension = floatval(str_replace(',', '.', $dimension));
        
        if (empty($dimension) || $dimension == 0) {
            $dimension = 10;
        }

        return $dimension;
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

    public function getPriceBx($method,$countryID,$cityOrigin,$citydest,$baseUrl,$itemProduct, $familiaProducto)
    {

        $seteoDatos = [
            "from" => [ "country" => "{$countryID}", "district" => "{$cityOrigin['districtCode']}" ],
            "to" => [ "country" => "{$countryID}", "state" => "{$citydest['regionCode']}", "district" => "{$citydest['districtCode']}" ],
            "serviceType" => "EX",
            "domain" => "{$baseUrl}",
            "datosProducto" => [
                "producto" => "P",
                "familiaProducto" => "{$familiaProducto}",
                "bultos" =>$itemProduct
            ]
        ];

	    $blueservice = $this->_blueservice;
        $costoEnvio = $blueservice->getBXCosto($seteoDatos);
        $json = json_decode($costoEnvio,true);
        $costo = 0;
        foreach ($json as $key => $datos){
            if($key == 'data'){
                if(is_array($datos) && !empty($datos)){
                    if($datos['total'] != '' && $datos['total'] != 0){
                        $method->setPrice((int)$datos['total']);
                        $method->setCost((int)$datos['total']);
                        if($familiaProducto === 'PUDO'){
                            $method->setMethodTitle($citydest['pickupInfo']['agency_name']);
                            $method->setCarrierTitle($citydest['pickupInfo']['agency_name']);
                        }else{
                            $method->setMethodTitle($datos['nameService']);
                            $method->setCarrierTitle($datos['promiseDay']);
                        }
                    }else{
                        $costo = -1;
                    }
                }else{
                    $costo = -1;
                }
            }
        }

        $response = [
            'method' =>$method,
            'costo' => $costo
        ];

        return $response;
    }
}
