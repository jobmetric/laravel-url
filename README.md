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

# Laravel URL

**SEO-Friendly URL and Slug Management**

Laravel URL simplifies URL and slug management in Laravel applications. Stop wrestling with manual URL generation and start building SEO-friendly, versioned URLs that automatically handle redirects, conflicts, and cascading updates. It provides automatic URL versioning, intelligent conflict detection, cascading URL updates, and smart fallback routing—all designed to make URL management effortless while maintaining SEO best practices.

## Why Laravel URL?

### Automatic URL Versioning

Track complete URL history with automatic versioning, ensuring that old URLs redirect to new ones, preserving SEO value and preventing broken links.

### Intelligent Conflict Detection

Enforce global uniqueness for active URLs and slugs, preventing duplicate content issues and ensuring a clean URL structure across your application.

### Cascading URL Updates

Automatically update child URLs when parent slugs change, maintaining hierarchical URL integrity without manual intervention.

### Smart Fallback Routing

Resolve unmatched request paths against the versioned `urls` table, providing automatic 301 redirects for legacy URLs and handling 404s gracefully.

## What is URL Management?

URL management is the process of creating, storing, and maintaining clean, SEO-friendly URLs for your application's content. In traditional Laravel applications, managing URLs often involves:

- Manually creating slugs for each model
- Implementing custom logic for URL versioning and redirects
- Handling URL conflicts and uniqueness checks
- Building custom fallback routes for unmatched URLs

Laravel URL solves these challenges by providing a unified system that works seamlessly with your Eloquent models. You can:

- **Manage slugs** - One slug per model with optional collection grouping
- **Version URLs** - Automatic versioning tracks complete URL history
- **Detect conflicts** - Global uniqueness enforcement for active URLs
- **Cascade updates** - Automatically update child URLs when parents change
- **Handle soft deletes** - Graceful conflict checking on restore
- **Rebuild URLs** - Bulk URL resynchronization for migrations

## What Awaits You?

By adopting Laravel URL, you will:

- **Improve SEO rankings** - Clean, consistent URLs and automatic redirects
- **Reduce development time** - Automate URL management tasks
- **Enhance user experience** - Prevent broken links and provide intuitive URLs
- **Simplify content management** - Easy slug and URL updates
- **Scale effortlessly** - Handle thousands of URLs with robust performance
- **Maintain clean code** - Intuitive API that follows Laravel conventions

## Quick Start

Install Laravel URL via Composer:

```bash
composer require jobmetric/laravel-url
```

Then publish the migration and run it:

```bash
php artisan vendor:publish --tag=url-migrations
php artisan migrate
```

## Documentation

Ready to transform your Laravel applications? Our comprehensive documentation is your gateway to mastering Laravel URL:

**[📚 Read Full Documentation →](https://jobmetric.github.io/packages/laravel-url/)**

The documentation includes:

- **Getting Started** - Quick introduction and installation guide
- **HasUrl Trait** - Core trait for URL and slug management
- **UrlContract Interface** - Define canonical URLs for your models
- **Slug & Url Models** - Database models for slugs and URLs
- **Events** - Hook into URL lifecycle
- **Exceptions** - Handle URL-related errors
- **Validation Rules** - `SlugExistRule` for uniqueness
- **API Resources** - `SlugResource` and `UrlResource` for API responses
- **FullUrlController** - Smart fallback routing
- **HasUrlType Trait** - Typeify integration for URL capability

## Contributing

Thank you for participating in `laravel-url`. A contribution guide can be found [here](CONTRIBUTING.md).

## License

The `laravel-url` is open-sourced software licensed under the MIT license. See [License File](LICENCE.md) for more information.
