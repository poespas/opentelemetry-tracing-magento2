<?php

namespace Poespas\OpentelemetryTracing\Profiler\Driver;

use Magento\Framework\Profiler\DriverInterface;
use Jaeger\Config;
use Jaeger\Tracer;
use OpenTracing\GlobalTracer;
use const Jaeger\SAMPLER_TYPE_CONST;

class TracingDriver implements DriverInterface
{
    const DEFAULT_APPLICATION_NAME = 'magento2';

    public Tracer $tracer;

    public array $scopes = [];

    public function __construct(array $config = null)
    {
        $appName = $config['application_name'] ?? self::DEFAULT_APPLICATION_NAME;

        $config = new Config(
            [
                'sampler' => [
                    'type' => SAMPLER_TYPE_CONST,
                    'param' => true,
                ],

                ...$config['config'],
                'tags' => [
                    'http.method' => $_SERVER['REQUEST_METHOD'],
                    'http.host' => $_SERVER['HTTP_HOST'],
                    'http.path' => $_SERVER['REQUEST_URI'],
                    'http.user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    ...($config['config']['tags'] ?? []),
                ]
            ],
            $appName
        );

        $config->initializeTracer();

        $this->tracer = GlobalTracer::get();

        register_shutdown_function([$this, 'finalize']);
    }

    public function start($timerId, array $attributes = null)
    {
        if (!is_array($attributes)) {
            $attributes = [];
        }

        $this->scopes[$timerId] = $this->tracer->startActiveSpan($timerId);
        $this->scopes[$timerId]->getSpan()->log($attributes);
    }

    public function stop($timerId)
    {
        $this->scopes[$timerId]->close();
    }

    public function clear($timerId = null)
    {
        if ($timerId) {
            unset($this->scopes[$timerId]);
        } else {
            $this->scopes = [];
        }
    }

    public function finalize() {
        $this->tracer->flush();
    }
}
