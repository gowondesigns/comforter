{~} Comforter
========

A RESTful PHP micro-framework for web applications and APIs.

## Features
- Automatic Routing - Comforter can search and register service classes for routing automatically. Disable to register services manually.
- Custom Encoders - Set encoders to handle any format request.
- Custom HTTP Request Methods - Add non standard verbs for Comforter to route to your services.
- Gzip Compression

## Requirements

PHP >= 5.3.0

## Usage

1. Add the .htaccess file included in this repository. This file redirects all incoming requests to a single dispatcher.
2. Create a dispatcher file (i.e. index.php) that includes Comforter.
3. Create a class that has the word "Service" as a suffix (UserService, SearchService, etc.).
4. Create a method in that class with a name starting with an HTTP Request Method verb like get, post, put or delete (i.e. getUser, postUser, putUser, deleteUser, etc.). The method must return a PHP type, object or array.
5. Include the Service class in the dispatcher (this is done automatically by default).
6. Start Comforter.

## Example

### Sample Service Class

```php
<?php
// user.php
class UserService {
  public static function getName($context) {
    return array("first" => "John", "last" => "Doe");
  }
}
```

### Sample Dispatcher

```php
<?php
// index.php
require "comforter.php";
require "user.php";

\Comforter\Api::Start();
```

## Guidelines

- Comforter can create routes automatically. Thus, `/userprofile/` => `UserProfileService` and `GET /userprofile/userdata/` => `UserProfileService::getUserData()`
- All methods within the Service class must be *static*.
- All methods within the Service class must contain a *$context* argument, which contains an array with three keys:
    * __request__ contains $_REQUEST data
    * __args__ contains all of the slugs in the URL beyond the second slash
    * __headers__ contains all the HTTP Headers that were sent along with the request
- All methods must start with an HTTP Request Method verb, like *get*, *post*, *put*, *delete*, *options*, etc.
- The 'Accept' header defines the API method's output. If Accept is 'application/json', it'll automatically encode the data.

## License

Comforter is licensed under the MIT License. Comforter is forked from [Descanse](https://github.com/joelalejandro/Descanse).
