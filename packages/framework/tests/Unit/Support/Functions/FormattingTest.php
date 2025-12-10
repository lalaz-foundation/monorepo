<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Functions;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

class FormattingTest extends FrameworkUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure functions are loaded
        require_once __DIR__ . '/../../../../src/Support/Functions/formatting.php';
    }

    // =========================================================================
    // money_format() tests
    // =========================================================================

    /**
     * @test
     */
    public function testmoneyFormatFormatsBrlCurrency(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = money_format(1234.56, 'BRL', 'pt_BR');

        $this->assertIsString($result);
        $this->assertStringContainsString('1.234,56', $result);
    }

    /**
     * @test
     */
    public function testmoneyFormatFormatsUsdCurrency(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = money_format(1234.56, 'USD', 'en_US');

        $this->assertIsString($result);
        $this->assertStringContainsString('1,234.56', $result);
    }

    /**
     * @test
     */
    public function testmoneyFormatFormatsEurCurrency(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = money_format(1234.56, 'EUR', 'de_DE');

        $this->assertIsString($result);
        $this->assertStringContainsString('1.234,56', $result);
    }

    // =========================================================================
    // number_format_locale() tests
    // =========================================================================

    /**
     * @test
     */
    public function testnumberFormatLocaleFormatsWithDefaultLocale(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = number_format_locale(1234567.89);

        $this->assertIsString($result);
        $this->assertStringContainsString('1,234,567.89', $result);
    }

    /**
     * @test
     */
    public function testnumberFormatLocaleFormatsWithUsLocale(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = number_format_locale(1234567.89, 2, 'en_US');

        $this->assertIsString($result);
        $this->assertStringContainsString('1,234,567.89', $result);
    }

    /**
     * @test
     */
    public function testnumberFormatLocaleRespectsDecimalPlaces(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = number_format_locale(1234.5678, 0, 'en_US');

        $this->assertIsString($result);
        $this->assertEquals('1,235', $result);
    }

    // =========================================================================
    // percent_format() tests
    // =========================================================================

    /**
     * @test
     */
    public function testpercentFormatFormatsPercentage(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = percent_format(0.1534);

        $this->assertIsString($result);
        // Different locales may format percentage differently
        $this->assertMatchesRegularExpression('/15[,.]34\s*%/', $result);
    }

    /**
     * @test
     */
    public function testpercentFormatRespectsDecimalPlaces(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $result = percent_format(0.1534, 1, 'en_US');

        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/15[,.]3\s*%/', $result);
    }

    // =========================================================================
    // bytes_format() tests
    // =========================================================================

    /**
     * @test
     */
    public function testbytesFormatFormatsBytes(): void
    {
        $this->assertEquals('0 B', bytes_format(0));
        $this->assertEquals('500 B', bytes_format(500));
    }

    /**
     * @test
     */
    public function testbytesFormatFormatsKilobytes(): void
    {
        $this->assertEquals('1 KB', bytes_format(1024));
        $this->assertEquals('1.5 KB', bytes_format(1536));
    }

    /**
     * @test
     */
    public function testbytesFormatFormatsMegabytes(): void
    {
        $this->assertEquals('1 MB', bytes_format(1048576));
        $this->assertEquals('1.46 MB', bytes_format(1536000));
    }

    /**
     * @test
     */
    public function testbytesFormatFormatsGigabytes(): void
    {
        $this->assertEquals('1 GB', bytes_format(1073741824));
    }

    /**
     * @test
     */
    public function testbytesFormatRespectsPrecision(): void
    {
        $this->assertEquals('1.5 MB', bytes_format(1536000, 1));
        $this->assertEquals('1.465 MB', bytes_format(1536000, 3));
    }

    // =========================================================================
    // ordinal() tests
    // =========================================================================

    /**
     * @test
     */
    public function testordinalFormatsEnglishOrdinals(): void
    {
        $this->assertEquals('1st', ordinal(1));
        $this->assertEquals('2nd', ordinal(2));
        $this->assertEquals('3rd', ordinal(3));
        $this->assertEquals('4th', ordinal(4));
        $this->assertEquals('11th', ordinal(11));
        $this->assertEquals('12th', ordinal(12));
        $this->assertEquals('13th', ordinal(13));
        $this->assertEquals('21st', ordinal(21));
        $this->assertEquals('22nd', ordinal(22));
        $this->assertEquals('23rd', ordinal(23));
        $this->assertEquals('100th', ordinal(100));
        $this->assertEquals('101st', ordinal(101));
    }

    /**
     * @test
     */
    public function testordinalFormatsPortugueseOrdinals(): void
    {
        $this->assertEquals('1º', ordinal(1, 'pt'));
        $this->assertEquals('2º', ordinal(2, 'pt'));
        $this->assertEquals('10º', ordinal(10, 'pt'));
        $this->assertEquals('100º', ordinal(100, 'pt'));
    }

    // =========================================================================
    // truncate() tests
    // =========================================================================

    /**
     * @test
     */
    public function testtruncateTruncatesString(): void
    {
        $this->assertEquals('Hello...', truncate('Hello World', 8));
    }

    /**
     * @test
     */
    public function testtruncateReturnsOriginalIfShorter(): void
    {
        $this->assertEquals('Hello', truncate('Hello', 10));
    }

    /**
     * @test
     */
    public function testtruncateUsesCustomEllipsis(): void
    {
        $this->assertEquals('Hello…', truncate('Hello World', 6, '…'));
    }

    /**
     * @test
     */
    public function testtruncatePreservesWords(): void
    {
        $result = truncate('Hello World Example', 15, '...', true);
        $this->assertEquals('Hello World...', $result);
    }

    /**
     * @test
     */
    public function testtruncateHandlesMultibyteStrings(): void
    {
        $this->assertEquals('Olá...', truncate('Olá Mundo', 6));
    }

    // =========================================================================
    // slug() tests
    // =========================================================================

    /**
     * @test
     */
    public function testslugCreatesSlugFromString(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $this->assertEquals('hello-world', slug('Hello World'));
    }

    /**
     * @test
     */
    public function testslugHandlesAccentedCharacters(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $this->assertEquals('ola-mundo', slug('Olá Mundo!'));
    }

    /**
     * @test
     */
    public function testslugUsesCustomSeparator(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $this->assertEquals('hello_world', slug('Hello World', '_'));
    }

    /**
     * @test
     */
    public function testslugRemovesSpecialCharacters(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl extension not available');
        }

        $this->assertEquals('hello-world', slug('Hello! @World#'));
    }

    // =========================================================================
    // mask() tests
    // =========================================================================

    /**
     * @test
     */
    public function testmaskAppliesCpfMask(): void
    {
        $this->assertEquals('123.456.789-01', mask('12345678901', '###.###.###-##'));
    }

    /**
     * @test
     */
    public function testmaskAppliesCnpjMask(): void
    {
        $this->assertEquals('12.345.678/0001-99', mask('12345678000199', '##.###.###/####-##'));
    }

    /**
     * @test
     */
    public function testmaskAppliesPhoneMask(): void
    {
        $this->assertEquals('(11) 99999-8888', mask('11999998888', '(##) #####-####'));
    }

    /**
     * @test
     */
    public function testmaskAppliesCepMask(): void
    {
        $this->assertEquals('01234-567', mask('01234567', '#####-###'));
    }

    // =========================================================================
    // unmask() tests
    // =========================================================================

    /**
     * @test
     */
    public function testunmaskRemovesFormatting(): void
    {
        $this->assertEquals('12345678901', unmask('123.456.789-01'));
        $this->assertEquals('11999998888', unmask('(11) 99999-8888'));
        $this->assertEquals('01234567', unmask('01234-567'));
    }

    /**
     * @test
     */
    public function testunmaskKeepsLettersWhenSpecified(): void
    {
        $this->assertEquals('ABC123', unmask('ABC-123', true));
        $this->assertEquals('Test1234', unmask('Test 1234!', true));
    }

    /**
     * @test
     */
    public function testunmaskRemovesLettersByDefault(): void
    {
        $this->assertEquals('123', unmask('ABC-123'));
    }

    // =========================================================================
    // initials() tests
    // =========================================================================

    /**
     * @test
     */
    public function testinitialsExtractsInitials(): void
    {
        $this->assertEquals('JD', initials('John Doe'));
        $this->assertEquals('JMD', initials('John Michael Doe'));
    }

    /**
     * @test
     */
    public function testinitialsRespectsLimit(): void
    {
        // limit=2 takes first 2 initials (J, M from John Michael Doe)
        $this->assertEquals('JM', initials('John Michael Doe', 2));
        $this->assertEquals('J', initials('John Michael Doe', 1));
    }

    /**
     * @test
     */
    public function testinitialsHandlesSingleName(): void
    {
        $this->assertEquals('J', initials('John'));
    }

    /**
     * @test
     */
    public function testinitialsHandlesEmptyString(): void
    {
        $this->assertEquals('', initials(''));
        $this->assertEquals('', initials('   '));
    }

    // =========================================================================
    // human_time_diff() tests
    // =========================================================================

    /**
     * @test
     */
    public function testhumanTimeDiffFormatsSecondsAgo(): void
    {
        $result = human_time_diff(time() - 30);
        $this->assertEquals('30 seconds ago', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffFormatsMinutesAgo(): void
    {
        $result = human_time_diff(time() - 120);
        $this->assertEquals('2 minutes ago', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffFormatsHoursAgo(): void
    {
        $result = human_time_diff(time() - 3600);
        $this->assertEquals('1 hour ago', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffFormatsDaysAgo(): void
    {
        $result = human_time_diff(time() - 86400);
        $this->assertEquals('1 day ago', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffFormatsFuture(): void
    {
        $result = human_time_diff(time() + 86400);
        $this->assertEquals('in 1 day', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffFormatsPortuguese(): void
    {
        $result = human_time_diff(time() - 30, null, 'pt');
        $this->assertEquals('há 30 segundos', $result);

        $result = human_time_diff(time() + 3600, null, 'pt');
        $this->assertEquals('em 1 hora', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffHandlesDatetimeInterface(): void
    {
        $from = new \DateTime('-1 hour');
        $result = human_time_diff($from);
        $this->assertEquals('1 hour ago', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffHandlesStringDate(): void
    {
        $result = human_time_diff('-1 day');
        $this->assertEquals('1 day ago', $result);
    }

    /**
     * @test
     */
    public function testhumanTimeDiffReturnsJustNow(): void
    {
        $result = human_time_diff(time());
        $this->assertEquals('just now', $result);
    }
}
