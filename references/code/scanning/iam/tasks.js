/**
 * Provides rules defintion for a scan
 */
var tasks = {
    listUsers: {
        task: 'listUsers',
        id: 'user',
        key: 'Name',
        description: 'List all users',
        items: {},
        actions: [
            'getUser',
            'listMFADevices',
            'listAccessKeys',
            'listUserPolicies',
            'listGroupsForUser',
            'listAttachedUserPolicies'
        ]
    },
    getUser: {},
    listMFADevices: {},
    listAccessKeys: {},
    listUserPolicies: {},
    listGroupsForUser: {},
    listAttachedUserPolicies: {},
}
var config = {
    service: 'iam',
    start: tasks.listUsers,
    defaults: {
        actions: {
            params: (item) => {
                return {
                    UserName: item.UserName
                }
            }
        }
    }
}

tasks.listUsers.items = {
    Users: [
        {
            Name: 'UserName',
            Type: 'String',
        },
        {
            Name: 'UserId',
            Type: 'String',
        },
        {
            Name: 'Arn',
            Type: 'String',
        },
        {
            Name: 'CreateDate',
            Type: 'Date',
        },
        {
            Name: 'PasswordLastUsed',
            Type: 'Date',
        },
        {
            Name: 'Path',
            Type: 'String',
        },
    ]
}

// tasks.listUserPolicies = {
//     params: (item) => {
//         return {
//             Filters: [
//                 {
//                     UserName: item.UserName
//                 }
//             ]
//         }
//     }
// }

//list user's AWS Console login details

module.exports = { config: config, tasks: tasks };