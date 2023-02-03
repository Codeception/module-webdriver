<?php

use Codeception\Stub;
use Codeception\Stub\Expected;
use Codeception\Test\Metadata;
use Codeception\Util\Maybe;
use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Assert;

class WebDriverTest extends \Codeception\Test\Unit
{
    public const MODULE_CLASS = 'Codeception\Module\WebDriver';
    public const WEBDRIVER_CLASS = 'Facebook\WebDriver\Remote\RemoteWebDriver';

    public function testCreateTestScreenshotOnFail()
    {
        $test = Stub::make(
            \Codeception\Test\TestCaseWrapper::class,
            [
                'getSignature' => 'testLogin',
                'getMetadata' => new Metadata(),
            ]
        );
        $fakeWd = Stub::make(self::WEBDRIVER_CLASS, [
            'takeScreenshot' => Expected::once(function ($filename) use ($test) {
                Assert::assertSame(
                    codecept_log_dir(get_class($test) . '.testLogin.fail.png'),
                    $filename
                );
            }),
            'getPageSource' => Expected::once(function () {
            }),
            'manage' => Stub::make('\Facebook\WebDriver\WebDriverOptions', [
                'getAvailableLogTypes' => Expected::atLeastOnce(fn() => []),
            ]),
        ]);
        $module = Stub::make(self::MODULE_CLASS, ['webDriver' => $fakeWd]);
        $module->_failed($test, new \PHPUnit\Framework\AssertionFailedError());
    }



    public function testCreateCestScreenshotOnFail()
    {
        $fakeWd = Stub::make(self::WEBDRIVER_CLASS, [
            'takeScreenshot' => Expected::once(function ($filename) {
                Assert::assertSame(codecept_log_dir('stdClass.login.fail.png'), $filename);
            }),
            'getPageSource' => Expected::once(function () {
            }),
            'manage' => Stub::make('\Facebook\WebDriver\WebDriverOptions', [
                'getAvailableLogTypes' => Expected::atLeastOnce(fn() => []),
            ]),
        ]);
        $module = Stub::make(self::MODULE_CLASS, ['webDriver' => $fakeWd]);
        $cest = new \Codeception\Test\Cest(
            new class {
                public function login()
                {
                }
            },
            'login',
            'someCest.php',
        );
        $module->_failed($cest, new \PHPUnit\Framework\AssertionFailedError());
    }

    public function testWebDriverWaits()
    {
        $fakeWd = Stub::make(self::WEBDRIVER_CLASS, ['wait' => Expected::exactly(16, fn() => new Maybe())]);
        $module = Stub::make(self::MODULE_CLASS, ['webDriver' => $fakeWd]);
        $module->waitForElement(WebDriverBy::partialLinkText('yeah'));
        $module->waitForElement(['id' => 'user']);
        $module->waitForElement(['css' => '.user']);
        $module->waitForElement('//xpath');

        $module->waitForElementVisible(WebDriverBy::partialLinkText('yeah'));
        $module->waitForElementVisible(['id' => 'user']);
        $module->waitForElementVisible(['css' => '.user']);
        $module->waitForElementVisible('//xpath');

        $module->waitForElementNotVisible(WebDriverBy::partialLinkText('yeah'));
        $module->waitForElementNotVisible(['id' => 'user']);
        $module->waitForElementNotVisible(['css' => '.user']);
        $module->waitForElementNotVisible('//xpath');

        $module->waitForElementClickable(WebDriverBy::partialLinkText('yeah'));
        $module->waitForElementClickable(['id' => 'user']);
        $module->waitForElementClickable(['css' => '.user']);
        $module->waitForElementClickable('//xpath');
    }
}
