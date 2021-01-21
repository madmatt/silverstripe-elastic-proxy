<?php

namespace Madmatt\ElasticProxy;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;

/**
 * Silverstripe -> Elastic App Search proxy
 *
 * Allows user to hide the 'public' search API key and endpoint URL, to prevent malicious usage or direct attack of the
 * Elastic Enterprise Search or Elastic App Search instance.
 *
 * Requires the following environment variables to be set alongside other typical ones like SS_DATABASE_USERNAME:
 * - APP_SEARCH_ENDPOINT: The full URL (without trailing slash) to your Elastic endpoint e.g. https://deploy-sha.ent-search.aws-region-code.aws.cloud.es.io
 * - APP_SEARCH_API_SEARCH_KEY: The public search key (begins with `search-`) as provided by the Elastic interface
 * - APP_SEARCH_ENGINE_PREFIX: The prefix for the Elastic engine that you expect to query
 * - APP_SEARCH_ENGINE_INDEX_NAME: The name of the Elastic index that you expect to query (defaults to 'content')
 *
 * See README.md and docs/configuration.md for full installation and configuration details.
 */
class ElasticsearchController extends Controller
{
    use Configurable;

    /**
     * @config
     * @var bool true to enable the controller, false to disable it. See docs/configuration.md to understand when to
     * enable the controller.
     */
    private static $enabled = false;

    /**
     * @config
     * @var int time in seconds allowed for curl to make a successful connection
     */
    private static $curl_connect_timeout = 2;

    /**
     * @config
     * @var int time in seconds allowed for curl to make a return a successful response
     */
    private static $curl_timeout = 5;

    /**
     * @config
     * @var string[]
     * A list of endpoints that should be allowed to be hit via this proxy
     */
    private static $allow_list = [
        'search',
        'query_suggestion',
        'curations',
        'schema',
        'synonyms',
    ];

    /**
     * Handle all possible error / edge cases, then passthru to Elastic for rendering of search results.
     *
     * @return string
     */
    public function index()
    {
        if (!$this->config()->enabled) {
            $this->httpError(500, 'ElasticsearchController is not enabled');
            exit(1);
        }

        $endpoint = Environment::getEnv('APP_SEARCH_ENDPOINT');
        $searchKey = Environment::getEnv('APP_SEARCH_API_SEARCH_KEY');
        $engineName = Environment::getEnv('APP_SEARCH_ENGINE_PREFIX');

        if (!$endpoint || !$searchKey || !$engineName) {
            $this->httpError(500, 'Required environment value not found for search-proxy');
        }

        if (strpos($searchKey, 'search-') !== 0) {
            $this->httpError(500, 'Elastic search key not correctly configured for search-proxy');
        }

        // Ensure we have POST data before attempting to extract it
        $postData = array_keys($_POST);

        if (sizeof($postData) === 0) {
            $this->httpError(500, 'No data submitted to search endpoint');
        }

        $url = $this->getRequest()->getURL();
        $action = substr(rtrim($url, '/'), strrpos($url, '/') + 1);

        // trim .json if present
        if (substr($action, -5) === '.json') {
            $action = substr($action, 0, -5);
        }

        if (!in_array($action, $this->config()->allow_list)) {
            $this->httpError(403, 'Attempted to access blocked endpoint');
        }

        // If we get here, all checks have passed and we just need to extract the POST data. We don't care what the actual data
        // is, so we run no further checks. This is an awkward way of extracting the POST data, which is sent by the Elastic
        // search-ui system as application/json in the POST body, but this isn't understood by PHP, so PHP assumes that the
        // POST body is actually the array *key* with an empty value - hence the use of array_keys to flip the array and then
        // extracting the zeroth key.
        $postData = $postData[0];

        // Allow POST data to be manipulated by extensions
        $this->extend('augmentElasticQuery', $postData);

        /**
         * Map of $_SERVER keys => Elastic header names that should be preserved and included in the request
         */
        $passthruHeaders = [
            'HTTP_X_SWIFTYPE_CLIENT' => 'X-Swiftype-Client',
            'HTTP_X_SWIFTYPE_CLIENT_VERSION' => 'X-Swiftype-Client-Version',
            'HTTP_X_SWIFTYPE_INTEGRATION' => 'x-swiftype-integration',
            'HTTP_X_SWIFTYPE_INTEGRATION_VERSION' => 'x-swiftype-integration-version'
        ];

        $headers = [
            sprintf('Authorization: Bearer %s', $searchKey), // Insert the API key stored in the environment
            'Content-Type: application/json;charset=utf-8' // Force to application/json as we've overwritten this in the JS so PHP knows it's a POST
        ];

        // Pass-through optional HTTP headers provided by the Elastic search-ui JS
        foreach ($passthruHeaders as $httpKey => $elasticKey) {
            if (isset($_SERVER[$httpKey])) {
                $headers[] = sprintf('%s: %s', $elasticKey, $_SERVER[$httpKey]);
            }
        }

        $indexName = Environment::getEnv('APP_SEARCH_ENGINE_INDEX_NAME') ?: 'content';

        // Hard-code the API path to ensure an attacker can't exploit other endpoints on the App Search instance
        $path = sprintf('/api/as/v1/engines/%s-%s/%s', $engineName, $indexName, $action);
        $fullUrl = $endpoint . $path;

        $curl = curl_init($fullUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        // Set a reasonable timeout to lower attack surface
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->config()->curl_connect_timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->config()->curl_timeout);

        // Check for proxy vars and set if present
        $proxyURL = Environment::getEnv('SS_OUTBOUND_PROXY');
        $proxyPort = Environment::getEnv('SS_OUTBOUND_PROXY_PORT');

        if ($proxyURL && $proxyPort) {
            curl_setopt($curl, CURLOPT_PROXY, "{$proxyURL}:{$proxyPort}");
        }

        // This will return the results of the API query out to stdout for the frontend library to interpret
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $curlError = curl_error($curl);
        }
        curl_close($curl);

        if (isset($curlError)) {
            Injector::inst()->get(LoggerInterface::class)->warning(
                sprintf("error connecting to elastic: %s", $curlError)
            );
        }

        // Allow response data from Elastic to be manipulated by extensions prior to being returned
        $this->extend('augmentElasticResults', $response);

        return $response;
    }
}
