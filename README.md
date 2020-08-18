# AMP App Shell for WordPress

Add support for app shell navigation via AMP Shadow DOM.

This is a WordPress plugin which you can activate to see the app shell navigation
in action. It extends the functionality of the [AMP plugin](https://wordpress.org/plugins/amp/).

Tha app shell navigation based on AMP Shadow DOM is an experimental feature initially
proposed in [ampproject/amp-wp#1519](https://github.com/ampproject/amp-wp/pull/1519).

In order for the plugin to work, you will have to install the
[AMP](https://wordpress.org/plugins/amp/) and [PWA](https://wordpress.org/plugins/pwa/) plugins.

Note that you will also have to run `composer install && npm install && npm run build`
prior to activating the plugin.

## How To Use

For a theme to support app shell via the AMP plugin, the bare minimum that needs to be done is:

1. Activate on [AMP](https://wordpress.org/plugins/amp/) and [PWA](https://wordpress.org/plugins/pwa/) plugins.
2. Ensure your theme (and plugins) work entirely in the AMP plugin's [Transitional mode](https://amp-wp.org/documentation/how-the-plugin-works/amp-plugin-serving-strategies/). You must use Transitional mode as opposed to Standard mode for now. Make sure that you have “Serve all templates as AMP regardless of what is being queried” enabled.
3. Identify the element that contains the markup that changes as you navigate from page to page and wrap it with two function calls to indicate where the app shell container is:
```php
<?php amp_start_app_shell_content(); ?>
    <?php get_template_part( 'content' ); ?>
<?php amp_end_app_shell_content(); ?>
```
4. Opt-in to AMP theme support for app shell by adding the following to your `functions.php`:
```php
add_theme_support( 'amp_app_shell' );
```
5. Ensure the theme uses the Transitional mode, i.e.:
```php
add_theme_support( 'amp', array(
    'paired' => true,
) );
```
6. You should define an `offline.php` template in your theme.

For theme which adds support for AMP app shell see https://github.com/xwp/twentyseventeen-westonson. Note that this theme copies some code from the Twenty Seventeen parent theme in order to add make the necessary modifications. For example, the `js/global.js` file modified as per [this diff](https://gist.github.com/westonruter/b9d7952c0879ea3cda9e0081e387846d). The theme has app shell behind a theme mod flag, so make sure you do `wp theme mod set service_worker_navigation amp_app_shell` via WP-CLI. See also how it [adds skeleton content](https://github.com/xwp/twentyseventeen-westonson/blob/03934b7328f9b18dd979ae97458ad38feb6f0e1a/functions.php#L241-L283) to the app shell. See also plugin to [enable stale-while-revalidate caching strategy for navigation requests](https://gist.github.com/westonruter/f848013108672568be6dcde153f9ca37).

This was presented at CDS 2018; see [related portion of talk](https://youtu.be/s1WrBaAyzAI?t=940).

See also this short screen cast: https://youtu.be/oAiIbhHdoXM

For some more background on this, see [GoogleChromeLabs/pwa-wp#12 (comment)](https://github.com/GoogleChromeLabs/pwa-wp/issues/12#issuecomment-401843173)
