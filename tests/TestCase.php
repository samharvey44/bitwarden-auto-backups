<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set variables in the process environment, as a user would before running
     * a command, removing them again once the test finishes.
     *
     * @param  array<string, string>  $variables
     */
    protected function withProcessEnvironment(array $variables): void
    {
        foreach ($variables as $name => $value) {
            putenv("{$name}={$value}");
        }

        $this->beforeApplicationDestroyed(function () use ($variables): void {
            foreach (array_keys($variables) as $name) {
                putenv($name);
            }
        });
    }
}
