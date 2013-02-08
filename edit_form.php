<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing readerview navigation instances.
 *
 * @since 2.0
 * @package blocks
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for setting navigation instances.
 *
 * @package blocks
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_readerview_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('blockreaderview', 'block_readerview'));

        $yesnooptions = array('yes'=>get_string('yes'), 'no'=>get_string('no'));

        $mform->addElement('select', 'config_allowquiztaken', get_string('settings:allowquiztaken',"block_readerview"), $yesnooptions);

        if (empty($this->block->config->config_allowquiztaken) || $this->block->config->config_allowquiztaken=='yes') {
            $mform->getElement('config_allowquiztaken')->setSelected('yes');
        } else {
            $mform->getElement('config_allowquiztaken')->setSelected('no');
        }


        $mform->addElement('select', 'config_takeaquizbefore', get_string('settings:takeaquizbefore',"block_readerview"), $yesnooptions);

        if (empty($this->block->config->config_takeaquizbefore) || $this->block->config->config_takeaquizbefore=='yes') {
            $mform->getElement('config_takeaquizbefore')->setSelected('yes');
        } else {
            $mform->getElement('config_takeaquizbefore')->setSelected('no');
        }


        $mform->addElement('select', 'config_sendtoserver', get_string('settings:sendtoserver',"block_readerview"), $yesnooptions);

        if (empty($this->block->config->config_sendtoserver) || $this->block->config->config_sendtoserver=='yes') {
            $mform->getElement('config_sendtoserver')->setSelected('yes');
        } else {
            $mform->getElement('config_sendtoserver')->setSelected('no');
        }
    }
}
