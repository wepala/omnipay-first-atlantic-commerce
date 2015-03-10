<?php

namespace Omnipay\FirstAtlanticCommerce\Message;

use Omnipay\Common\Message\AbstractRequest as BaseAbstractRequest;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\FirstAtlanticCommerce\ParameterTrait;
use SimpleXMLElement;

abstract class AbstractRequest extends BaseAbstractRequest
{
    use ParameterTrait;

    /**
     * FACPG2 live endpoint URL
     *
     * @var string
     */
    protected $liveEndpoint = 'https://marlin.firstatlanticcommerce.com/PGServiceXML/';

    /**
     * FACPG2 test endpoint URL
     *
     * @var string
     */
    protected $testEndpoint = 'https://ecm.firstatlanticcommerce.com/PGServiceXML/';

    /**
     * FACPG2 XML namespace
     *
     * @var string
     */
    protected $namespace = 'http://schemas.firstatlanticcommerce.com/gateway/data';

    /**
     * FACPG2 XML root
     *
     * @var string
     */
    protected $requestName;

    /**
     * Returns the amount formatted to match FAC's expectations.
     *
     * @return string The amount padded with zeros on the left to 12 digits and no decimal place.
     */
    protected function formatAmount()
    {
        $amount = $this->getAmount();

        $amount = str_replace('.', '', $amount);
        $amount = str_pad($amount, 12, '0', STR_PAD_LEFT);

        return $amount;
    }

    /**
     * Returns the signature for the request.
     *
     * @return string base64 encoded sha1 hash of the merchantPassword, merchantId,
     *    acquirerId, transactionId, amount and currency code.
     */
    protected function generateSignature()
    {
        $signature  = $this->getMerchantPassword();
        $signature .= $this->getMerchantId();
        $signature .= $this->getAcquirerId();
        $signature .= $this->getTransactionId();
        $signature .= $this->formatAmount();
        $signature .= $this->getCurrencyNumeric();

        return base64_encode( sha1($signature, true) );
    }

    /**
     * Returns the live or test endpoint depending on TestMode.
     *
     * @return string Endpoint URL
     */
    protected function getEndpoint()
    {
        return $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint;
    }

    /**
     * Return the response object
     *
     * @param \SimpleXMLElement $xml Response xml object
     *
     * @return ResponseInterface
     */
    abstract protected function newResponse($xml);

    /**
     * Send the request payload
     *
     * @param array $data Request payload
     *
     * @return ResponseInterface
     */
    public function sendData($data)
    {
        $httpResponse = $this->httpClient->post(
            $this->getEndpoint(),
            ['Content-Type' => 'text/xml; charset=utf-8'],
            $this->xmlSerialize($data)
        )->send();

        return $this->response = $this->newResponse( $httpResponse->xml() );
    }

    /**
     * Serializes an array into XML
     *
     * @param array $data Array to serialize into XML.
     * @param \SimpleXMLElement $xml SimpleXMLElement object.
     *
     * @return string XML
     */
    protected function xmlSerialize($data, $xml = null)
    {
        if ( !$xml instanceof SimpleXMLElement ) {
            $xml = new SimpleXMLElement('<'. $this->requestName .' xmlns="'. $this->namespace .'" />');
        }

        foreach ($data as $key => $value)
        {
            if ( !isset($value) )
            {
                continue;
            }

            if ( is_array($value) )
            {
                $node = $xml->addChild($key);
                $this->xmlSerialize($value, $node);
            }
            else
            {
                $xml->addChild($key, $value);
            }
        }

        return $xml->asXML();
    }
}