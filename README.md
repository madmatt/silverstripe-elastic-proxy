# SilverStripe Elastic Proxy

A search proxy system that proxies all search queries through a SilverStripe-hosted endpoint so that your API keys, endpoint URLs etc. aren't exposed to the public.

This is designed to be used alongside the [elastic/search-ui React library](https://github.com/elastic/search-ui). It lets you use all the power and flexibility of the React-powered frontend, while ensuring your API credentials and endpoint remain hidden.

The concept behind this is simple enough that should be straightforward to port to other frameworks if desired.


## Requirements

* SilverStripe ^4.0
* Elastic App Search, Elastic Enterprise Search or similar


## License
See [License](license.md)


## Installation

```
composer require madmatt/silverstripe-elastic-proxy
```

Then visit `?flush=1` in your browser to ensure the new `Controller` is registered.


## Configuration

Ensure you provide the following environment variables that the module can pick up:
* `SS_ELASTIC_PROXY_ENDPOINT`: The full URL to your Elastic instance (e.g. https://deploymentsha.app-search.ap-southeast-2.aws.found.io/)
* `SS_ELASTIC_PROXY_SEARCH_KEY`: The 'public' API search key to be used when querying the API
* `SS_ELASTIC_PROXY_ENGINE_NAME`: The engine name you want to search

Once the environment variables are configured, update the `AppSearchAPIConnector` you already have from [configuring the React library](https://github.com/elastic/search-ui/tree/master/packages/search-ui-app-search-connector):
```js
const connector = new AppSearchAPIConnector({
  // Note: Don't replace {{search-key}} or {{engine-name}} with your key - the module will do that for you!
  searchKey: "{{search-key}}",
  engineName: "{{engine-name}}",
  endpointBase: "/_external/elastic"
});
````

Rebuild your frontend components via your normal process and test that it works as expected.


## Troubleshooting

If you are having issues with the module, first try reverting the JS changes made in the previous section, to ensure what you are doing works fine with connecting directly to Elastic. If it does, but it doesn't work when using the `endpointBase` of `/_external/elastic`, there may be a bug with the module - please [create a GitHub issue](https://github.com/madmatt/silverstripe-elastic-proxy/issues) with as much detail as you can.


## Maintainers
 * [madmatt](https://github.com/madmatt)


## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over 
existing issues to ensure yours is unique. 
 
If the issue does look like a new bug:
 
 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots 
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version, 
 Operating System, any installed SilverStripe modules.
 
Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.
 

## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers. See the [Contributing documentation](Contributing.md) for more details.