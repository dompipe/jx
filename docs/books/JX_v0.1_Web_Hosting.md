# JX v0.1 Web Hosting

## Apache outside, JX inside

JX should not try to replace Apache. Apache is the public HTTP/TLS server; JX is the application host behind it.

The preferred server shape is:

```text
Internet
   |
 HTTPS
   v
Apache
   |
 private loopback HTTP
   v
JX host on 127.0.0.1:8765
   |
   +-- Book
   +-- Page / Leaf
   +-- Bags
   +-- Controls
   +-- Collectors
   +-- Layout / Style
   `-- PASL / PASM
```

### Persistent JX host

The XI/JX server already has a long-running CLI entry point. Run it on loopback:

```bash
cd /path/to/jx/pasl/xi
php xi.php 127.0.0.1:8765 start config.json --foreground
```

When Apache owns HTTPS, JX normally keeps its own SSL disabled. The JX port is private and never needs to be exposed directly to the Internet.

### Apache reverse proxy

A minimal Apache virtual host can delegate all application traffic to JX:

```apache
<VirtualHost *:80>
    ServerName jx.example.com

    ProxyRequests Off
    ProxyPreserveHost On

    ProxyPass        / http://127.0.0.1:8765/
    ProxyPassReverse / http://127.0.0.1:8765/
</VirtualHost>
```

For HTTPS, Apache terminates TLS and forwards private HTTP to JX:

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

The browser sees Apache. Apache sees JX. JX sees the Book.

### Let Apache serve static assets

Images, fonts, and other unchanged files can bypass JX:

```apache
Alias /assets/ /var/www/jx-assets/

<Directory /var/www/jx-assets/>
    Require all granted
</Directory>

ProxyPass /assets/ !
ProxyPass        / http://127.0.0.1:8765/
ProxyPassReverse / http://127.0.0.1:8765/
```

That gives a useful division:

```text
/assets/*    -> Apache
Book/Page    -> JX
host drops   -> JX
PASL events  -> JX
```

### Keep JX alive as a service

On a VPS or dedicated Linux server, JX should run under a process supervisor such as systemd:

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

The machine then has two long-running layers:

```text
Apache :80 / :443
JX     127.0.0.1:8765
```

### Shared hosting and PHP-FPM

Some shared-hosting or cPanel installations cannot keep a custom daemon alive or cannot configure Apache reverse proxying. JX therefore also needs an adapter mode:

```text
Browser
   |
Apache
   |
PHP-FPM
   |
JX bootstrap / browser adapter
   |
Book
```

The request adapter should use one canonical bootstrap:

```php
<?php
require_once '/path/to/jx/bootstrap.php';

jx\Host::browser()
    ->open('calculator')
    ->serve();
```

The current XI `xi.php` entry point is intentionally CLI-only. The PHP-FPM route should be a separate adapter over the same Book, Page, Bag, Control, Style, and host-protocol contracts.

### Includes belong at the boundary

The Page itself should not repeatedly include JX internals. In adapter mode:

```text
Apache / PHP-FPM
        |
   one JX bootstrap
        |
      Book
        |
 Pages / libraries / PASL
```

In persistent-host mode, Apache includes nothing from JX at all; it simply delegates HTTP to the running host.

### One Book, either mode

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
```

Deployment changes. Application meaning does not.

> **Apache guards and routes the doorway. JX owns the room behind it.**

## Next: SQL and NoSQL

The next JX v0.1 persistence chapter should connect external databases without making the database replace the language memory model:

```text
Book / Bags
    |
Storage Contract
  /          \
SQL          NoSQL
```

Bags remain the JX-owned mutable-memory boundary. SQL and NoSQL become persistent storage adapters, query sources, and synchronization targets behind that boundary.
