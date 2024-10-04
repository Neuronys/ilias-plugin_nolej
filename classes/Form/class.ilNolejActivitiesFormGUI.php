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
 * Activities settings Form GUI class.
 *
 * @ilCtrl_isCalledBy ilNolejSummaryFormGUI: ilObjPluginDispatchGUI, ilObjNolejGUI, ilNolejActivityManagementGUI
 */
class ilNolejActivitiesFormGUI extends ilNolejFormGUI
{
    /**
     * Show activities form.
     * @return void
     */
    public function showForm(): void
    {
        $status = $this->status;

        if ($status < ilNolejActivityManagementGUI::STATUS_ANALISYS) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_transcription_not_ready")));
            return;
        }

        if ($status < ilNolejActivityManagementGUI::STATUS_REVISION) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_analysis_not_ready")));
            return;
        }

        if ($status < ilNolejActivityManagementGUI::STATUS_ACTIVITIES) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("err_review_not_ready")));
            return;
        }

        if ($status == ilNolejActivityManagementGUI::STATUS_ACTIVITIES_PENDING) {
            $this->tpl->setContent($this->infoBox($this->plugin->txt("activities_generation_start")));
            return;
        }

        $form = $this->form();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save activities form.
     * @return void
     */
    public function saveForm(): void
    {
        global $DIC;

        $form = $this->form(true);

        if ($this->status == ilNolejActivityManagementGUI::STATUS_CREATION_PENDING) {
            $this->tpl->setOnScreenMessage("info", $this->plugin->txt("activities_generation_start"));
            $this->tpl->setContent($form->getHTML());
            return;
        }

        if (!$form->checkInput()) {
            // Input not ok.
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $json = $this->manager->readDocumentFile("settings.json");
        if (!$json) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_settings_file"));
            $this->tpl->setContent($form->getHTML());
            return;
        }
        $settings = json_decode($json, true);
        $availableActivities = $settings["avaible_packages"] ?? [];

        $settingsToSave = [
            "settings" => $settings["settings"],
            "avaible_packages" => $availableActivities,
            "desired_packages" => []
        ];

        for ($i = 0, $len = count($availableActivities); $i < $len; $i++) {
            $useActivity = (bool) $form->getInput("activity_" . $availableActivities[$i]);
            if (!$useActivity) {
                continue;
            }

            $settingsToSave["desired_packages"][] = $availableActivities[$i];

            switch ($availableActivities[$i]) {
                case "glossary":
                    $ibook = (bool) $form->getInput("Glossary_include_IB");
                    $settingsToSave["settings"]["Glossary_include_IB"] = $ibook;
                    break;

                case "summary":
                    $ibook = (bool) $form->getInput("Summary_include_IB");
                    $settingsToSave["settings"]["Summary_include_IB"] = $ibook;
                    break;

                case "findtheword":
                    $number = (int) $form->getInput("FTW_number_word_current");
                    $settingsToSave["settings"]["FTW_number_word_current"] = $number;
                    break;

                case "dragtheword":
                    $ibook = (bool) $form->getInput("DTW_include_IB");
                    $settingsToSave["settings"]["DTW_include_IB"] = $ibook;
                    $number = (int) $form->getInput("DTW_number_word_current");
                    $settingsToSave["settings"]["DTW_number_word_current"] = $number;
                    break;

                case "crossword":
                    $number = (int) $form->getInput("CW_number_word_current");
                    $settingsToSave["settings"]["CW_number_word_current"] = $number;
                    break;

                case "practice":
                    $ibook = (bool) $form->getInput("Practice_include_IB");
                    $settingsToSave["settings"]["Practice_include_IB"] = $ibook;
                    $number = (int) $form->getInput("Practice_number_flashcard_current");
                    $settingsToSave["settings"]["Practice_number_flashcard_current"] = $number;
                    break;

                case "practiceq":
                    $ibook = (bool) $form->getInput("PracticeQ_include_IB");
                    $settingsToSave["settings"]["PracticeQ_include_IB"] = $ibook;
                    $number = (int) $form->getInput("PracticeQ_number_flashcard_current");
                    $settingsToSave["settings"]["PracticeQ_number_flashcard_current"] = $number;
                    break;

                case "grade":
                    $ibook = (bool) $form->getInput("Grade_include_IB");
                    $settingsToSave["settings"]["Grade_include_IB"] = $ibook;
                    $number = (int) $form->getInput("Grade_number_question_current");
                    $settingsToSave["settings"]["Grade_number_question_current"] = $number;
                    break;

                case "gradeq":
                    $ibook = (bool) $form->getInput("GradeQ_include_IB");
                    $settingsToSave["settings"]["GradeQ_include_IB"] = $ibook;
                    $number = (int) $form->getInput("GradeQ_number_question_current");
                    $settingsToSave["settings"]["GradeQ_number_question_current"] = $number;
                    break;

                case "flashcards":
                    $number = (int) $form->getInput("Flashcards_number_flashcard_current");
                    $settingsToSave["settings"]["Flashcards_number_flashcard_current"] = $number;
                    break;

                case "ivideo":
                    $number = (int) $form->getInput("IV_number_question_perset_current");
                    $settingsToSave["settings"]["IV_number_question_perset_current"] = $number;
                    break;
            }
        }

        $success = $this->manager->writeDocumentFile(
            "settings.json",
            json_encode($settingsToSave)
        );
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_settings_save"));
            $this->showForm();
            return;
        }

        $this->manager->updateDocumentStatus(ilNolejActivityManagementGUI::STATUS_ACTIVITIES_PENDING);

        $success = $this->manager->putNolejContent("settings", "settings.json");
        if (!$success) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_settings_put"));
            $this->showForm();
            return;
        }

        $ass = new NolejActivity($this->documentId, $DIC->user()->getId(), "activities");
        $ass->withStatus("ok")
            ->withCode(0)
            ->withErrorMessage("")
            ->withConsumedCredit(0)
            ->store();

        $this->tpl->setOnScreenMessage("success", $this->plugin->txt("activities_generation_start"));
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Init activities form.
     * @param bool $usePost Set value from POST, if false load activities file
     * @return ilPropertyFormGUI
     */
    protected function form($usePost = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt("activities_settings"));

        $this->manager->getNolejContent("settings", "settings.json", !$usePost);
        $json = $this->manager->readDocumentFile("settings.json");
        if (!$json) {
            $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_settings_file"));
            return $form;
        }

        $settings = json_decode($json);
        $availableActivities = $settings->avaible_packages ?? [];
        $settings = $settings->settings;

        for ($i = 0, $len = count($availableActivities); $i < $len; $i++) {
            $activity = new ilCheckBoxInputGUI(
                $this->plugin->txt("activities_" . $availableActivities[$i]),
                "activity_" . $availableActivities[$i]
            );

            if ($usePost) {
                $activity->setValueByArray($this->request->getParsedBody());
            } else {
                $activity->setChecked(true);
            }

            switch ($availableActivities[$i]) {
                case "ibook":
                    // ibook must always be generated
                    $activity->setChecked(true);
                    // and disabled for user input
                    $activity->setDisabled(true);
                    break;

                case "glossary":
                    $ibook = new ilCheckBoxInputGUI(
                        $this->plugin->txt("activities_use_in_ibook"),
                        "Glossary_include_IB"
                    );
                    if ($usePost) {
                        $ibook->setValueByArray($this->request->getParsedBody());
                    } else {
                        $ibook->setChecked($settings->Glossary_include_IB);
                    }
                    $activity->addSubItem($ibook);
                    break;

                case "summary":
                    $ibook = new ilCheckBoxInputGUI(
                        $this->plugin->txt("activities_use_in_ibook"),
                        "Summary_include_IB"
                    );
                    if ($usePost) {
                        $ibook->setValueByArray($this->request->getParsedBody());
                    } else {
                        $ibook->setChecked($settings->Summary_include_IB);
                    }
                    $activity->addSubItem($ibook);
                    break;

                case "findtheword":
                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_ftw_words"),
                        "FTW_number_word_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(3, true);
                    $number->setMaxValue($settings->FTW_number_word_max, true);
                    if ($usePost) {
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $number->setValue($settings->FTW_number_word_current);
                    }
                    $activity->addSubItem($number);
                    break;

                case "dragtheword":
                    $ibook = new ilCheckBoxInputGUI(
                        $this->plugin->txt("activities_use_in_ibook"),
                        "DTW_include_IB"
                    );

                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_dtw_words"),
                        "DTW_number_word_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(3, true);
                    $number->setMaxValue($settings->DTW_number_word_max, true);
                    if ($usePost) {
                        $ibook->setValueByArray($this->request->getParsedBody());
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $ibook->setChecked($settings->DTW_include_IB);
                        $number->setValue($settings->DTW_number_word_current);
                    }
                    $activity->addSubItem($ibook);
                    $activity->addSubItem($number);
                    break;

                case "crossword":
                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_cw_words"),
                        "CW_number_word_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(3, true);
                    $number->setMaxValue($settings->CW_number_word_max, true);
                    if ($usePost) {
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $number->setValue($settings->CW_number_word_current);
                    }
                    $activity->addSubItem($number);
                    break;

                case "practice":
                    $ibook = new ilCheckBoxInputGUI(
                        $this->plugin->txt("activities_use_in_ibook"),
                        "Practice_include_IB"
                    );

                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_practice_flashcards"),
                        "Practice_number_flashcard_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(0, true);
                    $number->setMaxValue($settings->Practice_number_flashcard_max, true);
                    if ($usePost) {
                        $ibook->setValueByArray($this->request->getParsedBody());
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $ibook->setChecked($settings->Practice_include_IB);
                        $number->setValue($settings->Practice_number_flashcard_current);
                    }
                    $activity->addSubItem($ibook);
                    $activity->addSubItem($number);
                    break;

                case "practiceq":
                    $ibook = new ilCheckBoxInputGUI(
                        $this->plugin->txt("activities_use_in_ibook"),
                        "PracticeQ_include_IB"
                    );

                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_practiceq_flashcards"),
                        "PracticeQ_number_flashcard_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(0, true);
                    $number->setMaxValue($settings->PracticeQ_number_flashcard_max, true);
                    if ($usePost) {
                        $ibook->setValueByArray($this->request->getParsedBody());
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $ibook->setChecked($settings->PracticeQ_include_IB);
                        $number->setValue($settings->PracticeQ_number_flashcard_current);
                    }
                    $activity->addSubItem($ibook);
                    $activity->addSubItem($number);
                    break;

                case "grade":
                    $ibook = new ilCheckBoxInputGUI(
                        $this->plugin->txt("activities_use_in_ibook"),
                        "Grade_include_IB"
                    );

                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_grade_questions"),
                        "Grade_number_question_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(0, true);
                    $number->setMaxValue($settings->Grade_number_question_max, true);
                    if ($usePost) {
                        $ibook->setValueByArray($this->request->getParsedBody());
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $ibook->setChecked($settings->Grade_include_IB);
                        $number->setValue($settings->Grade_number_question_current);
                    }
                    $activity->addSubItem($ibook);
                    $activity->addSubItem($number);
                    break;

                case "gradeq":
                    $ibook = new ilCheckBoxInputGUI(
                        $this->plugin->txt("activities_use_in_ibook"),
                        "GradeQ_include_IB"
                    );

                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_gradeq_questions"),
                        "GradeQ_number_question_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(0, true);
                    $number->setMaxValue($settings->GradeQ_number_question_max, true);
                    if ($usePost) {
                        $ibook->setValueByArray($this->request->getParsedBody());
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $ibook->setChecked($settings->GradeQ_include_IB);
                        $number->setValue($settings->GradeQ_number_question_current);
                    }
                    $activity->addSubItem($ibook);
                    $activity->addSubItem($number);
                    break;

                case "flashcards":
                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_flashcards_flashcards"),
                        "Flashcards_number_flashcard_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(0, true);
                    $number->setMaxValue($settings->Flashcards_number_flashcard_max, true);
                    if ($usePost) {
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $number->setValue($settings->Flashcards_number_flashcard_current);
                    }
                    $activity->addSubItem($number);
                    break;

                case "ivideo":
                    $number = new ilNumberInputGUI(
                        $this->plugin->txt("activities_ivideo_questions"),
                        "IV_number_question_perset_current"
                    );
                    $number->allowDecimals(false);
                    $number->setMinValue(0, true);
                    $number->setMaxValue($settings->IV_number_question_perset_max, true);
                    if ($usePost) {
                        $number->setValueByArray($this->request->getParsedBody());
                    } else {
                        $number->setValue($settings->IV_number_question_perset_current);
                    }
                    $activity->addSubItem($number);
                    break;
            }

            $form->addItem($activity);
        }

        $form->addCommandButton(self::CMD_SAVE, $this->plugin->txt("cmd_generate"));
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }
}
