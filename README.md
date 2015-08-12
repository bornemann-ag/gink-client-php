# Gink Client (PHP)

This project is intended to help in accessing Bornemann AG's
[gink webservice](https://mygink.com/rest/v2docs/).

Essentially, all you really need is the script in **lib/GinkClient.php**. It is
nothing more than a thin wrapper around the PHP cURL functionality (you must
have this working in your PHP installation) which follows some conventions in
the webservice.

## Example: PHP Client

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


## Example: Infleet Portal embed in Intranet

Using the API in combination with the Infleet Portal, it is possible to retrieve
an access token, and us this to embed the portal inside an intranet without
revealing credentials to your users.

### How it works

Suppose you have a page on your Intranet and you want to embed an iframe with
the Infleet Portal (v1.3). The Infleet Portal supports an automated login
process using the [Gink Webservice](http://mygink.com/). The URL for this login
is: https://infleet.bornemann.net/v1.3/login.html

- token (required) : acquired from the [Gink Webservice](http://mygink.com/)
- redirect (optional): automatically redirect to page in portal

Here is an example, if your token is "sample":

https://infleet.bornemann.net/v1.3/login.html?redirect=live.html&token=sample

The function of this page is merely to set a Cookie ('token') in the browser
(for the infleet.bornemann.net domain), and then redirect the user to another
page in the Infleet Portal.

The Gink Webservice authentication is documented
[here](http://mygink.com/rest/v2docs/authentication). You can see this in action
in the provided PHP script iframe-embed.php. It uses a username and password
stored in an INI file to dynamically generate a token, then redirect to the
login page in the Infleet Portal (v1.3). Note that there are further comments in
that script and the included GinkClient.php script which may be helpful.

### Security

You should take steps to ensure that your credentials to the Infleet Portal are
NOT revealed by your webserver. A few options would be:

- Configure your web server to block access to the INI (or other type)
  containing your username and password.
- Change the iframe-embed.php script to obtain its credentials elsewhere
  (e.g. database)
- Hardcode your credentials in the PHP script (suboptimal solution)

### Other Considerations

The script here *always* generates a new token. In fact, tokens can be stored
and re-used until their expiration, which will speed up the loading of the
embedded frame because username/password authentication is no longer
needed. Please refer to the [Gink Webservice API](http://mygink.com/rest/v2docs)
for information about this.
