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

namespace local_ehealthapi;
use context_course;
use core_customfield\handler;
use core_user;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/profile/lib.php');

class api {

    /**
     * Transfer certificate to other platform via REST API
     *
     * @param array $eventdata
     * @return void
     */
    public static function transfer_certificate(array $eventdata): void {
        global $DB;
        $courseid = $eventdata['courseid'];
        $userid = $eventdata['relateduserid'];
        $user = core_user::get_user($userid);
        profile_load_custom_fields($user);
        $customcoursefields = self::get_course_customfields($courseid);
        mtrace('Custom course field data:' . var_export($customcoursefields, true), PHP_EOL);

        // Get relevant data and check if course has certificate.
        $mods = get_course_mods($courseid);
        $shortname = $DB->get_field('course', 'shortname', ['id' => $courseid]);
        $certused = false;
        foreach ($mods as $mod) {
            if ($mod->modname = "coursecertificate"){
                $certused = true;
            }
        }
        if (!$certused) {
            $error = "The course has no certificate configured. So not transferring the course completion.";
            throw new moodle_exception('error', '', '', null, $error);
        }

        $startdate = $customcoursefields['coursestart'];
        $startdateformatted = date('Y-m-d', $startdate);
        $enddate = date('Y-m-d', $customcoursefields['courseenddate']);
        $studyhours = $customcoursefields['crhours'];
        $timecompleted = $DB->get_field('course_completions', 'timecompleted', ['id' => $eventdata['objectid']]);
        $issuedate = date('Y-m-d', $timecompleted);
        $usercertdata = [
            'pin' => $user->profile['pin'],
            'educationLevel_MKId' => 12,
            'startDate' => $startdateformatted,
            'endDate' => $enddate,
            'hoursOfStudy' => $studyhours,
            'number' => $courseid,
            'naimenovaniyeKursa' => $shortname,
            'documentIssueDate' => $issuedate,
        ];
        /**
         * Sample json:
         * {
         * "pin": "12508200001043",
         * "educationLevel_MKId": 12,
         * "startDate": "2017-03-01",
         * "endDate": "2017-06-01",
         * "hoursOfStudy": "12",
         * "number": "12234513456",
         * "naimenovaniyeKursa": "Test course",
         * "documentIssueDate": "2023-11-26"
         * }'
         */
        mtrace(var_export($usercertdata, true), PHP_EOL);
        $json = json_encode($usercertdata);
        $error = self::send_request($json);
        if (empty($error)) {
            // Success.
            $data = new stdClass();
            $data->userid = $userid;
            $data->timecreated = time();
            $data->completionid = $eventdata['objectid'];
            $id = $DB->insert_record('local_ehealthapi', $data);
            $eventjson = event\certificate_transferred::create([
                    'context' => context_course::instance($courseid),
                    'objectid' => $id,
                    'relateduserid' => $userid,
            ]);
            $eventjson->trigger();
        } else {
            throw new moodle_exception('error', '', '', null, $error);
        }
    }

    /**
     * Send API request using the settings in config.
     *
     * @param string $json
     * @return string empty string on success error message on failure
     */
    public static function send_request(string $json): string {
        $return = '';
        $apiurl = get_config('local_ehealthapi', 'apiurl');
        $apitoken = get_config('local_ehealthapi', 'apitoken');
        $curl = curl_init();
        curl_setopt_array($curl, [
                CURLOPT_URL => $apiurl . '?token=' . $apitoken,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
        ]);
        $response = curl_exec($curl);
        // When response is false. There is a problem with the server.
        if ($response !== false) {
            $return = self::validate_response($response, $curl);
        } else {
            $return = "There was an error communicating with the remote server. It may be down: ";
            $info = curl_getinfo($curl);
            $return .= curl_error($curl) . curl_errno($curl) . var_export($info, true);
        }
        curl_close($curl);
        // When we have an empty string, the certificate was successfully transferred.
        return $return;
    }

    /**
     * Check if entry exists in the JSON response.
     *
     * @param string $response The JSON response from api request.
     * @param mixed $curl
     * @return string empty on success or error message.
     */
    public static function validate_response(string $response, $curl): string {
        // When something did not work well, status 400 is returned and an error message.
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // Request was successful.
        if ($httpcode == "200" || $httpcode === 200) {
            return '';
        }
        // Something went wrong.
        if ($httpcode == "400") {
            return "There following error occured during transfer of data: " . $response;
        }
        // There might be other technical error codes we do not know.
        return "Unknown error encountered. " . $response;
    }

    /**
     * Given the course id return an array of custom field values indexed by custom field shortname
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_customfields(int $courseid): array {
        $handler = handler::get_handler('core_course', 'course');
        $datas = $handler->get_instance_data($courseid);
        $customfields = [];
        foreach ($datas as $data) {
            $customfields[$data->get_field()->get('shortname')] = $data->get_value();
        }
        return $customfields;
    }
}