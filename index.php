<?php
/**
 * Load the framework.
 */
require "comforter.php";

/**
 * Example web service
 * Can be accessible via GET /profile/user
 */
class ProfileService {
    public static function getUser($context) {
        return array("name" => "John Doe", "id" => 9, "context" => $context);
    }
}

/**
 * Attend the request!
 */
\Comforter\Api::Start();
