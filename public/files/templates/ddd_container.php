<?php
/**
 * $_comment
 *
 * @category   Maestro
 * @package $_package
 * @copyright  Copyright (c) 2003-2017 UFJF (http://www.ufjf.br)
 * @license    http://siga.ufjf.br/license
 */

$builder = new \DI\ContainerBuilder();
$builder->addDefinitions(require 'injections.php');
$container = $builder->build();
return $container;