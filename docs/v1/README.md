# Webware Console

A text user interface for Webware and Mezzio CLI commands — a navigable menu,
better per-command help, and interactive input, built over Symfony Console.

[![Continuous Integration](https://github.com/webinertia/webware-console/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/webinertia/webware-console/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-console/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-console)

`webware/webware-console` is a **generic CLI host**. It owns the Symfony Console
runtime and command discovery, and presents and invokes commands that other
components register. It does not reimplement command logic.

## Features

- **Menu** — a keyboard-navigable list of every discovered command.
- **Help** — a command's purpose, arguments, and options on demand.
- **Run** — collect arguments, options, and flags interactively; run in-process;
  report output and exit status before returning to the menu.
- **Runtime discovery** — commands appear from installed components with no
  manual command list.

## Documentation

- [Installation](installation.md)
- [Quickstart](quickstart.md)
- [Command discovery](command-discovery.md)
- [Configuration](configuration.md)
- [Development](development.md)

## Requirements

- PHP 8.4.1+ (`~8.4.1 || ~8.5.0`)
- Composer
