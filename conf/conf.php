<?php

return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'Maestro Wizard',
    'import' => array(
        'models.*'
    ),
    'options' => array(
        //'basePath' => Manager::getHome() . '/apps/wizard/public/files',
        'basePath' => '/home/ematos/public_html/maestro/apps/wizard/public/files',
        'templateEngine' => 'latte'
    ),
    'theme' => array(
        'name' => 'wizard',
        'js' => 'easyui'
    ),
    'login' => array(
        'module' => "",
        'class' => "MAuthDbMd5",
        'check' => false
    ),
    'db' => array(
    ),
);