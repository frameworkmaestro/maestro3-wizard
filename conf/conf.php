<?php

return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'Maestro3 Wizard',
    'import' => array(
        'models.*'
    ),
    'options' => array(
        //'basePath' => Manager::getHome() . '/apps/wizard/public/files',
        'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '../public/files',
        'templateEngine' => 'latte'
    ),
    'theme' => array(
        'name' => 'wizard',
        'js' => 'easyui',
        'template' => 'index'
    ),
    'login' => array(
        'module' => "",
        'class' => "MAuthDbMd5",
        'check' => false
    ),
    'db' => array(
    ),
);
