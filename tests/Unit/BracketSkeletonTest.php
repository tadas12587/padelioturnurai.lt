<?php

namespace Tests\Unit;

use App\Models\Overlay;
use PHPUnit\Framework\TestCase;

class BracketSkeletonTest extends TestCase
{
    public function test_skeleton_8(): void
    {
        $m = Overlay::bracketSkeleton(8);
        $this->assertCount(8, $m);
        $this->assertSame('Ketvirtfinaliai', $m[0]['round']);
        $this->assertSame('Finalas', $m[6]['round']);
        $this->assertSame('Dėl 3 vietos', $m[7]['round']);
        $this->assertNull($m[0]['winner']);
        $this->assertSame('', $m[0]['team1']);
    }

    public function test_skeleton_16(): void
    {
        $m = Overlay::bracketSkeleton(16);
        $this->assertCount(16, $m);
        $this->assertSame('1/8 finalio', $m[0]['round']);
        $this->assertSame('Ketvirtfinaliai', $m[8]['round']);
        $this->assertSame('Finalas', $m[14]['round']);
        $this->assertSame('Dėl 3 vietos', $m[15]['round']);
    }
}
