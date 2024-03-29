<?php

namespace Tests\Unit\Codeception\Constraints;

use Facebook\WebDriver\Remote\RemoteWebElement;

class TestedWebElement extends RemoteWebElement
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getTagName()
    {
        return 'p';
    }
    public function getText()
    {
        return $this->value;
    }

    public function isDisplayed()
    {
        return true;
    }
}
