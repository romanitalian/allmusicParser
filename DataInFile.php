<?php

/**
 * Class DataInFile
 */
class DataInFile
{
    public $path;
    public $fileName;
    public $fileNameFull;

    public function __construct($path = null, $fileName = null) {
        if ($path && $fileName) {
            $this->initPath($path, $fileName);
        }
    }

    public function initPath($path = null, $fileName = 'saved') {
        $path = $path ?: $this->path;
        $fileName = $fileName ?: $this->fileName;
        $fileNameFull = $path . DIRECTORY_SEPARATOR . $fileName;
        if (!is_dir($path)) {
            mkdir($path, 650, true);
        }
        if (!file_exists($fileNameFull)) {
            file_put_contents($fileNameFull, '');
        }
        // return array($fileNameFull, $path, $fileName);
        $this->path = $path;
        $this->fileName = $fileName;
        $this->fileNameFull = $fileNameFull;
        return $this;
    }

    public function getSavedAsArray() {
        // file_put_contents($this->fileNameFull, 'Madonna'."\n");
        $data = $this->getSaved();
        $out = explode("\n", $data);
        if ($out && is_array($out)) {
            $out = array_map(function ($i) {
                return str_replace(array("\n", "\r"), array('', ''), $i);
            }, $out);
            array_pop($out);
        }
        return $out;
    }

    public function getSaved() {
        $out = file_exists($this->fileNameFull) ? file_get_contents($this->fileNameFull) : null;
        return $out;
    }

    public function save($data) {
        var_dump($this->fileNameFull);
        var_dump($data);
        file_put_contents($this->fileNameFull, $data . "\n", FILE_APPEND);
        return $this;
    }
}
