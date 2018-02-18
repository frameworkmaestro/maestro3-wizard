<?php
/**
 * $_comment
 *
 * @category   Maestro
 * @package $_package
 * @copyright  Copyright (c) 2003-2017 UFJF (http://www.ufjf.br)
 * @license    http://siga.ufjf.br/license
 */

namespace $_module\persistence\maestro\repositories;

use $_module\contracts\repository\$_classOInterface as RepositoryInterface;
$_uses

class $_classO extends \MBaseRepository implements RepositoryInterface
{
    public function __construct()
    {
        $persistence = \Manager::getOptions('persistence');
        parent::__construct($persistence);
    }

$_services
}

