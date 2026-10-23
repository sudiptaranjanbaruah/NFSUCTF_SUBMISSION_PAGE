<?php
namespace App\Core;

/**
 * Minimal router: METHOD + pattern with {params}, optional middleware names.
 */
final class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function middleware(string $name, callable $fn): void
    {
        $this->middleware[$name] = $fn;
    }

    public function get(string $pattern, callable|array $handler, array $mw = []): void
    {
        $this->add('GET', $pattern, $handler, $mw);
    }

    public function post(string $pattern, callable|array $handler, array $mw = []): void
    {
        $this->add('POST', $pattern, $handler, $mw);
    }

    public function add(string $method, string $pattern, callable|array $handler, array $mw = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($pattern, '/') ?: '/') . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'mw');
    }

    public function dispatch(Request $req): void
    {
        $path = rtrim($req->path(), '/') ?: '/';
        $allowed = false;

        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            $allowed = true;
            if ($r['method'] !== $req->method()) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($r['mw'] as $name) {
                $result = ($this->middleware[$name])($req);
                if ($result === false) {
                    return;
                }
            }

            $handler = $r['handler'];
            if (is_array($handler)) {
                [$class, $fn] = $handler;
                (new $class())->$fn($req, ...array_values($params));
            } else {
                $handler($req, ...array_values($params));
            }
            return;
        }

        if ($allowed) {
            abort(405, 'Method not allowed');
        }
        abort(404);
    }
}
