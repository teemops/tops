# Scan rules and service definitions

Everything a scan does is described by the JSON in this directory. Adding an AWS service
or a security check does not require PHP.

```
rules/
├── tasks/<service>/tasks.json   what to collect from AWS
├── rulesets/<name>.json         what counts as a finding
└── recommendations/tips.json    grouped remediation advice shown in the UI
```

Before opening a PR:

```bash
php artisan scan:validate-rules
```

That command is the only thing standing between a typo and a check that silently never
fires. Both file types fail quietly when wrong — a rule aimed at data nothing collects
produces no findings and no error, which is indistinguishable from a compliant account.

---

## Adding a service

Create `rules/tasks/<service>/tasks.json`. The directory name is the service's identity
everywhere else in the app, so it must match `config.service`.

```json
{
    "config": {
        "service":  "dynamodb",
        "label":    "DynamoDB",
        "client":   "dynamodb",
        "regional": true,
        "profiles": ["basic"],
        "start":    "listTables"
    },
    "tasks": [
        {
            "listTables": {
                "task": "listTables",
                "id": "table",
                "actions": [
                    { "describeTable": { "params": { "TableName": "$item" } } }
                ],
                "items": { "TableNames": [] }
            }
        }
    ]
}
```

### `config`

| Key | Required | Meaning |
|---|---|---|
| `service` | yes | Identity of the service. Must equal the directory name. |
| `label` | no | Display name in the UI. Defaults to the uppercased service name. |
| `client` | no | The AWS SDK manifest key (its endpoint prefix). Defaults to `service`. Not always guessable — ELBv2's is `elasticloadbalancingv2`. |
| `regional` | no | Whether to scan this service once per region. Defaults to `true`, the safer error: treating a regional service as global scans one region and reports the rest clean. |
| `profiles` | no | Which scan profiles include it. Defaults to `["basic"]`. |
| `arnService` | no | The service portion of this service's ARNs, for region pruning. Defaults to `service`; ELBv2's is `elasticloadbalancing`. |
| `start` | yes | The task to begin with. |
| `scanner` | no | A `GenericAwsScanner` subclass, for behaviour the SDK model cannot express. Two services use this today; see below before reaching for it. |
| `defaults` | no | Fallback `actions.params` for actions that declare none of their own. |

Method names are the SDK's camelCase operation names. They are checked against the AWS
API model, so you get every operation the service has — not a list somebody maintains.

### `tasks`

Each task calls one list/describe operation, then optionally runs `actions` per item.

| Key | Meaning |
|---|---|
| `task` | The operation name (same as the key). |
| `id` | Resource type recorded on findings (`table`, `instance`, `bucket`). |
| `key` | The field on each item holding its identifier. Omit only for scalar items. |
| `items` | Where items live: `{"<ResponseKey>": [ ...field docs... ]}`. The field list is documentation; only the key is read. |
| `itemsPath` | Dot path to items when they are nested. Overrides `items`. |
| `actions` | Follow-up calls per item. |

**Pagination is automatic.** Any operation the SDK models a paginator for is walked to
the end and flattened, so you never see a partial list.

### Item shapes

Three shapes come up, and all three are expressible without PHP:

**Objects in a list** — the common case. Declare `items` and `key`:

```json
"key": "TopicArn",
"items": { "Topics": [ { "Name": "TopicArn", "Type": "String" } ] }
```

**Bare strings in a list** — DynamoDB `listTables`, SQS `listQueues`. There is no field
to name, so omit `key`: the string *is* the identifier, and `$item` refers to it:

```json
"actions": [ { "getQueueAttributes": { "params": { "QueueUrl": "$item", "AttributeNames": ["All"] } } } ],
"items": { "QueueUrls": [] }
```

**Nested lists** — EC2 instances sit under `Reservations[].Instances[]`. Declare the path:

```json
"itemsPath": "Reservations.Instances"
```

### Action params

Params are PHP expressions with `$item` in scope, or literal values passed straight
through:

```json
{ "describeListeners": { "params": { "LoadBalancerArn": "$item['LoadBalancerArn']" } } }
{ "getQueueAttributes": { "params": { "QueueUrl": "$item", "AttributeNames": ["All"] } } }
{ "describeSecurityGroups": { "params": { "GroupIds": "array_column($item['SecurityGroups'] ?? [], 'GroupId')" } } }
```

An action that declares no params, and whose task declares no `defaults`, is called with
none. Nothing is inferred from field names.

### IAM permissions

Read-only services need no permission work: the role the onboarding CloudFormation
template creates already carries `ReadOnlyAccess`, so a new service works against every
account already onboarded, with no customer redeployment.

### When you need a custom scanner

Almost never. Two services do:

- **S3** resolves each bucket's region before bucket-scoped calls, because signing fails
  otherwise and the region is not known until asked.
- **IAM** translates `NoSuchEntity` from `getAccountPasswordPolicy` into an error marker,
  because *no password policy* is itself the finding CIS 1.8/1.9 look for.

Both subclass `GenericAwsScanner` and override one method. If you are considering a
third, check the behaviour cannot be declared instead.

---

## Adding a rule

Rules live in `rules/rulesets/<name>.json`. A rule is evaluated against every stored
response matching its `service` and `method`.

```json
{
    "rule": "tops-sqs-001",
    "name": "SQS Queue Not Encrypted At Rest",
    "service": "sqs",
    "method": "getQueueAttributes",
    "description": "SQS queues should encrypt messages at rest.",
    "severity": "medium",
    "remediation": "Enable server-side encryption on the queue.",
    "condition": "isset($data['Attributes']) && !isset($data['Attributes']['KmsMasterKeyId'])"
}
```

`rule` ids must be unique across every ruleset — they identify findings in the database
and are how compliance scoring attributes a finding to a framework.

`condition` is a PHP expression over `$data`, the stored response. It fires a finding
when it evaluates true.

### Two traps worth knowing

**Failed calls arrive as `false`.** When an API call fails, the response is stored as an
error marker that reaches your condition as `$data === false`. Decide which you mean:

```jsonc
// Absence IS the finding — no public access block configured:
"condition": "$data === false || !isset($data['PublicAccessBlockConfiguration'])"

// Absence is NOT the finding — an unreachable queue is unknown, not insecure:
"condition": "isset($data['Attributes']) && !isset($data['Attributes']['KmsMasterKeyId'])"
```

**Both the list response and each item are stored under the task's name.** A rule on a
task method sees the aggregate row *and* one row per item. Guard on a field only items
have, or the aggregate produces a spurious finding with no resource id:

```jsonc
"condition": "($data['PubliclyAccessible'] ?? false) == true"   // items only
```

### Remediation

Put it in the rule. It used to live in a PHP array, which meant a complete rule still
showed generic advice until someone edited `FindingsEngine`. A rule without
`remediation` still falls back to generic text — the validator warns about it.

---

## Adding a compliance framework

Compliance rulesets (`cis.json`, `pci.json`) work exactly like `basic.json`. A ruleset
becomes selectable in the UI once it contains at least one rule, and services join its
profile by listing that profile in their `tasks.json`.

How a rule declares membership of several frameworks at once is deliberately still open;
today a framework restates the rules it needs in its own file.
