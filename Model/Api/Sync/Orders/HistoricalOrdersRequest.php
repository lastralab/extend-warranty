<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2022 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Model\Api\Sync\Orders;

use Extend\Warranty\Api\ConnectorInterface;
use Extend\Warranty\Model\Api\Sync\AbstractRequest;
use Extend\Warranty\Model\Api\Request\OrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\ZendEscaper;
use Psr\Log\LoggerInterface;
use Zend_Http_Client;
use Zend_Http_Response;

class HistoricalOrdersRequest extends AbstractRequest
{
    /**
     * Create a warranty contract
     */
    const CREATE_ORDER_ENDPOINT = 'orders/batch';

    /**
     * Response status codes
     */
    const STATUS_CODE_SUCCESS = 201;

    /**
     * @var OrderBuilder
     */
    protected $orderBuilder;

    /**
     * @param ConnectorInterface $connector
     * @param JsonSerializer $jsonSerializer
     * @param ZendEscaper $encoder
     * @param LoggerInterface $logger
     * @param OrderBuilder $orderBuilder
     */
    public function __construct(
        ConnectorInterface $connector,
        JsonSerializer $jsonSerializer,
        ZendEscaper $encoder,
        LoggerInterface $logger,
        OrderBuilder $orderBuilder

    )
    {
        parent::__construct($connector, $jsonSerializer, $encoder, $logger);
        $this->orderBuilder = $orderBuilder;
    }

    /**
     * Send historical orders to Orders API
     *
     * @param array $orders
     * @param int $currentBatch
     * @return bool
     */
    public function create(array $orders, int $currentBatch = 1): bool
    {
        $url = $this->apiUrl . self::CREATE_ORDER_ENDPOINT;
        $historicalOrders = [];

        foreach ($orders as $order) {
            $historicalOrder = $this->orderBuilder->prepareHistoricalOrdersPayLoad($order);

            if (!empty($historicalOrder)) {
                $historicalOrders[] = $historicalOrder;
            }
        }

        if (!empty($historicalOrders)) {
            try {
                $response = $this->connector->call(
                    $url,
                    Zend_Http_Client::POST,
                    [
                        'Accept' => 'application/json; version=2021-07-01',
                        'Content-Type' => 'application/json',
                        self::ACCESS_TOKEN_HEADER => $this->apiKey,
                        'X-Idempotency-Key' => $this->getUuid4()
                    ],
                    $historicalOrders
                );
                $responseBody = $this->processResponse($response);

                if ($response->getStatus() === self::STATUS_CODE_SUCCESS) {
                    $this->logger->info(sprintf('Orders batch %s is synchronized successfully.', $currentBatch));
                    return true;
                } else {
                    $this->logger->error(sprintf('Order batch %s synchronization is failed.', $currentBatch));
                }
            } catch (LocalizedException $exception) {
                $this->logger->error(sprintf('Order batch %s synchronization is failed. Error message: %s', $currentBatch, $exception->getMessage()));
            } catch (\Zend_Http_Client_Exception $zend_Http_Client_Exception) {
                $this->logger->error(sprintf('Order batch %s synchronization is failed. Error message: %s', $currentBatch, $zend_Http_Client_Exception->getMessage()));
            }
        }

        return false;
    }

    /**
     * Process response
     *
     * @param Zend_Http_Response $response
     * @return array
     */
    protected function processResponse(Zend_Http_Response $response): array
    {
        $responseBody = [];
        $responseBodyJson = $response->getBody();

        if ($responseBodyJson) {
            $responseBody = $this->jsonSerializer->unserialize($responseBodyJson);

            if (isset($responseBody['customer'])) {
                $depersonalizedBody = $responseBody;
                $depersonalizedBody['customer'] = [];
                $rawBody = $this->jsonSerializer->serialize($depersonalizedBody);
            } else {
                $rawBody = $response->getRawBody();
            }

            $this->logger->info('Response: ' . $response->getHeadersAsString() . PHP_EOL . $rawBody);
        } else {
            $this->logger->error('Response body is empty.');
        }

        return $responseBody;
    }
}
