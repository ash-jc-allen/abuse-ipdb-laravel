<?php

namespace AbuseipdbLaravel;

use AbuseidbLaravel\Exceptions\TooManyRequestsException;
use AbuseipdbLaravel\Facades\AbuseIPDB;

class AbuseIPDBExceptionReporter
{

    public static function reportSuspiciousOperationException(): ?\ResponseObjects\ReportResponse
    {

        $attackingAddress = request()->ip();
        $params = "";
        foreach (request()->all() as $param => $value) {
            $params .= print_r($param, true) . ": " . print_r($value, true) . "\n";
        }
        $comment = "Suspicious Operation. Request content:\n" . $params;

        try {
            $response = AbuseIPDB::report(ip: $attackingAddress, categories: 21, comment: $comment);
            return $response;
        } catch (TooManyRequestsException $e) {
            /* This will neglect the exception by default
            If you would like to implement any logging you may do so here
            */
        }
        return null;
    }
}
