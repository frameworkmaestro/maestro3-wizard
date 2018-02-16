<?php
use TokenFun\TokenFinder\Tool\TokenFinderTool;

require_once  __DIR__ . "/../utils/bigbang.php";

class MWizardScriptDDDUpdate
{
    public $files;
    public $pathSource;
    public $pathTarget;
    public $fileScript;
    public $globals;
    public $errors;
    public $ini;
    public $generatedMaps;
    private $baseService;
    private $data;

    public function __construct()
    {
        $this->globals = new StdClass;
    }

    public function setPathSource($dir)
    {
        $this->pathSource = $dir;
    }

    public function setPathTarget($dir)
    {
        $this->pathTarget = $dir;
    }

    public function setFileScript($file)
    {
        $this->fileScript = $file;
    }

    public function scanSource()
    {
        $files = [];
        $this->scanPath($this->pathSource, $files);
        $this->files = $files;
    }

    private function scanPath($path, &$files)
    {
        $scandir = scandir($path) ?: [];
        $scandir = array_diff($scandir, ['..', '.']);
        foreach ($scandir as $s) {
            if ($s{0} != '.') {
                $p = $path . DIRECTORY_SEPARATOR . $s;
                if (is_dir($p)) {
                    $files[$s] = [];
                    $this->scanPath($p, $files[$s]);
                } elseif (is_file($p)) {
                    $index = str_replace('.php', '', $s);
                    if (strpos($index, '.') !== false) {
                        $index = str_replace('.', '_', $index);
                    }
                    $files[$index] = $s;
                }
            }
        }
    }

    public function generate()
    {
        require $this->pathSource . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

        $this->scanSource();
        $this->errors = array();
        mdump('file script: ' . $this->fileScript);
        $this->ini = parse_ini_file($this->fileScript, true);
        $tab = '    ';
        $this->globals->dbName = $this->ini['globals']['database'];
        $this->globals->appName = $this->ini['globals']['app'];
        $this->globals->moduleName = $this->ini['globals']['module'] ?: $this->globals->appName;
        $this->globals->packageName = $this->ini['globals']['package'];
        $this->globals->actions[] = $tab . "'{$this->globals->moduleName}' => ['{$this->globals->moduleName}', '{$this->globals->moduleName}/main/main', '{$this->globals->moduleName}IconForm', '', A_ACCESS, [";
        $this->baseService = false;

        $template = new MWizardTemplate();
        $template->rrmdir($this->pathTarget . "/{$this->globals->moduleName}");

        $controllers = [];
        $repository = [];
        $application = [];
        $domain = [];
        $exception = [];

        foreach ($this->ini as $className => $node) {
            if ($className == 'globals')
                continue;
            if ($node['type'] == 'controller') {
                if (is_array($node['includes'])) {
                    foreach ($node['includes'] as $actionName => $actionData) {
                        $controllers[$className][$actionName] = $this->ini['interface' . $actionName . 'Action'];
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
            if ($node['type'] == 'exception') {
                $exception[$className] = $node;
            }
            if ($node['type'] == 'model') {
                $this->generateModel($node, $className);
                $this->generatePersistentModel($node, $className);
            }
            if ($node['type'] == 'enumration') {
                $this->generateEnumeration($node, $className);
            }
        }

        $this->generateRepository($repository);
        $this->generateDomain($domain);
        $this->generateBaseService();
        $this->generateApplication($application);
        $this->generateControllers($controllers);
        $this->generateException($exception);
        $this->generateConf();
        $this->generateModelTrait();

        // Copia a pasta offlines
        $files = $this->files['offlines'];
        foreach($files as $fileName) {
            $fileSource = $this->pathSource . DIRECTORY_SEPARATOR . 'offlines' . DIRECTORY_SEPARATOR . $fileName;
            $template = new MWizardTemplate();
            $template->copy($fileSource, "{$this->globals->moduleName}/src/offlines/{$fileName}", $this->pathTarget);
        }
        // Copia a pasta offlines
        $files = $this->files['utils'];
        foreach($files as $fileName) {
            $fileSource = $this->pathSource . DIRECTORY_SEPARATOR . 'utils' . DIRECTORY_SEPARATOR . $fileName;
            $template = new MWizardTemplate();
            $template->copy($fileSource, "{$this->globals->moduleName}/src/utils/{$fileName}", $this->pathTarget);
        }
        // Copia a pasta services/integration, se existir
        $files = $this->files['services']['integration'];
        if (count($files)) {
            mdump($files);
            foreach ($files as $system => $services) {
                foreach($services as $fileName) {
                    $fileSource = $this->pathSource . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'integration' . DIRECTORY_SEPARATOR . $system . DIRECTORY_SEPARATOR .$fileName;
                    $template = new MWizardTemplate();
                    $template->copy($fileSource, "{$this->globals->moduleName}/src/services/integration/{$system}/{$fileName}", $this->pathTarget);
                }
            }
        }
    }

    /**
     * GenerateControllers
     * @param $controllers
     */

    public function generateControllers($controllers)
    {
        foreach ($controllers as $controllerName => $actions) {
            mdump('handleController = ' . $controllerName);
            $node = $this->ini[$controllerName];
            $name = $node['name'];
            $lcName = strtolower($name);
            $nameController = $name . 'Controller';
            $fileController = $lcName . 'Controller';
            $methods = [];
            $uses = [];
            if ($this->files['controllers'][$fileController]) {
                $namespace = $this->globals->moduleName . "\\controllers\\" . $fileController;
                $oReflectionClass = new ReflectionClass($namespace);
                $fileName = $oReflectionClass->getFileName();
                $tokens = token_get_all(file_get_contents($fileName));
                $uses = TokenFinderTool::getUseDependencies($tokens);
                $fileClass = file($fileName);
                foreach ($oReflectionClass->getMethods() as $method) {
                    $shortName = $method->getDeclaringClass()->getShortName();
                    if ($shortName == $nameController) {
                        $methods[$method->name] = $method;
                    }
                }
            }
            $actionMethods = '';
            foreach ($actions as $action) {
                $actionName = $this->lcFirstLetter($action['name']);
                $command = [];
                if (is_array($action['includes'])) {
                    foreach ($action['includes'] as $service) {
                        $s = explode(',', $service);
                        $v = lcfirst($s[1]);
                        $command[] = "        \${$v} = \\Manager::getService('','{$this->globals->moduleName}', '{$s[3]}\\{$s[0]}\\{$s[1]}');";
                        $command[] = "        \${$v}();";
                    }
                }
                $body = "    {\n" . implode("\n", $command) . "    }\n";
                if ($methods[$actionName]) {
                    $body = $this->getMethodBody($methods[$actionName], $fileClass);
                }

                $actionMethods .= <<<HERE

    public function {$actionName}()
{$body}
HERE;

            }

            // Copia todas as views do controller (não cria views)
            $files = $this->files['views'][$lcName];
            foreach($files as $fileName) {
                $fileSource = $this->pathSource . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $lcName . DIRECTORY_SEPARATOR . $fileName;
                $template = new MWizardTemplate();
                $template->copy($fileSource, "{$this->globals->moduleName}/src/views/{$lcName}/{$fileName}", $this->pathTarget);
            }

            foreach ($methods as $method) {
                if ((!$method->isPublic()) || ($method->getName() == 'init')) {
                    //mdump($method);
                    $comment = $method->getDocComment();
                    $signature = $this->getMethodSignature($method, $fileClass);
                    $body = $this->getMethodBody($method, $fileClass);
                    $actionMethods .= <<<HERE
    {$comment}
{$signature}{$body}
HERE;
                }
            }
            $usesStr = '';
            foreach($uses as $i => $use) {
                $usesStr .= "use {$use};\n";
            }
            $var = array();
            $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
            $var['originalClass'] = $node['name'];
            $var['actions'] = $actionMethods;
            $var['package'] = $node['package'];
            $var['comment'] = base64_decode($node['comment']);
            $var['uses'] = $usesStr;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_controller.php');
            $template->applyClass();
            $template->saveResult("{$this->globals->moduleName}/src/controllers/{$fileController}.php", $this->pathTarget);
        }

    }

    /**
     * GenerateBaseService
     */

    public function generateBaseService()
    {
        $var = array();
        $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
        $var['package'] = $this->globals->packageName;
        $var['comment'] = '';
        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('public/files/templates/ddd_service_base.php');
        $template->apply();
        $template->saveResult("{$this->globals->moduleName}/src/services/BaseService.php", $this->pathTarget);

    }

    /**
     * Generate Application Services
     * @param $application
     */
    public function generateApplication($application)
    {
        foreach ($application as $applicationName => $node) {
            mdump('handleApplication = ' . $applicationName);
            $system = $node['system'];
            $name = $node['name'];
            $nameService = $node['name'] . 'Service';
            $var = array();
            $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
            $var['system'] = $system;
            $var['service'] = $name;
            $var['package'] = $node['package'];
            $var['comment'] = base64_decode($node['comment']);
            $i = $j = 0;
            $includes = $node['includes'];
            $servicesAttributes = $servicesParameters = $servicesSet = "";
            if (is_array($includes)) {
                foreach ($includes as $includeStr) {
                    $include = explode(',', $includeStr); // 0-system, 1-service,  2-'service'
                    $attribute = $this->lcFirstLetter($include[0]) . $include[1];
                    $servicesParameters .= ($i > 0 ? ",\n        " : "") . "\\" . $var['module'] . "\\services\\" . $include[3] . "\\" . $include[0] . "\\" . $include[1] . "Service " . "\$" . $attribute;
                    $servicesSet .= ($i > 0 ? "\n        " : "") . "\$this->" . $attribute . " = \$" . $attribute . ";";
                    $servicesAttributes .= "\n    protected \$" . $attribute . ";";
                    ++$i;
                }
                $var['servicesAttributes'] = $servicesAttributes;
                $var['servicesParameters'] = $servicesParameters;// . (($servicesParameters != '') ? ',' : '');
                $var['servicesSet'] = $servicesSet;
            }
            $methods = [];
            if ($this->files['services']['application'][$system][$nameService]) {
                $namespace = $this->globals->moduleName . "\\services\\application\\{$system}\\" . $nameService;
                $oReflectionClass = new ReflectionClass($namespace);
                $fileClass = file($oReflectionClass->getFileName());
                foreach ($oReflectionClass->getMethods() as $method) {
                    $shortName = $method->getDeclaringClass()->getShortName();
                    if ($shortName == $nameService) {
                        $methods[$method->name] = $method;
                    }
                }
            }

            $exceptions = $node['exception'];
            $throws = '';
            if (is_array($exceptions)) {
                foreach ($exceptions as $exception) {
                    $throws .= "\n     * @throws \\" . $this->globals->moduleName . "\\exceptions\\" . $exception;
                }
            }

            $body = "    {\n    }\n";
            if ($methods['run']) {
                $body = $this->getMethodBody($methods['run'], $fileClass);
            }
            $comment = base64_decode($node['comment']);
            $package = $node['package'];
            $applicationMethods = <<<HERE
    /**
     * {$comment}{$docParams}{$throws}    
     * @package {$package}
     * @return {$return}
     */
    public function run(\\PlainObject \$parametros = null)
{$body}
HERE;

            foreach ($methods as $method) {
                if (!$method->isPublic()) {
                    //mdump($method);
                    $comment = $method->getDocComment();
                    $signature = $this->getMethodSignature($method, $fileClass);
                    $body = $this->getMethodBody($method, $fileClass);
                    $applicationMethods .= <<<HERE
    {$comment}
{$signature}{$body}
HERE;
                }
            }

            $var['services'] = $applicationMethods;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_application_service.php');
            $template->apply();
            $template->saveResult("{$this->globals->moduleName}/src/services/application/{$system}/{$nameService}.php", $this->pathTarget);
        }
    }

    /**
     * Generate Domain Services
     * @param $domain
     */
    public function generateDomain($domain)
    {
        foreach ($domain as $domainName => $node) {
            mdump('handleDomain = ' . $domainName);
            $system = $node['system'];
            $name = $node['name'];
            $nameService = $node['name'] . 'Service';
            $var = array();
            $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
            $var['system'] = $system;
            $var['service'] = $name;
            $var['package'] = $node['package'];
            $var['comment'] = base64_decode($node['comment']);
            $i = $j = 0;
            $includes = $node['includes'];
            $servicesAttributes = $servicesParameters = $servicesSet = "";
            if (is_array($includes)) {
                foreach ($includes as $includeStr) {
                    $include = explode(',', $includeStr); // 0-system, 1-service,  2-'service'
                    $attribute = $this->lcFirstLetter($include[0]) . $include[1];
                    $servicesParameters .= ($i > 0 ? ",\n        " : "") . "\\" . $var['module'] . "\\services\\" . $include[3] . "\\" . $include[0] . "\\" . $include[1] . "Service " . "\$" . $attribute;
                    $servicesSet .= ($i > 0 ? "\n        " : "") . "\$this->" . $attribute . " = \$" . $attribute . ";";
                    $servicesAttributes .= "\n    protected \$" . $attribute . ";";
                    ++$i;
                }
                $var['servicesAttributes'] = $servicesAttributes;
                $var['servicesParameters'] = $servicesParameters;// . (($servicesParameters != '') ? ',' : '');
                $var['servicesSet'] = $servicesSet;
            }
            $methods = [];
            if ($this->files['services']['domain'][$system][$nameService]) {
                $namespace = $this->globals->moduleName . "\\services\\domain\\{$system}\\" . $nameService;
                $oReflectionClass = new ReflectionClass($namespace);
                $fileClass = file($oReflectionClass->getFileName());
                foreach ($oReflectionClass->getMethods() as $method) {
                    $shortName = $method->getDeclaringClass()->getShortName();
                    if ($shortName == $nameService) {
                        $methods[$method->name] = $method;
                    }
                }
            }


            $exceptions = $node['exception'];
            $throws = '';
            if (is_array($exceptions)) {
                foreach ($exceptions as $exception) {
                    $throws .= "\n     * @throws \\" . $this->globals->moduleName . "\\exceptions\\" . $exception;
                }
            }

            $i = $j = 0;
            $references = $node['references'];
            $reposAttributes = $reposParameters = $reposSet = "";
            $atomic = true;
            if (is_array($references)) {
                foreach ($references as $reference) {
                    $referenceNode = $this->ini[$reference];
                    if ($referenceNode['type'] == 'repository') {
                        $attrRef = $this->lcFirstLetter($reference);
                        $reposParameters .= ($i > 0 ? ",\n        " : "") . "\\" . $var['module'] . "\\contracts\\repository\\" . $reference . "Interface \$" . $attrRef;
                        $reposSet .= ($i > 0 ? "\n        " : "") . "\$this->" . $attrRef . " = \$" . $attrRef . ";";
                        $reposAttributes .= "\n    protected \$" . $attrRef . ";";
                        ++$i;
                    } else {
                        $referenceNode = $this->ini[$reference . 'Class'];
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
                                    $body = "    {\n    }\n";
                                    if ($methods[$operationName]) {
                                        $body = $this->getMethodBody($methods[$operationName], $fileClass);
                                    }
                                    $domainMethods .= <<<HERE
    /**
     * {$comment}{$docParams}{$throws}          
     * @return {$return}
     */
     public function {$operationName}({$parameters})
{$body}
HERE;

                                }
                            }
                        }
                    }
                }
                $var['reposAttributes'] = $reposAttributes;
                $var['reposParameters'] = $reposParameters;
                $var['reposSet'] = $reposSet;
                $var['servicesParameters'] .= ((($servicesParameters != '') && ($reposParameters != '')) ? ',' : '');
            }

            foreach ($methods as $method) {
                if (!$method->isPublic()) {
                    $comment = $method->getDocComment();
                    $signature = $this->getMethodSignature($method, $fileClass);
                    $body = $this->getMethodBody($method, $fileClass);
                    $domainMethods .= <<<HERE
    {$comment}
{$signature}{$body}
HERE;
                }
            }


            if ($atomic) {
                $body = "    {\n    }\n";
                $comment = '';
                $docParams = '';
                $return = '';
                if ($methods['run']) {
                    $body = $this->getMethodBody($methods['run'], $fileClass);
                }
                $domainMethods = <<<HERE

    /**
     * {$comment}{$docParams}{$throws}          
     * @return {$return}
     */
    public function run(\\PlainObject \$parametros = null)
{$body}

HERE;

            }
            $var['services'] = $domainMethods;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_domain_service.php');
            $template->apply();
            $template->saveResult("{$this->globals->moduleName}/src/services/domain/{$system}/{$nameService}.php", $this->pathTarget);
        }
    }

    /**
     * Generate Repositories
     * @param $repository
     */
    public function generateRepository($repository)
    {

        foreach ($repository as $repositoryName => $node) {
            mdump('handleRepository = ' . $repositoryName);
            $var = array();
            $var['originalClass'] = $repositoryName;
            $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
            $var['package'] = $node['package'] . "\\Contracts\\Repository";
            $var['comment'] = base64_decode($node['comment']);
            // handle Contracts
            $methods = [];
            $operations = $node['operations'];
            if (is_array($operations)) {
                $repositoryContracts = '';
                foreach ($operations as $operationName => $operationData) {
                    $signature = $this->getSignature($operationName, $operationData);
                    $repositoryContracts .= <<<HERE
    /**
     *
     * @return {$signature[1]}
     */
     public function {$signature[0]};

HERE;
                }
            }

            $var['contracts'] = $repositoryContracts;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_repository_interface.php');
            $template->apply();
            $template->saveResult("{$this->globals->moduleName}/src/contracts/repository/{$repositoryName}Interface.php", $this->pathTarget);

            // handle Persistence

            $methods = [];
            $persistences = ['maestro', 'redis'];
            foreach ($persistences as $persistence) {
                if ($this->files['persistence'][$persistence]['repositories'][$repositoryName]) {
                    $namespace = $this->globals->moduleName . "\\persistence\\{$persistence}\\repositories\\" . $repositoryName;
                    $oReflectionClass = new ReflectionClass($namespace);
                    $fileClass = file($oReflectionClass->getFileName());
                    foreach ($oReflectionClass->getMethods() as $method) {
                        $shortName = $method->getDeclaringClass()->getShortName();
                        if ($shortName == $repositoryName) {
                            $methods[$method->name] = $method;
                        }
                    }
                }
                $operations = $node['operations'];
                if (is_array($operations)) {
                    $repositoryMethods = '';
                    foreach ($operations as $operationName => $operationData) {
                        $signature = $this->getSignature($operationName, $operationData);
                        $body = "    {\n    }\n";
                        if ($methods[$operationName]) {
                            $body = $this->getMethodBody($methods[$operationName], $fileClass);
                        }
                        $repositoryMethods .= <<<HERE
    /**
     *
     * @return {$signature[1]}
     */
    public function {$signature[0]}
{$body}
HERE;
                    }
                }
                foreach ($methods as $method) {
                    if (!$method->isPublic()) {
                        $comment = $method->getDocComment();
                        $signature = $this->getMethodSignature($method, $fileClass);
                        $body = $this->getMethodBody($method, $fileClass);
                        $repositoryMethods .= <<<HERE
    {$comment}
{$signature}{$body}
HERE;
                    }
                }
                $references = $node['references'];
                if (is_array($references)) {
                    $repositoryUses = '';
                    foreach ($references as $refName => $reference) {
                        $repositoryUses .= "use {$this->globals->moduleName}\\persistence\\{$persistence}\\models\\{$reference} as {$reference}Persistente;\n";
                    }
                }
                $var['uses'] = $repositoryUses;
                $var['services'] = $repositoryMethods;
                $var['package'] = $node['package'] . "\\Persistence\\" . ucFirst($persistence) . "\\Repositories";

                $template = new MWizardTemplate();
                $template->setVar($var);
                $template->setTemplate("/public/files/templates/ddd_repository_persistence_{$persistence}.php");
                $template->apply();
                $template->saveResult("{$this->globals->moduleName}/src/persistence/{$persistence}/repositories/{$repositoryName}.php", $this->pathTarget);
            }
        }
    }

    /**
     * Generate Exceptions
     * @param $exception
     */
    public function generateException($exception)
    {
        foreach ($exception as $exceptionName => $node) {
            mdump('handleException = ' . $exceptionName);
            $name = $exceptionName;
            $var = array();
            $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
            $var['exception'] = $name;
            $var['package'] = $node['package'];
            $var['comment'] = base64_decode($node['comment']);
            $i = $j = 0;
            $exceptionAttributes = $exceptionParameters = $exceptionSet = "";
            $attributes = $node['attributes'];
            if (is_array($attributes)) {
                foreach ($attributes as $attributeName => $attributeStr) {
                    $attribute = explode(',', $attributeStr); // 0-type, 1-comment'
                    $exceptionParameters .= ($i > 0 ? ",\n        " : "") . $attribute[0] . " \$" . $attributeName;
                    $exceptionSet .= ($i > 0 ? "\n        " : "") . "\$this->" . $attributeName . " = \$" . $attributeName . ";";
                    $exceptionAttributes .= "\n    protected \$" . $attributeName . ";";
                    ++$i;
                }
                $var['exceptionAttributes'] = $exceptionAttributes;
                $var['exceptionParameters'] = $exceptionParameters;
            }
            $methods = [];
            if ($this->files['exceptions'][$name]) {
                $namespace = $this->globals->moduleName . "\\exceptions\\" . $name;
                $oReflectionClass = new ReflectionClass($namespace);
                $fileClass = file($oReflectionClass->getFileName());
                foreach ($oReflectionClass->getMethods() as $method) {
                    $shortName = $method->getDeclaringClass()->getShortName();
                    if ($shortName == $name) {
                        $methods[$method->name] = $method;
                    }
                }
            }
            $exceptionBody = "public function __construct(\n {$exceptionParameters}\n    )\n    {\n        {$exceptionSet}\n        parent::__construct(\$msg, \$code);\n    }\n";
            if ($methods['__construct']) {
                $body = $this->getMethodBody($methods['__construct'], $fileClass);
                $comment = $method->getDocComment();
                $signature = $this->getMethodSignature($method, $fileClass);
                $exceptionBody = <<<HERE
    {$comment}
    public function __construct(\n{$body}
HERE;

            }
            $var['exceptionBody'] = $exceptionBody;
            $exceptionMethods = '';
            foreach ($methods as $method) {
                if (!$method->isPublic() && ($method->getName() != '__construct')) {
                    $comment = $method->getDocComment();
                    $signature = $this->getMethodSignature($method, $fileClass);
                    $body = $this->getMethodBody($method, $fileClass);
                    $exceptionMethods .= <<<HERE
    {$comment}
{$signature}{$body}
HERE;
                }
            }
            $var['exceptionMethods'] = $exceptionMethods;
            $template = new MWizardTemplate();
            $template->setVar($var);
            $template->setTemplate('/public/files/templates/ddd_exception.php');
            $template->apply();
            $template->saveResult("{$this->globals->moduleName}/src/exceptions/{$name}.php", $this->pathTarget);
        }
    }
    /**
     * Generate Model
     * @param $node
     * @param $className
     */
    public function generateModel($node, $className)
    {
        mdump('handleModel = ' . $className);
        $lcClassName = strtolower($className);
        $classComment = base64_decode($node['comment']);

        $var = array();
        $var['class'] = $lcClassName;
        $var['originalClass'] = $className;
        $var['model'] = $className;
        $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
        $var['package'] = $node['package'];

        $methods = [];
        if ($this->files['models'][$className]) {
            $namespace = $this->globals->moduleName . "\\models\\" . $className;
            $oReflectionClass = new ReflectionClass($namespace);
            $fileClass = file($oReflectionClass->getFileName());
            foreach ($oReflectionClass->getMethods() as $method) {
                $shortName = $method->getDeclaringClass()->getShortName();
                if ($shortName == $className) {
                    $methods[$method->name] = $method;
                }
            }
        }

        $getterSetter = $properties = '';
        $initAttr = '';
        $attributes = $node['attributes'];
        foreach ($attributes as $attributeName => $attributeData) {
            $at = explode(',', $attributeData);
            // atData:
            // 0 - column
            // 1 - type
            // 2 - null or not null
            // 3 - key type
            // 4 - generator
            // 5 - comment
            $isPK = ($at[3] == 'primary');
            $isFK = ($at[3] == 'foreign');
            $ucAttributeName = ucfirst($attributeName);
            $attributeType = $at[1];
            $lowerAttrType = strtolower($attributeType);
            $attrComment = base64_decode($at[5]);
            $setterBody = "\$this->{$attributeName} = \$value;\n        return \$this;";
            if ($lowerAttrType == 'currency') {
                $setterBody = "if (!(\$value instanceof \\MCurrency)) {\n            \$value = new \\MCurrency((float) \$value);\n        }\n        ";
                $initAttr .= "        \$this->{$attributeName} = null;\n";
            } elseif ($lowerAttrType == 'date') {
                $setterBody = "if (!(\$value instanceof \\MDate)) {\n            \$value = new \\MDate(\$value);\n        }\n        ";
                $initAttr .= "        \$this->{$attributeName} = null;\n";
            } elseif ($lowerAttrType == 'timestamp') {
                $setterBody = "if (!(\$value instanceof \\MTimeStamp)) {\n            \$value = new \\MTimeStamp(\$value);\n        }\n        ";
                $initAttr .= "        \$this->{$attributeName} = null;\n";
            } elseif ($lowerAttrType == 'cpf') {
                $setterBody = "if (!(\$value instanceof \\MCPF)) {\n            \$value = new \\MCPF(\$value);\n        }\n        ";
                $initAttr .= "        \$this->{$attributeName} = null;\n";
            } elseif ($lowerAttrType == 'cnpj') {
                $setterBody = "if (!(\$value instanceof \\MCNPJ)) {\n            \$value = new \\MCNPJ(\$value);\n        }\n        ";
                $initAttr .= "        \$this->{$attributeName} = null;\n";
            } elseif ($lowerAttrType == 'boolean') {
                $setterBody = "\$value = ((\$value != '0') && (\$value != 0) && (\$value != ''));\n        ";
                $initAttr .= "        \$this->{$attributeName} = false;\n";
            } elseif (strpos($lowerAttrType, 'enum') !== false) {
                $setterBody = "\$valid = false;\n" .
                    "        if (empty(\$value)) {\n" .
                    "            \$config = \$this->config();\n" .
                    "            \$valid = !array_search('notnull',\$config['validators']['{$attributeName}']);\n" .
                    "        }\n" .
                    "        if (!(\$valid || {$attributeType}Map::isValid(\$value))) {\n" .
                    "            throw new \EModelException('Valor inválido para a Enumeração {$attributeType}');\n" .
                    "        }\n        ";
            } elseif ($lowerAttrType == 'integer') {
                $initAttr .= "        \$this->{$attributeName} = 0;\n";
            } elseif ($lowerAttrType == 'string') {
                $initAttr .= "        \$this->{$attributeName} = '';\n";
            }

            if ($isPK) {
                $this->data[$className]['pkName'] = $attributeName;
            }

            if (!$isFK) {
                $properties .= "\n    /**\n     * {$attrComment}\n     * @var {$attributeType} \n     */";
                $properties .= "\n    protected " . "\$" . $attributeName . ";";
                $getOperation = "get" . ucfirst($attributeName);
                $body = "    {\n        return \$this->{$attributeName};\n    }\n";
                if ($methods[$getOperation]) {
                    $body = $this->getMethodBody($methods[$getOperation], $fileClass);
                }
                $getterSetter .= <<<HERE
    /**
     *
     * @return {$attributeType}
     */
    public function get{$ucAttributeName}()
{$body}
HERE;
                $setOperation = "set" . ucfirst($attributeName);
                $body = "    {\n        {$setterBody}\n    }\n";
                if ($methods[$setOperation]) {
                    $body = $this->getMethodBody($methods[$setOperation], $fileClass);
                }

                $getterSetter .= <<<HERE
    /**
     *
     * @param {$attributeType} \$value
     */
    public function set{$ucAttributeName}(\$value)
{$body}
HERE;
            }
        }
        $associations = $node['associations'];
        if (is_array($associations)) {
            foreach ($associations as $associationName => $associationData) {
                $assoc = explode(',', $associationData);
                // assoc:
                // 0 - toClass
                // 1 - cardinality
                // 2 - keys or associative
                $attributeType = "\\" . str_replace("persistence\\maestro\\", '', $assoc[0]);
                $keys = explode(':', $assoc[2]);
                $aToClass = explode('\\', $assoc[0]);
                $toClass = $aToClass[count($aToClass) - 1];
                $ucAssociationName = ucfirst($associationName);
                if ($assoc[1] == 'oneToOne') {
                    $type = $toClass;
                    $uKey = ucFirst($keys[1]);
                    $setterBody = "parent::set{$ucAssociationName}(\$value);\n        \$this->set{$keys[0]}(\$value->get{$uKey}());";
                    $initAttr .= "        \$this->{$associationName} = null;\n";
                } else {
                    $type = "Association [{$toClass}]";
                    $setterBody =  "\$this->{$attributeName} = \$value;\n        return \$this;";
                    $initAttr .= "        \$this->{$associationName} = new \\ArrayObject([]);\n";
                }

                $properties .= "\n    /**\n     * {$attrComment}\n     * @var {$type} \n     */";
                $properties .= "\n    protected " . "\$" . $associationName . ";";
                $getOperation = "get" . $ucAssociationName;
                $body = "    {\n        return \$this->{$attributeName};\n    }\n";
                if ($methods[$getOperation]) {
                    $body = $this->getMethodBody($methods[$getOperation], $fileClass);
                }
                $getterSetter .= <<<HERE
    /**
     *
     * @return {$attributeType}
     */
    public function get{$ucAssociationName}()
{$body}
HERE;
                $setOperation = "set" . $ucAssociationName;
                $body = "    {\n        {$setterBody}\n    }\n";
                if ($methods[$setOperation]) {
                    $body = $this->getMethodBody($methods[$setOperation], $fileClass);
                }

                $getterSetter .= <<<HERE
    /**
     *
     * @param {$attributeType} \$value
     */
    public function set{$ucAssociationName}({$attributeType} \$value)
{$body}
HERE;

            }
        }

        $construct = <<<HERE
/**
     *  Construct
     */
    public function __construct() 
    {
        parent::__construct();
{$initAttr}    }

HERE;
        $privateOperations = '';
        foreach ($methods as $method) {
            if (!$method->isPublic()) {
                $comment = $method->getDocComment();
                $signature = $this->getMethodSignature($method, $fileClass);
                $body = $this->getMethodBody($method, $fileClass);
                $privateOperations .= <<<HERE
    {$comment}
{$signature}{$body}
HERE;
            }
        }
        $operations = $node['operations'];
        $modelOperations = '';
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
                    $parameters = implode(',', $opData);
                    foreach ($opData as $p) {
                        $v = explode(' ', $p);
                        $parametersVar .= $v[1];
                    }
                }
                $body = "    {\n    }\n";
                if ($methods[$operationName]) {
                    $body = $this->getMethodBody($methods[$operationName], $fileClass);
                }

                $modelOperations .= <<<HERE
    /**
     * {$comment}
     * @return {$return}
     */
    public function {$operationName}({$parameters})
{$body}

HERE;
            }
        }

        $var['properties'] = $properties;
        $var['methods'] = $construct . $getterSetter . $modelOperations . $privateOperations;
        $var['comment'] = $classComment;
        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_model.php');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/src/models/{$className}.php", $this->pathTarget);
    }
    /**
     * Generate Persistent Model
     * @param $node
     * @param $className
     */
    public function generatePersistentModel($node, $className)
    {
        mdump('handlePersistentModel  = ' . $className);
        $tab = '    ';
        $lcClassName = strtolower($className);

        $var = array();
        $var['class'] = $lcClassName;
        $var['originalClass'] = $className;
        $var['model'] = $className;
        $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
        $var['package'] = $node['package'];

        $methods = [];
        if ($this->files['persistence']['maestro']['models'][$className]) {
            $namespace = $this->globals->moduleName . "\\persistence\\maestro\\models\\" . $className;
            $oReflectionClass = new ReflectionClass($namespace);
            $fileClass = file($oReflectionClass->getFileName());
            foreach ($oReflectionClass->getMethods() as $method) {
                $name = $method->getDeclaringClass()->getName();
                if ($name == $namespace) {
                    $methods[$method->name] = $method;
                }
            }
        }

        $propertiesPersistence = $validators = '';
        $extends = $node['extends'];
        $log = $node['log'];

        $document = $ormmap = $docassoc = $docattr = $attributes = [];
        $document[] = '';
        $document[] = $tab . 'public static function ORMMap() {';
        $document[] = '';
        $ormmap[] = $tab . $tab . 'return [';
        $ormmap[] = $tab . $tab . $tab . "'class' => \get_called_class(),";
        $ormmap[] = $tab . $tab . $tab . "'database' => " . (substr($this->globals->dbName, 0, 1) == "\\" ? $this->globals->dbName . ',' : "'{$this->globals->dbName}',");
        $tableName = $node['table'];
        $ormmap[] = $tab . $tab . $tab . "'table' => '{$tableName}',";
        if ($extends) {
            $ormmap[] = $tab . $tab . $tab . "'extends' => '{$extends}',";
        }

        $pk = '';
        $getterSetterPersistence = "";
        $attributes = $node['attributes'];
        foreach ($attributes as $attributeName => $attributeData) {
            $isPK = $isFK = false;
            $ucAttributeName = ucfirst($attributeName);
            $at = explode(',', $attributeData);
            // atData:
            // 0 - column
            // 1 - type
            // 2 - null or not null
            // 3 - key type
            // 4 - generator
            // 5 - comment
            $attrComment = base64_decode($at[5]);
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
            $attributeType = $at[1];
            $attribute .= ",'type' => '{$attributeType}'],";

            if ($isFK) {
                $propertiesPersistence .= <<<HERE
    /**
     * {$attrComment}
     * @var {$attributeType}
     */
    protected \${$attributeName};
HERE;

                $getOperation = "get" . ucfirst($attributeName);
                $body = "    {\n        return \$this->{$attributeName};\n    }\n";
                if ($methods[$getOperation]) {
                    $body = $this->getMethodBody($methods[$getOperation], $fileClass);
                }
                $getterSetterPersistence .= <<<HERE
    /**
     *
     * @return {$attributeType}
     */
    public function get{$ucAttributeName}()
{$body}
HERE;
                $setOperation = "set" . ucfirst($attributeName);
                $body = "    {\n        ". "\$this->{$attributeName} = \$value;\n        return \$this;" . "\n    }\n";
                if ($methods[$setOperation]) {
                    $body = $this->getMethodBody($methods[$setOperation], $fileClass);
                }

                $getterSetterPersistence .= <<<HERE
    /**
     *
     * @param {$attributeType} \$value
     */
    public function set{$ucAttributeName}(\$value)
{$body}
HERE;
            }
            $docattr[] = $tab . $attribute;

        }

        $docassoc = array();
        $associations = $node['associations'];
        if (is_array($associations)) {
            foreach ($associations as $associationName => $associationData) {
                $assoc = explode(',', $associationData);
                // assoc:
                // 0 - toClass
                // 1 - cardinality
                // 2 - keys or associative
                $attributeType = "\\" .str_replace("persistence\\maestro\\", '', $assoc[0]);
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
                    $type = $attributeType;
                    $uKey = ucFirst($keys[1]);
                    $set = "parent::set{$ucAssociationName}(\$value);\n        \$this->set{$keys[0]}(\$value->get{$uKey}());\n        ";
                } else {
                    $type = "\Association {$attributeType}";
                    $set = null;
                }

                $propertiesPersistence .= "\n    /**\n     * {$attrComment}\n     * @var {$type} \n     */";
                $propertiesPersistence .= "\n    protected " . "\$" . $associationName . ";";
                $getOperation = "get" . $ucAssociationName;
                $body = "    {\n        return \$this->getAssociation(\"{$associationName}\");\n    }\n";
                if ($methods[$getOperation]) {
                    $body = $this->getMethodBody($methods[$getOperation], $fileClass);
                }
                $getterSetterPersistence .= <<<HERE
    /**
     *
     * @return {$type}
     */
    public function get{$ucAssociationName}()
{$body}
HERE;
                $setOperation = "set" . $ucAssociationName;
                $body = "    {\n        {$set}\$this->{$associationName} = \$value; return \$this;\n    }\n";
                if ($methods[$setOperation]) {
                    $body = $this->getMethodBody($methods[$setOperation], $fileClass);
                }

                $getterSetterPersistence .= <<<HERE
    /**
     *
     * @param {$attributeType} \$value
     */
    public function set{$ucAssociationName}({$attributeType} \$value)
{$body}
HERE;
                $docassoc[] = $tab . $association;
            }
        }

        $privateOperations = '';
        foreach ($methods as $method) {
            if (!$method->isPublic()) {
                $comment = $method->getDocComment();
                $signature = $this->getMethodSignature($method, $fileClass);
                $body = $this->getMethodBody($method, $fileClass);
                $privateOperations .= <<<HERE
    {$comment}
{$signature}{$body}
HERE;
            }
        }

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
                    foreach ($opData as $p) {
                        $v = explode(' ', $p);
                        $parametersVar .= $v[1];
                    }
                }

                $body = "    {\n        return parent::{$operationName}({$parametersVar});\n    }\n";
                if ($methods[$operationName]) {
                    $body = $this->getMethodBody($methods[$operationName], $fileClass);
                }

                $modelOperationsPersistence .= <<<HERE
    /**
     * {$comment}
     * @return {$return}
     */
    public function {$operationName}({$parameters})
{$body}
HERE;
            }
        }
        $description = $node['description'] ?: $pk;

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
        $var['propertiesPersistence'] = $propertiesPersistence;
        $var['methodsPersistence'] = $getterSetterPersistence . $modelOperationsPersistence . $privateOperations;
        $var['comment'] = '';
        $var['ormmap'] = $map;
        $var['extends'] = $extends;
        $var['description'] = $description;
        $var['configLog'] = $configLog;
        $var['configValidators'] = $configValidators;
        $var['configConverters'] = $configConverters;

        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_model_persistence_maestro.php');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/src/persistence/maestro/models/{$className}.php", $this->pathTarget);


        // define actions
        //$upperClass = ucFirst($className);
        //$actions[] = $tab . $tab . "'{$className}' => ['{$upperClass}', '{$moduleName}/{$className}/main', '{$moduleName}IconForm', '', A_ACCESS, []],";

    }

    /**
     * GenerateModelTrait
     * @param
     */
    public function generateModelTrait()
    {
        $var = array();
        $var['module'] = $this->globals->moduleName ?: $this->globals->appName;
        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_modeltrait.php');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/src/persistence/maestro/models/ModelTrait.php", $this->pathTarget);
    }

    /**
     * Generate Enumeration
     * @param $node
     */
    public function generateEnumeration($node, $className)
    {
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

    /**
     * Generate Conf
     */
    public function generateConf()
    {

        $var['module'] = $this->globals->moduleName ?: $this->globals->appName;

        $actions[] = "    '{$var['module']}' => ['{$var['module']}', '{$var['module']}/main/main', 'sigaIconMAD', '', A_ACCESS, [\n    ]]\n";

// create Actions

        $var['actions'] = implode("\n", $actions);
        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_actions.php');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/conf/actions.php", $this->pathTarget);

// create Conf
        $template = new MWizardTemplate();
        $template->setTemplate('/public/files/templates/ddd_conf.php');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/conf/conf.php", $this->pathTarget);

// create Container and Injections
        $template = new MWizardTemplate();
        $template->setTemplate('/public/files/templates/ddd_container.php');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/conf/container.php", $this->pathTarget);

        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_injections.php');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/conf/injections.php", $this->pathTarget);

// create Main

        $template = new MWizardTemplate();
        $template->setVar($var);
        $template->setTemplate('/public/files/templates/ddd_main.xml');
        $template->applyClass();
        $template->saveResult("{$this->globals->moduleName}/src/views/main/main.xml", $this->pathTarget);

        $template->setTemplate('/public/files/templates/ddd_mainController.php');
        $template->apply();
        $template->saveResult("{$this->globals->moduleName}/src/controllers/mainController.php", $this->pathTarget);

    }

    public function getSignature($operationName, $operationData = [])
    {
        $opData = explode(',', $operationData);
        $parameters = '';
        $return = array_shift($opData);
        if (count($opData)) {
            $parameters = implode(', ', $opData);
        }
        return [$operationName . '(' . $parameters . ')', $return];
    }

    public function getMethodBody($method, $file)
    {
        $methodBody = '';
        for ($i = $method->getStartLine(); $i < $method->getEndLine(); $i++) {
            $methodBody .= $file[$i];
        }
        if (trim($methodBody) == '') {
            $methodBody = "    {\n    }\n";
        }
        return $methodBody . "\n";
    }

    public function getMethodSignature($method, $file)
    {
        $methodSignature = '';
        for ($i = $method->getStartLine() - 1; $i < $method->getStartLine(); $i++) {
            $methodSignature .= $file[$i];
        }
        return $methodSignature;
    }

    public function lcFirstLetter($string)
    {
        return (ctype_upper($string{1}) ? $string : lcFirst($string));
    }
}
