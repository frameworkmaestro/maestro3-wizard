<?php
/**
 * $_comment
 *
 * @category   Maestro
 * @package $_package
 * @copyright  Copyright (c) 2003-2017 UFJF (http://www.ufjf.br)
 * @license    http://siga.ufjf.br/license
 */

namespace $_module\persistence\redis\repositories;

use $_module\contracts\repository\$_classOInterface as RepositoryInterface;

class $_classO extends \MBaseRepository implements RepositoryInterface
{
    private $redis;

    public function __construct(\MRedis $redis)
    {
        parent::__construct('redis');
        $this->redis = $redis->getRedis();
    }

$_services
}

