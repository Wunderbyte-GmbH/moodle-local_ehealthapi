<?php

namespace local_ehealthapi;
use core\event\base;
use core_course\search\customfield;
use core_courseformat\output\local\content\cm\cmname;
use core_customfield\handler;

defined('MOODLE_INTERNAL') || die();


class api {

    /**
     * Transfer certificate to other platform via REST API
     *
     * @param base $event
     * @return string empty if not cert is issued
     */
    public static function transfer_certificate(base $event): string {
        $data = $event->get_data();
        $courseid = $data['courseid'];
        $userid = $data['relateduserid'];
        $user = \core_user::get_user($userid);
        profile_load_custom_fields($user);

        // Get relevant data and check if course has certificate.
        $mods = get_course_mods($courseid);
        $certused = false;
        foreach ($mods as $mod) {
            if ($mod->modname = "coursecertificate"){
                $certused = true;
            }
        }
        if (!$certused) {
            return '';
        }

        $usercertdata = [
            'pin' => $user->profile['pin'],
            'educationLevel_MKId' => '',
            'startDate' => '',
            'endDate' => '',
            'hoursOfStudy' => '',
            'number' => '',
            'naimenovaniyeKursa' => '',
            'documentIssueDate' => '',
        ];

        $json = json_encode($usercertdata);
        $response = self::send_request($json);
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
    }

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
        curl_close($curl);
        return $response;
    }

    /**
     * Check if entry exists in the JSON response.
     *
     * @param string $response The JSON response from ERPNext.
     * @return bool True if the entry exists, false otherwise.
     */
    public static function validate_response(string $response): bool {
        // Decode the JSON response into an associative array.
        $resparray = json_decode($response, true);
        // Check if the response contains data.
        if (isset($resparray['data'])) {
            return true; // Entry exists or entry was successfully created.
        }
        // Check if the response contains an error message.
        if (isset($resparray['exc_type'])) {
            $this->errormessage = $resparray['exc_type'];
            return false; // Entry does not exist (error).
        }
        if (isset($resparray['exception'])) {
            $this->errormessage = $resparray['exception'];
            return false; // Entry does not exist (error).
        }
        return false;
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