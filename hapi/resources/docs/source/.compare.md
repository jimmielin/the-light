---
title: API Reference

language_tabs:
- bash
- javascript

includes:

search: true

toc_footers:
- <a href='http://github.com/mpociot/documentarian'>Documentation Powered by Documentarian</a>
---
<!-- START_INFO -->
# Info

Welcome to the generated API reference.
[Get Postman Collection](http://localhost/hapi/public/docs/collection.json)

<!-- END_INFO -->

#Attentions


<!-- START_d8ef429e18af28329b7f66746eecb134 -->
## Add/Remove Attention
Follows/unfollows a given post. As a `PUT` request, if the action already has been taken, it will be 
silently dropped.

> Example request:

```bash
curl -X PUT \
    "http://localhost/hapi/public/api/holes/attention/do/ut?user_token=porro&switch=fugiat" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/attention/do/ut"
);

let params = {
    "user_token": "porro",
    "switch": "fugiat",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "PUT",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "success": true,
    "data": {
        "pid": 1,
        "text": "Test",
        "type": "text",
        "timestamp": 1234567890,
        "likenum": 2,
        "reply": 25,
        "tag": null,
        "extra": null,
        "attention": true
    }
}
```
> Example response (200):

```json
{
    "error_msg": "找不到该树洞",
    "error": "NotFound"
}
```

### HTTP Request
`PUT api/holes/attention/do/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | Target PID
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
    `switch` |  required  | 1 = Add attention; 0 = Remove attention.

<!-- END_d8ef429e18af28329b7f66746eecb134 -->

#Invites


<!-- START_02acc619314d21f06c59da0f64e4ef75 -->
## Retrieve invitation code

Retrieve invitation code for private / public beta.
If there is no invite code for this user it also generates as side-effect (ugh)

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/users/invites?user_token=dolores" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/users/invites"
);

let params = {
    "user_token": "dolores",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": "A95027CF8",
    "remaining": 2,
    "error": null
}
```

### HTTP Request
`GET api/users/invites`

#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 

<!-- END_02acc619314d21f06c59da0f64e4ef75 -->

#Moderation


All bans are handled by the observer hooked to flags, so there is no need to handle logic here.
Simply insert flags into the DB.
<!-- START_dd0186b94502fcc61e36145e9c93fb1b -->
## Report hole
Replaces `report`. Reports this main post for deletion (type = `report`) or hiding (type = `fold`)

When type is `fold`:
For ring &lt; 4, the post will be folded immediately. Otherwise, it will take `MIN_PUBLIC_FOLD_THRESHOLD` (right now `3`) to fold this post.
For ring &lt; 4, if the post was already folded, the reason will be overridden.
The API is throttled for once in 10 seconds with a silent drop if flood control is not met.
The folding reason will be used directly (if reporter is author) or using the most voted one (otherwise) and saved into `verdict` in either table.

Possible errors:
- `AlreadyFlagged`: cannot flag the same post/comment twice.
- `CannotFlagAlreadyBanned`: if there is ban history, the flag will be dropped
If entitlement `Sudoers`, then these checks are relaxed.

> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/holes/flag/illum?user_token=est" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"content":"qui","type":"voluptatem"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/flag/illum"
);

let params = {
    "user_token": "est",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "content": "qui",
    "type": "voluptatem"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null
}
```
> Example response (200):

```json
{
    "error_msg": "Error Message",
    "error": "NotFound"
}
```
> Example response (200):

```json
{
    "error_msg": "已经举报过了",
    "error": "AlreadyFlagged"
}
```

### HTTP Request
`POST api/holes/flag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `content` | required |  optional  | Report reason. Can be empty but field must exist.
        `type` | `report` |  optional  | or `fold`. Default: `report`
    
<!-- END_dd0186b94502fcc61e36145e9c93fb1b -->

<!-- START_0bceaf0426133a8026e20065d35a8ebc -->
## Report comment
Replaces `report`. Reports this comment for deletion (type = `report`) or hiding (type = `fold`)

When type is `fold`:
For ring &lt; 4, the comment will be folded immediately. Otherwise, it will take `MIN_PUBLIC_FOLD_THRESHOLD` (right now `3`) to fold this comment.
For ring &lt; 4, if the post was already folded, the reason will be overridden.
The API is throttled for once in 10 seconds with a silent drop if flood control is not met.
The folding reason will be used directly (if reporter is author) or using the most voted one (otherwise) and saved into `verdict` in either table.

Possible errors:
- `AlreadyFlagged`: cannot flag the same post/comment twice.
- `CannotFlagAlreadyBanned`: if there is ban history, the flag will be dropped
If entitlement `Sudoers`, then these checks are relaxed.

> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/comments/flag/nam?user_token=delectus" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"content":"rem","type":"tempora"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/comments/flag/nam"
);

let params = {
    "user_token": "delectus",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "content": "rem",
    "type": "tempora"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null
}
```
> Example response (200):

```json
{
    "error_msg": "Error Message",
    "error": "NotFound"
}
```
> Example response (200):

```json
{
    "error_msg": "已经举报过了",
    "error": "AlreadyFlagged"
}
```

### HTTP Request
`POST api/comments/flag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `content` | required |  optional  | Report reason. Can be empty but field must exist.
        `type` | `report` |  optional  | or `fold`. Default: `report`
    
<!-- END_0bceaf0426133a8026e20065d35a8ebc -->

<!-- START_82098c0391940f9ab9a71cb9438b2085 -->
## View Post Flags

View flags attached to post.
If a ban action was imposed, the ban information will be available in `ban_data`.

An **encrypted** representation of the `user_id` is passed and known to the server. This encrypted representation can be used to look up flags by this user in a different future API.

Requires entitlement `ViewingFlags`.

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/holes/flag/reprehenderit?user_token=sunt" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/flag/reprehenderit"
);

let params = {
    "user_token": "sunt",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null,
    "data": {
        "created_at": 1234567890,
        "content": "reason",
        "user_id": "ab9210fca90"
    },
    "ban_data": null
}
```
> Example response (200):

```json
{
    "error_msg": "无权查看此页面",
    "error": "NoPermission"
}
```

### HTTP Request
`GET api/holes/flag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 

<!-- END_82098c0391940f9ab9a71cb9438b2085 -->

<!-- START_bfe0b87ca5aa632da14deb0c5904c772 -->
## View Comment Flags

View flags attached to comment.
If a ban action was imposed, the ban information will be available in `ban_data`.

An **encrypted** representation of the `user_id` is passed and known to the server. This encrypted representation can be used to look up flags by this user in a different future API.

Requires entitlement `ViewingFlags`.

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/comments/flag/molestiae?user_token=debitis" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/comments/flag/molestiae"
);

let params = {
    "user_token": "debitis",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null,
    "data": {
        "created_at": 1234567890,
        "content": "reason",
        "user_id": "ab9210fca90"
    },
    "ban_data": null
}
```
> Example response (200):

```json
{
    "error_msg": "无权查看此页面",
    "error": "NoPermission"
}
```

### HTTP Request
`GET api/comments/flag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 

<!-- END_bfe0b87ca5aa632da14deb0c5904c772 -->

<!-- START_c0711d435676195e1981d1745b454abb -->
## Tag Post
This will add a tag to the post. Requires entitlement `Tagging`.

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/holes/tag/corporis?user_token=blanditiis" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"content":"ut"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/tag/corporis"
);

let params = {
    "user_token": "blanditiis",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "content": "ut"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/holes/tag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `content` | required |  optional  | 
    
<!-- END_c0711d435676195e1981d1745b454abb -->

<!-- START_3f2d12e4466bc69e587f53e67e9e6682 -->
## Tag Comment
This will add a tag to the comment. Requires entitlement `Tagging`.

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/comments/tag/sapiente?user_token=ut" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"content":"aut"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/comments/tag/sapiente"
);

let params = {
    "user_token": "ut",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "content": "aut"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/comments/tag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `content` | required |  optional  | 
    
<!-- END_3f2d12e4466bc69e587f53e67e9e6682 -->

<!-- START_dfd58db7876726f6d3dda82093891156 -->
## Edit Post
Edits post contents. If only `content` is edited, the entitlement `EditingText` is required.
Additionally if `type` and `extra` are modified, the entitlement `EditingTypeExtra` is required.

Not all arguments need to be present.

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/holes/edit/similique?user_token=sapiente" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"text":"omnis","type":"officiis","extra":"qui"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/edit/similique"
);

let params = {
    "user_token": "sapiente",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "text": "omnis",
    "type": "officiis",
    "extra": "qui"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/holes/edit/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `text` | string |  optional  | 
        `type` | string |  optional  | text/image/any..
        `extra` | string |  optional  | any raw extra data
    
<!-- END_dfd58db7876726f6d3dda82093891156 -->

<!-- START_936862ac79e7489648e7b49f11e51d7b -->
## Edit Comment
Edits comment contents. If only `content` is edited, the entitlement `EditingText` is required.
Additionally if `type` and `extra` are modified, the entitlement `EditingTypeExtra` is required.

Not all arguments need to be present.

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/comments/edit/reiciendis?user_token=quo" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"text":"dignissimos","type":"asperiores","extra":"quia"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/comments/edit/reiciendis"
);

let params = {
    "user_token": "quo",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "text": "dignissimos",
    "type": "asperiores",
    "extra": "quia"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/comments/edit/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `text` | string |  optional  | 
        `type` | string |  optional  | text/image/any..
        `extra` | string |  optional  | any raw extra data
    
<!-- END_936862ac79e7489648e7b49f11e51d7b -->

<!-- START_fca7ea1a788311eddc1b2cbfebdb1291 -->
## Unban Post

"Unflags" a post, removing the imposed ban. Requires entitlement `UndoBan`.
Once a post has been unbanned, it can *never* be banned again.

Requires entitlement `UndoBan`.

> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/holes/unflag/tempore?user_token=voluptatum" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"undelete":11,"unfold":15,"unban":1,"reason":"optio"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/unflag/tempore"
);

let params = {
    "user_token": "voluptatum",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "undelete": 11,
    "unfold": 15,
    "unban": 1,
    "reason": "optio"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/holes/unflag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `undelete` | integer |  required  | undo delete action? (`0` or `1`)
        `unfold` | integer |  required  | undo fold action? (`0` or `1`)
        `unban` | integer |  required  | undo ban action? (`0` or `1`)
        `reason` | string |  optional  | unban reason - will be recorded on file
    
<!-- END_fca7ea1a788311eddc1b2cbfebdb1291 -->

<!-- START_4400cde054ef75b1d14f9461d1824575 -->
## Unban Comment

"Unflags" a post, removing the imposed ban. Requires entitlement `UndoBan`.
Once a post has been unbanned, it can *never* be banned again.

Requires entitlement `UndoBan`.

> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/comments/unflag/qui?user_token=et" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"undelete":11,"unfold":17,"unban":12,"reason":"est"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/comments/unflag/qui"
);

let params = {
    "user_token": "et",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "undelete": 11,
    "unfold": 17,
    "unban": 12,
    "reason": "est"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/comments/unflag/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `undelete` | integer |  required  | undo delete action? (`0` or `1`)
        `unfold` | integer |  required  | undo fold action? (`0` or `1`)
        `unban` | integer |  required  | undo ban action? (`0` or `1`)
        `reason` | string |  optional  | unban reason - will be recorded on file
    
<!-- END_4400cde054ef75b1d14f9461d1824575 -->

#Posting


<!-- START_54d067956d73549a403213c3a3d6c2e7 -->
## Post a new hole
Replaces `dopost`. Returns as the data the newly inserted post ID.
Note that if content is deemed to be duplicate or flood, Error 429 `FloodControl` will trigger. If user is banned from posting, Error 403 `Banned` will trigger; `message` will show the specific ban reason and expiration time.

The maximum size permitted for `image` is 1 MiB after base64-decoding.

Possible error codes:
- `InsufficientParameters`
- `NotFound`
- `Banned`
- `UnsupportedType`
- `FloodControl` - &quot;您发帖速度过快, 请等待后再发送!&quot;
- 500: `InternalAtomicTransactionError`

For image upload:
- `IncorrectBinaryEncoding`
- `IncorrectBase64EncodingLegacy`
- `ExceededMaximumFileSize1MB`
- `IncorrectImageFormat`

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/holes/post?user_token=hic" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"text":"provident","type":"veritatis","data_type":"necessitatibus","data":"dignissimos","bridge":false}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/post"
);

let params = {
    "user_token": "hic",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "text": "provident",
    "type": "veritatis",
    "data_type": "necessitatibus",
    "data": "dignissimos",
    "bridge": false
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null,
    "data": "1820322"
}
```
> Example response (200):

```json
{
    "error_msg": "您被封禁至 until, 因此无法发帖",
    "error": "Banned",
    "message": "Banned due to reason XYZ until 2038-01-01 11:22:33Z"
}
```

### HTTP Request
`POST api/holes/post`

#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `text` | string |  required  | Post text. Can be blank but must be present.
        `type` | string |  required  | text, image
        `data_type` | string |  optional  | If given: jpeg, png, gif ... (PHP supported image formats).
        `data` | string |  optional  | If `data_type` exists, raw data; otherwise a base64-encoded image.
        `bridge` | boolean |  optional  | Is this a bridge hole? true|false. Default: false
    
<!-- END_54d067956d73549a403213c3a3d6c2e7 -->

<!-- START_61a8bee511cefeeee0594029a11c92c2 -->
## Reply to a hole
Replaces `docomment`. Returns as the data the newly inserted comment ID.
Note that if content is deemed to be duplicate or flood, Error 429 `FloodControl` will trigger. If user is banned from posting, Error 403 `Banned` will trigger; `message` will show the raw ban reason and `until` the timestamped expiry time.

The operations on this method are pessimistic locked.

Possible error codes:
- `InsufficientParameters`
- `NotFound`
- `Banned`
- `UnsupportedType`
- `FloodControl`
- 500: `InternalAtomicTransactionError`

For image upload:
- `IncorrectBinaryEncoding`
- `IncorrectBase64EncodingLegacy`
- `ExceededMaximumFileSize1MB`
- `IncorrectImageFormat`
- `UnsupportedUploadImageFormat`

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/api/holes/reply/sapiente?user_token=et" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"text":"commodi","type":"dolores","data_type":"odio","data":"pariatur"}'

```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/reply/sapiente"
);

let params = {
    "user_token": "et",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "text": "commodi",
    "type": "dolores",
    "data_type": "odio",
    "data": "pariatur"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null,
    "data": "1820322"
}
```
> Example response (200):

```json
{
    "error_msg": "参数错误",
    "error": "InsufficientParameters"
}
```
> Example response (200):

```json
{
    "error_msg": "您被封禁至 until, 因此无法发帖",
    "error": "Banned",
    "message": "Banned due to reason XYZ until 2038-01-01 11:22:33Z"
}
```
> Example response (500):

```json
{
    "error_msg": "服务器内部错误",
    "error": "InternalAtomicTransactionError"
}
```

### HTTP Request
`POST api/holes/reply/{id}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | Target PID
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `text` | string |  required  | 
        `type` | string |  optional  | text or image
        `data_type` | string |  optional  | If given: jpeg, png, gif ... (PHP supported image formats).
        `data` | string |  optional  | If `data_type` exists, raw data; otherwise a base64-encoded image.
    
<!-- END_61a8bee511cefeeee0594029a11c92c2 -->

#System Messages


<!-- START_0c00225057590c878b2eca3acf472125 -->
## View System Messages
Lists system messages received by user. Only shows latest 25 by design.

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/messages/list?user_token=qui" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/messages/list"
);

let params = {
    "user_token": "qui",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET api/messages/list`

#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 

<!-- END_0c00225057590c878b2eca3acf472125 -->

#User and Permission


<!-- START_543a4fd2de4f96aa4572963f24265c24 -->
## Get Info

Retrieve a list of flags which indicate user permission to show and hide UX.
Also returns the application version.

Entitlements that the user does not have access to are *hidden*.

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/users/info?user_token=qui" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/users/info"
);

let params = {
    "user_token": "qui",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "entitlements": {
        "EditingText": true,
        "EditingTypeExtra": true,
        "Tagging": true,
        "ViewingDeleted": true,
        "ViewingFlags": true,
        "UndoBan": true,
        "Sudoers": true
    },
    "ring": 0,
    "ban_until": null,
    "latest_message": null,
    "alerts": [
        {
            "type": "info",
            "is_html": true,
            "message": "Test"
        }
    ],
    "version": "20200913",
    "error": null
}
```

### HTTP Request
`GET api/users/info`

#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 

<!-- END_543a4fd2de4f96aa4572963f24265c24 -->

#Viewing


<!-- START_ba7d114130a7a3777d0d73681eb8f36d -->
## List holes
Replaces `getlist`. Additionally, returns the `begchunk` (latest) and `endchunk` (oldest) of the chunk being retrieved, and `favorites_in_chunk` which includes followed posts within the chunk.

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/holes/list/25?user_token=illo&limit=cumque" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/list/25"
);

let params = {
    "user_token": "illo",
    "limit": "cumque",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null,
    "count": 25,
    "data": [
        {
            "pid": 1,
            "text": "Test",
            "type": "text",
            "timestamp": 1234567890,
            "likenum": 2,
            "reply": 25,
            "tag": null,
            "extra": null,
            "attention": true,
            "hidden": 1,
            "verdict": "折叠理由"
        }
    ],
    "timestamp": 1599589514,
    "begchunk": 25,
    "endchunk": 1,
    "favorites_in_chunk": {
        "1": 1599665989
    }
}
```
> Example response (200):

```json
{
    "error_msg": "Error Message",
    "error": "Unauthenticated"
}
```

### HTTP Request
`GET api/holes/list/{after?}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `after` |  optional  | Starting offset of hole ID. Defaults to latest if 0 or negative or not present.
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
    `limit` |  optional  | Limit size per fetch. Defaults to 25. Maximum 50.

<!-- END_ba7d114130a7a3777d0d73681eb8f36d -->

<!-- START_45fb3d05b56c13bbf7b2327664d12e42 -->
## Get followed holes
Replaces `getattention`. Returns data in the same format as `/holes/list`, while also allowing an optional `after?` argument (note that holes are ordered in BACKWARDS order, newest first). It also provides the total number of followed holes, which can be used for pagination.

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/holes/attention/25?user_token=maiores&limit=optio" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/attention/25"
);

let params = {
    "user_token": "maiores",
    "limit": "optio",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null,
    "count": 25,
    "total": 72,
    "data": [
        {
            "pid": 1,
            "text": "Test",
            "type": "text",
            "timestamp": 1234567890,
            "likenum": 2,
            "reply": 25,
            "tag": null,
            "extra": null,
            "attention": true
        }
    ],
    "timestamp": 1599589514,
    "begchunk": 25,
    "endchunk": 1
}
```
> Example response (200):

```json
{
    "error_msg": "Not Logged In",
    "error": "Unauthenticated"
}
```

### HTTP Request
`GET api/holes/attention/{after?}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `after` |  optional  | Starting offset of hole ID. Defaults to latest if 0 or negative or not present.
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
    `limit` |  optional  | Limit size per fetch. Defaults to 25. Maximum 50.

<!-- END_45fb3d05b56c13bbf7b2327664d12e42 -->

<!-- START_e1280616cc18bfc7714ceaa19ffa36ae -->
## Search
Replaces `search`. Returns a collection of Posts based on search input. The result is similar to `/holes/list`.

The usual caveat with ngram 2 index is of course, single keys cannot be searched.

The FULLTEXT index created for the posts table is: `FULLTEXT KEY content(content) WITH PARSER ngram`.

**As MariaDB 10.5 is yet to support the ngram parser, currently the search is a very slow `%LIKE%` search. It will be changed next month when MariaDB updates.** Hopefully this will be done by the end of this month. See [MDEV-10267](https://jira.mariadb.org/browse/MDEV-10267)

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/holes/search/25?user_token=ducimus&keywords=molestiae" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/search/25"
);

let params = {
    "user_token": "ducimus",
    "keywords": "molestiae",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "error": null,
    "data": [
        {
            "pid": 1,
            "hot": 1599665989,
            "timestamp": 1599660194,
            "reply": 0,
            "likenum": 1,
            "tag": null,
            "text": "Genesis",
            "hidden": 0,
            "url": "",
            "extra": null,
            "attention": true
        }
    ],
    "count": 1,
    "begchunk": 1,
    "endchunk": 1
}
```
> Example response (200):

```json
{
    "error": null,
    "data": [],
    "count": 0,
    "begchunk": null,
    "endchunk": null
}
```

### HTTP Request
`GET api/holes/search/{after?}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `after` |  optional  | Starting offset of hole ID. Defaults to latest if 0 or negative or not present.
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 
    `keywords` |  required  | Keyword(s) to search. If numeric, will display that hole first if it matches.

<!-- END_e1280616cc18bfc7714ceaa19ffa36ae -->

<!-- START_7b693593811f97fc1ff2dfbf51c3a966 -->
## View single hole
Replaces `getone`, `getcomment`, following `getcomment` conventions to return `data` for comments data, and merging `getone` data into `post_data`.

If post `hidden` is 1, it means that the content should be folded and not displayed by default. Entitlement `ViewingDeleted` additionally has access to `hidden` 2 comments.

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/api/holes/view/laboriosam/8?user_token=libero" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/api/holes/view/laboriosam/8"
);

let params = {
    "user_token": "libero",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
null
```
> Example response (200):

```json
{
    "error_msg": "Error Message",
    "error": "NotFound"
}
```

### HTTP Request
`GET api/holes/view/{id}/{after?}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `id` |  required  | 
    `after` |  optional  | After sequence number (0, 1, 2, ...). If **negative**, then the limit is interpreted as counting backwards from `-after` (i.e. -150 loads -150...-101). The chunk size is always 100 if after is specified.
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `user_token` |  required  | 

<!-- END_7b693593811f97fc1ff2dfbf51c3a966 -->

#general


<!-- START_c3e8f38646ef32e5792f8b0c741088a3 -->
## gapi
> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/gapi" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/gapi"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET gapi`


<!-- END_c3e8f38646ef32e5792f8b0c741088a3 -->

<!-- START_71adb40b2c0adef071ba4e47f92d1f57 -->
## gapi
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/gapi" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/gapi"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST gapi`


<!-- END_71adb40b2c0adef071ba4e47f92d1f57 -->

<!-- START_0d8ee3364358597a15d6d95b8f097a2f -->
## gapi/{default}
> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/gapi/1" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/gapi/1"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET gapi/{default}`


<!-- END_0d8ee3364358597a15d6d95b8f097a2f -->

<!-- START_85bf8782bcb86a099176288a6902d9a1 -->
## gapi/{default}
> Example request:

```bash
curl -X POST \
    "http://localhost/hapi/public/gapi/1" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/gapi/1"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST gapi/{default}`


<!-- END_85bf8782bcb86a099176288a6902d9a1 -->

<!-- START_53be1e9e10a08458929a2e0ea70ddb86 -->
## Invoke the controller method.

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET /`


<!-- END_53be1e9e10a08458929a2e0ea70ddb86 -->

<!-- START_e5dd14f3c3b2776743ceca909f987e6c -->
## Invoke the controller method.

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/rules" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/rules"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET rules`


<!-- END_e5dd14f3c3b2776743ceca909f987e6c -->

<!-- START_02e76b1de32d36dca1d246390f59266f -->
## Invoke the controller method.

> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/rules-bridge" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/rules-bridge"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET rules-bridge`


<!-- END_02e76b1de32d36dca1d246390f59266f -->

<!-- START_67bbb0edb196a905febb14a098d97999 -->
## pillory
> Example request:

```bash
curl -X GET \
    -G "http://localhost/hapi/public/pillory" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/hapi/public/pillory"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET pillory`


<!-- END_67bbb0edb196a905febb14a098d97999 -->


