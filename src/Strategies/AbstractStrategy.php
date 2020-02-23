<?php

namespace PhpMediaCache\Strategies;

class AbstractStrategy
{
    public function __construct($config)
    {
        $this->config = $config;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    public function transcode($absolutePath, $flySpec)
    {
        return $this->createTranscoder()->transcode($absolutePath, $flySpec);
    }
}
