<?php
/**
 * $_comment
 *
 * @category   Maestro
 * @package $_package
 * @copyright  Copyright (c) 2003-2017 UFJF (http://www.ufjf.br)
 * @license    http://siga.ufjf.br/license
 */

namespace $_module\services;

class BaseService extends \MTransactionalService
{
    public function __construct()
    {
        parent::__construct("$_db");
    }

    public function __invoke($parameters) {
        return $this->execute($parameters);
    }

    public function init() {
        // not checking login
    }
}
