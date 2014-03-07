<?php
namespace Yasunde;

class Yasunde
{
    private static $services;

    private static $settings = array(
        "auto_register" => true,
        "verbs" => array("options", "get", "head", "post", "put", "delete", "trace", "connect")
    );

    private static function failRequest()
    {
        header("HTTP/1.1 400 Bad Request");
        exit;
    }

    private static function attendRequest()
    {
        // take into account the relative location of "index.php" to the domain root
        $uri = explode("/", substr($_SERVER["REQUEST_URI"], strlen(substr($_SERVER["PHP_SELF"], 0, -9))));
        $http_method = strtolower($_SERVER["REQUEST_METHOD"]);
        $format = strpos($_SERVER["HTTP_ACCEPT"], ",")
            ? explode(",", $_SERVER["HTTP_ACCEPT"])[0]
            : $_SERVER["HTTP_ACCEPT"];

        $service = self::getServiceBySlug($uri[0]);
        $method = self::getMethodBySlug($service, $uri[1], $http_method);
        $serviceName = ucfirst($uri[0]) . 'Service';
        $data = $serviceName::{$method}(array(
            "request" => $_REQUEST,
            "args" => array_slice($uri, 2)
        ));

        switch ($format) {
            case "text/plain":
                $response = $data;
                break;
            case "application/json":
            default:
                $response = json_encode($data);
                break;
        }

        header("Content-Type: $format");
        header("Content-Length: " . strlen($response));
        echo $response;
    }

    private static function getServiceBySlug($slug)
    {
        if (count(self::$services) == 0 || !isset(self::$services[$slug])) {
            self::failRequest();
        }
        return self::$services[$slug];
    }

    private static function getMethodBySlug($service, $resource_slug, $verb)
    {
        if (!isset($service[$resource_slug][$verb])) {
            self::failRequest();
        }
        return $service[$resource_slug][$verb];
    }

    public static function go()
    {
        if (self::$settings["auto_register"]) {
            foreach (array_filter(get_declared_classes(), function ($c) {
                return strrpos($c, "Service") !== false;
            }) as $service) {
                self::registerSingleService($service);
            }
        }
        self::attendRequest();
    }

    private static function getServiceMethods($class_name)
    {
        return (new \ReflectionClass($class_name))->getMethods(\ReflectionMethod::IS_PUBLIC);
    }

    private static function registerSingleService($name)
    {
        $newService = array();
        if (strrpos($name, "Service") !== false) {
            $serviceName = $name;
        } else if (in_array($name . "Service", get_declared_classes())) {
            $serviceName = $name . "Service";
        } else {
            return;
        }

        $slug = strtolower(str_replace("Service", "", $serviceName));
        foreach (self::getServiceMethods($serviceName) as $method) {
            foreach (self::$settings["verbs"] as $http_method) {
                if (stripos($method->name, $http_method) === 0) {
                    $newService[strtolower(str_replace($http_method, "", $method->name))][$http_method] = $method->name;
                    break;
                }
            }
        }

        self::$services[$slug] = $newService;
    }

    public static function registerService($service_name)
    {
        if (is_array($service_name)) {
            foreach ($service_name as $sn) {
                self::registerSingleService($sn);
            }
        }
        self::registerSingleService($service_name);
    }

}

abstract class AbstractService {

    public function FailRequest()
    {
        header("HTTP/1.1 400 Bad Request");
        exit;
    }
}
