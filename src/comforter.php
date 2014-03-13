<?php
/**
 * Comforter - A RESTful PHP micro-framework for web applications and APIs
 *
 * @author      Gowon Patterson <info@gowondesigns.com>
 * @copyright   2014 Gowon Patterson, Gowon Designs
 * @link        http://www.gowondesigns.com
 * @license     http://www.gowondesigns.com/license
 * @version     1.0.0
 * @package     Comforter
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace Comforter;

/**
 * Api
 * @package Comforter
 * @author  Gowon Patterson
 * @since   1.0.0
 */
class Api
{
    /**
     * @var bool
     */
    private static $autoRegister = true;

    /**
     * @var bool
     */
    private static $enableGzip = true;

    /**
     * @var string
     */
    private static $serviceNamespace = null;

    /**
     * @var string
     */
    private static $defaultEncoder = 'application/json';

    /**
     * @var array
     */
    private static $services = array();

    /**
     * @var string[]
     */
    private static $verbs = array("options", "get", "head", "post", "put", "delete", "trace", "connect");

    /**
     * @var array
     */
    private static $encoders = array(
        'application/json' => 'json_encode',
        'text/plain' => 'Api::PlainTextEncoder'
    );

    /**
     * Process URI and HTTP Request Headers, and route the request.
     */
    private static function AttendRequest()
    {
        // Take into account the relative location of "index.php" to the domain root
        /* Parse out query string as an argument: /{0,1}((/?\?|/?\#)([^\s])*)?$ */
        $uri = preg_replace('/\/{0,1}((\/?\?|\/?\#)([^\s])*)?$/', '', substr($_SERVER["REQUEST_URI"], strlen(substr($_SERVER["PHP_SELF"], 0, -9))));
        $uri = explode("/", $uri);
        $http_method = strtolower($_SERVER["REQUEST_METHOD"]);
        $format = strpos($_SERVER["HTTP_ACCEPT"], ",")
            ? explode(",", $_SERVER["HTTP_ACCEPT"])[0]
            : $_SERVER["HTTP_ACCEPT"];

        if (count($uri) < 2) {
            self::FailRequest();
        }

        $serviceName = self::GetServiceBySlug($uri[0]);
        $method = self::GetMethodBySlug($serviceName, $uri[1], $http_method);
        $serviceName .= 'Service';

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

        if (self::$enableGzip && strpos($_SERVER["HTTP_ACCEPT_ENCODING"], 'gzip') !== false) {
            ob_start(function ($buffer, $mode) {
                return ob_get_length() >= 860 ? ob_gzhandler($buffer, $mode) : $buffer;
            });
        }
        echo $response;
        ob_end_flush();
    }

    /**
     * Gather the service based on the resource requested.
     * @param string $slug
     * @return string
     */
    private static function GetServiceBySlug($slug)
    {
        if (count(self::$services) > 0) {
            foreach(self::$services as $key => $value) {
                if ((strcasecmp($slug, end(explode("\\", $key))) == 0))
                    return $key;
            }
        }

        self::FailRequest();
    }

    /**
     * Gather the method to route the request to based on the Service Class,
     * URI, and HTTP Request Method.
     * @param string $serviceName
     * @param string $resourceSlug
     * @param string $verb
     * @return string
     */
    private static function GetMethodBySlug($serviceName, $resourceSlug, $verb)
    {
        $service = self::$services[$serviceName];
        if (!isset($service[$resourceSlug][$verb])) {
            self::FailRequest();
        }
        return $service[$resourceSlug][$verb];
    }

    /**
     * Gather internal methods for a given class
     * @param string $className
     * @return \ReflectionMethod[]
     */
    private static function GetServiceMethods($className)
    {
        return (new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC);
    }

    /**
     * Gather HTTP Request headers from the client
     * @return string[]
     */
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

    /**
     * Internal method to register a service class for Comforter to route
     * @param string $name
     */
    private static function RegisterSingleService($name)
    {
        $pattern = (self::$serviceNamespace !== null) ? '/^' . self::$serviceNamespace . '\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*Service$/': '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*Service$/';
        $namespace = (self::$serviceNamespace !== null) ? self::$serviceNamespace . "\\": '';
        if (preg_match($pattern, $name) === 1) {
            $serviceName = $name;
        } else if (in_array($namespace . $name . "Service", get_declared_classes())) {
            $serviceName = $namespace . $name . "Service";
        } else {
            return;
        }

        $newService = array();
        foreach (self::GetServiceMethods($serviceName) as $method) {
            foreach (self::$verbs as $http_method) {
                if (stripos($method->name, $http_method) === 0) {
                    $newService[strtolower(str_replace($http_method, "", $method->name))][$http_method] = $method->name;
                    break;
                }
            }
        }

        $slug = str_replace("Service", "", $serviceName);
        self::$services[$slug] = $newService;
    }

    /**
     * Send Bad Request HTTP Response and kill execution.
     */
    private static function FailRequest()
    {
        header("HTTP/1.1 400 Bad Request");
        exit;
    }

    /**
     * Initializer
     */
    public static function Start()
    {
        if (self::$autoRegister) {
            /* ^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\]*Service$ */
            $pattern = (self::$serviceNamespace !== null) ? '/^' . self::$serviceNamespace . '\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*Service$/': '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*Service$/';
            foreach (array_filter(get_declared_classes(), function ($c) use ($pattern) {
                return preg_match($pattern, $c) === 1;
            }) as $service) {
                self::RegisterSingleService($service);
            }
        }

        self::AttendRequest();
    }

    /**
     * Stub method to send plain text unencoded responses.
     * @param string $data
     * @return string
     */
    public static function PlainTextEncoder($data)
    {
        return $data;
    }

    /**
     * Register a callback to handle a requests for the specified MIME type.
     * @param string $serviceName
     * @see Comforter::RegisterSingleService
     */
    public static function RegisterService($serviceName)
    {
        if (is_array($serviceName)) {
            foreach ($serviceName as $sn) {
                self::RegisterSingleService($sn);
            }
        }
        self::RegisterSingleService($serviceName);
    }

    /**
     * Register a callback to handle a requests for the specified MIME type.
     * @param string $mimeType
     * @param string $callbackName
     */
    public static function RegisterEncoder($mimeType, $callbackName)
    {
        if ($callbackName === null) {
            unset(self::$encoders[$mimeType]);
        }
        self::$encoders[$mimeType] = $callbackName;
    }

    /**
     * Add a non-standard HTTP Request Method to hook for services
     * @param string $string
     */
    public static function RegisterHttpRequestMethod($string)
    {
        self::$verbs[] = $string;
    }

    /**
     * Set Comforter to automatically scan and register valid service classes.
     * @param bool $bool
     * @throws ComforterErrorException
     */
    public static function UseAutoRegisterService($bool)
    {
        if (is_bool($bool)) {
            self::$autoRegister = $bool;
        }
        throw new ComforterErrorException('Setting must be boolean.');
    }

    /**
     * Set Comforter to use gzip compression on responses when possible.
     * @param bool $bool
     * @throws ComforterErrorException
     */
    public static function UseGzipCompression($bool)
    {
        if (is_bool($bool)) {
            self::$enableGzip = $bool;
        }
        throw new ComforterErrorException('Setting must be boolean.');
    }

    /**
     * Set Service Namespace
     *
     * When using the Auto Register feature, Comforter scans the whole space of
     * declared classes. Setting the Service Namespace limits the search scope
     * to just that space.
     * @param string $namespace
     * @throws ComforterErrorException
     */
    public static function UseServiceNamespace($namespace)
    {
        if ($namespace === null) {
            self::$serviceNamespace = null;
            return;
        }

        $exists = function ($name) {
            $name .= "\\";
            foreach(get_declared_classes() as $class)
                if(strpos($class, $name) === 0) return true;
            return false;
        };

        if ($exists($namespace)) {
            self::$serviceNamespace = $namespace;
            return;
        }
        throw new ComforterErrorException("Namespace '$namespace' is not defined.");
    }

    /**
     * Set the default enconder for response data.
     * @param string $encoder
     * @throws ComforterErrorException
     */
    public static function SetDefaultEncoder($encoder)
    {
        if ((isset(self::$encoders[$encoder]))) {
            self::$defaultEncoder = $encoder;
        }
        throw new ComforterErrorException("MIME Type '$encoder' does not have a registered encoder.");
    }
}

/**
 * ComforterErrorException
 *
 * Comforter-specific exception for handling.
 * @package Comforter
 * @author  Gowon Patterson
 * @since   1.0.0
 */
class ComforterErrorException extends \Exception {}

/**
 * AbstractServiceClass
 *
 * Skeleton class with helper methods to bootstrap the development of service classes.
 * @package Comforter
 * @author  Gowon Patterson
 * @since   1.0.0
 */
abstract class AbstractServiceClass
{
    /**
     * Set a key:value paired HTTP Header.
     * @param string|array $key
     * @param string $value
     */
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

    /**
     * Send an HTTP Response Header.
     * @param  int|string $code Code number or string to create non-standard response
     * @throws ComforterErrorException
     */
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
            throw new ComforterErrorException("Unsupported response code or object type '$code' was given.");
        }
    }
}