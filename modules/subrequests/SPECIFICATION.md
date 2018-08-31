# Request Blueprint

## Request format

### Basic requests
A request blueprint is a document containing a list of HTTP requests that a
client can send to a server. Once the server receives the request blueprint, it
**MUST** process all the HTTP requests in the blueprint to produce a
single response containing the information of the responses to the blueprint
requests.

The request that sends the request blueprint is referenced as the master request
or the root request.

An example of a blueprint could look like this:

```json
[
  {
    "requestId": "req-1",
    "uri": "/restaurants/886e3b86-fa53-4bb3-b2c2-3ed544f1cd51?fields=title",
    "action": "view",
    "headers": {
      "Accept": "application/json"
    }
  },
  {
    "requestId": "req-2",
    "uri": "/deals?page[limit]=5",
    "action": "view",
    "headers": {
      "Accept": "application/vnd.api+json"
    }
  },
  {
    "requestId": "req-3",
    "uri": "/stats",
    "action": "create",
    "body": "{\"visitor\":\"anonymoys\"}",
    "headers": {
      "Accept": "application/json",
      "Content-Type": "application/json"
    }
  }
]
```

The blueprint above represents three different requests that may be run in
parallel by the server. These requests could transform into the following
parallel HTTP requests.

```http
GET /restaurants/886e3b86-fa53-4bb3-b2c2-3ed544f1cd51 HTTP/1.1
Host: example.org
Accept: application/vnd.api+json

```
```http
GET /restaurants/886e3b86-fa53-4bb3-b2c2-3ed544f1cd51 HTTP/1.1
Host: example.org
Accept: application/vnd.api+json

```
```http
POST /stats HTTP/1.1
Host: example.org
Accept: application/json
Content-Type: application/json

{"visitor":"anonymoys"}
```

### Payload format
You **MUST** provide a `Content-Type` header when sending a request to the front
controller that processes the request blueprint. The content type header will
determine what format is used in the blueprint. All the examples in this
document assume `application/json` as the content type.

You **MAY** send the blueprint document as the payload in a `POST` request.
Alternatively, you **MAY** also send it in a `GET` request as a [percent
encoded](https://tools.ietf.org/html/rfc3986#section-2.1) string in a query
string parameter with the name of `query`.

The blueprint document **MUST** be an array of subrequests. Each one of these
subrequests **SHOULD** contain at least the following properties:

  * `action`: this indicates the type of action this subrequest will execute.
    Common values for this property are `view`, `create`, `update`, `replace`,
    `delete`, `exists` and `discover`.
  * `uri`: the URI for the subrequest.

Additionally the payload for a subrequest **MAY** contain the following
properties:

  * `requestId`: a unique identifier for the subrequest. This will be used to
    match the subrequest with one of the partial responses.
  * `body`: the serialized content of the body for the subrequest.
  * `headers`: an object of key value pairs. Each key **MUST** be interpreted as
    a header name for the subrequest, and the values as the header value.
  * `waitFor`: contains the array of request IDs from another request. Indicates
    that the current subrequest depends on the other subrequest. When this
    property is present, the that particular subrequest cannot be processed
    until the referenced request has generated a response.

### Sequential requests
Many times it is necessary to use the information of previous requests in order
to build the correct request. That happens because a given request has a
dependency some other requests to be resolved first. That use case is solved by
request dependencies and by response pointers.

#### Request dependency
Any request can express a dependency on the response of a previous request. To
do so such request **SHOULD** express the dependency in the `waitFor` key. The
contents of this property **WILL** contain the request ID this request depends
on.

One subrequest **CAN** only indicate a dependency to one other subrequest. If a
subrequest depends on a collection of subrequests at the same time, then the
user **MAY** turn that collection into a request blueprint. That will to convert
that collection into a single subrequest that can be waited for.

#### Response embedding
Some subrequests need information that is only made available when a previous
subrequest has been processed and turned into a response. In that situation a
subrequest **CAN** contain a replacement token that **MUST** be resolved from
responses to previous subrequests in the same blueprint.

The format of the replacement token is:
```
{{/<request-id>.<location>@<json-path-expression>}}
```

The replacement data will be extracted from the response to the request
indicated by the request ID in the replacement token. The specific data in that
response to be embedded will be selected using a json path. If the JSON path
expression resolves more than one result, then multiple responses will be
generated for that single request.

A data pointer is a string that specifies what part of the referenced response
should be embedded in place of the token. The embedded data **SHOULD** be
serialized into a string according to the content type specified for the master
request.

When the content type of the master request is set to `application/json` then
the data pointer **SHOULD** conform to the JSON pointer specification [RFC
6901](https://tools.ietf.org/html/rfc6901). If the content type is set to
`application/xml` then the data pointer **SHOULD** be an XPath 2.0 compatible
with the [W3C specification](https://www.w3.org/TR/xpath20).

Replacement tokens **SHOULD NOT** be used in the `requestId` or `waitFor`
properties.

#### Example
The following example shows how you can make use of the response embedding in
the request blueprint to express dependencies.

```json
[
  {
    "requestId": "req-1",
    "uri": "/restaurants/886e3b86-fa53-4bb3-b2c2-3ed544f1cd51&fields=menus",
    "action": "view",
    "headers": {
      "Accept": "application/json"
    }
  },
  {
    "requestId": "req-2",
    "waitFor": ["req-1"],
    "uri": "/menus/{{req-1.body@$.rels.meny.id}}",
    "action": "view",
    "headers": {
      "Accept": "application/json"
    }
  },
  {
    "requestId": "req-3",
    "waitFor": ["req-2"],
    "uri": "/menus/{{req-1.body@$.rels.meny.id}}/courses/{{req-2.body@$.mainCourse.id}}",
    "action": "view",
    "headers": {
      "Accept": "application/json"
    }
  }
]
```

This example shows how the request for the restaurant menu needs information in
the body from the request to the response to _req-1_. It also shows that
_req-3_ depends on both _req-1_ and _req-2_ to compose the request.

# Response format
Once all the requests have been processed and the corresponding responses have
been generated, the server **MUST** give a single response to the master
request containing the responses to all subrequests.

The response **MUST** use the 207 response code for multiple status, since each
partial response will specify the status for their requests. In addition to that
the response to the master request will use the `multipart/related` MIME type as
specified in [RFC 2387](https://tools.ietf.org/html/rfc2387).

The response contents to the blueprint above could look like:

```http
--e43889
Cache-Control: no-cache
Content-Id:    <req-1>
Content-Type:  application/json
Status:        200
 
{"attrs": {"name": "Foo restaurant"}, "rels": {"menu": {"id": "1234"}}}
--e43889
Cache-Control: no-cache
Content-Id:    <req-2>
Content-Type:  application/json
Status:        200
 
{"mainCourse": {"id": "meat-pie"}, "desert": {"id": "9876"}}
--e43889
Cache-Control: no-cache
Content-Id:    <req-3>
Content-Type:  application/vnd.api+json
Status:        200

 
{"ingredients": ["meat", "crust"]}
--e43889--
```

Whereas the response HTTP header for the content type specifies the delimiter,
among other information, as:

```http
Conten-Type: multipart/related; boundary="e43889", type=application/json
```
