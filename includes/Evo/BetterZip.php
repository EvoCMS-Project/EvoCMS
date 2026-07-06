<?php
namespace Evo;

class BetterZip extends \ZipArchive
{
    private $filters = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function addDir(string $path, string $base = '', array $exclude = [])
    {
        if ($base !== '' && strpos($path, $base) === 0) {
            $startpos = strlen($base) + 1;
        } else {
            $startpos = 0;
        }

        if ($this->isExcluded($path, $exclude)) {
            return;
        }

        $localName = substr($path, $startpos);
        if ($localName !== '') {
            $this->addEmptyDir($localName);
        }

        foreach (glob($path . '/{.??*,*}', GLOB_BRACE) as $node) {
            if ($this->isExcluded($node, $exclude)) {
                continue;
            } elseif (is_dir($node)) {
                $this->addDir($node, $base, $exclude);
            } else if (is_file($node)) {
                $this->addFile($node, substr($node, $startpos));
            }
        }
    }

    private function isExcluded(string $path, array $exclude): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);

        foreach (array_merge($this->filters, $exclude) as $filter) {
            $filter = trim((string) $filter);
            if ($filter === '') {
                continue;
            }

            $normalizedFilter = str_replace('\\', '/', $filter);
            if (@preg_match($normalizedFilter, '') !== false) {
                if (preg_match($normalizedFilter, $normalizedPath)) {
                    return true;
                }
                continue;
            }

            $plainFilter = trim($normalizedFilter, '/');
            if (
                strpos($normalizedPath, rtrim($normalizedFilter, '/')) === 0 ||
                $normalizedPath === $plainFilter ||
                strpos($normalizedPath, '/' . $plainFilter . '/') !== false ||
                substr($normalizedPath, -strlen('/' . $plainFilter)) === '/' . $plainFilter
            ) {
                return true;
            }
        }

        return false;
    }
}
