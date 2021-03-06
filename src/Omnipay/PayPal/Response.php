<?php

/*
 * This file is part of the Omnipay package.
 *
 * (c) Adrian Macneil <adrian@adrianmacneil.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omnipay\PayPal;

use Omnipay\Common\AbstractResponse;

/**
 * PayPal Express Class
 */
class Response extends AbstractResponse
{
    public function __construct($data)
    {
        parse_str($data, $this->data);
    }

    public function isSuccessful()
    {
        return isset($this->data['ACK']) && in_array($this->data['ACK'], array('Success', 'SuccessWithWarning'));
    }

    public function getGatewayReference()
    {
        foreach (array('REFUNDTRANSACTIONID', 'TRANSACTIONID', 'PAYMENTINFO_0_TRANSACTIONID') as $key) {
            if (isset($this->data[$key])) {
                return $this->data[$key];
            }
        }
    }

    public function getMessage()
    {
        return isset($this->data['L_LONGMESSAGE0']) ? $this->data['L_LONGMESSAGE0'] : null;
    }

    public function getExpressRedirectToken()
    {
        return isset($this->data['TOKEN']) ? $this->data['TOKEN'] : null;
    }
}
