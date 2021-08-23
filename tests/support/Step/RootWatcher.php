<?php
namespace Step;

class RootWatcher extends \AcceptanceTester
{
    public function seeInRootPage($message)
    {
        $I = $this;
        $I->see($message);
    }
}