# SpinupWP MU Plugin

This must-use plugin adds page cache purging functionality to your WordPress site. In addition to automatically clearing the page cache for individual posts, pages, and other content when it is updated, this plugin allows you to purge the cache for the whole site as well. You will need to download this plugin and install it in the /wp-content/mu-plugins/ folder.

## Install

Copy the contents of the `src` directory to `/wp-content/mu-plugins`, giving a directory structure like so:

```
├── wp-content
    ├── mu-plugins
        ├── spinupwp
        └── spinupwp.php
    ├── plugins
    └── themes
```

Add the following constants to your `wp-config.php`:

```
define( 'WP_CACHE_KEY_SALT', '{DOMAIN}' );
define( 'WP_REDIS_SELECTIVE_FLUSH', true );
define( 'SPINUPWP_CACHE_PATH', '/sites/{DOMAIN}/cache' );
```
