<?php

declare(strict_types=1);

namespace Omniship\Aras\Tests;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

function createMockHttpClient(string $responseBody = '{}', int $statusCode = 200): ClientInterface
{
    return new class ($responseBody, $statusCode) implements ClientInterface {
        public function __construct(
            private readonly string $responseBody,
            private readonly int $statusCode,
        ) {}

        public function sendRequest(RequestInterface $request): PsrResponseInterface
        {
            return new class ($this->responseBody, $this->statusCode) implements PsrResponseInterface {
                public function __construct(
                    private readonly string $body,
                    private readonly int $statusCode,
                ) {}

                public function getStatusCode(): int
                {
                    return $this->statusCode;
                }

                public function getReasonPhrase(): string
                {
                    return 'OK';
                }

                public function getProtocolVersion(): string
                {
                    return '1.1';
                }

                public function withProtocolVersion(string $version): static
                {
                    return $this;
                }

                public function getHeaders(): array
                {
                    return [];
                }

                public function hasHeader(string $name): bool
                {
                    return false;
                }

                public function getHeader(string $name): array
                {
                    return [];
                }

                public function getHeaderLine(string $name): string
                {
                    return '';
                }

                public function withHeader(string $name, $value): static
                {
                    return $this;
                }

                public function withAddedHeader(string $name, $value): static
                {
                    return $this;
                }

                public function withoutHeader(string $name): static
                {
                    return $this;
                }

                public function getBody(): StreamInterface
                {
                    return new class ($this->body) implements StreamInterface {
                        public function __construct(private readonly string $content) {}

                        public function __toString(): string
                        {
                            return $this->content;
                        }

                        public function close(): void {}
                        public function detach() { return null; }
                        public function getSize(): ?int { return strlen($this->content); }
                        public function tell(): int { return 0; }
                        public function eof(): bool { return true; }
                        public function isSeekable(): bool { return false; }
                        public function seek(int $offset, int $whence = SEEK_SET): void {}
                        public function rewind(): void {}
                        public function isWritable(): bool { return false; }
                        public function write(string $string): int { return 0; }
                        public function isReadable(): bool { return true; }
                        public function read(int $length): string { return $this->content; }
                        public function getContents(): string { return $this->content; }
                        public function getMetadata(?string $key = null) { return null; }
                    };
                }

                public function withBody(StreamInterface $body): static
                {
                    return $this;
                }

                public function withStatus(int $code, string $reasonPhrase = ''): static
                {
                    return $this;
                }
            };
        }
    };
}

function createMockRequestFactory(): RequestFactoryInterface
{
    return new class implements RequestFactoryInterface {
        public function createRequest(string $method, $uri): RequestInterface
        {
            return new class ($method, (string) $uri) implements RequestInterface {
                /** @var array<string, list<string>> */
                private array $headers = [];
                private ?StreamInterface $body = null;

                public function __construct(
                    private readonly string $method,
                    private readonly string $uri,
                ) {}

                public function getRequestTarget(): string { return $this->uri; }
                public function withRequestTarget(string $requestTarget): static { return $this; }
                public function getMethod(): string { return $this->method; }
                public function withMethod(string $method): static { return $this; }

                public function getUri(): UriInterface
                {
                    return new class ($this->uri) implements UriInterface {
                        public function __construct(private readonly string $uri) {}
                        public function getScheme(): string { return ''; }
                        public function getAuthority(): string { return ''; }
                        public function getUserInfo(): string { return ''; }
                        public function getHost(): string { return ''; }
                        public function getPort(): ?int { return null; }
                        public function getPath(): string { return $this->uri; }
                        public function getQuery(): string { return ''; }
                        public function getFragment(): string { return ''; }
                        public function withScheme(string $scheme): static { return $this; }
                        public function withUserInfo(string $user, ?string $password = null): static { return $this; }
                        public function withHost(string $host): static { return $this; }
                        public function withPort(?int $port): static { return $this; }
                        public function withPath(string $path): static { return $this; }
                        public function withQuery(string $query): static { return $this; }
                        public function withFragment(string $fragment): static { return $this; }
                        public function __toString(): string { return $this->uri; }
                    };
                }

                public function withUri(UriInterface $uri, bool $preserveHost = false): static { return $this; }
                public function getProtocolVersion(): string { return '1.1'; }
                public function withProtocolVersion(string $version): static { return $this; }
                public function getHeaders(): array { return $this->headers; }
                public function hasHeader(string $name): bool { return isset($this->headers[$name]); }
                public function getHeader(string $name): array { return $this->headers[$name] ?? []; }
                public function getHeaderLine(string $name): string { return implode(', ', $this->getHeader($name)); }

                public function withHeader(string $name, $value): static
                {
                    $clone = clone $this;
                    $clone->headers[$name] = is_array($value) ? $value : [$value];
                    return $clone;
                }

                public function withAddedHeader(string $name, $value): static { return $this; }
                public function withoutHeader(string $name): static { return $this; }

                public function getBody(): StreamInterface
                {
                    return $this->body ?? new class implements StreamInterface {
                        public function __toString(): string { return ''; }
                        public function close(): void {}
                        public function detach() { return null; }
                        public function getSize(): ?int { return 0; }
                        public function tell(): int { return 0; }
                        public function eof(): bool { return true; }
                        public function isSeekable(): bool { return false; }
                        public function seek(int $offset, int $whence = SEEK_SET): void {}
                        public function rewind(): void {}
                        public function isWritable(): bool { return false; }
                        public function write(string $string): int { return 0; }
                        public function isReadable(): bool { return true; }
                        public function read(int $length): string { return ''; }
                        public function getContents(): string { return ''; }
                        public function getMetadata(?string $key = null) { return null; }
                    };
                }

                public function withBody(StreamInterface $body): static
                {
                    $clone = clone $this;
                    $clone->body = $body;
                    return $clone;
                }
            };
        }
    };
}

function createMockStreamFactory(): StreamFactoryInterface
{
    return new class implements StreamFactoryInterface {
        public function createStream(string $content = ''): StreamInterface
        {
            return new class ($content) implements StreamInterface {
                public function __construct(private readonly string $content) {}
                public function __toString(): string { return $this->content; }
                public function close(): void {}
                public function detach() { return null; }
                public function getSize(): ?int { return strlen($this->content); }
                public function tell(): int { return 0; }
                public function eof(): bool { return true; }
                public function isSeekable(): bool { return false; }
                public function seek(int $offset, int $whence = SEEK_SET): void {}
                public function rewind(): void {}
                public function isWritable(): bool { return false; }
                public function write(string $string): int { return 0; }
                public function isReadable(): bool { return true; }
                public function read(int $length): string { return $this->content; }
                public function getContents(): string { return $this->content; }
                public function getMetadata(?string $key = null) { return null; }
            };
        }

        public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
        {
            return $this->createStream('');
        }

        public function createStreamFromResource($resource): StreamInterface
        {
            return $this->createStream('');
        }
    };
}
