<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\Exception;
use Pdp\PublicSuffix;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Pdp\PublicSuffix
 */
class PublicSuffixTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::__set_state
     * @covers ::__debugInfo
     * @covers ::jsonSerialize
     * @covers ::getIterator
     */
    public function testInternalPhpMethod()
    {
        $publicSuffix = new PublicSuffix('ac.be');
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        $this->assertEquals($publicSuffix, $generatePublicSuffix);
        $this->assertSame(['be', 'ac'], iterator_to_array($publicSuffix));
        $this->assertJsonStringEqualsJsonString(
            json_encode($publicSuffix->__debugInfo()),
            json_encode($publicSuffix)
        );
    }

    /**
     * @covers ::__construct
     * @covers ::setDomain
     * @covers ::setSection
     * @covers ::getContent
     * @covers ::toUnicode
     */
    public function testPSToUnicodeWithUrlEncode()
    {
        $this->assertSame('bébe', (new PublicSuffix('b%C3%A9be'))->toUnicode()->getContent());
    }

    /**
     * @covers ::__construct
     * @covers ::setDomain
     * @covers ::idnToAscii
     */
    public function testPSToAsciiThrowsException()
    {
        $this->expectException(Exception::class);
        new PublicSuffix('a⒈com');
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeThrowsException()
    {
        $this->expectException(Exception::class);
        (new PublicSuffix('xn--a-ecp.ru'))->toUnicode();
    }

    /**
     * @covers ::toAscii
     * @covers ::toUnicode
     * @covers ::idnToAscii
     * @covers ::idnToUnicode
     */
    public function testConversionReturnsTheSameInstance()
    {
        $instance = new PublicSuffix('ac.be');
        $this->assertSame($instance->toUnicode(), $instance);
        $this->assertSame($instance->toAscii(), $instance);
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeReturnsSameInstance()
    {
        $instance = new PublicSuffix('食狮.公司.cn');
        $this->assertSame($instance->toUnicode(), $instance);
    }

    /**
     * @dataProvider countableProvider
     * @param string|null $domain
     * @param int         $nbLabels
     * @param string[]    $labels
     * @covers ::count
     */
    public function testCountable($domain, $nbLabels, $labels)
    {
        $domain = new PublicSuffix($domain);
        $this->assertCount($nbLabels, $domain);
        $this->assertSame($labels, iterator_to_array($domain));
    }

    public function countableProvider()
    {
        return [
            'null' => [null, 0, []],
            'empty string' => ['', 1, ['']],
            'simple' => ['foo.bar.baz', 3, ['baz', 'bar', 'foo']],
            'unicode' => ['www.食狮.公司.cn', 4, ['cn', '公司', '食狮', 'www']],
        ];
    }

    /**
     * @covers ::getLabel
     */
    public function testGetLabel()
    {
        $domain = new PublicSuffix('master.example.com');
        $this->assertSame('com', $domain->getLabel(0));
        $this->assertSame('example', $domain->getLabel(1));
        $this->assertSame('master', $domain->getLabel(-1));
        $this->assertNull($domain->getLabel(23));
        $this->assertNull($domain->getLabel(-23));
    }

    /**
     * @covers ::keys
     */
    public function testOffsets()
    {
        $domain = new PublicSuffix('master.example.com');
        $this->assertSame([2], $domain->keys('master'));
    }
}
