<?php

namespace TestMonitor\VueI18nGenerator\Tests;

use Illuminate\Support\Facades\Artisan;

class GenerateVueTranslationsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->cleanUp();
    }

    private function cleanUp()
    {
        if (is_file(__DIR__ . '/data/output.js')) {
            unlink(__DIR__ . '/data/output.js');
        }
    }

    /** @test */
    public function it_can_generate_vue_i18n_translations_by_running_the_console_command()
    {
        Artisan::call('vue:translations');

        $this->assertFileExists(__DIR__ . '/data/output.js');

        $output = "export default {\n" .
            "    \"en\": {\n" .
            "        \"The fox jumped over the lazy dog\": \"The fox jumped over the lazy dog\"\n" .
            "    },\n" .
            "    \"nl\": {\n" .
            "        \"The fox jumped over the lazy dog\": \"De vos sprong over de luie hond heen\"\n" .
            "    }\n" .
        "}\n";

        $this->assertStringEqualsFile(__DIR__ . '/data/output.js', $output);
    }
}
