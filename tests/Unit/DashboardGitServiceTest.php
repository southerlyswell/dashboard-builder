<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use App\Services\DashboardGitService;

class DashboardGitServiceTest extends \Tests\TestCase
{
    private DashboardGitService $git;

    protected function setUp(): void
    {
        parent::setUp();
        $this->git = new DashboardGitService();
    }

    #[Test]
    public function it_slugifies_dashboard_names(): void
    {
        $this->assertEquals('test-dashboard-123', $this->git->slugify('Test Dashboard 123!'));
        $this->assertEquals('dashboard', $this->git->slugify(''));
    }

    #[Test]
    public function it_saves_dashboard_and_returns_git_hash(): void
    {
        $result = $this->git->save('test-client', 'Unit Test Dashboard', [
            'title' => 'Unit Test Dashboard',
            'theme' => 'dark',
            'cards' => [
                ['id' => 'kpi-1', 'type' => 'kpi', 'title' => 'Test KPI', 'colspan' => 2],
            ]
        ]);

        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('git_hash', $result);
        $this->assertFileExists($result['file']);
    }

    #[Test]
    public function it_creates_version_metadata_on_save(): void
    {
        $result = $this->git->save('test-client', 'Versioned Dashboard', [
            'title' => 'Versioned',
            'cards' => [['id' => 'x', 'type' => 'kpi', 'title' => 'X', 'colspan' => 3]]
        ]);

        $versionFile = dirname($result['file']) . DIRECTORY_SEPARATOR . '.versions.json';
        $this->assertFileExists($versionFile);
    }
}
