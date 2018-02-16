<?php

class formCSS extends MFormBase {

    public function __construct(){
        parent::__construct('Script Wizard', '>wizard/main');
    }

    public function createFields() {

        $fields = array(
            new MTextField('app', '', 'App name', '30'),
        );
        $this->setFields($fields);

        $buttons = array(
           new MButton('btnPost','Generate','@wizard/main/createCSS')
        );
        $this->setButtons($buttons);

    }
   
}
?>
