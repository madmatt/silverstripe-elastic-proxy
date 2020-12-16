<?php
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

use SilverStripe\Core\Environment;

// Find autoload.php
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo '{"errors":["autoload.php not found for search-proxy"]}';
    exit(1);
}

$endpoint = Environment::getEnv('APP_SEARCH_ENDPOINT');
$searchKey = Environment::getEnv('APP_SEARCH_API_SEARCH_KEY');
$engineName = Environment::getEnv('APP_SEARCH_ENGINE_PREFIX');

if (!$endpoint || !$searchKey || !$engineName) {
    header('HTTP/1.1 500 Internal Server Error');
    echo '{"errors":["Required environment value not found for search-proxy"]}';
    exit(1);
}

if (strpos($searchKey, 'search-') !== 0) {
    header('HTTP/1.1 500 Internal Server Error');
    echo '{"errors":["Elastic search key not correctly configured for search-proxy"]}';
    exit(1);
}

// Ensure we have POST data before attempting to extract it
$postData = array_keys($_POST);

if (sizeof($postData) === 0) {
    header('HTTP/1.1 500 Internal Server Error');
    echo '{"errors":["No data submitted to search endpoint"]}';
    exit(1);
}

$url = $_SERVER['REQUEST_URI'];
$action = substr(rtrim($url, '/'), strrpos($url, '/') + 1);

// Non-exhaustive list of allowed endpoints - update if necessary
$allowList = [
    'search',
    'query_suggestion',
    'curations',
    'schema',
    'synonyms',
];

// trim .json if present
if (substr($action, -5) === '.json') {
    $action = substr($action, 0, -5);
}

if (!in_array($action, $allowList)) {
    header('HTTP/1.1 403 Forbidden');
    echo '{"errors":["Attempted to access blocked endpoint"]}';
    exit(1);
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
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

// This will return the results of the API query out to stdout for the frontend library to interpret
curl_exec($curl);
