<?php

namespace Quarkinocom\TranslationManager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LanguageTranslationCommand extends Command
{
    protected $signature = 'language:translate {action} {source} {target?}';
    protected $description = 'Translate language files.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $action = $this->argument('action');
        $source = $this->argument('source');
        $target = $this->argument('target');

        switch ($action) {
            case 'show':
                $this->show($source);
                break;
            case 'compare':
                $this->compare($source, $target);
                break;
            case 'translate':
                $this->translate($source, $target, false);
                break;
            case 'repair':
                $this->translate($source, $target, true);
                break;
            default:
                $this->error('Invalid action.');
                break;
        }
    }

    /**
     * @param $source
     * @return void
     */
    protected function show($source): void
    {
        $langPath = resource_path("lang/$source");
        if (!file_exists($langPath) || !is_dir($langPath)) {
            $this->error("The specified language directory does not exist: $source");
            return;
        }

        try {
            $files = $this->getLangFiles($langPath);
        } catch (\Exception $e) {
            $this->error("Error scanning language directory: " . $e->getMessage());
            return;
        }

        $rows = [];
        $totalFiles = 0;
        $totalKeys = 0;

        foreach ($files as $file) {
            try {
                $filePath = $file->getRealPath();
                $translations = include $filePath;
                if (!is_array($translations)) {
                    throw new \Exception("Invalid file format. Expected array, got " . gettype($translations));
                }
                $keyCount = count($translations);
                $rows[] = [
                    'Directory' => $file->getPath(),
                    'File' => $file->getFilename(),
                    'Keys' => $keyCount
                ];
                $totalFiles++;
                $totalKeys += $keyCount;
            } catch (\Exception $e) {
                $this->error("Error processing file {$file->getFilename()}: " . $e->getMessage());
                continue;
            }
        }

        if ($totalFiles > 0) {
            $this->table(['Directory', 'File', 'Keys'], $rows);
            $this->info("Total files: $totalFiles");
            $this->info("Total keys: $totalKeys");
        } else {
            $this->info("No translation files found for the specified language.");
        }
    }

    /**
     * @param $dir
     * @param array $results
     * @return array
     */
    protected function getLangFiles($dir, array &$results = []): array
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $results[] = $file;
            }
        }

        return $results;
    }

    /**
     * @param $source
     * @param $target
     * @return void
     */
    protected function compare($source, $target): void
    {
        $sourcePath = resource_path("lang/$source");
        $targetPath = resource_path("lang/$target");

        // Check if source and target directories exist
        if (!file_exists($sourcePath) || !is_dir($sourcePath)) {
            $this->error("The source language directory does not exist: $source");
            return;
        }

        if (!file_exists($targetPath) || !is_dir($targetPath)) {
            $this->error("The target language directory does not exist: $target");
            return;
        }

        // Get all source and target language files
        $sourceFiles = $this->getLangFiles($sourcePath);
        $targetFiles = $this->getLangFiles($targetPath);

        // Mapping file paths for easy lookup
        $targetFilesMap = [];
        foreach ($targetFiles as $file) {
            $relativePath = str_replace($targetPath, '', $file->getRealPath());
            $targetFilesMap[$relativePath] = $file;
        }

        $rows = [];
        $totalSourceKeys = 0;
        $totalTargetKeys = 0;
        $differences = 0;

        foreach ($sourceFiles as $file) {
            $relativePath = str_replace($sourcePath, '', $file->getRealPath());
            $sourceTranslations = include $file->getRealPath();
            $totalSourceKeys += count($sourceTranslations);

            if (!array_key_exists($relativePath, $targetFilesMap)) {
                $rows[] = [
                    'File' => $relativePath,
                    'Key' => 'N/A',
                    'Status' => 'Missing file in target'
                ];
                $differences++;
                continue;
            }

            $targetTranslations = include $targetFilesMap[$relativePath]->getRealPath();
            $totalTargetKeys += count($targetTranslations);

            foreach ($sourceTranslations as $key => $value) {
                if (!array_key_exists($key, $targetTranslations)) {
                    $rows[] = [
                        'File' => $relativePath,
                        'Key' => $key,
                        'Status' => 'Missing key in target'
                    ];
                    $differences++;
                } elseif (empty($targetTranslations[$key])) {
                    // Check for empty values in target translations
                    $rows[] = [
                        'File' => $relativePath,
                        'Key' => $key,
                        'Status' => 'Empty value in target'
                    ];
                    $differences++;
                }
            }
        }

        if (!empty($rows)) {
            $this->table(['File', 'Key', 'Status'], $rows);
        } else {
            $this->info("No missing or empty keys found between $source and $target languages.");
        }

        $this->info("Summary:");
        $this->info("Total source files: " . count($sourceFiles));
        $this->info("Total target files: " . count($targetFiles));
        $this->info("Total source keys: $totalSourceKeys");
        $this->info("Total target keys: $totalTargetKeys");
        $this->info("Differences detected: $differences");
    }


    /**
     * @param $source
     * @param $target
     * @param bool $repair
     * @return void
     */
    protected function translate($source, $target, bool $repair = false): void
    {
        $languages = include resource_path('lang/' . $source . '/lang.php');
        $sourcePath = resource_path("lang/$source");
        $targetPath = resource_path("lang/$target");

        if (!array_key_exists($source, $languages) || !array_key_exists($target, $languages)) {
            $this->error("One of the specified languages is not supported.");
            return;
        }

        if (!file_exists($targetPath) && !$repair) {
            mkdir($targetPath, 0755, true);
        }

        $sourceFiles = $this->getLangFiles($sourcePath);
        $apiCalls = 0;
        $updates = 0;

        foreach ($sourceFiles as $file) {
            $relativePath = str_replace($sourcePath, '', $file->getRealPath());
            $targetFilePath = $targetPath . $relativePath;
            $shouldTranslateFile = !$repair || !file_exists($targetFilePath);

            $sourceTranslations = include $file->getRealPath();
            $targetTranslations = $shouldTranslateFile ? [] : include $targetFilePath;

            $translationsToUpdate = [];

            foreach ($sourceTranslations as $key => $value) {
                if (!array_key_exists($key, $targetTranslations) || empty($targetTranslations[$key])) {
                    $translatedText = $this->translateText($value, $languages[$source], $languages[$target]);
                    if ($translatedText !== null) {
                        $translationsToUpdate[$key] = $translatedText;
                        $updates++;
                    }
                }
            }

            if (!empty($translationsToUpdate)) {
                $apiCalls++;
                $targetTranslations = array_merge($targetTranslations, $translationsToUpdate);

                if (!file_exists(dirname($targetFilePath))) {
                    mkdir(dirname($targetFilePath), 0755, true);
                }

                file_put_contents($targetFilePath, "<?php\n\nreturn " . var_export($targetTranslations, true) . ";\n");
            }
        }

        $this->info("Translation completed. API calls made: $apiCalls. Keys updated: $updates.");
    }

    /**
     * @param $text
     * @param $sourceLangName
     * @param $targetLangName
     * @return mixed|null
     */
    protected function translateText($text, $sourceLangName, $targetLangName): mixed
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key')
            ])->post('https://api.openai.com/v1/translations', [
                'input' => $text,
                'source_language' => $sourceLangName,
                'target_language' => $targetLangName,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['translations'][0]['text'] ?? null;
            } else {
                $this->error("Failed to translate text due to an API error.");
                return null;
            }
        } catch (\Exception $e) {
            $this->error("An exception occurred during translation: " . $e->getMessage());
            return null;
        }
    }
}
