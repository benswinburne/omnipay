# Tala Payments

**Easy to use, consistent payment processing for PHP 5.3+**

[![Build Status](https://secure.travis-ci.org/adrianmacneil/tala-payments.png)](http://travis-ci.org/adrianmacneil/tala-payments)

Tala Payments is a payment processing library for PHP 5.3+. It has been designed based on
experience using [Active Merchant](http://activemerchant.org/), plus experience implementing
dozens of gateways for [CI Merchant](http://ci-merchant.org/). It has a clear and consistent API,
and comes with a full suite of unit tests, plus an example application for you to pull apart.

This library is under active developemnt, and all feedback is welcome - please raise a github issue
to discuss ideas, or fork the project and send a pull request.

**Why use Tala instead of a gateway's official PHP package?**

* Because you can learn one API and use it across multiple sites using different gateways
* Because if you need to change payment gateways you won't need to rewrite your code
* Because most official PHP payment gateway libraries are a mess
* Because most payment gateways have exceptionally poor documentation
* Because you are writing a shopping cart and need to support multiple gateways

## TL;DR

Just want to see some code?

```php
use Tala\CreditCard;
use Tala\GatewayFactory;

$gateway = GatewayFactory::create('Stripe');
$gateway->setApiKey('abc123');

$card = new CreditCard(['number' => '4111111111111111', 'expiryMonth' => 6, 'expiryYear' => 2016]);
$response = $gateway->purchase(['amount' => 1000, 'card' => $card]);

if ($response->isSuccessful()) {
    // payment was successful: update database
    print_r($response);
} elseif ($response->isRedirect()) {
    // redirect to offsite payment gateway
    $response->redirect();
} else {
    // payment failed, display message to customer
    exit($response->getMessage());
}
```

As you can see, Tala Payments has a consistent, well thought out API. We try to abstract as much
as possible the differences between the various payments gateways.

## Package Layout

Tala Payments is a single package which provides abstract base classes and implementations for all
officially supported gateways. There are no dependencies on official payment gateway PHP packages -
we prefer to work with the HTTP API directly. Under the hood, we use [Buzz](https://github.com/kriswallsmith/Buzz)
to make HTTP requests, though you are free to swap out this dependency.

Unsupported gateways can either be added by forking this package and submitting a pull request
(unit tests and tidy code required), or by distributing a separate library which depends on this
package and makes use of our base classes and consistent developer API.

## Installation

Tala Payments is installed via [Composer](http://getcomposer.org/). To install, simply add it
to your `composer.json` file:

```json
{
    "require": {
        "adrianmacneil/tala-payments": "dev-master"
    }
}
```

And run composer to update your dependencies:

    $ curl -s http://getcomposer.org/installer | php
    $ php composer.phar update

We will make a beta release shortly - for now you can use the above code to install the latest master.

## Payment Gateways

All payment gateways must implement [Tala\GatewayInterface](https://github.com/adrianmacneil/tala-payments/blob/master/src/Tala/GatewayInterface.php), and will usually
extend [Tala\AbstractGateway](https://github.com/adrianmacneil/tala-payments/blob/master/src/Tala/AbstractGateway.php) for basic functionality.

The following gateways are already implemented:

* 2Checkout
* Authorize.Net AIM
* Authorize.Net SIM
* CardSave
* GoCardless
* Netaxept (BBS)
* Payflow Pro
* PaymentExpress (DPS) PxPay
* PaymentExpress (DPS) PxPost
* PayPal Express Checkout
* PayPal Payments Pro
* Pin Payments
* Stripe
* WorldPay

More are coming soon! [All of these](https://github.com/expressodev/ci-merchant/tree/develop/libraries/merchant)
will be implemented before we reach 1.0.

Gateways are created and initialized like so:

```php
use Tala\GatewayFactory;

$gateway = GatewayFactory::create('PayPal_Express');
$gateway->setUsername('adrian');
$gateway->setPassword('12345');
```

Most settings are gateway specific. If you need to query a gateway to get a list
of available settings, you can call `defineSettings()`:

```php
$settings = $gateway->defineSettings();
// default settings array format:
array(
    'username' => '', // string variable
    'testMode' => false, // boolean variable
    'landingPage' => array('billing', 'login'), // enum variable, first item should be treated as default
);
```

Generally most payment gateways can be classified as one of two types:

* Off-site gateways such as PayPal Express, where the customer is redirected to a third party site to enter payment details
* On-site (merchant-hosted) gateways such as PayPal Pro, where the customer enters their credit card details on your site

However, there are some gateways such as SagePay Direct, where you take credit card details on site, then optionally redirect
if the customer's card supports 3D Secure authentication. Therefore, there is no point differentiating between the two types of
gateway (other than by the methods they support).

## Credit Card / Payment Form Input

User form input is directed to a [Tala\CreditCard](https://github.com/adrianmacneil/tala-payments/blob/master/src/Tala/CreditCard.php)
object. This provides a safe way to accept user input.

The `CreditCard` object has the following fields:

* firstName
* lastName
* number
* expiryMonth
* expiryYear
* startMonth
* startYear
* cvv
* issue
* type
* billingAddress1
* billingAddress2
* billingCity
* billingPostcode
* billingState
* billingCountry
* shippingAddress1
* shippingAddress2
* shippingCity
* shippingPostcode
* shippingState
* shippingCountry
* company
* phone
* email

Even off-site gateways make use of the `CreditCard` object, because often you need to pass
customer billing or shipping details through to the gateway.

The `CreditCard` object can be intialized with untrusted user input via the constructor.
Any fields passed to the constructor which are not recognized will be ignored.

```php
$formInputData = array(
    'firstName' => 'Bobby',
    'lastName' => 'Tables',
    'number' => '4111111111111111',
);
$card = new CreditCard($formInputData);
```

You can also access the fields using getters and setters:

```php
$number = $card->getNumber();
$card->setFirstName('Adrian');
```

If you submit credit card details which are obviously invalid (missing required fields, or a number
which fails the Luhn check), [Tala\Exception\InvalidCreditCardException](https://github.com/adrianmacneil/tala-payments/blob/master/src/Tala/Exception/InvalidCreditCardException.php)
will be thrown.  You should validate the card details using your framework's validation library
before submitting the details to your gateway, to avoid unnecessary API calls.

For on-site payment gateways, the following card fields are always required:

* firstName
* lastName
* number
* expiryMonth
* expiryYear
* cvv

You can also verify the card number using the Luhn algorithm by calling `Tala\Helper::validateLuhn($number)`.

## Gateway Methods

The main methods implemented by gateways are:

* `authorize($options)` - authorize an amount on the customer's card
* `completeAuthorize($options)` - handle return from off-site gateways after authorization
* `capture($options)` - capture an amount you have previously authorized
* `purchase($options)` - authorize and immediately capture an amount on the customer's card
* `completePurchase($options)` - handle return from off-site gateways after purchase
* `refund($options)` - refund an already processed transaction
* `void($options)` - generally can only be called up to 24 hours after submitting a transaction

On-site gateways do not need to implement the `completeAuthorize` and `completePurchase` methods. If any gateway does not support
certain features (such as refunds), it will throw `Tala\Exception\UnsupportedMethodException`.

All gateway methods take an `$options` array as an argument. Each gateway differs in which
parameters are required, and the gateway will throw `Tala\Exception\InvalidRequestException` if you
omit any required parameters. All gateways will accept a subset of these options:

* card
* token
* amount
* currency
* description
* transactionId
* clientIp
* returnUrl
* cancelUrl

Pass the options through to the method like so:

```php
$card = new CreditCard($formData);
$response = $gateway->authorize([
    'amount' => 1000, // this represents $10.00
    'card' => $card,
    'returnUrl' => 'https://www.example.com/return',
]);
```

For most transactions, either the `card` or `token` parameter is required. For more information on
using tokens, see the Token Billing section below.

When calling the `completeAuthorize` or `completePurchase` methods, the exact same arguments should be provided as
when you made the initial `authorize` or `purchase` call (some gateways will need to verify for example the actual
amount paid equals the amount requested). The only parameter you can omit is `card`.

To summarize the various parameters you have available to you:

* Gateway settings (e.g. username and password) are set directly on the gateway. These settings apply to all payments, and generally you will store these in a configuration file or in the database.
* Method options are used for any payment-specific options, which are not set by the customer. For example, the payment `amount`, `currency`, `transactionId` and `returnUrl`.
* CreditCard parameters are data which the user supplies. For example, you want the user to specify their `firstName` and `billingCountry`, but you don't want a user to specify the payment `currency` or `returnUrl`.

## The Payment Response

The payment response must implement [\Tala\ResponseInterface](https://github.com/adrianmacneil/tala-payments/blob/master/src/Tala/ResponseInterface.php). There are two main types of response:

* Payment was successful (standard response)
* Website requires redirect to off-site payment form (redirect response)

### Successful Response

For a successful responses, a reference will normally be generated, which can be used to capture or refund the transaction
at a later date. The following methods are always available:

```php
$reference = $response->getGatewayReference();
$mesage = $response->getMessage();
```

In addition, most gateways will override the response object, and provide access to any extra fields returned by the gateway.

### Redirect Response

The redirect response is further broken down by whether the customer's browser must redirect using GET (RedirectResponse object), or
POST (FormRedirectResponse). These could potentially be combined into a single response class, with a `getRedirectMethod()`.

After processing a payment, the cart should check whether the response requires a redirect, and if so, redirect accordingly:

```php
$response = $gateway->purchase(1000, $card);
if ($response->isSuccessful()) {
    // payment is complete
} elseif ($response->isRedirect()) {
    $response->redirect(); // this will automatically forward the customer
} else {
    // not successful
}
```

The customer isn't automatically forwarded on, because often the cart or developer will want to customize the redirect method
(or if payment processing is happening inside an AJAX call they will want to return JS to the browser instead).

To display your own redirect page, simply call `getRedirectUrl()` on the response, then display it accordingly:

```php
$url = $response->getRedirectUrl();
// for a form redirect, you can also call the following method:
$data = $response->getFormData(); // associative array of fields which must be posted to the redirectUrl
```

## Error Handling

You can test for a successful response by calling `isSuccessful()` on the response object. If there
was an error communicating with the gateway, or your request was obviously invalid, an exception
will be thrown. In general, if the gateway does not throw an exception, but returns an unsuccessful
response, it is a message you should display to the customer. If an exception is thrown, it is
either a bug in your code (missing required fields), or a communication error with the gateway.

You can handle both scenarios by wrapping the entire request in a try-catch block:

```php
try {
    $response = $gateway->purchase(1000, $card);
    if ($response->isSuccessful()) {
        // mark order as complete
    } elseif ($response->isRedirect()) {
        $response->redirect();
    } else {
        // display error to customer
        exit($response->getMessage());
    }
} catch (\Exception $e) {
    // internal error, log exception and display a generic message to the customer
    exit('Sorry, there was an error processing your payment. Please try again later.');
}
```

## Token Billing

Token billing is still under development. Most likely gateways will be able to implement the
following methods:

* `store($options)` - returns a response object which includes a `token`, which can be used for future transactions
* `unstore($options)` - remove a stored card, not all gateways support this method

## Recurring Billing

At this stage, automatic recurring payments functionality is out of scope for this library.
This is because there is likely far too many differences between how each gateway handles
recurring billing profiles. Also in most cases token billing will cover your needs, as you can
store a credit card then charge it on whatever schedule you like. Feel free to get in touch if
you really think this should be a core feature and worth the effort.

## Example Application

An example application is provided in the `example` directory. You can run it using PHP's built in
web server (PHP 5.4+):

    $ php composer.phar update --dev
    $ php -S localhost:8000 -t example/

For more information, see the [example application directory](https://github.com/adrianmacneil/tala-payments/tree/master/example).

## Feedback

**Please provide feedback!** We want to make this library useful in as many projects as possible.
Please raise a Github issue, and point out what you do and don't like, or fork the project and make
suggestions. **No issue is too small.**
