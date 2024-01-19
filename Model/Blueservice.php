<?php declare(strict_types=1);

namespace BlueExpress\Shipping\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use BlueExpress\Shipping\Helper\Data as HelperBX;
use GuzzleHttp\Client as Client;
use Psr\Log\LoggerInterface as LoggerInterface;

class Blueservice
{
    /**
     *
     * @var string
     */
    protected $_apiUrlGeo;

    /**
     *
     * @var string
     */
    protected $_apiUrlPrice;

    /**
     *
     * @var string
     */
    protected $_bxapiKey;

    /**
     *
     *
     * @var string
     */
    protected $_urlWebhook;

    /**
     *
     * @var HelperBX
     */
    protected $helperBX;

    /**
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     *
     * @var Client
     */
    protected $client;

       /**
     *
     * @var string
     */
    private $integratorEndpoint;

       /**
     *
     * @var string
     */
    private $webhookEndpoint;


    /**
     * Webservice constructor.
     * @param CheckoutSession $checkoutSession
     * @param HelperBX $helperBX
     * @param LoggerInterface $logger
     */
    public function __construct(
        HelperBX $helperBX,
        LoggerInterface $logger
    ) {
        $this->client               = new Client();
        $this->logger               = $logger;
        $this->_urlBx               = $helperBX->getUrlBx();
        $this->geoEndpoint          = "/api/ecommerce/comunas/v1/bxgeo/v2";
        $this->pricingEndpoint      = "/api/ecommerce/pricing/v1";
        $this->integratorEndpoint   = "/api/integrations/magento/v1";
        $this->webhookEndpoint      = "/api/integr/magento-wh/v1";
    }

    /**
     * Funcion para el envio de la orden
     * @param mixed $datosParams
     * @return array
     */
    public function getBXOrder($datosParams)
    {
        $headers = [
            "Content-Type" => "application/json",
            "apikey" => "{$this->_bxapiKey}"
        ];
        $response = $this->client->post("{$this->_urlBx}{$this->integratorEndpoint}", [
            'headers' => $headers,
            'body' => json_encode($datosParams)
        ]);
        $result = $response->getBody()->getContents();

        return $result;
    }

    public function getBXNewOrder($datosParams)
    {
        $headers = [
            "Content-Type" => "application/json",
            "apikey" => "{$this->_bxapiKey}"
        ];
        $response = $this->client->post("{$this->_urlBx}{$this->webhookEndpoint}", [
            'headers' => $headers,
            'body' => json_encode($datosParams)
        ]);
        $result = $response->getBody()->getContents();

        return $result;
    }

    /**
     * Funcion para buscar el costo del despacho
     * @param array $shippingParams
     * @return array
     */
    public function getBXCosto($shippingParams)
    {
      $shippingParams['domain'] = 'https://magento.dev.blue.cl/'; //eliminar
        $this->logger->info('Information sent to api price', $shippingParams);
        $headers = [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "apikey" => "{$this->_bxapiKey}"
        ];
        $response = $this->client->post("{$this->_urlBx}{$this->pricingEndpoint}", [
            'headers' => $headers,
            'body' => json_encode($shippingParams)
        ]);
        $result = $response->getBody()->getContents();

        return $result;
    }

    /**
     * Funcion para setear la comuna
     * @param string $shippingCity
     * @return array
     */
    public function getGeolocation($shippingCity)
    {
        $this->logger->info('Information sent to api geolocation', $shippingCity);

        $headers = [
            "Content-Type" => "application/json",
            "Accept" => "application/json"
        ];
        $response = $this->client->post("{$this->_urlBx}{$this->geoEndpoint}", [
            'headers' => $headers,
            'body' => json_encode($shippingCity)
        ]);
        $result = $response->getBody()->getContents();
        $dadosGeo = json_decode($result, true);

        return $dadosGeo;
    }

    public function eliminarAcentos($cadena)
    {

        //Reemplazamos la A y a
        $cadena = str_replace(
            array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'ª','É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê','Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î','Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô','Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û','Ñ', 'ñ', 'Ç', 'ç'),
            array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'a','E', 'E', 'E', 'E', 'e', 'e', 'e', 'e','I', 'I', 'I', 'I', 'i', 'i', 'i', 'i','O', 'O', 'O', 'O', 'o', 'o', 'o', 'o','U', 'U', 'U', 'U', 'u', 'u', 'u', 'u','N', 'n', 'C', 'c'),
            $cadena
        );
        return $cadena;
    }
}
