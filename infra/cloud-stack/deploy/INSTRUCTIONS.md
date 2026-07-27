Create a script that does the following:
- Use the deployment region from `TOPS_DEPLOYMENT_REGION` (falling back to `AWS_DEFAULT_REGION`); do not hard-code a region
- Launches the @templates/iam.role.child.account.cfn.yaml as a CloudFormation
- Launches the @templates/sns.topic.cfn.yaml as a CloudFormation StackSet
- Launches the @templates/vpc.launch.cfn.yaml
- Launches the @templates/ec2.alb.launch.cfn.yaml 
 
