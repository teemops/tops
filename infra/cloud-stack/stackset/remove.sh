#!/bin/bash
SNS_STACK_NAME=${SNS_STACK_NAME:-teemops-sns-topic}

AWS_PROFILE=${AWS_PROFILE:-tops-test-admin}
# Get the current account ID
ACCOUNT_ID=$(aws sts get-caller-identity --query "Account" --output text 2>&1)

echo "ACCOUNT_ID: $ACCOUNT_ID"
#list stack instances and get all regions with a space between each region
REGIONS=$(aws cloudformation list-stack-instances --stack-set-name $SNS_STACK_NAME --query "Summaries[].Region" --output text)

#loop through each region and delete the stack instances
for REGION in $REGIONS; do
    echo "Deleting stack instances in region: $REGION"
    aws cloudformation delete-stack-instances \
        --stack-set-name $SNS_STACK_NAME \
        --accounts $ACCOUNT_ID \
        --regions $REGION \
        --no-retain-stacks \
        --operation-preferences MaxConcurrentCount=1,FailureToleranceCount=0
done
echo "Stack instances deleted successfully"
