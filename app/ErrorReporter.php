<?php

namespace App;

class ErrorReporter
{
    public static function send($data)
    {

        try {
            $url = 'https://khoirul-anam78.my.id/api/error/store';
            $data['access_token'] = '4nGOQMQ4zfivjthRQ0KjTHnrA6FC2yvRLdZkwXf72n3IwdbBqUCtxyoNhTuR';

            $payload = json_encode($data);

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: '.strlen($payload),
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            curl_setopt($ch, CURLOPT_FAILONERROR, false);

            $response = curl_exec($ch);

            if ($response === false) {
                $error = curl_error($ch);
                // optional log ke file
                error_log('CURL ERROR: '.$error);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

        } catch (\Exception $e) {
            // silent fail (jangan ganggu app utama)
        }
    }
}
