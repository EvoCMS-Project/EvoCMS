<?php
namespace Evo;

class BetterZip extends \ZipArchive
{
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function addDir(string $path, string $base = '')
    {
        if ($base !== '' && strpos($path, $base) === 0) {
            $startpos = strlen($base) + 1;
        } else {
            $startpos = 0;
        }

        if ($this->filters && preg_match($this->filters, $path))
            return;

        $this->addEmptyDir(substr($path, $startpos));
        foreach (glob($path . '/{.??*,*}', GLOB_BRACE) as $node) {
            if ($this->filters && preg_match($this->filters, $node)) {
                continue;
            } elseif (is_dir($node)) {
                $this->addDir($node, $base);
            } else if (is_file($node)) {
                $this->addFile($node, substr($node, $startpos));
            }
        }
    }
}
