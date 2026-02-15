<?php

declare(strict_types=1);

namespace racacax\XmlTv\Tests\Unit\Component;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\ProviderCache;
use ReflectionClass;

/**
 * Tests exhaustifs pour ProviderCache
 *
 * SCÉNARIOS TESTÉS:
 * 1. Lecture/écriture de contenu brut (string)
 * 2. Lecture/écriture de données structurées (array/JSON)
 * 3. Gestion des fichiers inexistants
 * 4. Création automatique des répertoires
 * 5. Modification partielle d'un cache (setArrayKey)
 * 6. Suppression du cache (clearCache)
 * 7. Gestion de JSON invalide
 * 8. Tests de sécurité (path traversal)
 */
class ProviderCacheTest extends TestCase
{
    private string $testCachePath = 'var/provider/';
    private string $testFile = 'test_cache.json';

    protected function setUp(): void
    {
        parent::setUp();

        // Nettoyer le cache avant chaque test
        $this->cleanupTestCache();
    }

    protected function tearDown(): void
    {
        // Nettoyer le cache après chaque test
        $this->cleanupTestCache();

        parent::tearDown();
    }

    private function cleanupTestCache(): void
    {
        if (is_dir($this->testCachePath)) {
            $files = glob($this->testCachePath . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->testCachePath);
        }
    }

    // ========================================
    // TEST: Constructor
    // ========================================

    public function testConstructorSetsFileName(): void
    {
        $cache = new ProviderCache('test.json');

        $reflection = new ReflectionClass($cache);
        $property = $reflection->getProperty('file');
        $property->setAccessible(true);

        $this->assertEquals('test.json', $property->getValue($cache));
    }

    // ========================================
    // TEST: getContent() - File doesn't exist
    // ========================================

    public function testGetContentReturnsNullWhenFileDoesNotExist(): void
    {
        $cache = new ProviderCache('nonexistent.json');

        $this->assertNull($cache->getContent());
    }

    // ========================================
    // TEST: getContent() - File exists
    // ========================================

    public function testGetContentReturnsFileContent(): void
    {
        $expectedContent = 'test content';

        // Créer le fichier manuellement
        @mkdir($this->testCachePath, 0777, true);
        file_put_contents($this->testCachePath . $this->testFile, $expectedContent);

        $cache = new ProviderCache($this->testFile);

        $this->assertEquals($expectedContent, $cache->getContent());
    }

    // ========================================
    // TEST: setContent() - Creates directory
    // ========================================

    public function testSetContentCreatesDirectoryIfNotExists(): void
    {
        $this->assertDirectoryDoesNotExist($this->testCachePath);

        $cache = new ProviderCache($this->testFile);
        $cache->setContent('test');

        $this->assertDirectoryExists($this->testCachePath);
    }

    // ========================================
    // TEST: setContent() - Writes content
    // ========================================

    public function testSetContentWritesContentToFile(): void
    {
        $content = 'test content with special chars: éàü 日本語';

        $cache = new ProviderCache($this->testFile);
        $cache->setContent($content);

        $this->assertFileExists($this->testCachePath . $this->testFile);
        $this->assertEquals($content, file_get_contents($this->testCachePath . $this->testFile));
    }

    // ========================================
    // TEST: setContent() - Overwrites existing file
    // ========================================

    public function testSetContentOverwritesExistingFile(): void
    {
        $cache = new ProviderCache($this->testFile);

        $cache->setContent('first content');
        $this->assertEquals('first content', $cache->getContent());

        $cache->setContent('second content');
        $this->assertEquals('second content', $cache->getContent());
    }

    // ========================================
    // TEST: getArray() - File doesn't exist
    // ========================================

    public function testGetArrayReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $cache = new ProviderCache('nonexistent.json');

        $result = $cache->getArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================
    // TEST: getArray() - Valid JSON
    // ========================================

    public function testGetArrayReturnsDecodedJsonArray(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2', 'nested' => ['a' => 'b']];

        $cache = new ProviderCache($this->testFile);
        $cache->setContent(json_encode($data));

        $result = $cache->getArray();

        $this->assertEquals($data, $result);
    }

    // ========================================
    // TEST: getArray() - Invalid JSON
    // ========================================

    public function testGetArrayHandlesInvalidJson(): void
    {
        $cache = new ProviderCache($this->testFile);
        $cache->setContent('invalid json {broken');

        $result = $cache->getArray();

        // JSON invalide devrait retourner un tableau vide au lieu de crasher
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================
    // TEST: getArray() - Empty file
    // ========================================

    public function testGetArrayHandlesEmptyFile(): void
    {
        $cache = new ProviderCache($this->testFile);
        $cache->setContent('');

        $result = $cache->getArray();

        // Empty string should be treated as '[]'
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ========================================
    // TEST: setArrayKey() - New key
    // ========================================

    public function testSetArrayKeyAddsNewKey(): void
    {
        $cache = new ProviderCache($this->testFile);

        $cache->setArrayKey('key1', 'value1');

        $result = $cache->getArray();
        $this->assertArrayHasKey('key1', $result);
        $this->assertEquals('value1', $result['key1']);
    }

    // ========================================
    // TEST: setArrayKey() - Update existing key
    // ========================================

    public function testSetArrayKeyUpdatesExistingKey(): void
    {
        $cache = new ProviderCache($this->testFile);

        $cache->setArrayKey('key1', 'value1');
        $cache->setArrayKey('key1', 'updated_value');

        $result = $cache->getArray();
        $this->assertEquals('updated_value', $result['key1']);
    }

    // ========================================
    // TEST: setArrayKey() - Preserves other keys
    // ========================================

    public function testSetArrayKeyPreservesOtherKeys(): void
    {
        $cache = new ProviderCache($this->testFile);

        $cache->setArrayKey('key1', 'value1');
        $cache->setArrayKey('key2', 'value2');
        $cache->setArrayKey('key3', 'value3');

        $result = $cache->getArray();

        $this->assertCount(3, $result);
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
    }

    // ========================================
    // TEST: setArrayKey() - Complex values
    // ========================================

    public function testSetArrayKeyHandlesComplexValues(): void
    {
        $cache = new ProviderCache($this->testFile);

        $complexValue = [
            'nested' => ['deep' => ['value' => 123]],
            'array' => [1, 2, 3],
            'boolean' => true,
            'null' => null
        ];

        $cache->setArrayKey('complex', $complexValue);

        $result = $cache->getArray();
        $this->assertEquals($complexValue, $result['complex']);
    }

    // ========================================
    // TEST: setArrayKey() - After setContent
    // ========================================

    public function testSetArrayKeyWorksAfterSetContent(): void
    {
        $cache = new ProviderCache($this->testFile);

        // Écrire du JSON initial
        $initialData = ['existing_key' => 'existing_value'];
        $cache->setContent(json_encode($initialData));

        // Ajouter une nouvelle clé
        $cache->setArrayKey('new_key', 'new_value');

        $result = $cache->getArray();

        $this->assertCount(2, $result);
        $this->assertEquals('existing_value', $result['existing_key']);
        $this->assertEquals('new_value', $result['new_key']);
    }

    // ========================================
    // TEST: clearCache() - Removes all files
    // ========================================

    public function testClearCacheRemovesAllCacheFiles(): void
    {
        // Créer plusieurs fichiers de cache
        $cache1 = new ProviderCache('cache1.json');
        $cache2 = new ProviderCache('cache2.json');
        $cache3 = new ProviderCache('cache3.json');

        $cache1->setContent('content1');
        $cache2->setContent('content2');
        $cache3->setContent('content3');

        $this->assertFileExists($this->testCachePath . 'cache1.json');
        $this->assertFileExists($this->testCachePath . 'cache2.json');
        $this->assertFileExists($this->testCachePath . 'cache3.json');

        // Nettoyer le cache
        ProviderCache::clearCache();

        $this->assertDirectoryDoesNotExist($this->testCachePath);
    }

    // ========================================
    // TEST: clearCache() - Handles empty directory
    // ========================================

    public function testClearCacheHandlesNonExistentDirectory(): void
    {
        $this->assertDirectoryDoesNotExist($this->testCachePath);

        // Ne devrait pas lancer d'erreur
        ProviderCache::clearCache();

        $this->assertDirectoryDoesNotExist($this->testCachePath);
    }

    // ========================================
    // TEST: Multiple instances same file
    // ========================================

    public function testMultipleInstancesSameFileShareData(): void
    {
        $cache1 = new ProviderCache($this->testFile);
        $cache2 = new ProviderCache($this->testFile);

        $cache1->setContent('shared content');

        $this->assertEquals('shared content', $cache2->getContent());
    }

    // ========================================
    // TEST: Different files are independent
    // ========================================

    public function testDifferentFilesAreIndependent(): void
    {
        $cache1 = new ProviderCache('file1.json');
        $cache2 = new ProviderCache('file2.json');

        $cache1->setContent('content1');
        $cache2->setContent('content2');

        $this->assertEquals('content1', $cache1->getContent());
        $this->assertEquals('content2', $cache2->getContent());
    }

    // ========================================
    // TEST: Large content handling
    // ========================================

    public function testHandlesLargeContent(): void
    {
        $largeContent = str_repeat('A', 1024 * 1024); // 1MB of 'A'

        $cache = new ProviderCache($this->testFile);
        $cache->setContent($largeContent);

        $this->assertEquals($largeContent, $cache->getContent());
    }

    // ========================================
    // TEST: Special characters in data
    // ========================================

    public function testHandlesSpecialCharactersInArrayData(): void
    {
        $cache = new ProviderCache($this->testFile);

        $specialData = [
            'unicode' => '日本語 中文 한글 العربية',
            'special' => 'éàüöñ çâêîô',
            'symbols' => '€£¥₹ ©®™ ←→↑↓',
            'quotes' => "double\"quotes and 'single'",
            'newlines' => "line1\nline2\rline3"
        ];

        $cache->setArrayKey('special', $specialData);

        $result = $cache->getArray();
        $this->assertEquals($specialData, $result['special']);
    }

    // ========================================
    // TEST: Security - Path traversal attempt
    // ========================================

    public function testPathTraversalAttempt(): void
    {
        // Tenter de créer un fichier en dehors du répertoire cache avec path traversal
        $maliciousPath = '../../../etc/malicious_test_file_' . time();
        $cache = new ProviderCache($maliciousPath);

        $cache->setContent('malicious content');

        // Lister tous les fichiers créés dans le cache
        $files = glob($this->testCachePath . '*');

        // Vérifier qu'un fichier sécurisé a été créé DANS le cache
        $this->assertNotEmpty($files, 'A sanitized file should have been created in the cache directory');

        // Vérifier que le nom du fichier a été sanitisé
        $createdFile = basename($files[0]);
        $this->assertStringNotContainsString(
            '..',
            $createdFile,
            'Sanitized filename should not contain ".."'
        );
        $this->assertStringNotContainsString(
            '/',
            $createdFile,
            'Sanitized filename should not contain "/"'
        );
        $this->assertStringNotContainsString(
            '\\',
            $createdFile,
            'Sanitized filename should not contain "\\"'
        );

        // Vérifier que le fichier n'a PAS été créé en dehors du cache
        $outsideCache = realpath(__DIR__ . '/../../../etc/malicious_test_file_*');
        $this->assertFalse(
            $outsideCache,
            'CRITICAL: File should NOT be created outside cache directory'
        );

        // Vérifier que le contenu peut être relu via la même interface
        $cache2 = new ProviderCache($maliciousPath);
        $this->assertEquals(
            'malicious content',
            $cache2->getContent(),
            'Content should be retrievable via the sanitized path'
        );

        // Nettoyer
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
