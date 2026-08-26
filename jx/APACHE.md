# JX v0.1 Apache Hosting

JX should support Apache without making Apache understand Books, Pages, Bags, Controls, or PASL. Apache owns the public HTTP edge. JX owns the application runtime behind it.

The preferred deployment is:

```text
Internet
   |
 HTTPS :443
   v
Apache
   |
 HTTP over loopback
   v
127.0.0.1:8765
   |
JX / XI host
   |- Book
   |- Page / Leaf
   |- Bags
   |- Controls
   |- Collectors
   |- Layout / Style
   `- PASL / PASM
```

## 1. Preferred mode: persistent JX behind Apache

The XI entry point is already a long-running CLI server. It loads the XI/JX runtime, creates `XipEngine` and `Server`, and then calls `serve()`.

Start it on loopback only:

```bash
cd /path/to/jx/pasl/xi
php xi.php 127.0.0.1:8765 start config.json --foreground
```

The important boundary is that JX listens on `127.0.0.1`, not on a public interface. Apache is the public server.

The JX config should normally keep its own TLS off when Apache terminates HTTPS:

```json
{
  "books_root": "books",
  "data_root": "data",
  "default_book": "cover",
  "ssl": false,
  "dry": true,
  "allow_input": true,
  "allow_output": true
}
```

## 2. Apache reverse proxy

A minimal HTTP virtual host is:

```apache
<VirtualHost *:80>
    ServerName jx.example.com

    ProxyRequests Off
    ProxyPreserveHost On

    ProxyPass        / http://127.0.0.1:8765/
    ProxyPassReverse / http://127.0.0.1:8765/
</VirtualHost>
```

The browser sees only Apache. The internal JX port remains private.

Conceptually:

```text
https://jx.example.com/book/home
              |
              v
           Apache
              |
              v
     http://127.0.0.1:8765/book/home
              |
              v
             JX
```

## 3. HTTPS boundary

For HTTPS, Apache owns certificates and encryption. JX stays on private HTTP behind it.

```apache
<VirtualHost *:443>
    ServerName jx.example.com

    SSLEngine on
    SSLCertificateFile /path/to/fullchain.pem
    SSLCertificateKeyFile /path/to/privkey.pem

    ProxyRequests Off
    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"

    ProxyPass        / http://127.0.0.1:8765/
    ProxyPassReverse / http://127.0.0.1:8765/
</VirtualHost>
```

This keeps certificate rotation and public TLS concerns outside the JX runtime.

## 4. Static assets can bypass JX

Apache is already optimized for static files. JX does not need to spend runtime work serving unchanged images, fonts, or other assets.

```apache
Alias /assets/ /var/www/jx-assets/

<Directory /var/www/jx-assets/>
    Require all granted
</Directory>

ProxyPass /assets/ !
ProxyPass        / http://127.0.0.1:8765/
ProxyPassReverse / http://127.0.0.1:8765/
```

The split becomes:

```text
/assets/logo.png -> Apache directly
/book/home       -> JX host
/drop            -> JX host
/page/event      -> JX host
```

This is delegation, not duplication.

## 5. Run JX as a service

On Linux, the persistent host should normally be supervised by the service manager rather than started manually after every reboot.

Example systemd unit:

```ini
[Unit]
Description=JX Book Host
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/jx/pasl/xi
ExecStart=/usr/bin/php /opt/jx/pasl/xi/xi.php 127.0.0.1:8765 start config.json --foreground
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
```

The machine then has two clear long-lived roles:

```text
Linux
|- Apache :80 / :443
`- JX     127.0.0.1:8765
```

## 6. Alternate mode: Apache + PHP-FPM adapter

Some shared-hosting and cPanel environments cannot run a long-lived JX process or cannot configure reverse proxying. JX therefore needs a request/response adapter mode too.

That mode is:

```text
Browser
   |
Apache
   |
PHP-FPM
   |
JX bootstrap / adapter
   |
Book / Page / Bags / PASL
```

The adapter should load JX once per PHP request through one canonical bootstrap, not require every Page to include many internal files.

Conceptually:

```php
<?php
require_once '/path/to/jx/bootstrap.php';

jx\Host::browser()
    ->open('calculator')
    ->serve();
```

The current `pasl/xi/xi.php` remains CLI-only and should not itself be used as the PHP-FPM request script. The PHP-FPM route is a separate adapter over the same Book and host contracts.

## 7. One application, two deployment modes

The application should not care which Apache mode is used:

```text
                 same JX Book
                      |
           +----------+-----------+
           |                      |
 persistent JX host       PHP-FPM adapter
           |                      |
           +----------+-----------+
                      |
                   Apache
                      |
                   Browser
```

The Book, Page contract, Bags, Controls, Collectors, Style, and PASL/PASM semantics stay the same.

## 8. Deployment principle

Apache should do what Apache is already good at:

```text
public HTTP edge
TLS
virtual hosts
static assets
compression / caching
reverse proxying
```

JX should do what belongs to JX:

```text
Book ownership
Page / Leaf state
Bags
Controls / Collectors
Style / Layout
host protocol
PASL / PASM execution
application events
```

> **Apache guards and routes the doorway. JX owns the room behind it.**

## 9. Next persistence layer

Once the hosting boundary is stable, the next JX v0.1 layer is persistent data interoperability:

```text
JX Bags / Books
      |
 storage adapters
   /          \
 SQL          NoSQL
```

SQL and NoSQL should be adapters behind the JX memory/application model rather than replacing Bags as the language-level mutable-memory concept.
