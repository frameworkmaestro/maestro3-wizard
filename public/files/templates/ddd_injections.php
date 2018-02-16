<?php
/**
 * $_comment
 *
 * @category   Maestro
 * @package $_package
 * @copyright  Copyright (c) 2003-2017 UFJF (http://www.ufjf.br)
 * @license    http://siga.ufjf.br/license
 */

return [
    '$_module\contracts\repository\\*RepositoryInterface' => function (\DI\Container $c, \DI\Factory\RequestedEntry $entry) {
        $persistence = 'maestro';
        if ($persistence == 'maestro') {
            $name = $entry->getName();
            $reflection = new ReflectionClass($name);
            $shortName = $reflection->getShortName();
            $class = "$_module\\persistence\\maestro\\repositories\\" . str_replace("Interface", '', $shortName);
            return new $class();
        } elseif ($persistence == 'redis') {
            $name = $entry->getName();
            $reflection = new ReflectionClass($name);
            $shortName = $reflection->getShortName();
            $class = "$_module\\persistence\\redis\\repositories\\" . str_replace("Interface", '', $shortName);
            $redis = \MRedis::getInstance();
            return new $class($redis);
        } else {
            //return new $class;
        }
    },
    '$_module\services\\*' => function (\DI\Container $c, \DI\Factory\RequestedEntry $entry) {
        $class = $entry->getName();
        $reflection = new ReflectionClass($class);
        $params = $reflection->getConstructor()->getParameters();
        $constructor = array();
        foreach ($params as $param) {
            $constructor[] = $c->get($param->getClass()->getName());
        }
        return new $class(...$constructor);
    },
    '$_module\controllers\\*' => function (\DI\Container $c, \DI\Factory\RequestedEntry $entry) {
        $class = $entry->getName();
        $controller = new $class();
        $reflection = new ReflectionClass($class);
        $params = $reflection->getMethod('services')->getParameters();
        $services = array();
        foreach ($params as $param) {
            $services[] = $c->get($param->getClass()->getName());
        }
        $controller->services(... $services);
        return $controller;
    },

];