
# Laravel Translation Manager

The Laravel Translation Manager is an Artisan command suite designed for managing and automating the translation process in Laravel applications. Utilizing the OpenAI API, this package simplifies tasks such as displaying, comparing, translating, and repairing language files across different languages, making it a must-have for developers working on multilingual projects.

## Installation

To get started, install the package through Composer:

```bash
composer require quarkinocom/laravel-translation-manager
```

This command automatically installs the package and registers its service provider via Laravel's package discovery.

## Publishing Configuration

To customize the package's settings, such as specifying your OpenAI API key, publish the configuration file:

```bash
php artisan vendor:publish --provider="Quarkinocom\TranslationManager\TranslationManagerServiceProvider" --tag="config"
```

This command copies the `translation-manager.php` configuration file to your project's `config` directory.

## Usage

The package introduces several Artisan commands to streamline language file management:

### Show Command

Display a list of all translation files for a specified language, including the count of translation keys.

**Usage**:

```bash
php artisan language:translate show {language}
```

**Sample Command**:

```bash
php artisan language:translate show en
```

**Expected Output**:

```
+---------------------+---------------+-------+
| Directory           | File          | Keys  |
+---------------------+---------------+-------+
| /resources/lang/en  | messages.php  | 10    |
| /resources/lang/en  | validation.php| 42    |
+---------------------+---------------+-------+
Total files: 2
```

### Compare Command

Compare translation files between two languages, highlighting missing or empty keys.

**Usage**:

```bash
php artisan language:translate compare {source-language} {target-language}
```

**Sample Command**:

```bash
php artisan language:translate compare en fr
```

**Expected Output**:

```
+---------------------+-----------+-----------------------+
| File                | Key       | Status                |
+---------------------+-----------+-----------------------+
| messages.php        | welcome   | Missing in target     |
| validation.php      | required  | Empty value in target |
+---------------------+-----------+-----------------------+
Differences detected: 2
```

### Translate Command

Translate missing or empty keys from the source language to the target language. Optionally, create or update translation files.

**Usage**:

- To translate all keys:
```bash
php artisan language:translate translate {source-language} {target-language}
```

- To repair (translate only missing or empty keys):
```bash
php artisan language:translate repair {source-language} {target-language}
```

**Sample Command**:

```bash
php artisan language:translate translate en fr
```

**Expected Result**: This command will output the number of keys translated and API calls made, without a specific table format as the result is dependent on the translations performed.

## Git Repository

Explore or contribute to Laravel Translation Manager on GitHub:

[https://github.com/quarkinocom/laravel-translation-manager](https://github.com/quarkinocom/laravel-translation-manager)

## Contributing

Contributions are welcome and greatly appreciated! Feel free to fork the repository, make your changes, and submit a pull request. For substantial changes, please open an issue first to discuss what you would like to change. Remember to update tests as needed.

