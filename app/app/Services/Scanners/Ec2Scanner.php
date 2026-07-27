<?php

namespace App\Services\Scanners;

use App\Services\AwsSecurityScanner;

class Ec2Scanner extends AwsSecurityScanner
{
    /**
     * Execute EC2 API calls based on method name
     * Returns raw AWS SDK response (array)
     */
    public function executeApiCall(string $method, array $credentials, array $params = [], ?string $region = null): array
    {
        $ec2Client = $this->createClient('ec2', $credentials, $region);

        return $this->callApi('EC2', $method, $params, fn () => match ($method) {
            'describeInstances' => $ec2Client->describeInstances($params)->toArray(),
            'describeVpcs' => $ec2Client->describeVpcs($params)->toArray(),
            'describeSubnets' => $ec2Client->describeSubnets($params)->toArray(),
            'describeInternetGateways' => $ec2Client->describeInternetGateways($params)->toArray(),
            'describeNatGateways' => $ec2Client->describeNatGateways($params)->toArray(),
            'describeRouteTables' => $ec2Client->describeRouteTables($params)->toArray(),
            'describeNetworkAcls' => $ec2Client->describeNetworkAcls($params)->toArray(),
            'describeVpcPeeringConnections' => $ec2Client->describeVpcPeeringConnections($params)->toArray(),
            'describeVpcEndpoints' => $ec2Client->describeVpcEndpoints($params)->toArray(),
            'describeSecurityGroups' => $ec2Client->describeSecurityGroups($params)->toArray(),
            'describeFlowLogs' => $ec2Client->describeFlowLogs($params)->toArray(),
            'describeRegions' => $ec2Client->describeRegions($params)->toArray(),
            default => throw new \InvalidArgumentException("Unknown EC2 method: {$method}"),
        }, ['region' => $region]);
    }
}
