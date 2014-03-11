<?php
namespace Comforter;

class Api
{
    private static $autoRegister = true;
    private static $services = array();
    private static $encoders = array('application/json' => 'json_encode', 'text/plain' => 'Api::PlainTextEncoder');
    private static $defaultEncoder = 'application/json';
    private static $verbs = array("options", "get", "head", "post", "put", "delete", "trace", "connect");

    /*
     * Private Methods
     */

    private static function AttendRequest()
    {
        // take into account the relative location of "index.php" to the domain root
        // TODO properly handle the last trailing slash and parse out the query string. Use regex.
        $uri = explode("/", substr($_SERVER["REQUEST_URI"], strlen(substr($_SERVER["PHP_SELF"], 0, -9))));
        $http_method = strtolower($_SERVER["REQUEST_METHOD"]);
        $format = strpos($_SERVER["HTTP_ACCEPT"], ",")
            ? explode(",", $_SERVER["HTTP_ACCEPT"])[0]
            : $_SERVER["HTTP_ACCEPT"];

        $service = self::GetServiceBySlug($uri[0]);
        $method = self::GetMethodBySlug($service, $uri[1], $http_method);

        // TODO This must be changed: UserProfileService != UserprofileService
        $serviceName = ucfirst($uri[0]) . 'Service';

        try {
            $data = $serviceName::{$method}(array(
                "headers" => self::GetHttpHeaders(),
                "request" => $_REQUEST,
                "args" => array_slice($uri, 2)
            ));
        } catch (\Exception $e) {
            header("HTTP/1.1 500 Internal Server Error");
            $data = $e->getMessage();
        }

        if (isset(self::$encoders[$format])) {
            $encoder = self::$encoders[$format];
        } elseif (self::$defaultEncoder !== null && isset(self::$encoders[self::$defaultEncoder])) {
            $encoder = self::$encoders[self::$defaultEncoder];
        } else {
            self::FailRequest();
        }

        if (strpos($encoder, '::')) {
            list($class, $method) = explode("::", $encoder);
            $response = $class::{$method}($data);
        } else {
            $response = $encoder($data);
        }

        header("Content-Type: $format");
        header("Content-Length: " . strlen($response));
        echo $response;
    }

    private static function GetServiceBySlug($slug)
    {
        if (count(self::$services) == 0 || !isset(self::$services[$slug])) {
            self::FailRequest();
        }
        return self::$services[$slug];
    }

    private static function GetMethodBySlug($service, $resource_slug, $verb)
    {
        if (!isset($service[$resource_slug][$verb])) {
            self::FailRequest();
        }
        return $service[$resource_slug][$verb];
    }

    private static function GetServiceMethods($class_name)
    {
        return (new \ReflectionClass($class_name))->getMethods(\ReflectionMethod::IS_PUBLIC);
    }

    private static function GetHttpHeaders()
    {
        $headers = '';
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    private static function RegisterSingleService($name)
    {
        $newService = array();
        if (substr($name, -7) == "Service") {
            $serviceName = $name;
        } else if (in_array($name . "Service", get_declared_classes())) {
            $serviceName = $name . "Service";
        } else {
            return;
        }


        $slug = strtolower(str_replace("Service", "", $serviceName));
        foreach (self::GetServiceMethods($serviceName) as $method) {
            foreach (self::$verbs as $http_method) {
                if (stripos($method->name, $http_method) === 0) {
                    $newService[strtolower(str_replace($http_method, "", $method->name))][$http_method] = $method->name;
                    break;
                }
            }
        }

        self::$services[$slug] = $newService;
    }

    private static function FailRequest()
    {
        header("HTTP/1.1 400 Bad Request");
        exit;
    }

    /*
     * Public Methods
     */

    public static function Start()
    {
        if (self::$autoRegister) {
            // TODO Handle multiple namespaces
            foreach (array_filter(get_declared_classes(), function ($c) {
                return strrpos($c, "Service") !== false;
            }) as $service) {
                self::RegisterSingleService($service);
            }
        }

        self::AttendRequest();
    }

    public static function PlainTextEncoder($data)
    {
        return $data;
    }

    public static function RegisterService($service_name)
    {
        if (is_array($service_name)) {
            foreach ($service_name as $sn) {
                self::RegisterSingleService($sn);
            }
        }
        self::RegisterSingleService($service_name);
    }

    public static function RegisterEncoder($type, $callback)
    {
        if ($callback === null) {
            unset(self::$encoders[$type]);
        }
        self::$encoders[$type] = $callback;
    }

    public static function RegisterHttpRequestMethod($string)
    {
        self::$verbs[] = $string;
    }


    public static function SetAutoRegisterService($bool)
    {
        if (is_bool($bool)) {
            self::$autoRegister = $bool;
        }
        trigger_error('Setting must be boolean.', E_USER_ERROR);
    }

    public static function SetDefaultEncoder($encoder)
    {
        if ((isset(self::$encoders[$encoder]))) {
            self::$defaultEncoder = $encoder;
        }
        trigger_error('"' . $encoder . '" has not been mapped.', E_USER_ERROR);
    }
}

abstract class AbstractServiceClass
{
    protected static function AddHeader($key, $value = '')
    {
        if (is_array($key)) {
            foreach ($key as $name => $val) {
                header($name . ': ' . $val);
            }
        } elseif (is_string($key)) {
            header($key . ': ' . $value);
        }
    }

    protected static function SendHttpResponse($code)
    {
        // Last Updated: 3/7/14
        // Based on the list of HTTP Response Codes:
        // http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
        $responses = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            //306 => 'Reserved',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required'
        );
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');

        if (is_int($code) && isset($responses[$code])) {
            header($protocol . ' ' . $code . ' ' . $responses[$code]);
            if ($code >= 400) {
                exit;
            }
        } elseif (is_string($code)) {
            header($protocol . ' ' . $code);
        } else {
            trigger_error('Unsupported response code or object type "' . $code . '" was given.', E_USER_ERROR);
        }
    }
}