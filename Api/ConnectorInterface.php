<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Api;

use Zend_Http_Client;
use Zend_Http_Response;
use Zend_Http_Client_Exception;

/**
 * Interface ConnectorInterface
 */
interface ConnectorInterface
{
    /**
     * Send request
     *
     * @param string $endpoint
     * @param string $method
     * @param array $headers
     * @param array $data
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function call(
        string $endpoint,
        string $method = Zend_Http_Client::GET,
        array $headers = [],
        array $data = []
    ): Zend_Http_Response;
}
