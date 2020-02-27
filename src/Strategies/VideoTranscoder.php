<?php

namespace PhpMediaCache\Strategies;

class VideoTranscoder
{
    protected $intermediateTempFile;
    protected $transcodedFilePath;

    public function transcode($absolutePath, $flySpec)
    {
        $this->intermediateTempFile = tempnam('/tmp', 'flyfiles');
        $this->transcodedFilePath = $this->intermediateTempFile.'.'.$flySpec['format'];

        $command = dirname(__FILE__).'/../scripts/convert_'.$flySpec['format']." \"$absolutePath\" $this->transcodedFilePath";

        error_log('executing '.$command);
        exec($command);
        error_log('and done it');

        return $this->transcodedFilePath;
    }

    public function cleanup()
    {
        unlink($this->transcodedFilePath);
        unlink($this->intermediateTempFile);
    }
}
