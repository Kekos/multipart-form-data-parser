# PHP parser for HTTP multipart/form-data bodies

Parses HTTP bodies encoded as `multipart/form-data`.

This package can be used as a polyfill for the PHP function
[`request_parse_body()`](https://wiki.php.net/rfc/rfc1867-non-post) available since PHP 8.4.

## Install

You can install this package via [Composer](http://getcomposer.org/):

```
composer kekos/multipart-form-data-parser
```

## Documentation

### Usage with existing PSR-7 request

```php
<?php
use Kekos\MultipartFormDataParser\Parser;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

/** @var ServerRequestInterface $request */
/** @var UploadedFileFactoryInterface $uploaded_file_factory */
/** @var StreamFactoryInterface $stream_factory */
$parser = Parser::createFromRequest($request, $uploaded_file_factory, $stream_factory);
$parser->decorateRequest($request);

$post_fields = $request->getParsedBody();
$files = $request->getUploadedFiles();
```

## Bugs and improvements

Report bugs in GitHub issues or feel free to make a pull request :-)

## License

MIT
