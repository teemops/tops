# Scan logic for Rules Engine

OK there needs to be a 2 step approach here.
1./ a customer scans an AWS account. However based on this scan we are going to refer to tasks.json to run the exact commands against the AWS SDK. For example for IAM we will have listUsers, listRoles etc. This will be defined in the iam/tasks.json file somewhere.

2./ Then once the scans have run the findings will be run against the scan details. This means we need to create a separate findings table within the database, because we may have more than 1 finding for 1 API call

Example is:
- listUsers is called
- 10 users are returned
- For each user we call at least 2 methods (listMFADevices and listAccessKeys) and will have the raw output from each method. 
We should store this raw output in the scan_details table as JSON.

Then we run the findings engine against this data. So for each user we may have multiple findings. 1 finding for no MFA, 1 finding for access keys older than 90 days, 1 for 30 days, 60 days etc.


