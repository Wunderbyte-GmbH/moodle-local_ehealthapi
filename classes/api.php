<?php

namespace local_ehealthapi;
defined('MOODLE_INTERNAL') || die();


class api {

    public static function transfer_certificate(string $json): string {
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
}