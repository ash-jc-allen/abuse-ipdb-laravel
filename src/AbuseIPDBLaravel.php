<?php

namespace AbuseIPDB;

use AbuseIPDB\Exceptions\InvalidParameterException;
use AbuseIPDB\ResponseObjects\ReportsPaginatedResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AbuseIPDBLaravel
{
    /**
     * The AbuseIPDB API base url
     *
     * @var string $baseUrl
     */
    private string $baseUrl = 'https://api.abuseipdb.com/api/v2/';

    /**
     * The request headers
     *
     * @var string[] $headers
     */
    private array $headers = [];

    /**
     * API endpoints available with their respective HTTP request verbs
     *
     * @var string[]
     */
    private array $endpoints = [
        'check' => 'get',
        'reports' => 'get',
        'blacklist' => 'get',
        'report' => 'post',
        'check-block' => 'get',
        'bulk-report' => 'post',
        'clear-address' => 'delete',
    ];

    /**
     * Attacks categories available with their respective identifier
     *
     * @var string[]
     */
    private array $categories = [
        'DNS_Compromise' => 1,
        'DNS_Poisoning' => 2,
        'Fraud_Orders' => 3,
        'DDoS_Attack' => 4,
        'FTP_Brute_Force' => 5,
        'Ping_of_Death' => 6,
        'Phishing' => 7,
        'Fraud_VoIP' => 8,
        'Open_Proxy' => 9,
        'Web_Spam' => 10,
        'Email_Spam' => 11,
        'Blog_Spam' => 12,
        'VPN_IP' => 13,
        'Port_Scan' => 14,
        'Hacking' => 15,
        'SQL_Injection' => 16,
        'Spoofing' => 17,
        'Brute_Force' => 18,
        'Bad_Web_Bot' => 19,
        'Exploited_Host' => 20,
        'Web_App_Attack' => 21,
        'SSH' => 22,
        'IoT_Targeted' => 23,
    ];

    /* function that all requests will be passed through */
    public function makeRequest($endpointName, $parameters, $acceptType = 'application/json'): ?Response
    {

        //check that endpoint passed in exists for the api
        if (!array_key_exists($endpointName, $this->endpoints)) {
            throw new Exceptions\InvalidEndpointException("Endpoint name given is invalid.");
        }

        //grab the proper request method from the endpoints array
        $requestMethod = $this->endpoints[$endpointName];

        //check that accept type is application json, or plaintext for blacklist, if not throw error
        if ($acceptType != 'application/json') {
            if ($acceptType == 'text/plain' && $endpointName == 'blacklist') {
                //do nothing
            } else {
                throw new Exceptions\InvalidAcceptTypeException("Accept Type given may not be used.");
            }

        }

        //give the accept type to the headers array
        $this->headers['Accept'] = $acceptType;

        //get the api key from the env, if not present throw an error
        if (env('ABUSEIPDB_API_KEY') != null) {
            $this->headers['Key'] = env('ABUSEIPDB_API_KEY');
        } else {
            throw new Exceptions\MissingAPIKeyException("ABUSEIPDB_API_KEY must be set in .env with an AbuseIPBD API key.");
        }

        //create client and assign headers array
        $client = Http::withHeaders($this->headers);

        //verify false here for local development purposes, to avoid certificate issues
        if (env('APP_ENV') == 'local') {
            $client->withOptions(['verify' => false]);
        }

        //make the request to the api
        $response = $client->$requestMethod($this->baseUrl . $endpointName, $parameters);

        //extract the status code
        $status = $response->status();

        if ($status == 200) {
            return $response;
        } else {
            //check for different possible error codes
            $message = "AbuseIPDB: " . $response->object()->errors[0]->detail;
            if ($status == 429) {
                throw new Exceptions\TooManyRequestsException($message);
            } else if ($status == 402) {
                throw new Exceptions\PaymentRequiredException($message);
            } else if ($status == 422) {
                throw new Exceptions\UnprocessableContentException($message);
            } else {
                //Error is not one of the conventional errors thrown by application
                throw new Exceptions\UnconventionalErrorException($message);
            }

        }
        return null;
    }

    /* makes call to the check endpoint of api */
    public function check(string $ipAddress, int $maxAgeInDays = 30, bool $verbose = false): ResponseObjects\CheckResponse
    {
        $parameters['ipAddress'] = $ipAddress;
        //only send nullable parameters if present
        if ($maxAgeInDays) {
            if ($maxAgeInDays >= 1 && $maxAgeInDays <= 365) {
                $parameters['maxAgeInDays'] = $maxAgeInDays;
            } else {
                throw new Exceptions\InvalidParameterException("maxAgeInDays must be between 1 and 365.");
            }
        }

        if ($verbose) {
            $parameters['verbose'] = $verbose;
        }

        $httpResponse = $this->makeRequest('check', $parameters);

        return new ResponseObjects\CheckResponse($httpResponse);
    }

    /* makes call to report endpoint of api */
    public function report(string $ip, array|int $categories, string $comment = ''): ResponseObjects\ReportResponse
    {

        foreach ((array)$categories as $cat) {
            if (!in_array($cat, $this->categories)) {
                throw new Exceptions\InvalidParameterException("Individual category must be a valid category.");
            }
        }

        $parameters = ['ip' => $ip, 'categories' => $categories];

        //only send nullable parameters if present
        if ($comment) {
            $parameters['comment'] = $comment;
        }

        $httpResponse = $this->makeRequest('report', $parameters);

        return new ResponseObjects\ReportResponse($httpResponse);
    }

    /**
     * Get the reports for a single IP address (v4 or v6)
     *
     * @param string $ipAddress
     * @param int $maxAgeInDays
     * @param int $page
     * @param int $perPage
     * @return \AbuseIPDB\ResponseObjects\ReportsPaginatedResponse
     * @throws \AbuseIPDB\Exceptions\InvalidParameterException
     */
    public function reports(string $ipAddress, int $maxAgeInDays = 30, int $page = 1, int $perPage = 25): ReportsPaginatedResponse
    {
        if ($maxAgeInDays < 1 || $maxAgeInDays > 365) {
            throw new InvalidParameterException('maxAgeInDays must be between 1 and 365.');
        }

        if ($page < 1) {
            throw new InvalidParameterException('page must be at least 1.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidParameterException('perPage must be between 1 and 100.');
        }

        $response = $this->makeRequest('reports', [
            'ipAddress' => $ipAddress,
            'maxAgeInDays' => $maxAgeInDays,
            'page' => $page,
            'perPage' => $perPage,
        ]);

        return new ReportsPaginatedResponse($response);
    }
}
