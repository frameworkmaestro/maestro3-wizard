<?php

class MWizardScriptDDD
{

    public $fileScript;
    public $baseDir;
    public $errors;
    public $ini;
    private $className;
    private $moduleName;
    private $databaseName;
    public $generatedMaps;
    private $baseService;
    private $data;

    public function setBaseDir($dir)
    {
        $this->baseDir = $dir;
    }

    public function setFile($file)
    {
        $this->fileScript = $file;
    }

    public function generate()
    {
        $this->errors = array();
        mdump('file script: ' . $this->fileScript);
        $this->ini = parse_ini_file($this->fileScript, true);
        $tab = '    ';
        $dbName = $this->ini['globals']['database'];
        $appName = $this->ini['globals']['app'];
        $moduleName = $this->ini['globals']['module'] ?: $appName;
        $actions[] = $tab . "'{$moduleName}' => ['{$moduleName}', '{$moduleName}/main/main', '{$moduleName}IconForm', '', A_ACCESS, [";
        $this->baseService = false;

        $controllers = [];
        $repository = [];
        $application = [];
        $domain = [];
        $actions = [];

        foreach ($this->ini as $className => $node) {
            if ($node['type'] == 'controller') {
                if (is_array($node['includes'])) {
                    foreach ($node['includes'] as $actionName => $actionData) {
                        $controllers[$className][$actionName] = $this->ini[$actionName . 'Action'];
                    }
                }
            }
            if ($node['type'] == 'service') {
                if ($node['typeSystem'] == 'application') {
                    $application[$className] = $node;
                }
                if ($node['typeSystem'] == 'domain') {
                    $domain[$className] = $node;
                }
            }
            if ($node['type'] == 'repository') {
                $repository[$className] = $node;
            }
        }


        foreach ($this->ini as $className => $node) {
            $originalClassName = $className;
            $className = strtolower($className);
            if ($className == 'globals')
                continue;
            $properties = $propertiesPersistence = $methods = $validators = '';
            $extends = $node['extends'];
            $log = $node['log'];

            if ($node['type'] == 'enumeration') {
                mdump('handleEnumeration = ' . $className);
                $consts = $modelName = $tableName = $properties = '';
                $attributes = $node['attributes'];
                foreach ($attributes as $attributeName => $attributeData) {
                    if ($attributeName == 'model') {
                        $modelName = $attributeData;
                    }
                    if ($attributeName == 'table') {
                        $tableName = $attributeData;
                    }
                    if (($attributeName == 'model') || ($attributeName == 'table')) {
                        $attributeData = "\"{$attributeData}\"";
                    }
                    $properties .= "\n    protected static \$" . $attributeName . " = " . $attributeData . ";";
                }

                if ($tableName) {
                    $sessionId = Manager::getSession()->getId();
                    $url = Manager::getAppURL($appName, $moduleName . '/tabelageral/getenumeration/' . $tableName . "?ajaxResponseType=JSON", true);
                    //mdump($url);
                    if ($stream = fopen($url, 'r')) {
                        $result = MJSON::decode(stream_get_contents($stream));
                        $constants = $result['data']['result']['items'];
                        //mdump($constants);
                        foreach ($constants as $value) {
                            $consts .= "\n    const " . str_replace(' ', '_', $value['name']) . " = " . $value['idTable'] . ";";
                        }
                        fclose($stream);
                    }
                } else {
                    $constants = $node['constants'];
                    foreach ($constants as $constantName => $constantData) {
                        $consts .= "\n    const " . $constantName . " = " . $constantData . ";";
                    }
                }

                $var = array();
                $var['class'] = $className;
                $var['originalClass'] = $originalClassName;
                $var['model'] = $className;
                $var['module'] = $moduleName ?: $appName;
                $var['moduleName'] = $moduleName;
                $var['default'] = $node['default'] ?: 'DEFAULT';
                $var['constants'] = $consts;
                $var['properties'] = $properties;
                $var['comment'] = $comment;
                $var['package'] = $appName;
                $var['extends'] = $extends ?: '\MEnumBase';
                $var['description'] = $description;
                $this->generateEnumeration($originalClassName, $className, $var);
                continue;
            }


            if ($node['type'] == 'model') {
                mdump('handleModel = ' . $className);
                $document = $ormmap = $docassoc = $docattr = $attributes = array();
                $document[] = '';
                $document[] = $tab . 'public static function ORMMap() {';
                $document[] = '';
                $ormmap[] = $tab . $tab . 'return [';
                $ormmap[] = $tab . $tab . $tab . "'class' => \get_called_class(),";
                $ormmap[] = $tab . $tab . $tab . "'database' => " . (substr($dbName, 0, 1) == "\\" ? $dbName . ',' : "'{$dbName}',");
                $tableName = $node['table'];
                $ormmap[] = $tab . $tab . $tab . "'table' => '{$tableName}',";
                if ($extends) {
                    $ormmap[] = $tab . $tab . $tab . "'extends' => '{$extends}',";
                }

                $pk = '';
                $getterSetter = "\n\n    /**\n     * Getters/Setters\n     */";
                $getterSetterPersistence = "\n\n    /**\n     * Getters/Setters Persistence\n     */";
                $modelOperations = '';
                $initAttr = '';
                $attributes = $node['attributes'];
                foreach ($attributes as $attributeName => $attributeData) {
                    $isPK = $isFK = false;
                    $at = explode(',', $attributeData);
                    // atData:
                    // 0 - column
                    // 1 - type
                    // 2 - null or not null
                    // 3 - key type
                    // 4 - generator
                    $attribute = $tab . $tab . $tab . "'{$attributeName}' => [";
                    $attribute .= "'column' => '{$at[0]}'";
                    if ($at[3]) {
                        $attribute .= ",'key' => '{$at[3]}'";
                        $isPK = $at[3] == 'primary';
                        $isFK = $at[3] == 'foreign';
                        if ($isPK) {
                            $pk = $attributeName;
                            if ($at[4]) {
                                $attribute .= ",'idgenerator' => '{$at[4]}'";
                            } else {
                                $attribute .= ",'idgenerator' => 'identity'";
                            }
                        }
                    }
                    if (($at[2] == 'not null') && (!$isPK)) {
                        $validators .= "\n    " . $tab . $tab . $tab . "'{$attributeName}' => ['notnull'],";
                    }
                    $attrType = $at[1];
                    $attribute .= ",'type' => '{$attrType}'],";

                    $ucAttributeName = ucfirst($attributeName);

                    $setterBody = '';
                    $lowerAttrType = strtolower($attrType);
                    if ($lowerAttrType == 'currency') {
                        $setterBody = "if (!(\$value instanceof \\MCurrency)) {\n            \$value = new \\MCurrency((float) \$value);\n        }\n        ";
                        $initAttr .= "        \$this->{$attributeName} = new \\MCurrency((float) 0);\n";
                    } elseif ($lowerAttrType == 'date') {
                        $setterBody = "if (!(\$value instanceof \\MDate)) {\n            \$value = new \\MDate(\$value);\n        }\n        ";
                        $initAttr .= "        \$this->{$attributeName} = new \\MDate('00/00/0000');\n";
                    } elseif ($lowerAttrType == 'timestamp') {
                        $setterBody = "if (!(\$value instanceof \\MTimeStamp)) {\n            \$value = new \\MTimeStamp(\$value);\n        }\n        ";
                        $initAttr .= "        \$this->{$attributeName} = new \\MTimeStamp('00/00/0000 00:00');\n";
                    } elseif ($lowerAttrType == 'cpf') {
                        $setterBody = "if (!(\$value instanceof \\MCPF)) {\n            \$value = new \\MCPF(\$value);\n        }\n        ";
                        $initAttr .= "        \$this->{$attributeName} = new \\MCPF('');\n";
                    } elseif ($lowerAttrType == 'cnpj') {
                        $setterBody = "if (!(\$value instanceof \\MCNPJ)) {\n            \$value = new \\MCNPJ(\$value);\n        }\n        ";
                        $initAttr .= "        \$this->{$attributeName} = new \\MCNPJ('');\n";
                    } elseif ($lowerAttrType == 'boolean') {
                        $setterBody = "\$value = ((\$value != '0') && (\$value != 0) && (\$value != ''));\n        ";
                        $initAttr .= "        \$this->{$attributeName} = false;\n";
                    } elseif (strpos($lowerAttrType, 'enum') !== false) {
                        $setterBody = "\$valid = false;\n" .
                            "        if (empty(\$value)) {\n" .
                            "            \$config = \$this->config();\n" .
                            "            \$valid = !array_search('notnull',\$config['validators']['{$attributeName}']);\n" .
                            "        }\n" .
                            "        if (!(\$valid || {$attrType}Map::isValid(\$value))) {\n" .
                            "            throw new \EModelException('Valor inválido para a Enumeração {$attrType}');\n" .
                            "        }\n        ";
                    } elseif ($lowerAttrType == 'integer') {
                        $initAttr .= "        \$this->{$attributeName} = 0;\n";
                    } elseif ($lowerAttrType == 'string') {
                        $initAttr .= "        \$this->{$attributeName} = '';\n";
                    }

                    if ($isPK) {
                        //$setterBody .= "\$this->{$attributeName} = (\$value ? : NULL);";
                        $setterBody .= "\$this->{$attributeName} = \$value;\n        return \$this;";
                        $this->data[$originalClassName]['pkName'] = $attributeName;
                    } else {
                        $setterBody .= "\$this->{$attributeName} = \$value;\n        return \$this;";
                    }

                    if ($isFK) {
                        $propertiesPersistence .= <<<HERE
    /**
     * {$attrComment}
     * @var {$attrType}
     */
    protected \${$attributeName};

HERE;
                        $getterSetterPersistence .= <<<HERE

    /**
     *
     * @return {$attrType}
     */
     public function get{$ucAttributeName}()
     {
        return \$this->{$attributeName};
     }
    /**
     *
     * @param {$attrType} $value
     */
     public function set{$ucAttributeName}(\$value)
     {
        {$setterBody};
     }

HERE;
                    } else {
                        $properties .= "\n    /**\n     * {$attrComment}\n     * @var {$attrType} \n     */";
                        $properties .= "\n    protected " . "\$" . $attributeName . ";";
                        $getterSetter .= "\n    public function get" . ucfirst($attributeName) . "() {\n        return \$this->{$attributeName};\n    }\n";
                        $getterSetter .= "\n    public function set" . ucfirst($attributeName) . "(\$value) {\n        {$setterBody}\n    }\n";
                    }

                    $docattr[] = $tab . $attribute;

                }

                $description = $node['description'] ?: $pk;
                $this->data[$originalClassName]['description'] = $description;

                //$propertiesPersistence .= "\n\n    /**\n     * Associations\n     */";

                $docassoc = array();
                $associations = $node['associations'];
                if (is_array($associations)) {
                    foreach ($associations as $associationName => $associationData) {
                        $assoc = explode(',', $associationData);
                        // assoc:
                        // 0 - toClass
                        // 1 - cardinality
                        // 2 - keys or associative
                        $association = $tab . $tab . $tab . "'{$associationName}' => [";
                        $association .= "'toClass' => '{$assoc[0]}'";
                        $association .= ", 'cardinality' => '{$assoc[1]}' ";
                        if ($assoc[1] == 'manyToMany') {
                            $association .= ", 'associative' => '{$assoc[2]}'], ";
                        } else {
                            $association .= ", 'keys' => '{$assoc[2]}'], ";
                        }
                        $keys = explode(':', $assoc[2]);
                        $aToClass = explode('\\', $assoc[0]);
                        $toClass = $aToClass[count($aToClass) - 1];

                        $ucAssociationName = ucfirst($associationName);

                        if ($assoc[1] == 'oneToOne') {
                            $type = $toClass;
                            $uKey = ucFirst($keys[1]);
                            $set = "parent::set{$ucAssociationName}(\$value);\n        \$this->set{$keys[0]}(\$value->get{$uKey}());\n        ";
                            $initAttr .= "        \$this->{$associationName} = null;\n";
                        } else {
                            $type = "Association [{$toClass}]";
                            $set = null;
                            $initAttr .= "        \$this->{$associationName} = new \\ArrayObject([]);\n";
                        }

                        $properties .= "\n    /**\n     * {$attrComment}\n     * @var {$type} \n     */";
                        $properties .= "\n    protected " . "\$" . $associationName . ";";
                        $getterSetter .= "\n    public function get" . $ucAssociationName . "() {\n        return \$this->{$associationName};\n    }\n";
                        $getterSetter .= "\n    public function set" . $ucAssociationName . "(\$value) {\n        \$this->{$associationName} = \$value;\n    }\n";

                        $propertiesPersistence .= "\n    /**\n     * {$attrComment}\n     * @var {$type} \n     */";
                        $propertiesPersistence .= "\n    protected " . "\$" . $associationName . ";";


                        $methods .= <<<HERE
    /**
     *
     * @return {$type}
     */
     public function get{$ucAssociationName}()
     {
        return \$this->getAssociation("{$associationName}");
     }
    /**
     *
     * @param {$type} \$value
     */
     public function set{$ucAssociationName}(\$value)
     {
        {$set}\$this->{$associationName} = \$value;
        return \$this;
     }

HERE;
                        $docassoc[] = $tab . $association;
                    }
                }

                $construct = <<<HERE
/**
    *  Construct
    */
    public function __construct() {
        parent::__construct();
{$initAttr}    }
HERE;
                $operations = $node['operations'];
                $modelOperationsPersistence = '';
                if (is_array($operations)) {
                    foreach ($operations as $operationName => $operationData) {
                        $opData = explode(',', $operationData);
                        $parameters = '';
                        $parametersVar = '';
                        $return = array_shift($opData);
                        $comment = array_shift($opData);
                        if ($comment != '') {
                            $comment = base64_decode($comment);
                        }
                        if (count($opData)) {
                            //$param = array_slice($opData, 1);
                            $parameters = implode(',', $opData);
                            foreach($opData as $p) {
                                $v = explode(' ', $p);
                                $parametersVar .= $v[1];
                            }
                        }
                        $modelOperations .= <<<HERE
    /**
     * {$comment}
     * @return {$return}
     */
     public function {$operationName}({$parameters})
     {
     }

HERE;

                        $modelOperationsPersistence .= <<<HERE
    /**
     * {$comment}
     * @return {$return}
     */
     public function {$operationName}({$parameters})
     {
        return parent::{$operationName}({$parametersVar});
     }

HERE;
                    }
                }

                $ormmap[] = $tab . $tab . $tab . "'attributes' => [";
                foreach ($docattr as $attr) {
                    $ormmap[] = $attr;
                }
                $ormmap[] = $tab . $tab . $tab . "],";
                $ormmap[] = $tab . $tab . $tab . "'associations' => [";
                foreach ($docassoc as $assoc) {
                    $ormmap[] = $assoc;
                }
                $ormmap[] = $tab . $tab . $tab . "]";
                $ormmap[] = $tab . $tab . "];";

                $ormmapdef = implode("\n", $ormmap);
                $this->generatedMaps[$originalClassName] = $ormmapdef;

                $document[] = $ormmapdef;
                $document[] = $tab . "}";

                $map = implode("\n", $document);
                $configLog = "[ " . $log . " ],";
                $configValidators = "[" . $validators . "\n            ],";
                $configConverters = "[]";


                // generate PHP class
                $var = array();
                $var['class'] = $className;
                $var['originalClass'] = $originalClassName;
                $var['model'] = $className;
                $var['module'] = $moduleName ?: $appName;
                $var['properties'] = $properties;
                $var['propertiesPersistence'] = $propertiesPersistence;
                $var['methods'] = $construct . $getterSetter . $modelOperations;
                $var['methodsPersistence'] = $getterSetterPersistence . $methods . $modelOperationsPersistence;
                $var['comment'] = $comment;
                $var['package'] = $appName;
                $var['ormmap'] = $map;
                $var['extends'] = $extends;
                $var['description'] = $description;
                $var['pkName'] = $this->data[$originalClassName]['pkName'];
                $var['lookup'] = $description;
                $var['configLog'] = $configLog;
                $var['configValidators'] = $configValidators;
                $var['configConverters'] = $configConverters;

                // Create Models & Map

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_model.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/models/{$originalClassName}.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_model_persistence.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/models/{$originalClassName}.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_modeltrait.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/models/ModelTrait.php", $this->baseDir);

                /*
                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_modelmaptrait.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/ModelMapTrait.php", $this->baseDir);
                */

                // define actions
                $upperClass = ucFirst($className);
                $actions[] = $tab . $tab . "'{$className}' => ['{$upperClass}', '{$moduleName}/{$className}/main', '{$moduleName}IconForm', '', A_ACCESS, []],";
            }
        }

        // controllers

        foreach ($controllers as $controllerName => $actions) {
            mdump('handleController = ' . $controllerName);
            $node = $this->ini[$controllerName];
            $name = strtolower($node['name']);
            $actionMethods = '';
            foreach ($actions as $action) {
                $actionName = lcfirst($action['name']);
                $services = '';
                $command = [];
                if (is_array($action['includes'])) {
                    foreach ($action['includes'] as $service) {
                        $s = explode(',', $service);
                        $v = lcfirst($s[1]);
                        $command[] = "        \${$v} = \\Manager::getService('','{$moduleName}', '{$s[3]}\\{$s[0]}\\{$s[1]}');";
                        $command[] = "        \${$v}();";
                    }
                }
                $commands = implode("\n", $command);
                $actionMethods .= <<<HERE

    public function {$actionName}()
    {
{$commands}
    }

HERE;

            }

            $var = array();
            $var['originalClass'] = $node['name'];
            $var['actions'] = $actionMethods;
            $var['module'] = $moduleName ?: $appName;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_controller.php');
            $template->applyClass();
            $template->saveResult("{$moduleName}/src/controllers/{$name}Controller.php", $this->baseDir);
        }

        // services

        $var = array();
        $var['module'] = $moduleName ?: $appName;
        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('public/files/templates/ddd_service_base.php');
        $template->apply();
        $template->saveResult("{$moduleName}/src/services/BaseService.php", $this->baseDir);

        foreach ($application as $applicationName => $node) {
            mdump('handleApplication = ' . $applicationName);
            $system = $node['system'];
            $name = $node['name'];
            // includes
            $i = $j = 0;
            $systems = [];
            $includes = $node['includes'];
            $servicesAttributes = $servicesParameters = $servicesSet = "";
            $i = 0;
            if (is_array($includes)) {
                foreach ($includes as $includeStr) {
                    $include = explode(',', $includeStr); // 0-system, 1-service,  2-'service'
                    $attribute = lcFirst($include[0]) . $include[1];
                    $servicesParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\services\\" . $include[3] . "\\" . $include[0] . "\\" . $include[1] . "Service " . "\$" . $attribute;
                    $servicesSet .= ($i > 0 ? "\n        " : "") . "\$this->" . $attribute . " = \$" . $attribute . ";";
                    $servicesAttributes .= "\n    protected \$" . $attribute . ";";
                    ++$i;
                }
            }

            $var = array();
            $var['model'] = lcfirst($model) ?: lcfirst($system);
            $var['system'] = $system;
            $var['service'] = $name;
            $var['originalClass'] = $system . $name;
            $var['lookup'] = $description;
            $var['module'] = $moduleName ?: $appName;
            $var['servicesAttributes'] = $servicesAttributes;
            $var['servicesParameters'] = $servicesParameters;
            $var['servicesSet'] = $servicesSet;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_application_service.php');
            $template->apply();
            $template->saveResult("{$moduleName}/src/services/application/{$system}/{$name}Service.php", $this->baseDir);
        }

        foreach ($domain as $domainName => $node) {
            mdump('handleDomain = ' . $domainName);
            $system = $node['system'];
            $name = $node['name'];
            // includes
            $i = $j = 0;
            $includes = $node['includes'];
            $servicesAttributes = $servicesParameters = $servicesSet = "";
            if (is_array($includes)) {
                foreach ($includes as $includeStr) {
                    $include = explode(',', $includeStr); // 0-system, 1-service,  2-'service'
                    $attribute = lcFirst($include[0]) . $include[1];
                    $servicesParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\services\\" . $include[3] . "\\" . $include[0] . "\\" . $include[1] . "Service " . "\$" . $attribute;
                    $servicesSet .= ($i > 0 ? "\n        " : "") . "\$this->" . $attribute . " = \$" . $attribute . ";";
                    $servicesAttributes .= "\n    protected \$" . $attribute . ";";
                    ++$i;
                }
            }
            $i = $j = 0;
            $references = $node['references'];
            $model = $reposAttributes = $reposParameters = $reposSet = "";
            $atomic = true;
            if (is_array($references)) {
                foreach ($references as $reference) {
                    $referenceNode = $this->ini[$reference];
                    if ($referenceNode['type'] == 'repository') {
                        $reposParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\contracts\\repository\\" . $reference . "Interface \$" . lcFirst($reference);
                        $reposSet .= ($i > 0 ? "\n        " : "") . "\$this->" . lcFirst($reference) . " = \$" . lcFirst($reference) . ";";
                        $reposAttributes .= "\n    protected \$" . lcFirst($reference) . ";";
                        ++$i;
                    } else {
                        $referenceNode = $this->ini[$reference . 'Class'];
                        mdump($referenceNode);
                        if ($referenceNode['type'] == 'serviceclass') {
                            $atomic = false;
                            $classNode = $referenceNode;//$this->ini[$reference . 'Class'];
                            $operations = $classNode['operations'];
                            if (is_array($operations)) {
                                $domainMethods = '';
                                foreach ($operations as $operationName => $operationData) {
                                    $docParams = "\n";
                                    $opData = explode(',', $operationData);
                                    $parameters = '';
                                    $return = array_shift($opData);
                                    $comment = array_shift($opData);
                                    if ($comment != '') {
                                        $comment = base64_decode($comment);
                                    }
                                    if (count($opData)) {
                                        //$param = array_slice($opData, 1);
                                        $parameters = implode(', ', $opData);
                                        foreach ($opData as $p) {
                                            $docParams .= "     * @param " . $p;
                                        }
                                    }
                                    $domainMethods .= <<<HERE
    /**
     * {$comment}{$docParams}     
     * @return {$return}
     */
     public function {$operationName}({$parameters})
     {
     }

HERE;

                                }
                            }
                        }
                    }
                }
            }

            if ($atomic) {
                $domainMethods = <<<HERE

    public function run(\$parameters = null)
    {

    }

HERE;

            }

            $var = array();
            $var['model'] = lcfirst($model) ?: lcfirst($system);
            $var['system'] = $system;
            $var['service'] = $name;
            $var['originalClass'] = $system . $name;
            $var['lookup'] = $description;
            $var['services'] = $domainMethods;
            $var['module'] = $moduleName ?: $appName;
            $var['servicesAttributes'] = $servicesAttributes;
            $var['servicesParameters'] = $servicesParameters . (($servicesParameters != '') ? ',' : '');
            $var['servicesSet'] = $servicesSet;
            $var['reposAttributes'] = $reposAttributes;
            $var['reposParameters'] = $reposParameters;
            $var['reposSet'] = $reposSet;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_domain_service.php');
            $template->apply();
            $template->saveResult("{$moduleName}/src/services/domain/{$system}/{$name}Service.php", $this->baseDir);
        }

        // repositories

        foreach ($repository as $repositoryName => $node) {
            mdump('handleRepository = ' . $repositoryName);
            $i = $j = 0;
            $operations = $node['operations'];
            if (is_array($operations)) {
                $repositoryContracts = $repositoryMethods = '';
                foreach ($operations as $operationName => $operationData) {
                    $opData = explode(',', $operationData);
                    $parameters = '';
                    $return = array_shift($opData);
                    if (count($opData)) {
                        //$param = array_slice($opData, 1);
                        $parameters = implode(', ', $opData);
                    }

                    $repositoryMethods .= <<<HERE
    /**
     *
     * @return {$return}
     */
     public function {$operationName}({$parameters})
     {
     }

HERE;

                    $repositoryContracts .= <<<HERE
    /**
     *
     * @return {$return}
     */
     public function {$operationName}({$parameters});

HERE;
                }
            }

            $references = $node['references'];
            if (is_array($references)) {
                $repositoryUsesMaestro = '';
                $repositoryUsesRedis = '';
                foreach ($references as $refName => $reference) {
                    $repositoryUsesMaestro .= "use {$moduleName}\\persistence\\maestro\\models\\{$reference};\n";
                    $repositoryUsesRedis .= "use {$moduleName}\\persistence\\maestro\\models\\{$reference};\n";
                }
                mdump($repositoryUsesMaestro);
                mdump($repositoryUsesRedis);
            }

            $var = array();
            $var['model'] = lcfirst($model) ?: lcfirst($system);
            $var['originalClass'] = $repositoryName;
            $var['usesMaestro'] = $repositoryUsesMaestro;
            $var['usesRedis'] = $repositoryUsesRedis;
            $var['services'] = $repositoryMethods;
            $var['contracts'] = $repositoryContracts;
            $var['module'] = $moduleName ?: $appName;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_repository_persistence_maestro.php');
            $template->apply();
            $template->saveResult("{$moduleName}/src/persistence/maestro/repositories/{$repositoryName}.php", $this->baseDir);
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_repository_persistence_redis.php');
            $template->apply();
            $template->saveResult("{$moduleName}/src/persistence/redis/repositories/{$repositoryName}.php", $this->baseDir);
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_repository_interface.php');
            $template->apply();
            $template->saveResult("{$moduleName}/src/contracts/repository/{$repositoryName}Interface.php", $this->baseDir);
        }


        $actions[] = $tab . "]]\n";

        $var['module'] = $moduleName ?: $appName;

// create Actions

        $var['actions'] = implode("\n", $actions);
        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_actions.php');
        $template->applyClass();
        $template->saveResult("{$moduleName}/conf/actions.php", $this->baseDir);


// create Conf
        $template = new MWizardTemplate();
        $template->setTemplate('/public/files/templates/ddd_conf.php');
        $template->applyClass();
        $template->saveResult("{$moduleName}/conf/conf.php", $this->baseDir);

// create Container and Injections
        $template = new MWizardTemplate();
        $template->setTemplate('/public/files/templates/ddd_container.php');
        $template->applyClass();
        $template->saveResult("{$moduleName}/conf/container.php", $this->baseDir);

        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_injections.php');
        $template->applyClass();
        $template->saveResult("{$moduleName}/conf/injections.php", $this->baseDir);

// create Main

        $template = new MWizardTemplate();
        $var['services'] = $services;
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_main.xml');
        $template->applyClass();
        $template->saveResult("{$moduleName}/src/views/main/main.xml", $this->baseDir);

        $template->setTemplate('/public/files/templates/ddd_mainController.php');
        $template->apply();
        $template->saveResult("{$moduleName}/src/controllers/mainController.php", $this->baseDir);
    }

    public
    function generateEnumeration($originalClassName, $className, $var)
    {
        // Create Model & Map
        $moduleName = $var['moduleName'];

        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_enum_model.php');
        $template->applyClass();
        $template->saveResult("{$moduleName}/src/models/{$originalClassName}.php", $this->baseDir);

        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_enum_map.php');
        $template->applyClass();
        $template->saveResult("{$moduleName}/src/persistence/maestro/{$className}/{$originalClassName}Map.php", $this->baseDir);

    }

}
