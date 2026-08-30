# Installation

## Requirements

- PHP 8.4.1+ (`~8.4.1 || ~8.5.0`)
- Composer

## Install

The package is published as `webware/webware-console`. Until the first stable
tag, install the dev branch through a VCS repository entry:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/webinertia/webware-console"
        }
    ],
    "require": {
        "webware/webware-console": "0.1.x-dev"
    },
    "minimum-stability": "dev"
}
```

Then run:

```bash
composer update
```

## Entry point

The package ships a `bin/console` entry point over the bundled config and
container bootstrap. From this repository:

```bash
composer console          # run the Application
composer menu             # open the menu directly
```

Or invoke the entry point directly:

```bash
php bin/console
```
