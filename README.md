# SilverStripe Elastic Proxy

A search proxy system that proxies all search queries through a SilverStripe-hosted endpoint so that your API keys, endpoint URLs etc. aren't exposed to the public.

This is designed to be used alongside the [elastic/search-ui React library](https://github.com/elastic/search-ui). It lets you use all the power and flexibility of the React-powered frontend, while ensuring your API credentials and endpoint remain hidden.

The concept behind this is simple enough that should be straightforward to port to other frameworks if desired.


## Requirements

* SilverStripe `^4`
* Elastic App Search, Elastic Enterprise Search or similar (either self-hosted or Elastic Cloud)


## License
See [License](license.md)


## Installation

```
composer require madmatt/silverstripe-elastic-proxy
```

## Configuration

This module does nothing until it's configured. See [docs/configuration.md](docs/configuration.md).


## Upgrading

When new versions of the module are released, you will need to manually re-run through the [configuration steps again](docs/configuration.md) - nothing will automatically update for you.

## Troubleshooting

If you are having issues with the module, first try reverting the JS changes made in the [configuration docs](docs/configuration.md), to ensure what you are doing works fine when connecting directly to Elastic. If it does, but it doesn't work when using the `endpointBase` of `/_search`, there may be a bug with the module - please [create a GitHub issue](https://github.com/madmatt/silverstripe-elastic-proxy/issues) with as much detail as you can.


## Maintainers
 * [madmatt](https://github.com/madmatt)


## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: Silverstripe CMS version, Browser (if relevant), PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.


## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers. See the [Contributing documentation](Contributing.md) for more details.
