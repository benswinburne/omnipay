<?php

/*
 * This file is part of the Omnipay package.
 *
 * (c) Ben Swinburne <ben.swinburne@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omnipay\SecureTrading;

use SimpleXMLElement;
use DOMDocument;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Exception;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\RedirectResponse;
use Omnipay\Common\Request;

/**
 * Secure Trading Gateway
 *
 * @link http://www.securetrading.com/sites/default/files/downloads/webservices/STPP%20Web%20Services%20User%20Guide.pdf
 * @link http://www.securetrading.com/sites/default/files/downloads/xml/STPP%20XML%20Specification.pdf
 */
class Gateway extends AbstractGateway
{
	protected $endpoint     = 'https://webservices.securetrading.net:443/xml/';
	protected $testEndpoint = 'https://webservices.securetrading.net:443/xml/';
	protected $testMode;
	
	/**
	 * Web Services Compatible and enabled site reference provided by Secure Trading
	 */
	protected $siteReference;
	
	/**
	 * Web Services Compatible and enable username provided by Secure Trading
	 */
	protected $username;
	
	/**
	 * Web Services Compatible and enable password provided by Secure Trading
	 */
	protected $password;
	
	public function getName()
	{
		return 'Secure Trading';
	}

	public function defineSettings()
	{
		return array(
			'siteReference' => '',
			'username' => '',
			'password' => '',
			'userAgent' => 'OmniPay',
			'testMode' => false,
			'requestBlockVersion' => 3.67,
		);
	}

	public function getRequestBlockVersion()
	{
		return $this->requestBlockVersion;
	}

	public function setRequestBlockVersion($value)
	{
		$this->requestBlockVersion = $value;
	}

	public function getSiteReference()
	{
		return $this->siteReference;
	}

	public function setSiteReference($value)
	{
		$this->siteReference = $value;
	}
	
	public function getUsername()
	{
		return $this->username;
	}

	public function setUsername($value)
	{
		$this->username = $value;
	}
	
	public function getUserAgent()
	{
		return $this->userAgent;
	}

	public function setUserAgent($value)
	{
		$this->userAgent = $value;
	}
	
	public function getPassword()
	{
		return $this->password;
	}

	public function setPassword($value)
	{
		$this->password = $value;
	}

	public function getTestMode()
	{
		return $this->testMode;
	}

	public function setTestMode($value)
	{
		$this->testMode = $value;
	}
	
	protected function getCurrentEndpoint()
	{
		return $this->testMode ? $this->testEndpoint : $this->endpoint;
	}
	
	public function purchase($options)
	{
		$request = new Request($options);
		$data = $this->buildPurchaseRequest($request);
		
		return $this->send($data, $request);
	}
	
	protected function buildRequest($action, $options = array())
	{
		$data = new SimpleXMLElement("<{$action} />");
		
		foreach ($options as $key => $value)
		{
			$data->addAttribute($key, $value);
		}
		
		return $data;
	}
	
	protected function buildPurchaseRequest(Request $request)
	{
		$request->validate(array('amount'));
		$source = $request->getCard();
		$source->validate();
		
		if ( ! $source->getType())
		{
			$source->setType($source->determineType($source->getNumber()));
		}

		$data = $this->buildRequest('requestblock', array(
			'version'	=>	$this->getRequestBlockVersion()
		));
		
		$data->alias = $this->username;
		$data->addChild('request')
			 ->addAttribute('type', 'AUTH');
		
		// Merchant
		$data->request->merchant->orderreference = $request->getTransactionId();
		
		// Customer
		$data->request->customer->town = $source->getCity();
		$data->request->customer->name->last = $source->getLastName();
		$data->request->customer->name->first = $source->getFirstName();
		$data->request->customer->ip = $request->getClientIp();
		$data->request->customer
			->addChild('telephone', $source->getPhone())
			->addAttribute('type', 'M');
		$data->request->customer->premise = $source->getAddress1();
		$data->request->customer->street = $source->getAddress2();
		$data->request->customer->postcode = $source->getPostcode();
		
		// Billing
		$data->request->addChild('billing');
		$data->request->billing
			->addChild('telephone', $source->getPhone())
			->addAttribute('type', 'M');
		$data->request->billing->county = $source->getState();
		$data->request->billing->premise = $source->getAddress1();
		$data->request->billing->street = $source->getAddress2();
		$data->request->billing->postcode = $source->getPostcode();
		$data->request->billing
			->addChild('payment')
			->addAttribute('type', strtoupper($source->getType()));
		$data->request->billing->town = $source->getCity();
		$data->request->billing->name->last = $source->getLastName();
		$data->request->billing->name->first = $source->getFirstName();
		$data->request->billing->country = $source->getCountry();
		$data->request->billing
			->addChild('amount', $request->getAmount())
			->addAttribute('currencycode', $request->getCurrency());
		$data->request->billing->email = $source->getEmail();
		
		if ($source->getStartMonth() and $source->getStartYear())
		{
			$data->request->billing->payment->startdate =
				$source->getStartDate('m').'/'.$source->getStartDate('Y');
		}
		
		$data->request->billing->payment->expirydate =
			$source->getExpiryDate('m').'/'.$source->getExpiryDate('Y');
		$data->request->billing->payment->pan = $source->getNumber();
		$data->request->billing->payment->securitycode = $source->getCvv();
		
		// Operation
		$data->request->operation->sitereference = $this->siteReference;
		$data->request->operation->accounttypedescription = 'ECOM';
		
		// header("Content-type: text/xml");
		// echo $data->asXML();
		// exit;

		return $data;
	}
	
	protected function send($data, Request $request)
	{
		$credentials = "{$this->username}:{$this->password}";
		$payload = $data->asXML();
		$authHeader = base64_encode($credentials);
		
		$headers = array(
			'Content-Type' => 'text/xml; charset=utf-8',
			'Content-Length' => strlen($payload),
			'Accept' => 'text/xml',
			'Authorization' => 'Basic '.$authHeader,
			'User-Agent' => $this->userAgent,
			'Host' => 'webservices.securetrading.net',
			'Connection' => 'close'
		);
		
		$httpResponse = $this->httpClient->post($this->getCurrentEndpoint(), $headers, $payload)->send();
		
		$responseDom = new DOMDocument;
		$responseDom->loadXML($httpResponse->getBody());
		
		header("Content-type: text/xml");
		print_r($responseDom->saveXML());
		exit;
	}
}
