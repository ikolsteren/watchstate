<?php

declare(strict_types=1);

use App\Libs\Config;
use App\Libs\Container;
use App\Libs\Entity\StateInterface;
use App\Libs\Extends\Date;
use App\Libs\HttpException;
use App\Libs\Servers\ServerInterface;
use App\Libs\Storage\StorageInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        if (false === ($value = $_ENV[$key] ?? getenv($key))) {
            return getValue($default);
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('getValue')) {
    function getValue(mixed $var): mixed
    {
        return ($var instanceof Closure) ? $var() : $var;
    }
}

if (!function_exists('makeDate')) {
    /**
     * Make Date Time Object.
     *
     * @param string|int $date Defaults to now
     * @param string|DateTimeZone|null $tz For given $date, not for display.
     *
     * @return Date
     */
    function makeDate(string|int $date = 'now', DateTimeZone|string|null $tz = null): Date
    {
        if (ctype_digit((string)$date)) {
            $date = '@' . $date;
        }

        if (null === $tz) {
            $tz = date_default_timezone_get();
        }

        if (!($tz instanceof DateTimeZone)) {
            $tz = new DateTimeZone($tz);
        }

        return (new Date($date))->setTimezone($tz);
    }
}

if (!function_exists('ag')) {
    function ag(array $array, string|null $path, mixed $default = null, string $separator = '.'): mixed
    {
        if (null === $path) {
            return $array;
        }

        if (array_key_exists($path, $array)) {
            return $array[$path];
        }

        if (!str_contains($path, $separator)) {
            return $array[$path] ?? getValue($default);
        }

        foreach (explode($separator, $path) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return getValue($default);
            }
        }

        return $array;
    }
}

if (!function_exists('ag_set')) {
    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param array $array
     * @param string $path
     * @param mixed $value
     * @param string $separator
     *
     * @return array return modified array.
     */
    function ag_set(array $array, string $path, mixed $value, string $separator = '.'): array
    {
        $keys = explode($separator, $path);

        $at = &$array;

        while (count($keys) > 0) {
            if (1 === count($keys)) {
                if (is_array($at)) {
                    $at[array_shift($keys)] = $value;
                } else {
                    throw new RuntimeException("Can not set value at this path ($path) because its not array.");
                }
            } else {
                $path = array_shift($keys);
                if (!isset($at[$path])) {
                    $at[$path] = [];
                }
                $at = &$at[$path];
            }
        }

        return $array;
    }
}

if (!function_exists('ag_exists')) {
    /**
     * Determine if the given key exists in the provided array.
     *
     * @param array $array
     * @param string|int $path
     * @param string $separator
     *
     * @return bool
     */
    function ag_exists(array $array, string|int $path, string $separator = '.'): bool
    {
        if (is_int($path)) {
            return isset($array[$path]);
        }

        foreach (explode($separator, $path) as $lookup) {
            if (isset($array[$lookup])) {
                $array = $array[$lookup];
            } else {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('ag_delete')) {
    /**
     * Delete given key path.
     *
     * @param array $array
     * @param int|string $path
     * @param string $separator
     * @return array
     */
    function ag_delete(array $array, string|int $path, string $separator = '.'): array
    {
        if (array_key_exists($path, $array)) {
            unset($array[$path]);

            return $array;
        }

        if (is_int($path)) {
            if (isset($array[$path])) {
                unset($array[$path]);
            }
            return $array;
        }

        $items = &$array;

        $segments = explode($separator, $path);

        $lastSegment = array_pop($segments);

        foreach ($segments as $segment) {
            if (!isset($items[$segment]) || !is_array($items[$segment])) {
                continue;
            }

            $items = &$items[$segment];
        }

        if (null !== $lastSegment && array_key_exists($lastSegment, $items)) {
            unset($items[$lastSegment]);
        }

        return $array;
    }
}

if (!function_exists('fixPath')) {
    function fixPath(string $path): string
    {
        return rtrim(implode(DIRECTORY_SEPARATOR, explode(DIRECTORY_SEPARATOR, $path)), DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('fsize')) {
    function fsize(string|int $bytes = 0, bool $showUnit = true, int $decimals = 2, int $mod = 1000): string
    {
        $sz = 'BKMGTP';

        $factor = floor((strlen((string)$bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", (int)($bytes) / ($mod ** $factor)) . ($showUnit ? $sz[(int)$factor] : '');
    }
}

if (!function_exists('saveWebhookPayload')) {
    function saveWebhookPayload(ServerRequestInterface $request, string $name, array $parsed = [])
    {
        $content = [
            'query' => $request->getQueryParams(),
            'parsed' => $request->getParsedBody(),
            'server' => $request->getServerParams(),
            'body' => (string)$request->getBody(),
            'attributes' => $request->getAttributes(),
            'cParsed' => $parsed,
        ];

        @file_put_contents(
            Config::get('tmpDir') . '/webhooks/' . sprintf('webhook.%s.%d.json', $name, time()),
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(int $status, array $body, $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = 'application/json';

        return new Response(
            status:  $status,
            headers: $headers,
            body:    json_encode(
                         $body,
                         JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
                     )
        );
    }
}
if (!function_exists('httpClientChunks')) {
    /**
     * Handle Response Stream as Chunks
     *
     * @param ResponseStreamInterface $responseStream
     * @return Generator
     *
     * @throws TransportExceptionInterface
     */
    function httpClientChunks(ResponseStreamInterface $responseStream): Generator
    {
        foreach ($responseStream as $chunk) {
            yield $chunk->getContent();
        }
    }
}

if (!function_exists('preServeHttpRequest')) {
    function preServeHttpRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        foreach (Config::get('supported', []) as $server) {
            assert($server instanceof ServerInterface);
            $request = $server::processRequest($request);
        }

        return $request;
    }
}

if (!function_exists('serveHttpRequest')) {
    function serveHttpRequest(ServerRequestInterface $request): ResponseInterface
    {
        $logger = Container::get(LoggerInterface::class);

        try {
            $request = preServeHttpRequest($request);

            // -- get apikey from header or query.
            $apikey = $request->getHeaderLine('x-apikey');
            if (empty($apikey)) {
                $apikey = ag($request->getQueryParams(), 'apikey', '');
                if (empty($apikey)) {
                    throw new HttpException('No API key was given.', 400);
                }
            }

            $server = [];
            Config::get('servers', []);

            // -- Find Server
            foreach (Config::get('servers', []) as $name => $info) {
                if (null === ag($info, 'webhook.token')) {
                    continue;
                }

                if (!hash_equals(ag($info, 'webhook.token'), $apikey)) {
                    continue;
                }

                $userId = ag($info, 'user_id', null);
                $matchUser = true === ag($info, 'webhook.match.user') && null !== $userId;
                if (true === $matchUser && $userId !== $request->getAttribute('USER_ID', null)) {
                    continue;
                }

                $uuid = ag($info, 'uuid', null);
                $matchUUID = true === ag($info, 'webhook.match.uuid') && null !== $uuid;
                if (true === $matchUUID && $uuid !== $request->getAttribute('SERVER_ID', null)) {
                    continue;
                }

                $server = array_replace_recursive(['name' => $name], $info);
                break;
            }

            if (empty($server)) {
                throw new HttpException('Invalid API key was given.', 401);
            }

            if (true !== ag($server, 'webhook.import')) {
                throw new HttpException(
                    sprintf(
                        'Import via webhook for this server \'%s\' is disabled.',
                        ag($server, 'name')
                    ),
                    500
                );
            }

            try {
                $server['class'] = makeServer($server, $server['name']);
            } catch (RuntimeException $e) {
                throw new HttpException($e->getMessage(), 500);
            }

            $entity = $server['class']->parseWebhook($request);

            if (!$entity->hasGuids()) {
                return new Response(status: 204, headers: ['X-Status' => 'No GUIDs.']);
            }

            $storage = Container::get(StorageInterface::class);

            if (null === ($backend = $storage->get($entity))) {
                $entity = $storage->insert($entity);
                queuePush($entity);
                return jsonResponse(status: 200, body: $entity->getAll());
            }

            if (true === $entity->isTainted()) {
                if ($backend->apply($entity, guidOnly: true)->isChanged()) {
                    if (!empty($entity->meta)) {
                        $backend->meta = $entity->meta;
                    }
                    $backend = $storage->update($backend);
                    return jsonResponse(status: 200, body: $backend->getAll());
                }

                return new Response(
                    status:  200,
                    headers: ['X-Status' => 'Nothing updated, entity state is tainted.']
                );
            }

            if ($backend->updated > $entity->updated) {
                if ($backend->apply($entity, guidOnly: true)->isChanged()) {
                    if (!empty($entity->meta)) {
                        $backend->meta = $entity->meta;
                    }
                    $backend = $storage->update($backend);
                    return jsonResponse(status: 200, body: $backend->getAll());
                }

                return new Response(
                    status:  200,
                    headers: ['X-Status' => 'Entity date is older than what available in storage.']
                );
            }

            if ($backend->apply($entity)->isChanged()) {
                $backend = $storage->update($backend);

                queuePush($backend);
                return jsonResponse(status: 200, body: $backend->getAll());
            }

            return new Response(status: 200, headers: ['X-Status' => 'Entity is unchanged.']);
        } catch (HttpException $e) {
            if (200 === $e->getCode()) {
                return new Response(status: $e->getCode(), headers: ['X-Status' => $e->getMessage()]);
            }

            $logger->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            return jsonResponse(status: $e->getCode(), body: ['error' => true, 'message' => $e->getMessage()]);
        }
    }
}

if (!function_exists('queuePush')) {
    function queuePush(StateInterface $entity): void
    {
        if (!$entity->hasGuids()) {
            return;
        }

        try {
            $cache = Container::get(CacheInterface::class);

            $list = $cache->get('queue', []);

            $list[$entity->id] = $entity->getAll();

            $cache->set('queue', $list);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            Container::get(LoggerInterface::class)->error($e->getMessage(), $e->getTrace());
        }
    }
}

if (!function_exists('afterLast')) {
    function afterLast(string $subject, string $search): string
    {
        if (empty($search)) {
            return $subject;
        }

        $position = mb_strrpos($subject, $search, 0);

        if (false === $position) {
            return $subject;
        }

        return mb_substr($subject, $position + mb_strlen($search));
    }
}

if (!function_exists('makeServer')) {
    /**
     * @param array{name:string|null, type:string, url:string, token:string|int|null, user:string|int|null, persist:array, options:array} $server
     * @param string|null $name server name.
     * @return ServerInterface
     *
     * @throws RuntimeException if configuration is wrong.
     */
    function makeServer(array $server, string|null $name = null): ServerInterface
    {
        if (null === ($serverType = ag($server, 'type'))) {
            throw new RuntimeException('No server type was selected.');
        }

        if (null === ag($server, 'url')) {
            throw new RuntimeException('No url was set for server.');
        }

        if (null === ($class = Config::get("supported.{$serverType}", null))) {
            throw new RuntimeException(
                sprintf(
                    'Unexpected server type was given. Was expecting [%s] but got \'%s\' instead.',
                    $serverType,
                    implode('|', Config::get("supported", []))
                )
            );
        }

        return Container::getNew($class)->setUp(
            name:    $name ?? ag($server, 'name', fn() => md5(ag($server, 'url'))),
            url:     new Uri(ag($server, 'url')),
            token:   ag($server, 'token', null),
            userId:  ag($server, 'user', null),
            uuid:    ag($server, 'uuid', null),
            persist: ag($server, 'persist', []),
            options: ag($server, 'options', []),
        );
    }
}

if (!function_exists('arrayToString')) {
    function arrayToString(array $arr, string $separator = ', '): string
    {
        $list = [];

        foreach ($arr as $key => $val) {
            if (is_object($val)) {
                if (($val instanceof JsonSerializable)) {
                    $val = json_encode($val, flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } elseif (($val instanceof Stringable) || method_exists($val, '__toString')) {
                    $val = (string)$val;
                } else {
                    $val = [
                        spl_object_hash($val) => get_class($val),
                        ...(array)$val
                    ];
                }
            }

            if (is_array($val)) {
                $val = json_encode($val, flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $val = $val ?? 'None';
            }

            $list[] = sprintf("(%s: %s)", $key, $val);
        }

        return implode($separator, $list);
    }
}

if (!function_exists('commandContext')) {
    function commandContext(): string
    {
        if (env('IN_DOCKER')) {
            return sprintf('docker exec -ti %s console ', env('CONTAINER_NAME', 'watchstate'));
        }

        return ($_SERVER['argv'][0] ?? 'php console') . ' ';
    }
}
