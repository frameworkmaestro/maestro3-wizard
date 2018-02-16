<?php

/* Copyright [2011, 2013, 2017] da Universidade Federal de Juiz de Fora
 * Este arquivo é parte do programa Framework Maestro.
 * O Framework Maestro é um software livre; você pode redistribuí-lo e/ou
 * modificá-lo dentro dos termos da Licença Pública Geral GNU como publicada
 * pela Fundação do Software Livre (FSF); na versão 2 da Licença.
 * Este programa é distribuído na esperança que possa ser  útil,
 * mas SEM NENHUMA GARANTIA; sem uma garantia implícita de ADEQUAÇÃO a qualquer
 * MERCADO ou APLICAÇÃO EM PARTICULAR. Veja a Licença Pública Geral GNU/GPL
 * em português para maiores detalhes.
 * Você deve ter recebido uma cópia da Licença Pública Geral GNU, sob o título
 * "LICENCA.txt", junto com este programa, se não, acesse o Portal do Software
 * Público Brasileiro no endereço www.softwarepublico.gov.br ou escreva para a
 * Fundação do Software Livre(FSF) Inc., 51 Franklin St, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

class MView extends MBaseView
{

    protected function processPHP()
    {
        $page = Manager::getPage();
        $viewName = basename($this->viewFile, '.php');
        mtrace($viewName);
        include_once $this->viewFile;
        $view = new $viewName();
        $view->setView($this);
        $view->load();
        if ($page->isPostBack()) {
            $view->eventHandler($this->data);
            $view->postback();
        }
        $page->addContent($view);
        if (MAESTRO_VERSION == '3.0') {
            return (Manager::isAjaxCall() ? $page->generate() : $page->render());
        }
    }

    protected function processXML()
    {
        $page = Manager::getPage();
        $container = new MContainer();
        $container->setView($this);
        $controls = $container->getControlsFromXML($this->viewFile);
        if (is_array($controls)) {
            foreach ($controls as $control) {
                if (is_object($control)) {
                    $control->load();
                    if ($page->isPostBack()) {
                        $control->postback();
                    }
                    $page->addContent($control);
                }
            }
        }
        if (MAESTRO_VERSION == '3.0') {
            return (Manager::isAjaxCall() ? $page->generate() : $page->render());
        }
    }

    protected function processTemplate()
    {
        $page = Manager::getPage();
        $baseName = basename($this->viewFile);
        $template = new MTemplate(dirname($this->viewFile));
        $template->context('manager', Manager::getInstance());
        $template->context('page', Manager::getPage());
        $template->context('view', $this);
        $template->context('data', $this->data);
        $template->context('template', $template);
        mtrace('basename = ' . $baseName);
        $content = $template->fetch($baseName);
        $page->setContent($content);
        if (MAESTRO_VERSION == '3.0') {
            return (Manager::isAjaxCall() ? $page->generate() : $page->render());
        }
    }

    public function processPrompt(MPromptData $prompt)
    {
        $page = Manager::getPage();
        $type = $prompt->type;
        $oPrompt = MPrompt::$type($prompt->message, $prompt->action1, $prompt->action2);
        $page->setName($oPrompt->getId());
        if (!$page->isPostBack()) {
            Manager::getPage()->onLoad("manager.doPrompt('{$oPrompt->getId()}')");
        }
        $page->setContent($oPrompt);
        $prompt->setContent($page->generate());
        $prompt->setId($oPrompt->getId());
    }

    public function processWindow()
    {
        $page = Manager::getPage();
        return $page->window();
    }

    public function processRedirect($url)
    {
        if (Manager::isAjaxCall()) {
            $page = Manager::getPage();
            $page->onLoad("manager.doRedirect('{$url}','');");
            //$page->setContent($url);
            if (MAESTRO_VERSION == '3.0') {
                return (Manager::isAjaxCall() ? $page->generate() : $page->render());
            }
        } else {
            return $url;
        }
    }
}
