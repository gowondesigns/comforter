Yasunde
========

A really really simple API framework for PHP.

## The name

"Yasunde" is the Japanese translation for "rest".

## Requirements

PHP >= 5.3.0

## Usage

1. Add the .htaccess file included in this repository. This file redirects all incoming requests to a single dispatcher.
2. Create a dispatcher file (i.e. index.php) that include Yasunde.
3. Create a class that has the word "Service" as a suffix (UserService, SearchService, etc.).
4. Create a method in that class with a name starting with get, post, put or delete (i.e. getUser, postUser, putUser, deleteUser, etc.). The method must return a PHP type, object or array.
5. Include the Service class in the dispatcher (this is done automatically by default).
6. Run Yasunde.

## A Service Class example

<code><pre><?php
// user.php
class UserService {
  public static function getName($context) {
    return array("first" => "Joel", "last" => "Villarreal");
  }
}
?>
</pre></code>

## The Dispatcher

<code><pre><?php
require "yasunde.php";
require "user.php";
Yasunde::Start();
?></pre></code>

## Guidelines

- Yasunde creates routes automatically. Thus, UserProfileService becomes /userprofile/, and UserProfileService::getUserData() becomes GET /userprofile/userdata.
- All methods within the Service class must be *static*.
- All methods within the Service class must contain a *$context* argument, which contains an array with two keys: *request* (contains $_REQUEST data) and *args* (contains all of the slugs in the URL beyond the second slash, i.e. if the route is /userprofile/userdata/1/abc/3f, *args* will contain [0] => 1, [1] => abc, [2] => 3f).
- All methods must start with *get*, *post*, *put* or *delete*.
- If you wish to change the default settings of Yasunde, you may do so using *Yasunde::$settings* before *Yasunde::Start()*.
- The 'Accept' header defines the API method's output. If Accept is 'application/json', it'll automatically encode the data.

## Settings

- *auto_register*: true to search in all declared classes those whose name ends with "Service", false to allow manual registration. To do so, use the *Yasunde::registerService($name)** method.

## License
Yasunde is forked from [Descanse](https://github.com/joelalejandro/Descanse).

Descanse is licensed under the MIT License.
