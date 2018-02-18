<?php

class MWizardTemplate {

    public function setTemplate($template) {
        $this->template = Manager::getAppPath($template);
        $this->templateFile = file_get_contents($this->template);
    }

    public function setVar($var) {
        $this->var = $var;
    }

    public function setFields($fields) {
        $this->fields = $fields;
        $this->fieldsControls = array();
        $_model = $this->var['model'];
        $_modelC = ucfirst($this->var['model']);
        $this->var['lookupForeginInstance']  = "";
        foreach($this->fields['attributes'] as $name => $field){

            $nameC = ucfirst($name);
            if ($field['key'] == 'primary') {
                $this->pkName = $name;
                $this->fieldsControls[] = "<mhiddenfield id=\"{$_model}::{$name}\" />";
            } elseif ($field['type'] == 'string') {
                $this->fieldsControls[] = "<mtextfield id=\"{$_model}::{$name}\" label=\"{$nameC}\" />";
            } elseif ($field['type'] == 'boolean') {
                $this->fieldsControls[] = "<mbooleanfield id=\"{$_model}::{$name}\" label=\"{$nameC}\" />";
            } elseif ($field['type'] == 'date') {
                $this->fieldsControls[] = "<mcalendarfield id=\"{$_model}::{$name}\" label=\"{$nameC}\" />";
            } elseif ($field['type'] == 'timestamp') {
                $this->fieldsControls[] = "<mtimestampfield id=\"{$_model}::{$name}\" label=\"{$nameC}\" />";
            } elseif ($field['type'] == 'currency') {
                $this->fieldsControls[] = "<mcurrencyfield id=\"{$_model}::{$name}\" label=\"{$nameC}\" />";
            } elseif ($field['type'] == 'text') {
                $this->fieldsControls[] = "<mmultilinefield id=\"{$_model}::{$name}\" label=\"{$nameC}\"  rows=\"5\" cols=\"50\"/>";
            } elseif ($field['type'] == 'blob') {
                $this->fieldsControls[] = "<mfilefield id=\"{$_model}::{$name}\" label=\"{$_model}::{$nameC}\" multiple=\"false\" text=\"Selecionar Arquivo\" />";
            } elseif ($field['type'] == 'integer') {

                if ($field['key']=='foreign')
                {
                	//Supondo idChaveEstrangeira
                	$foreignField =substr($name,2) ; //retira o id do campo idChaveEstrangeira
                	$foreignFieldLower = strtolower($foreignField);
                	 
                	 $lkp = "<mlookupfieldvalue id=\"{$_model}::{$name}Desc\"  label=\"{$foreignField}\"  related=\"{$_model}::{$name}:{$name},{$_model}::{$name}Desc:descricao\" action=\"{$this->var['module']}/{$foreignFieldLower}/lookup\" />";		
                	 $lkp .= "<mhiddenfield id=\"{$_model}::{$name}\" />";	

                	 $this->fieldsControls[] = $lkp;

                	 $this->var['lookupForeginInstance'] .= "\$this->data->{$_model}->{$name}Desc = \${$_model}->get{$foreignField}()->getDescription();\n\t";//$_modelC::create(\$this->data->id)->get{$foreignField}()->getDescription();\n\t";
                }
                else
                {
                	$this->fieldsControls[] = "<mnumberfield id=\"{$_model}::{$name}\" label=\"{$nameC}\" />";	
                }
            }
        }
          
    }

    public function apply() {
        $result = str_replace('$_classC', $this->var['classCase'], $this->templateFile);
        $result = str_replace('$_classO', $this->var['originalClass'], $result);
        $result = str_replace('$_classL', lcfirst($this->var['originalClass']), $result);
        $result = str_replace('$_controllerL', strtolower($this->var['originalClass']), $result);
        $result = str_replace('$_lookupForeginInstance', $this->var['lookupForeginInstance'], $result);
        $result = str_replace('$_class', $this->var['class'], $result);
        $result = str_replace('$_db', $this->var['db'], $result);
        $result = str_replace('$_modulemain', $this->var['module'] .'/main', $result);
        $result = str_replace('$_moduleC', ucfirst($this->var['module']), $result);
        $result = str_replace('$_module', $this->var['module'], $result);
        $result = str_replace('$_modulemain', $this->var['module'] .'/main', $result);
        $result = str_replace('$_modelC', ucfirst($this->var['model']), $result);
        $result = str_replace('$_model', $this->var['model'], $result);
        $result = str_replace('$_comment', $this->var['comment'], $result);
        $result = str_replace('$_package', $this->var['package'], $result);
        $result = str_replace('$_lookupC', ucfirst($this->var['lookup']), $result);
        $result = str_replace('$_lookup', $this->var['lookup'], $result);
        $result = str_replace('$_descfield', $this->var['descfield'], $result);
        $result = str_replace('$_desclabel', $this->var['desclabel'], $result);
        $result = str_replace('$_servicesAttributes', $this->var['servicesAttributes'], $result);
        $result = str_replace('$_servicesParameters', $this->var['servicesParameters'], $result);
        $result = str_replace('$_servicesSet', $this->var['servicesSet'], $result);
        $result = str_replace('$_services', $this->var['services'], $result);
        $result = str_replace('$_service', $this->var['service'], $result);
        $result = str_replace('$_exceptionAttributes', $this->var['exceptionAttributes'], $result);
        $result = str_replace('$_exceptionParameters', $this->var['exceptionParameters'], $result);
        $result = str_replace('$_exceptionSet', $this->var['exceptionSet'], $result);
        $result = str_replace('$_exceptionBody', $this->var['exceptionBody'], $result);
        $result = str_replace('$_exceptionMethods', $this->var['exceptionMethods'], $result);
        $result = str_replace('$_exception', $this->var['exception'], $result);
        $result = str_replace('$_contracts', $this->var['contracts'], $result);
        $result = str_replace('$_usesMaestro', $this->var['usesMaestro'], $result);
        $result = str_replace('$_usesRedis', $this->var['usesRedis'], $result);
        $result = str_replace('$_uses', $this->var['uses'], $result);
        $result = str_replace('$_reposAttributes', $this->var['reposAttributes'], $result);
        $result = str_replace('$_reposParameters', $this->var['reposParameters'], $result);
        $result = str_replace('$_reposSet', $this->var['reposSet'], $result);
        $result = str_replace('$_systemL', lcfirst($this->var['system']), $result);
        $result = str_replace('$_system', $this->var['system'], $result);

        $result = $this->getFields($result);
        $this->resultFile = $result;
    }

    public function applyClass() {
        $result = str_replace('$_classC', ucfirst($this->var['class']), $this->templateFile);
        $result = str_replace('$_classO', $this->var['originalClass'], $result);
        $result = str_replace('$_class', $this->var['class'], $result);
        $result = str_replace('$_moduleC', ucfirst($this->var['module']), $result);
        $result = str_replace('$_module', $this->var['module'], $result);
        $result = str_replace('$_constants', $this->var['constants'], $result);
        $result = str_replace('$_propertiesPersistence', $this->var['propertiesPersistence'], $result);
        $result = str_replace('$_properties', $this->var['properties'], $result);
        $result = str_replace('$_methodsPersistence', $this->var['methodsPersistence'], $result);
        $result = str_replace('$_methods', $this->var['methods'], $result);
        $result = str_replace('$_comment', $this->var['comment'], $result);
        $result = str_replace('$_package', $this->var['package'], $result);
        $result = str_replace('$_ormmap', $this->var['ormmap'], $result);
        $result = str_replace('$_actions', $this->var['actions'], $result);
        $result = str_replace('$_descriptionC', ucfirst($this->var['description'] ?: 'id'), $result);
        $result = str_replace('$_description', $this->var['description'] ?: 'id', $result);
        $result = str_replace('$_pkNameO', ucfirst($this->var['pkName']) ?: 'Id', $result);
        $result = str_replace('$_pkName', $this->var['pkName'] ?: 'id', $result);
        $result = str_replace('$_configLog', $this->var['configLog'], $result);
        $result = str_replace('$_configValidators', $this->var['configValidators'], $result);
        $result = str_replace('$_configConverters', $this->var['configConverters'], $result);
        $result = str_replace('$_extends', $this->var['extends'] ? : '\MBusinessModel', $result);
        $result = str_replace('$_time', Manager::getSysTime(), $result);
        $result = str_replace('$_default', $this->var['default'], $result);
        $result = str_replace('$_uses', $this->var['uses'], $result);

        $this->resultFile = $result;
    }
    
    public function getFields($result) {

        $result = str_replace('$_pkName', $this->pkName, $result);
        if ($this->fieldsControls) {
            $result = str_replace('$_fields', implode("        \n", $this->fieldsControls), $result);
        }
        return $result;
    }

    public function saveResult($fileName, $baseDir = '') {
        if ($baseDir != '') {
            $file = $baseDir;//Manager::getAppPath($baseDir);
            $dirs = explode('/', $fileName);
            $n = count($dirs);
            if ($n > 0) {
                $path = $file;
                for($i = 0; $i < ($n - 1); $i++){
                    $path .= '/' . $dirs[$i];
                    if (!is_dir($path)) {
                        mkdir($path);
                        chmod($path, octdec(777));
                    }
                }
            }
            $file .= '/' . $fileName;
        } else {
            $file = Manager::getAppPath($fileName);
            $path = pathinfo($file, PATHINFO_DIRNAME);
            if (!is_dir($path)) {
                mkdir($path);
                chmod($path, octdec(777));
            }
        }
        file_put_contents($file, $this->resultFile);
        chmod($file, octdec(777));
    }

    public function copy($fileSource, $fileName, $baseDir = '') {
        if ($baseDir != '') {
            $fileDest = $baseDir;//Manager::getAppPath($baseDir);
            $dirs = explode('/', $fileName);
            $n = count($dirs);
            if ($n > 0) {
                $path = $fileDest;
                for($i = 0; $i < ($n - 1); $i++){
                    $path .= '/' . $dirs[$i];
                    if (!is_dir($path)) {
                        mkdir($path);
                        chmod($path, octdec(777));
                    }
                }
            }
            $fileDest .= '/' . $fileName;
        } else {
            $file = Manager::getAppPath($fileName);
            $path = pathinfo($file, PATHINFO_DIRNAME);
            if (!is_dir($path)) {
                mkdir($path);
                chmod($path, octdec(777));
            }
        }
        copy($fileSource, $fileDest);
    }

    public function copydir($src,$dst) {
        if (file_exists($src)) {
            $dir = opendir($src);
            @mkdir($dst);
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) {
                        $this->copydir($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        }
    }

    public function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir."/".$object))
                        $this->rrmdir($dir."/".$object);
                    else
                        unlink($dir."/".$object);
                }
            }
            rmdir($dir);
        }
    }

}

?>