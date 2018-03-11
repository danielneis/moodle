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
 * Contains helper class for XOAUTH2 authentication method.
 *
 * @package    core
 * @copyright  2018 Iñaki Arenaza <iarenaza@escomposlinux.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\xoauth2;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for XOAUTH2 authentication method.
 *
 * @package    core
 * @copyright  2018 Iñaki Arenaza <iarenaza@escomposlinux.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Helper function to build a SELECT element with all the available OAuth2 services.
     *
     * @param string $settingname Name of the setting to attach to the SELECT element.
     * @param string $servicename Name of the service to show in the description of the SELECT element.
     *
     * @return object A admin_setting_configselect instance .
     */
    public static function service_providers_configselect($settingname, $servicename) {
        $options = [];
        $issuers = \core\oauth2\api::get_all_issuers();
        foreach ($issuers as $issuer) {
            $options[$issuer->get('id')] = s($issuer->get('name'));
        }
        // If there are no OAuth2 issuers defined yet, the $options array
        // is empty and the select element is not rendered. Add an entry
        // with an invitation to create and select one issuer.
        if (!count($options)) {
            $options[] = get_string('selectoauth2issuer', 'admin');
        }
        return new \admin_setting_configselect($settingname,
                                               new \lang_string('oauth2issuer', 'admin'),
                                               new \lang_string('oauth2issuer_desc', 'admin', $servicename),
                                               null, $options);
    }

    /**
     * Helper function to build a SELECT element with all the available OAuth2 services.
     *
     * @param int $issuerid The id value of an OAUTH2 Service provider.
     * @param string $username The account used to authenticate via XOAUTH2.
     *
     * @return object A Horde_Imap_Client_Password_Xoauth2 capable of generating the
     *                XOAUTH2 bearer token value.
     */
    public static function get_token_generator($issuerid, $username) {
        try {
            $issuer = \core\oauth2\api::get_issuer($issuerid);
        } catch (dml_missing_record_exception $e) {
            $message = $e->getMessage();
            throw new \moodle_exception('oauth2servicefailure', 'admin', '', null, $message);
        }
        if ($issuer && !$issuer->get('enabled')) {
            $message = get_string('oauth2issuer_disabled', 'admin');
            throw new \moodle_exception('oauth2servicefailure', 'admin', '', null, $message);
        }
        if (!($oauth2client = \core\oauth2\api::get_system_oauth_client($issuer))) {
            $message = get_string('oauth2issuer_connectionerror', 'admin');
            throw new \moodle_exception('oauth2servicefailure', 'admin', '', null, $message);
        }
        $accesstoken = $oauth2client->get_accesstoken();
        return new \Horde_Imap_Client_Password_Xoauth2($username, $accesstoken->token);
    }
}