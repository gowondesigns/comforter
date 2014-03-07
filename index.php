<?php
/**
 * Load the framework.
 */
require "yasunde.php";
use Yasunde\Yasunde;

/**
 * Example web service
 * Can be accessible via GET /profile/user
 */
class ProfileService {
    public static function getUser($context) {
        return array("name" => "Gowon Designs", "age" => 88, "context" => $context);
    }
}
/**/

/**
 * Attend the request!
 */
Yasunde::go();
