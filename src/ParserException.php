<?php declare(strict_types=1);

namespace Kekos\MultipartFormDataParser;

use Exception;

class ParserException extends Exception
{
    public const ERR_CONTENT_TYPE = 1;
    public const ERR_CONTENT_TYPE_BOUNDARY = 2;
    public const ERR_PARSE_HEADER_LINE_VALUE = 3;
    public const ERR_PARSE_HEADER_LINE_NAME = 4;
    public const ERR_PARSE_SPLIT_REGEX = 5;

    public static function wrongContentType(string $given): self
    {
        return new self(
            sprintf('Expected Content-Type "%s", "%s" given', Parser::CONTENT_TYPE_MULTIPART, $given),
            self::ERR_CONTENT_TYPE
        );
    }

    public static function missingBoundary(): self
    {
        return new self('Missing Content-Type boundary', self::ERR_CONTENT_TYPE_BOUNDARY);
    }

    public static function headerLineError(string $header_line): self
    {
        return new self(
            sprintf('HTTP header field value missing: "%s', $header_line),
            self::ERR_PARSE_HEADER_LINE_VALUE
        );
    }

    public static function headerLineNameError(string $header_line): self
    {
        return new self(
            sprintf('HTTP header field name must not end with whitespace: "%s', $header_line),
            self::ERR_PARSE_HEADER_LINE_NAME
        );
    }

    public static function splitRegex(string $pattern): self
    {
        return new self(
            sprintf('Split regex error for pattern "%s"', $pattern),
            self::ERR_PARSE_SPLIT_REGEX
        );
    }
}
