# SafeCurl

SafeCurl intends to be a drop-in replacement for the
[curl_exec](http://php.net/manual/en/function.curl-exec.php) function in PHP.
SafeCurl validates each part of the URL against a white or black list, to help
protect against Server-Side Request Forgery attacks.

For more infomation about the project see the blog post
['SafeCurl: SSRF Protection, and a "Capture the Bitcoins"'](https://whitton.io/articles/safecurl-ssrf-protection-and-a-capture-the-bitcoins/).

## Protections

Each part of the URL is broken down and validated against a white or black
list. This includes resolving a domain name to its IP addresses.

If you chose to enable "FOLLOWLOCATION", then any redirects are caught, and
re-validated.

## Installation

SafeCurl can be included in any PHP project using
[Composer](https://getcomposer.org). Include the following in your
`composer.json` file under `require`.

```
    "require": {
        "fin1te\safecurl": "~1"
    }
```

Then update Composer.

```
composer update
```

## Usage

It's as easy as replacing `curl_exec` with `SafeCurl::execute`, and wrapping it
in a `try {} catch {}` block.

```php
use fin1te\SafeCurl\SafeCurl;
use fin1te\SafeCurl\Exception;

try {
    $url = 'http://www.google.com';

    $curlHandle = curl_init();
    //Your usual cURL options
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (SafeCurl)');

    //Execute using SafeCurl
    $response = SafeCurl::execute($url, $curlHandle);
} catch (Exception $e) {
    //URL wasn't safe
}
```

#### Options

The default options are to not allow access to any
[private IP addresses](http://en.wikipedia.org/wiki/Private_network), and to
only allow HTTP(S) connections.

If you wish to add your own options (such as to blacklist any requests to
domains your control), simply get a new SimpleCurl\Options object, add to the
white or black lists, and pass it along with the method calls.

Domains are express using regex syntax, whilst IPs, scheme and ports are
standard strings (IPs can be specified in
[CIDR notation](https://en.wikipedia.org/wiki/Cidr)).

```php
use fin1te\SafeCurl\Options;

$options = new Options();
$options->addToList('blacklist', 'domain', '(.*)\.fin1te\.net');
$options->addToList('whitelist', 'scheme', 'ftp');

//This will now throw an InvalidDomainException
$response = SafeCurl::execute('http://safecurl.fin1te.net', $curlHandle, $options);

//Whilst this will be allowed, and return the response
$response = SafeCurl::execute('ftp://fin1te.net', $curlHandle, $options);
```

Since we can't get access to any already set cURL options (see Caveats
section), to enable `CURL_FOLLOWREDIRECTS` you must call the
`enableFollowRedirects()` method. If you wish to specify a redirect limit, you
will need to call `setMaxRedirects()`. Passing in `0` will allow infinite
redirects.

```php
$options = new Options();
$options->enableFollowLocation();
//Abort after 10 redirects
$options->setFollowLocationLimit(10);
```

#### URL Checking

The URL checking methods are also public, meaning that you can validate a URL
before using it elsewhere in your application, although you'd want to try and
catch any redirects.

```php
use fin1te\SafeCurl\Url;

try {
    $url = 'http://www.google.com';

    $validatedUrl = Url::validateUrl($url);
    $fullUrl = $validatedUrl['url'];
} catch (Exception $e) {
    // URL wasn't safe
}
```
#### Optional Protections

In addition to the standard checks, two more are available.

The first is to prevent [DNS Rebinding](https://en.wikipedia.org/wiki/DNS_rebinding)
attacks. This can be enabled by calling the `enablePinDns` method on an
`Options` object. There is one major issue with this - the SSL certificate
**can't** be validated. This is due to the real hostname being sent in the
`Host` header, and the URL using the IP address.

```php
$options = new Options();
$options->enablePinDns();
```

The second disables the use of credentials in a URL, since PHP's `parse_url`
returns values which differ from ones cURL uses. This is a temporary fix.

```php
$options = new Options();
$options->disableSendCredentials();

//This will throw an InvalidURLException
$response = SafeCurl::execute('http://user:pass@google.com', $curlHandle, $options);
```

#### Caveats

Since SafeCurl uses `getaddrbyhostl` to resolve domain names, which isn't IPv6
compatible, the class will only work with IPv4 at the moment. See
[Issue #1](https://github.com/fin1te/safecurl/issues/1).

As mentioned above, we can't fetch the value of any cURL options set against
the provided cURL handle. Because SafeCurl handles redirects itself, it will
turn off `CURLOPT_FOLLOWLOCATION` and use the value from the `Options` object.
This is also true of `CURLOPT_MAXREDIRECTS`.

## Demo

A live demo was available at
[http://safecurl.fin1te.net/#demo](http://safecurl.fin1te.net/#demo). For the
site source code (if you're curious), it's hosted at
[fin1te/safecurl.fin1te.net](https://github.com/fin1te/safecurl.fin1te.net).

## Bounty

In order to help make SafeCurl secure and ready for production use,
[a Bitcoin bounty](http://safecurl.fin1te.net/#bounty) was run.

Inside the document root was a [Bitcoin wallet](http://safecurl.fin1te.net/btc.txt),
which could only be accessed by 127.0.0.1. If you could bypass the protections
and grab the file, you were free to take the Bitcoins.

The Bitcoin bounty has since concluded. For more information on the Bitcoin
bounty, see the blog post
['SafeCurl "Capture the Bitcoins" Post-Mortem'](https://whitton.io/articles/safecurl-capture-the-bitcoins-post-mortem/).
