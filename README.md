# Webware Console

A text user interface for Webware and Mezzio CLI commands - a navigable menu,
better per-command help, and interactive input, built over Symfony Console.

[![PHP Version](https://img.shields.io/packagist/php-v/webware/webware-console)](https://packagist.org/packages/webware/webware-console)
[![Latest Version](https://img.shields.io/packagist/v/webware/webware-console)](https://packagist.org/packages/webware/webware-console)
[![License](https://img.shields.io/github/license/webinertia/webware-console)](LICENSE)
[![Continuous Integration](https://github.com/webinertia/webware-console/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml/badge.svg)](https://github.com/webinertia/webware-console/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-console/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-console)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fwebware-console%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/webware-console/1.0.x)

`webware/webware-console` is a **generic CLI host**. It owns the Symfony Console
runtime and command discovery, and presents and invokes commands that other
components register. It does not reimplement command logic.

## Features

- **Menu**: a keyboard-navigable list of every discovered command.
- **Help**: a command's purpose, arguments, and options on demand.
- **Run**: collect arguments, options, and flags interactively; run in-process;
  report output and exit status before returning to the menu.
- **Runtime discovery**: commands appear from installed components with no
  manual command list.

## Documentation

Versioned documentation lives under [`docs/`](docs/):

- [Installation](docs/v1/installation.md)
- [Quickstart](docs/v1/quickstart.md)
- [Command discovery](docs/v1/command-discovery.md)
- [Command input](docs/v1/command-input.md)
- [Configuration](docs/v1/configuration.md)
- [Development](docs/v1/development.md)

## Requirements

- PHP 8.4.1+ (`~8.4.1 || ~8.5.0 || ~8.6.0`)
- Composer
