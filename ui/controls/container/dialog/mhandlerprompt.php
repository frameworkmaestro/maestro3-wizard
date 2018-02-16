<?php

class MHandlerPrompt {

    static public function handler (MPromptData $prompt) {
        $type = $prompt->type;
        $oPrompt = MPrompt::$type($prompt->message, $prompt->action1, $prompt->action2);
        $prompt->setControl($oPrompt);
        if (!Manager::isAjaxCall()) {
            Manager::getPage()->onLoad("manager.doPrompt('{$oPrompt->getId()}')");
        }
    }
}