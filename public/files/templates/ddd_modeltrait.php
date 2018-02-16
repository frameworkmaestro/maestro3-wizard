<?php
/**
 * $_comment
 *
 * @category   Maestro
 * @package $_package
 * @copyright  Copyright (c) 2003-2017 UFJF (http://www.ufjf.br)
 * @license    http://siga.ufjf.br/license
 */

namespace $_module\persistence\maestro\models;

trait  ModelTrait
{
    private $_map;

    public function __construct($data = NULL) {
        parent::__construct($data);
        $this->_map = new \MBusinessModel(null, $this);
    }

    public function __call($name, $arguments) {
        mdump('Calling on Map: ' . $name);
        if (is_callable(array($this->_map, $name))) {
            return $this->_map->$name($arguments[0], $arguments[1], $arguments[2], $arguments[3],null);
        }
        throw new \BadMethodCallException("Method [{$name}] doesn't exists in " . get_class($this) . " class.");
    }

    public function getAssociation($association) {
        if (is_null($this->$association) || !count($this->$association)){
            $this->_map->retrieveAssociation($association);
        }
        return $this->$association;
    }

}