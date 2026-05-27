<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

class ViewerAuthorizationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.viewer.enabled', true);
    }

    public function test_forbidden_outside_local_without_a_gate(): void
    {
        // Env is "testing" (not local) and no viewFlow gate is defined.
        $this->get('/flow')->assertForbidden();
    }
}
