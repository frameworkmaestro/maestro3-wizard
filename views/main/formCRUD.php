<?php

class formCRUD extends MFormBase {

    public function __construct(){
        parent::__construct('CRUD Wizard', '>wizard/main');
    }

    public function createFields() {

        $fields = array(
            new MTextField('moduleName','','Módulo'),
            new MTextField('modelName','','Modelo'),
            new MTextField('lookupName','','Campo de Descrição'),
            new MMultiLineField('definition','','ORMMap', 10, 100)
        );
        $this->setFields($fields);

        $buttons = array(
           new MButton('btnPost','Enviar','@wizard/main/createCRUD')
        );
        $this->setButtons($buttons);

    }

}
?>
