<?php

declare(strict_types=1);

namespace Tests\Unit\Codeception\Constraints;

use Codeception\Constraint\WebDriver;
use Codeception\PHPUnit\TestCase;

class WebDriverConstraintTest extends TestCase
{
    protected ?WebDriver $constraint = null;

    public function _setUp()
    {
        $this->constraint = new WebDriver('hello', '/user');
    }

    public function testEvaluation()
    {
        $nodes = array(new TestedWebElement('Hello world'), new TestedWebElement('Bye world'));
        $this->constraint->evaluate($nodes);
    }

    public function testFailMessageResponseWithStringSelector()
    {
        $nodes = array(new TestedWebElement('Bye warcraft'), new TestedWebElement('Bye world'));
        try {
            $this->constraint->evaluate($nodes, 'selector');
        } catch (\PHPUnit\Framework\AssertionFailedError $fail) {
            $this->assertStringContainsString(
                "Failed asserting that any element by 'selector' on page /user",
                $fail->getMessage()
            );
            $this->assertStringContainsString('+ <p> Bye world', $fail->getMessage());
            $this->assertStringContainsString('+ <p> Bye warcraft', $fail->getMessage());
            return;
        }
        $this->fail("should have failed, but not");
    }

    public function testFailMessageResponseWhenMoreNodes()
    {
        $nodes = array();
        for ($i = 0; $i < 15; $i++) {
            $nodes[] = new TestedWebElement("item $i");
        }
        try {
            $this->constraint->evaluate($nodes, 'selector');
        } catch (\PHPUnit\Framework\AssertionFailedError $fail) {
            $this->assertStringContainsString(
                "Failed asserting that any element by 'selector' on page /user",
                $fail->getMessage()
            );
            $this->assertStringNotContainsString('+ <p> item 0', $fail->getMessage());
            $this->assertStringNotContainsString('+ <p> item 14', $fail->getMessage());
            $this->assertStringContainsString('[total 15 elements]', $fail->getMessage());
            return;
        }
        $this->fail("should have failed, but not");
    }

    public function testFailMessageResponseWithoutUrl()
    {
        $this->constraint = new WebDriver('hello');
        $nodes = array(new TestedWebElement('Bye warcraft'), new TestedWebElement('Bye world'));
        try {
            $this->constraint->evaluate($nodes, 'selector');
        } catch (\PHPUnit\Framework\AssertionFailedError $fail) {
            $this->assertStringContainsString("Failed asserting that any element by 'selector'", $fail->getMessage());
            $this->assertStringNotContainsString("Failed asserting that any element by 'selector' on page", $fail->getMessage());
            return;
        }
        $this->fail("should have failed, but not");
    }
}
