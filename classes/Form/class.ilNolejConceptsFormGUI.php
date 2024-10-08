<?php

/**
 * This file is part of Nolej Repository Object Plugin for ILIAS,
 * developed by OC Open Consulting to integrate ILIAS with Nolej
 * software by Neuronys.
 *
 * @author Vincenzo Padula <vincenzo@oc-group.eu>
 * @copyright 2024 OC Open Consulting SB Srl
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Concepts Form GUI class.
 *
 * @ilCtrl_isCalledBy ilNolejConceptsFormGUI: ilObjPluginDispatchGUI, ilObjNolejGUI, ilNolejManagerGUI
 */
class ilNolejConceptsFormGUI extends ilNolejFormGUI
{
    /**
     * Show concepts form.
     * @return void
     */
    public function showForm(): void
    {
        if ($this->status < ilNolejManagerGUI::STATUS_ANALISYS) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_transcription_not_ready")));
            return;
        }

        if ($this->status < ilNolejManagerGUI::STATUS_REVISION) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_analysis_not_ready")));
            return;
        }

        $form = $this->form();
        $this->tpl->setContent($form->getHTML());

        $this->tpl->setRightContent(
            $this->renderer->render($this->manager->getWorkflow()->withActive(2))
        );
    }

    /**
     * Save concepts form.
     * @return void
     */
    public function saveForm(): void
    {
        if ($this->status < ilNolejManagerGUI::STATUS_REVISION) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_analysis_not_ready")));
            return;
        }

        $form = $this->form(true);
        if (!$form->checkInput()) {
            // Input not ok.
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $concepts = [];

        $length = $form->getInput("concepts_count");
        for ($i = 0; $i < $length; $i++) {
            $id = $form->getInput("concept_{$i}_id");
            $enable = (bool) $form->getInput("concept_{$i}_enable");
            $useForCW = (bool) $form->getInput("concept_{$i}_cw") ?? false;
            $useForDTW = (bool) $form->getInput("concept_{$i}_dtw") ?? false;
            $useForFTW = (bool) $form->getInput("concept_{$i}_ftw") ?? false;
            $useForGaming = (bool) $form->getInput("concept_{$i}_gaming") ?? false;
            $useForPractice = (bool) $form->getInput("concept_{$i}_practice") ?? false;
            $useForAssessment = false; // For legacy purpose.
            $label = $form->getInput("concept_{$i}_label");
            $language = $form->getInput("concept_{$i}_language");
            $definition = $form->getInput("concept_{$i}_definition");
            $games = json_decode($form->getInput("concept_{$i}_games"));

            if (!empty($id)) {
                $concepts[] = [
                    "id" => $id,
                    "enable" => $enable,
                    "use_for_cw" => $useForCW,
                    "use_for_dtw" => $useForDTW,
                    "use_for_ftw" => $useForFTW,
                    "use_for_gaming" => $useForGaming,
                    "use_for_practice" => $useForPractice,
                    "use_for_assessment" => $useForAssessment,
                    "concept" => [
                        "label" => $label,
                        "language" => $language,
                        "definition" => $definition,
                        "available_games" => $games
                    ]
                ];
            }
        }

        $success = $this->manager->writeDocumentFile(
            "concepts.json",
            json_encode(["concepts" => $concepts])
        );
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_concepts_save"));
            $this->showForm();
            return;
        }

        $success = $this->manager->putNolejContent("concepts", "concepts.json");
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_concepts_put"));
        } else {
            $this->tpl->setOnScreenMessage("success", $this->plugin->txt("concepts_saved"));
        }
        $this->showForm();
    }

    /**
     * Init concepts form.
     * @param bool $usePost Set value from POST, if false load concepts file
     * @return ilPropertyFormGUI
     */
    protected function form($usePost = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt("review_concepts"));

        $this->manager->getNolejContent("concepts", "concepts.json");
        $json = $this->manager->readDocumentFile("concepts.json");
        if (!$json) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_concepts_file"));
            return $form;
        }

        $concepts = json_decode($json);
        $concepts = $concepts->concepts;

        $length = count($concepts);
        $length_input = new ilHiddenInputGUI("concepts_count");
        $length_input->setValue($length);
        $form->addItem($length_input);
        for ($i = 0; $i < $length; $i++) {
            $section = new ilFormSectionHeaderGUI();
            $section->setTitle(sprintf($this->plugin->txt("concepts_n"), $i + 1));
            $form->addItem($section);

            $id = new ilHiddenInputGUI("concept_{$i}_id");
            $id->setValue($concepts[$i]->id);
            $form->addItem($id);

            $label = new ilNonEditableValueGUI(
                $this->plugin->txt("concepts_label"),
                "concept_{$i}_label"
            );
            $label->setValue($concepts[$i]->concept->label);
            $form->addItem($label);

            $enable = new ilCheckboxInputGUI(
                $this->plugin->txt("concepts_enable"),
                "concept_{$i}_enable"
            );
            $form->addItem($enable);

            $definition = new ilTextAreaInputGUI(
                $this->plugin->txt("concepts_definition"),
                "concept_{$i}_definition"
            );
            $definition->usePurifier(false);
            $definition->setRows(4);
            $enable->addSubItem($definition);

            $availableGames = $concepts[$i]->concept->available_games;
            $useForGaming = new ilCheckboxInputGUI(
                $this->plugin->txt("concepts_use_for_gaming"),
                "concept_{$i}_gaming"
            );

            $useForCW = new ilCheckboxInputGUI(
                $this->plugin->txt("concepts_use_for_cw"),
                "concept_{$i}_cw"
            );

            $useForDTW = new ilCheckboxInputGUI(
                $this->plugin->txt("concepts_use_for_dtw"),
                "concept_{$i}_dtw"
            );

            $useForFTW = new ilCheckboxInputGUI(
                $this->plugin->txt("concepts_use_for_ftw"),
                "concept_{$i}_ftw"
            );

            if ($availableGames != null && is_array($availableGames) && count($availableGames) > 0) {
                $enable->addSubItem($useForGaming);

                if (in_array("cw", $concepts[$i]->concept->available_games)) {
                    $useForGaming->addSubItem($useForCW);
                }

                if (in_array("dtw", $concepts[$i]->concept->available_games)) {
                    $useForGaming->addSubItem($useForDTW);
                }

                if (in_array("ftw", $concepts[$i]->concept->available_games)) {
                    $useForGaming->addSubItem($useForFTW);
                }
            }

            $useForPractice = new ilCheckboxInputGUI(
                $this->plugin->txt("concepts_use_for_practice"),
                "concept_{$i}_practice"
            );
            $enable->addSubItem($useForPractice);

            $language = new ilHiddenInputGUI("concept_{$i}_language");
            $language->setValue($concepts[$i]->concept->language);
            $enable->addSubItem($language);

            $games = new ilHiddenInputGUI("concept_{$i}_games");
            $games->setValue(json_encode($concepts[$i]->concept->available_games));
            $enable->addSubItem($games);

            if ($usePost) {
                $enable->setValueByArray($this->request->getParsedBody());
                $useForCW->setValueByArray($this->request->getParsedBody());
                $useForDTW->setValueByArray($this->request->getParsedBody());
                $useForFTW->setValueByArray($this->request->getParsedBody());
                $useForGaming->setValueByArray($this->request->getParsedBody());
                $useForPractice->setValueByArray($this->request->getParsedBody());
                // $useForAssessment->setValueByArray($this->request->getParsedBody());
                $definition->setValueByArray($this->request->getParsedBody());
            } else {
                $enable->setChecked($concepts[$i]->enable);
                $useForCW->setChecked($concepts[$i]->use_for_cw);
                $useForDTW->setChecked($concepts[$i]->use_for_dtw);
                $useForFTW->setChecked($concepts[$i]->use_for_ftw);
                $useForGaming->setChecked($concepts[$i]->use_for_gaming);
                $useForPractice->setChecked($concepts[$i]->use_for_practice);
                // $useForAssessment->setChecked($concepts[$i]->use_for_assessment);
                $definition->setValue($concepts[$i]->concept->definition);
            }
        }

        $form->addCommandButton(self::CMD_SAVE, $this->lng->txt("save"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }
}
