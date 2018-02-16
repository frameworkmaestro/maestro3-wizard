<?php

class formORM extends MFormBase {

    public function __construct(){
        parent::__construct('ORM Wizard', '>wizard/main/main');
    }

    public function createFields() {

        $fields = array(
            new MTextField('xmi', '', 'XMI File', '30', 'inside files/xmi'),
            new MTextField('module', '', 'Module', '30'),
            new MTextField('database', '', 'Database', '30'),
            new MTextField('package', '', 'Package', '30'),
        );
        $this->setFields($fields);

        $buttons = array(
           new MButton('btnPost','Generate Class/Map Files','@wizard/main/createORM')
        );
        $this->setButtons($buttons);

    }
   
}
?>
