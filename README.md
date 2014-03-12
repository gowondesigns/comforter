{~} Comforter
========

A RESTful PHP micro-framework for web applications and APIs.

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
?>
```

### Sample Dispatcher

```php
<?php
// index.php
require "comforter.php";
require "user.php";

\Comforter\Api::Start();
?>
```

## Guidelines

- Comforter creates routes automatically. Thus, UserProfileService becomes /userprofile/, and UserProfileService::getUserData() becomes GET /userprofile/userdata.
- All methods within the Service class must be *static*.
- All methods within the Service class must contain a *$context* argument, which contains an array with three keys:
    * __request__ contains $_REQUEST data
    * __args__ contains all of the slugs in the URL beyond the second slash
    * __headers__ contains all the HTTP Headers that were sent along with the request
- All methods must start with an HTTP Request Method verb, like *get*, *post*, *put*, *delete*, *options*, etc. You can even register new verbs to be used with your application.
- The 'Accept' header defines the API method's output. If Accept is 'application/json', it'll automatically encode the data.
    * Custom encoders can be registered with Comforter to export data into any format.

## Settings

- __autoRegister__: true to search in all declared classes those whose name ends with "Service", false to allow manual registration. To do so, use the *Api::RegisterService($name)* method.
- __enableGzip__
- __encoders__
- __defaultEncoder__
- __verbs__

## License

Comforter is licensed under the MIT License. Comforter is forked from [Descanse](https://github.com/joelalejandro/Descanse).
