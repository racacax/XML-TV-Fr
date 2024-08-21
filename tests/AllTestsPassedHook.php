<?php

namespace racacax\XmlTvTest;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterSuccessfulTestHook;

class AllTestsPassedHook implements AfterLastTestHook, AfterSuccessfulTestHook
{
    private bool $allTestsPassed = true;

    public function executeAfterSuccessfulTest(string $test, float $time): void
    {
        // This method is called after each successful test
    }

    public function executeAfterLastTest(): void
    {
        if ($this->allTestsPassed) {
            $this->allTestsSucceeded();
        }
    }

    public function executeAfterIncompleteTest(string $test, string $message, float $time): void
    {
        if(!str_contains($test, 'testIntegrity')) {
            $this->allTestsPassed = false;
        }
    }

    public function executeAfterRiskyTest(string $test, string $message, float $time): void
    {
        if(!str_contains($test, 'testIntegrity')) {
            $this->allTestsPassed = false;
        }
    }

    public function executeAfterSkippedTest(string $test, string $message, float $time): void
    {
        if(!str_contains($test, 'testIntegrity')) {
            $this->allTestsPassed = false;
        }
    }

    public function executeAfterTestFailure(string $test, string $message, float $time): void
    {
        if(!str_contains($test, 'testIntegrity')) {
            $this->allTestsPassed = false;
        }
    }

    public function executeAfterTestError(string $test, string $message, float $time): void
    {
        if(!str_contains($test, 'testIntegrity')) {
            $this->allTestsPassed = false;
        }
    }
    private function isRunningFullSuite(): bool
    {
        // Check if PHPUnit was run without any test file or directory filters
        global $argv;

        foreach ($argv as $arg) {
            if (strpos($arg, '--filter') !== false) {
                return false;
            }
        }

        return true;
    }
    private function allTestsSucceeded(): void
    {
        if($this->isRunningFullSuite()) {
            echo 'Integrity hash generated';
            file_put_contents('integrity.sha256', Utils::generateHash());
        }
    }
}
