<?php

namespace Madmatt\ElasticProxy;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;

/**
 * Silverstripe -> Elastic App Search proxy
 *
 * Allows user to hide the 'public' search API key and endpoint URL, to prevent malicious usage or direct attack of the
 * Elastic Enterprise Search or Elastic App Search instance.
 *
 * Requires the following environment variables to be set alongside other typical ones like SS_DATABASE_USERNAME:
 * - SS_ELASTIC_PROXY_ENDPOINT: The full URL (without trailing slash) to your Elastic endpoint e.g. https://deploy-sha.ent-search.aws-region-code.aws.cloud.es.io
 * - SS_ELASTIC_PROXY_SEARCH_KEY: The public search key (begins with `search-`) as provided by the Elastic interface
 * - SS_ELASTIC_PROXY_ENGINE_NAME: The name of the Elastic engine that you expect to query
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

        $endpoint = Environment::getEnv('SS_ELASTIC_PROXY_ENDPOINT');
        $searchKey = Environment::getEnv('SS_ELASTIC_PROXY_SEARCH_KEY');
        $engineName = Environment::getEnv('SS_ELASTIC_PROXY_ENGINE_NAME');

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

        // If we get here, all checks have passed and we just need to extract the POST data. We don't care what the actual data
        // is, so we run no further checks. This is an awkward way of extracting the POST data, which is sent by the Elastic
        // search-ui system as application/json in the POST body, but this isn't understood by PHP, so PHP assumes that the
        // POST body is actually the array *key* with an empty value - hence the use of array_keys to flip the array and then
        // extracting the zeroth key.
        $postData = $postData[0];

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
            'Content-Type: application/json' // Force to application/json as we've overwritten this in the JS so PHP knows it's a POST
        ];

        // Pass-through optional HTTP headers provided by the Elastic search-ui JS
        foreach ($passthruHeaders as $httpKey => $elasticKey) {
            if (isset($_SERVER[$httpKey])) {
                $headers[] = sprintf('%s: %s', $elasticKey, $_SERVER[$httpKey]);
            }
        }

        // Hard-code the API path to ensure an attacker can't exploit other endpoints on the App Search instance
        $path = sprintf('/api/as/v1/engines/%s/search.json', $engineName);
        $fullUrl = $endpoint . $path;

        $curl = curl_init($fullUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        // This will return the results of the API query out to stdout for the frontend library to interpret
        return curl_exec($curl);
    }
}
