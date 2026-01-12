<?php
require 'vendor/autoload.php';
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\Iam\IamClient;
use Aws\Exception\AwsException;

//$credentials = new Credentials('your-access-key', 'your-secret-key');
$client = new S3Client([
    'profile' => 'default',
    'region' => 'us-east-1',
    'version' => '2006-03-01'
]);

try {
    $result = $client->listBuckets();
    var_dump($result);
} catch (AwsException $e) {
    // output error message if fails
    error_log($e->getMessage());
}