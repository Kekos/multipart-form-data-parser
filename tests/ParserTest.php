<?php declare(strict_types=1);

namespace Kekos\MultipartFormDataParser\Tests;

use Kekos\MultipartFormDataParser\Parser;
use Kekos\MultipartFormDataParser\ParserException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

class ParserTest extends TestCase
{
    private static UploadedFileFactoryInterface $uploaded_file_factory;
    /** @var StreamFactoryInterface */
    private static $stream_factory;

    public static function setUpBeforeClass(): void
    {
        self::$uploaded_file_factory = self::$stream_factory = new Psr17Factory();
    }

    public function testThrowsInvalidContentType(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected Content-Type');

        new Parser(
            '',
            'text/plain ; charset=UTF-8',
            self::$uploaded_file_factory,
            self::$stream_factory
        );
    }

    public function testThrowsMissingBoundary(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Missing Content-Type boundary');

        new Parser(
            '',
            'multipart/form-data ; ',
            self::$uploaded_file_factory,
            self::$stream_factory
        );
    }

    public function testReadsBoundary(): void
    {
        $expected = 'b----1234';
        $parser = new Parser(
            '',
            sprintf('multipart/form-data ; boundary="%s"', $expected),
            self::$uploaded_file_factory,
            self::$stream_factory
        );

        $this->assertEquals($expected, $parser->getBoundary());
    }

    public function testFormFields(): void
    {
        $boundary = 'b----1234';
        $content_type = sprintf('multipart/form-data;boundary=%s', $boundary);

        $expected = [
            'foo' => 'this is a test',
            'bar' => [
                "x\r\nA",
                'B',
            ],
        ];

        $body = <<<EOF
--$boundary\r
Content-Disposition: form-data; name="foo"\r
\r
{$expected['foo']}\r
--$boundary\r
Content-Disposition: form-data; name="bar[]"\r
\r
{$expected['bar'][0]}\r
--$boundary\r
Content-Disposition: form-data; name="bar[]"\r
\r
{$expected['bar'][1]}\r
--$boundary--\r
EOF;

        $parser = new Parser(
            $body,
            $content_type,
            self::$uploaded_file_factory,
            self::$stream_factory
        );

        $this->assertEquals($expected, $parser->getFormFields());
    }

    public function testFiles(): void
    {
        $boundary = 'b----1234';
        $content_type = sprintf('multipart/form-data;boundary=%s', $boundary);

        $expected = [
            'foo' => [
                'contents' => 'this is a test',
                'mime' => 'text/plain',
                'name' => 'text.txt',
            ],
            'bar' => [
                [
                    'contents' => "x\r\nA",
                    'mime' => 'text/plain',
                    'name' => 'xa.txt',
                ],
                [
                    'contents' => '<hr>',
                    'mime' => 'text/html',
                    'name' => 'b.html',
                ],
            ],
        ];

        $body = <<<EOF
--$boundary\r
Content-Disposition: form-data; name="foo"; filename="{$expected['foo']['name']}"\r
Content-Type: {$expected['foo']['mime']}\r
\r
{$expected['foo']['contents']}\r
--$boundary\r
Content-Disposition: form-data; name="bar[]"; filename="{$expected['bar'][0]['name']}"\r
Content-Type: {$expected['bar'][0]['mime']}\r
\r
{$expected['bar'][0]['contents']}\r
--$boundary\r
Content-Disposition: form-data; name="bar[]"; filename="{$expected['bar'][1]['name']}"\r
Content-Type: {$expected['bar'][1]['mime']}\r
\r
{$expected['bar'][1]['contents']}\r
--$boundary--\r
EOF;

        $parser = new Parser(
            $body,
            $content_type,
            self::$uploaded_file_factory,
            self::$stream_factory
        );

        /** @var UploadedFileInterface[] $files */
        $files = $parser->getFiles();

        $this->assertEquals($expected['foo']['contents'], (string) $files['foo']->getStream());
        $this->assertEquals($expected['foo']['mime'], $files['foo']->getClientMediaType());
        $this->assertEquals($expected['foo']['name'], $files['foo']->getClientFilename());

        /** @var UploadedFileInterface[][] $files */
        $files = $parser->getFiles();

        $this->assertEquals($expected['bar'][0]['contents'], (string) $files['bar'][0]->getStream());
        $this->assertEquals($expected['bar'][0]['mime'], $files['bar'][0]->getClientMediaType());
        $this->assertEquals($expected['bar'][0]['name'], $files['bar'][0]->getClientFilename());

        $this->assertEquals($expected['bar'][1]['contents'], (string) $files['bar'][1]->getStream());
        $this->assertEquals($expected['bar'][1]['mime'], $files['bar'][1]->getClientMediaType());
        $this->assertEquals($expected['bar'][1]['name'], $files['bar'][1]->getClientFilename());
    }

    public function testFileAndTextarea(): void
    {
        $boundary = 'b----1234';
        $content_type = sprintf('multipart/form-data; boundary=%s', $boundary);

        $body = <<<EOF
--$boundary\r
Content-Disposition: form-data; name="field_text_area"\r
\r
Test data\r
\r
in textarea\r
ÅÄÖ\r
--$boundary\r
Content-Disposition: form-data; name="field_file"; filename="my_file.txt"\r
Content-Type: text/plain\r
\r
Text file\r
\r
with CRLF\r
\r
--$boundary--\r
EOF;

        $parser = new Parser(
            $body,
            $content_type,
            self::$uploaded_file_factory,
            self::$stream_factory
        );

		/** @var array<string, string> $form_fields */
		$form_fields = $parser->getFormFields();

		$this->assertEquals("Test data\r\n\r\nin textarea\r\nÅÄÖ", $form_fields['field_text_area']);

        /** @var UploadedFileInterface[] $files */
        $files = $parser->getFiles();

        $this->assertEquals("Text file\r\n\r\nwith CRLF\r\n", (string) $files['field_file']->getStream());
        $this->assertEquals('text/plain', $files['field_file']->getClientMediaType());
        $this->assertEquals('my_file.txt', $files['field_file']->getClientFilename());
    }

    /**
     * @dataProvider providerRequest
     * @param string $boundary
     * @param string $body
     * @param array<string, string> $expected_body
     * @param array<string, array<string, mixed>> $expected_files
     */
    public function testDecorateRequest(string $boundary, string $body, array $expected_body, array $expected_files): void
    {
        $content_type = sprintf('multipart/form-data;boundary=%s', $boundary);

        $parser = new Parser(
            $body,
            $content_type,
            self::$uploaded_file_factory,
            self::$stream_factory
        );

        $request = new ServerRequest('PUT', '');
        $request = $parser->decorateRequest($request);

        $body = (array) $request->getParsedBody();
        $this->assertEquals($expected_body, $body);

        /** @var UploadedFileInterface[] $files */
        $files = $request->getUploadedFiles();

        $this->assertEquals($expected_files['foo']['contents'], (string) $files['foo']->getStream());
        $this->assertEquals($expected_files['foo']['mime'], $files['foo']->getClientMediaType());
        $this->assertEquals($expected_files['foo']['name'], $files['foo']->getClientFilename());
    }

    /**
     * @dataProvider providerRequest
     * @param string $boundary
     * @param string $body
     * @param array<string, string> $expected_body
     * @param array<string, array<string, mixed>> $expected_files
     */
    public function testCreateFromRequest(string $boundary, string $body, array $expected_body, array $expected_files): void
    {
        $content_type = sprintf('multipart/form-data;boundary=%s', $boundary);

        $request = new ServerRequest('PUT', '', [
            'Content-Type' => $content_type,
        ], $body);
        $parser = Parser::createFromRequest($request, self::$uploaded_file_factory, self::$stream_factory);

        $this->assertEquals($expected_body, $parser->getFormFields());

        /** @var UploadedFileInterface[] $files */
        $files = $parser->getFiles();

        $this->assertEquals($expected_files['foo']['contents'], (string) $files['foo']->getStream());
        $this->assertEquals($expected_files['foo']['mime'], $files['foo']->getClientMediaType());
        $this->assertEquals($expected_files['foo']['name'], $files['foo']->getClientFilename());
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function providerRequest(): array
    {
        $boundary = 'b----1234';

        $expected_body = [
            'bar' => 'baz',
        ];
        $expected_files = [
            'foo' => [
                'contents' => 'this is a test',
                'mime' => 'text/plain',
                'name' => 'text.txt',
            ],
        ];

        $body = <<<EOF
--$boundary\r
Content-Disposition: form-data; name="bar"\r
\r
{$expected_body['bar']}\r
--$boundary\r
Content-Disposition: form-data; name="foo"; filename="{$expected_files['foo']['name']}"\r
Content-Type: {$expected_files['foo']['mime']}\r
\r
{$expected_files['foo']['contents']}\r
--$boundary--\r
EOF;

        return [
            [
                $boundary,
                $body,
                $expected_body,
                $expected_files
            ]
        ];
    }
}
