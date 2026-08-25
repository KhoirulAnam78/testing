<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PowerGridPerformanceConventionTest extends TestCase
{
    public function test_powergrid_tables_use_minimal_record_count(): void
    {
        foreach (glob(__DIR__.'/../../app/Livewire/Table*.php') as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsString('showRecordCount()', $contents, basename($file).' should avoid full pagination counts.');
        }
    }

    public function test_powergrid_reference_filters_are_cached(): void
    {
        foreach (glob(__DIR__.'/../../app/Livewire/Table*.php') as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsString('->dataSource(Prodi::', $contents, basename($file).' should cache Prodi filter options.');
            $this->assertStringNotContainsString('->dataSource(Semester::', $contents, basename($file).' should cache Semester filter options.');
            $this->assertStringNotContainsString('->dataSource(MataKuliah::', $contents, basename($file).' should cache MataKuliah filter options.');
        }
    }
}
