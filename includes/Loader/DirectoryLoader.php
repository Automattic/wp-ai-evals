<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Loader;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Suite;

final class DirectoryLoader
{
    public function load(Registry $registry, string $directory): Registry
    {
        $root = realpath($directory);
        if (false === $root || !is_dir($root) || !is_readable($root)) {
            throw new InvalidArgumentException(sprintf('Eval directory "%s" is not readable.', $directory));
        }

        $loaded = 0;
        foreach ($this->topLevelPhpFiles($root) as $file) {
            $loaded += $this->registerSuites($registry, $this->requireFile($file), $file);
        }

        foreach ($this->suiteDirectories($root) as $suiteDirectory) {
            $manifest = $suiteDirectory . '/suite.php';
            if (!is_readable($manifest)) {
                throw new InvalidArgumentException(sprintf(
                    'Eval suite directory "%s" requires a readable suite.php manifest.',
                    $suiteDirectory
                ));
            }

            $suite = $this->requireFile($manifest);
            if (!$suite instanceof Suite) {
                throw new InvalidArgumentException(sprintf('Eval manifest "%s" must return a Suite.', $manifest));
            }

            $casesDirectory = $suiteDirectory . '/cases';
            if (is_dir($casesDirectory)) {
                foreach ($this->recursivePhpFiles($casesDirectory) as $caseFile) {
                    $this->addCases($suite, $this->requireFile($caseFile), $caseFile);
                }
            }

            $registry->register($suite);
            ++$loaded;
        }

        if (0 === $loaded) {
            throw new InvalidArgumentException(sprintf('Eval directory "%s" did not contain any suites.', $directory));
        }

        return $registry;
    }

    /** @return list<string> */
    private function topLevelPhpFiles(string $root): array
    {
        $files = glob($root . '/*.php');
        if (false === $files) {
            return [];
        }

        sort($files, SORT_STRING);

        return array_values($files);
    }

    /** @return list<string> */
    private function suiteDirectories(string $root): array
    {
        $directories = glob($root . '/*', GLOB_ONLYDIR);
        if (false === $directories) {
            return [];
        }

        sort($directories, SORT_STRING);

        return array_values($directories);
    }

    /** @return list<string> */
    private function recursivePhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && 'php' === strtolower($file->getExtension())) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /** @return mixed */
    private function requireFile(string $file)
    {
        return (static function (string $isolatedFile) {
            return require $isolatedFile;
        })($file);
    }

    /** @param mixed $value */
    private function registerSuites(Registry $registry, $value, string $file): int
    {
        if ($value instanceof Suite) {
            $registry->register($value);

            return 1;
        }

        if (!is_iterable($value)) {
            throw new InvalidArgumentException(sprintf(
                'Eval file "%s" must return a Suite or iterable of suites.',
                $file
            ));
        }

        $count = 0;
        foreach ($value as $suite) {
            if (!$suite instanceof Suite) {
                throw new InvalidArgumentException(sprintf('Eval file "%s" returned a non-Suite value.', $file));
            }
            $registry->register($suite);
            ++$count;
        }

        return $count;
    }

    /** @param mixed $value */
    private function addCases(Suite $suite, $value, string $file): void
    {
        if ($value instanceof EvaluationCase) {
            $suite->addCase($value);
            return;
        }

        if (!is_iterable($value)) {
            throw new InvalidArgumentException(sprintf(
                'Eval case file "%s" must return an EvaluationCase or iterable of cases.',
                $file
            ));
        }

        foreach ($value as $case) {
            if (!$case instanceof EvaluationCase) {
                throw new InvalidArgumentException(sprintf(
                    'Eval case file "%s" returned a non-EvaluationCase value.',
                    $file
                ));
            }
            $suite->addCase($case);
        }
    }
}
