# Configuration

## Step 1: Environment variables
Ensure you provide the following environment variables that the module can pick up:
* `APP_SEARCH_ENDPOINT`: The full URL (without trailing slash) to your Elastic endpoint e.g. https://deploy-sha.ent-search.aws-region-code.aws.cloud.es.io
* `APP_SEARCH_API_SEARCH_KEY`: The public search key (begins with `search-`) as provided by the Elastic interface
* `APP_SEARCH_ENGINE_PREFIX`: The prefix for the Elastic engine that you expect to query
* [Optional] `APP_SEARCH_ENGINE_INDEX_NAME`: The name of the index you wish to use in the proxy (defaults to 'content')

## Step 2: Re-configure the Elastic React library
Once the environment variables are configured, update the `AppSearchAPIConnector` you already have from [configuring the React library](https://github.com/elastic/search-ui/tree/master/packages/search-ui-app-search-connector):

```js
const connector = new AppSearchAPIConnector({
  // Note: Don't replace --dummy value-- below with your actual key or engine name - the module will do that for you!
  searchKey: "--dummy value--",
  engineName: "--dummy value--",
  endpointBase: "/_search",
  // This is required to allow PHP to extract data sent via POST
  additionalHeaders: {
    "Content-Type": "application/x-www-form-urlencoded"
  }
});
````

Rebuild your frontend components via your normal process and test that it works as expected.

## Step 3: Choose the proxy you want and enable it
Decide on how you want to use the proxy. There are two ways you can use this - you only need to follow either 3a or 3b below.

* **Option 3a:** Use the fast search-proxy.php
  * **Pro:** Faster (doesn't need to go through Silverstripe framework's entire bootstrap)
  * **Con:** Not secure if the website you are adding search to is protected, or you want to add any kind of rate-limiting or similar security measures to the proxy.

* **Option 3b:** Use the slower but more secure ElasticsearchController
  * **Pro:** Allows for all standard Silverstripe `HTTPMiddleware` layers, including security, rate limiting etc.
  * **Pro:** Allows for standard Silverstripe `Extension` hooks (see [Extending the query and response](#extending-the-query-and-response) below)
  * **Con:** Slower (as it requires Silverstripe bootstrap). May not be suitable for all use-cases, e.g. you will need to consider significant additional server load if you want to use this with auto-complete.

### 3a: Use search-proxy.php

Add the following to your `.htaccess` in your document root (this is normally the `public` directory for Silverstripe 4 installations), just before the Silverstripe processing logic. Alternatively, change this and the `endpointBase` value configured above:

```apacheconfig
RewriteRule ^_search$ search-proxy.php [L]
```

Manually copy `vendor/madmatt/silverstripe-elatic-proxy/search-proxy.php` into your document root.

You're done - everything should now be working and routing through your web server. If anything doesn't work, check the Network tab of your Dev Tools to see what errors are being returned and fix them up.

### 3b: Use ElasticsearchController

Enable the `ElasticsearchController` and decide on the URL route for the controller by adding this to a new YML config file (e.g. `app/_config/elastic.yml`):

```yml
Madmatt\ElasticProxy\ElasticsearchController:
  enabled: true
SilverStripe\Control\Director:
  rules:
    # We need this to include 6 params because the default Silverstripe rule only includes 3, and the Elastic-generated URL looks like /api/as/v1/engines/<engine name>/search.json
    '_search/$1/$2/$3/$4/$5/$6': Madmatt\ElasticProxy\ElasticsearchController
```

By default, this controller will run all created `HTTPMiddleware` layers, however you may need to configure these (for example, specific rate limiting for this endpoint).

## Extending the query and response

If using Option 3b above, you can use standard Silverstripe `Extension` hooks to augment both the JSON request sent **to** Elastic as well as the results returned to the React frontend, depending on your needs.

Add the following to your YML configuration:

```yml
Madmatt\ElasticProxy\ElasticsearchController:
  extensions:
    - App\Extensions\ElasticsearchControllerExtension
```

Create your extension:

```php
<?php

namespace App\Extensions;

use SilverStripe\Core\Extension;

class ElasticsearchControllerExtension extends Extension
{
    public function augmentElasticQuery(&$postData)
    {
        // Make modifications to the POST data that is about to be sent to Elastic Cloud.
        // For example, you might want to force a specific filter (e.g. subsite_id) to always be applied
        // When you're done, simply update $postData with your new JSON to submit to Elastic
        // For example:
        $postData = str_replace('test', 'test replacement', $postData);
    }

    public function augmentElasticResults(&$response)
    {
        // Make modifications to the JSON response string that Elastic returns after performing a search.
        // When you're done, simply update $response with your new JSON to pass back to the React search-ui library.
        // For example:
        $response = str_replace('badword', '*******', $response);
    }
}
```
