# Gink Client (PHP)

This project is intended to help in accessing Bornemann AG's
[gink webservice](https://mygink.com/rest/v2docs/).

Essentially, all you really need is the script in **lib/GinkClient.php**. It is
nothing more than a thin wrapper around the PHP cURL functionality (you must
have this working in your PHP installation) which follows some conventions in
the webservice.

## Examples

There are a few example scripts in the "samples" directory. When you run them,
you should be in the project root directory for PHP to find the files in the
"lib" folder.

### Creating a client

```php
<?php
$client = new GinkClient();// looks for file ./gink.ini, or uses default config
$client = new GinkClient("https://mygink.com/rest/v2/");// uses HTTPS
$client = new GinkClient(null, "/tmp");// set a different temp directory
```

### Authentication
```php
<?php
$gateway = $client->token($username, $password);// authenticate using password
$gateway = $client->token($appKey);// authenticate using application key
```

### Getting more data

The Gink API requires you to follow links from the **gateway** data object to
other objects.

```php
<?php
$trackers = $client->get($gateway->trackers_url);// follow the links
```

The data objects available are
[documented here](http://mygink.com/rest/v2docs/datatypes).

