<?php

namespace Step;

class RootWatcher extends \WebGuy
{
    public function seeInRootPage($message)
    {
        $I = $this;
        $I->amOnPage('/');
        $I->see($message);
    }
}
