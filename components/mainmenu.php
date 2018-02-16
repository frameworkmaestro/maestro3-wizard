<?php

class MainMenu extends MTree {

    public function __construct(){
        parent::__construct('wizardMainMenu');
        
        $array = array(
            array(0, 'Wizard', 'main/main', 'root'),
            array(1, 'CRUD', 'main/formCRUD', 0),
        );

        $this->setItemsFromArray($array);

    }
}
?>
