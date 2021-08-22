## [1.3.0](https://github.com/Codeception/module-webdriver/releases/tag/1.3.0)
Add new option webdriver_proxy to tunnel requests to the remote WebDriver server

## [1.2.2](https://github.com/Codeception/module-webdriver/releases/tag/1.2.č)
Documentation update

## [1.2.1](https://github.com/Codeception/module-webdriver/releases/tag/1.2.1)
Merge pull request [#65](https://github.com/Codeception/module-webdriver/pull/65) from ThomasLandauer/patch-1
Added Scenario Metadata

## [1.2.0](https://github.com/Codeception/module-webdriver/releases/tag/1.2.0)
Merge pull request [#35](https://github.com/Codeception/module-webdriver/pull/35) from krumedia/element-screenshot
Support for taking screenshots of elements

## [1.1.4](https://github.com/Codeception/module-webdriver/releases/tag/1.1.4)
Merge branch 'ThomasLandauer-patch-1'

## [1.1.3](https://github.com/Codeception/module-webdriver/releases/tag/1.1.3)
Merge pull request [#27](https://github.com/Codeception/module-webdriver/pull/27) from Codeception/php8
Support PHP 8

## [1.1.2](https://github.com/Codeception/module-webdriver/releases/tag/1.1.2)
Documented behaviour of openNewTab
Explaining weird behavior of sessionStorage, see https://stackoverflow.com/q/20879714/1668200

## [1.1.1](https://github.com/Codeception/module-webdriver/releases/tag/1.1.1)
Merge pull request [#17](https://github.com/Codeception/module-webdriver/pull/17) from takaoyuri/patch1
Add support for saving multi byte filename

## [1.1.0](https://github.com/Codeception/module-webdriver/releases/tag/1.1.0)
Make switch to frame possible ([#9](https://github.com/Codeception/module-webdriver/pull/9))
* Make switch to frame possible
* Fix tag name in debug information
* Correct description of switchToIframe
Co-authored-by: Gintautas Miselis <gintautas@miselis.lt>

## [1.0.8](https://github.com/Codeception/module-webdriver/releases/tag/1.0.8)
Suppress UnknownErrorException in _closeSession

## [1.0.7](https://github.com/Codeception/module-webdriver/releases/tag/1.0.7)
[switchToIFrame] Undefined variable: els ([#12](https://github.com/Codeception/module-webdriver/pull/12))

## [1.0.6](https://github.com/Codeception/module-webdriver/releases/tag/1.0.6)
Merge pull request [#10](https://github.com/Codeception/module-webdriver/pull/10) from Codeception/fix-set-cookie
setCookie: don't add domain to cookie unless explicitly specified.

## [1.0.5](https://github.com/Codeception/module-webdriver/releases/tag/1.0.5)
Merge pull request [#7](https://github.com/Codeception/module-webdriver/pull/7) from bassrock/fix-php56
Removing string typehintb

## [1.0.4](https://github.com/Codeception/module-webdriver/releases/tag/1.0.4)
Fixed docblock in TestsForWeb

## [1.0.3](https://github.com/Codeception/module-webdriver/releases/tag/1.0.3)
Fix cookie domain match ([#5](https://github.com/Codeception/module-webdriver/pull/5))
* stub webdriver cookies as objects over arrays
* check for cookie domain with isset, not array_key_exists
calling array_key_exists on classes implementing \ArrayAccess always 
returns false and emits a deprecation notice on 7.4

## [1.0.2](https://github.com/Codeception/module-webdriver/releases/tag/1.0.2)
Merge pull request [#3](https://github.com/Codeception/module-webdriver/pull/3) from Codeception/DavertMik-patch-1
Changed default values for webdriver logs

## [1.0.1](https://github.com/Codeception/module-webdriver/releases/tag/1.0.1)
Merge pull request [#1](https://github.com/Codeception/module-webdriver/pull/1) from OndraM/php-webdriver
Rename php-webdriver package

## [1.0.0](https://github.com/Codeception/module-webdriver/releases/tag/1.0.0)
display cookie details in debug output (#5709)
