<?php

namespace TestMonitor\VueI18nGenerator\Console;

use Illuminate\Support\Str;
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
                            {--path= : Laravel language source path}
                            {--output= : Vue-i18n output file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Vue-i18n JSON translation file based on Laravel JSON translation files';

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
    public function handle()
    {
        // Determine input and output paths.
        $this->languagePath = base_path($this->option('path') ?? config('vue-i18n-generator.languagePath'));
        $this->outputFile = base_path($this->option('output') ?? config('vue-i18n-generator.outputFile'));

        if (! is_dir($this->languagePath)) {
            $this->error("\"{$this->languagePath}\" does not exists.");

            return 1;
        }

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
            ->flatMap(fn ($path) => $this->findTranslationFiles($path))
            ->groupBy(fn ($paths) => $this->getTranslationLanguage($paths))
            ->map(function (Collection $files) {
                return $files->flatMap(fn ($file) => $this->readTranslationFile($file));
            })
            ->map(fn ($content) => $this->convertTranslations($content))
            ->all();
    }

    /**
     * Scan the provided path for Laravel JSON and PHP files.
     *
     * @param string $path
     *
     * @return array|false
     */
    protected function findTranslationFiles(string $path): array|false
    {
        return glob($path . '/{,*/}*.{json,php}', GLOB_BRACE);
    }

    /**
     * Get the translation key based on the provided filename.
     *
     * @param string $filename
     *
     * @return string
     */
    public function getTranslationLanguage(string $filename): string
    {
        return match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'json' => str_replace('.json', '', basename($filename)),
            'php' => basename(dirname($filename)),
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
     * Convert translations into the Vue-i18n format.
     *
     * @param \Illuminate\Support\Collection $lines
     *
     * @return array<string,string|array>
     */
    protected function convertTranslations(Collection $lines): array
    {
        return $lines
            ->mapWithKeys(fn ($translation, $key) => [
                $this->convertTranslation($key) => $this->convertTranslation($translation),
            ])
            ->all();
    }

    /**
     * Converts a single translation line.
     *
     * @param string $content
     *
     * @return string
     */
    protected function convertTranslation(string|array $content): string|array
    {
        // Handle nested translations
        if (is_array($content)) {
            return array_combine(
                array_keys($content),
                array_map(fn ($value) => $this->convertTranslation($value), $content)
            );
        }

        return Str::of($content)
            ->pipe(fn ($line) => $this->transformPluralization($line))
            ->pipe(fn ($line) => $this->transformCollonsToBraces($line))
            ->pipe(fn ($line) => $this->removeEscapeCharacter($line))
            ->value();
    }

    /**
     * Remove escape characters.
     *
     * @param string $line
     *
     * @return string
     */
    protected function removeEscapeCharacter(string $line): string
    {
        return preg_replace_callback(
            '/' . preg_quote('!', '/') . "(:\w+)/",
            fn ($matches) => '{' . mb_substr($matches[0], 1) . '}',
            $line
        );
    }

    /**
     * Turn Laravel style ":link" into vue-i18n style "{link}".
     *
     * @param string $line
     *
     * @return string
     */
    protected function transformCollonsToBraces(string $line): string
    {
        return preg_replace_callback(
            '/(?<!mailto|tel|' . preg_quote('!', '/') . "):\w+/",
            fn ($matches) => '{' . mb_substr($matches[0], 1) . '}',
            $line
        );
    }

    /**
     * Convert Laravel into Vue18n pluralization style.
     *
     * @param string $line
     *
     * @return string
     */
    protected function transformPluralization(string $line): string
    {
        return preg_replace_callback(
            "/\{0\}\s(.*)\|\{1\}(.*)\|\[2,\*\](.*)/",
            fn ($matches) => "{$matches[1]}|{$matches[2]}|{$matches[3]}",
            $line
        );
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
