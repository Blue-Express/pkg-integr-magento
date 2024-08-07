<?php declare(strict_types=1);

namespace BlueExpress\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order;
use BlueExpress\Shipping\Model\Blueservice;
use BlueExpress\Shipping\Helper\Data as HelperBX;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;


class OrderSaveAfter implements ObserverInterface
{
    protected $_blueservice;
    protected $weightStore;
    protected $logger;
    protected $storeManager;
    protected $newOrdersBlue;

    public function __construct(
        StoreManagerInterface $storeManager,
        Blueservice $blueservice,
        HelperBX $helperBX,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->_blueservice = $blueservice;
        $this->weightStore = $helperBX->getWeightUnit();
        $this->logger = $logger;
        $this->newOrdersBlue = true;
    }
    
    public function execute(Observer $observer)
    {
      if ($this->newOrdersBlue) {
          $this->processOrder($observer, true);
      } else {
          $this->processOrder($observer, false);
      }
    }

    private function processOrder(Observer $observer, bool $isNewOrder){

        $order = $observer->getEvent()->getOrder();
        $orderID = $order->getId();
        $incrementId = $order->getIncrementId();
        $status = $order->getStatus();
        $state = $order->getState();
        $weightUnit = $this->weightStore;
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        if ($state === 'processing' && in_array($order->getShippingMethod(), [
          'bxexpress_bxexpress',
          'bxprioritario_bxprioritario',
          'bxPremium_bxPremium',
          'bxsameday_bxsameday'
      ])) {

        $shippingAddress = $this->getAddressDetails($order->getShippingAddress(),$baseUrl);
        $billingAddress = $this->getAddressDetails($order->getBillingAddress(),$baseUrl);

        $productDetails = [];
        $dimensions = [];
        $productResource = ObjectManager::getInstance()->get(ProductResource::class);

        foreach ($order->getAllVisibleItems() as $item) {
            $productDetails[] = [
                "item_id" =>$item->getItemId(),
                "qty" => $item->getQtyOrdered(),
		        "qty_invoiced" => $item->getQtyInvoiced(),
                "weight" => $item->getWeight(),
                "sku" => $item->getSku(),
                "name" => $item->getName(),
                "price" => $item->getPrice(),
                "discount_percent" => $item->getDiscountPercent(),
                "discount_amount" => $item->getDiscountAmount(),
                "row_weight" => $item->getRowWeight(),
                "free_shipping" => $item->getFreeShipping()
            ];

            $dimensions[] = $this->getProductDimensions($productResource, $item->getSku());
        }

        $typeCarrier = $this->getTypeCarrier($order->getShippingMethod());

        $orderPayload = [
            "orderId" => $orderID,
            "incrementId" => $incrementId,
            "weight" => $weightUnit,
            "detailOrder" => $order->getData(),
            "shipping" => $shippingAddress,
            "billing" => $billingAddress,
            "product" => $productDetails,
            "dimensions" => $dimensions,
            "agencyId" => $shippingAddress['agency_id'] ?? null,
            "origin" => ["account" => $baseUrl],
            "type_carrier" => $typeCarrier
        ];

        $this->logger->info('Information sent to the webhook', ['Detail' => $orderPayload]);
        
        if ($isNewOrder) {
            $this->_blueservice->getBXNewOrder($orderPayload);
        } else {
            $this->_blueservice->getBXOrder($orderPayload);
        }

      }

    }

    private function getAddressDetails($address, $baseUrl)
    {
        if (!$address || !$address->getEntityId()) {
            return [];
        }

        $regionCodeShipping = $address->getRegionCode();
        $regionShipping = substr($regionCodeShipping, 3, 2);

        $street = explode(' - ', $address->getStreet()[0]);

        $comuna = [
          "address" => $address->getCity(),
          "type" => "shopify",
          "shop" => $baseUrl,
          "regionCode" => $regionShipping,
          "agencyId" => $street[1] ?? null
        ];

        $geolocation = $this->_blueservice->getGeolocation($comuna);
        $district = array_key_exists("districtCode", $geolocation) ? $geolocation['districtCode'] : null;
        $region = array_key_exists("regionCode", $geolocation) ? $geolocation['regionCode'] : null;

        return [
            "region" => $address->getRegion(),
            "postcode" => $address->getPostcode(),
            "lastname" => $address->getLastname(),
            "street" => $address->getStreet(),
            "city" => $this->_blueservice->eliminarAcentos($address->getCity()),
            "district" => $district ,
            "state" =>  $region,
            "email" => $address->getEmail(),
            "telephone" => $address->getTelephone(),
            "country_id" => $address->getCountryId(),
            "firstname" => $address->getFirstname(),
            "address_type" => $address->getAddressType(),
            "company" => $address->getCompany(),
            "agency_id" => $street[1] ?? null
        ];
    }

    private function getProductDimensions(ProductResource $resource, string $sku)
    {
        $height = $resource->getAttributeRawValue($resource->getIdBySku($sku), 'height', 0) ?: 10;
        $width = $resource->getAttributeRawValue($resource->getIdBySku($sku), 'width', 0) ?: 10;
        $length = $resource->getAttributeRawValue($resource->getIdBySku($sku), 'large', 0) ?: 10;
        
        $height = is_string($height) ? str_replace(',', '.', $height) : $height;
        $width = is_string($width) ? str_replace(',', '.', $width) : $width;
        $length = is_string($length) ? str_replace(',', '.', $length) : $length;

        return [
            "height" => $height,
            "width" => $width,
            "large" => $length
        ];
    }

    private function getTypeCarrier(string $shippingMethod)
    {
        switch ($shippingMethod) {
            case 'bxexpress_bxexpress':
                return "EX";
            case 'bxprioritario_bxprioritario':
                return "PY";
            case 'bxsameday_bxsameday':
                return "MD";
            default:
                return "";
        }
    }
}
