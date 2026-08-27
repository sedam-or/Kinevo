<?php
/**
 * Build-time polyfill for Alpine containers whose packaged ext-zip .so has an
 * ABI mismatch. Implements only the surface NativePHP's createZipBundle uses
 * (open/addFile/addEmptyDir/statName/close), delegating compression to zip(1).
 * Loaded via auto_prepend_file during native:* build prep on Linux only.
 */
if (! class_exists('ZipArchive')) {
    final class ZipArchive
    {
        public const CREATE = 1;
        public const OVERWRITE = 8;
        public const RDONLY = 16;

        private string $dest = '';

        /** @var string|null path when opened for reading/extraction */
        private ?string $readOnlySource = null;

        /** @var array<int,array{0:string,1:string}> */
        private array $files = [];

        /** @var string[] */
        private array $dirs = [];

        public function getNumFiles(): int|false
        {
            if ($this->readOnlySource === null) {
                return false;
            }
            exec('zipinfo -1 '.escapeshellarg($this->readOnlySource).' 2>/dev/null', $names);

            return count($names);
        }

        public function getNameIndex(int $index): string|false
        {
            if ($this->readOnlySource === null) {
                return false;
            }
            exec('zipinfo -1 '.escapeshellarg($this->readOnlySource).' 2>/dev/null', $names);

            return $names[$index] ?? false;
        }

        public function extractTo(string $destination, mixed $entries = null): bool
        {
            if ($this->readOnlySource === null || ! is_file($this->readOnlySource)) {
                return false;
            }
            @mkdir($destination, 0777, true);
            if (is_array($entries) && $entries !== []) {
                foreach ($entries as $entry) {
                    exec('cd '.escapeshellarg($destination).' && unzip -q -o '
                        .escapeshellarg($this->readOnlySource).' '.escapeshellarg((string) $entry), $out, $code);
                    if ($code !== 0) {
                        return false;
                    }
                }

                return true;
            }
            exec('unzip -q -o '.escapeshellarg($this->readOnlySource).' -d '.escapeshellarg($destination), $out, $code);

            return $code === 0;
        }

        public function open(string $filename, int $flags = 0): bool
        {
            $this->readOnlySource = $filename;

            if ($flags & self::RDONLY) {
                exec('unzip -tq '.escapeshellarg($filename).' >/dev/null 2>&1', $out, $code);

                return $code === 0;
            }
            if ($flags & self::OVERWRITE) {
                @unlink($filename);
            }
            $this->dest = $filename;
            $this->files = [];
            $this->dirs = [];

            return true;
        }

        public function addFile(string $filepath, string $entryname = ''): bool
        {
            $this->files[] = [$filepath !== '' ? $filepath : '.', ($entryname !== '' ? $entryname : $filepath)];

            return true;
        }

        public function addEmptyDir(string $dirname): bool
        {
            $this->dirs[] = rtrim($dirname, '/');

            return true;
        }

        public function statName(string $name): mixed
        {
            foreach ($this->dirs as $d) {
                if ($d === rtrim($name, '/') || $d.'/' === rtrim($name, '/').'/') {
                    return ['name' => $name];
                }
            }
            foreach ($this->files as [, $entry]) {
                if ($entry === $name) {
                    return ['name' => $name];
                }
            }

            return false;
        }

        public function close(): bool
        {
            if ($this->readOnlySource !== null) {
                return true;
            }
            if (! is_dir(dirname($this->dest))) {
                mkdir(dirname($this->dest), 0777, true);
            }
            @unlink($this->dest);

            // All sources share one root (a staging copy of the Laravel app).
            $root = null;
            foreach ($this->files as [$path, $entry]) {
                if (str_ends_with($path, '/'.$entry) || $path === $entry) {
                    $candidate = rtrim(substr($path, 0, strlen($path) - strlen($entry)), '/');
                    if ($candidate !== '' && ($root === null || strlen($candidate) < strlen($root))) {
                        $root = $candidate;
                    }
                }
            }
            if ($root === null || ! is_dir($root)) {
                return false;
            }

            // Stream the pruned staging tree into zip(1) instead of holding
            // every path in memory (vendor/ alone is tens of thousands of files).
            $cmd = sprintf(
                'cd %s && find . -mindepth 1 ! -name ziparchive_cli_shim.php 2>/dev/null'
                .' | sed "s#^\./##" | zip -q -X --symlinks %s -@',
                escapeshellarg($root),
                escapeshellarg($this->dest)
            );
            passthru($cmd, $code);

            return $code === 0;
        }
    }
}
