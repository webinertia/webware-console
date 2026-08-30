# Installation

## Requirements

- PHP 8.4.1+ (`~8.4.1 || ~8.5.0`)
- Composer

## Consumer applications

Install from within the consuming application:

```bash
composer require --dev webware/webware-console
```

## Development

When working on this package directly:

```bash
composer install
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
