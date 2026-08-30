# Configuration

The runtime is Mezzio-style: `laminas-config-aggregator` merges configuration and
`laminas-servicemanager` builds the container. It is **not** Mezzio itself.

## Layout

```
config/
  config.php                        # ConfigAggregator over providers + autoload dir + cache
  container.php                     # ServiceManager build
  development.config.php.dist       # dev-mode template (debug on, cache off)
  autoload/
    dependencies.global.php         # service aliases / invokables / factories
    global.php                      # global flags (cache, debug)
    .gitignore                      # ignores local.php, *.local.php
data/
  cache/                            # config cache output
bin/
  console                           # entry point: build container, run Application
```

## Providers

`config/config.php` aggregates, in order:

1. `Laminas\ServiceManager\ConfigProvider`
2. `Webware\Console\ConfigProvider` — wires the Application, command loader, and
   menu command factories.
3. Cache config.
4. `config/autoload/{{,*.}global,{,*.}local}.php` — application config.
5. `config/development.config.php` — if present.

## Local overrides

`config/autoload/` follows the standard local-override convention: `local.php`
and `*.local.php` are gitignored and loaded after `global.php`, so local settings
win.

## Development mode

Copy the template to enable development mode (debug enabled, config caching
disabled):

```bash
cp config/development.config.php.dist config/development.config.php
```
