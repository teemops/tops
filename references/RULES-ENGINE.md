# RULES ENGINE

The rules engine is responsible for 2 areas:
1./ Determining which checks to run for a given scan
2./ Determining the severity of findings based on the rules defined

##  Determining which checks to run

When a scan is initiated, the rules engine will determine which checks to run based on the following criteria:
- The type of scan being performed (e.g. EC2, S3, IAM, RDS, etc)
- The tasks.json file associated with the scan type

tasks.json files can be updated to add as many checks as needed. The check is essentially a reference to a service client
that will be used to perform the check AND the specific methods that will be called on that client.

e.g. listBuckets, getBucketAcl, etc.

Example tasks file: tasks.json

```json
{
    "$schema": "tops/findings/tasks/schema.v1",
    "name": "aws-ec2-tasks.json",
    "description": "Tasks for auditing AWS EC2 resources",
    "services": {
        "ec2": {
            "client": "EC2Client",
            "methods": [
                "describeInstances",
                "describeSubnets",
                "describeSecurityGroups"
            ]
        }
    }
}
```

## Determining the severity of findings

When a scan is completed, the rules engine will determine the severity of each finding based on the following criteria:
- The rules defined in the rules engine
- The context of the finding (e.g. resource type, region, etc)
- The specific rule e.g. basic.json, cis.json, pci.json.
- By Default the system uses basic.json and this will always be applied.

The basic.json file uses the method field to map to the method called on the service client defined in tasks.json.

Example rules file: basic.json

```json
{
    "$schema": "tops/findings/ruleset/schema.v1",
    "name": "basic.json",
    "description": "Basic rule set for auditing AWS environments",
    "rules": [
        {
            "rule": "tops-iam-001",
            "name": "IAM User MFA Token",
            "service": "iam",
            "method": "listMFADevices",
            "description": "IAM Users should have MFA enabled",
            "severity": "high",
            "condition": "r.MFADevices.length == 0"
        },
        {
            "rule": "tops-iam-002",
            "name": "IAM User",
            "service": "iam",
            "method": "listAccessKeys",
            "description": "Access Keys should not be used for IAM Users. Instead use IAM Identity Center to setup Single Sign-on.",
            "severity": "medium",
            "condition": "r.AccessKeyMetadata.length > 0"
        },
        {
            "rule": "tops-iam-003",
            "name": "IAM User Inline Policies",
            "service": "iam",
            "method": "listUserPolicies",
            "description": "IAM Users should not have inline IAM policies. Instead use IAM Groups to define IAM Policies and assign the user to the group.",
            "severity": "medium",
            "condition": "r.PolicyNames.length > 0"
        },
        {
            "rule": "tops-iam-004",
            "name": "IAM User Attached Policies",
            "service": "iam",
            "method": "listAttachedUserPolicies",
            "description": "IAM Policies should not be attached to IAM Users. Instead use IAM Groups to define IAM Policies and assign the user to the group.",
            "severity": "medium",
            "condition": "r.AttachedPolicies.length > 0"
        },
        {
            "rule": "tops-route53-001",
            "name": "Route53 DNS Sec not enabled",
            "service": "zone",
            "method": "getDNSSEC",
            "description": "DNSSEC should be enabled on your Route53 Public Hosted Zones. This will increase the likelihood that end users are communicating with your domain and not a malicious domain.",
            "severity": "high",
            "condition": "r.Status.ServeSignature != 'SIGNING'"
        },
        {
            "rule": "tops-s3-001",
            "name": "S3 Public Access Block",
            "service": "s3",
            "method": "getPublicAccessBlock",
            "description": "S3 Buckets should not be publicly available",
            "severity": "high",
            "condition": "r==false || (r.PublicAccessBlockConfiguration.BlockPublicAcls == false || r.PublicAccessBlockConfiguration.BlockPublicPolicy == false || r.PublicAccessBlockConfiguration.IgnorePublicAcls == false || r.PublicAccessBlockConfiguration.RestrictPublicBuckets == false)"
        },
        {
            "rule": "tops-s3-002",
            "name": "S3 Bucket Encryption",
            "service": "s3",
            "method": "getBucketEncryption",
            "description": "S3 Buckets should have encryption enabled",
            "severity": "medium",
            "condition": "r.ServerSideEncryptionConfiguration.Rules[0].BucketKeyEnabled == false"
        },
        {
            "rule": "tops-s3-003",
            "name": "S3 Bucket versioning",
            "service": "s3",
            "method": "getBucketVersioning",
            "description": "S3 Buckets should have versioning enabled",
            "severity": "medium",
            "condition": "r.Status != 'Enabled'"
        },
        {
            "rule": "tops-ec2-001",
            "name": "EC2 Cloudwatch Monitoring",
            "service": "ec2",
            "method": "instance",
            "description": "EC2 Instances should have Cloudwatch Monitoring enabled",
            "severity": "medium",
            "condition": "r.Monitoring.State == 'disabled'"
        },
        {
            "rule": "tops-ec2-002",
            "name": "EC2 Public IP Address",
            "service": "ec2",
            "method": "instance",
            "description": "EC2 Instances should not have a Public IP Address",
            "severity": "medium",
            "condition": "r.PublicIpAddress != undefined"
        },
        {
            "rule": "tops-ec2-003",
            "name": "EC2 Auto Recovery",
            "service": "ec2",
            "method": "instance",
            "description": "EC2 Instances should have Auto Recovery enabled",
            "severity": "medium",
            "condition": "r.MaintenanceOptions.AutoRecovery == 'disabled'"
        },
        {
            "rule": "tops-ec2-004",
            "name": "Subnet Default Public IP",
            "service": "ec2",
            "method": "describeSubnets",
            "description": "EC2 Subnets should not have Public IP enabled by default",
            "severity": "medium",
            "condition": "r.Subnets[0].MapPublicIpOnLaunch == true"
        }
    ]
}
```