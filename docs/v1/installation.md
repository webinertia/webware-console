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

The simplest way in is a Composer alias that opens the menu:

```bash
composer console-menu    # or: composer menu
```

Both aliases run the bundled `bin/console` entry point. Invoke it directly when
needed:

```bash
php bin/console          # run the Application
php bin/console menu     # open the menu directly
```
