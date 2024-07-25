[contributors-shield]: https://img.shields.io/github/contributors/jobmetric/laravel-url.svg?style=for-the-badge
[contributors-url]: https://github.com/jobmetric/laravel-url/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/jobmetric/laravel-url.svg?style=for-the-badge&label=Fork
[forks-url]: https://github.com/jobmetric/laravel-url/network/members
[stars-shield]: https://img.shields.io/github/stars/jobmetric/laravel-url.svg?style=for-the-badge
[stars-url]: https://github.com/jobmetric/laravel-url/stargazers
[license-shield]: https://img.shields.io/github/license/jobmetric/laravel-url.svg?style=for-the-badge
[license-url]: https://github.com/jobmetric/laravel-url/blob/master/LICENCE.md
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-blue.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/majidmohammadian

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![MIT License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

# Url And Slug for laravel Model

It is a package for url and slug storage management in each model that you can use in your Laravel projects.

## Install via composer

Run the following command to pull in the latest version:

```bash
composer require jobmetric/laravel-url
```

## Documentation

Undergoing continuous enhancements, this package evolves each day, integrating an array of diverse features. It stands as an indispensable asset for enthusiasts of Laravel, offering a seamless way to harmonize their projects with url database models.

In this package, you can employ it seamlessly with any model requiring database url.

Now, let's delve into the core functionality.

>#### Before doing anything, you must migrate after installing the package by composer.

```bash
php artisan migrate
```

Meet the `Urlable` class, meticulously designed for integration into your model. This class automates essential tasks, ensuring a streamlined process for:

In the first step, you need to connect this class to your main model.

```php
use JobMetric\Url\Urlable;

class Product extends Model
{
    use Urlable;
}
```

## How is it used?

### Storing a url

You can now use the `Urlable` class to store urls for your model. The following example shows how to create a new product by saving a url:

```php
$product = new Product();
$product->name = 'Product 1';
$product->save();

$product->dispatchUrl('slug', 'product');
```

In this example, the `dispatchUrl` method is used to store an url for the product model. The first parameter is the value of the url, and the second parameter is the type of url.

### Retrieving a url

You can retrieve a url for a model using the `getUrl` method. The following example shows how to retrieve a url for a product:

```php
$product = Product::find(1);
$product->getUrl();
```

or you can use the following code to get the url of the product:

```php
$product = Product::find(1);
$product->url;
```

In this example, we retrieved a product from the database and then retrieved the url for the product using the `getUrl` method.

### Forget a url

You can forget a url for a model using the `forgetUrl` method. The following example shows how to forget a url for a product:

```php
$product = Product::find(1);
$product->forgetUrl('product');
```

In this example, we retrieved a product from the database and then forgot the url for the product using the `forgetUrl` method. The parameter is the type of url.

## Contributing

Thank you for considering contributing to the Laravel Url! The contribution guide can be found in the [CONTRIBUTING.md](https://github.com/jobmetric/laravel-url/blob/master/CONTRIBUTING.md).

## License

The MIT License (MIT). Please see [License File](https://github.com/jobmetric/laravel-url/blob/master/LICENCE.md) for more information.
