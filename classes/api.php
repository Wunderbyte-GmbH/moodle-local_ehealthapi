<?php

namespace local_ehealthapi;
use cache;
use context_course;
use core\event\base;
use core_customfield\handler;
use core_user;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();


class api {

    /**
     * Transfer certificate to other platform via REST API
     *
     * @param base $event
     * @return string an empty string on success or the error as string.
     */
    public static function transfer_certificate(base $event): string {
        global $DB;
        $data = $event->get_data();
        $courseid = $data['courseid'];
        $userid = $data['relateduserid'];
        $user = core_user::get_user($userid);
        profile_load_custom_fields($user);
        $customcoursefields = self::get_course_customfields($courseid);
        $course = cache::make('core', 'course')->get($courseid);
        // Check if the course object exists and has a shortname
        if ($course && property_exists($course, 'shortname')) {
            $shortname = $course->shortname;
        } else {
            $course = get_course($courseid);
            if ($course) {
                $shortname = $course->shortname;
            } else {
                throw new moodle_exception("cannotfindcourse");
            }
        }

        // Get relevant data and check if course has certificate.
        $mods = get_course_mods($courseid);
        $certused = false;
        foreach ($mods as $mod) {
            if ($mod->modname = "coursecertificate"){
                $certused = true;
            }
        }
        if (!$certused) {
            return "The course has no certificate configured. So not transferring the course completion.";
        }

        $startdate = $customcoursefields['coursestart'];
        $timestamp = strtotime($startdate);
        $startdateformatted = date('Y-m-d', $timestamp);
        $timestamp = strtotime($customcoursefields['courseenddate']);
        $enddate = date('Y-m-d', $timestamp);
        $studyhours = $customcoursefields['crhours'];
        $now = time();
        $issuedate = date('Y-m-d', $now);
        $usercertdata = [
            'pin' => $user->profile['pin'],
            'educationLevel_MKId' => '',
            'startDate' => $startdateformatted,
            'endDate' => $enddate,
            'hoursOfStudy' => $studyhours,
            'number' => $courseid,
            'naimenovaniyeKursa' => $shortname,
            'documentIssueDate' => $issuedate,
        ];
        /**
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

        $json = json_encode($usercertdata);
        $error = self::send_request($json);
        if (empty($error)) {
            // Success.
            $data = new stdClass();
            $data->userid = $userid;
            $data->timecreated = $now;
            $data->completionid = $data['objectid'];
            $id = $DB->insert_record('local_ehealthapi', $data);
            $event = event\certificate_transferred::create([
                    'context' => context_course::instance($courseid),
                    'objectid' => $id,
                    'relateduserid' => $userid,
            ]);
            $event->trigger();
            return '';
        } else {
            return $error;
        }
    }

    /**
     * Send API request using the settings in config.
     *
     * @param string $json
     * @return string empty string on success error message on failure
     */
    public static function send_request(string $json): string {
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
        if ($response) {
            $return = self::validate_response($response, $curl);
        } else {
            $return = "There was an error communicating with the remote server. It may be down: ";
            $return .= curl_error($curl) . curl_errno($curl);
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
        if ($httpcode == "200") {
            return "";
        }
        // Something went wrong.
        if ($httpcode == "400") {
            return "There was an error encountered: " . $response;
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