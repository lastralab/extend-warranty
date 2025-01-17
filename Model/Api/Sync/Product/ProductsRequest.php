<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Model\Api\Sync\Product;

use Extend\Warranty\Api\ConnectorInterface;
use Extend\Warranty\Model\Api\Request\ProductDataBuilder as ProductPayloadBuilder;
use Extend\Warranty\Model\Api\Sync\AbstractRequest;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\ZendEscaper;
use Psr\Log\LoggerInterface;
use Zend_Http_Client;
use Zend_Http_Client_Exception;
use InvalidArgumentException;

/**
 * Class ProductsRequest
 *
 * Warranty ProductsRequest
 */
class ProductsRequest extends AbstractRequest
{
    /**
     * Create / update a product
     */
    public const CREATE_PRODUCT_ENDPOINT = 'products/';

    /**
     * Get a product
     */
    public const GET_PRODUCT_ENDPOINT = 'products/';

    /**
     * Response status codes
     */
    public const STATUS_CODE_SUCCESS = 201;

    /**
     * Product Payload Builder Model
     *
     * @var ProductPayloadBuilder
     */
    private $productPayloadBuilder;

    /**
     * Logger Interface
     *
     * @var LoggerInterface
     */
    private $syncLogger;

    /**
     * Url Encoder
     *
     * @var ZendEscaper
     */
    private $encoder;

    /**
     * ProductsRequest constructor
     *
     * @param ConnectorInterface $connector
     * @param JsonSerializer $jsonSerializer
     * @param LoggerInterface $logger
     * @param ProductPayloadBuilder $productPayloadBuilder
     * @param LoggerInterface $syncLogger
     * @param ZendEscaper $encoder
     */
    public function __construct(
        ConnectorInterface $connector,
        JsonSerializer $jsonSerializer,
        LoggerInterface $logger,
        ProductPayloadBuilder $productPayloadBuilder,
        LoggerInterface $syncLogger,
        ZendEscaper $encoder
    ) {
        $this->productPayloadBuilder = $productPayloadBuilder;
        $this->syncLogger = $syncLogger;
        parent::__construct($connector, $jsonSerializer, $encoder, $logger);
    }

    /**
     * Create / update batch of products
     *
     * @param array $products
     * @param int $currentBatch
     */
    public function create(array $products, int $currentBatch = 1)
    {
        $productData = [];
        foreach ($products as $product) {
            $productPayload = $this->productPayloadBuilder->preparePayload($product);
            if (!empty($productPayload)) {
                $productData[] = $productPayload;
            }
        }

        if (!empty($productData)) {
            try {
                $response = $this->connector->call(
                    $this->buildUrl(self::CREATE_PRODUCT_ENDPOINT . '?batch=true'),
                    Zend_Http_Client::POST,
                    [self::ACCESS_TOKEN_HEADER => $this->apiKey],
                    $productData
                );
                $responseBody = $this->processResponse($response);

                if ($response->getStatus() === self::STATUS_CODE_SUCCESS) {
                    $this->logger->info(sprintf('Product batch %s is synchronized successfully.', $currentBatch));
                    $this->syncLogger->info('Synced ' . count($productData) . ' product(s) in batch ' . $currentBatch);

                    foreach ($responseBody as $name => $section) {
                        $info = array_column($section, 'referenceId');
                        $this->syncLogger->info($name, $info);
                    }
                } else {
                    $this->logger->error(sprintf('Product batch %s synchronization is failed.', $currentBatch));
                }
            } catch (Zend_Http_Client_Exception $exception) {
                $this->logger->error($exception->getMessage());
            } catch (InvalidArgumentException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * Check if connection successful
     *
     * @return bool
     */
    public function isConnectionSuccessful(): bool
    {
        try {
            $response = $this->connector->call(
                $this->buildUrl(self::GET_PRODUCT_ENDPOINT),
                Zend_Http_Client::GET,
                [self::ACCESS_TOKEN_HEADER => $this->apiKey]
            );

            $isConnectionSuccessful = $response->isSuccessful();
        } catch (Zend_Http_Client_Exception $exception) {
            $isConnectionSuccessful = false;
        }

        return $isConnectionSuccessful;
    }
}
