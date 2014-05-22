<?php
namespace fin1te\SafeCurl;

use fin1te\SafeCurl\Exception\InvalidURLException;
use fin1te\SafeCurl\Exception\InvalidURLException\InvalidDomainException;
use fin1te\SafeCurl\Exception\InvalidURLException\InvalidIPException;
use fin1te\SafeCurl\Exception\InvalidURLException\InvalidPortException;
use fin1te\SafeCurl\Exception\InvalidURLException\InvalidSchemeException;

class Url {
    /**
     * Validates the whole URL
     *
     * @param $url     string
     * @param $options fin1te\SafeCurl\Options
     *
     * @return string
     */
    public static function validateUrl($url, Options $options) {
        if (trim($url) == '') {
            throw new InvalidURLException("Provided URL '$url' cannot be empty");
        }

        //Split URL into parts first
        $parts = parse_url($url);

        if (empty($parts)) {
            throw new InvalidURLException("Error parsing URL '$url'");
        }

        if (!array_key_exists('host', $parts)) {
            throw new InvalidURLException("Provided URL '$url' doesn't contain a hostname");
        }

        //First, validate the scheme 
        if (array_key_exists('scheme', $parts)) {
            $parts['scheme'] = self::validateScheme($parts['scheme'], $options);
        } else {
            //Default to http
            $parts['scheme'] = 'http';
        }

        //Validate the port
        if (array_key_exists('port', $parts)) {
            $parts['port'] = self::validatePort($parts['port'], $options);
        }

        //Reolve host to ip(s)
        $parts['ips'] = self::resolveHostname($parts['host']);

        //Validate the host
        $parts['host'] = self::validateHostname($parts['host'], $parts['ips'], $options);
        if ($options->getPinDns()) {
            //Since we're pinning DNS, we replace the host in the URL
            //with an IP, then get cURL to send the Host header
            $parts['host'] = $parts['ips'][0]; 
        }

        //Rebuild the URL
        $cleanUrl = self::buildUrl($parts);

        return array('originalUrl' => $url, 'cleanUrl' => $cleanUrl, 'parts' => $parts);
    }

    /**
     * Validates a URL scheme
     *
     * @param $scheme  string
     * @param $options fin1te\SafeCurl\Options
     *
     * @return string
     */
    public static function validateScheme($scheme, Options $options) {
        //Whitelist always takes precedence over a blacklist
        if (!$options->isInList('whitelist', 'scheme', $scheme)) {
            throw new InvalidSchemeException("Provided scheme '$scheme' doesn't match whitelisted values: "
                                           . implode(', ', $options->getList('whitelist', 'scheme')));
        }

        if ($options->isInList('blacklist', 'scheme', $scheme)) {
            throw new InvalidSchemeException("Provided scheme '$scheme' matches a blacklisted value");
        }

        //Existing value is fine
        return $scheme;
    }

    /**
     * Validates a port
     *
     * @param $port    int
     * @param $options fin1te\SafeCurl\Options
     *
     * @return int
     */
    public static function validatePort($port, Options $options) {
        if (!$options->isInList('whitelist', 'port', $port)) {
            throw new InvalidPortException("Provided port '$port' doesn't match whitelisted values: "
                                         . implode(', ', $options->getList('whitelist', 'port')));
        }

        if ($options->isInList('blacklist', 'port', $port)) {
            throw new InvalidPortException("Provided port '$port' matches a blacklisted value");
        }

        //Existing value is fine
        return $port;
    }

    /**
     * Validates a URL hostname
     *
     * @param $hostname string
     * @param $options  fin1te\SafeCurl\Options
     *
     * @returns string
     */
    public static function validateHostname($hostname, $ips, Options $options) {
        //Check the host against the domain lists
        if (!$options->isInList('whitelist', 'domain', $hostname)) {
            throw new InvalidDomainException("Provided hostname '$hostname' doesn't match whitelisted values: "
                                           . implode(', ', $options->getList('whitelist', 'domain')));
        }

        if ($options->isInList('blacklist', 'domain', $hostname)) {
            throw new InvalidDomainException("Provided hostname '$hostname' matches a blacklisted value");
        }

        $whitelistedIps = $options->getList('whitelist', 'ip');

        if (!empty($whitelistedIps)) {
            $valid = false;

            foreach ($whitelistedIps as $whitelistedIp) {
                foreach ($ips as $ip) {
                    if (self::cidrMatch($ip, $whitelistedIp)) {
                        $valid = true;
                        break 2;
                    }
                }
            }

            if (!$valid) {
                throw new InvalidIpException("Provided hostname '$hostname' resolves to '" . implode(', ', $ips) 
                                           . "', which doesn't match whitelisted values: "
                                           . implode(', ', $whitelistedIps));
            }
        }

        $blacklistedIps = $options->getList('blacklist', 'ip');

        if (!empty($blacklistedIps)) {
            foreach ($blacklistedIps as $blacklistedIp) {
                foreach ($ips as $ip) {
                    if (self::cidrMatch($ip, $blacklistedIp)) {
                        throw new InvalidIpException("Provided hostname '$hostname' resolves to '" . implode(', ', $ips) 
                                                   . "', which matches a blacklisted value: " . $blacklistedIp);
                    }
                }
            }
        }

        return $hostname;
    }

    /**
     * Re-build a URL based on an array of parts
     *
     * @param $parts array
     *
     * @return string
     */
    public static function buildUrl($parts) {
        $url  = '';

        $url .= (!empty($parts['scheme'])) 
              ? $parts['scheme'] . '://' 
              : '';

        $url .= (!empty($parts['user'])) 
              ? rawurlencode($parts['user']) 
              : '';

        $url .= (!empty($parts['pass'])) 
              ? ':' . rawurlencode($parts['pass'])
              : '';

        //If we have a user or pass, make sure to add an "@"
        $url .= (!empty($parts['user']) || !empty($parts['pass'])) 
              ? '@' 
              : '';

        $url .= (!empty($parts['host'])) 
              ? $parts['host'] 
              : '';

        $url .= (!empty($parts['port']))
              ? ':' . (int) $parts['port']
              : '';

        $url .= (!empty($parts['path'])) 
              ? '/' . rawurlencode(substr($parts['path'], 1))
              : '';

        //The query string is difficult to encode properly
        //We need to ensure no special characters can be 
        //used to mangle the URL, but URL encoding all of it
        //prevents the query string from being parsed properly
        if (!empty($parts['query'])) {
            $query = rawurlencode($parts['query']);
            //Replace encoded &, =, ;, [ and ] to originals
            $query = str_replace(array('%26', '%3D', '%3B', '%5B', '%5D'),
                                 array('&',   '=',   ';',   '[',   ']'),
                                 $query);

            $url .= '?' . $query;
        }

        $url .= (!empty($parts['fragment']))
              ? '#' . rawurlencode($parts['fragment'])
              : '';

        return $url;
    }

    /**
     * Resolves a hostname to its IP(s)
     *
     * @param $hostname string
     *
     * @return array
     */
    public static function resolveHostname($hostname) {
        $ips = @gethostbynamel($hostname);
        if (empty($ips)) {
            throw new InvalidDomainException("Provided hostname '$hostname' doesn't resolve to an IP address");
        }

        return $ips;
    }

    /**
     * Checks a passed in IP against a CIDR.
     * See http://stackoverflow.com/questions/594112/matching-an-ip-to-a-cidr-mask-in-php5
     *
     * @param $ip   string
     * @param $cidr string
     *
     * @return bool
     */
    public static function cidrMatch($ip, $cidr) {
        if (strpos($cidr, '/') === false) {
            //It doesn't have a prefix, just a straight IP match
            return $ip == $cidr;
        }

        list($subnet, $mask) = explode('/', $cidr);
        if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1) ) == ip2long($subnet)) {
            return true;
        }

        return false;
    }
}
