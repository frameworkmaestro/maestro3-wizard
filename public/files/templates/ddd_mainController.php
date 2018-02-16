<?php
/**
 * $_comment
 *
 * @category   Maestro
 * @package $_package
 * @copyright  Copyright (c) 2003-2017 UFJF (http://www.ufjf.br)
 * @license    http://siga.ufjf.br/license
 */

namespace $_module\controllers;

class MainController extends \MController {

    public function init() {
        // Manager::checkLogin();
    }

    public function main() {
        //Manager::checkAccess('???', A_ACCESS, true);
        $this->render();
    }

}
