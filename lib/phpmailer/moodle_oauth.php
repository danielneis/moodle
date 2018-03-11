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
 * Customised version of phpmailer's OAuth for Moodle
 *
 * @package    core
 * @copyright  2018 onwards Iñaki Arenaza <iarenaza@escomposlinux.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle Customised version of the OAuth class
 *
 * This class supersedes the stock OAuth class
 * in order to reduce dependencies on 3rd party libraries,
 * as the Horde IMAP library already includes the functionality
 * needed by the class.
 *
 * @copyright 2018 Iñaki Arenaza (iarenaza@escomposlinux.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 3.4
 */
class moodle_oauth {
    /**
     * The id of a Moodle OAuth2 service.
     *
     * @var integer
     */
    protected $issuerid;

    /**
     * The username to authenticate with.
     *
     * @var string
     */
    protected $username;

    /**
     * Constructor - creates an instance of the moodle_oauth class.
     *
     * @param int $issuerid The id of a Moodle OAuth2 service.
     * @param string $username The username to authenticate with the OAuth2 service.
     *
     * @return object An instance of moodle_oauth.
     */
    public function __construct($issuerid, $username) {
        $this->issuerid = $issuerid;
        $this->username = $username;
    }

    /**
     * Returns the XOAUTH2 authentication value, in Base64 format (see
     * https://developers.google.com/gmail/imap/xoauth2-protocol#the_sasl_xoauth2_mechanism)
     *
     * IMPORTANT NOTICE: This method *MUST* be called 'getOauth64', as
     *                   \PHPMailer\PHPMailer\PHPMailer class will invoke
     *                   a method with this exact name from inside its code.
     *
     * @return string The Base64-encoded of the XOAUTH2 authentication value.
     */
    public function getOauth64() {
        global $CFG;

        $xoauth2token = \core\xoauth2\helper::get_token_generator($this->issuerid, $this->username);
        return $xoauth2token->getPassword();
    }
}