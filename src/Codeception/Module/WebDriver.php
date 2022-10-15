<?php

//phpcs:disable Generic.Files.LineLength.TooLong
declare(strict_types=1);

namespace Codeception\Module;

use Closure;
use Codeception\Coverage\Subscriber\LocalServer;
use Codeception\Exception\ConnectionException;
use Codeception\Exception\ElementNotFound;
use Codeception\Exception\MalformedLocatorException;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Exception\TestRuntimeException;
use Codeception\Lib\Interfaces\ConflictsWithModule;
use Codeception\Lib\Interfaces\ElementLocator;
use Codeception\Lib\Interfaces\MultiSession as MultiSessionInterface;
use Codeception\Lib\Interfaces\PageSourceSaver;
use Codeception\Lib\Interfaces\Remote as RemoteInterface;
use Codeception\Lib\Interfaces\RequiresPackage;
use Codeception\Lib\Interfaces\ScreenshotSaver;
use Codeception\Lib\Interfaces\SessionSnapshot;
use Codeception\Lib\Interfaces\Web as WebInterface;
use Codeception\Module as CodeceptionModule;
use Codeception\Constraint\Page as PageConstraint;
use Codeception\Constraint\WebDriver as WebDriverConstraint;
use Codeception\Constraint\WebDriverNot as WebDriverConstraintNot;
use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\ScenarioDriven;
use Codeception\TestInterface;
use Codeception\Util\ActionSequence;
use Codeception\Util\Locator;
use Codeception\Util\Uri;
use Exception;
use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\Cookie as WebDriverCookie;
use Facebook\WebDriver\Exception\InvalidElementStateException;
use Facebook\WebDriver\Exception\InvalidSelectorException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\UnknownErrorException;
use Facebook\WebDriver\Exception\WebDriverCurlException;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\LocalFileDetector;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\UselessFileDetector;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Facebook\WebDriver\WebDriver as WebDriverInterface;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverSearchContext;
use Facebook\WebDriver\WebDriverSelect;
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError as PHPUnitAssertionFailedError;
use PHPUnit\Framework\SelfDescribing;

/**
 * Run tests in real browsers using the W3C [WebDriver protocol](https://www.w3.org/TR/webdriver/).
 * There are multiple ways of running browser tests using WebDriver:
 *
 * ## Selenium (Recommended)
 *
 * * Java is required
 * * NodeJS is required
 *
 * The fastest way to get started is to [Install and launch Selenium using selenium-standalone NodeJS package](https://www.npmjs.com/package/selenium-standalone).
 *
 * Launch selenium standalone in separate console window:
 *
 * ```
 * selenium-standalone start
 * ```
 *
 * Update configuration in `acceptance.suite.yml`:
 *
 * ```yaml
 * modules:
 *    enabled:
 *       - WebDriver:
 *          url: 'http://localhost/'
 *          browser: chrome # 'chrome' or 'firefox'
 * ```
 *
 * ## Headless Chrome Browser
 *
 * To enable headless mode (launch tests without showing a window) for Chrome browser using Selenium use this config in `acceptance.suite.yml`:
 *
 * ```yaml
 * modules:
 *    enabled:
 *       - WebDriver:
 *          url: 'http://localhost/'
 *          browser: chrome
 *          capabilities:
 *             chromeOptions:
 *                args: ["--headless", "--disable-gpu"]
 * ```
 *
 * ## Headless Selenium in Docker
 *
 * Docker can ship Selenium Server with all its dependencies and browsers inside a single container.
 * Running tests inside Docker is as easy as pulling [official selenium image](https://github.com/SeleniumHQ/docker-selenium) and starting a container with Chrome:
 *
 * ```
 * docker run --net=host --shm-size 2g selenium/standalone-chrome
 * ```
 *
 * By using `--net=host` allow Selenium to access local websites.
 *
 * ## Local Chrome and/or Firefox
 *
 * Tests can be executed directly through ChromeDriver or GeckoDriver (for Firefox). Consider using this option if you don't plan to use Selenium.
 *
 * ### ChromeDriver
 *
 * * Download and install [ChromeDriver](https://sites.google.com/chromium.org/driver/downloads?authuser=0)
 * * Launch ChromeDriver in a separate console window: `chromedriver --url-base=/wd/hub`.
 *
 * Configuration in `acceptance.suite.yml`:
 *
 * ```yaml
 * modules:
 *    enabled:
 *       - WebDriver:
 *          browser: chrome
 *          url: 'http://localhost/'
 *          window_size: 2000x1000
 *          port: 9515
 *          capabilities:
 *              chromeOptions:
 *                  args: ["--headless", "--disable-gpu"] # Run Chrome in headless mode
 *                  prefs:
 *                      download.default_directory: "..."
 * ```
 * See here for additional [Chrome options](https://sites.google.com/a/chromium.org/chromedriver/capabilities)
 *
 *
 * ### GeckoDriver
 *
 * * [GeckoDriver](https://github.com/mozilla/geckodriver/releases) must be installed
 * * Start GeckoDriver in a separate console window: `geckodriver`.
 *
 * Configuration in `acceptance.suite.yml`:
 *
 * ```yaml
 * modules:
 *    enabled:
 *       - WebDriver:
 *          browser: firefox
 *          url: 'http://localhost/'
 *          window_size: 2000x1000
 *          path: ''
 *          capabilities:
 *              acceptInsecureCerts: true # allow self-signed certificates
 *              moz:firefoxOptions:
 *                  args: ["-headless"] # Run Firefox in headless mode
 *                  prefs:
 *                      intl.accept_languages: "de-AT" # Set HTTP-Header `Accept-Language: de-AT` for requests
 * ```
 * See here for [Firefox capabilities](https://developer.mozilla.org/en-US/docs/Web/WebDriver/Capabilities#List_of_capabilities)
 *
 * ## Cloud Testing
 *
 * Cloud Testing services can run your WebDriver tests in the cloud.
 * In case you want to test a local site or site behind a firewall
 * you should use a tunnel application provided by a service.
 *
 * ### SauceLabs
 *
 * 1. Create an account at [SauceLabs.com](https://saucelabs.com/) to get your username and access key
 * 2. In the module configuration use the format `username`:`access_key`@ondemand.saucelabs.com' for `host`
 * 3. Configure `platform` under `capabilities` to define the [Operating System](https://docs.saucelabs.com/basics/platform-configurator/)
 * 4. run a tunnel app if your site can't be accessed from Internet
 *
 * ```yaml
 *     modules:
 *        enabled:
 *           - WebDriver:
 *              url: http://mysite.com
 *              host: '<username>:<access key>@ondemand.saucelabs.com'
 *              port: 80
 *              browser: chrome
 *              capabilities:
 *                  platform: 'Windows 10'
 * ```
 *
 * ### BrowserStack
 *
 * 1. Create an account at [BrowserStack](https://www.browserstack.com/) to get your username and access key
 * 2. In the module configuration use the format `username`:`access_key`@hub.browserstack.com' for `host`
 * 3. Configure `os` and `os_version` under `capabilities` to define the operating System
 * 4. If your site is available only locally or via VPN you should use a tunnel app. In this case add `browserstack.local` capability and set it to true.
 *
 * ```yaml
 *     modules:
 *        enabled:
 *           - WebDriver:
 *              url: http://mysite.com
 *              host: '<username>:<access key>@hub.browserstack.com'
 *              port: 80
 *              browser: chrome
 *              capabilities:
 *                  os: Windows
 *                  os_version: 10
 *                  browserstack.local: true # for local testing
 * ```
 *
 * ### LambdaTest
 *
 * 1. Create an account at [LambdaTest](https://www.lambdatest.com) to get your username and access key
 * 2. In the module configuration use the format `username`:`access key`@hub.lambdatest.com' for `host`
 * 3. Configure `platformName`, 'browserVersion', and 'browserName' under `LT:Options` to define test environments.
 * 4. If your website is available only locally or via VPN you should use LambdaTest tunnel. In this case, you can add capability "tunnel":true;.
 *
 * ```yaml
 *    modules:
 *  enabled:
 *    - WebDriver:
              url: "https://openclassrooms.com"
              host: 'hub.lambdatest.com'
              port: 80
              browser: 'Chrome'
              capabilities:
                 LT:Options:
                  platformName: 'Windows 10'
                  browserVersion: 'latest-5'
                  browserName: 'Chrome'
                  tunnel: true #for Local testing
 * ```
 *
 * ### TestingBot
 *
 * 1. Create an account at [TestingBot](https://testingbot.com/) to get your key and secret
 * 2. In the module configuration use the format `key`:`secret`@hub.testingbot.com' for `host`
 * 3. Configure `platform` under `capabilities` to define the [Operating System](https://testingbot.com/support/getting-started/browsers.html)
 * 4. Run [TestingBot Tunnel](https://testingbot.com/support/other/tunnel) if your site can't be accessed from Internet
 *
 * ```yaml
 *     modules:
 *        enabled:
 *           - WebDriver:
 *              url: http://mysite.com
 *              host: '<key>:<secret>@hub.testingbot.com'
 *              port: 80
 *              browser: chrome
 *              capabilities:
 *                  platform: Windows 10
 * ```
 *
 * ## Configuration
 *
 * * `url` *required* - Base URL for your app (amOnPage opens URLs relative to this setting).
 * * `browser` *required* - Browser to launch.
 * * `host` - Selenium server host (127.0.0.1 by default).
 * * `port` - Selenium server port (4444 by default).
 * * `restart` - Set to `false` (default) to use the same browser window for all tests, or set to `true` to create a new window for each test. In any case, when all tests are finished the browser window is closed.
 * * `start` - Autostart a browser for tests. Can be disabled if browser session is started with `_initializeSession` inside a Helper.
 * * `window_size` - Initial window size. Set to `maximize` or a dimension in the format `640x480`.
 * * `clear_cookies` - Set to false to keep cookies, or set to true (default) to delete all cookies between tests.
 * * `wait` (default: 0 seconds) - Whenever element is required and is not on page, wait for n seconds to find it before fail.
 * * `capabilities` - Sets Selenium [desired capabilities](https://github.com/SeleniumHQ/selenium/wiki/DesiredCapabilities). Should be a key-value array.
 * * `connection_timeout` - timeout for opening a connection to remote selenium server (30 seconds by default).
 * * `request_timeout` - timeout for a request to return something from remote selenium server (30 seconds by default).
 * * `pageload_timeout` - amount of time to wait for a page load to complete before throwing an error (default 0 seconds).
 * * `http_proxy` - sets http proxy server url for testing a remote server.
 * * `http_proxy_port` - sets http proxy server port
 * * `ssl_proxy` - sets ssl(https) proxy server url for testing a remote server.
 * * `ssl_proxy_port` - sets ssl(https) proxy server port
 * * `debug_log_entries` - how many selenium entries to print with `debugWebDriverLogs` or on fail (0 by default).
 * * `log_js_errors` - Set to true to include possible JavaScript to HTML report, or set to false (default) to deactivate.
 * * `webdriver_proxy` - sets http proxy to tunnel requests to the remote Selenium WebDriver through
 * * `webdriver_proxy_port` - sets http proxy server port to tunnel requests to the remote Selenium WebDriver through
 *
 * Example (`acceptance.suite.yml`)
 *
 * ```yaml
 *     modules:
 *        enabled:
 *           - WebDriver:
 *              url: 'http://localhost/'
 *              browser: firefox
 *              window_size: 1024x768
 *              capabilities:
 *                  unexpectedAlertBehaviour: 'accept'
 *                  firefox_profile: '~/firefox-profiles/codeception-profile.zip.b64'
 * ```
 *
 * ## Loading Parts from other Modules
 *
 * While all Codeception modules are designed to work stand-alone, it's still possible to load *several* modules at once. To use e.g. the [Asserts module](https://codeception.com/docs/modules/Asserts) in your acceptance tests, just load it like this in your `acceptance.suite.yml`:
 *
 * ```yaml
 * modules:
 *     enabled:
 *         - WebDriver
 *         - Asserts
 * ```
 *
 * However, when loading a framework module (e.g. [Symfony](https://codeception.com/docs/modules/Symfony)) like this, it would lead to a conflict: When you call `$I->amOnPage()`, Codeception wouldn't know if you want to access the page using WebDriver's `amOnPage()`, or Symfony's `amOnPage()`. That's why possibly conflicting modules are separated into "parts". Here's how to load just the "services" part from e.g. Symfony:
 * ```yaml
 * modules:
 *     enabled:
 *         - WebDriver
 *         - Symfony:
 *             part: services
 * ```
 * To find out which parts each module has, look at the "Parts" header on the module's page.
 *
 * ## Usage
 *
 * ### Locating Elements
 *
 * Most methods in this module that operate on a DOM element (e.g. `click`) accept a locator as the first argument,
 * which can be either a string or an array.
 *
 * If the locator is an array, it should have a single element,
 * with the key signifying the locator type (`id`, `name`, `css`, `xpath`, `link`, or `class`)
 * and the value being the locator itself.
 * This is called a "strict" locator.
 * Examples:
 *
 * * `['id' => 'foo']` matches `<div id="foo">`
 * * `['name' => 'foo']` matches `<div name="foo">`
 * * `['css' => 'input[type=input][value=foo]']` matches `<input type="input" value="foo">`
 * * `['xpath' => "//input[@type='submit'][contains(@value, 'foo')]"]` matches `<input type="submit" value="foobar">`
 * * `['link' => 'Click here']` matches `<a href="google.com">Click here</a>`
 * * `['class' => 'foo']` matches `<div class="foo">`
 *
 * Writing good locators can be tricky.
 * The Mozilla team has written an excellent guide titled [Writing reliable locators for Selenium and WebDriver tests](https://blog.mozilla.org/webqa/2013/09/26/writing-reliable-locators-for-selenium-and-webdriver-tests/).
 *
 * If you prefer, you may also pass a string for the locator. This is called a "fuzzy" locator.
 * In this case, Codeception uses a a variety of heuristics (depending on the exact method called) to determine what element you're referring to.
 * For example, here's the heuristic used for the `submitForm` method:
 *
 * 1. Does the locator look like an ID selector (e.g. "#foo")? If so, try to find a form matching that ID.
 * 2. If nothing found, check if locator looks like a CSS selector. If so, run it.
 * 3. If nothing found, check if locator looks like an XPath expression. If so, run it.
 * 4. Throw an `ElementNotFound` exception.
 *
 * Be warned that fuzzy locators can be significantly slower than strict locators.
 * Especially if you use Selenium WebDriver with `wait` (aka implicit wait) option.
 * In the example above if you set `wait` to 5 seconds and use XPath string as fuzzy locator,
 * `submitForm` method will wait for 5 seconds at each step.
 * That means 5 seconds finding the form by ID, another 5 seconds finding by CSS
 * until it finally tries to find the form by XPath).
 * If speed is a concern, it's recommended you stick with explicitly specifying the locator type via the array syntax.
 *
 * ### Get Scenario Metadata
 *
 * You can inject `\Codeception\Scenario` into your test to get information about the current configuration:
 * ```php
 * use Codeception\Scenario
 * public function myTest(AcceptanceTester $I, Scenario $scenario)
 * {
 *     if ('firefox' === $scenario->current('browser')) {
 *         // ...
 *     }
 * }
 * ```
 * See [Get Scenario Metadata](https://codeception.com/docs/07-AdvancedUsage#Get-Scenario-Metadata) for more information on `$scenario`.
 *
 * ## Public Properties
 *
 * * `webDriver` - instance of `\Facebook\WebDriver\Remote\RemoteWebDriver`. Can be accessed from Helper classes for complex WebDriver interactions.
 *
 * ```php
 * // inside Helper class
 * $this->getModule('WebDriver')->webDriver->getKeyboard()->sendKeys('hello, webdriver');
 * ```
 *
 */
class WebDriver extends CodeceptionModule implements
    WebInterface,
    RemoteInterface,
    MultiSessionInterface,
    SessionSnapshot,
    ScreenshotSaver,
    PageSourceSaver,
    ElementLocator,
    ConflictsWithModule,
    RequiresPackage
{
    /**
     * @var string[]
     */
    protected array $requiredFields = ['browser', 'url'];

    protected array $config = [
        'protocol'             => 'http',
        'host'                 => '127.0.0.1',
        'port'                 => '4444',
        'path'                 => '/wd/hub',
        'start'                => true,
        'restart'              => false,
        'wait'                 => 0,
        'clear_cookies'        => true,
        'window_size'          => false,
        'capabilities'         => [],
        'connection_timeout'   => null,
        'request_timeout'      => null,
        'pageload_timeout'     => null,
        'http_proxy'           => null,
        'http_proxy_port'      => null,
        'ssl_proxy'            => null,
        'ssl_proxy_port'       => null,
        'debug_log_entries'    => 0,
        'log_js_errors'        => false,
        'webdriver_proxy'      => null,
        'webdriver_proxy_port' => null,
    ];

    protected ?string $wdHost = null;

    /**
     * @var mixed
     */
    protected $capabilities;

    /**
     * @var float|int|null
     */
    protected $connectionTimeoutInMs;

    /**
     * @var float|int|null
     */
    protected $requestTimeoutInMs;

    protected array $sessions = [];

    protected array $sessionSnapshots = [];

    /**
     * @var mixed
     */
    protected $webdriverProxy;

    /**
     * @var mixed
     */
    protected $webdriverProxyPort;

    public ?RemoteWebDriver $webDriver = null;

    protected ?WebDriverSearchContext $baseElement = null;

    public function _requires(): array
    {
        return [RemoteWebDriver::class => '"php-webdriver/webdriver": "^1.0.1"'];
    }

    /**
     * @throws ModuleException
     */
    protected function getBaseElement(): WebDriverSearchContext
    {
        if (!$this->baseElement) {
            throw new ModuleException(
                $this,
                "Page not loaded. Use `\$I->amOnPage` (or hidden API methods `_request` and `_loadPage`) to open it"
            );
        }

        return $this->baseElement;
    }

    public function _initialize()
    {
        $this->wdHost = sprintf(
            '%s://%s:%s%s',
            $this->config['protocol'],
            $this->config['host'],
            $this->config['port'],
            $this->config['path']
        );
        $this->capabilities = $this->config['capabilities'];
        $this->capabilities[WebDriverCapabilityType::BROWSER_NAME] = $this->config['browser'];
        if ($proxy = $this->getProxy()) {
            $this->capabilities[WebDriverCapabilityType::PROXY] = $proxy;
        }

        $this->connectionTimeoutInMs = $this->config['connection_timeout'] * 1000;
        $this->requestTimeoutInMs = $this->config['request_timeout'] * 1000;
        $this->webdriverProxy = $this->config['webdriver_proxy'];
        $this->webdriverProxyPort = $this->config['webdriver_proxy_port'];
        $this->loadFirefoxProfile();
    }

    /**
     * Change capabilities of WebDriver. Should be executed before starting a new browser session.
     * This method expects a function to be passed which returns array or [WebDriver Desired Capabilities](https://github.com/php-webdriver/php-webdriver/blob/main/lib/Remote/DesiredCapabilities.php) object.
     * Additional [Chrome options](https://github.com/php-webdriver/php-webdriver/wiki/ChromeOptions) (like adding extensions) can be passed as well.
     *
     * ```php
     * <?php // in helper
     * public function _before(TestInterface $test)
     * {
     *     $this->getModule('WebDriver')->_capabilities(function($currentCapabilities) {
     *         // or new \Facebook\WebDriver\Remote\DesiredCapabilities();
     *         return \Facebook\WebDriver\Remote\DesiredCapabilities::firefox();
     *     });
     * }
     * ```
     *
     * to make this work load `\Helper\Acceptance` before `WebDriver` in `acceptance.suite.yml`:
     *
     * ```yaml
     * modules:
     *     enabled:
     *         - \Helper\Acceptance
     *         - WebDriver
     * ```
     *
     * For instance, [**BrowserStack** cloud service](https://www.browserstack.com/automate/capabilities) may require a test name to be set in capabilities.
     * This is how it can be done via `_capabilities` method from `Helper\Acceptance`:
     *
     * ```php
     * <?php // inside Helper\Acceptance
     * public function _before(TestInterface $test)
     * {
     *      $name = $test->getMetadata()->getName();
     *      $this->getModule('WebDriver')->_capabilities(function($currentCapabilities) use ($name) {
     *          $currentCapabilities['name'] = $name;
     *          return $currentCapabilities;
     *      });
     * }
     * ```
     * In this case, please ensure that `\Helper\Acceptance` is loaded before WebDriver so new capabilities could be applied.
     *
     * @api
     */
    public function _capabilities(Closure $capabilityFunction): void
    {
        $this->capabilities = $capabilityFunction($this->capabilities);
    }

    public function _conflicts(): string
    {
        return WebInterface::class;
    }

    public function _before(TestInterface $test)
    {
        if ($this->webDriver === null && $this->config['start']) {
            $this->_initializeSession();
        }

        $this->setBaseElement();

        $test->getMetadata()->setCurrent(
            [
                'browser'      => $this->webDriver->getCapabilities()->getBrowserName(),
                'capabilities' => $this->webDriver->getCapabilities()->toArray(),
            ]
        );
    }

    /**
     * Restarts a web browser.
     * Can be used with `_reconfigure` to open browser with different configuration
     *
     * ```php
     * <?php
     * // inside a Helper
     * $this->getModule('WebDriver')->_restart(); // just restart
     * $this->getModule('WebDriver')->_restart(['browser' => $browser]); // reconfigure + restart
     * ```
     *
     * @api
     */
    public function _restart(array $config = []): void
    {
        $this->webDriver->quit();
        if (!empty($config)) {
            $this->_reconfigure($config);
        }

        $this->_initializeSession();
    }

    protected function onReconfigure()
    {
        $this->_initialize();
    }

    protected function loadFirefoxProfile(): void
    {
        if (!array_key_exists('firefox_profile', $this->config['capabilities'])) {
            return;
        }

        $firefox_profile = $this->config['capabilities']['firefox_profile'];
        if (!file_exists($firefox_profile)) {
            throw new ModuleConfigException(
                __CLASS__,
                "Firefox profile does not exist under given path " . $firefox_profile
            );
        }

        // Set firefox profile as capability
        $this->capabilities['firefox_profile'] = file_get_contents($firefox_profile);
    }

    protected function initialWindowSize(): void
    {
        if ($this->config['window_size'] == 'maximize') {
            $this->maximizeWindow();
            return;
        }

        $size = explode('x', (string) $this->config['window_size']);
        if (count($size) == 2) {
            $this->resizeWindow((int) $size[0], (int) $size[1]);
        }
    }

    public function _after(TestInterface $test)
    {
        if ($this->config['restart']) {
            $this->stopAllSessions();
            return;
        }

        if ($this->config['clear_cookies'] && $this->webDriver !== null) {
            try {
                $this->webDriver->manage()->deleteAllCookies();
            } catch (Exception $exception) {
                // may cause fatal errors when not handled
                $this->debug("Error, can't clean cookies after a test: " . $exception->getMessage());
            }
        }
    }

    public function _failed(TestInterface $test, $fail)
    {
        if (!$test instanceof SelfDescribing) {
            // this exception should never been throw because all existing test types implement SelfDescribing
            throw new InvalidArgumentException('Test class does not implement SelfDescribing interface');
        }
        $this->debugWebDriverLogs($test);
        $filename = preg_replace('#[^a-zA-Z0-9\x80-\xff]#', '.', Descriptor::getTestSignatureUnique($test));
        $outputDir = codecept_output_dir();
        $this->_saveScreenshot($report = $outputDir . mb_strcut($filename, 0, 245, 'utf-8') . '.fail.png');
        $test->getMetadata()->addReport('png', $report);
        $this->_savePageSource($report = $outputDir . mb_strcut($filename, 0, 244, 'utf-8') . '.fail.html');
        $test->getMetadata()->addReport('html', $report);
        $this->debug("Screenshot and page source were saved into '{$outputDir}' dir");
    }

    /**
     * Print out latest Selenium Logs in debug mode
     */
    public function debugWebDriverLogs(TestInterface $test = null): void
    {
        if ($this->webDriver === null) {
            $this->debug('WebDriver::debugWebDriverLogs method has been called when webDriver is not set');
            return;
        }

        // don't show logs if log entries not set
        if (!$this->config['debug_log_entries']) {
            return;
        }

        try {
            // Dump out latest Selenium logs
            $logs = $this->webDriver->manage()->getAvailableLogTypes();
            foreach ($logs as $logType) {
                $logEntries = array_slice(
                    $this->webDriver->manage()->getLog($logType),
                    -$this->config['debug_log_entries']
                );

                if (empty($logEntries)) {
                    $this->debugSection("Selenium {$logType} Logs", " EMPTY ");
                    continue;
                }

                $this->debugSection("Selenium {$logType} Logs", "\n" . $this->formatLogEntries($logEntries));

                if (
                    $logType === 'browser' && $this->config['log_js_errors']
                    && ($test instanceof ScenarioDriven)
                ) {
                    $this->logJSErrors($test, $logEntries);
                }
            }
        } catch (Exception $e) {
            $this->debug('Unable to retrieve Selenium logs : ' . $e->getMessage());
        }
    }

    /**
     * Turns an array of log entries into a human-readable string.
     * Each log entry is an array with the keys "timestamp", "level", and "message".
     * See https://code.google.com/p/selenium/wiki/JsonWireProtocol#Log_Entry_JSON_Object
     */
    protected function formatLogEntries(array $logEntries): string
    {
        $formattedLogs = '';

        foreach ($logEntries as $logEntry) {
            // Timestamp is in milliseconds, but date() requires seconds.
            $time = date('H:i:s', intval($logEntry['timestamp'] / 1000)) .
                // Append the milliseconds to the end of the time string
                '.' . ($logEntry['timestamp'] % 1000);
            $formattedLogs .= "{$time} {$logEntry['level']} - {$logEntry['message']}\n";
        }

        return $formattedLogs;
    }

    /**
     * Logs JavaScript errors as comments.
     */
    protected function logJSErrors(ScenarioDriven $test, array $browserLogEntries): void
    {
        foreach ($browserLogEntries as $logEntry) {
            if (
                isset($logEntry['level'])
                && isset($logEntry['message'])
                && $this->isJSError($logEntry['level'], $logEntry['message'])
            ) {
                // Timestamp is in milliseconds, but date() requires seconds.
                $time = date('H:i:s', intval($logEntry['timestamp'] / 1000)) .
                    // Append the milliseconds to the end of the time string
                    '.' . ($logEntry['timestamp'] % 1000);
                $test->getScenario()->comment("{$time} {$logEntry['level']} - {$logEntry['message']}");
            }
        }
    }

    /**
     * Determines if the log entry is an error.
     * The decision is made depending on browser and log-level.
     */
    protected function isJSError(string $logEntryLevel, string $message): bool
    {
        return
            (
                ($this->isPhantom() && $logEntryLevel != 'INFO')          // phantomjs logs errors as "WARNING"
                || $logEntryLevel === 'SEVERE'                            // other browsers log errors as "SEVERE"
            )
            && strpos($message, 'ERR_PROXY_CONNECTION_FAILED') === false;  // ignore blackhole proxy
    }

    public function _afterSuite()
    {
        // this is just to make sure webDriver is cleared after suite
        $this->stopAllSessions();
    }

    protected function stopAllSessions(): void
    {
        foreach ($this->sessions as $session) {
            $this->_closeSession($session);
        }

        $this->webDriver = null;
        $this->baseElement = null;
    }

    public function amOnSubdomain(string $subdomain): void
    {
        $url = $this->config['url'];
        $url = preg_replace('#(https?://)(.*\.)(.*\.)#', "$1$3", $url); // removing current subdomain
        $url = preg_replace('#(https?://)(.*)#', sprintf('$1%s.$2', $subdomain), $url); // inserting new
        $this->_reconfigure(['url' => $url]);
    }

    /**
     * Returns URL of a host.
     *
     * @api
     * @return mixed
     * @throws ModuleConfigException
     */
    public function _getUrl()
    {
        if (!isset($this->config['url'])) {
            throw new ModuleConfigException(
                __CLASS__,
                "Module connection failure. The URL for client can't bre retrieved"
            );
        }

        return $this->config['url'];
    }

    protected function getProxy(): ?array
    {
        $proxyConfig = [];
        if ($this->config['http_proxy']) {
            $proxyConfig['httpProxy'] = $this->config['http_proxy'];
            if ($this->config['http_proxy_port']) {
                $proxyConfig['httpProxy'] .= ':' . $this->config['http_proxy_port'];
            }
        }

        if ($this->config['ssl_proxy']) {
            $proxyConfig['sslProxy'] = $this->config['ssl_proxy'];
            if ($this->config['ssl_proxy_port']) {
                $proxyConfig['sslProxy'] .= ':' . $this->config['ssl_proxy_port'];
            }
        }

        if (!empty($proxyConfig)) {
            $proxyConfig['proxyType'] = 'manual';
            return $proxyConfig;
        }

        return null;
    }

    /**
     * Uri of currently opened page.
     * @api
     * @throws ModuleException
     */
    public function _getCurrentUri(): string
    {
        $url = $this->webDriver->getCurrentURL();
        if ($url == 'about:blank' || strpos($url, 'data:') === 0) {
            throw new ModuleException($this, 'Current url is blank, no page was opened');
        }

        return Uri::retrieveUri($url);
    }

    public function _saveScreenshot(string $filename)
    {
        if ($this->webDriver === null) {
            $this->debug('WebDriver::_saveScreenshot method has been called when webDriver is not set');
            return;
        }

        try {
            $this->webDriver->takeScreenshot($filename);
        } catch (Exception $e) {
            $this->debug('Unable to retrieve screenshot from Selenium : ' . $e->getMessage());
            return;
        }
    }

    /**
     * @param string|array|WebDriverBy $selector
     */
    public function _saveElementScreenshot($selector, string $filename): void
    {
        if ($this->webDriver === null) {
            $this->debug('WebDriver::_saveElementScreenshot method has been called when webDriver is not set');
            return;
        }

        try {
            $this->matchFirstOrFail($this->webDriver, $selector)->takeElementScreenshot($filename);
        } catch (Exception $e) {
            $this->debug('Unable to retrieve element screenshot from Selenium : ' . $e->getMessage());
            return;
        }
    }

    public function _findElements($locator): array
    {
        return $this->match($this->webDriver, $locator);
    }

    /**
     * Saves HTML source of a page to a file
     */
    public function _savePageSource(string $filename): void
    {
        if ($this->webDriver === null) {
            $this->debug('WebDriver::_savePageSource method has been called when webDriver is not set');
            return;
        }

        try {
            file_put_contents($filename, $this->webDriver->getPageSource());
        } catch (Exception $e) {
            $this->debug('Unable to retrieve source page from Selenium : ' . $e->getMessage());
        }
    }

    /**
     * Takes a screenshot of the current window and saves it to `tests/_output/debug`.
     *
     * ``` php
     * <?php
     * $I->amOnPage('/user/edit');
     * $I->makeScreenshot('edit_page');
     * // saved to: tests/_output/debug/edit_page.png
     * $I->makeScreenshot();
     * // saved to: tests/_output/debug/2017-05-26_14-24-11_4b3403665fea6.png
     * ```
     */
    public function makeScreenshot(string $name = null): void
    {
        if (empty($name)) {
            $name = uniqid(date("Y-m-d_H-i-s_"));
        }

        $debugDir = codecept_log_dir() . 'debug';
        if (!is_dir($debugDir)) {
            mkdir($debugDir);
        }

        $screenName = $debugDir . DIRECTORY_SEPARATOR . $name . '.png';
        $this->_saveScreenshot($screenName);
        $this->debugSection('Screenshot Saved', "file://{$screenName}");
    }

    /**
     * Takes a screenshot of an element of the current window and saves it to `tests/_output/debug`.
     *
     * ``` php
     * <?php
     * $I->amOnPage('/user/edit');
     * $I->makeElementScreenshot('#dialog', 'edit_page');
     * // saved to: tests/_output/debug/edit_page.png
     * $I->makeElementScreenshot('#dialog');
     * // saved to: tests/_output/debug/2017-05-26_14-24-11_4b3403665fea6.png
     * ```
     *
     * @param WebDriverBy|array $selector
     */
    public function makeElementScreenshot($selector, string $name = null): void
    {
        if (empty($name)) {
            $name = uniqid(date("Y-m-d_H-i-s_"));
        }

        $debugDir = codecept_log_dir() . 'debug';
        if (!is_dir($debugDir)) {
            mkdir($debugDir);
        }

        $screenName = $debugDir . DIRECTORY_SEPARATOR . $name . '.png';
        $this->_saveElementScreenshot($selector, $screenName);
        $this->debugSection('Screenshot Saved', "file://{$screenName}");
    }

    public function makeHtmlSnapshot(string $name = null): void
    {
        if (empty($name)) {
            $name = uniqid(date("Y-m-d_H-i-s_"));
        }

        $debugDir = codecept_output_dir() . 'debug';
        if (!is_dir($debugDir)) {
            mkdir($debugDir);
        }

        $fileName = $debugDir . DIRECTORY_SEPARATOR . $name . '.html';

        $this->_savePageSource($fileName);
        $this->debugSection('Snapshot Saved', "file://{$fileName}");
    }



    /**
     * Resize the current window.
     *
     * ``` php
     * <?php
     * $I->resizeWindow(800, 600);
     *
     * ```
     */
    public function resizeWindow(int $width, int $height): void
    {
        $this->webDriver->manage()->window()->setSize(new WebDriverDimension($width, $height));
    }

    private function debugCookies(): void
    {
        $result = [];
        $cookies = $this->webDriver->manage()->getCookies();
        foreach ($cookies as $cookie) {
            $result[] = $cookie->toArray();
        }

        $this->debugSection('Cookies', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function seeCookie($cookie, array $params = [], bool $showDebug = true): void
    {
        $cookies = $this->filterCookies($this->webDriver->manage()->getCookies(), $params);
        $cookies = array_map(
            fn($c) => $c['name'],
            $cookies
        );
        if ($showDebug) {
            $this->debugCookies();
        }
        $this->assertContains($cookie, $cookies);
    }

    public function dontSeeCookie($cookie, array $params = [], bool $showDebug = true): void
    {
        $cookies = $this->filterCookies($this->webDriver->manage()->getCookies(), $params);
        $cookies = array_map(
            fn($c) => $c['name'],
            $cookies
        );
        if ($showDebug) {
            $this->debugCookies();
        }
        $this->assertNotContains($cookie, $cookies);
    }

    public function setCookie($name, $value, array $params = [], $showDebug = true): void
    {
        $params['name'] = $name;
        $params['value'] = $value;
        if (isset($params['expires'])) { // PhpBrowser compatibility
            $params['expiry'] = $params['expires'];
        }

        // #5401 Supply defaults, otherwise chromedriver 2.46 complains.
        $defaults = [
            'path' => '/',
            'expiry' => time() + 86400,
            'secure' => false,
            'httpOnly' => false,
        ];
        foreach ($defaults as $key => $default) {
            if (empty($params[$key])) {
                $params[$key] = $default;
            }
        }

        $this->webDriver->manage()->addCookie($params);
        if ($showDebug) {
            $this->debugCookies();
        }
    }

    public function resetCookie($cookie, array $params = [], bool $showDebug = true): void
    {
        $this->webDriver->manage()->deleteCookieNamed($cookie);
        if ($showDebug) {
            $this->debugCookies();
        }
    }

    public function grabCookie($cookie, array $params = []): mixed
    {
        $params['name'] = $cookie;
        $cookies = $this->filterCookies($this->webDriver->manage()->getCookies(), $params);
        if (empty($cookies)) {
            return null;
        }

        $cookie = reset($cookies);
        return $cookie['value'];
    }

    /**
     * Grabs current page source code.
     *
     * @throws ModuleException if no page was opened.
     * @return string Current page source code.
     */
    public function grabPageSource(): string
    {
        // Make sure that some page was opened.
        $this->_getCurrentUri();

        return $this->webDriver->getPageSource();
    }

    /**
     * @param Cookie[] $cookies
     * @param array<string, string> $params
     * @return Cookie[]
     */
    protected function filterCookies(array $cookies, array $params = []): array
    {
        foreach (['domain', 'path', 'name'] as $filter) {
            if (!isset($params[$filter])) {
                continue;
            }

            $cookies = array_filter(
                $cookies,
                fn($item): bool => $item[$filter] == $params[$filter]
            );
        }

        return $cookies;
    }

    public function amOnUrl($url): void
    {
        $host = Uri::retrieveHost($url);
        $this->_reconfigure(['url' => $host]);
        $this->debugSection('Host', $host);
        $this->webDriver->get($url);
    }

    public function amOnPage($page): void
    {
        $url = Uri::appendPath($this->config['url'], $page);
        $this->debugSection('GET', $url);
        $this->webDriver->get($url);
    }

    public function see($text, $selector = null): void
    {
        if (!$selector) {
            $this->assertPageContains($text);
            return;
        }

        $this->enableImplicitWait();
        $nodes = $this->matchVisible($selector);
        $this->disableImplicitWait();
        $this->assertNodesContain($text, $nodes, $selector);
    }

    public function dontSee($text, $selector = null): void
    {
        if (!$selector) {
            $this->assertPageNotContains($text);
        } else {
            $nodes = $this->matchVisible($selector);
            $this->assertNodesNotContain($text, $nodes, $selector);
        }
    }

    public function seeInSource($raw): void
    {
        $this->assertPageSourceContains($raw);
    }

    public function dontSeeInSource($raw): void
    {
        $this->assertPageSourceNotContains($raw);
    }

    /**
     * Checks that the page source contains the given string.
     *
     * ```php
     * <?php
     * $I->seeInPageSource('<link rel="apple-touch-icon"');
     * ```
     */
    public function seeInPageSource(string $text): void
    {
        $this->assertThat(
            $this->webDriver->getPageSource(),
            new PageConstraint($text, $this->_getCurrentUri())
        );
    }

    /**
     * Checks that the page source doesn't contain the given string.
     */
    public function dontSeeInPageSource(string $text): void
    {
        $this->assertThatItsNot(
            $this->webDriver->getPageSource(),
            new PageConstraint($text, $this->_getCurrentUri())
        );
    }

    public function click($link, $context = null): void
    {
        $page = $this->webDriver;
        if ($context) {
            $page = $this->matchFirstOrFail($this->webDriver, $context);
        }

        $el = $this->_findClickable($page, $link);
        if ($el === null) { // check one more time if this was a CSS selector we didn't match
            try {
                $els = $this->match($page, $link);
            } catch (MalformedLocatorException $exception) {
                throw new ElementNotFound(
                    "name={$link}",
                    "'{$link}' is invalid CSS and XPath selector and Link or Button"
                );
            }

            $el = reset($els);
        }

        if (!$el) {
            throw new ElementNotFound($link, 'Link or Button or CSS or XPath');
        }

        $el->click();
    }

    /**
     * Locates a clickable element.
     *
     * Use it in Helpers or GroupObject or Extension classes:
     *
     * ```php
     * <?php
     * $module = $this->getModule('WebDriver');
     * $page = $module->webDriver;
     *
     * // search a link or button on a page
     * $el = $module->_findClickable($page, 'Click Me');
     *
     * // search a link or button within an element
     * $topBar = $module->_findElements('.top-bar')[0];
     * $el = $module->_findClickable($topBar, 'Click Me');
     *
     * ```
     * @param WebDriverSearchContext $page WebDriver instance or an element to search within
     * @param string|array|WebDriverBy $link A link text or locator to click
     * @api
     */
    public function _findClickable(WebDriverSearchContext $page, $link): ?WebDriverElement
    {
        if (is_array($link) || $link instanceof WebDriverBy) {
            return $this->matchFirstOrFail($page, $link);
        }

        // try to match by strict locators, CSS Ids or XPath
        if (Locator::isPrecise($link)) {
            return $this->matchFirstOrFail($page, $link);
        }

        $locator = self::xPathLiteral(trim((string) $link));

        // narrow
        $xpath = Locator::combine(
            ".//a[normalize-space(.)={$locator}]",
            ".//button[normalize-space(.)={$locator}]",
            ".//a/img[normalize-space(@alt)={$locator}]/ancestor::a",
            ".//input[./@type = 'submit' or ./@type = 'image' or ./@type = 'button'][normalize-space(@value)={$locator}]"
        );

        $els = $page->findElements(WebDriverBy::xpath($xpath));
        if (count($els) > 0) {
            return reset($els);
        }

        // wide
        $xpath = Locator::combine(
            ".//a[./@href][((contains(normalize-space(string(.)), {$locator})) or contains(./@title, {$locator}) or .//img[contains(./@alt, {$locator})])]",
            ".//input[./@type = 'submit' or ./@type = 'image' or ./@type = 'button'][contains(./@value, {$locator})]",
            ".//input[./@type = 'image'][contains(./@alt, {$locator})]",
            ".//button[contains(normalize-space(string(.)), {$locator})]",
            ".//input[./@type = 'submit' or ./@type = 'image' or ./@type = 'button'][./@name = {$locator} or ./@title = {$locator}]",
            ".//button[./@name = {$locator} or ./@title = {$locator}]"
        );
        $els = $page->findElements(WebDriverBy::xpath($xpath));
        if (count($els) > 0) {
            return reset($els);
        }

        return null;
    }

    /**
     * @param WebDriverElement|WebDriverBy|array|string $selector
     * @return WebDriverElement[]
     * @throws ElementNotFound
     */
    protected function findFields($selector): array
    {
        if ($selector instanceof WebDriverElement) {
            return [$selector];
        }

        if (is_array($selector) || ($selector instanceof WebDriverBy)) {
            $fields = $this->match($this->getBaseElement(), $selector);

            if (empty($fields)) {
                throw new ElementNotFound($selector);
            }

            return $fields;
        }

        $locator = self::xPathLiteral(trim((string) $selector));
        // by text or label
        $xpath = Locator::combine(
            ".//*[self::input | self::textarea | self::select][not(./@type = 'submit' or ./@type = 'image' or ./@type = 'hidden')][(((./@name = {$locator}) or ./@id = //label[contains(normalize-space(string(.)), {$locator})]/@for) or ./@placeholder = {$locator})]",
            ".//label[contains(normalize-space(string(.)), {$locator})]//.//*[self::input | self::textarea | self::select][not(./@type = 'submit' or ./@type = 'image' or ./@type = 'hidden')]"
        );
        $fields = $this->getBaseElement()->findElements(WebDriverBy::xpath($xpath));
        if (!empty($fields)) {
            return $fields;
        }

        // by name
        $xpath = ".//*[self::input | self::textarea | self::select][@name = {$locator}]";
        $fields = $this->getBaseElement()->findElements(WebDriverBy::xpath($xpath));
        if (!empty($fields)) {
            return $fields;
        }

        // try to match by CSS or XPath
        $fields = $this->match($this->getBaseElement(), $selector, false);
        if (!empty($fields)) {
            return $fields;
        }

        throw new ElementNotFound($selector, "Field by name, label, CSS or XPath");
    }

    /**
     * @param string|array|WebDriverBy|WebDriverElement $selector
     * @throws ElementNotFound
     */
    protected function findField($selector): WebDriverElement
    {
        $arr = $this->findFields($selector);
        return reset($arr);
    }

    public function seeLink(string $text, string $url = null): void
    {
        $this->enableImplicitWait();
        $nodes = $this->getBaseElement()->findElements(WebDriverBy::partialLinkText($text));
        $this->disableImplicitWait();
        $currentUri = $this->_getCurrentUri();

        if (empty($nodes)) {
            $this->fail("No links containing text '{$text}' were found in page {$currentUri}");
        }

        if ($url) {
            $nodes = $this->filterNodesByHref($url, $nodes);
        }

        $this->assertNotEmpty(
            $nodes,
            "No links containing text '{$text}' and URL '{$url}' were found in page {$currentUri}"
        );
    }

    public function dontSeeLink(string $text, string $url = ''): void
    {
        $nodes = $this->getBaseElement()->findElements(WebDriverBy::partialLinkText($text));
        $currentUri = $this->_getCurrentUri();
        if (!$url) {
            $this->assertEmpty($nodes, "Link containing text '{$text}' was found in page {$currentUri}");
        } else {
            $nodes = $this->filterNodesByHref($url, $nodes);
            $this->assertEmpty(
                $nodes,
                "Link containing text '{$text}' and URL '{$url}' was found in page {$currentUri}"
            );
        }
    }

    private function filterNodesByHref(string $url, array $nodes): array
    {
        //current uri can be relative, merging it with configured base url gives absolute url
        $absoluteCurrentUrl = Uri::mergeUrls($this->_getUrl(), $this->_getCurrentUri());
        $expectedUrl = Uri::mergeUrls($absoluteCurrentUrl, $url);
        return array_filter(
            $nodes,
            function (WebDriverElement $e) use ($expectedUrl, $absoluteCurrentUrl): bool {
                $elementHref = Uri::mergeUrls($absoluteCurrentUrl, $e->getAttribute('href'));
                return $elementHref === $expectedUrl;
            }
        );
    }

    public function seeInCurrentUrl(string $uri): void
    {
        $this->assertStringContainsString($uri, $this->_getCurrentUri());
    }

    public function seeCurrentUrlEquals(string $uri): void
    {
        $this->assertEquals($uri, $this->_getCurrentUri());
    }

    public function seeCurrentUrlMatches(string $uri): void
    {
        $this->assertRegExp($uri, $this->_getCurrentUri());
    }

    public function dontSeeInCurrentUrl(string $uri): void
    {
        $this->assertStringNotContainsString($uri, $this->_getCurrentUri());
    }

    public function dontSeeCurrentUrlEquals(string $uri): void
    {
        $this->assertNotEquals($uri, $this->_getCurrentUri());
    }

    public function dontSeeCurrentUrlMatches(string $uri): void
    {
        $this->assertNotRegExp($uri, $this->_getCurrentUri());
    }

    public function grabFromCurrentUrl($uri = null): mixed
    {
        if (!$uri) {
            return $this->_getCurrentUri();
        }

        $matches = [];
        $res = preg_match($uri, $this->_getCurrentUri(), $matches);
        if (!$res) {
            $this->fail("Couldn't match {$uri} in " . $this->_getCurrentUri());
        }

        if (!isset($matches[1])) {
            $this->fail("Nothing to grab. A regex parameter required. Ex: '/user/(\\d+)'");
        }

        return $matches[1];
    }

    public function seeCheckboxIsChecked($checkbox): void
    {
        $this->assertTrue($this->findField($checkbox)->isSelected());
    }

    public function dontSeeCheckboxIsChecked($checkbox): void
    {
        $this->assertFalse($this->findField($checkbox)->isSelected());
    }

    public function seeInField($field, $value): void
    {
        $els = $this->findFields($field);
        $this->assert($this->proceedSeeInField($els, $value));
    }

    public function dontSeeInField($field, $value): void
    {
        $els = $this->findFields($field);
        $this->assertNot($this->proceedSeeInField($els, $value));
    }

    public function seeInFormFields($formSelector, array $params): void
    {
        $this->proceedSeeInFormFields($formSelector, $params, false);
    }

    public function dontSeeInFormFields($formSelector, array $params): void
    {
        $this->proceedSeeInFormFields($formSelector, $params, true);
    }

    /**
     * @param string|array|WebDriverBy $formSelector
     * @throws ModuleException
     */
    protected function proceedSeeInFormFields($formSelector, array $params, bool $assertNot)
    {
        $form = $this->match($this->getBaseElement(), $formSelector);
        if (empty($form)) {
            throw new ElementNotFound($formSelector, "Form via CSS or XPath");
        }

        $form = reset($form);

        $els = [];
        foreach ($params as $name => $values) {
            $this->pushFormField($els, $form, $name, $values);
        }

        foreach ($els as $arrayElement) {
            [$el, $values] = $arrayElement;

            if (!is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $value) {
                $ret = $this->proceedSeeInField($el, $value);
                if ($assertNot) {
                    $this->assertNot($ret);
                } else {
                    $this->assert($ret);
                }
            }
        }
    }

    /**
     * Map an array element passed to seeInFormFields to its corresponding WebDriver element,
     * recursing through array values if the field is not found.
     *
     * @param array $els The previously found elements.
     * @param WebDriverElement $form The form in which to search for fields.
     * @param string $name The field's name.
     * @param mixed $values
     */
    protected function pushFormField(array &$els, WebDriverElement $form, string $name, $values): void
    {
        $el = $form->findElements(WebDriverBy::name($name));

        if ($el !== []) {
            $els[] = [$el, $values];
        } elseif (is_array($values)) {
            foreach ($values as $key => $value) {
                $this->pushFormField($els, $form, "{$name}[{$key}]", $value);
            }
        } else {
            throw new ElementNotFound($name);
        }
    }

    /**
     * @param WebDriverElement[] $elements
     * @param mixed $value
     */
    protected function proceedSeeInField(array $elements, $value): array
    {
        $strField = reset($elements)->getAttribute('name');
        if (reset($elements)->getTagName() === 'select') {
            $el = reset($elements);
            $elements = $el->findElements(WebDriverBy::xpath('.//option'));
            if (empty($value) && empty($elements)) {
                return ['True', true];
            }
        }

        $currentValues = [];
        if (is_bool($value)) {
            $currentValues = [false];
        }

        foreach ($elements as $el) {
            switch ($el->getTagName()) {
                case 'input':
                    if ($el->getAttribute('type') === 'radio' || $el->getAttribute('type') === 'checkbox') {
                        if ($el->getAttribute('checked')) {
                            if (is_bool($value)) {
                                $currentValues = [true];
                                break;
                            } else {
                                $currentValues[] = $el->getAttribute('value');
                            }
                        }
                    } else {
                        $currentValues[] = $el->getAttribute('value');
                    }

                    break;
                case 'option':
                    if (!$el->isSelected()) {
                        break;
                    }

                    $currentValues[] = $el->getText();
                    // no break we need the trim text and the value also
                case 'textarea':
                    $currentValues[] = trim($el->getText());
                    // we include trimmed and real value of textarea for check
                default:
                    $currentValues[] = $el->getAttribute('value'); // raw value
                    break;
            }
        }

        return [
            'Contains',
            $value,
            $currentValues,
            "Failed testing for '{$value}' in {$strField}'s value: '" . implode("', '", $currentValues) . "'"
        ];
    }

    public function selectOption($select, $option): void
    {
        $el = $this->findField($select);
        if ($el->getTagName() != 'select') {
            $els = $this->matchCheckables($select);
            $radio = null;
            foreach ($els as $el) {
                $radio = $this->findCheckable($el, $option, true);
                if ($radio) {
                    break;
                }
            }

            if (!$radio) {
                throw new ElementNotFound($select, "Radiobutton with value or name '{$option} in");
            }

            $radio->click();
            return;
        }

        $wdSelect = new WebDriverSelect($el);
        if ($wdSelect->isMultiple()) {
            $wdSelect->deselectAll();
        }

        if (!is_array($option)) {
            $option = [$option];
        }

        $matched = false;

        if (key($option) !== 'value') {
            foreach ($option as $opt) {
                try {
                    $wdSelect->selectByVisibleText($opt);
                    $matched = true;
                } catch (NoSuchElementException $exception) {
                }
            }
        }

        if ($matched) {
            return;
        }

        if (key($option) !== 'text') {
            foreach ($option as $opt) {
                try {
                    $wdSelect->selectByValue($opt);
                    $matched = true;
                } catch (NoSuchElementException $exception) {
                }
            }
        }

        if ($matched) {
            return;
        }

        // partially matching
        foreach ($option as $opt) {
            try {
                $optElement = $el->findElement(WebDriverBy::xpath('.//option [contains (., "' . $opt . '")]'));
                $matched = true;
                if (!$optElement->isSelected()) {
                    $optElement->click();
                }
            } catch (NoSuchElementException $exception) {
                // exception treated at the end
            }
        }

        if ($matched) {
            return;
        }

        throw new ElementNotFound(
            json_encode($option, JSON_THROW_ON_ERROR),
            "Option inside {$select} matched by name or value"
        );
    }

    /**
     * Manually starts a new browser session.
     *
     * ```php
     * <?php
     * $this->getModule('WebDriver')->_initializeSession();
     * ```
     *
     * @api
     */
    public function _initializeSession(): void
    {
        try {
            $this->sessions[] = $this->webDriver;
            $this->webDriver = RemoteWebDriver::create(
                $this->wdHost,
                $this->capabilities,
                $this->connectionTimeoutInMs,
                $this->requestTimeoutInMs,
                $this->webdriverProxy,
                $this->webdriverProxyPort
            );
            if (!is_null($this->config['pageload_timeout'])) {
                $this->webDriver->manage()->timeouts()->pageLoadTimeout($this->config['pageload_timeout']);
            }

            $this->setBaseElement();
            $this->initialWindowSize();
        } catch (WebDriverCurlException $exception) {
            codecept_debug('Curl error: ' . $exception->getMessage());
            throw new ConnectionException(
                "Can't connect to WebDriver at {$this->wdHost}."
                . ' Make sure that ChromeDriver, GeckoDriver or Selenium Server is running.'
            );
        }
    }

    /**
     * Loads current RemoteWebDriver instance as a session
     *
     * @param RemoteWebDriver $session
     * @api
     */
    public function _loadSession($session): void
    {
        $this->webDriver = $session;
        $this->setBaseElement();
    }

    /**
     * Returns current WebDriver session for saving
     *
     * @api
     */
    public function _backupSession(): WebDriverInterface
    {
        return $this->webDriver;
    }

    /**
     * Manually closes current WebDriver session.
     *
     * ```php
     * <?php
     * $this->getModule('WebDriver')->_closeSession();
     *
     * // close a specific session
     * $webDriver = $this->getModule('WebDriver')->webDriver;
     * $this->getModule('WebDriver')->_closeSession($webDriver);
     * ```
     *
     * @api
     * @param RemoteWebDriver|null $webDriver a specific webdriver session instance
     */
    public function _closeSession($webDriver = null): void
    {
        if (!$webDriver && $this->webDriver) {
            $webDriver = $this->webDriver;
        }

        if (!$webDriver) {
            return;
        }

        try {
            $webDriver->quit();
            unset($webDriver);
        } catch (UnknownErrorException $exception) {
            // Session already closed so nothing to do
        }
    }

    /**
     * Unselect an option in the given select box.
     *
     * @param string|array|WebDriverBy $select
     * @param string|array|WebDriverBy $option
     */
    public function unselectOption($select, $option): void
    {
        $el = $this->findField($select);

        $wdSelect = new WebDriverSelect($el);

        if (!is_array($option)) {
            $option = [$option];
        }

        $matched = false;

        foreach ($option as $opt) {
            try {
                $wdSelect->deselectByVisibleText($opt);
                $matched = true;
            } catch (NoSuchElementException $e) {
                // exception treated at the end
            }

            try {
                $wdSelect->deselectByValue($opt);
                $matched = true;
            } catch (NoSuchElementException $e) {
                // exception treated at the end
            }
        }

        if ($matched) {
            return;
        }

        throw new ElementNotFound(json_encode($option), "Option inside {$select} matched by name or value");
    }

    /**
     * @param string|array|WebDriverBy|WebDriverElement $radioOrCheckbox
     */
    protected function findCheckable(
        WebDriverSearchContext $context,
        $radioOrCheckbox,
        bool $byValue = false
    ): ?WebDriverElement {
        if ($radioOrCheckbox instanceof WebDriverElement) {
            return $radioOrCheckbox;
        }

        if (is_array($radioOrCheckbox) || $radioOrCheckbox instanceof WebDriverBy) {
            return $this->matchFirstOrFail($this->getBaseElement(), $radioOrCheckbox);
        }

        $locator = self::xPathLiteral($radioOrCheckbox);
        if ($context instanceof WebDriverElement && $context->getTagName() === 'input') {
            $contextType = $context->getAttribute('type');
            if (!in_array($contextType, ['checkbox', 'radio'], true)) {
                return null;
            }

            $nameLiteral = self::xPathLiteral($context->getAttribute('name'));
            $typeLiteral = self::xPathLiteral($contextType);
            $inputLocatorFragment = "input[@type = {$typeLiteral}][@name = {$nameLiteral}]";
            $xpath = Locator::combine(
                "ancestor::form//{$inputLocatorFragment}[(@id = ancestor::form//label[contains(normalize-space(string(.)), {$locator})]/@for) or @placeholder = {$locator}]",
                "ancestor::form//label[contains(normalize-space(string(.)), {$locator})]//{$inputLocatorFragment}"
            );
            if ($byValue) {
                $xpath = Locator::combine($xpath, "ancestor::form//{$inputLocatorFragment}[@value = {$locator}]");
            }
        } else {
            $xpath = Locator::combine(
                "//input[@type = 'checkbox' or @type = 'radio'][(@id = //label[contains(normalize-space(string(.)), {$locator})]/@for) or @placeholder = {$locator} or @name = {$locator}]",
                "//label[contains(normalize-space(string(.)), {$locator})]//input[@type = 'radio' or @type = 'checkbox']"
            );
            if ($byValue) {
                $xpath = Locator::combine(
                    $xpath,
                    sprintf("//input[@type = 'checkbox' or @type = 'radio'][@value = %s]", $locator)
                );
            }
        }

        $els = $context->findElements(WebDriverBy::xpath($xpath));
        if (count($els) > 0) {
            return reset($els);
        }

        $els = $context->findElements(WebDriverBy::xpath(str_replace('ancestor::form', '', $xpath)));
        if (count($els) > 0) {
            return reset($els);
        }

        $els = $this->match($context, $radioOrCheckbox);
        if (count($els) > 0) {
            return reset($els);
        }

        return null;
    }

    /**
     * @param string|array|WebDriverBy $selector
     * @return WebDriverElement[]
     */
    protected function matchCheckables($selector): array
    {
        $els = $this->match($this->webDriver, $selector);
        if ($els === []) {
            throw new ElementNotFound($selector, "Element containing radio by CSS or XPath");
        }

        return $els;
    }

    public function checkOption($option): void
    {
        $field = $this->findCheckable($this->webDriver, $option);
        if (!$field) {
            throw new ElementNotFound($option, "Checkbox or Radio by Label or CSS or XPath");
        }

        if ($field->isSelected()) {
            return;
        }

        $field->click();
    }

    public function uncheckOption($option): void
    {
        $field = $this->findCheckable($this->getBaseElement(), $option);
        if (!$field) {
            throw new ElementNotFound($option, "Checkbox by Label or CSS or XPath");
        }

        if (!$field->isSelected()) {
            return;
        }

        $field->click();
    }

    public function fillField($field, $value): void
    {
        $el = $this->findField($field);
        $el->clear();
        $el->sendKeys((string)$value);
    }

    /**
     * Clears given field which isn't empty.
     *
     * ``` php
     * <?php
     * $I->clearField('#username');
     * ```
     *
     * @param string|array|WebDriverBy $field
     */
    public function clearField($field): void
    {
        $el = $this->findField($field);
        $el->clear();
    }

    /**
     * Type in characters on active element.
     * With a second parameter you can specify delay between key presses.
     *
     * ```php
     * <?php
     * // activate input element
     * $I->click('#input');
     *
     * // type text in active element
     * $I->type('Hello world');
     *
     * // type text with a 1sec delay between chars
     * $I->type('Hello World', 1);
     * ```
     *
     * This might be useful when you an input reacts to typing and you need to slow it down to emulate human behavior.
     * For instance, this is how Credit Card fields can be filled in.
     *
     * @param int $delay [sec]
     */
    public function type(string $text, int $delay = 0): void
    {
        $keys = str_split($text);
        foreach ($keys as $key) {
            sleep($delay);
            $this->webDriver->getKeyboard()->pressKey($key);
        }

        sleep($delay);
    }

    public function attachFile($field, string $filename): void
    {
        $el = $this->findField($field);
        // in order to be compatible on different OS
        $filePath = codecept_data_dir() . $filename;
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File does not exist: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException("File is not readable: {$filePath}");
        }

        // in order for remote upload to be enabled
        $el->setFileDetector(new LocalFileDetector());

        // skip file detector for phantomjs
        if ($this->isPhantom()) {
            $el->setFileDetector(new UselessFileDetector());
        }

        $el->sendKeys(realpath($filePath));
    }

    /**
     * Grabs all visible text from the current page.
     */
    protected function getVisibleText(): ?string
    {
        if ($this->getBaseElement() instanceof RemoteWebElement) {
            return $this->getBaseElement()->getText();
        }

        $els = $this->getBaseElement()->findElements(WebDriverBy::cssSelector('body'));
        if (isset($els[0])) {
            return $els[0]->getText();
        }

        return '';
    }

    public function grabTextFrom($cssOrXPathOrRegex): mixed
    {
        $els = $this->match($this->getBaseElement(), $cssOrXPathOrRegex, false);
        if ($els !== []) {
            return $els[0]->getText();
        }

        if (
            is_string($cssOrXPathOrRegex)
            && @preg_match($cssOrXPathOrRegex, $this->webDriver->getPageSource(), $matches)
        ) {
            return $matches[1];
        }

        throw new ElementNotFound($cssOrXPathOrRegex, 'CSS or XPath or Regex');
    }

    public function grabAttributeFrom($cssOrXpath, $attribute): ?string
    {
        $el = $this->matchFirstOrFail($this->getBaseElement(), $cssOrXpath);
        return $el->getAttribute($attribute);
    }

    public function grabValueFrom($field): ?string
    {
        $el = $this->findField($field);
        // value of multiple select is the value of the first selected option
        if ($el->getTagName() == 'select') {
            $select = new WebDriverSelect($el);
            return $select->getFirstSelectedOption()->getAttribute('value');
        }

        return $el->getAttribute('value');
    }

    public function grabMultiple($cssOrXpath, $attribute = null): array
    {
        $els = $this->match($this->getBaseElement(), $cssOrXpath);
        return array_map(
            function (WebDriverElement $e) use ($attribute): ?string {
                if ($attribute) {
                    return $e->getAttribute($attribute);
                }

                return $e->getText();
            },
            $els
        );
    }

    protected function filterByAttributes($els, array $attributes)
    {
        foreach ($attributes as $attr => $value) {
            $els = array_filter(
                $els,
                fn(WebDriverElement $el): bool => $el->getAttribute($attr) == $value
            );
        }

        return $els;
    }

    public function seeElement($selector, array $attributes = []): void
    {
        $this->enableImplicitWait();
        $els = $this->matchVisible($selector);
        $this->disableImplicitWait();
        $els = $this->filterByAttributes($els, $attributes);
        $this->assertNotEmpty($els);
    }

    public function dontSeeElement($selector, array $attributes = []): void
    {
        $els = $this->matchVisible($selector);
        $els = $this->filterByAttributes($els, $attributes);
        $this->assertEmpty($els);
    }

    /**
     * Checks that the given element exists on the page, even it is invisible.
     *
     * ``` php
     * <?php
     * $I->seeElementInDOM('//form/input[type=hidden]');
     * ```
     *
     * @param string|array|WebDriverBy $selector
     */
    public function seeElementInDOM($selector, array $attributes = []): void
    {
        $this->enableImplicitWait();
        $els = $this->match($this->getBaseElement(), $selector);
        $els = $this->filterByAttributes($els, $attributes);
        $this->disableImplicitWait();
        $this->assertNotEmpty($els);
    }


    /**
     * Opposite of `seeElementInDOM`.
     *
     * @param string|array|WebDriverBy $selector
     */
    public function dontSeeElementInDOM($selector, array $attributes = []): void
    {
        $els = $this->match($this->getBaseElement(), $selector);
        $els = $this->filterByAttributes($els, $attributes);
        $this->assertEmpty($els);
    }

    public function seeNumberOfElements($selector, $expected): void
    {
        $counted = count($this->matchVisible($selector));
        if (is_array($expected)) {
            [$floor, $ceil] = $expected;
            $this->assertTrue(
                $floor <= $counted && $ceil >= $counted,
                'Number of elements counted differs from expected range'
            );
        } else {
            $this->assertSame(
                $expected,
                $counted,
                'Number of elements counted differs from expected number'
            );
        }
    }

    /**
     * @param string|array|WebDriverBy $selector
     * @param int|array $expected
     * @throws ModuleException
     */
    public function seeNumberOfElementsInDOM($selector, $expected)
    {
        $counted = count($this->match($this->getBaseElement(), $selector));
        if (is_array($expected)) {
            [$floor, $ceil] = $expected;
            $this->assertTrue(
                $floor <= $counted && $ceil >= $counted,
                'Number of elements counted differs from expected range'
            );
        } else {
            $this->assertSame(
                $expected,
                $counted,
                'Number of elements counted differs from expected number'
            );
        }
    }

    public function seeOptionIsSelected($selector, $optionText): void
    {
        $el = $this->findField($selector);
        if ($el->getTagName() !== 'select') {
            $els = $this->matchCheckables($selector);
            foreach ($els as $k => $el) {
                $els[$k] = $this->findCheckable($el, $optionText, true);
            }

            $this->assertNotEmpty(
                array_filter(
                    $els,
                    fn($e): bool => $e && $e->isSelected()
                )
            );
        } else {
            $select = new WebDriverSelect($el);
            $this->assertNodesContain($optionText, $select->getAllSelectedOptions(), 'option');
        }
    }

    public function dontSeeOptionIsSelected($selector, $optionText): void
    {
        $el = $this->findField($selector);
        if ($el->getTagName() !== 'select') {
            $els = $this->matchCheckables($selector);
            foreach ($els as $k => $el) {
                $els[$k] = $this->findCheckable($el, $optionText, true);
            }

            $this->assertEmpty(
                array_filter(
                    $els,
                    fn($e): bool => $e && $e->isSelected()
                )
            );
        } else {
            $select = new WebDriverSelect($el);
            $this->assertNodesNotContain($optionText, $select->getAllSelectedOptions(), 'option');
        }
    }

    public function seeInTitle($title)
    {
        $this->assertStringContainsString($title, $this->webDriver->getTitle());
    }

    public function dontSeeInTitle($title)
    {
        $this->assertStringNotContainsString($title, $this->webDriver->getTitle());
    }

    /**
     * Accepts the active JavaScript native popup window, as created by `window.alert`|`window.confirm`|`window.prompt`.
     * Don't confuse popups with modal windows,
     * as created by [various libraries](https://jster.net/category/windows-modals-popups).
     */
    public function acceptPopup(): void
    {
        if ($this->isPhantom()) {
            throw new ModuleException($this, 'PhantomJS does not support working with popups');
        }

        $this->webDriver->switchTo()->alert()->accept();
    }

    /**
     * Dismisses the active JavaScript popup, as created by `window.alert`, `window.confirm`, or `window.prompt`.
     */
    public function cancelPopup(): void
    {
        if ($this->isPhantom()) {
            throw new ModuleException($this, 'PhantomJS does not support working with popups');
        }

        $this->webDriver->switchTo()->alert()->dismiss();
    }

    /**
     * Checks that the active JavaScript popup,
     * as created by `window.alert`|`window.confirm`|`window.prompt`, contains the given string.
     *
     * @throws ModuleException
     */
    public function seeInPopup(string $text): void
    {
        if ($this->isPhantom()) {
            throw new ModuleException($this, 'PhantomJS does not support working with popups');
        }

        $alert = $this->webDriver->switchTo()->alert();
        try {
            $this->assertStringContainsString($text, $alert->getText());
        } catch (PHPUnitAssertionFailedError $failedError) {
            $alert->dismiss();
            throw $failedError;
        }
    }

    /**
     * Checks that the active JavaScript popup,
     * as created by `window.alert`|`window.confirm`|`window.prompt`, does NOT contain the given string.
     *
     * @throws ModuleException
     */
    public function dontSeeInPopup(string $text): void
    {
        if ($this->isPhantom()) {
            throw new ModuleException($this, 'PhantomJS does not support working with popups');
        }

        $alert = $this->webDriver->switchTo()->alert();
        try {
            $this->assertStringNotContainsString($text, $alert->getText());
        } catch (PHPUnitAssertionFailedError $e) {
            $alert->dismiss();
            throw $e;
        }
    }

    /**
     * Enters text into a native JavaScript prompt popup, as created by `window.prompt`.
     *
     * @throws ModuleException
     */
    public function typeInPopup(string $keys): void
    {
        if ($this->isPhantom()) {
            throw new ModuleException($this, 'PhantomJS does not support working with popups');
        }

        $this->webDriver->switchTo()->alert()->sendKeys($keys);
    }

    /**
     * Reloads the current page.
     */
    public function reloadPage(): void
    {
        $this->webDriver->navigate()->refresh();
    }

    /**
     * Moves back in history.
     */
    public function moveBack(): void
    {
        $this->webDriver->navigate()->back();
        $this->debug($this->_getCurrentUri());
    }

    /**
     * Moves forward in history.
     */
    public function moveForward(): void
    {
        $this->webDriver->navigate()->forward();
        $this->debug($this->_getCurrentUri());
    }

    protected function getSubmissionFormFieldName(string $name): string
    {
        if (substr($name, -2) === '[]') {
            return substr($name, 0, -2);
        }

        return $name;
    }

    /**
     * Submits the given form on the page, optionally with the given form
     * values.  Give the form fields values as an array. Note that hidden fields
     * can't be accessed.
     *
     * Skipped fields will be filled by their values from the page.
     * You don't need to click the 'Submit' button afterwards.
     * This command itself triggers the request to form's action.
     *
     * You can optionally specify what button's value to include
     * in the request with the last parameter as an alternative to
     * explicitly setting its value in the second parameter, as
     * button values are not otherwise included in the request.
     *
     * Examples:
     *
     * ``` php
     * <?php
     * $I->submitForm('#login', [
     *     'login' => 'davert',
     *     'password' => '123456'
     * ]);
     * // or
     * $I->submitForm('#login', [
     *     'login' => 'davert',
     *     'password' => '123456'
     * ], 'submitButtonName');
     *
     * ```
     *
     * For example, given this sample "Sign Up" form:
     *
     * ``` html
     * <form action="/sign_up">
     *     Login:
     *     <input type="text" name="user[login]" /><br/>
     *     Password:
     *     <input type="password" name="user[password]" /><br/>
     *     Do you agree to our terms?
     *     <input type="checkbox" name="user[agree]" /><br/>
     *     Select pricing plan:
     *     <select name="plan">
     *         <option value="1">Free</option>
     *         <option value="2" selected="selected">Paid</option>
     *     </select>
     *     <input type="submit" name="submitButton" value="Submit" />
     * </form>
     * ```
     *
     * You could write the following to submit it:
     *
     * ``` php
     * <?php
     * $I->submitForm(
     *     '#userForm',
     *     [
     *         'user[login]' => 'Davert',
     *         'user[password]' => '123456',
     *         'user[agree]' => true
     *     ],
     *     'submitButton'
     * );
     * ```
     * Note that "2" will be the submitted value for the "plan" field, as it is
     * the selected option.
     *
     * Also note that this differs from PhpBrowser, in that
     * ```'user' => [ 'login' => 'Davert' ]``` is not supported at the moment.
     * Named array keys *must* be included in the name as above.
     *
     * Pair this with seeInFormFields for quick testing magic.
     *
     * ``` php
     * <?php
     * $form = [
     *      'field1' => 'value',
     *      'field2' => 'another value',
     *      'checkbox1' => true,
     *      // ...
     * ];
     * $I->submitForm('//form[@id=my-form]', $form, 'submitButton');
     * // $I->amOnPage('/path/to/form-page') may be needed
     * $I->seeInFormFields('//form[@id=my-form]', $form);
     * ```
     *
     * Parameter values must be set to arrays for multiple input fields
     * of the same name, or multi-select combo boxes.  For checkboxes,
     * either the string value can be used, or boolean values which will
     * be replaced by the checkbox's value in the DOM.
     *
     * ``` php
     * <?php
     * $I->submitForm('#my-form', [
     *      'field1' => 'value',
     *      'checkbox' => [
     *          'value of first checkbox',
     *          'value of second checkbox',
     *      ],
     *      'otherCheckboxes' => [
     *          true,
     *          false,
     *          false,
     *      ],
     *      'multiselect' => [
     *          'first option value',
     *          'second option value',
     *      ]
     * ]);
     * ```
     *
     * Mixing string and boolean values for a checkbox's value is not supported
     * and may produce unexpected results.
     *
     * Field names ending in "[]" must be passed without the trailing square
     * bracket characters, and must contain an array for its value.  This allows
     * submitting multiple values with the same name, consider:
     *
     * ```php
     * $I->submitForm('#my-form', [
     *     'field[]' => 'value',
     *     'field[]' => 'another value', // 'field[]' is already a defined key
     * ]);
     * ```
     *
     * The solution is to pass an array value:
     *
     * ```php
     * // this way both values are submitted
     * $I->submitForm('#my-form', [
     *     'field' => [
     *         'value',
     *         'another value',
     *     ]
     * ]);
     * ```
     *
     * The `$button` parameter can be either a string, an array or an instance
     * of Facebook\WebDriver\WebDriverBy. When it is a string, the
     * button will be found by its "name" attribute. If $button is an
     * array then it will be treated as a strict selector and a WebDriverBy
     * will be used verbatim.
     *
     * For example, given the following HTML:
     *
     * ``` html
     * <input type="submit" name="submitButton" value="Submit" />
     * ```
     *
     * `$button` could be any one of the following:
     *   - 'submitButton'
     *   - ['name' => 'submitButton']
     *   - WebDriverBy::name('submitButton')
     *
     * @param string|array|WebDriverBy $selector
     * @param string|array|WebDriverBy|null $button
     */
    public function submitForm($selector, array $params, $button = null): void
    {
        $form = $this->matchFirstOrFail($this->getBaseElement(), $selector);

        $fields = $form->findElements(
            WebDriverBy::cssSelector(
                'input:enabled[name],textarea:enabled[name],select:enabled[name],input[type=hidden][name]'
            )
        );
        foreach ($fields as $field) {
            $fieldName = $this->getSubmissionFormFieldName($field->getAttribute('name') ?? '');
            if (!isset($params[$fieldName])) {
                continue;
            }

            $value = $params[$fieldName];
            if (is_array($value) && $field->getTagName() !== 'select') {
                if ($field->getAttribute('type') === 'checkbox' || $field->getAttribute('type') === 'radio') {
                    $found = false;
                    foreach ($value as $index => $val) {
                        if (!is_bool($val) && $val === $field->getAttribute('value')) {
                            array_splice($params[$fieldName], $index, 1);
                            $value = $val;
                            $found = true;
                            break;
                        }
                    }

                    if (!$found && !empty($value) && is_bool(reset($value))) {
                        $value = array_pop($params[$fieldName]);
                    }
                } else {
                    $value = array_pop($params[$fieldName]);
                }
            }

            if ($field->getAttribute('type') === 'checkbox' || $field->getAttribute('type') === 'radio') {
                if ($value === true || $value === $field->getAttribute('value')) {
                    $this->checkOption($field);
                } else {
                    $this->uncheckOption($field);
                }
            } elseif ($field->getAttribute('type') === 'button' || $field->getAttribute('type') === 'submit') {
                continue;
            } elseif ($field->getTagName() === 'select') {
                $this->selectOption($field, $value);
            } else {
                $this->fillField($field, $value);
            }
        }

        $this->debugSection(
            'Uri',
            $form->getAttribute('action') ? $form->getAttribute('action') : $this->_getCurrentUri()
        );
        $this->debugSection('Method', $form->getAttribute('method') ? $form->getAttribute('method') : 'GET');
        $this->debugSection('Parameters', json_encode($params, JSON_THROW_ON_ERROR));

        $submitted = false;
        if (!empty($button)) {
            if (is_array($button)) {
                $buttonSelector = $this->getStrictLocator($button);
            } elseif ($button instanceof WebDriverBy) {
                $buttonSelector = $button;
            } else {
                $buttonSelector = WebDriverBy::name($button);
            }

            $els = $form->findElements($buttonSelector);

            if (!empty($els)) {
                $el = reset($els);
                $el->click();
                $submitted = true;
            }
        }

        if (!$submitted) {
            $form->submit();
        }

        $this->debugSection('Page', $this->_getCurrentUri());
    }

    /**
     * Waits up to $timeout seconds for the given element to change.
     * Element "change" is determined by a callback function which is called repeatedly
     * until the return value evaluates to true.
     *
     * ``` php
     * <?php
     * use \Facebook\WebDriver\WebDriverElement
     * $I->waitForElementChange('#menu', function(WebDriverElement $el) {
     *     return $el->isDisplayed();
     * }, 100);
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @throws ElementNotFound
     */
    public function waitForElementChange($element, Closure $callback, int $timeout = 30): void
    {
        $el = $this->matchFirstOrFail($this->getBaseElement(), $element);
        $checker = fn() => $callback($el);
        $this->webDriver->wait($timeout)->until($checker);
    }

    /**
     * Waits up to $timeout seconds for an element to appear on the page.
     * If the element doesn't appear, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElement('#agree_button', 30); // secs
     * $I->click('#agree_button');
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function waitForElement($element, int $timeout = 10): void
    {
        $condition = WebDriverExpectedCondition::presenceOfElementLocated($this->getLocator($element));
        $this->webDriver->wait($timeout)->until($condition);
    }

    /**
     * Waits up to $timeout seconds for the given element to be visible on the page.
     * If element doesn't appear, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElementVisible('#agree_button', 30); // secs
     * $I->click('#agree_button');
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function waitForElementVisible($element, int $timeout = 10): void
    {
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($this->getLocator($element));
        $this->webDriver->wait($timeout)->until($condition);
    }

    /**
     * Waits up to $timeout seconds for the given element to become invisible.
     * If element stays visible, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElementNotVisible('#agree_button', 30); // secs
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function waitForElementNotVisible($element, int $timeout = 10): void
    {
        $condition = WebDriverExpectedCondition::invisibilityOfElementLocated($this->getLocator($element));
        $this->webDriver->wait($timeout)->until($condition);
    }

    /**
     * Waits up to $timeout seconds for the given element to be clickable.
     * If element doesn't become clickable, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElementClickable('#agree_button', 30); // secs
     * $I->click('#agree_button');
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function waitForElementClickable($element, int $timeout = 10): void
    {
        $condition = WebDriverExpectedCondition::elementToBeClickable($this->getLocator($element));
        $this->webDriver->wait($timeout)->until($condition);
    }

    /**
     * Waits up to $timeout seconds for the given string to appear on the page.
     *
     * Can also be passed a selector to search in, be as specific as possible when using selectors.
     * waitForText() will only watch the first instance of the matching selector / text provided.
     * If the given text doesn't appear, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForText('foo', 30); // secs
     * $I->waitForText('foo', 30, '.title'); // secs
     * ```
     *
     * @param int $timeout seconds
     * @param null|string|array|WebDriverBy $selector
     * @throws Exception
     */
    public function waitForText(string $text, int $timeout = 10, $selector = null): void
    {
        $message = sprintf(
            'Waited for %d secs but text %s still not found',
            $timeout,
            Locator::humanReadableString($text)
        );
        if (!$selector) {
            $condition = WebDriverExpectedCondition::elementTextContains(WebDriverBy::xpath('//body'), $text);
            $this->webDriver->wait($timeout)->until($condition, $message);
            return;
        }

        $condition = WebDriverExpectedCondition::elementTextContains($this->getLocator($selector), $text);
        $this->webDriver->wait($timeout)->until($condition, $message);
    }

    /**
     * Wait for $timeout seconds.
     *
     * @param int|float $timeout secs
     * @throws TestRuntimeException
     */
    public function wait($timeout): void
    {
        if ($timeout >= 1000) {
            throw new TestRuntimeException(
                "
                Waiting for more then 1000 seconds: 16.6667 mins\n
                Please note that wait method accepts number of seconds as parameter."
            );
        }

        usleep((int)($timeout * 1_000_000));
    }

    /**
     * Low-level API method.
     * If Codeception commands are not enough, this allows you to use Selenium WebDriver methods directly:
     *
     * ``` php
     * $I->executeInSelenium(function(\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
     *   $webdriver->get('https://google.com');
     * });
     * ```
     *
     * This runs in the context of the
     * [RemoteWebDriver class](https://github.com/php-webdriver/php-webdriver/blob/master/lib/remote/RemoteWebDriver.php).
     * Try not to use this command on a regular basis.
     * If Codeception lacks a feature you need, please implement it and submit a patch.
     *
     * @param Closure $function
     * @return mixed
     */
    public function executeInSelenium(Closure $function)
    {
        return $function($this->webDriver);
    }

    /**
     * Switch to another window identified by name.
     *
     * The window can only be identified by name. If the $name parameter is blank, the parent window will be used.
     *
     * Example:
     * ``` html
     * <input type="button" value="Open window" onclick="window.open('https://example.com', 'another_window')">
     * ```
     *
     * ``` php
     * <?php
     * $I->click("Open window");
     * # switch to another window
     * $I->switchToWindow("another_window");
     * # switch to parent window
     * $I->switchToWindow();
     * ```
     *
     * If the window has no name, match it by switching to next active tab using `switchToNextTab` method.
     *
     * Or use native Selenium functions to get access to all opened windows:
     *
     * ``` php
     * <?php
     * $I->executeInSelenium(function (\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
     *      $handles=$webdriver->getWindowHandles();
     *      $last_window = end($handles);
     *      $webdriver->switchTo()->window($last_window);
     * });
     * ```
     */
    public function switchToWindow(string $name = null): void
    {
        $this->webDriver->switchTo()->window($name);
    }

    /**
     * Switch to another iframe on the page.
     *
     * Example:
     * ``` html
     * <iframe name="another_frame" id="fr1" src="https://example.com">
     *
     * ```
     *
     * ``` php
     * <?php
     * # switch to iframe by name
     * $I->switchToIFrame("another_frame");
     * # switch to iframe by CSS or XPath
     * $I->switchToIFrame("#fr1");
     * # switch to parent page
     * $I->switchToIFrame();
     *
     * ```
     *
     * @param string|null $locator (name, CSS or XPath)
     */
    public function switchToIFrame(string $locator = null): void
    {
        $this->findAndSwitchToFrame($locator, 'iframe');
    }

    /**
     * Switch to another frame on the page.
     *
     * Example:
     * ``` html
     * <frame name="another_frame" id="fr1" src="https://example.com">
     *
     * ```
     *
     * ``` php
     * <?php
     * # switch to frame by name
     * $I->switchToFrame("another_frame");
     * # switch to frame by CSS or XPath
     * $I->switchToFrame("#fr1");
     * # switch to parent page
     * $I->switchToFrame();
     *
     * ```
     *
     * @param string|null $locator (name, CSS or XPath)
     */
    public function switchToFrame(string $locator = null): void
    {
        $this->findAndSwitchToFrame($locator);
    }

    private function findAndSwitchToFrame(string $locator = null, string $tag = 'frame'): void
    {
        if ($locator === null) {
            $this->webDriver->switchTo()->defaultContent();
            return;
        }

        $els = null;
        try {
            $els = $this->_findElements("{$tag}[name='{$locator}']");
        } catch (Exception $e) {
            $this->debug('Failed to find locator by name: ' . $e->getMessage());
        }

        if (!isset($els) || !is_array($els) || $els === []) {
            $this->debug(ucfirst($tag) . ' was not found by name, locating ' . $tag . ' by CSS or XPath');
            $els = $this->_findElements($locator);
        }

        if ($els === []) {
            throw new ElementNotFound($locator, ucfirst($tag));
        }

        $this->webDriver->switchTo()->frame($els[0]);
    }

    /**
     * Executes JavaScript and waits up to $timeout seconds for it to return true.
     *
     * In this example we will wait up to 60 seconds for all jQuery AJAX requests to finish.
     *
     * ``` php
     * <?php
     * $I->waitForJS("return $.active == 0;", 60);
     * ```
     *
     * @param int $timeout seconds
     */
    public function waitForJS(string $script, int $timeout = 5): void
    {
        $condition = fn($wd) => $wd->executeScript($script);
        $message = sprintf(
            "Waited for %d secs but script %s still doesn't evaluate to true",
            $timeout,
            Locator::humanReadableString($script)
        );
        $this->webDriver->wait($timeout)->until($condition, $message);
    }

    /**
     * Executes custom JavaScript.
     *
     * This example uses jQuery to get a value and assigns that value to a PHP variable:
     *
     * ```php
     * <?php
     * $myVar = $I->executeJS('return $("#myField").val()');
     *
     * // additional arguments can be passed as array
     * // Example shows `Hello World` alert:
     * $I->executeJS("window.alert(arguments[0])", ['Hello world']);
     * ```
     *
     * @param array $arguments
     * @return mixed
     */
    public function executeJS(string $script, array $arguments = [])
    {
        return $this->webDriver->executeScript($script, $arguments);
    }

    /**
     * Executes asynchronous JavaScript.
     * A callback should be executed by JavaScript to exit from a script.
     * Callback is passed as a last element in `arguments` array.
     * Additional arguments can be passed as array in second parameter.
     *
     * ```js
     * // wait for 1200 milliseconds my running `setTimeout`
     * * $I->executeAsyncJS('setTimeout(arguments[0], 1200)');
     *
     * $seconds = 1200; // or seconds are passed as argument
     * $I->executeAsyncJS('setTimeout(arguments[1], arguments[0])', [$seconds]);
     * ```
     *
     * @param array $arguments
     * @return mixed
     */
    public function executeAsyncJS(string $script, array $arguments = [])
    {
        return $this->webDriver->executeAsyncScript($script, $arguments);
    }

    /**
     * Maximizes the current window.
     */
    public function maximizeWindow(): void
    {
        $this->webDriver->manage()->window()->maximize();
    }

    /**
     * Performs a simple mouse drag-and-drop operation.
     *
     * ``` php
     * <?php
     * $I->dragAndDrop('#drag', '#drop');
     * ```
     *
     * @param string|array|WebDriverBy $source (CSS ID or XPath)
     * @param string|array|WebDriverBy $target (CSS ID or XPath)
     */
    public function dragAndDrop($source, $target): void
    {
        $sourceNodes = $this->matchFirstOrFail($this->getBaseElement(), $source);
        $targetNodes = $this->matchFirstOrFail($this->getBaseElement(), $target);

        $action = new WebDriverActions($this->webDriver);
        $action->dragAndDrop($sourceNodes, $targetNodes)->perform();
    }

    /**
     * Move mouse over the first element matched by the given locator.
     * If the first parameter null then the page is used.
     * If the second and third parameters are given,
     * then the mouse is moved to an offset of the element's top-left corner.
     * Otherwise, the mouse is moved to the center of the element.
     *
     * ``` php
     * <?php
     * $I->moveMouseOver(['css' => '.checkout']);
     * $I->moveMouseOver(null, 20, 50);
     * $I->moveMouseOver(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param null|string|array|WebDriverBy $cssOrXPath css or xpath of the web element
     * @throws ElementNotFound
     */
    public function moveMouseOver($cssOrXPath = null, int $offsetX = null, int $offsetY = null): void
    {
        $where = null;
        if (null !== $cssOrXPath) {
            $el = $this->matchFirstOrFail($this->getBaseElement(), $cssOrXPath);
            $where = $el->getCoordinates();
        }

        $this->webDriver->getMouse()->mouseMove($where, $offsetX, $offsetY);
    }

    /**
     * Performs click with the left mouse button on an element.
     * If the first parameter `null` then the offset is relative to the actual mouse position.
     * If the second and third parameters are given,
     * then the mouse is moved to an offset of the element's top-left corner.
     * Otherwise, the mouse is moved to the center of the element.
     *
     * ``` php
     * <?php
     * $I->clickWithLeftButton(['css' => '.checkout']);
     * $I->clickWithLeftButton(null, 20, 50);
     * $I->clickWithLeftButton(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param null|string|array|WebDriverBy $cssOrXPath css or xpath of the web element (body by default).
     *
     * @throws ElementNotFound
     */
    public function clickWithLeftButton($cssOrXPath = null, int $offsetX = null, int $offsetY = null): void
    {
        $this->moveMouseOver($cssOrXPath, $offsetX, $offsetY);
        $this->webDriver->getMouse()->click();
    }

    /**
     * Performs contextual click with the right mouse button on an element.
     * If the first parameter `null` then the offset is relative to the actual mouse position.
     * If the second and third parameters are given,
     * then the mouse is moved to an offset of the element's top-left corner.
     * Otherwise, the mouse is moved to the center of the element.
     *
     * ``` php
     * <?php
     * $I->clickWithRightButton(['css' => '.checkout']);
     * $I->clickWithRightButton(null, 20, 50);
     * $I->clickWithRightButton(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param null|string|array|WebDriverBy $cssOrXPath css or xpath of the web element (body by default).
     * @throws ElementNotFound
     */
    public function clickWithRightButton($cssOrXPath = null, int $offsetX = null, int $offsetY = null): void
    {
        $this->moveMouseOver($cssOrXPath, $offsetX, $offsetY);
        $this->webDriver->getMouse()->contextClick();
    }


    /**
     * Performs a double click on an element matched by CSS or XPath.
     *
     * @param string|array|WebDriverBy $cssOrXPath
     * @throws ElementNotFound
     */
    public function doubleClick($cssOrXPath): void
    {
        $el = $this->matchFirstOrFail($this->getBaseElement(), $cssOrXPath);
        $this->webDriver->getMouse()->doubleClick($el->getCoordinates());
    }

    /**
     * @param string|array|WebDriverBy $selector
     * @return WebDriverElement[]
     */
    protected function match(WebDriverSearchContext $page, $selector, bool $throwMalformed = true): array
    {
        if (is_array($selector)) {
            try {
                return $page->findElements($this->getStrictLocator($selector));
            } catch (InvalidSelectorException $exception) {
                throw new MalformedLocatorException(key($selector) . ' => ' . reset($selector), "Strict locator");
            } catch (InvalidElementStateException $exception) {
                if ($this->isPhantom() && $exception->getResults()['status'] == 12) {
                    throw new MalformedLocatorException(
                        key($selector) . ' => ' . reset($selector),
                        "Strict locator " . $exception->getCode()
                    );
                }
            }
        }

        if ($selector instanceof WebDriverBy) {
            try {
                return $page->findElements($selector);
            } catch (InvalidSelectorException $exception) {
                throw new MalformedLocatorException(
                    sprintf(
                        "WebDriverBy::%s('%s')",
                        $selector->getMechanism(),
                        $selector->getValue()
                    ),
                    'WebDriver'
                );
            }
        }

        $isValidLocator = false;
        $nodes = [];
        try {
            if (Locator::isID($selector)) {
                $isValidLocator = true;
                $nodes = $page->findElements(WebDriverBy::id(substr($selector, 1)));
            }

            if (Locator::isClass($selector)) {
                $isValidLocator = true;
                $nodes = $page->findElements(WebDriverBy::className(substr($selector, 1)));
            }

            if (empty($nodes) && Locator::isCSS($selector)) {
                $isValidLocator = true;
                try {
                    $nodes = $page->findElements(WebDriverBy::cssSelector($selector));
                } catch (InvalidElementStateException $exception) {
                    $nodes = $page->findElements(WebDriverBy::linkText($selector));
                }
            }

            if (empty($nodes) && Locator::isXPath($selector)) {
                $isValidLocator = true;
                $nodes = $page->findElements(WebDriverBy::xpath($selector));
            }
        } catch (InvalidSelectorException $exception) {
            throw new MalformedLocatorException($selector);
        }

        if (!$isValidLocator && $throwMalformed) {
            throw new MalformedLocatorException($selector);
        }

        return $nodes;
    }

    protected function getStrictLocator(array $by): WebDriverBy
    {
        $type = key($by);
        $locator = $by[$type];
        switch ($type) {
            case 'id':
                return WebDriverBy::id($locator);
            case 'name':
                return WebDriverBy::name($locator);
            case 'css':
                return WebDriverBy::cssSelector($locator);
            case 'xpath':
                return WebDriverBy::xpath($locator);
            case 'link':
                return WebDriverBy::linkText($locator);
            case 'class':
                return WebDriverBy::className($locator);
            default:
                throw new MalformedLocatorException(
                    "{$type} => {$locator}",
                    "Strict locator can be either xpath, css, id, link, class, name: "
                );
        }
    }

    /**
     * @param string|array|WebDriverBy $selector
     * @throws ElementNotFound
     */
    protected function matchFirstOrFail(WebDriverSearchContext $page, $selector): WebDriverElement
    {
        $this->enableImplicitWait();
        $els = $this->match($page, $selector);
        $this->disableImplicitWait();
        if ($els === []) {
            throw new ElementNotFound($selector, "CSS or XPath");
        }

        return reset($els);
    }

    /**
     * Presses the given key on the given element.
     * To specify a character and modifier (e.g. <kbd>Ctrl</kbd>, Alt, Shift, Meta), pass an array for `$char` with
     * the modifier as the first element and the character as the second.
     * For special keys, use the constants from [`Facebook\WebDriver\WebDriverKeys`](https://github.com/php-webdriver/php-webdriver/blob/main/lib/WebDriverKeys.php).
     *
     * ``` php
     * <?php
     * // <input id="page" value="old" />
     * $I->pressKey('#page','a'); // => olda
     * $I->pressKey('#page',array('ctrl','a'),'new'); //=> new
     * $I->pressKey('#page',array('shift','111'),'1','x'); //=> old!!!1x
     * $I->pressKey('descendant-or-self::*[@id='page']','u'); //=> oldu
     * $I->pressKey('#name', array('ctrl', 'a'), \Facebook\WebDriver\WebDriverKeys::DELETE); //=>''
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param array<string|string[]>$chars Can be char or array with modifier. You can provide several chars.
     * @throws ElementNotFound
     */
    public function pressKey($element, ...$chars): void
    {
        $el = $this->matchFirstOrFail($this->getBaseElement(), $element);
        $keys = [];
        foreach ($chars as $char) {
            $keys[] = $this->convertKeyModifier($char);
        }

        $el->sendKeys($keys);
    }

    /**
     * @param string|string[] $char
     * @return string|string[]
     */
    protected function convertKeyModifier($char)
    {
        if (is_string($char)) {
            return $char;
        }

        if (!isset($char[1])) {
            return $char;
        }

        [$modifier, $key] = $char;

        switch ($modifier) {
            case 'ctrl':
            case 'control':
                return [WebDriverKeys::CONTROL, $key];
            case 'alt':
                return [WebDriverKeys::ALT, $key];
            case 'shift':
                return [WebDriverKeys::SHIFT, $key];
            case 'meta':
                return [WebDriverKeys::META, $key];
        }

        return $char;
    }

    protected function assertNodesContain($text, $nodes, $selector = null): void
    {
        $this->assertNodeConstraint($nodes, new WebDriverConstraint($text, $this->_getCurrentUri()), $selector);
    }

    protected function assertNodesNotContain($text, $nodes, $selector = null): void
    {
        $this->assertNodeConstraint($nodes, new WebDriverConstraintNot($text, $this->_getCurrentUri()), $selector);
    }

    protected function assertNodeConstraint($nodes, WebDriverConstraint $constraint, $selector = null): void
    {
        $message = $selector;
        if (is_array($selector)) {
            $type = key($selector);
            $locator = $selector[$type];
            $message = $type . ':' . $locator;
        }

        $this->assertThat($nodes, $constraint, $message);
    }

    protected function assertPageContains($needle, string $message = ''): void
    {
        $this->assertThat(
            htmlspecialchars_decode($this->getVisibleText()),
            new PageConstraint($needle, $this->_getCurrentUri()),
            $message
        );
    }

    protected function assertPageNotContains($needle, string $message = ''): void
    {
        $this->assertThatItsNot(
            htmlspecialchars_decode($this->getVisibleText()),
            new PageConstraint($needle, $this->_getCurrentUri()),
            $message
        );
    }

    protected function assertPageSourceContains($needle, string $message = ''): void
    {
        $this->assertThat(
            $this->webDriver->getPageSource(),
            new PageConstraint($needle, $this->_getCurrentUri()),
            $message
        );
    }

    protected function assertPageSourceNotContains($needle, string $message = ''): void
    {
        $this->assertThatItsNot(
            $this->webDriver->getPageSource(),
            new PageConstraint($needle, $this->_getCurrentUri()),
            $message
        );
    }

    /**
     * Append the given text to the given element.
     * Can also add a selection to a select box.
     *
     * ``` php
     * <?php
     * $I->appendField('#mySelectbox', 'SelectValue');
     * $I->appendField('#myTextField', 'appended');
     * ```
     *
     * @param string|array|WebDriverBy $field
     * @param string $value
     * @throws ElementNotFound
     */
    public function appendField($field, string $value): void
    {
        $el = $this->findField($field);

        switch ($el->getTagName()) {
            //Multiple select
            case "select":
                $matched = false;
                $wdSelect = new WebDriverSelect($el);
                try {
                    $wdSelect->selectByVisibleText($value);
                    $matched = true;
                } catch (NoSuchElementException $e) {
                    // exception treated at the end
                }

                try {
                    $wdSelect->selectByValue($value);
                    $matched = true;
                } catch (NoSuchElementException $e) {
                    // exception treated at the end
                }

                if ($matched) {
                    return;
                }

                throw new ElementNotFound(
                    json_encode($value, JSON_THROW_ON_ERROR),
                    "Option inside {$field} matched by name or value"
                );
            case "textarea":
                $el->sendKeys($value);
                return;
            case "div": //allows for content editable divs
                $el->sendKeys(WebDriverKeys::END);
                $el->sendKeys($value);
                return;
            //Text, Checkbox, Radio
            case "input":
                $type = $el->getAttribute('type');

                if ($type == 'checkbox') {
                    //Find by value or css,id,xpath
                    $field = $this->findCheckable($this->getBaseElement(), $value, true);
                    if (!$field) {
                        throw new ElementNotFound($value, "Checkbox or Radio by Label or CSS or XPath");
                    }

                    if ($field->isSelected()) {
                        return;
                    }

                    $field->click();
                    return;
                } elseif ($type == 'radio') {
                    $this->selectOption($field, $value);
                    return;
                }

                $el->sendKeys($value);
                return;
        }

        throw new ElementNotFound($field, "Field by name, label, CSS or XPath");
    }

    /**
     * @param string|array|WebDriverBy $selector
     */
    protected function matchVisible($selector): array
    {
        $els = $this->match($this->getBaseElement(), $selector);
        return array_filter(
            $els,
            fn(WebDriverElement $el): bool => $el->isDisplayed()
        );
    }

    /**
     * @param string|array|WebDriverBy $selector
     * @throws InvalidArgumentException
     */
    protected function getLocator($selector): WebDriverBy
    {
        if ($selector instanceof WebDriverBy) {
            return $selector;
        }

        if (is_array($selector)) {
            return $this->getStrictLocator($selector);
        }

        if (Locator::isID($selector)) {
            return WebDriverBy::id(substr($selector, 1));
        }

        if (Locator::isCSS($selector)) {
            return WebDriverBy::cssSelector($selector);
        }

        if (Locator::isXPath($selector)) {
            return WebDriverBy::xpath($selector);
        }

        throw new InvalidArgumentException("Only CSS or XPath allowed");
    }

    public function saveSessionSnapshot($name)
    {
        $this->sessionSnapshots[$name] = [];

        foreach ($this->webDriver->manage()->getCookies() as $cookie) {
            if (in_array(trim($cookie['name']), [LocalServer::COVERAGE_COOKIE, LocalServer::COVERAGE_COOKIE_ERROR])) {
                continue;
            }

            if ($this->cookieDomainMatchesConfigUrl($cookie)) {
                $this->sessionSnapshots[$name][] = $cookie;
            }
        }

        $this->debugSection('Snapshot', sprintf('Saved "%s" session snapshot', $name));
    }

    public function loadSessionSnapshot($name, bool $showDebug = true): bool
    {
        if (!isset($this->sessionSnapshots[$name])) {
            return false;
        }

        foreach ($this->webDriver->manage()->getCookies() as $cookie) {
            if (in_array(trim($cookie['name']), [LocalServer::COVERAGE_COOKIE, LocalServer::COVERAGE_COOKIE_ERROR])) {
                continue;
            }

            $this->webDriver->manage()->deleteCookieNamed($cookie['name']);
        }

        foreach ($this->sessionSnapshots[$name] as $cookie) {
            $this->setCookie($cookie['name'], $cookie['value'], (array)$cookie, false);
        }

        if ($showDebug) {
            $this->debugCookies();
        }
        $this->debugSection('Snapshot', sprintf('Restored "%s" session snapshot', $name));
        return true;
    }

    public function deleteSessionSnapshot($name)
    {
        if (isset($this->sessionSnapshots[$name])) {
            unset($this->sessionSnapshots[$name]);
        }

        $this->debugSection('Snapshot', sprintf('Deleted "%s" session snapshot', $name));
    }

    /**
     * Check if the cookie domain matches the config URL.
     *
     * Taken from Guzzle\Cookie\SetCookie
     */
    private function cookieDomainMatchesConfigUrl(array|WebDriverCookie $cookie): bool
    {
        if (!isset($cookie['domain'])) {
            return true;
        }

        $domain = parse_url($this->config['url'], PHP_URL_HOST);

        // Remove the leading '.' as per spec in RFC 6265.
        // https://tools.ietf.org/html/rfc6265#section-5.2.3
        $cookieDomain = ltrim($cookie['domain'], '.');
        // Domain not set or exact match.
        if (!$cookieDomain || !strcasecmp($domain, $cookieDomain)) {
            return true;
        }

        // Matching the subdomain according to RFC 6265.
        // https://tools.ietf.org/html/rfc6265#section-5.1.3
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return false;
        }

        return (bool) preg_match('/\.' . preg_quote($cookieDomain, '/') . '$/', $domain);
    }

    protected function isPhantom(): bool
    {
        return strpos($this->config['browser'], 'phantom') === 0;
    }

    /**
     * Move to the middle of the given element matched by the given locator.
     * Extra shift, calculated from the top-left corner of the element,
     * can be set by passing $offsetX and $offsetY parameters.
     *
     * ``` php
     * <?php
     * $I->scrollTo(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param string|array|WebDriverBy $selector
     */
    public function scrollTo($selector, int $offsetX = null, int $offsetY = null): void
    {
        $el = $this->matchFirstOrFail($this->getBaseElement(), $selector);
        $x = $el->getLocation()->getX() + $offsetX;
        $y = $el->getLocation()->getY() + $offsetY;
        $this->webDriver->executeScript(sprintf('window.scrollTo(%d, %d)', $x, $y));
    }

    /**
     * Opens a new browser tab and switches to it.
     *
     * ```php
     * <?php
     * $I->openNewTab();
     * ```
     * The tab is opened with JavaScript's `window.open()`, which means:
     * * Some ad-blockers might restrict it.
     * * The sessionStorage is copied to the new tab (contrary to a tab that was manually opened by the user)
     */
    public function openNewTab(): void
    {
        $this->executeJS("window.open('about:blank','_blank');");
        $this->switchToNextTab();
    }

    /**
     * Checks current number of opened tabs
     *
     * ```php
     * <?php
     * $I->seeNumberOfTabs(2);
     * ```
     */
    public function seeNumberOfTabs(int $number): void
    {
        $this->assertCount($number, $this->webDriver->getWindowHandles());
    }

    /**
     * Closes current browser tab and switches to previous active tab.
     *
     * ```php
     * <?php
     * $I->closeTab();
     * ```
     */
    public function closeTab(): void
    {
        $currentTab = $this->webDriver->getWindowHandle();
        $prevTab = $this->getRelativeTabHandle(-1);
        if ($prevTab === $currentTab) {
            throw new ModuleException($this, 'Will not close the last open tab');
        }
        $this->webDriver->close();
        $this->webDriver->switchTo()->window($prevTab);
    }

    /**
     * Switches to next browser tab.
     * An offset can be specified.
     *
     * ```php
     * <?php
     * // switch to next tab
     * $I->switchToNextTab();
     * // switch to 2nd next tab
     * $I->switchToNextTab(2);
     * ```
     */
    public function switchToNextTab(int $offset = 1): void
    {
        $tab = $this->getRelativeTabHandle($offset);
        $this->webDriver->switchTo()->window($tab);
    }

    /**
     * Switches to previous browser tab.
     * An offset can be specified.
     *
     * ```php
     * <?php
     * // switch to previous tab
     * $I->switchToPreviousTab();
     * // switch to 2nd previous tab
     * $I->switchToPreviousTab(2);
     * ```
     */
    public function switchToPreviousTab(int $offset = 1): void
    {
        $this->switchToNextTab(0 - $offset);
    }

    protected function getRelativeTabHandle($offset)
    {
        if ($this->isPhantom()) {
            throw new ModuleException($this, "PhantomJS doesn't support tab actions");
        }

        $handle = $this->webDriver->getWindowHandle();
        $handles = $this->webDriver->getWindowHandles();
        $currentHandleIdx = array_search($handle, $handles);
        $newHandleIdx = ($currentHandleIdx + $offset) % count($handles);
        if ($newHandleIdx < 0) {
            $newHandleIdx = count($handles) + $newHandleIdx;
        }
        return $handles[$newHandleIdx];
    }

    /**
     * Waits for element and runs a sequence of actions inside its context.
     * Actions can be defined with array, callback, or `Codeception\Util\ActionSequence` instance.
     *
     * Actions as array are recommended for simple to combine "waitForElement" with assertions;
     * `waitForElement($el)` and `see('text', $el)` can be simplified to:
     *
     * ```php
     * <?php
     * $I->performOn($el, ['see' => 'text']);
     * ```
     *
     * List of actions can be pragmatically build using `Codeception\Util\ActionSequence`:
     *
     * ```php
     * <?php
     * $I->performOn('.model', ActionSequence::build()
     *     ->see('Warning')
     *     ->see('Are you sure you want to delete this?')
     *     ->click('Yes')
     * );
     * ```
     *
     * Actions executed from array or ActionSequence will print debug output for actions, and adds an action name to
     * exception on failure.
     *
     * Whenever you need to define more actions a callback can be used. A WebDriver module is passed for argument:
     *
     * ```php
     * <?php
     * $I->performOn('.rememberMe', function (WebDriver $I) {
     *      $I->see('Remember me next time');
     *      $I->seeElement('#LoginForm_rememberMe');
     *      $I->dontSee('Login');
     * });
     * ```
     *
     * In 3rd argument you can set number a seconds to wait for element to appear
     *
     * @param string|array|WebDriverBy $element
     * @param callable|array|ActionSequence $actions
     */
    public function performOn($element, $actions, int $timeout = 10): void
    {
        $this->waitForElement($element, $timeout);
        $this->setBaseElement($element);
        $this->debugSection('InnerText', $this->getBaseElement()->getText());

        if (is_callable($actions)) {
            $actions($this);
            $this->setBaseElement();
            return;
        }

        if (is_array($actions)) {
            $actions = ActionSequence::build()->fromArray($actions);
        }

        if (!$actions instanceof ActionSequence) {
            throw new InvalidArgumentException("2nd parameter, actions should be callback, ActionSequence or array");
        }

        $actions->run($this);
        $this->setBaseElement();
    }

    /**
     * @param string|array|WebDriverBy $element
     */
    protected function setBaseElement($element = null): void
    {
        if ($element === null) {
            $this->baseElement = $this->webDriver;
            return;
        }

        $this->baseElement = $this->matchFirstOrFail($this->webDriver, $element);
    }

    protected function enableImplicitWait(): void
    {
        if (!$this->config['wait']) {
            return;
        }

        $this->webDriver->manage()->timeouts()->implicitlyWait($this->config['wait']);
    }

    protected function disableImplicitWait(): void
    {
        if (!$this->config['wait']) {
            return;
        }

        $this->webDriver->manage()->timeouts()->implicitlyWait(0);
    }

    /**
     * From symfony/dom-crawler
     *
     * Converts string for XPath expressions.
     *
     * Escaped characters are: quotes (") and apostrophe (').
     *
     *  Examples:
     *
     *     echo self::xPathLiteral('foo " bar');
     *     //prints 'foo " bar'
     *
     *     echo self::xPathLiteral("foo ' bar");
     *     //prints "foo ' bar"
     *
     *     echo self::xPathLiteral('a\'b"c');
     *     //prints concat('a', "'", 'b"c')
     *
     * @return string Converted string
     */
    private static function xPathLiteral($s): string
    {
        if (false === strpos($s, "'")) {
            return sprintf("'%s'", $s);
        }

        if (false === strpos($s, '"')) {
            return sprintf('"%s"', $s);
        }

        $string = $s;
        $parts = [];
        while (true) {
            if (false !== $pos = strpos($string, "'")) {
                $parts[] = sprintf("'%s'", substr($string, 0, $pos));
                $parts[] = "\"'\"";
                $string = substr($string, $pos + 1);
            } else {
                $parts[] = "'{$string}'";
                break;
            }
        }

        return sprintf('concat(%s)', implode(', ', $parts));
    }
}
