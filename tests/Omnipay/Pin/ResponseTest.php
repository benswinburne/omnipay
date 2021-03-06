<?php

/*
 * This file is part of the Omnipay package.
 *
 * (c) Adrian Macneil <adrian@adrianmacneil.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omnipay\Pin;

use Omnipay\TestCase;

class ResponseTest extends TestCase
{
    public function testConstructError()
    {
        $httpResponse = $this->getMockResponse('PurchaseFailure.txt');
        $response = new Response($httpResponse->json());

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getGatewayReference());
        $this->assertSame('The current resource was deemed invalid.', $response->getMessage());
    }

    public function testConstructSuccess()
    {
        $httpResponse = $this->getMockResponse('PurchaseSuccess.txt');
        $response = new Response($httpResponse->json());

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('ch_fXIxWf0gj1yFHJcV1W-d-w', $response->getGatewayReference());
        $this->assertSame('Success!', $response->getMessage());
    }
}
