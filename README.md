## Introdution

This is an ItIsAllMail driver for avito.ru site. You need curl-impersonated to be installed to use this driver.

## Installation:

1) Install ItIsAllMail.
2) Install curl-impersonated somewhere and add this line to `conf/config.yml`:

    curl_impersonated_bin : "/path/to/curl/impersonated/bin"
    
2) Install the driver.

    cd lib/ItIsAllMail/Driver/
    git clone https://github.com/yalexwander/https://github.com/yalexwander/iam-driver-avito.ru avito.ru

3) Add driver to ItIsAllMail `conf/config.yml`:

```
drivers :
  - "avito.ru"
```

3) Add source in `conf/sources.yml`:

```
- url: "http://avitor.ru/some/search/url"
  mailbox_base_dir: /tmp
  mailbox: mailbox_avito
```

You must obtain URL from the browser by executing some search. After you apply all needed filters just copy the URL.
