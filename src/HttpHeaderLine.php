<?php declare(strict_types=1);

namespace Kekos\MultipartFormDataParser;

class HttpHeaderLine
{
    /** @var string */
    private $name;
    /** @var string */
    private $value;
    /** @var array<string, string> */
    private $key_values = [];

    public function __construct(string $raw_header_line)
    {
        $header_line = explode(':', $raw_header_line, 2);

        if (count($header_line) !== 2) {
            throw ParserException::headerLineError($raw_header_line);
        }

        list($name, $value) = $header_line;

        if (trim($name) !== $name) {
            throw ParserException::headerLineNameError($raw_header_line);
        }

        $this->name = $name;

        $value = explode(';', $value);
        $value = array_map('trim', $value);

        $this->value = current($value);

        foreach ($value as &$raw_value_part) {
            $value_part = explode('=', $raw_value_part, 2);

            if (count($value_part) !== 2) {
                continue;
            }

            list($key, $key_value) = $value_part;

            $key = strtolower(trim($key));
            $this->key_values[$key] = trim(trim($key_value), '"\'');
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getKeyValue(string $key): ?string
    {
        if (!isset($this->key_values[$key])) {
            return null;
        }

        return $this->key_values[$key];
    }
}
