<?php
require 'vendor/autoload.php';
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\Iam\IamClient;
use Aws\Exception\AwsException;

//$credentials = new Credentials('your-access-key', 'your-secret-key');
$client = new IamClient([
    'profile' => 'default',
    'region' => 'us-west-2',
    'version' => '2010-05-08'
]);

try {
    $result = $client->getUser(['UserName' => 'tempsls']);
    var_dump($result);
} catch (AwsException $e) {
    // output error message if fails
    error_log($e->getMessage());
}