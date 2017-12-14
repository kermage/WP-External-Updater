# External Update Manager --
> *"A drop-in library for WordPress themes or plugins to manage updates."*

*self-hosted...can't be submitted to official WordPress repository...non-GPL licensed...custom-made...commercial...etc.*

## Requirements
* PHP 5.2.4+
* WordPress 4.4.0+

## Installation
1. Grab the `class-external-update-manager.php` file and place it somewhere inside the theme or plugin directory
2. Add a `require_once` call in the theme's `functions.php` or in the plugin's `main php file` referencing the class file
3. Instantiate the `External_Update_Manager` with the `full path` of the theme or plugin and the `update URL` to check

```php
require_once( 'class-external-update-manager.php' );
new External_Update_Manager( __FILE__, '<UPDATE URL>' );
```

## Working Example

* (ThemePlate)[https://github.com/kermage/ThemePlate]
