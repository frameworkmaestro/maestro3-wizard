<?php

class MainController extends MController {

    public function init() {
        //Manager::getPage()->setTemplateName('content');
    }

    public function main() {
        $this->render();
    }

    public function formORM() {
        $this->render();
    }

    public function createORM() {
        $fileName = $this->data->xmi;
        $moduleName = $this->data->module;
        $databaseName = $this->data->database;
        $package = $this->data->package;
        $fileXMI = Manager::getAppPath('public/files/xmi/') . $fileName;

        $xmi = new MWizardORM();
        $baseDir = Manager::getOptions('basePath');
        $xmi->setBaseDir($baseDir);
        $xmi->setFile($fileXMI);
        $xmi->setModuleName($moduleName);
        $xmi->setDatabaseName($databaseName);
        $xmi->setPackage($package);
        $xmi->generate();
        if (count($xmi->errors)) {
            $this->renderPrompt('error', $xmi->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }

    public function formXMIScript() {
        $this->render();
    }

    public function createXMIScript() {
        $fileName = $this->data->xmi;
        $appName = $this->data->app;
        $moduleName = $this->data->module;
        $databaseName = $this->data->database;
        $package = $this->data->package;
        $fileXMI = Manager::getAppPath('public/files/xmi/') . $fileName;

        if (!file_exists($fileXMI)) {
            $fileXMI.=".xmi";
            if (!file_exists($fileXMI)) {
                throw new Exception("Arquivo XMI inexistente em public/files/xmi/");
            }
        }

        $baseDir = Manager::getAppPath('public/files/scripts');
        $xmi = new MWizardXMIScript();
        $baseDir = Manager::getOptions('basePath') . '/scripts';
        $xmi->setBaseDir($baseDir);
        $xmi->setFile($fileXMI);
        $xmi->setAppName($appName);
        $xmi->setModuleName($moduleName);
        $xmi->setDatabaseName($databaseName);
        $xmi->setPackage($package);
        $xmi->setKeepModelNames($this->data->keepModelNames);
        $xmi->setDDD($this->data->ddd);
        $xmi->setSchemaName($this->data->schema);
        $xmi->generate();
        if (count($xmi->errors)) {
            $this->renderPrompt('error', $xmi->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }

    public function formXMIScriptDDD() {
        $this->render();
    }

    public function createXMIScriptDDD() {
        $fileName = $this->data->xmi;
        $appName = $this->data->app;
        $moduleName = $this->data->module;
        $databaseName = $this->data->database;
        $prefix = $this->data->prefix;
        $fileXMI = Manager::getAppPath('public/files/xmi/') . $fileName;

        if (!file_exists($fileXMI)) {
            $fileXMI.=".xmi";
            if (!file_exists($fileXMI)) {
                throw new Exception("Arquivo XMI inexistente em public/files/xmi/");
            }
        }

        //$baseDir = Manager::getAppPath('public/files/scripts');
        $xmi = new MWizardXMIScriptDDD();
        $baseDir = Manager::getOptions('basePath') . '/scripts';
        $xmi->setBaseDir($baseDir);
        $xmi->setFile($fileXMI);
        $xmi->setAppName($appName);
        $xmi->setModuleName($moduleName);
        $xmi->setDatabaseName($databaseName);
        $xmi->setPrefix($prefix);
        $xmi->generate();
        if (count($xmi->errors)) {
            $this->renderPrompt('error', $xmi->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }

    public function formScriptStructure() {
        $this->render();
    }

    public function createScriptStructure() {
        $fileName = $this->data->script;
        $fileScript = Manager::getAppPath('public/files/scripts/') . $fileName;

        if (!file_exists($fileScript)) {
            $fileScript.=".txt";
            if (!file_exists($fileScript)) {
                throw new Exception("Arquivo Script inexistente");
            }
        }

        $script = new MWizardScript();
        $baseDir = Manager::getOptions('basePath') . '/structure';
        $script->setBaseDir($baseDir);
        $script->setFile($fileScript);
        $script->setKeepModelNames($this->data->keepModelNames);
        $script->generate();
        if (count($script->errors)) {
            $this->renderPrompt('error', $script->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }

    public function formScriptStructureDDD() {
        $this->render();
    }

    public function createScriptStructureDDD() {
        $fileName = $this->data->script;
        $fileScript = Manager::getAppPath('public/files/scripts/') . $fileName;

        if (!file_exists($fileScript)) {
            $fileScript.=".txt";
            if (!file_exists($fileScript)) {
                throw new Exception("Arquivo Script inexistente");
            }
        }

        $script = new MWizardScriptDDD();
        $baseDir = Manager::getOptions('basePath') . '/structure';
        $script->setBaseDir($baseDir);
        $script->setFile($fileScript);
        $script->generate();
        if (count($script->errors)) {
            $this->renderPrompt('error', $script->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }

    public function formScriptDDDUpdate() {
        $this->render();
    }

    public function updateScriptStructureDDD() {
        $fileScript = Manager::getAppPath('public/files/scripts/') . $this->data->script;
        if (!file_exists($fileScript)) {
            $fileScript.=".txt";
            if (!file_exists($fileScript)) {
                throw new Exception("Arquivo Script inexistente");
            }
        }

        $pathSource = $this->data->source;
        if (!file_exists($pathSource)) {
            throw new Exception("Path Source inexistente");
        }

        $script = new MWizardScriptDDDUpdate();
        $baseDir = Manager::getOptions('basePath') . '/structure';
        $script->setPathTarget($baseDir);
        $script->setPathSource($pathSource);
        $script->setFileScript($fileScript);
        $script->generate();
        if (count($script->errors)) {
            $this->renderPrompt('error', $script->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }

    public function formYAMLDDDUpdate() {
        $this->render();
    }

    public function updateYAMLStructureDDD() {
        $fileScript = Manager::getAppPath('public/files/scripts/') . $this->data->yaml;
        if (!file_exists($fileScript)) {
            $fileScript.=".yml";
            if (!file_exists($fileScript)) {
                throw new Exception("Arquivo YAML inexistente");
            }
        }

        $pathSource = $this->data->source;
        if (!file_exists($pathSource)) {
            throw new Exception("Path Source inexistente");
        }

        $script = new MWizardYAMLDDDUpdate();
        $baseDir = Manager::getOptions('basePath') . '/structure';
        $script->setPathTarget($baseDir);
        $script->setPathSource($pathSource);
        $script->setFileScript($fileScript);
        $script->generate();
        if (count($script->errors)) {
            $this->renderPrompt('error', $script->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }

    public function formCSS() {
        $this->render();
    }

    public function createCSS() {
        $images = array();
        $results = array();
        $name = array();
        $app = $this->data->app;
        $images = scandir("/home/ematos/public_html/maestro/apps/{$app}/public/images/32x32");
        foreach ($images as $image) {
            preg_match_all("/^[a-zA-Z0-9-_]+[^\.]/", $image, $results[]);
        }
        $i = 0;
        foreach ($results as $r) {
            foreach ($r as $result) {
                $filename = $result[0];
                if ($filename{0} != NULL) {
                    $result[0] = ucfirst($result[0]);
                    $name[] = '.appIcon' . $result[0] . "{\nbackground-image: url(../images/32x32/" . $images[$i] . ");\n}\n";
                }
                $i++;
            }
        }
        $template = new MWizardTemplate();
        $template->resultFile = implode('', $name);
        $template->saveResult("/{$app}/public/css/style.css", 'public/files/base');
        $this->renderPrompt('information', 'Arquivo gerado com sucesso!');
    }

    public function formReverseMySQL() {
        $this->render();
    }

    public function reverseMySQL() {
        $script = new MReverseMySQL();
        $baseDir = Manager::getOptions('basePath');
        $script->setBaseDir($baseDir);
        $script->setFile($this->data->script);
        $script->setDatabaseName($this->data->database);
        $script->setAppName($this->data->app);
        $script->setModuleName($this->data->module);
        $script->generate();
        if (count($script->errors)) {
            $this->renderPrompt('error', $script->errors);
        } else {
            $this->renderPrompt('information', 'Arquivos gerados com sucesso em ' . $baseDir);
        }
    }
}