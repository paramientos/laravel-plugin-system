<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plugins Path
    |--------------------------------------------------------------------------
    |
    | This value determines the path where your plugins are stored.
    | By default, plugins are stored in the app/Plugins directory.
    |
    */
    'plugins_path' => app_path('Plugins'),

    /*
    |--------------------------------------------------------------------------
    | Plugin Namespace
    |--------------------------------------------------------------------------
    |
    | This value determines the base namespace for your plugins.
    | All plugin classes will be created under this namespace.
    |
    */
    'plugin_namespace' => 'App\\Plugins',

    /*
    |--------------------------------------------------------------------------
    | Use Plugins Prefix in Routes
    |--------------------------------------------------------------------------
    |
    | When set to true, all plugin routes will be prefixed with 'plugins/'.
    | When false, routes will only use the plugin name as prefix.
    |
    */
    'use_plugins_prefix_in_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Default View Type
    |--------------------------------------------------------------------------
    |
    | This determines the default view type when creating new plugins.
    | Options: 'volt', 'blade'
    | 'volt' - Creates Livewire Volt components
    | 'blade' - Creates traditional Blade views
    |
    */
    'default_view_type' => 'volt',

    /*
    |--------------------------------------------------------------------------
    | Volt Support
    |--------------------------------------------------------------------------
    |
    | Whether to enable Volt support for plugin views.
    | Set to false if you don't want to use Livewire Volt.
    |
    */
    'enable_volt_support' => true,
];
