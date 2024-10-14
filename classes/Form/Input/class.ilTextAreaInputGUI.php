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

namespace Nolej;

/**
 * Custom text input GUI class.
 */
class ilTextAreaInputGUI extends \ilTextAreaInputGUI
{
    /** @var array */
    protected $regex = [];

    /**
     * Constructor.
     * @param string $title
     * @param string $postvar
     * @param \ilNolejPlugin $plugin
     */
    public function __construct(
        string $title = "",
        string $postvar = ""
    ) {
        parent::__construct($title, $postvar);
        $this->usePurifier(false);
        $this->setRows(3);
    }

    /**
     * Add a regex validator to the input.
     * @param string $pattern
     * @param string $errorMessage
     * @return void
     */
    public function addRegex($pattern, $errorMessage): void
    {
        $this->regex[] = [
            "pattern" => $pattern,
            "alert" => $errorMessage,
        ];
    }

    /**
     * Check patterns.
     * @return bool
     */
    public function checkInput(): bool
    {
        if (!parent::checkInput()) {
            return false;
        }

        $value = $this->getInput();

        foreach ($this->regex as $regex){
            if (!preg_match($regex["pattern"], $value)) {
                $this->setAlert($regex["alert"]);
                return false;
            }
        }

        return true;
    }
}
