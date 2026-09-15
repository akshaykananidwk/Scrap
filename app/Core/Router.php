<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pattern router. Routes are declared in /routes/web.php and /routes/api.php.
 * Patterns support {param} and {param:\d+} style constraints.
 */
final class Router
{
    private array $routes = [];
    private array $named = [];
    private array $groupStack = [];

    public function get(string $uri, array|callable $action): self
    {
        return $this->add('GET', $uri, $action);
    }

    public function post(string $uri, array|callable $action): self
    {
        return $this->add('POST', $uri, $action);
    }

    public function put(string $uri, array|callable $action): self
    {
        return $this->add('PUT', $uri, $action);
    }

    public function patch(string $uri, array|callable $action): self
    {
        return $this->add('PATCH', $uri, $action);
    }

    public function delete(string $uri, array|callable $action): self
    {
        return $this->add('DELETE', $uri, $action);
    }

    public function any(array $methods, string $uri, array|callable $action): self
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $uri, $action);
        }
        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $uri, array|callable $action): self
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= isset($group['prefix']) ? '/' . trim($group['prefix'], '/') : '';
            $middleware = array_merge($middleware, (array) ($group['middleware'] ?? []));
        }
        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        if ($uri === '/') {
            $uri = '/';
        }
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middleware,
            'name' => null,
        ];
        return $this;
    }

    public function name(string $name): self
    {
        $index = array_key_last($this->routes);
        if ($index !== null) {
            $this->routes[$index]['name'] = $name;
            $this->named[$name] = $this->routes[$index]['uri'];
        }
        return $this;
    }

    public function middleware(string|array $middleware): self
    {
        $index = array_key_last($this->routes);
        if ($index !== null) {
            $this->routes[$index]['middleware'] = array_merge(
                $this->routes[$index]['middleware'],
                (array) $middleware
            );
        }
        return $this;
    }

    public function route(string $name, array $params = []): string
    {
        $uri = $this->named[$name] ?? '/';
        foreach ($params as $key => $value) {
            $uri = preg_replace('#\{' . preg_quote((string) $key, '#') . '(:[^}]+)?\}#', (string) $value, $uri) ?? $uri;
        }
        return base_url(ltrim($uri, '/'));
    }

    public function match(string $method, string $path): ?array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $pattern = $this->compile($route['uri']);
            if (preg_match($pattern, $path, $matches)) {
                if ($route['method'] !== $method) {
                    $allowed[] = $route['method'];
                    continue;
                }
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }
                return ['route' => $route, 'params' => $params];
            }
        }
        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed for this URL.');
        }
        return null;
    }

    private function compile(string $uri): string
    {
        $pattern = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#', static function (array $m): string {
            $constraint = $m[2] ?? '[^/]+';
            return '(?P<' . $m[1] . '>' . $constraint . ')';
        }, $uri);
        return '#^' . $pattern . '$#u';
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
