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
        $this->ini = parse_ini_file($this->fileScript, true);
        $tab = '    ';
        $dbName = $this->ini['globals']['database'];
        $appName = $this->ini['globals']['app'];
        $moduleName = $this->ini['globals']['module'] ?: $appName;
        $actions[] = $tab . "'{$moduleName}' => ['{$moduleName}', '{$moduleName}/main/main', '{$moduleName}IconForm', '', A_ACCESS, [";
        $this->baseService = false;

        foreach ($this->ini as $className => $node) {
            $originalClassName = $className;
            $className = strtolower($className);
            if ($className == 'globals')
                continue;
            $properties = $propertiesPersistence = $methods = $validators = '';
            mdump('handleClass = ' . $className);
            $extends = $node['extends'];
            $log = $node['log'];

            if ($node['type'] == 'enumeration') {
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
                    } elseif ($lowerAttrType == 'date') {
                        $setterBody = "if (!(\$value instanceof \\MDate)) {\n            \$value = new \\MDate(\$value);\n        }\n        ";
                    } elseif ($lowerAttrType == 'timestamp') {
                        $setterBody = "if (!(\$value instanceof \\MTimeStamp)) {\n            \$value = new \\MTimeStamp(\$value);\n        }\n        ";
                    } elseif ($lowerAttrType == 'cpf') {
                        $setterBody = "if (!(\$value instanceof \\MCPF)) {\n            \$value = new \\MCPF(\$value);\n        }\n        ";
                    } elseif ($lowerAttrType == 'cnpj') {
                        $setterBody = "if (!(\$value instanceof \\MCNPJ)) {\n            \$value = new \\MCNPJ(\$value);\n        }\n        ";
                    } elseif ($lowerAttrType == 'boolean') {
                        $setterBody = "\$value = ((\$value != '0') && (\$value != 0) && (\$value != ''));\n        ";
                    } elseif (strpos($lowerAttrType, 'enum') !== false) {
                        $setterBody = "\$valid = false;\n" .
                            "        if (empty(\$value)) {\n" .
                            "            \$config = \$this->config();\n" .
                            "            \$valid = !array_search('notnull',\$config['validators']['{$attributeName}']);\n" .
                            "        }\n" .
                            "        if (!(\$valid || {$attrType}Map::isValid(\$value))) {\n" .
                            "            throw new \EModelException('Valor inválido para a Enumeração {$attrType}');\n" .
                            "        }\n        ";
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
                        } else {
                            $type = "Association [{$toClass}]";
                            $set = null;
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
                $var['methods'] = $getterSetter;
                $var['methodsPersistence'] = $getterSetterPersistence . $methods;
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

                // Create Models & Repositories & Map & Service

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_model.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/models/{$originalClassName}.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_readrepositoryinterface.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/contracts/repository/{$originalClassName}ReadRepositoryInterface.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_writerepositoryinterface.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/contracts/repository/{$originalClassName}WriteRepositoryInterface.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_map.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/{$className}/{$originalClassName}Map.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_model_persistence.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/{$className}/{$originalClassName}.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_readrepository_persistence.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/{$className}/{$originalClassName}ReadRepository.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_writerepository_persistence.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/{$className}/{$originalClassName}WriteRepository.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_modeltrait.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/ModelTrait.php", $this->baseDir);

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate('/public/files/templates/ddd_modelmaptrait.php');
                $template->applyClass();
                $template->saveResult("{$moduleName}/src/persistence/maestro/ModelMapTrait.php", $this->baseDir);

                // define actions
                $upperClass = ucFirst($className);
                $actions[] = $tab . $tab . "'{$className}' => ['{$upperClass}', '{$moduleName}/{$className}/main', '{$moduleName}IconForm', '', A_ACCESS, []],";
            }

            if ($node['type'] == 'controller') {
                $system = $node['system'];
                $name = $node['name'];
                $controller = strtolower($system . $name);
                $includes = $node['includes'];
                $systemAttributes = $servicesClasses = $servicesSet = "";
                $i = $j = 0;
                $systems = [];
                foreach ($includes as $includeStr) {
                    $include = explode(',', $includeStr); // 0-system, 1-service,  2-'service'
                    $servicesClasses .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\services\\" . $include[0] . "\\" . $include[1] . "Service " . "\$" . lcFirst($include[0]) . $include[1];
                    $servicesSet .= ($i > 0 ? "\n        " : "") . "\$this->" . lcFirst($include[0]) . "->add('" . lcFirst($include[1]) . "', \$" . lcFirst($include[0]) . $include[1] . ");";
                    if (!$systems[$include[0]]) {
                        $systemAttributes .= "\n    /**\n     *  Services Container\n     */\n    protected \$" . lcFirst($include[0]) . ";";
                        $servicesSet = ($j++ > 0 ? "\n        ": "") . "\$this->" . lcFirst($include[0]) . " = new \MServiceContainer();" . "\n        " . $servicesSet;
                        $systems[$include[0]] = true;
                    }
                    ++$i;
                }
                $services = <<<HERE
    {$systemAttributes}

    public function services(
        {$servicesClasses}
    )
    {
        {$servicesSet}
    }
HERE;

                if (strtoupper($name) == 'CRUD') {
                    // references - get Model
                    $references = $node['references'];
                    $model = "";
                    if (is_array($references)) {
                        foreach ($references as $reference) {
                            $model = $reference;
                        }
                    }

                    // Create CRUD
                    $fileName = array();
                    $fileName[] = array('public/files/templates/ddd_formBase.xml', "{$moduleName}/src/views/{$controller}/formBase.xml");
                    $fileName[] = array('public/files/templates/ddd_formFind.xml', "{$moduleName}/src/views/{$controller}/formFind.xml");
                    $fileName[] = array('public/files/templates/ddd_formNew.xml', "{$moduleName}/src/views/{$controller}/formNew.xml");
                    $fileName[] = array('public/files/templates/ddd_formObject.xml', "{$moduleName}/src/views/{$controller}/formObject.xml");
                    $fileName[] = array('public/files/templates/ddd_formUpdate.xml', "{$moduleName}/src/views/{$controller}/formUpdate.xml");
                    $fileName[] = array('public/files/templates/ddd_lookup.xml', "{$moduleName}/src/views/{$controller}/lookup.xml");
                    $fileName[] = array('public/files/templates/ddd_fields.xml', "{$moduleName}/src/views/{$controller}/fields.xml");
                    $fileName[] = array('public/files/templates/ddd_controller_crud.php', "{$moduleName}/src/controllers/{$controller}Controller.php");
                    $template = new MWizardTemplate();
                    $var = array();
                    $model = $model ?: $system;
                    $description = $this->data[$model]['description'];
                    $var['model'] = lcfirst($model);
                    $var['system'] = $system;
                    $var['originalClass'] = $system . $name;
                    $var['lookup'] = $description;
                    $var['module'] = $moduleName ?: $appName;
                    $var['services'] = $services;
                    $template->setVar($var);

                    $template->setFields(eval(stripslashes($this->generatedMaps[$model])));
                    foreach ($fileName as $f) {
                        $template->setTemplate($f[0]);
                        $template->apply();
                        $template->saveResult($f[1], $this->baseDir);
                    }

                } else {
                    $fileName = array();
                    $fileName[] = array('public/files/templates/ddd_formGeneric.xml', "{$moduleName}/src/views/{$controller}/formGeneric.xml");
                    $fileName[] = array('public/files/templates/ddd_controller.php', "{$moduleName}/src/controllers/{$controller}Controller.php");
                    $template = new MWizardTemplate();
                    $var = array();
                    $var['model'] = lcfirst($system);
                    $var['system'] = $system;
                    $var['originalClass'] = $system . $name;
                    $var['lookup'] = $description;
                    $var['module'] = $moduleName ?: $appName;
                    $var['services'] = $services;
                    $template->setVar($var);

                    foreach ($fileName as $f) {
                        $template->setTemplate($f[0]);
                        $template->apply();
                        $template->saveResult($f[1], $this->baseDir);
                    }
                }
            }

            if ($node['type'] == 'service') {
                $system = $node['system'];
                $name = $node['name'];
                $controller = strtolower($system . $name);
                // references
                $references = $node['references'];
                $model = $reposAttributes = $reposParameters = $reposSet = "";
                $i = $j = 0;
                $systems = [];
                if (is_array($references)) {
                    foreach ($references as $reference) {
                        $reposParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\contracts\\repository\\" . $reference . "ReadRepositoryInterface " . "\$" . lcFirst($reference) . "ReadRepository";
                        $reposSet .= ($i > 0 ? "\n        " : "") . "\$this->" . lcFirst($reference) . "->addReadRepository(" . "\$" . lcFirst($reference) . "ReadRepository" . ");";
                        ++$i;
                        $reposParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\contracts\\repository\\" . $reference . "WriteRepositoryInterface " . "\$" . lcFirst($reference) . "WriteRepository";
                        $reposSet .= ($i > 0 ? "\n        " : "") . "\$this->" . lcFirst($reference) . "->addWriteRepository(" . "\$" . lcFirst($reference) . "WriteRepository" . ");";
                        if (!$systems[$reference]) {
                            $reposAttributes .= "\n    /**\n     *  Container\n     */\n    protected \$" . lcFirst($reference) . ";";
                            $reposSet = ($j++ > 0 ? "\n        ": "") . "\$this->" . lcFirst($reference) . " = new \\MServiceContainer();" . "\n        " . $reposSet;
                            $model = $reference;
                            $systems[$reference] = true;
                        }
                    }
                }
                // includes
                $includes = $node['includes'];
                $servicesAttributes = $servicesParameters = $servicesSet = "";
                $i = 0;
                if (is_array($includes)) {
                    foreach ($includes as $includeStr) {
                        $include = explode(',', $includeStr); // 0-system, 1-service,  2-'service'
                        $servicesParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\services\\" . $include[0] . "\\" . $include[1] . "Service " . "\$" . lcFirst($include[0]) . $include[1];
                        $servicesSet .= ($i > 0 ? "\n        " : "") . "\$this->" . lcFirst($include[0]) . "->add('" . lcFirst($include[1]) . "', \$" . lcFirst($include[0]) . $include[1] . ");";
                        if (!$systems[$include[0]]) {
                            $servicesAttributes .= "\n    /**\n     *  Container\n     */\n    protected \$" . lcFirst($include[0]) . ";";
                            $servicesSet = ($j++ > 0 ? "\n        ": "") . "\$this->" . lcFirst($include[0]) . " = new \\MServiceContainer();" . "\n        " . $servicesSet;
                            $systems[$include[0]] = true;
                        }
                        ++$i;
                    }
                }

                $fileName = array();
                mdump(">>>" . $system . ' - ' . $name);
                if ($name == "Save") {
                    $fileName[] = array('public/files/templates/ddd_service_save.php', "{$moduleName}/src/services/{$system}/{$name}Service.php");
                } elseif ($name == "Delete") {
                    $fileName[] = array('public/files/templates/ddd_service_delete.php', "{$moduleName}/src/services/{$system}/{$name}Service.php");
                } else {
                    $fileName[] = array('public/files/templates/ddd_service.php', "{$moduleName}/src/services/{$system}/{$name}Service.php");
                }

                if (!$this->baseService) {
                    $fileName[] = array('public/files/templates/ddd_service_base.php', "{$moduleName}/src/services/BaseService.php");
                    $this->baseService = true;
                }

                $template = new MWizardTemplate();
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
                $var['reposAttributes'] = $reposAttributes;
                $var['reposParameters'] = ($reposParameters != '') ?  $reposParameters . (($servicesParameters != '') ?  ',' : '') : '';
                $var['reposSet'] = $reposSet;
                $template->setVar($var);
                foreach ($fileName as $f) {
                    $template->setTemplate($f[0]);
                    $template->apply();
                    $template->saveResult($f[1], $this->baseDir);
                }

            }

            if ($node['type'] == 'query') {
                $system = $node['system'];
                $name = $node['name'];
                $controller = strtolower($system . $name);
                // references
                $references = $node['references'];
                $model = $reposAttributes = $reposParameters = $reposSet = "";
                $i = $j = 0;
                $systems = [];
                if (is_array($references)) {
                    foreach ($references as $reference) {
                        $reposParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\contracts\\repository\\" . $reference . "ReadRepositoryInterface " . "\$" . lcFirst($reference) . "ReadRepository";
                        $reposSet .= ($i > 0 ? "\n        " : "") . "\$this->" . lcFirst($reference) . "->addReadRepository(" . "\$" . lcFirst($reference) . "ReadRepository" . ");";
                        ++$i;
                        if (!$systems[$reference]) {
                            $reposAttributes .= "\n    /**\n     *  Container\n     */\n    protected \$" . lcFirst($reference) . ";";
                            $reposSet = ($j++ > 0 ? "\n        ": "") . "\$this->" . lcFirst($reference) . " = new \\MServiceContainer();" . "\n        " . $reposSet;
                            $model = $reference;
                            $systems[$reference] = true;
                        }
                    }
                }

                //includes
                $includes = $node['includes'];
                $servicesAttributes = $servicesParameters = $servicesSet = "";
                $i = 0;
                if (is_array($includes)) {
                    foreach ($includes as $includeStr) {
                        $include = explode(',', $includeStr); // 0-system, 1-service,  2-'service'
                        $servicesParameters .= ($i > 0 ? ",\n        " : "") . "\\" . ($moduleName ?: $appName) . "\\services\\" . $include[0] . "\\" . $include[1] . "Service " . "\$" . lcFirst($include[0]) . $include[1];
                        $servicesSet .= ($i > 0 ? "\n        " : "") . "\$this->" . lcFirst($include[0]) . "->add('" . lcFirst($include[1]) . "', \$" . lcFirst($include[0]) . $include[1] . ");";
                        if (!$systems[$include[0]]) {
                            $servicesAttributes .= "\n    /**\n     *  Container\n     */\n    protected \$" . lcFirst($include[0]) . ";";
                            $servicesSet = ($j++ > 0 ? "\n        ": "") . "\$this->" . lcFirst($include[0]) . " = new \\MServiceContainer();" . "\n        " . $servicesSet;
                            $systems[$include[0]] = true;
                        }
                        ++$i;
                    }
                }

                $fileName = array();
                mdump(">>>" . $system . ' - ' . $name);
                if ($name == "GetById") {
                    $fileName[] = array('public/files/templates/ddd_service_getbyid.php', "{$moduleName}/src/services/{$system}/{$name}Service.php");
                } elseif ($name == "ListByFilter") {
                    $fileName[] = array('public/files/templates/ddd_service_listbyfilter.php', "{$moduleName}/src/services/{$system}/{$name}Service.php");
                } else {
                    $fileName[] = array('public/files/templates/ddd_service.php', "{$moduleName}/src/services/{$system}/{$name}Service.php");
                }

                $template = new MWizardTemplate();
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
                $var['reposAttributes'] = $reposAttributes;
                $var['reposParameters'] = ($reposParameters != '') ?  $reposParameters . (($servicesParameters != '') ?  ',' : '') : '';
                $var['reposSet'] = $reposSet;

                $template->setVar($var);

                foreach ($fileName as $f) {
                    $template->setTemplate($f[0]);
                    $template->apply();
                    $template->saveResult($f[1], $this->baseDir);
                }

            }
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

    public function generateEnumeration($originalClassName, $className, $var)
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
