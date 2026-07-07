<?php

namespace TestMonitor\VueI18nGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GenerateVueTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vue:translations
                            {--path= : Laravel language source path (defaults to Laravel language path)}
                            {--output= : Vue-i18n output file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Vue-i18n JS translation file based on Laravel JSON / PHP translation files';

    /**
     * Laravel source language path.
     *
     * @var string
     */
    protected string $languagePath;

    /**
     * Vue-i18n output file path.
     *
     * @var string
     */
    protected string $outputFile;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Determine input and output paths.
        $this->languagePath = $this->option('path') ?
            base_path($this->option('path')) :
            lang_path();

        if (! is_dir($this->languagePath)) {
            $this->error("\"{$this->languagePath}\" does not exists.");

            return 1;
        }

        $this->outputFile = $this->option('output') ?
            base_path($this->option('output')) :
            config('vue-i18n-generator.outputFile');

        // Parse Laravel translations.
        $translations = $this->getTranslations([$this->languagePath]);

        // Write translation to Vue-i18n file.
        $size = $this->generateVue18nFile($this->outputFile, $translations);

        // Show number of translations per language.
        $this->table(
            ['Language', 'Translations'],
            array_map(
                fn ($language, $lines) => [$language, count($lines)],
                array_keys($translations),
                array_values($translations)
            )
        );

        $this->line("<fg=yellow>{$this->outputFile}</fg=yellow> generated (<fg=green>{$size} bytes</fg=green>).");

        return 0;
    }

    /**
     * Parse all translation files into a collection.
     *
     * @param array $paths
     *
     * @return array
     */
    public function getTranslations(array $paths): array
    {
        return Collection::make($paths)
            ->flatMap(fn ($path) => $this->findTranslationFilesRecursively($path))
            ->groupBy(fn ($path) => $this->getTranslationLanguage($path))
            ->map(function (Collection $files) {
                return $files->flatMap(function ($file) {
                    $translations = $this->readTranslationFile($file);

                    return [$file => $translations];
                });
            })
            ->map(fn ($content, $language) => $this->convertTranslationsToNestedArray($content->toArray(), $language))
            ->all();
    }

    /**
     * Scan the provided path for Laravel JSON and PHP files.
     *
     * @param string $path
     *
     * @return array|false
     */
    protected function findTranslationFilesRecursively(string $path): array | false
    {
        // Use Symfony's Finder component for more robust recursive file finding
        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in($path)->name('*.php')->name('*.json');

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return collect($files) ? $files : false;
    }

    /**
     * Converts the provided translations into nested arrays.
     *
     * @param array $translations
     *
     * @param string $language
     *
     * @return array
     */
    protected function convertTranslationsToNestedArray(array $translations, string $language): array
    {
        $nested = [];

        foreach ($translations as $filePath => $content) {
            // Remove the base path and language directory from the file path
            $relativePath = str_replace($this->languagePath . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR, '', $filePath);
            $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
            $current = &$nested;
            foreach ($pathParts as $index => $part) {
                $isFile = $index === count($pathParts) - 1;
                // If the $part is the file then remove the file extension
                $part = $isFile ? pathinfo($part, PATHINFO_FILENAME) : $part;

                // If we are at the last part, assign the content directly
                if ($isFile) {
                    $current[$part] = $content[$part];
                } else {
                    // Create nested structure if it doesn't exist
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
        }

        return $nested;
    }

    /**
     * Get the translation key based on the provided filename or path to file.
     *
     * @param string $filename
     *
     * @return string
     */
    public function getTranslationLanguage(string $filename): string
    {
        return match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'json' => str_replace('.json', '', basename($filename)),
            'php' => explode(DIRECTORY_SEPARATOR, trim(str_replace($this->languagePath, '', dirname($filename)), DIRECTORY_SEPARATOR))[0],
        };
    }

    /**
     * Read a JSON or PHP file and parse it into an array.
     *
     * @param string $filename
     *
     * @return array<string,string>
     */
    protected function readTranslationFile(string $filename): array
    {
        return match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'json' => json_decode(file_get_contents($filename), true),
            'php' => [basename($filename, '.php') => include($filename)],
        };
    }

    /**
     * Writes translations to a JSON file.
     *
     * @param string $filename
     * @param array<string,array> $translations
     *
     * @return int|false
     */
    protected function generateVue18nFile(string $filename, array $translations): int|false
    {
        return file_put_contents(
            $filename,
            $this->convertTranslationsToVue18n($translations)
        );
    }

    /**
     * Convert translation array to Vue-i18n JSON file.
     *
     * @param array<string,array> $translations
     *
     * @return string
     */
    protected function convertTranslationsToVue18n(array $translations): string
    {
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "export default {$json}" . PHP_EOL;
    }
}
