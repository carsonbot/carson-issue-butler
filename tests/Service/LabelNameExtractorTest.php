<?php

namespace App\Tests\Service;

use App\Repository\Repository;
use App\Service\LabelNameExtractor;
use App\Tests\Service\Issues\Github\FakedCachedLabelApi;
use Github\Api\Issue\Labels;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;

class LabelNameExtractorTest extends TestCase
{
    public function testExtractLabels()
    {
        $backendApi = $this->getMockBuilder(Labels::class)
            ->disableOriginalConstructor()
            ->getMock();
        $api = new FakedCachedLabelApi($backendApi, new NullAdapter());
        $extractor = new LabelNameExtractor($api);
        $repo = new Repository('carson-playground', 'symfony');

        $this->assertSame(['Messenger'], $extractor->extractLabels('[Messenger] Foobar', $repo));
        $this->assertSame(['Messenger'], $extractor->extractLabels('[messenger] Foobar', $repo));
        $this->assertSame(['Messenger', 'Mime'], $extractor->extractLabels('[Messenger][Mime] Foobar', $repo));
        $this->assertSame(['Messenger', 'Mime'], $extractor->extractLabels('[Messenger] [Mime] Foobar', $repo));
        $this->assertSame(['Messenger', 'Mime'], $extractor->extractLabels('[Messenger] Foobar [Mime] ', $repo));
    }
}
