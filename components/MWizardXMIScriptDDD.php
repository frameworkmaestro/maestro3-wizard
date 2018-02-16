<?php

class MWizardXMIScriptDDD
{

    public $fileXMI;
    public $baseDir;
    private $nodes;
    private $className;
    private $appName;
    private $moduleName;
    private $databaseName;
    private $classPackage;
    private $servicePackage;
    private $repositoryPackage;
    private $exceptionPackage;
    private $prefix;
    public $generatedMaps;
    public $xpath;
    public $errors;

    public function setBaseDir($dir)
    {
        $this->baseDir = $dir;
    }

    public function setFile($fileXMI)
    {
        $this->fileXMI = $fileXMI;
    }

    public function setAppName($name)
    {
        $this->appName = $name;
    }

    public function setModuleName($name)
    {
        $this->moduleName = $name;
    }

    public function setDatabaseName($name)
    {
        $this->databaseName = $name;
    }

    public function setClassPackage($name)
    {
        $this->classPackage = $name;
    }

    public function setServicePackage($name)
    {
        $this->servicePackage = $name;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
        $this->classPackage = $prefix . '_Classes';
        $this->servicePackage = $prefix . '_Services';
        $this->repositoryPackage = $prefix . '_Repositories';
        $this->exceptionPackage = $prefix . '_Exceptions';
    }

    public function getGeneratedMaps()
    {
        sort($this->generatedMaps);
        return $this->generatedMaps;
    }

    public function generate()
    {
        $this->errors = array();
        $this->generatedMaps = array();
        $doc = new domDocument();
        $doc->load($this->fileXMI);

        $this->xpath = new DOMXpath($doc);

        $this->parse($doc);
        $this->handleAssociation();
        $this->handleAssociationClass();
        $this->handleClassPK();
        $this->handleClassModule();
        $this->handleClassComment();
        $this->handleClassGeneralization();
        $this->handleSystem();

        $script = [];
        $elements = $this->xpath->query("//ownedMember[@name='{$this->classPackage}']/ownedMember[@xmi:type='uml:Class'] | " .
            " //ownedMember[@name='{$this->classPackage}']/ownedMember[@xmi:type='uml:AssociationClass'] | " .
            " //ownedMember[@name='{$this->classPackage}']/ownedMember[@xmi:type='uml:Enumeration'] ");

        if ($elements->length > 0) {
            $document = $this->handleClass($elements);
            //$this->handleAssociativeClass();
        } else {
            throw new Exception("Não foi possível encontrar o Class Package {$this->classPackage} no arquivo XMI.", 1);
        }
        $script = array_merge($script, $document);

        $elements = $this->xpath->query("//ownedMember[@name='{$this->servicePackage}']/ownedMember[@xmi:type='uml:Package'] ");

        if ($elements->length > 0) {
            $document = $this->handleService($elements);
        } else {
            throw new Exception("Não foi possível encontrar o Event Package {$this->servicePackage} no arquivo XMI.", 1);
        }
        $script = array_merge($script, $document);

        $elements = $this->xpath->query("//ownedMember[@name='{$this->repositoryPackage}']/ownedMember[@xmi:type='uml:Interface'] ");

        if ($elements->length > 0) {
            $document = $this->handleRepository($elements);
        } else {
            throw new Exception("Não foi possível encontrar o Repository Package {$this->repositoryPackage} no arquivo XMI.", 1);
        }
        $script = array_merge($script, $document);

        $elements = $this->xpath->query("//ownedMember[@name='{$this->exceptionPackage}']/ownedMember[@xmi:type='uml:Class'] ");

        if ($elements->length > 0) {
            $document = $this->handleException($elements);
        } else {
            throw new Exception("Não foi possível encontrar o Exception Package {$this->exceptionPackage} no arquivo XMI.", 1);
        }
        $script = array_merge($script, $document);

        $map = implode("\n", $script);
        $filename = $this->baseDir . '/' . $this->moduleName . '.txt';
        file_put_contents($filename, $map);
    }

    private function parse($domNode)
    {
        $childDomNode = $domNode->firstChild;
        while ($childDomNode) {
            if ($childDomNode->nodeType == XML_ELEMENT_NODE) {
                $this->parseNode($childDomNode);
            }
            $childDomNode = $childDomNode->nextSibling;
        }
    }

    private function parseNode($node)
    {
        if ($node->hasAttributes()) {
            $array = $node->attributes;
            $ok = false;
            foreach ($array AS $domAttribute) {
                if ($domAttribute->name == "type") {
                    if (($domAttribute->value == "uml:Association") ||
                        ($domAttribute->value == "uml:Interface") ||
                        ($domAttribute->value == "uml:Class") ||
                        ($domAttribute->value == "uml:Property") ||
                        ($domAttribute->value == "dbTable") ||
                        ($domAttribute->value == "dbColumn") ||
                        ($domAttribute->value == "dbForeignKey") ||
                        ($domAttribute->value == "dbForeignKeyConstraint") ||
                        ($domAttribute->value == "uml:Enumeration") ||
                        ($domAttribute->value == "uml:AssociationClass") ||
                        ($domAttribute->value == "uml:Model") ||
                        ($domAttribute->value == "uml:UseCase") ||
                        ($domAttribute->value == "uml:Include")
                    ) {
                        $ok = true;
                        $type = trim($domAttribute->value);
                    }
                }
                if ($domAttribute->name == "id") {
                    $id = trim($domAttribute->value);
                }
                if ($domAttribute->name == "foreignKey") {
                    $fk = trim($domAttribute->value);
                }
                if ($domAttribute->name == "relationshipEndModel") {
                    $rel = trim($domAttribute->value);
                }
            }
            if ($ok) {
                $this->nodes[$type][$id] = $node;

                if ($type == "dbForeignKeyConstraint") {
                    $this->nodes[$type][$fk] = $node;
                }
                if ($type == "dbForeignKey") {
                    $this->nodes[$type][$rel] = $node;
                }
            }
        }
        if ($node->hasChildNodes()) {
            $this->parse($node);
        }
    }

    private function handleAssociation()
    {
        foreach ($this->nodes['uml:Association'] as $idA => $assoc) {
            $i = 0;
            $association = array();
            $a = $assoc->firstChild;
            while ($a) {
                if ($a->nodeType == XML_ELEMENT_NODE) {
                    if ($a->nodeName == 'memberEnd') {
                        $idref = $a->getAttributeNode('xmi:idref')->value;
                        $property = $this->nodes['uml:Property'][$idref];
                        $association[$i++] = $property;
                    }
                }
                $a = $a->nextSibling;
            }
            if ((count($association) == 0) ||
                !($association[0] instanceof DomElement) || !($association[1] instanceof DomElement)
            ) {
                $this->errors[] = 'Error at Association ' . $idA;
            } else {
                $class0 = $association[0]->getAttributeNode('type')->value;
                $this->nodes['Associations'][$class0][$idA] = $association;
                $class1 = $association[1]->getAttributeNode('type')->value;
                $this->nodes['Associations'][$class1][$idA] = $association;
            }
        }
    }

    private function handleAssociationClass()
    {
        foreach ($this->nodes['uml:AssociationClass'] as $idA => $assoc) {
            $i = 0;
            $associationName = '';
            $association = $associationExtra = array();
            $a = $assoc->firstChild;
            while ($a) {
                if ($a->nodeType == XML_ELEMENT_NODE) {
                    if ($a->nodeName == 'memberEnd') {
                        $idref = $a->getAttributeNode('xmi:idref')->value;
                        $property = $this->nodes['uml:Property'][$idref];
                        $association[$i] = $property;
                        $i++;
                    }
                    if ($a->nodeName == 'xmi:Extension') {
                        $b = $a->firstChild;
                        while ($b) {
                            if ($b->nodeName == 'associationClass') {
                                $associationName = $b->getAttributeNode('name')->value;
                            }
                            $b = $b->nextSibling;
                        }
                    }
                }
                $a = $a->nextSibling;
            }
            if ((count($association) == 0) ||
                !($association[0] instanceof DomElement) || !($association[1] instanceof DomElement)
            ) {
                $this->errors[] = 'Error at Association ' . $idA;
            } else {
                if ($associationName == '') {
                    $associationName = strtolower($assoc->getAttributeNode('name')->value);
                }
                $class0 = $association[0]->getAttributeNode('type')->value;
                $class1 = $association[1]->getAttributeNode('type')->value;
                $this->nodes['Associations'][$class0][$class1] = $association;
                $this->nodes['Associations'][$class1][$class0] = $association;
                $this->nodes['AssociativeClass'][$class0][$idA] = array($assoc->getAttributeNode('name')->value, $idA, $associationName);
                $this->nodes['AssociativeClass'][$class1][$idA] = array($assoc->getAttributeNode('name')->value, $idA, $associationName);
                $this->nodes['AssociativeClassAttribute'][$idA][$class0] = $class0;
                $this->nodes['AssociativeClassAttribute'][$idA][$class1] = $class1;
                $this->nodes['InverseAssociativeClass'][$idA][$class0] = array($class0, $association[0]->getAttributeNode('name')->value);
                $this->nodes['InverseAssociativeClass'][$idA][$class1] = array($class1, $association[1]->getAttributeNode('name')->value);
            }
        }
    }

    private function handleClassPK()
    {
        $classNodes = $this->nodes['uml:Class'];
        foreach ($classNodes as $id => $node) {
            $n = $node->firstChild->nextSibling;
            while ($n) {
                if ($n->nodeType == XML_ELEMENT_NODE) {
                    if ($n->nodeName == 'ownedAttribute') {
                        if ($c = $this->getChild($n->firstChild->nextSibling, 'ormDetail')) {
                            $colId = $c->getAttributeNode('columnModel')->value;
                            $col = $this->nodes['dbColumn'][$colId];
                            if ($col->nodeType == XML_ELEMENT_NODE) {
                                if ($col->getAttributeNode('primaryKey')->value == 'true') {
                                    $this->nodes['classPK'][$colId] = $n->getAttributeNode('name')->value;
                                    $this->nodes['classPK'][$colId . '_type'] = $n->getAttributeNode('type');
                                    $this->nodes['PK'][$id] = array($n->getAttributeNode('name')->value, $col->getAttributeNode('name')->value, $n->getAttributeNode('type'));
                                }
                            }
                        }
                    }
                }
                $n = $n->nextSibling;
            }
        }
    }

    private function handleClassGeneralization()
    {
        $classNodes = $this->nodes['uml:Class'];
        foreach ($classNodes as $id => $node) {
            $n = $node->firstChild->nextSibling;
            while ($n) {
                if ($n->nodeType == XML_ELEMENT_NODE) {
                    if ($n->nodeName == 'generalization') {
                        $this->nodes['classGeneralization'][$id] = $n->getAttributeNode('general')->value;
                        $this->nodes['classEspecialization'][$n->getAttributeNode('general')->value][] = $id;
                    }
                }
                $n = $n->nextSibling;
            }
        }
        $classNodes = $this->nodes['uml:Enumeration'];
        if (is_array($classNodes)) {
            foreach ($classNodes as $id => $node) {
                $n = $node->firstChild->nextSibling;
                while ($n) {
                    if ($n->nodeType == XML_ELEMENT_NODE) {
                        if ($n->nodeName == 'generalization') {
                            $this->nodes['classGeneralization'][$id] = $n->getAttributeNode('general')->value;
                            //$this->nodes['classEspecialization'][$n->getAttributeNode('general')->value][] = $id;
                        }
                    }
                    $n = $n->nextSibling;
                }
            }
        }
    }

    private function handleClassComment()
    {
        $classNodes = $this->nodes['uml:Class'];
        foreach ($classNodes as $id => $node) {
            $n = $node->firstChild->nextSibling;
            while ($n) {
                if ($n->nodeType == XML_ELEMENT_NODE) {
                    if ($n->nodeName == 'ownedComment') {
                        if ($c = $this->getChild($n, 'body')) {
                            $this->nodes['classComment'][$id] = str_replace("\n", "\n     * ", $c->nodeValue);
                        }
                    }
                }
                $n = $n->nextSibling;
            }
        }
    }

    private function handleClassModule()
    {
        $classNodes = $this->nodes['uml:Class'];
        foreach ($classNodes as $id => $node) {
            $package = $node->parentNode->getAttributeNode('name')->value;
            $moduleName = strtolower(str_replace('_Classes', '', $package));
            $this->nodes['classModule'][$id] = $moduleName;
        }
        $classNodes = $this->nodes['uml:Enumeration'];
        if (is_array($classNodes)) {
            foreach ($classNodes as $id => $node) {
                $package = $node->parentNode->getAttributeNode('name')->value;
                $moduleName = strtolower(str_replace('_Classes', '', $package));
                $this->nodes['classModule'][$id] = $moduleName;
            }
        }
    }

    function handleClass($elements)
    {
        $tab = '    ';
        $classNodes = $elements;
        $dbName = $this->databaseName;
        $moduleName = $this->moduleName;
        $document = array();

        $document[] = "[globals]";
        $document[] = "database = \"{$dbName}\"";
        $document[] = "app = \"{$this->appName}\"";
        $document[] = "module = \"{$this->moduleName}\"";
        $document[] = "package = \"{$this->prefix}\"";
        $document[] = '';

        foreach ($classNodes as $node) {
            $properties = $methods = '';
            $id = $node->getAttributeNode('xmi:id')->value;
            //$this->moduleName = $moduleName = $this->nodes['classModule'][$id];
            $classNameXMI = $node->getAttributeNode('name')->value;
            $this->className = $className = strtolower($classNameXMI);

            if ($className == 'menumdatabase') {
                continue;
            }

            mdump('handleClass = ' . $classNameXMI . ' ' . $id);

            $docassoc = $docattr = $docop = $attributes = array();
            $document[] = '[' . $classNameXMI . ']';
            $document[] = "type = \"model\"";
            $document[] = "package = \"{$this->prefix}\\Models\"";
            if ($t = $this->getChild($node->firstChild->nextSibling, 'ormDetail')) {
                $tableId = $t->getAttributeNode('tableModel')->value;
                $table = $this->nodes['dbTable'][$tableId];
                if ($table->nodeType == XML_ELEMENT_NODE) {
                    $tableName = $table->getAttributeNode('name')->value;
                    $document[] = "table = \"{$tableName}\"";
                }
            }

            $extends = '';
            if ($generalization = $this->nodes['classGeneralization'][$id]) {
                $moduleSuperClass = $this->nodes['classModule'][$generalization];
                if ($node->getAttributeNode('xmi:type')->value != 'uml:Enumeration') {
                    $superClass = $this->nodes['uml:Class'][$generalization];
                    $extends = "\\" . $moduleSuperClass . "\models\\" . $superClass->getAttributeNode('name')->value;
                    $document[] = "extends = \"{$extends}\"";
                    $reference = $this->getChild($node->firstChild->nextSibling, 'ormDetail');
                    if ($reference) {
                        $key = $this->nodes['PK'][$generalization];
                        $columnType = $this->getType($key[2]);
                        $docattr[] = "attributes['{$key[0]}'] = \"{$key[1]},{$columnType},not null,reference\"";
                    }
                } else {
                    $superClass = $this->nodes['uml:Enumeration'][$generalization];
                    if ($superClass->getAttributeNode('name')->value != 'MEnumDatabase') {
                        $extends = "\\" . $moduleSuperClass . "\models\\" . $superClass->getAttributeNode('name')->value;
                    } else {
                        $extends = "\\" . $superClass->getAttributeNode('name')->value;
                    }
                    $document[] = "extends = \"{$extends}\"";
                }
            }

            $comment = $this->nodes['classComment'][$id];

            $noGenerator = $this->hasChild($node->firstChild->nextSibling, 'appliedStereotype', "Class_ORM No Generator_id");
            $idIdentity = $this->hasChild($node->firstChild->nextSibling, 'appliedStereotype', "Class_ORM ID Identity_id");
            $getterSetter = '';
            $pk = '';
            if ($node->getAttributeNode('xmi:type')->value == 'uml:Enumeration') {
                $document[] = "type = \"enumeration\"";
            } else {
                $document[] = "log = \"\"";
            }
            $description = $classComment = "";

            if ($cmt = $this->getChild($node, 'ownedComment')) {
                $c = $this->getChild($cmt, 'body');
                $classComment = base64_encode(str_replace("\n", "\n     * ", $c->nodeValue));
            }

            $n = $node->firstChild->nextSibling;
            while ($n) {
                if ($n->nodeType == XML_ELEMENT_NODE) {
                    if ($n->nodeName == 'ownedAttribute') {
                        if ($n->getAttributeNode('association')->value != '') { // e uma associação, não um atributo
                            $n = $n->nextSibling;
                            continue;
                        }

                        $at = $n->getAttributeNode('name')->value;
                        $attributes[$at] = $at;
                        $attribute = "attributes['{$at}'] = \"";
                        $attributeData = [0 => '', 1 => '', 2 => '', 3 => '', 4 => '', 5 => ''];

                        if ($cmt = $this->getChild($n, 'ownedComment')) {
                            $c = $this->getChild($cmt, 'body');
                            $attributeData[5] = base64_encode(str_replace("\n", "\n     * ", $c->nodeValue));
                        }

                        if ($c = $this->getChild($n->firstChild->nextSibling, 'ormDetail')) {
                            $colId = $c->getAttributeNode('columnModel')->value;
                            $col = $this->nodes['dbColumn'][$colId];
                            if ($col->nodeType == XML_ELEMENT_NODE) {
                                $colName = $col->getAttributeNode('name')->value;
                                $attributeData[0] = $colName;
                            }
                        }

                        $columnType = $this->getType($n->getAttributeNode('type'));
                        $attributeData[1] = $columnType;

                        $isPK = false;
                        if ($c = $this->getChild($n->firstChild->nextSibling, 'appliedStereotype')) {
                            if ($c->getAttributeNode('xmi:value')->value == 'Attribute_PK_id') {
                                $atributeData[2] = 'not null';
                                $attributeData[3] = 'primary';
                                $isPK = true;
                                $pk = $at;
                                if (!$noGenerator) {
                                    if ($idIdentity) {
                                        $attributeData[4] = "identity";
                                    } else {
                                        $attributeData[4] = "seq_{$tableName}";
                                    }
                                }
                            }
                            if ($c->getAttributeNode('xmi:value')->value == 'Attribute_Description_id') {
                                $description = $at;
                            }

                            if ($c->getAttributeNode('xmi:value')->value == 'Attribute_UID_id') {
                                $atributeData[2] = 'not null';
                                $attributeData[3] = 'uid';
                            }
                        }
                        $attribute .= implode(',', $attributeData);
                        $docattr[] = $attribute . "\"";
                    } else if ($n->nodeName == 'ownedLiteral') {
                        $at = $n->getAttributeNode('name')->value;
                        mdump($at);
                        $attributes[$at] = $at;
                        $c = $n->firstChild->nextSibling;
                        if ($c->nodeType == XML_ELEMENT_NODE) {
                            $value = $c->getAttributeNode('value')->value;
                            if ($at == 'model') {
                                $attribute = "attributes['{$at}'] = \"\\{$moduleName}\\models\\{$value}\"";
                            } elseif ($at == 'table') {
                                $attribute = "attributes['{$at}'] = \"{$value}\"";
                            } else {
                                $attribute = "constants['{$at}'] = \"{$value}\"";
                            }
                        }
                        if ($enumDefault == '') {
                            $enumDefault = 'default = ' . "\"{$at}\"";
                            $docattr[] = $enumDefault;
                        }
                        $docattr[] = $attribute;
                    } else if ($n->nodeName == 'ownedOperation') {

                        $op = $n->getAttributeNode('name')->value;
                        $operations[$op] = $op;
                        $operation = "operations['{$op}'] = \"";

                        $return = 'none';
                        $params = '';
                        $m = $n->firstChild->nextSibling;
                        while ($m) {
                            if ($m->nodeType == XML_ELEMENT_NODE) {
                                if ($m->nodeName == 'ownedParameter') {
                                    $paramKind = $m->getAttributeNode('kind')->value;
                                    $paramName = $m->getAttributeNode('name')->value;
                                    $paramType = $m->getAttributeNode('type')->value;
                                    if ($paramKind == 'return') {
                                        if ($paramType != '') {
                                            if (strpos($paramType, '_id')) {
                                                //$return = strtolower(str_replace('_id', '', $paramType));
                                                $return = str_replace('_id', '', $paramType);
                                            } else {
                                                $class = $this->nodes['uml:Class'][$paramType] ?: $this->nodes['uml:AssociationClass'][$paramType];
                                                $return = $class->getAttributeNode('name')->value;
                                            }
                                        }
                                    } else if ($paramName != '') {
                                        if (strpos($paramType, '_id')) {
                                            if ($paramType == 'PlainObject_id') {
                                                $type = "\PlainObject ";
                                            } else if ($paramType == 'ArrayObject_id') {
                                                $type = "\ArrayObject ";
                                            } else {
                                                $type = "";
                                            }
                                        } else {
                                            $class = $this->nodes['uml:Class'][$paramType] ?: $this->nodes['uml:AssociationClass'][$paramType];
                                            if ($class != '') {
                                                $module = strtolower(str_replace('_Classes', '', $class->parentNode->getAttributeNode('name')->value));
                                                $model = $class->getAttributeNode('name')->value;
                                                $type = "\\{$module}\\models\\{$model} ";
                                            } else {
                                                $type = "";
                                            }
                                        }
                                        $params .= ",{$type}\${$paramName}";
                                    }
                                }
                                $comment = ',';
                                if ($m->nodeName == 'ownedComment') {
                                    $c = $m->firstChild->nextSibling;
                                    if ($c->nodeName == 'body') {
                                        $comment .= base64_encode($c->nodeValue);
                                    }
                                }
                            }
                            $m = $m->nextSibling;
                        }
                        $docop[] = $operation . $return . $comment . $params . "\"";
                    }


                }
                $n = $n->nextSibling;
            }
            $document[] = "description = \"{$description}\"";
            $document[] = "comment = \"{$classComment}\"";

            if (count($this->nodes['Associations'][$id])) {
                foreach ($this->nodes['Associations'][$id] as $idA => $association) {
                    $i = 0;
                    $j = 0;
                    $class0 = $association[0]->getAttributeNode('type')->value;
                    $name0 = trim($association[0]->getAttributeNode('name')->value);
                    $class1 = $association[1]->getAttributeNode('type')->value;
                    $name1 = trim($association[1]->getAttributeNode('name')->value);
                    mdump('associations = ' . $name0 . ' ' . $name1);
                    $attribute = '';
                    if (($class0 == $id) && ($name1 != '')) {
                        $docassoc[] = $this->createAssociationNode($association[1], $association[0], $attribute);

                        if (($attribute != '') && (!$attributes[$attribute[0]])) {
                            $docattr[] = "attributes['{$attribute[0]}'] = \"{$attribute[0]},{$attribute[2]},,foreign\"";
                            $attributes[$attribute[0]] = $attribute[0];
                        }
                    }
                    if (($class1 == $id) && ($name0 != '')) {
                        $docassoc[] = $this->createAssociationNode($association[0], $association[1], $attribute);

                        if (($attribute != '') && (!$attributes[$attribute[0]])) {
                            $docattr[] = "attributes['{$attribute[0]}'] = \"{$attribute[0]},{$attribute[2]},,foreign\"";
                            $attributes[$attribute[0]] = $attribute[0];
                        }
                    }
                }
            }

            if (count($this->nodes['AssociativeClass'][$id])) {
                foreach ($this->nodes['AssociativeClass'][$id] as $idA => $association) {
                    $toClass = strtolower($association[0]);
                    $toClassName = $association[0];
                    $associationName = $association[2];
                    $module = $this->nodes['classModule'][$id];
                    $docattr[] = "associations['{$associationName}'] = \"{$module}\\persistence\\maestro\\models\\{$toClassName},oneToMany,{$pk}:{$pk}\"";
                }
            }

            if (count($this->nodes['AssociativeClassAttribute'][$id])) {
                foreach ($this->nodes['AssociativeClassAttribute'][$id] as $idA => $associatedClass) {
                    $atName = $this->nodes['PK'][$associatedClass][0];
                    $atCol = $this->nodes['PK'][$associatedClass][0];
                    $atType = $this->nodes['PK'][$associatedClass][0];
                    $docattr[] = "attributes['{$atName}'] = \"{$atCol},integer,,foreign\"";
                }
            }

            if (count($this->nodes['InverseAssociativeClass'][$id])) {
                foreach ($this->nodes['InverseAssociativeClass'][$id] as $idA => $association) {
                    $associatedClass = $this->nodes['uml:Class'][trim($association[0])];
                    $toClassName = $associatedClass->getAttributeNode('name')->value;
                    $associationName = lcFirst($toClassName);//$association[1]; // associação inversa é oneToOne
                    $pk = $this->nodes['PK'][trim($association[0])][0];
                    $module = $this->nodes['classModule'][trim($association[0])];
                    $docattr[] = "associations['{$associationName}'] = \"{$module}\\persistence\\maestro\\models\\{$toClassName},oneToOne,{$pk}:{$pk}\"";
                }
            }
            //"{$module}\\persistence\\maestro\\{$toClass}\\{$toClassName}";

            if (count($especializations = $this->nodes['classEspecialization'][$id])) {
                foreach ($especializations as $especialization) {
                    $moduleSubClass = $this->nodes['classModule'][$especialization];
                    $subClass = $this->nodes['uml:Class'][$especialization];
                    $subClassName = $subClass->getAttributeNode('name')->value;
                    $subClassNameLower = strtolower($subClassName);
                    $subClassNameFull = "\\{$moduleSubClass}\\persistence\maestro\\{$subClassNameLower}\\{$subClassName}";
                    $key = $this->nodes['PK'][$id];
                    $docassoc[] = "associations['{$subClassName}'] = \"{$subClassNameFull},oneToOne,{$key[0]}:{$key[1]}\"";
                }
            }

            foreach ($docattr as $attr) {
                $document[] = $attr;
            }
            foreach ($docop as $op) {
                $document[] = $op;
            }
            foreach ($docassoc as $assoc) {
                $document[] = $assoc;
            }
            $document[] = '';
        }

        return $document;
    }

    /*
      private function handleAssociativeClass() {
      $classNodes = $this->nodes['AssociativeClass'];
      $dbName = $this->databaseName;
      if (count($classNodes)) {
      foreach ($classNodes as $className => $associations) {
      $a = $associations[0];
      $id = $a->getAttributeNode('xmi:id')->value;
      $relNode = $this->nodes['dbForeignKey'][$id];
      $tableId = $relNode->getAttributeNode('to')->value;
      $tableNode = $this->nodes['dbTable'][$tableId];
      $tableName = $tableNode->getAttributeNode('name')->value;
      }
      }
      }
     */

    private function createAssociationNode($association, $myself, &$attribute)
    {
        $tab = '    ';
        $target = trim($association->getAttributeNode('name')->value);

        $at = "associations['{$target}'] = \"";

        $autoAssociation = false;
        $to = $this->nodes['uml:Class'][$association->getAttributeNode('type')->value];
        if ($to->nodeType == XML_ELEMENT_NODE) {
            $module = $this->nodes['classModule'][$to->getAttributeNode('xmi:id')->value];
            $toClassName = $to->getAttributeNode('name')->value;
            $toClass = strtolower($toClassName);
            $from = $this->nodes['uml:Class'][$myself->getAttributeNode('type')->value];
            $fromClass = strtolower($from->getAttributeNode('name')->value);
            $autoAssociation = ($toClass == $fromClass);
        }
        $at .= "{$module}\\persistence\\maestro\\models\\{$toClassName}";

        $c0 = $c1 = '';
        $lower = $this->getChild($association, 'lowerValue');
        $upper = $this->getChild($association, 'upperValue');
        if ($upper->nodeType == XML_ELEMENT_NODE) {
            $c0 = $upper->getAttributeNode('value')->value;
        } elseif ($lower->nodeType == XML_ELEMENT_NODE) {
            $c0 = $lower->getAttributeNode('value')->value;
        }

        $lower = $this->getChild($myself, 'lowerValue');
        $upper = $this->getChild($myself, 'upperValue');
        if ($upper->nodeType == XML_ELEMENT_NODE) {
            $c1 = $upper->getAttributeNode('value')->value;
        } elseif ($lower->nodeType == XML_ELEMENT_NODE) {
            $c1 = $lower->getAttributeNode('value')->value;
        }

        if (($c0 == '*') && ($c1 == '*')) {
            $cardinality = 'manyToMany';
        } elseif (($c0 == '*')) {
            $cardinality = 'oneToMany';
        } else {
            $cardinality = 'oneToOne';
        }

        $at .= ",{$cardinality}";

        $deleteAutomatic = $saveAutomatic = $retrieveAutomatic = false;

        $node = $this->getChild($association->firstChild->nextSibling, 'appliedStereotype');
        while ($node) {
            if ($node->nodeType == XML_ELEMENT_NODE) {
                $deleteAutomatic |= strpos(strtolower($node->getAttribute('xmi:value')), 'deleteautomatic') !== false;
                $saveAutomatic |= strpos(strtolower($node->getAttribute('xmi:value')), 'saveautomatic') !== false;
                $retrieveAutomatic |= strpos(strtolower($node->getAttribute('xmi:value')), 'retrieveautomatic') !== false;
            }
            $node = $node->nextSibling;
        }

        $createFK = $isPK = false;

        $fk = $this->getChild($association->firstChild->nextSibling, 'ormDetail');
        if ($fk->nodeType == XML_ELEMENT_NODE) {
            $fkm = $fk->getAttributeNode('foreignKeyModel')->value;
            if ($fkm != '') {
                $fknode = $this->nodes['dbForeignKeyConstraint'][$fkm];
                if ($fknode->nodeType == XML_ELEMENT_NODE) {
                    $refCol = $fknode->getAttributeNode('refColumn')->value;
                    $col = $this->nodes['dbColumn'][$refCol];
                    if ($col->nodeType == XML_ELEMENT_NODE) {
                        $pkName = $this->nodes['classPK'][$refCol];
                        $refType = $this->getType($this->nodes['classPK'][$refCol . '_type']);
                        $createFK = true;
                    }
                }
            }
        }


        if ($pkName == '') {
            $fk = $this->getChild($myself->firstChild->nextSibling, 'ormDetail');
            if ($fk->nodeType == XML_ELEMENT_NODE) {
                $fkm = $fk->getAttributeNode('foreignKeyModel')->value;
                if ($fkm != '') {
                    $fknode = $this->nodes['dbForeignKeyConstraint'][$fkm];
                    if ($fknode->nodeType == XML_ELEMENT_NODE) {
                        $refCol = $fknode->getAttributeNode('refColumn')->value;
                        $col = $this->nodes['dbColumn'][$refCol];
                        if ($col->nodeType == XML_ELEMENT_NODE) {
                            $refType = $this->getType($this->nodes['classPK'][$refCol . '_type']);
                            $pkName = $this->nodes['classPK'][$refCol];
                        }
                    }
                }
            }
        }

        if ($fknode) {
            if ($fknode->nodeType == XML_ELEMENT_NODE) {
                $fkCol = $fknode->parentNode->parentNode;
                $fkName = $fkCol->getAttributeNode('name')->value;
                $isPK = ($fkCol->getAttributeNode('primaryKey')->value == 'true');
            }
        }

        if ($cardinality == 'manyToMany') {
            $a = $association;
            $id = $a->getAttributeNode('xmi:id')->value;
            $relNode = $this->nodes['dbForeignKey'][$id];
            if (!$relNode) {
                $id = $myself->getAttributeNode('xmi:id')->value;
                $relNode = $this->nodes['dbForeignKey'][$id];
            }
            $tableId = $relNode->getAttributeNode('to')->value;
            $tableNode = $this->nodes['dbTable'][$tableId];
            $tableName = $tableNode->getAttributeNode('name')->value;
            $at .= ",{$tableName}\"";

            $keyName1 = $myself->getAttributeNode('name')->value;
            $keyName2 = $association->getAttributeNode('name')->value;
        } else {
            if ($createFK) {
                $at .= ",{$fkName}:{$pkName}\"";
                $attribute = array($fkName, $pkName, $refType);
                if ($fkName == '') {
                    $this->errors[] = $fromClass . ' - ' . $toClass . ': Chave FK nula';
                }
                if ($pkName == '') {
                    $this->errors[] = $fromClass . ' - ' . $toClass . ': Chaves PK nula';
                }
            } else {
                if ($fkName == '') {
                    $this->errors[] = $fromClass . ' - ' . $toClass . ': Chave FK nula';
                }
                if ($pkName == '') {
                    $this->errors[] = $fromClass . ' - ' . $toClass . ': Chaves PK nula';
                }
                $at .= ",{$pkName}:{$fkName}\"";
            }
        }
        return $at;
    }

    private function getType($node)
    {
        $value = strtolower($node->value);
        if (strpos($value, '_id') !== false) {
            $columnType = str_replace('_id', '', $value);
            if ($columnType == 'char') {
                $columnType = 'string';
            } elseif ($columnType == 'int') {
                $columnType = 'integer';
            }
        } else {
            $enum = $this->nodes['uml:Enumeration'][$node->value];
            if ($enum) {
                $columnType = $enum->getAttributeNode('name')->value;
            }
        }
        return $columnType;
    }

    private function getChild($node, $nodeName)
    {
        try {
            if (!$node)
                throw new Exception;
            if ($node->hasChildNodes()) {
                $n = $node->firstChild;
                while ($n) {
                    if ($n->nodeType == XML_ELEMENT_NODE) {
                        if ($n->nodeName == $nodeName) {
                            return $n;
                        }
                    }
                    $n = $n->nextSibling;
                }
            }
        } catch (Exception $e) {
            var_dump($e->getTraceAsString());
        }
        return NULL;
    }

    private function hasChild($node, $nodeName, $value = NULL)
    {
        $ok = $found = false;
        try {
            if (!$node)
                throw new Exception;
            if ($node->hasChildNodes()) {
                $n = $node->firstChild;
                while ($n && !$found) {
                    if ($n->nodeType == XML_ELEMENT_NODE) {
                        if ($n->nodeName == $nodeName) {
                            $found = $ok = true;
                            if ($value) {
                                $found = $ok = $n->getAttributeNode('xmi:value')->value == $value;
                            }
                        }
                    }
                    $n = $n->nextSibling;
                }
            }
        } catch (Exception $e) {
            var_dump($e->getTraceAsString());
        }
        return $ok;
    }

    public function handleSystem()
    {
        foreach ($this->nodes['uml:Model'] as $id => $node) {
            $name = $node->getAttributeNode('name')->value;
            $externalSystem = $this->hasChild($node->firstChild->nextSibling, 'appliedStereotype', "System_external system_id");
            $typeSystemNode = $this->getChild($node->firstChild->nextSibling, 'appliedStereotype');
            if ($typeSystemNode == '') {
                $externalSystem = true;
            } else {
                $typeSystemValue = explode('_', strtolower($typeSystemNode->getAttributeNode('xmi:value')->value));
                $typeSystem = $typeSystemValue[1];
            }
            if (!$externalSystem) {
                $this->nodes['Systems'][$id]['name'] = $name;
                $n = $node->firstChild->nextSibling;
                while ($n) {
                    if ($n->nodeType == XML_ELEMENT_NODE) {
                        if ($n->nodeName == 'ownedMember') {
                            $idUseCase = $n->getAttributeNode('xmi:id')->value;
                            $this->nodes['Systems'][$id]['usecases'][] = $idUseCase;
                            $nameUseCase = $n->getAttributeNode('name')->value;
                            $commentUseCase = '';
                            if ($cmt = $this->getChild($n, 'ownedComment')) {
                                $c = $this->getChild($cmt, 'body');
                                $commentUseCase = base64_encode(str_replace("\n", "\n     * ", $c->nodeValue));
                            }

                            $external = $this->hasChild($n->firstChild->nextSibling, 'appliedStereotype', "UseCase_external service_id");
                            $service = $this->hasChild($n->firstChild->nextSibling, 'appliedStereotype', "UseCase_service_id");
                            $controller = $this->hasChild($n->firstChild->nextSibling, 'appliedStereotype', "UseCase_controller_id");
                            $query = $this->hasChild($n->firstChild->nextSibling, 'appliedStereotype', "UseCase_query_id");
                            $action = $this->hasChild($n->firstChild->nextSibling, 'appliedStereotype', "UseCase_action_id");
                            if (!$external) {
                                $this->nodes['UseCases'][$idUseCase] = [$nameUseCase, $name, ($service ? 'service' : ($controller ? 'controller' : ($query ? 'query' : ($action ? 'action' : '')))), $typeSystem, $commentUseCase];
                                $i = $n->firstChild->nextSibling;
                                while ($i) {
                                    if ($i->nodeType == XML_ELEMENT_NODE) {
                                        if ($i->nodeName == 'include') {
                                            $this->nodes['Includes'][$idUseCase][] = $i->getAttributeNode('addition')->value;
                                        }
                                        if ($i->nodeName == 'xmi:Extension') {
                                            $j = $i->firstChild->nextSibling;
                                            while ($j) {
                                                if ($j->nodeName == 'references') {
                                                    $k = $j->firstChild->nextSibling;
                                                    while ($k) {
                                                        if ($k->nodeType == XML_ELEMENT_NODE) {
                                                            $url = $k->getAttributeNode('url')->value;
                                                            $r = explode(':', $url);
                                                            $reference = $r[count($r) - 1];
                                                            $this->nodes['References'][$idUseCase][] = $reference;
                                                        }
                                                        $k = $k->nextSibling;
                                                    }
                                                }
                                                $j = $j->nextSibling;
                                            }
                                        }
                                    }
                                    $i = $i->nextSibling;
                                }
                            }
                        }
                    }
                    $n = $n->nextSibling;
                }
            }
        }
        //mdump($this->nodes['Systems']);
        //mdump($this->nodes['Includes']);
    }

    public function handleService($packages)
    {
        $document = [];
        foreach ($packages as $package) {
            $idPackage = $package->getAttributeNode('xmi:id')->value;
            $elements = $this->xpath->query("//ownedMember[@xmi:id='{$idPackage}']/ownedMember[@xmi:type='uml:Model'] ");
            foreach ($elements as $node) {
                $id = $node->getAttributeNode('xmi:id')->value;
                $systemName = $node->getAttributeNode('name')->value;
                mdump('handleService = ' . $systemName);
                $useCases = $this->nodes['Systems'][$id]['usecases'];
                if (is_array($useCases)) {
                    foreach ($useCases as $idUseCase) {
                        $useCase = $this->nodes['UseCases'][$idUseCase];
                        $fullName = $useCase[3] . $systemName . $useCase[0] . ucFirst($useCase[2]);
                        $document[] = "[{$fullName}]";
                        $document[] = "system = \"{$systemName}\"";
                        $document[] = "name = \"{$useCase[0]}\"";
                        $document[] = "type = \"{$useCase[2]}\"";
                        $document[] = "typeSystem = \"{$useCase[3]}\"";
                        $document[] = "package = \"{$this->prefix}\\Services\\" . ucFirst($useCase[3]) . "\\{$systemName}\"";
                        $document[] = "comment = \"{$useCase[4]}\"";
                        if (is_array($this->nodes['Includes'][$idUseCase])) {
                            foreach ($this->nodes['Includes'][$idUseCase] as $include) {
                                if ($this->nodes['UseCases'][$include]) {
                                    $includeName = $this->nodes['UseCases'][$include][0];
                                    $includeSystem = $this->nodes['UseCases'][$include][1];
                                    $includeType = $this->nodes['UseCases'][$include][2];
                                    $includeTypeSystem = $this->nodes['UseCases'][$include][3];
                                    $include = $includeSystem . $includeName;
                                    $document[] = "includes['{$include}'] = \"" . $includeSystem . "," . $includeName . "," . $includeType . "," . $includeTypeSystem . "\"";
                                }
                            }
                        }
                        if (is_array($this->nodes['References'][$idUseCase])) {
                            foreach ($this->nodes['References'][$idUseCase] as $reference) {
                                if ($this->nodes['uml:Class'][$reference]) {
                                    $n = $this->nodes['uml:Class'][$reference];
                                    $class = $n->getAttributeNode('name')->value;
                                    $line = "references['{$class}'] = \"" . $class . "\"";
                                    if ($c = $this->getChild($n->firstChild->nextSibling, 'appliedStereotype')) {
                                        if ($c->getAttributeNode('xmi:value')->value == 'Class_Exception_id') {
                                            $line = "exception['{$class}'] = \"" . $class . "\"";
                                        }
                                    }
                                    $document[] = $line;
                                } else if ($this->nodes['uml:AssociationClass'][$reference]) {
                                    $class = $this->nodes['uml:AssociationClass'][$reference]->getAttributeNode('name')->value;;
                                    $document[] = "references['{$class}'] = \"" . $class . "\"";
                                } else if ($this->nodes['uml:Interface'][$reference]) {
                                    $class = $this->nodes['uml:Interface'][$reference]->getAttributeNode('name')->value;;
                                    $document[] = "references['{$class}'] = \"" . $class . "\"";
                                }
                            }
                        }
                        $document[] = "";
                    }
                }
            }
            $elements = $this->xpath->query("//ownedMember[@xmi:id='{$idPackage}']/ownedMember[@xmi:type='uml:Class'] ");
            foreach ($elements as $node) {
                $name = $node->getAttributeNode('name')->value;
                mdump('-- name = ' . $name);
                $fullName = $name . 'Class';
                $document[] = "[{$fullName}]";
                $document[] = "system = \"{$systemName}\"";
                $document[] = "name = \"{$name}\"";
                $document[] = "type = serviceclass";
                $document[] = "typeSystem = domain";
                $docop = [];
                $n = $node->firstChild->nextSibling;
                while ($n) {
                    if ($n->nodeType == XML_ELEMENT_NODE) {
                        if ($n->nodeName == 'ownedOperation') {
                            if ($n->getAttributeNode('association')->value != '') { // e uma associação, não um atributo
                                $n = $n->nextSibling;
                                continue;
                            }

                            $op = $n->getAttributeNode('name')->value;
                            $operations[$op] = $op;
                            $operation = "operations['{$op}'] = \"";
                            $comment = '';
                            $return = 'none';
                            $params = '';
                            $m = $n->firstChild->nextSibling;
                            while ($m) {
                                if ($m->nodeType == XML_ELEMENT_NODE) {
                                    if ($m->nodeName == 'ownedParameter') {
                                        $paramKind = $m->getAttributeNode('kind')->value;
                                        $paramName = $m->getAttributeNode('name')->value;
                                        $paramType = $m->getAttributeNode('type')->value;
                                        if ($paramKind == 'return') {
                                            if ($paramType != '') {
                                                if (strpos($paramType, '_id')) {
                                                    //$return = strtolower(str_replace('_id', '', $paramType));
                                                    $return = str_replace('_id', '', $paramType);
                                                } else {
                                                    $class = $this->nodes['uml:Class'][$paramType] ?: $this->nodes['uml:AssociationClass'][$paramType];
                                                    $return = $class->getAttributeNode('name')->value;
                                                }
                                            }
                                        } else if ($paramName != '') {
                                            if (strpos($paramType, '_id')) {
                                                if ($paramType == 'PlainObject_id') {
                                                    $type = "\PlainObject ";
                                                } else if ($paramType == 'ArrayObject_id') {
                                                    $type = "\ArrayObject ";
                                                } else {
                                                    $type = "";
                                                }
                                            } else {
                                                $class = $this->nodes['uml:Class'][$paramType] ?: $this->nodes['uml:AssociationClass'][$paramType];
                                                if ($class != '') {
                                                    $module = strtolower(str_replace('_Classes', '', $class->parentNode->getAttributeNode('name')->value));
                                                    $model = $class->getAttributeNode('name')->value;
                                                    $type = "\\{$module}\\models\\{$model} ";
                                                } else {
                                                    $type = "";
                                                }
                                            }
                                            $params .= ",{$type}\${$paramName}";
                                        }
                                    }
                                    $comment = ',';
                                    if ($m->nodeName == 'ownedComment') {
                                        $c = $m->firstChild->nextSibling;
                                        if ($c->nodeName == 'body') {
                                            $comment .= base64_encode($c->nodeValue);
                                        }
                                    }
                                }
                                $m = $m->nextSibling;
                            }

                            $docop[] = $operation . $return . $comment . $params . "\"";
                        }
                    }
                    $n = $n->nextSibling;
                }

                foreach ($docop as $op) {
                    $document[] = $op;
                }
                $document[] = '';

            }
        }
        return $document;

    }

    function handleRepository($elements)
    {
        $tab = '    ';
        $classNodes = $elements;
        $document = array();

        foreach ($classNodes as $node) {
            $properties = $methods = '';
            $id = $node->getAttributeNode('xmi:id')->value;
            //$this->moduleName = $moduleName = $this->nodes['classModule'][$id];
            $classNameXMI = $node->getAttributeNode('name')->value;
            $this->className = $className = strtolower($classNameXMI);

            if ($className == 'menumdatabase') {
                continue;
            }

            mdump('handleRepository = ' . $classNameXMI);

            $attributes = array();
            $document[] = '[' . $classNameXMI . ']';
            $document[] = "type = \"repository\"";
            $document[] = "package = \"{$this->prefix}\"";
            $description = "";
            $docop = [];
            $docref = [];
            $n = $node->firstChild->nextSibling;
            while ($n) {
                if ($n->nodeType == XML_ELEMENT_NODE) {
                    if ($n->nodeName == 'ownedOperation') {
                        if ($n->getAttributeNode('association')->value != '') { // e uma associação, não um atributo
                            $n = $n->nextSibling;
                            continue;
                        }

                        $op = $n->getAttributeNode('name')->value;
                        $operations[$op] = $op;
                        $operation = "operations['{$op}'] = \"";

                        $return = 'none';
                        $params = '';
                        $m = $n->firstChild->nextSibling;
                        while ($m) {
                            if ($m->nodeType == XML_ELEMENT_NODE) {
                                if ($m->nodeName == 'ownedParameter') {
                                    $paramKind = $m->getAttributeNode('kind')->value;
                                    $paramName = $m->getAttributeNode('name')->value;
                                    $paramType = $m->getAttributeNode('type')->value;
                                    if ($paramKind == 'return') {
                                        if ($paramType != '') {
                                            if (strpos($paramType, '_id')) {
                                                $return = strtolower(str_replace('_id', '', $paramType));
                                            } else {
                                                $class = $this->nodes['uml:Class'][$paramType] ?: $this->nodes['uml:AssociationClass'][$paramType];
                                                $return = $class->getAttributeNode('name')->value;
                                            }
                                        }
                                    } else if ($paramName != '') {
                                        if (strpos($paramType, '_id')) {
                                            if ($paramType == 'PlainObject_id') {
                                                $type = "\PlainObject ";
                                            } else if ($paramType == 'ArrayObject_id') {
                                                $type = "\ArrayObject ";
                                            } else {
                                                $type = "";
                                            }
                                        } else {
                                            $class = $this->nodes['uml:Class'][$paramType] ?: $this->nodes['uml:AssociationClass'][$paramType];
                                            if ($class != '') {
                                                $module = strtolower(str_replace('_Classes', '', $class->parentNode->getAttributeNode('name')->value));
                                                $model = $class->getAttributeNode('name')->value;
                                                $type = "\\{$module}\\models\\{$model} ";
                                            } else {
                                                $type = "";
                                            }
                                        }
                                        $params .= ",{$type}\${$paramName}";
                                    }
                                }
                            }
                            $m = $m->nextSibling;
                        }
                        $docop[] = $operation . $return . $params . "\"";
                    }
                    if ($n->nodeName == 'xmi:Extension') {
                        $j = $n->firstChild->nextSibling;
                        while ($j) {
                            if ($j->nodeName == 'references') {
                                $k = $j->firstChild->nextSibling;
                                while ($k) {
                                    if ($k->nodeType == XML_ELEMENT_NODE) {
                                        $url = $k->getAttributeNode('url')->value;
                                        $r = explode(':', $url);
                                        $reference = $r[count($r) - 1];
                                        if ($this->nodes['uml:Class'][$reference]) {
                                            $class = $this->nodes['uml:Class'][$reference];
                                        } elseif ($this->nodes['uml:AssociationClass'][$reference]) {
                                            $class = $this->nodes['uml:AssociationClass'][$reference];
                                        } else {
                                            mdump('Referencia não encontrada: ' . $reference);
                                        }
                                        $name = $class->getAttributeNode('name')->value;
                                        $docref[] = "references['{$name}'] = \"{$name}\"";
                                    }
                                    $k = $k->nextSibling;
                                }
                            }
                            $j = $j->nextSibling;
                        }
                    }
                }
                $n = $n->nextSibling;
            }
            $document[] = "description = \"{$description}\"";
            foreach ($docop as $op) {
                $document[] = $op;
            }
            foreach ($docref as $ref) {
                $document[] = $ref;
            }
            $document[] = '';
        }
        return $document;
    }

    function handleException($elements)
    {
        $classNodes = $elements;
        $document = array();

        foreach ($classNodes as $node) {
            $id = $node->getAttributeNode('xmi:id')->value;
            $classNameXMI = $node->getAttributeNode('name')->value;
            $this->className = $className = strtolower($classNameXMI);

            mdump('handleException = ' . $classNameXMI);

            $attributes = array();
            $document[] = '[' . $classNameXMI . ']';
            $document[] = "type = \"exception\"";
            $document[] = "package = \"{$this->prefix}\\Exceptions\"";
            $docattr = [];
            $n = $node->firstChild->nextSibling;
            while ($n) {
                if ($n->nodeType == XML_ELEMENT_NODE) {
                    if ($n->nodeName == 'ownedAttribute') {
                        $at = $n->getAttributeNode('name')->value;
                        $attributes[$at] = $at;
                        $attribute = "attributes['{$at}'] = \"";
                        $attributeData = [0 => '', 1 => ''];
                        if ($cmt = $this->getChild($n, 'ownedComment')) {
                            $c = $this->getChild($cmt, 'body');
                            $attributeData[1] = base64_encode(str_replace("\n", "\n     * ", $c->nodeValue));
                        }
                        $paramType = $n->getAttributeNode('type')->value;
                        $class = $this->nodes['uml:Class'][$paramType] ?: $this->nodes['uml:AssociationClass'][$paramType];
                        if ($class != '') {
                            $module = strtolower(str_replace('_Classes', '', $class->parentNode->getAttributeNode('name')->value));
                            $model = $class->getAttributeNode('name')->value;
                            $type = "\\{$module}\\models\\{$model}";
                        } else {
                            $type = "";
                        }
                        $attributeData[0] = $type;
                        $attribute .= implode(',', $attributeData);
                        $docattr[] = $attribute . "\"";
                    }
                }
            $n = $n->nextSibling;
        }
        foreach ($docattr as $attr) {
            $document[] = $attr;
        }
        $document[] = '';
    }
return $document;
}
}