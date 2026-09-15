<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private array $headers = [];

    private function __construct(
        private string $content = '',
        private int $status = 200,
        private ?string $filePath = null,
        private ?string $downloadName = null
    ) {
    }

    public static function make(string $content, int $status = 200): self
    {
        return new self($content, $status);
    }

    public static function html(string $content, int $status = 200): self
    {
        $response = new self($content, $status);
        $response->headers['Content-Type'] = 'text/html; charset=UTF-8';
        return $response;
    }

    public static function json(array $data, int $status = 200): self
    {
        $response = new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status
        );
        $response->headers['Content-Type'] = 'application/json; charset=UTF-8';
        return $response;
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $url = str_starts_with($to, 'http://') || str_starts_with($to, 'https://') ? $to : base_url($to);
        $response = new self('', $status);
        $response->headers['Location'] = $url;
        return $response;
    }

    public static function download(string $path, ?string $name = null, string $contentType = 'application/octet-stream'): self
    {
        $response = new self('', 200, $path, $name ?? basename($path));
        $response->headers['Content-Type'] = $contentType;
        return $response;
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if ($this->filePath !== null) {
            if (!is_file($this->filePath)) {
                http_response_code(404);
                echo 'File not found.';
                return;
            }
            if (!headers_sent()) {
                header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $this->downloadName) . '"');
                header('Content-Length: ' . (string) (filesize($this->filePath) ?: 0));
                header('X-Content-Type-Options: nosniff');
            }
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            readfile($this->filePath);
            return;
        }

        echo $this->content;
    }
}
